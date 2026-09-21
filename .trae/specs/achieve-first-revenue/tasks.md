# Tasks

> 코드 구현(수수료 정책·수금 원장·출금 게이트·등록자/관리자 화면·매출 지표)은 이미 완료되어 로컬 테스트 385건으로 검증됨.
> 남은 작업은 **실제 첫 수익을 발생시키는 운영·배포 단계**다.

- [x] Task 1: 매입 계좌 설정
  - [x] SubTask 1.1: 사용자로부터 은행명/계좌번호/예금주 확보 (NH농협 / 3010374548781 / JINLONG)
  - [x] SubTask 1.2: 로컬 `.env`에 `SETTLEMENT_PLATFORM_BANK_NAME/NUMBER/HOLDER` 반영
  - [x] SubTask 1.3: 서버 `/var/www/nowhere/.env`에 동일 값 반영

- [x] Task 2: 서버 배포 + DB 마이그레이션
  - [x] SubTask 2.1: 프론트 빌드(`npm run build`) 및 배포
  - [x] SubTask 2.2: 백엔드 변경 파일 + 마이그레이션(08-30~09-10 전 구간) 반영
    - [x] `settlements.fee_rate`(요율 스냅샷)
    - [x] `users.fee_rate`(등록자 개별 요율)
    - [x] `settlements.collection_*`(수금 컬럼)
  - [x] SubTask 2.3: `migrate --force` + 캐시/라우트/뷰 클리어 + php-fpm 리로드
  - [x] SubTask 2.4: `/up` 헬스 체크 및 SPA 정상 서빙 검증 (up 200 / root 200)

- [x] Task 3: 첫 수익 경로 운영 검증 (리허설)
  - [x] SubTask 3.1: 신규 등록자 가입(Customer) → 운행 등록 → 셀프 공개 → 마켓 노출 확인
  - [x] SubTask 3.2: 기사 가입(Driver) → 가져오기 신청 → 등록자 승인 → 운행 시작 → 완료(실제금액 100,000)
  - [x] SubTask 3.3: 유예(24h) 경과 후 `orders:auto-settle` → 정산 원장 생성 확인 (gross 100,000 / fee 5,000 / net 95,000)
  - [x] SubTask 3.4: 관리자 `POST /admin/settlements/{id}/collect` → `collection_status=paid` 확인
  - [x] SubTask 3.5: 운영 지표 '수수료 매출'(`month_fee`)에 5,000 반영 확인
  - [x] SubTask 3.6: 검증 스크립트 `.deploy/rehearse_first_revenue.sh` (19단계 전부 통과) — 생성한 재무 기록은 삭제해 지표 원복
  - 남은 것: 실제 등록자·기사가 만든 **실제 운행 1건**뿐 (경로 자체는 이상 없음이 확인됨)

- [x] Task 4: 홍보 표면(공개 랜딩·공유 카드) 구축 — 첫 수익의 유입 전제
  - [x] SubTask 4.1: 비로그인 랜딩 화면 `LandingView.vue` 신규 (`/welcome`)
    - 가치 제안·추천 카드 예시(조건% + 근거)·5단계 흐름·기사/등록자 CTA
  - [x] SubTask 4.2: 게스트 라우팅을 로그인 폼 → 랜딩으로 변경 (`router/index.js`)
    - 홍보 링크 진입 시 곧바로 로그인 화면을 만나 이탈하는 문제 제거
  - [x] SubTask 4.3: 공유 카드(OG) 이미지 생성 — `.deploy/make_og.php` → `frontend/public/og-cover.jpg`(1200×630)
  - [x] SubTask 4.4: 공유·검색 메타 보강 (`frontend/index.html`) — og:image·twitter summary_large_image·description
  - [x] SubTask 4.5: `robots.txt` 추가
  - [x] SubTask 4.6: 프론트 빌드·테스트(63 pass)·배포 및 서버 서빙 검증

- [x] Task 5: 운행 공유 링크 — 등록자가 외부에 직접 뿌려 기사를 모으는 유입 장치
  - [x] SubTask 5.1: `orders.share_token`(추측 불가 토큰) 마이그레이션 + `Order::ensureShareToken()`
  - [x] SubTask 5.2: 비로그인 공개 조회 API `GET /api/public/orders/{token}` — 고객 실명·연락처·운행번호·내부 id 미노출
  - [x] SubTask 5.3: 공유 링크 발급 API `POST /orders/{order}/share` (등록자만)
  - [x] SubTask 5.4: `/s/order/{token}` OG 미리보기 (Laravel 렌더 + nginx 라우팅) — 카카오톡·SNS 카드
  - [x] SubTask 5.5: SPA 공개 화면 `/share/order/{token}` + 로그인 없이 접근
  - [x] SubTask 5.6: 운행 상세에 '공유' 버튼 (HTTPS 아닌 환경 대비 클립보드 폴백 포함)
  - [x] SubTask 5.7: 테스트 8건 + 전체 360건 통과 · 빌드 · 배포 · 서버 검증

- [x] Task 6: 서버 드리프트 복구 — 운영에 반영되지 않았던 코드 동기화
  - 원인: 배포가 파일 단위 수동 반영이라 서버에 컨트롤러 6개가 누락된 상태였고, `PasswordResetController` 누락으로 `route:list` 자체가 실패했다(비밀번호 찾기·신고·관리자 운영·행동 로그가 운영에서 동작 불가).
  - 조치: 로컬 소스 기준으로 `app/`·`routes/`·`config/`·`resources/views/`·`database/migrations/`·`bootstrap/` 동기화 + `composer dump-autoload -o` + 캐시 정리 + php-fpm 리로드. 배포 전 백업(`/var/www/backups/app-before-sync-*.tar.gz`) 확보.

