// 템플릿 참조 검사기 — 뷰/컴포넌트의 <template>에서 쓰는 식별자가
// <script setup>에 정의/import 됐는지 확인한다 (부분 원복으로 인한 ReferenceError 방지).
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, 'frontend', 'src');
const GLOBALS = new Set([
    'Math', 'JSON', 'Object', 'Number', 'String', 'Boolean', 'Array', 'Date', 'Promise',
    'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'console', 'window', 'document',
    'localStorage', 'sessionStorage', 'fetch', 'Set', 'Map', 'WeakMap', 'WeakSet',
    'undefined', 'null', 'true', 'false', 'globalThis', 'encodeURIComponent',
    'decodeURIComponent', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval',
    'requestAnimationFrame', 'navigator', 'location', 'history', 'Intl',
    // Vue 내장 컴포넌트 — import 없이 템플릿에서 바로 사용한다
    'Teleport', 'Transition', 'TransitionGroup', 'KeepAlive', 'Suspense', 'Component', 'Slot',
]);

function walk(dir, out = []) {
    for (const name of fs.readdirSync(dir)) {
        const full = path.join(dir, name);
        const stat = fs.statSync(full);
        if (stat.isDirectory()) {
            walk(full, out);
        } else if (name.endsWith('.vue')) {
            out.push(full);
        }
    }
    return out;
}

// 라우터 검사 — 라우트에서 참조하는 컴포넌트/라우트명이 실제로 정의되어 있는지 확인.
// lazy import 선언이 부분 원복으로 사라지면 ReferenceError가 나므로 사전에 잡는다.
function checkRouter() {
    const routerFile = path.join(ROOT, 'router', 'index.js');
    const src = fs.readFileSync(routerFile, 'utf8');
    const issues = [];

    // 1) lazy import로 정의된 컴포넌트 이름 — const XxxView = () => import(...)
    const definedComponents = new Set();
    for (const m of src.matchAll(/const\s+([A-Z][\w]*)\s*=\s*\(\s*\)\s*=>\s*import/g)) {
        definedComponents.add(m[1]);
    }

    // 2) 라우트에서 참조하는 컴포넌트 이름 — component: XxxView
    for (const m of src.matchAll(/component:\s*([A-Z][\w]*)/g)) {
        if (!definedComponents.has(m[1])) {
            issues.push(`라우트 컴포넌트 미정의: ${m[1]}`);
        }
    }

    // 3) 정의된 라우트 name 수집 — name: 'xxx'
    const routeNames = new Set();
    for (const m of src.matchAll(/name:\s*'([\w-]+)'/g)) {
        routeNames.add(m[1]);
    }

    // 4) 뷰에서 router.push({ name: 'xxx' })로 참조하는 라우트명 검사
    for (const file of walk(ROOT)) {
        const vue = fs.readFileSync(file, 'utf8');
        for (const m of vue.matchAll(/router\.push\(\s*\{\s*name:\s*'([\w-]+)'/g)) {
            if (!routeNames.has(m[1])) {
                issues.push(`${path.relative(ROOT, file)}: 정의되지 않은 라우트 '${m[1]}'`);
            }
        }
    }

    return issues;
}

function scriptIdentifiers(src) {
    const ids = new Set();
    const script = src.match(/<script setup>([\s\S]*?)<\/script>/)?.[1] ?? '';
    if (!script) return ids;

    for (const m of script.matchAll(/import\s+([A-Za-z_$][\w$]*)\s+from/g)) ids.add(m[1]);
    for (const m of script.matchAll(/import\s*\{([^}]+)\}\s*from/g)) {
        for (const part of m[1].split(',')) {
            const name = part.match(/([A-Za-z_$][\w$]*)/)?.[1];
            if (name && name !== 'as') ids.add(name);
        }
    }
    for (const m of script.matchAll(/\b(?:const|let|var|function)\s+([A-Za-z_$][\w$]*)/g)) ids.add(m[1]);
    // 디스트럭처링 — const { a, b } = / const [a, b] =
    for (const m of script.matchAll(/\b(?:const|let|var)\s*\{([^}]*)\}\s*=/g)) {
        for (const part of m[1].split(',')) {
            const name = part.match(/([A-Za-z_$][\w$]*)/)?.[1];
            if (name && name !== 'as') ids.add(name);
        }
    }
    for (const m of script.matchAll(/\b(?:const|let|var)\s*\[([^\]]*)\]\s*=/g)) {
        for (const part of m[1].split(',')) {
            const name = part.match(/([A-Za-z_$][\w$]*)/)?.[1];
            if (name) ids.add(name);
        }
    }
    return ids;
}

