# Current Task

> 상태: ✅ 현행 (2026-09-09 갱신) — 상세 실행 로드맵·체크리스트는 [IMPROVEMENT_PLAN.md](./IMPROVEMENT_PLAN.md), 일별 이력은 `daily-review/` 참조

## Completed
- [x] 프로젝트 생성
- [x] Laravel 13 설치
- [x] 로그인
- [x] 회원가입
- [x] 비밀번호 찾기
- [x] 프로필
- [x] Auth Module
- [x] 404 페이지 정리
- [x] Shared Layout 구조 정리
- [x] Base Components 5종 구조 정리
- [x] Axios 설치
- [x] Shared API Module
- [x] AppHeader Notification UI
- [x] Dashboard Notification Test Page
- [x] Dashboard Workspace 개선
- [x] Shared Dropdown Component
- [x] Dashboard 액션 Dropdown 적용
- [x] Header 액션 Dropdown 적용
- [x] 바로가기 메뉴 아이콘 적용
- [x] Dashboard 모듈형 테스트 허브 분리
- [x] 헤더 알림 기능 연결
- [x] User Management 1차
- [x] Spatie Permission 설계 반영
- [x] Board Module 1차
- [x] File Module 기반 설계 및 Media Library 설치
- [x] Dashboard 파일관리 모듈 등록
- [x] File Module 1차 Upload UI / Manager UI
- [x] File Module 다중선택 / 이미지 미리보기 / 청크 업로드
- [x] Shared DataTable 1차 List Framework
- [x] Board 첨부파일 File Module 연결
- [x] Shared Toast UI Editor / Viewer Module
- [x] Form 2차 BaseInput 계층 재구성
- [x] Modal 공통 구조 정리
- [x] Dialog 공통 구조 정리
- [x] Dashboard 카드(Card) 모듈 등록
- [x] Dashboard 리스트(List) 모듈 등록
- [x] Dashboard 폼(Form) 모듈 등록
- [x] Dashboard 토스트(Toast) 모듈 등록
- [x] Dashboard 로딩·빈 상태(Loading/Empty) 모듈 등록
- [x] Dashboard 알림 배너(Alert) 모듈 등록
- [x] Foundation 규약 명세 문서(docs/FOUNDATION.md) 작성
- [x] STEP 2-5 API 규약 문서(docs/API.md) 작성
- [x] 비즈니스/데모 라우트 분리 (dashboard.business.* vs dashboard.modules.*)
- [x] 비즈니스 프론트엔드 분리 (resources/js/business/*, app.js 모노리스 축소)
- [x] 권한 검증 Policy 도입 (Board/Order/User Policy + 라우트 can: 미들웨어)
- [x] 대시보드 공용 컴포넌트 적용 (x-alert 로그인 안내 · Empty State 운행 카드)

### NoWhere 마켓 (SPA + Laravel API, 2026-08)
- [x] 마켓: 운행 등록/목록/필터/정렬/퀵 칩/긴급 배지
- [x] 마켓: 본인 등록 운행도 노출하되 본인 claim 차단 (관리자 검증용)
- [x] 운행 가져오기(claim) 요청/승인/거절 라이프사이클 + 채팅 이벤트 카드
- [x] 자동 운행 등록 (orders:auto-register, 관리자 설정 화면, 매일 09:00 KST)
- [x] 정산 관리: 완료→정산 전이, 일괄 정산 (useBatchSettle/SettlementView)
- [x] 운행 매칭: 매칭 설정(preferences), is_matched_to_me, matched=1 필터, 콜링
- [x] 웹 푸시(VAPID) + 실시간 알림 센터 (상황별 카테고리/색상)
- [x] 커뮤니티: 카테고리 7종/검색/인기 글/작성 모달
- [x] 채팅: 승인/시간변경/경로변경/요금협의/취소 요청 카드, 날짜·유형 분류
- [x] 리뷰/평점: 완료·정산 후 상호 리뷰, 등록자 신뢰 정보(마켓 카드)
- [x] 기사 앱: 홈(오늘 운행/수익/빠른 매칭)·운행 이력·정산·설정/더보기
- [x] KST 시간대, 색상 토큰 체계(라이트/다크), 880px 공통 레이아웃
- [x] 배포 자동화: deploy.sh, SKILL.md(nowhere-deployment), RULES.md 반영
- [x] 민감 정보 관리: SECRETS.md 로컬 전용 분리 (커밋·업로드 금지)

### 서버 운영 안정화 (2026-08-26, 서버 초기화 후 재구성)
- [x] 새 서버 환경 재구성: PHP 8.3/nginx/composer(알리윤 미러)/node/cloudflared 설치, 코드 tar 업로드 배포
- [x] SSH 접속 안정화: 키 인증(OpenSSH ssh + SSH_ASKPASS, `.deploy/id_rsa`)으로 전환
- [x] 계정 재설정: 전 계정 비밀번호 `123456`, `market@example.com`=Admin (SECRETS.md 기준)
- [x] SQLite DB 자동 백업 cron (매일 01:00, 14일 보존, `/var/www/backups/`)
- [x] 외부 접속: Cloudflare Quick Tunnel systemd 서비스(`cloudflared-quick`, https)

### 앱 전반 점검·핵심 플로우 검증 (2026-08-26, 라이브 서버)
- [x] 핵심 플로우 전 사이클 검증: 등록(+10XP)→게시→가져오기(+20XP)→승인→운행중→완료(+50XP)→정산(+30XP)→상호 리뷰 (order 73, 전 구간 200/201)
- [x] 부수 동작 검증: 기사 상태 `on_trip→online` 자동 복귀, 드라이버 XP 20→100, 리뷰 요약/분포, `settled`=터미널 상태
- [x] 앱 전반 점검: SPA 렌더링(200) · 주요 API(admin/driver/stats/notifications/options) 전부 200
- [x] 서버 로그 점검: nginx·php-fpm 에러 0건, 최근 500 응답 0건, Laravel 로그(LOG_LEVEL=error) 에러 없음
- [x] 프론트엔드 소스(`frontend/src` 96파일) git 트래킹 확인 — `dist`만 빌드물 제외, 재배포 시 서버 빌드 필요

### 운행 플로우 개선 (2026-08-26)
- [x] claim 수락 대기 상태 완성: `STATUS_FLOW`에 `acceptance_pending` 경로 명시, 전이 API에서 claim 서비스로 승인/거절/철회 위임(claimant 정리 일관), 드라이버 요청 철회 기능
- [x] 운행 시간·실제 수익 기록: `started_at`/`completed_at`/`actual_revenue` 컬럼 추가, 운행중·완료 전이 시 기록, 완료 시 실제 수익 입력 UI, 상세 화면 표시
- [x] 마켓 공개 필수 입력 검증: publish 전 출발지/도착지/차량/구분/서비스 일시/금액 필수 (API·대시보드 공통)
- [x] 테스트 7건 추가, 전체 220개 통과 · 라이브 서버 배포 후 전 구간 검증 완료 (빈 운행 publish 422, claim 철회, transition 승인, 시간/금액 기록)

### 운행 템플릿 활용 점검·개선 (2026-08-26)
- [x] 템플릿 기능 점검: 백엔드(index/store/destroy)·프론트(적용/저장/삭제)·마이그레이션 모두 연결 확인, 라이브 동작 검증
- [x] 활용 갭 개선: 템플릿 UI가 manual 모드에서만 보이던 것을 AI 구조화/직접 입력 공통으로 확장
- [x] 템플릿 API 테스트 4건 추가 (저장/목록/본인만 삭제/사용자 스코프)

### SQLite → MySQL 전환 (2026-08-26, MariaDB)
- [x] 서버 MariaDB 설치·스키마 구성: DB `nowhere`(utf8mb4) + 전용 유저, 28개 테이블 마이그레이션
- [x] SQLite 데이터 이관: orders 75·users 3·notifications 등 PDO 스크립트로 전부 복사 (migrations/cache/sessions/jobs 제외)
- [x] MySQL 호환 쿼리 수정: `(service_date || ' ' || service_time)` concat → 날짜·시간 분리 비교 (OrderListService·SendRideReminders)
- [x] 서버 `.env` DB 전환(`mysql`)+config:cache + AUTO_INCREMENT 정상 (orders 76, users 4)
- [x] 라이브 검증: 로그인·상세·마켓 목록 38건·내 운행 완료 탭 정상 — 전환 직후 마켓 0건이던 서버 미배포(구버전 concat 쿼리) 문제 수정

### 유사 플랫폼 벤치마크 기반 기능 (2026-08-26, 조사 반영)
- [x] 매칭 알림 강화: 콜링(온라인+매칭 켬) 전환·설정 활성 시 보류 매칭 운행 즉시 알림, 같은 운행 중복 알림 방지 (화물맨 '화물 듣기' 벤치마크)
- [x] 요금 제안(오퍼) 시스템: 기사 운임 제안/철회, 등록자 수락·거절, 수락 전 기사 연락처 비공개, claim·확정 시 대기 제안 자동 정리 (uShip/uber Freight 벤치마크)
- [x] 딜 성사 금액 계약 반영: 등록자가 6만에 등록해도 기사가 6만5천에 제안 후 수락하면 계약 금액이 6만5천으로 확정(`expected_revenue`/`amount_value` 갱신) — 수락 응답에 `accepted_amount`, 알림·수락 메시지에 딜 금액 명시
- [x] 처리할 일(액션 센터) 통합: 흩어져 있던 운행 액션을 한 페이지(`/actions`)로 — ① 가져오기 승인/거절(claimant 평판 포함) ② 요금 제안 수락/거절 ③ 채팅 요청 카드(시간·경로·요금·취소 → 채팅에서 응답), 알림·더보기에서 진입, 전체 대기 건수 표시
- [x] 왕복 노선 추천: 내가 맡은 운행의 하차지 근처에서 시작하는 운행을 마켓 상단 우선 노출, 시/도 단위 과매칭 제거·구/동 접미사 정규화 (CJ 더운반/uber Freight 리턴 로드 벤치마크)
- [x] 테스트 29건 추가(매칭 5·오퍼 13·왕복 6·액션센터 5), 전체 253개 통과 · 라이브 배포·검증 완료 (오퍼 수락 → order 63/76 accepted, 왕복 추천 마포구 5건)

### 검증 인프라 구축 (2026-08-29, 자동 사이클 기반)
- [x] 프론트 테스트(vitest) 도입: vitest + @vue/test-utils + happy-dom, `npm test` 스크립트, 순수 로직·유틸·스토어 테스트 32건 (formatTime/chatTime/communityCategories/useImageStatus/levels/ui store)
- [x] 원클릭 검증 스크립트 `verify_all.ps1`: Pint → Pest → vitest → 빌드 → check:refs, 실패 단계 리포트·종료 코드
- [x] pre-commit에 백엔드 테스트 추가: PHP 변경 시 `php artisan test --compact` 자동 실행 (hooks/pre-commit 버전 관리)
- [x] 검증 전용 스킬(`.cursor/skills/nowhere-verification`) — RULES 3단계 검증 체크리스트 정형화
- [x] 왕복 추천 테스트 시간 의존성 제거: travelTo + service_time·소요시간 고정으로 결정적 테스트 전환 (281건 전체 통과)

### 홈 추천일정 (2026-08-29, 일정 없는 기사 추천 강화)
- [x] 백엔드 `GET /orders/recommendations`: 일정(수락/운행중)이 있으면 왕복 노선 추천 우선, 없으면 '매칭 설정 + 운행 이력' 복합 추천 (이유: 매칭 설정/자주 다니는 노선/왕복 노선)
- [x] 홈(HomeView)에 '추천일정' 섹션 추가 — OrderCard + 추천 이유 배지, 카드 클릭 시 운행 상세로 이동
- [x] 추천 API 테스트 4건 추가 (설정/이력/왕복/빈 배열), 전체 285건 통과

### 신뢰·운영·자동화·품질 구현 (2026-08-30 ~ 2026-09-08 — IMPROVEMENT_PLAN Phase A~D/Q)
- [x] **Phase A(MVP '돈·역할·근거')**: 가입 역할 선택(기사/등록자)+등록자 전용 홈 분기, 채팅 요청(시간·경로·요금·취소) 확정 API·화면, 정산 원장(`settlements`)·수수료·기사 계좌·출금 신청→지급, 상태 타임라인(`order_events`)·운행 상세 세로 타임라인, 홈 추천 카드 근거 ✓ 체크리스트
- [x] **Phase B-1 신고/분쟁**: 운행·사용자·채팅 신고 접수, 접수→확인→조사→처리→완료 흐름·양방향 알림
- [x] **Phase B-2 관리자 개입**: 운행 숨김/보류/강제취소, 기사·등록자 제재(주의/제한/정지·`moderation_status`), 채팅 운영(대화 확인/운행·정산 보류/중재 메시지), 일일 운영 화면(🔴>🟡>🟢)
- [x] **Phase B-3 증빙 심사**: 차량/면허 파일 업로드 → 관리자 심사(대기 우선·사진 확인) → 승인/거절 알림·재신청
- [x] **Phase B-4 고객지원**: 공지·FAQ(관리자 작성·수정·삭제), 1:1 문의 작성→답변→알림
- [x] **Phase C 자동화**: 30분 미승인 claim 백그라운드 자동 만료(`orders:expire-claims`), 완료→자동 정산·정산 완료 알림(`orders:auto-settle`), 공개 시 매칭 알림(조건 충족+온라인+비충돌), 알림 반복 방지(`notifyOnce`)
- [x] **Q-2 행동 데이터**: `behavior_events`(추천 노출/클릭/신청/거절/취소/완료), 홈·마켓 카드 트래킹 전송, 기사 활동 권역(하차지) 집계
- [x] **채팅**: 목록 '모두 읽음' 일괄 처리, 하단 메뉴 미확인 배지 잔류·즉시 갱신 수정
- [x] **Q-4 보안·계정**: 비밀번호 찾기/재설정(이메일 6자리 인증코드, 계정 스캔 방지·60초 재전송 제한·만료 60분), 로그인 잠금(계정 단위 연속 5회 실패 → 15분, `login_attempts`), 역할·권한 정리(Spatie **미도입** 결정 — `ADMIN_ROLES`/`roleLabels()`/`ROOT_USER_ID` 단일화, `AuthorizesAdmin` 트레이트로 컨트롤러 인가 통일, 프런트 `data/roles.js`)
- [x] **Q-1 기술부채**: 빈 `MarketBroadcast.php` 제거, OrderDetail `.status-flow` 데드 CSS 제거, `boards` 테이블·`board.*` 권한 정리(드롭 마이그레이션), `Order::trading` 레거시 문서·코드 정리(상태 유지)
- [x] 검증: 백엔드 312 passed(1485 assertions)·프런트 vitest 54·vite 빌드·Pint 클린 (2026-09-08 기준)

## Current
- [ ] 다음 작업: **첫 수익 1건 실현** — 실제 운행 등록 → 공유 링크 → 신청 → 승인 → 운행 → 완료 → 정산 → 입금·수금 확인까지 실데이터로 한 바퀴
  - 선행 A(막힘): **이름·도메인 확정** — `nowhere.com`·`.net`·`.app`·`.run`은 물론 `nowhere.kr`·`nowhere.co.kr`도 이미 등록돼 있어 **NoWhere로 쓸 도메인이 없다**. 이름 교체 여부·새 이름은 2026-09-11 **보류**(후보·조회 결과·조회 방법은 `.trae/specs/achieve-first-revenue/tasks.md` Task 22)
  - 선행 B: **고정 HTTPS 주소** — 홍보 유입·iOS 홈 화면 설치·웹 푸시·GPS가 모두 HTTPS 전용이라 이 하나가 실질 병목이다. 도메인 확정 후 Cloudflare Named Tunnel + `APP_URL`/`FRONTEND_URL` + `VITE_SITE_URL` 재빌드 (절차는 [DEPLOY.md](./DEPLOY.md) §7)
  - 그 전까지: quick tunnel(HTTPS)로 아이폰 실기 검증(설치 안내 → 홈 화면 추가 → 알림 켜기 → 수신) + `rehearse_first_revenue.sh` 경로 리허설. 단 `npm run dev`는 서비스 워커를 해제하므로 반드시 `npm run preview`(PROD) 사용
- [x] Q-6 문서 동기화: CHANGELOG 2026-09-11 절·ORDER_FLOW 정산 수금·OPERATIONS 입금 확인·TASKS Current 갱신 (2026-09-11)

## 원칙
- 이 문서는 현재 작업을 하나만 지정하는 기준 문서다.
- AI는 이 문서에 적힌 현재 작업만 수행한다.
- `Completed`는 다시 작업하지 않는다.
- `Current`만 작업한다.
- `Next`는 사용자의 지시가 있기 전까지 구현하지 않는다.
- 현재 작업이 완료되면 종료하고, 다음 작업은 이 문서를 갱신한 뒤 시작한다.

## Module
- NoWhere 마켓 SPA (`frontend/`) + Laravel API (`app/`, `routes/`, `database/`)

## Step
- Phase A~C·B(신뢰·운영)·Q-1/Q-2/Q-4 구현 완료 (2026-09-08) · Q-6 문서 동기화 완료 (2026-09-11) · 품질 잔여(Q-3 CI·Q-5 모바일·성능) 진행 · Phase D(장기) 대기

## Current Work
- MVP 기능(운행 등록→가져오기→운행→정산→리뷰)과 신뢰·운영(신고/개입/심사/고객지원)·자동화(자동 만료·정산)·보안(Q-4)·기술부채(Q-1)·문서 동기화(Q-6)가 완료된 상태. 남은 것은 **첫 수익 1건을 실데이터로 끝까지 돌리는 것**이며, 그 선행 조건인 도메인 + 고정 HTTPS를 사용자가 준비 중이다. 품질 잔여(Q-3 CI·Q-5 모바일·성능)와 Phase D는 그 뒤에 다룬다.

## Scope
- 다음 작업 후보
  - Q-3 검증 인프라: GitHub Actions CI(Pint→Pest→vitest→빌드→check:refs), 프런트 테스트 확대/E2E(Playwright) 후보
  - Q-5 모바일·성능: PWA 아이콘·스플래시, 3초 폴링 부하 검토(일괄 sync/WebSocket 여부), 반응형·다크모드 일관성
  - Q-6 문서 동기화: ROADMAP.md·TASKS.md 갱신(본 문서 갱신 완료), CHANGELOG·노후 문서 정리
  - Phase D(장기): 행동 데이터 기반 2~3단계 추천, 동선 최적화, 오늘 수익 목표·조합 추천, 배차 엔진
  - 상용화: GitHub 커밋 히스토리 정리 + 리포 private, 고정 도메인 + Let's Encrypt SSL, 서버 비밀번호 로그인 비활성화

## Foundation Roadmap

### 목표
- 마켓 핵심 기능(운행 등록→가져오기→운행→정산→리뷰)을 마무리하고, 서버 운영 안정화 후 상용화 준비로 전환한다.

### Business Queue
- [x] Order (등록/마켓/가져오기/전이/정산)
- [x] Match (매칭 설정/추천/콜링)
- [x] Driver (기사 앱 홈/이력/정산/설정)
- [ ] Settlement 고도화 (정산서·세금계산서 등, 필요 시)
- [ ] 상용화 전환 (고정 주소·도메인·SSL — SQLite→MySQL(MariaDB) 전환 완료)

## 완료 기준
- 서버 접속이 SSH 키로 안정화되었다. (완료)
- SQLite 백업이 cron으로 매일 자동 수행된다. (완료 — 01:00, 14일 보존)
- 다음 작업이 지정되기 전까지 임의 구현 금지.
- 작업 완료 후 종료한다.