- [x] Task 7: 공개 임시주소 재발급 + API 오류 응답 계약 정리
  - [x] SubTask 7.1: `cloudflared-quick` 재시작으로 임시주소 재발급 → `.env` `APP_URL`/`FRONTEND_URL` 갱신 + `config:cache`
  - [x] SubTask 7.2: 비로그인 API 요청이 500이 되던 문제 수정 — 기본 `redirectGuestsTo`가 즉시 `route('login')`을 평가하는데 login 라우트가 없었음 → `bootstrap/app.php`에서 API는 리다이렉트하지 않도록 변경(401 JSON)
  - [x] SubTask 7.3: 존재하지 않는 `/api/*` 경로가 SPA `index.html`(200)로 감싸지던 문제 수정 → 404 JSON (`routes/web.php` fallback)
  - [x] SubTask 7.4: `errors/404.blade.php`를 독립 HTML로 재작성 (삭제된 `<x-layouts.app>`·없는 `route('dashboard')` 참조 제거)
  - [x] SubTask 7.5: nginx `X-Forwarded-Proto $scheme` 덮어쓰기 수정 → 업스트림(터널) https 스킴 보존. 공유 카드 `og:image`/`og:url`이 https 절대 URL로 출력됨을 확인
  - [x] SubTask 7.6: 테스트 3건 추가(401 JSON·404 JSON) + 전체 363건 통과 · pint · 배포 · 서버 검증

- [x] Task 8: 내 마켓 목록에서 바로 공유 (2탭 → 1탭)
  - 배경: 공유는 상세 화면에만 있어 등록자가 공개 운행을 뿌리려면 목록 → 상세 → 공유 2번 이동해야 했다. 홍보 동선의 마찰을 줄인다.
  - [x] SubTask 8.1: `OrderCard`에 `shareable` prop + `share` emit 추가. 공유 버튼은 `내가 등록(userId 일치) + 공개(published) + 일반 목록`일 때만 노출 — 남에게 넘긴 운행은 공유 권한이 없어 제외
  - [x] SubTask 8.2: 태그·찜 바를 `order-card__actions`로 묶어 공유·찜을 카드 클릭(상세 이동)과 분리 (`@click.stop.prevent`)
  - [x] SubTask 8.3: `share` 아이콘(`ShareSocialOutline`) 등록 (`utils/icons.js`)
  - [x] SubTask 8.4: `MyMarketView` 목록에 `shareable` + `@share="share"` (`useOrderShare` 재사용 — 상세 화면과 동일한 동작)
  - [x] SubTask 8.5: 프론트 테스트 66건 통과(icons 3건 신규) · 빌드 · 배포 · 배포 번들 검증(`order-card__share`·`shareable` 포함 확인)

- [x] Task 9: 커뮤니티 피드 500 장애 수정 (운영 전용 오류)
  - 증상: `/api/community/posts` 가 운영에서만 500. 로그: `SQLSTATE[42000] 1055 'nowhere.community_posts.id' isn't in GROUP BY`
  - 원인: 카테고리 집계 쿼리가 `feed()` 스코프가 채워둔 `community_posts.*` + `withCount/withExists` 서브쿼리 위에 `selectRaw('category, count(*) as total')` 를 덧붙여 `GROUP BY category` 와 충돌. 운영은 `strict => true` 라 `ONLY_FULL_GROUP_BY` 가 켜져 있는데, 그 전까지는 config 캐시에 `strict=false` 시절 값이 남아 있어 잠복해 있었다.
  - [x] SubTask 9.1: `select('category')` 로 컬럼을 먼저 비운 뒤 집계 컬럼을 붙이도록 수정
  - [x] SubTask 9.2: 회귀 테스트 추가 — 실행된 SQL 을 캡처해 `community_posts.*`·`likes_count` 가 함께 나가지 않음을 검증 (수정 전 코드에서 실패함을 확인)
  - [x] SubTask 9.3: 전체 364건 통과 · pint · 배포 · 운영 검증(feed 200)
  - [x] SubTask 9.4: `.deploy/smoke_endpoints.sh` 신규 — 주요 GET 35개(공통·운행·기사/정산·관리자) 전부 200 확인. 다른 잠복 오류 없음

- [x] Task 10: 마켓 운행 목록 공백 원인 규명 + 데모 재고 갱신
  - 증상: 마켓 목록이 비어 있음 (`/api/orders?scope=market` → 200, `total: 0`)
  - 원인: 코드 결함이 아니라 **데이터 문제**. published 1261건이 모두 `service_date` 과거(최대 2026-09-09)였고, 마켓은 `service_date >= 오늘(KST)` + `시작 2시간 경과 제외` 조건으로 과거 운행을 숨긴다. 즉 미래 날짜 재고가 0이었다. (데모 시드 `seed_dense.php` 는 08-29 실행분이라 오늘/내일 기준 데이터가 소진됨)
  - 조치: 프로젝트 자체 시더 `seed_dense.php` 재실행 → 오늘 63건 + 내일 1008건 = **1071건** 생성. 마켓 `total: 1071`, 홈 추천도 정상 응답 확인.
  - 주의(운영): 생성분은 `DEN-` 접두사 테스트 데이터다. 실제 기사 신청에 등록자가 응답하지 않으므로 **첫 수익을 위해서는 실제 등록자의 운행 등록이 필요**하다. 정리: `delete from orders where order_number like 'DEN-%'`

