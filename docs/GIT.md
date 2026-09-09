# GIT

> 상태: ✅ 현행 — 실제 워크플로우 기준 (단일 main 브랜치, 로컬 우선 개발, tar 기반 배포).

## 브랜치 전략
- **단일 `main` 브랜치** — 로컬 개발 → 검증 → 직접 커밋한다. (feature/fix 브랜치·PR을 사용하지 않음)
- 원격은 `origin/main` 하나만 운영한다.

## 커밋 원칙
- 한 커밋은 하나의 목적만 담는다.
- 의미 있는 단위로 자주 커밋한다.
- 커밋 메시지는 컨벤셔널 프리픽스를 사용한다.
  - `feat:` 기능 추가
  - `fix:` 버그 수정
  - `docs:` 문서 작업
  - `chore:` 기타 정리·설정
- 예시
  - `feat: add login flow`
  - `fix: resolve blade layout component path`
  - `docs: add project documents`

## 커밋 금지 대상
- 민감 정보(`.env`, SSH 키, 테스트 계정·API 키, `SECRETS.md`) — 로컬 전용으로만 보관
- 프론트 빌드본(`public/assets`·`public/index.html`·`public/build`, `frontend/dist`)

## 서버 반영 (Git 사용 안 함)
- 서버 배포·동기화는 **Git을 사용하지 않는다** (git 기반 서버 동기화·`git reset --hard` 금지).
- `.deploy/deploy_all.ps1` 통합 스크립트로 **tar 기반 업로드** 후 서버 `.deploy/deploy_all.sh`가 해제·마이그레이션·캐시 정리·검증까지 수행한다.
- 상세: [DEPLOY.md](./DEPLOY.md) · [RULES.md](./RULES.md) `배포·운영 규칙` 참조.

## 작업 흐름
1. 로컬에서 기능 개발 (vite dev `localhost:5174` + `php artisan serve`)
2. 로컬 검증 후 커밋
3. 사용자가 명시적으로 요청할 때만 배포 스크립트로 서버 반영