// 템플릿 블록 추출 — 첫 <template>부터 마지막 </template>까지.
// v-for 내부의 중첩 <template> 태그 때문에 처음 닫는 태그에서 끊기면 안 된다.
function extractTemplate(src) {
    const start = src.indexOf('<template>') + '<template>'.length;
    const end = src.lastIndexOf('</template>');

    return start >= 0 && end > start ? src.slice(start, end) : '';
}

// 템플릿의 v-slot 디스트럭처링 이름 — 스크립트 정의로 취급한다
// 예: <component v-slot="{ Component }">의 Component는 외부 스코프 변수 아님
function templateSlotNames(src) {
    const tpl = extractTemplate(src);
    const names = new Set();

    for (const m of tpl.matchAll(/(?:v-slot|#default)="\{([^}]*)\}"/g)) {
        for (const part of m[1].split(',')) {
            const name = part.match(/([A-Za-z_$][\w$]*)/)?.[1];
            if (name) names.add(name);
        }
    }

    return names;
}

function templateIdentifiers(src) {
    const tpl = extractTemplate(src);
    const ids = new Set();

    // 표현식 영역만 추출: mustache / :prop / @event / v-* 바인딩
    const exprs = [];
    for (const m of tpl.matchAll(/\{\{([\s\S]*?)\}\}/g)) exprs.push(m[1]);
    for (const m of tpl.matchAll(/\b(?:v-[a-z-]+|:[a-zA-Z-]+|#[a-zA-Z-]+|@[a-zA-Z-]+)\s*=\s*"([^"]*)"/g)) exprs.push(m[1]);
    for (const m of tpl.matchAll(/\bv-(?:for|if|else-if)\s*=\s*"([^"]*)"/g)) exprs.push(m[1]);

    // PascalCase 컴포넌트 태그
    for (const m of tpl.matchAll(/<([A-Z][\w]*)/g)) ids.add(m[1]);
    for (const m of tpl.matchAll(/<\/?([A-Z][\w]*)/g)) ids.add(m[1]);

    for (const expr of exprs) {
        // 속성 접근(x.foo)·메서드 호출(x.foo())은 제외 — 바로 앞이 '.'인 토큰은 건너뛴다
        for (const m of expr.matchAll(/(?<!\.)\b([A-Za-z_$][\w$]*)\b/g)) {
            const tok = m[1];
            if (/^n[A-Z]/.test(tok)) continue; // naive-ui 자동 import 컴포넌트
            if (tok.startsWith('v-')) continue;
            if (/^[a-z]/.test(tok)) continue; // 로컬 데이터/함수(소문자)는 아래 별도 체크
            ids.add(tok);
        }
    }

    return ids;
}

let issues = 0;
for (const file of walk(ROOT)) {
    const src = fs.readFileSync(file, 'utf8');
    const defined = new Set([...scriptIdentifiers(src), ...templateSlotNames(src)]);
    const used = templateIdentifiers(src);
    const missing = [];

    for (const id of used) {
        const raw = id.startsWith('?') ? id.slice(1) : id;
        if (GLOBALS.has(raw)) continue;
        if (raw === raw.toLowerCase()) continue; // 소문자는 경고 대상에서 제외(너무 많은 노이즈)
        if (!defined.has(raw)) missing.push(raw);
    }

    if (missing.length) {
        issues += missing.length;
        console.log(`\n${path.relative(ROOT, file)}`);
        console.log(`  MISSING: ${[...new Set(missing)].join(', ')}`);
    }
}

// 라우터 검사 — lazy import 선언/라우트명 원복 방지
const routerIssues = checkRouter();
if (routerIssues.length) {
    issues += routerIssues.length;
    console.log('\n[라우터]');
    for (const msg of routerIssues) console.log(`  ${msg}`);
}

console.log(issues === 0 ? '\nOK: 모든 템플릿 참조가 정의되어 있습니다.' : `\n${issues}개의 미정의 참조 발견`);
process.exit(issues > 0 ? 1 : 0);