- [x] Task 11: 운영 스케줄러 미동작 복구 — 첫 수익 경로의 실질적 병목
  - 발견: 서버에 Laravel 스케줄러 cron 이 **전혀 등록되어 있지 않았다**. `schedule:list` 에 작업은 정의돼 있으나 이를 매분 깨우는 주체(cron/systemd timer)가 없어 3주간 단 한 번도 실행되지 않았다.
  - 영향(치명적): `orders:auto-settle` 이 돌지 않아 **완료된 운행의 정산 원장이 생성될 수 없었다** → 수수료 매출이 구조적으로 0. `orders:expire-claims`(30분 미승인 자동 만료)·`orders:close-published`·`orders:expire-offers`·`rides:remind` 도 전부 미동작. `auto_order_settings` 는 `is_active=1` 인데 `last_run_at=NULL` 이었던 것이 방증.
  - [x] SubTask 11.1: `/etc/cron.d/nowhere-scheduler` 설치 — 매분 `www-data` 로 `php artisan schedule:run`, 로그 `storage/logs/schedule.log`
  - [x] SubTask 11.2: cron 재시작 후 1분 내 `rides:remind`·`orders:expire-claims` 실행 확인
  - [x] SubTask 11.3: `orders:auto-settle` 수동 실행 정상(오류 없음), `orders:auto-register` 20건 등록·`last_run_at` 갱신, `orders:close-published` 50건 정리 확인
  - [x] SubTask 11.4: 설치 스크립트 `.deploy/install_scheduler_cron.sh` 보관

- [x] Task 12: 등록자 모집 동선의 치명적 전환 버그 수정
  - 증상: 랜딩 하단 '운행 등록하러 가기'(등록자 CTA)가 `{ name: 'register' }` 로만 이동 → 가입 화면 기본값이 **기사**라, 등록자로 모집한 사람이 기사로 가입해 버린다. 모집을 시작하면 100% 발생하는 전환 손실.
  - [x] SubTask 12.1: `data/roles.js` 에 `signupRoleFromQuery()` 추가 — 가입 가능 역할(기사/등록자)만 허용, 그 외·미지정은 기사
  - [x] SubTask 12.2: `RegisterView` 가 `?role=` 쿼리로 초기 역할을 선택하도록 변경
  - [x] SubTask 12.3: `LandingView` 등록자 CTA 에 `query: { role: ROLE_CUSTOMER }` 전달
  - [x] SubTask 12.4: 테스트 2건 추가(등록자 쿼리 → 등록자 / 미지정·알 수 없는 값·배열 → 기사) · 프론트 68건 통과 · 빌드 · 배포

- [x] Task 13: 외부 접속 표면 검증 — 인터넷에서 온 실제 사용자 동선 확인
  - 배경: 서버 내부(localhost) 검증만으로는 "남이 실제로 쓸 수 있는가"를 알 수 없다. 홍보를 시작하기 전에 외부 경로를 실측했다.
  - [x] SubTask 13.1: 정적 IP `http://114.132.240.52` 전 경로 200 — `/`·`/welcome`·`/register?role=Customer`·`/robots.txt`·`/og-cover.jpg`·`/manifest.webmanifest`·`/up`. HTTPS 는 미적용(인증서 없음)
  - [x] SubTask 13.2: `https://114-132-240-52.sslip.io/` → 302 Tencent 차단(`dnspod.qcloud.com/static/webblock.html`). **도메인 기반 접속은 ICP 미비로 차단**되므로 정적 IP 직결 또는 Cloudflare 터널(아웃바운드)만 유효하다
  - [x] SubTask 13.3: `.deploy/rehearse_external_surface.ps1` 신규 — 정적 IP에서 등록자 가입 → 운행 등록 → 공개 → 마켓 노출(total 1091) → 공유 링크 → 비로그인 공개 조회(민감정보 미노출) → OG 카드 절대 URL·이미지 도달까지 **18단계 전부 통과**
  - [x] SubTask 13.4: `.deploy/cleanup_ext_test.sh` 신규 — 검증이 만든 계정·운행 정리(users 10 / settlements 0 원복 확인)
  - 남은 병목: **HTTPS 고정 주소**. 현재 HTTPS 는 Cloudflare Quick Tunnel 뿐인데 재시작마다 주소가 바뀐다 → 홍보 링크가 조용히 죽는다. 도메인 확보가 필요(사용자만 가능)

- [x] Task 14: HTTP(비보안 컨텍스트) 접속 경로 결함 수정
  - 배경: 정적 IP(`http://`)로 접속하는 기사가 실제로 생기는데, Service Worker·Push 는 보안 컨텍스트에서만 동작한다. `'serviceWorker' in navigator` 는 HTTP 에서도 true 라 `navigator.serviceWorker.ready` 가 영원히 대기하고, 알림 권한은 '거부'로 잘못 안내됐다.
  - [x] SubTask 14.1: `utils/push.js` — `isPushSupported()` 에 `window.isSecureContext` 요구 추가
  - [x] SubTask 14.2: `useProfileSettings.toggleNotify` — 비보안 컨텍스트에서 원인을 정확히 안내("브라우저 알림은 HTTPS 주소에서만 켤 수 있습니다.")
  - [x] SubTask 14.3: `utils/push.test.js` 신규 5건(HTTP 미지원 / PushManager 부재 / HTTPS 지원 / ready 미대기 / 즉시 실패) · 프론트 73건 통과 · 빌드 · 배포 · 운영 41개 엔드포인트 스모크 전부 200

