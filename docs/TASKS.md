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

## Current
- [ ] 서버 운영 안정화 — SSH 접속 복구(비밀번호 확정·shadow 복구) 및 키 인증 전환
- [ ] SQLite DB 자동 백업 cron 설정
- [ ] SSL 인증서 적용 (도메인 확보 후 Let's Encrypt)

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
- 마켓 기능 개발 완료 · 서버 운영 안정화 단계

## Current Work
- NoWhere 마켓 기능(자동 등록·정산·매칭·웹푸시·커뮤니티·기사 앱)을 완료 선언하고, 현재는 서버 운영 안정화(SSH 접속 복구·백업·SSL)를 진행한다.

## Scope
- 서버 SSH 접속 복구 (비밀번호 확정, shadow 해시 정상화, SSH 키 인증 등록)
- SQLite 백업 cron (매일 자동 백업, 보존 정책)
- SSL 인증서 (도메인 확보 시 Let's Encrypt 적용)
- 상용화 전 임시 검증 환경 유지 (Cloudflare Quick Tunnel)

## Foundation Roadmap

### 목표
- 마켓 핵심 기능(운행 등록→가져오기→운행→정산→리뷰)을 마무리하고, 서버 운영 안정화 후 상용화 준비로 전환한다.

### Business Queue
- [x] Order (등록/마켓/가져오기/전이/정산)
- [x] Match (매칭 설정/추천/콜링)
- [x] Driver (기사 앱 홈/이력/정산/설정)
- [ ] Settlement 고도화 (정산서·세금계산서 등, 필요 시)
- [ ] 상용화 전환 (고정 주소·도메인·SSL, SQLite→MySQL 검토)

## 완료 기준
- 서버 접속이 SSH 키로 안정화되고, 비밀번호 노출 문제가 해소된다.
- SQLite 백업이 cron으로 매일 자동 수행된다.
- 다음 작업이 지정되기 전까지 임의 구현 금지.
- 작업 완료 후 종료한다.
