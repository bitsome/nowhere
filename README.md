# NoWhere

공항·셔틀 오더 마켓 — 오더를 **등록**하고, 다른 드라이버가 **가져와서**(claim) 운행·정산하는 2면 시장 플랫폼.

```
등록자 ──오더 등록──▶ 마켓(공개) ──claim──▶ 수행자 ──운행·완료──▶ 리뷰·평점·정산
```

## 주요 기능

- **오더 워크스페이스**: 단일/셋트 오더 등록, AI 구조화, 마켓 노출, claim, 상태 전환(published → trading → accepted → driving → completed → settled), 일괄 정산
- **평점·리뷰**: 완료 오더에서 상호 리뷰, 프로필에 평점·완료 실적·누적 매출 표시
- **채팅**: 오더 연동 대화, 이미지 첨부, 실시간 안읽음 배지
- **커뮤니티**: 게시글·이미지·댓글·좋아요
- **대시보드**: 오늘/내일 운행, 일별·월별 매출 차트, 상태 분포
- **알림**: 실시간 미확인 배지(SSE once 모드), 읽음 처리, 필터
- **인증**: 차량·면허 인증 신청/관리자 승인, 레벨·XP 시스템

## 기술 스택

| 영역 | 스택 |
|---|---|
| 백엔드 | Laravel 12, Sanctum, SQLite(개발) |
| 프론트엔드 | Vue 3, Vite, Pinia, Vue Router, Naive UI (독립 SPA `frontend/`) |
| 실시간 | SSE `/api/events` (once 모드: 5초 재연결) |
| 테스트 | Pest(PHPUnit) — 190+ 테스트 |

## 시작하기

```bash
# 백엔드
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # 데모 계정 + 오더·채팅·커뮤니티 시드
php artisan serve            # http://localhost:8000

# 프론트엔드 (별도 터미널)
cd frontend
npm ci
npm run dev                  # http://localhost:5174 (vite 프록시로 /api 연결)
```

데모 계정: `test@example.com` / `password` (Operator), `market@example.com`(Admin), `driver01@example.com`(Driver)

## 테스트

```bash
php artisan test   # 전체 190건 (오더·리뷰·채팅·커뮤니티·통계·인증·레거시 모듈)
```

> 테스트 실행 전 `php artisan config:clear` — config 캐시가 있으면 테스트 환경(APP_ENV=testing)이 적용되지 않는다.

## 프로젝트 구조

```
app/
  Http/Controllers/Api/   # SPA 전용 REST API (auth·order·chat·community·notification·review·stats·stream·verification)
  Models/                 # Order·OrderGroup·Review·Conversation·Message·CommunityPost 등
  Notifications/          # DB 알림 (OrderNotification 재사용)
  Policies/               # Order·User·Board 권한
  Services/               # OrderGroupService·AI 구조화
  Support/                # 목록 빌더·레벨 테이블·마켓 브로드캐스트
  Support/Orders/         # OrderListRowBuilder·OrderWorkspaceListBuilder (SPA/Blade 공용 계약)
frontend/
  src/views/              # SPA 화면 (마켓·내 오더·채팅·커뮤니티·대시보드·알림·프로필)
  src/components/         # 오더 카드·채팅·헤더 배지·스켈레톤
  src/stores/             # Pinia (auth·chats·notifications·theme·ui)
  src/api/                # axios 클라이언트
docs/                     # API.md·ARCHITECTURE.md·DEPLOY.md·CHANGELOG.md 등
tests/Feature/Api/        # API 회귀 테스트
```

## 문서

- [API.md](docs/API.md) — 전체 REST API 스펙
- [DEPLOY.md](docs/DEPLOY.md) — 배포 절차·SSE 실시간 모드 전환
- [CHANGELOG.md](docs/CHANGELOG.md) — 변경 이력
- [ARCHITECTURE.md](docs/ARCHITECTURE.md) — 아키텍처 개요