- [x] Task 15: 한 계정으로 등록 + 수행 (직접 수행)
  - 배경: 기사 모집이 첫 수익의 병목이라, 운영자가 직접 운행을 등록하고 본인이 수행하기로 했다. 그런데 코드상 두 곳이 막혀 있었다 — ① 자기 운행은 가져오기(claim) 불가(`OrderClaimService::claim`), ② `published → accepted` 전이 자체가 상태 흐름에 없음(`Order::STATUS_FLOW`). 즉 등록자와 기사 계정을 따로 만들어 4번씩 로그인을 오가야만 한 건이 완주되는 구조였다.
  - 설계: `직접 수행`은 문서·정산 코드가 이미 전제하던 개념이다(`docs/ORDER_FLOW.md` 정산 주체 = "원 등록자 또는 미이전 본인 등록", `batchSettle`·`SettlementService` 의 `original_owner_id ?? user_id`). 그 의도에 맞춰 **claim 파이프라인을 재사용**해 붙였다 — 별도 상태 흐름을 만들지 않아 정산·타임라인·목록 분류가 기존과 동일하게 동작한다.
  - [x] SubTask 15.1: `OrderClaimService::selfDrive()` — 등록자 본인만, 공개(published) 운행만, 다른 기사가 신청하지 않은 경우에만 직접 수행 확정. `original_owner_id`·`claimed_at`·`approved_at` 을 기록해 정산 원장·히스토리 분류가 가져오기 흐름과 같은 기준을 쓴다
  - [x] SubTask 15.2: `OrderTransitionService` 에 `published → accepted` 위임 분기 추가 (`STATUS_FLOW` 는 건드리지 않아 다른 경로로는 열리지 않는다)
  - [x] SubTask 15.3: 프론트 — `useOrderDetail` 에 `canSelfDrive` + 주동작 「내가 직접 수행하기」, `isPerformer` 에 자기 수행 포함(하단 운행 스테퍼 노출), 완료 후에는 정산 버튼이 보이도록 조정
  - [x] SubTask 15.4: 회귀 테스트 6건 신규(`OrderSelfDriveApiTest`) — 직접 수행 성공 / 남의 운행 403 / 기사 신청분 409 / 초안 422 / 정산 원장(90,000·4,500·85,500) / 마켓 제외·내 운행 노출. 백엔드 370건 통과
  - [x] SubTask 15.5: `.deploy/rehearse_self_drive.ps1` — 정적 IP 외부에서 **한 계정으로 15단계 전부 통과** (가입 → 등록 → 공개 → 직접 수행 → 운행 시작 → 5단계 스테퍼 → 완료 → 정산 → 원장 fee 4,500 수금 대기 → 마켓 제외)
  - 남은 것: ~~자기 수행 운행은 등록자=수행자가 같은 사람이라 수금(에스크로 입금) 단계가 순환한다~~ → **Task 23에서 해소**(전액 0 마감으로 확정)

- [x] Task 16: 위챗 그룹 운행을 앱으로 옮기는 길목 복구 (AI 구조화 폴백)
  - 배경: 실제 운행은 위챗 단체방에서 돈다. 앱에는 이미 위챗 문구용 AI 구조화가 있었지만(`OrderSummaryAiStructurer` 시스템 프롬프트가 `3.30送机 蚕室`, `2号 一起出 카니발` 같은 실물 문구를 예시로 쓴다) **운영에서 100% 실패**하고 있었다.
  - 원인: 키 문제가 아니라 **네트워크**. 중국 본토 서버에서 `api.openai.com` 연결이 차단된다(DNS가 `...face:b00c...` 메타 대역으로 오염). 실측 — OpenAI 도달 불가 / DeepSeek·Qwen(DashScope)·Kimi·GLM·SiliconFlow·豆包 전부 도달 가능(401=키만 없음).
  - [x] SubTask 16.1: `OrderSummaryAiStructurer` 리팩터 — `structure()` → `decode()`(AI → 실패 시 규칙 파서) → `assembleStructured()`(기존 정규화 파이프라인 재사용). AI 경로 동작은 그대로 유지
  - [x] SubTask 16.2: `decodeLocally()` 신규 — **키 없이 동작하는 규칙·사전 파서**. 기존 중국어 사전(`送机/接机/收送机/卡起/新卡/利亚/小车/万/块`)을 그대로 재사용하고, 한 줄에 붙은 여러 운행을 일정으로 분리한다
  - [x] SubTask 16.3: `parsed_by`(`ai`|`local`)를 응답에 추가 → 화면에서 해석 경로를 안내. AI 실패는 서버 로그(warning)로도 남긴다
  - [x] SubTask 16.4: `H.MM`(예: `3.30`)은 시각 표기로 보고 날짜 폴백을 막음 — 없으면 `3월 30일`로 잘못 채워진다
  - [x] SubTask 16.5: 회귀 테스트 7건 신규(`OrderStructureLocalTest`) — 샌딩·노선·接机·묶음·차량·다중 일정·AI 정상 경로 보존. 백엔드 377건 통과
  - [x] SubTask 16.6: `.deploy/rehearse_wechat_parse.ps1` — **운영 서버에서 실제 위챗 문구 5종 전부 통과**(`3.30送机 蚕室 3人 2行李 9万` → 03:30·샌딩·잠실→인천·3명·9만 / `3号 卡起 03:00 ... 07:00 ...` → 2건 분리·셋트·카니발부터 가능)
  - 남은 것: 중국 LLM(DeepSeek/Qwen 등) 키를 넣으면 `parsed_by=ai` 로 정확도가 올라간다(설정만 교체, 코드 변경 없음)
  - 미사용 잔재: `orders.original_summary`/`structured_payload` 컬럼은 어디서도 쓰이지 않는다(원문 보관을 하려면 이 컬럼을 살리면 된다). `StoreStructuredOrderRequest` 등 미사용 FormRequest 는 삭제했다

