# Current Task

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
- [x] 대시보드 공용 컴포넌트 적용 (x-alert 로그인 안내 · Empty State 오더 카드)

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

## Current
- [ ] 다음 작업 미지정 (후보: ① GitHub 히스토리 정리 후 리포 private 전환 ② 상용화 준비(고정 도메인·SSL) ③ Settlement 고도화(정산서·세금계산서))

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
- 마켓 기능 개발 완료 · 서버 운영 안정화 완료 · 다음 작업 결정 대기

## Current Work
- NoWhere 마켓 기능과 서버 운영 안정화(SSH 키 인증·백업 cron·https 터널), SQLite→MySQL(MariaDB) 전환이 완료된 상태. 다음 작업(GitHub 리포 private 전환 / 상용화 준비(고정 도메인·SSL) / Settlement 고도화)은 사용자가 지정할 때까지 대기한다.

## Scope
- 다음 작업 후보
  - GitHub 커밋 히스토리에 노출된 구 비밀번호 정리 + 리포 private 전환 검토
  - 서버 보안 강화: 비밀번호 로그인 비활성화(`PasswordAuthentication no`) — 키 인증만 유지
  - 상용화 준비: 고정 도메인 확보 + Let's Encrypt SSL (SQLite→MySQL은 완료)
  - Settlement 고도화 (정산서·세금계산서 등, 필요 시)

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