- [x] Task 17: 운행 등록을 쉽게 — AI 결과 수정 + 붙여넣기 1회로 N건 등록
  - 배경: 위챗방 → 앱 이관의 마찰은 두 곳이었다. ① AI(현재는 규칙 파서) 구조화 결과가 **읽기 전용**이라 오해석을 고칠 수 없는데 안내 문구는 "수정해 주세요"라고 했다 — 고칠 수단 없이 잘못된 값이 그대로 공개될 수 있었다. ② 한 방에 여러 건이 섞이면 한 운행 + 여러 일정으로만 들어가, 등록자가 원하는 "여러 운행"이 되지 않았다.
  - [x] SubTask 17.1: `components/orders/OrderFieldsForm.vue` 신규 — 운행 입력 필드를 컴포넌트로 추출. 직접 입력 폼과 AI 결과가 **같은 필드**를 공유한다(중복 제거, 죽은 CSS·변수 정리)
  - [x] SubTask 17.2: AI 구조화 결과를 읽기 전용 카드 → **편집 가능한 카드**로. `structuredPreview` 제거, `aiOrders`(등록 초안 목록) 도입. 수정 모드는 직접 입력 폼으로 고정
  - [x] SubTask 17.3: `utils/orderCreate.js::buildSplitOrders()` — 일정이 여러 건이면 각각 독립 운행 초안으로 분리. 항목에 없는 값(차량 등)은 문구 전체 값에서 상속
  - [x] SubTask 17.4: `POST /orders/bulk` 신규 + `OrderCreator::createMany()` — N건을 한 트랜잭션으로 생성(실패 시 전부 롤백). `publish` 시 공개 요건 미달 건은 초안으로 남기고 `draft_ids`로 반환. `orderPayloadRules($prefix)`로 단일/일괄 검증 규칙 공유
  - [x] SubTask 17.5: 파서 보강 — `normalizeLineItems`/`parseLocalSegment`가 항목별 `amount_text`·`amount_value`·`vehicle_type`·`flight_number`를 함께 낸다(나눠 등록할 때 각 운행 금액이 제값을 갖도록)
  - [x] SubTask 17.6: 회귀 테스트 — 백엔드 `OrderBulkStoreApiTest`(5건: 독립 운행·공개 요건·목록·권한·검증) + `OrderStructureLocalTest` 항목별 금액 1건, 프론트 `orderCreate.test.js`(4건). 검증: 백엔드 Order* 97건, 프론트 77건, `check:refs`·`vite build` 통과
  - 남은 것: 셋트 탭과 AI 탭의 병존·입력 최소화는 Task 18에서 처리(라벨 정리 + 필수/추가 입력 분리)

- [x] Task 18: 등록 화면 입력 부담 줄이기 (유저 편의)
  - 배경: 붙여넣기 결과 카드가 13개 필드를 평면으로 보여줘 "다 확인해야 하나" 하는 부담이 컸고, 무엇을 채워야 마켓에 공개되는지 화면에 없었다. 묶음(셋트) 등록은 공개 옵션이 없어 초안으로만 남았는데 저장 후 마켓으로 이동해 "등록했는데 안 보인다"가 됐다.
  - [x] SubTask 18.1: `OrderFieldsForm` 재구성 — 공개 요건 7개(출발·도착·날짜·시간·차량·구분·금액)를 먼저, 나머지(항공편·고객명·연락처·인원·짐·긴급)는 '추가 입력'으로 접기. 값이 있으면 자동 펼침 + 채워진 개수 배지
  - [x] SubTask 18.2: 워딩을 등록자 언어로 — 탭 'AI 구조화 등록/셋트 등록' → '붙여넣기로 등록/여러 건 묶어서', 'AI 구조화' → '문구 해석하기', 묶음 탭의 '셋트 정보/셋트명/셋트 일정/셋트로 변환/셋트 등록' → '묶음 정보/묶음 이름/묶을 운행/운행으로 나누기/N건 묶어서 등록'
  - [x] SubTask 18.3: 공개 요건 안내 문구 + 결과 카드 헤더에 노선·일시 요약(여러 건일 때 어느 카드인지 식별)
  - [x] SubTask 18.4: `POST /orders/batch`에 `publish` 추가 — 묶음 등록도 공개 요건 미달 건만 초안으로 남기고 나머지는 공개. 응답에 `published`·`draft_ids` 추가
  - [x] SubTask 18.5: 묶음 등록 후 마켓 점프 제거 — 다른 등록 경로와 같게 목록 복귀 + 새로고침, 등록 결과(초안 N건) 안내
  - [x] SubTask 18.6: 회귀 테스트 `OrderBatchStoreApiTest`(2건). 검증: 백엔드 관련 65건·프론트 77건·`check:refs`·`vite build`·pint 통과
  - 참고: 문구 해석 자체(정확도)는 다른 API로 교체 예정 — 이 Task는 화면·흐름만 다룬다

- [x] Task 19: 공유 링크 → 가입/로그인 → 그 운행 복귀 (위챗방 이관의 마지막 1홉)
  - 배경: 위챗방에 뿌린 공유 링크로 들어온 기사가 "기사로 가입하고 신청하기"를 누르면 가입 후 홈으로 떨어져 **그 운행을 잃었다**. 위챗방으로 되돌아가 링크를 다시 찾아야 했고, 이관 동선의 정확히 그 지점에서 사람이 빠졌다.
  - 원인: `SharedOrderView` 비로그인 CTA가 `{ name: 'register' }`로 토큰을 넘기지 않았고, `RegisterView`/`LoginView`는 성공 후 무조건 홈·내 마켓으로 보냈다. 라우터 가드에도 복귀 장치가 없었다.
  - [x] SubTask 19.1: `utils/redirect.js::safeRedirectPath()` 신규 — redirect 쿼리는 외부에 노출·조작 가능하므로 **내부 경로만** 허용(open redirect 차단). `//host`(프로토콜 상대), `https://…`, `/\evil.com`(역슬래시 우회), 비문자열 모두 거부
  - [x] SubTask 19.2: `SharedOrderView` — 비로그인 CTA와 헤더 로그인 링크에 `redirect=현재 경로`를 붙이고, CTA 아래에 "가입하거나 로그인하면 이 운행으로 바로 돌아옵니다" 안내 추가
  - [x] SubTask 19.3: `RegisterView`·`LoginView` — `redirect`가 있으면 그 경로로, 없으면 기존(역할별 첫 화면/홈)으로 복귀
  - [x] SubTask 19.4: 로그인 ↔ 회원가입 ↔ 비밀번호 찾기 교차 링크에 `redirect` 전달 — 우회 경로에서도 복귀가 끊기지 않게 (`ForgotPasswordView` 재설정 완료 시 로그인으로 전달 포함)
  - [x] SubTask 19.5: 회귀 테스트 `redirect.test.js` 6건(내부 경로 통과 / 외부·프로토콜 상대·역슬래시 거부 / 빈 값·비문자열). 검증: 프론트 83건·`check:refs`·`vite build` 통과
  - 참고: 복귀 경로로 `/share/order/:token`(공개 라우트)을 쓰므로 로그인 직후 가드에 막히지 않는다. `route.path`만 사용해 redirect가 자기 자신에 겹쳐 붙는 것을 막았다

- [x] Task 20: 딥링크·세션 만료 복귀 (로그인 유도 뒤 보던 화면으로)
  - 배경: Task 19는 공유 링크(공개 라우트)의 복귀만 열었다. 비로그인 상태로 로그인 필요 딥링크(`/orders/12`, `/chat?room=3`)를 열면 랜딩으로 보내고 **목적지를 기억하지 않아** 로그인 후 홈으로 떨어졌다. 세션 만료(401)도 마찬가지로 보던 화면을 잃고 로그인 화면만 남았다.
  - [x] SubTask 20.1: `router/index.js`의 인증 가드를 `authGuard`로 추출(테스트 가능하게) — 비로그인 딥링크는 `redirect=to.fullPath`를 실어 로그인으로 보낸다. 홈(`/`)만 기존대로 랜딩 유지(홍보 유입 보호)
  - [x] SubTask 20.2: `AUTH_PAGES`(welcome·login·register·password-reset)를 라우터에서 export — 가드와 401 인터셉터가 같은 목록을 쓴다(로그인·가입·랜딩은 복귀 목적지로 삼지 않는다)
  - [x] SubTask 20.3: `api/client.js` 401 인터셉터 — 토큰을 지운 뒤 현재 `fullPath`를 `safeRedirectPath`로 검증해 `redirect`로 실어 로그인으로 보낸다. 기존엔 `{ name: 'login' }`만 보내 보던 화면을 잃었다
  - [x] SubTask 20.4: 회귀 테스트 `router/authGuard.test.js` 6건(비로그인 딥링크 redirect / 쿼리 포함 / 홈→랜딩 유지 / 로그인 상태 통과 / 로그인 화면→마켓 / 관리자 전용 차단). 검증: 프론트 89건·`check:refs`·`vite build` 통과
  - 참고: 로그인·가입·비밀번호 찾기 화면은 Task 19에서 `redirect`를 이미 처리하므로 추가 변경 없이 이어진다. 복귀 경로는 문자열 경로라 `router.push(redirectTo)`로 그대로 이동한다

- [x] Task 21: PWA 정비 (아이폰 우선) — 홈 화면 앱으로 실제 전환
  - 배경: "웹페이지를 앱으로 전환" 요청. 확인 결과 **앱의 대부분은 이미 PWA로 갖춰져 있었다**(manifest·아이콘 4종·서비스 워커·웹 푸시 구독/발송·VAPID). 실제 병목은 ① HTTPS 부재 ② 아이폰에서 설치 안내가 **한 번도 뜨지 않음**(`usePwaInstall`이 `beforeinstallprompt` 전용인데 iOS Safari는 이 이벤트를 보내지 않는다) ③ 알림 탭이 404로 가는 서비스 워커 하드코딩. 아이폰은 **홈 화면에 추가한 뒤에만 웹 푸시가 동작**하므로 ②는 알림 채널이 영영 열리지 않는 결함이었다.
  - [x] SubTask 21.1: `utils/pwa.js` 신규 — 설치 안내 판정을 순수 함수로 분리(`isIosDevice`·`isInAppBrowser`·`isStandalone`·`detectInstallGuide`). iPadOS 13+ 데스크톱 UA는 터치 지원으로 아이패드 판정, 위챗·카카오 인앱 브라우저는 홈 화면 추가가 막혀 별도 안내로 분기
  - [x] SubTask 21.2: `usePwaInstall` 확장 — `guide`(`prompt`|`ios`|`inapp`|null)·`installed` 노출. 안드로이드·데스크톱 크롬은 기존 설치 버튼, 아이폰 Safari는 순서 안내로 분기
  - [x] SubTask 21.3: 설정→화면에 아이폰 설치 순서(공유 → 홈 화면에 추가 → 추가)와 인앱 브라우저 "Safari로 열기" 안내 추가
  - [x] SubTask 21.4: 설정→알림 — 아이폰 Safari·인앱 브라우저에서는 토글 대신 "홈 화면에 추가한 뒤 켤 수 있어요" 안내를 띄운다. 종전에는 켜기를 누르면 권한 요청이 즉시 거부돼 "권한이 거부되었습니다"로 **잘못 안내**됐다
  - [x] SubTask 21.5: `index.html`에 iOS 홈 화면 앱 메타 추가 — `apple-mobile-web-app-capable`·`apple-mobile-web-app-title`(종전엔 홈 화면 아이콘 이름이 39자 문서 제목으로 잘렸다)·상태 표시줄 `default`. 헤더가 상단 안전영역을 침범하지 않도록 `black-translucent`는 쓰지 않았다
  - [x] SubTask 21.6: `sw.js`의 하드코딩 `/spa` 제거 — 현재 배포는 루트(`VITE_BASE` 미설정)인데 서비스 워커만 `/spa`를 전제해 **알림 아이콘 404 + 알림 탭 시 `/spa/notifications` 404**였다. 배포 경로를 `registration.scope`에서 얻어 루트·하위 경로 모두 맞게 동작
  - [x] SubTask 21.7: manifest 카피 교정 — "드라이버 오더 마켓 / 오더 등록·수주" → "NoWhere — 운행 최적화 플랫폼 / 기사님이 오늘 받을 운행을…" (용어 규칙: 오더 → 운행, 드라이버 → 기사)
  - [x] SubTask 21.8: 회귀 테스트 `pwa.test.js` 11건(아이폰·아이패드OS UA / 위챗 인앱 / standalone / 안내 4분기). 검증: 프론트 100건·`check:refs`·`vite build` 통과, 빌드 산출물(`public/index.html`·`public/sw.js`) 반영 확인
  - 남은 것: 도메인 + 고정 HTTPS(대표님 구매 진행 중) — 이게 없으면 설치 프롬프트·푸시·GPS가 모두 동작하지 않는다. 도메인 확보 후 Cloudflare Named Tunnel 연결은 이어서 진행

- [x] Task 22: 로컬 HTTPS 검증 환경 + 도메인·이름 조사 (이름 교체는 보류)
  - 배경: 대표님이 "NoWhere 도메인이 없으니 이름을 바꿔야 한다"고 판단. 실제 조회로 사실을 확인하고 후보를 조사했으나, **도메인·이름 확정은 뒤로 미룸**(대표님 지시 "도메인 뒤로하고 마무리").
  - [x] SubTask 22.1: 로컬 HTTPS 검증 환경 구성·철거 — `php artisan serve`(:8000) + `npm run preview`(:4173, PROD 빌드라 서비스 워커 등록됨) + cloudflared quick tunnel. 터널 주소로 `/`·`manifest.webmanifest`·`sw.js`·아이콘·`og-cover.jpg` 200, `sw.js`에 `/spa` 잔재 없음, API 로그인 성공(Driver)까지 확인. 검증 후 터널·preview 종료(공개 노출 차단)
    - 함정: `npm run dev`는 `main.js`가 서비스 워커를 **해제**하므로 PWA 설치 검증에 쓸 수 없다 → 반드시 `npm run preview`(PROD) 사용
    - 로컬 DB에 테스트 로그인을 만들기 위해 `driver01@example.com` 비밀번호를 임시로 `password`로 재설정(로컬 전용, 서버 DB 동기화 시 덮어써짐)
  - [x] SubTask 22.2: 도메인 조사 — `nowhere.com`·`.net`·`.app`·`.run` 모두 등록. **`nowhere.kr`·`nowhere.co.kr`도 가비아 NS로 위임돼 등록 상태** → NoWhere로 쓸 수 있는 도메인 없음(대표님 판단 확인)
  - [x] SubTask 22.3: 조회 방법 확립(재사용) — 무료 RDAP(`rdap.org`)은 `.kr`·`.co.kr`·`.io`·`.me`에서 **오답**(대조군 `google.co.kr`·`github.io`가 "사용 가능"으로 오판). `.com`은 **Verisign 공식 RDAP**(`rdap.verisign.com/com/v1/domain/<name>`) + 대조군 `google.com`으로 신뢰, `.kr`류는 **DNS NS 위임 여부**로 교차 확인. RDAP 404는 "미등록"일 뿐 프리미엄 가격·예약 상태일 수 있음
  - [x] SubTask 22.4: 후보 조사 — `.com` 한정 230여 개 조회. 아래는 **2026-09-11 조회 시점 미등록**(선점 가능성 있으므로 구매 직전 재확인 필요)
    - 브랜드형: `unhaengi`, `dallimi`, `doreumi`, `oneulgil`, `ttokgo`, `chakgo`, `dalgongi`, `gonghangi`, `hanbeone`, `cheotbeon`, `gyeolgo`, `baechago`, `unhaengio`, `unhaengro`, `unhaengly`, `cheokgo`, `cheokro`, `routmo`
    - 숫자 조합: `unhaeng365`, `unhaeng24`, `unhaeng247`, `unhaeng1`, `unhaeng2`, `unhaeng7`, `oneul24`, `oneul1`, `oneul247`, `gonghang24`, `gonghang1`, `gonghang365`, `24unhaeng`, `24gonghang`(→`gonghang24`), `dallimi365`, `dallim1`, `doreumi1`, `nuri1`, `chul1`, `moeun1`, `ttok365`, `chak365`, `1unhaeng`, `7unhaeng`, `4unhaeng`
    - 과감형(한국어 숫자말·치환): `8282ride`, `1004unhaeng`, `7942ride`, `haruride`, `rideharu`, `byeol365`, `gil365`, `dallyeo24`, `dallyeo365`, `dallim365`, `chongal24`, `bunge24`, `2dayride`, `ride2day`, `unhaeng4u`, `oneul4u`, `u2ride`, `77unhaeng`, `ilsa1`
    - `1ride` 계열: **`1ride.com`은 이미 등록** → `the1ride`, `1ridekorea`, `1ride24`, `1ride4u`, `my1ride`, `go1ride`, `1ridego`, `1rideone`, `1ridekr`, `pick1ride`, `1ride365`, `1rideon`. 참고로 `1ride.app`·`1ride.net`은 등록, `1ride.kr`·`1ride.co.kr`·`1ride.io`은 NS 위임 없음(등록 가능성 높음)
  - [x] SubTask 22.5: 제외 권고 — `1be`는 한국에서 **"일베"** 로 읽혀 브랜드 리스크(숫자 문제가 아니라 이름 문제). `nuriro`(누리로)는 코레일 열차명, `bunge`(번개)는 번개장터, `chongal`(총알)은 총알배송과 혼동 소지
  - [ ] **보류**: 이름·도메인 확정 및 전면 교체
  - 교체 대상(이름 확정 시): 화면 10여 곳(`index.html`·`manifest.webmanifest`·`LandingView`·`LoginView`·`RegisterView`·`SharedOrderView`·`useOrderShare`), 공유·알림 문구(`ShareController` 3곳·`sw.js` 푸시 제목), 발신 주소(`config/webpush.php` VAPID subject), 저장 키(`nowhere_login_saved`·`nowhere:market:*` 4곳·`nowhere:home:*`), 알림 태그(`nowhere-push`·`nowhere`), 다운로드 파일명(`nowhere-image-N.jpg`), 배포 설정(`fly.toml`·`render.yaml`·`docker`·`hooks`), 문서 일괄
  - 참고: 실사용자 0명이라 **저장 키를 지금 바꾸는 것이 가장 저렴**하다. 기사가 유입된 뒤에는 `nowhere_login_saved` 이전 비용이 붙는다
  - 아이폰 실기 확인 항목(미완 — 환경 재기동 후): ① 설정→화면에 공유→홈 화면에 추가 순서 표시 ② 홈 화면 아이콘 이름이 `NoWhere`로 짧게 ③ 홈 화면 앱으로 열면 주소창 없음 ④ 설정→알림에 토글 표시(설치 안내 아님) ⑤ 토글 켜기→권한 허용 ⑥ `php artisan push:test --user=<이메일>`로 실제 푸시 수신·알림 탭 이동(404 아님)

  - [x] SubTask 22.6: 아이폰 실기 검증 환경 재기동 — `php artisan serve`(:8000) + `npm run preview`(:4173) + cloudflared quick tunnel. `/`·`/manifest.webmanifest`·`/sw.js` 200, `/api/orders` 401, API 로그인(Driver) 성공까지 확인. 실기 확인은 대표님 대기

- [x] Task 23: 자기 수행 정산 전액 0 (수금 순환 해소 · 확정)
  - 배경: Task 15로 등록자=수행자가 같아지면서 '등록자 입금 → 관리자 수금 확인 → 기사 지급'이 자기 자신에게 왕복하는 순환이 생겼다(정책 결정 대기로 남아 있던 미해결 지점). 대표님이 **"전액 0 (수수료도 면제)"** 로 확정 — 플랫폼을 통과하는 돈이 없으므로 수금·수수료·지급을 모두 0으로 마감한다.
  - [x] SubTask 23.1: `Settlement::COLLECTION_NOT_REQUIRED`(`not_required`) 상수 추가 — `collection_status`는 string(20)이라 마이그레이션 불필요
  - [x] SubTask 23.2: `SettlementService::isSelfDrive()` — 원 등록자(`original_owner_id`)와 수행자(`user_id`)가 같으면 자기 수행. `createFor()`가 이 경우 `gross/fee/net = 0`, `fee_rate = 0`, `status = paid`, `collection_status = not_required`로 원장을 즉시 마감(수금 확인 목록·출금 재원에서 자동 제외)
  - [x] SubTask 23.3: `notifySettled()`에 자기 수행 분기 — 입금 안내 대신 "자기 수행 운행으로 정산되었습니다. 수금·수수료·지급 없이 마감됩니다."만 발송(입금 계좌 안내 제거)
  - [x] SubTask 23.4: 직접 수행 버튼에 **경고 확인 다이얼로그** — "직접 운행하시겠습니까?" + "마켓에서 내려가고 기사 모집이 중단됩니다 / 수금 0 / 되돌릴 수 없습니다"(`type: 'warning'`). 실행 시 공개 즉시 중단(마켓 제외) 안내
  - [x] SubTask 23.5: 등록자 정산 화면(`RegistrantSettlementView`) 수금 상태 라벨 3분기 — `paid`(입금 확인) / `pending`(입금 대기) / `not_required`(자기 수행, 중립 그레이). 자기 수행이 '입금 대기'로 잘못 보이지 않게
  - [x] SubTask 23.6: 회귀 테스트 — `OrderSelfDriveApiTest`의 정산 기대값을 90,000/4,500/85,500 → **0/0/0**으로 교체 + `collection_status = not_required`·`status = paid` 검증. `SettlementApiTest` 포함 23건 통과
  - [x] SubTask 23.7: 문서 정합 — `docs/ORDER_FLOW.md` §3-6(전액 0·수금 면제 확정 반영)·§7·§8, `docs/OPERATIONS.md` 정산 운영(수금 면제)
  - 검증: 백엔드 385건(1,868 assertions)·프론트 100건·`pint --dirty`·`check:refs`·`vite build` 전부 통과

# Task Dependencies
- [Task 2] depends on [Task 1] (배포 시 매입 계좌가 함께 반영돼야 입금 안내가 정상 동작)
- [Task 3] depends on [Task 2] (배포 후 실제 운영 데이터로 검증)
- [Task 4] 홍보 유입의 전제 — 완료
- [Task 5] 등록자가 스스로 기사를 모으는 유입 장치 — 완료
- [Task 6] 다른 모든 배포의 신뢰 기반 — 완료
