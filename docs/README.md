# NoWhere 문서 인덱스

`docs/` 디렉터리의 문서 역할과 읽는 순서를 정리한다. 프로젝트 참여자와 AI 에이전트가 공통 기준으로 문서를 참조할 수 있게 한다.

## 상태 배지 기준

- ✅ **현행** — 현재 코드와 일치하는 문서
- 🔶 **부분 구식** — 일부 내용이 현재 구현과 다름
- 🔵 **설계·초안/이행 설계** — 구현 전 계획 또는 이행 완료된 설계(레퍼런스)
- ⚪ **미갱신** — 현재 구현을 따라가지 못한 문서

## 권장 읽기 순서

1. [PRODUCT_VISION.md](./PRODUCT_VISION.md) — 제품 방향성 (운행 최적화 플랫폼, 단일 소스)
2. [OPERATIONS.md](./OPERATIONS.md) — 운영 기획 (관리자 시스템·자동화, 단일 소스)
3. [PROJECT.md](./PROJECT.md) — 프로젝트 소개와 현재 상태
4. [ARCHITECTURE.md](./ARCHITECTURE.md) — 현재 시스템 구조
5. [ORDER_FLOW.md](./ORDER_FLOW.md) — 운행 상태 흐름 (단일 소스)
6. [RULES.md](./RULES.md) — 개발 및 AI 작업 규칙
7. [RECOMMENDATION.md](./RECOMMENDATION.md) — 운행 추천 알고리즘
8. [API.md](./API.md) — REST API 스펙
9. [DATABASE.md](./DATABASE.md) — DB 구조
10. [COLORS.md](./COLORS.md) → [FOUNDATION.md](./FOUNDATION.md) → [UI.md](./UI.md) — UI/UX 기준
11. [DEPLOY.md](./DEPLOY.md) — 배포 절차
12. [TEST.md](./TEST.md) — 테스트 전략
13. [GIT.md](./GIT.md) — 커밋·브랜치·서버 반영 규칙
14. [CHANGELOG.md](./CHANGELOG.md) — 변경 이력

## 문서 목록

### 제품 방향

- [PRODUCT_VISION.md](./PRODUCT_VISION.md) ✅ 현행 — 제품 방향성 (운행 최적화 플랫폼·UX 원칙·화면 가이드·사업 전략)
- [OPERATIONS.md](./OPERATIONS.md) ✅ 현행 — 운영 기획 (관리자 시스템·자동화·정산·신고·운영 전략)

### 프로젝트 개요

- [PROJECT.md](./PROJECT.md) 🔶 부분 구식 — 프로젝트 소개와 목표 (초기 Blade/Foundation 문구 잔존)
- [ARCHITECTURE.md](./ARCHITECTURE.md) ✅ 현행 — 현재 시스템 아키텍처 (Laravel API + 독립 SPA)
- [CHANGELOG.md](./CHANGELOG.md) 🔶 부분 구식 — 변경 이력 (0.2.0 이후 대규모 변경 미기록)

### 규칙·운영

- [RULES.md](./RULES.md) ✅ 현행 — 개발 및 AI 작업 규칙 (모듈화·구조·프로세스)
- [GIT.md](./GIT.md) ✅ 현행 — 커밋·브랜치·서버 반영 규칙 (단일 main, tar 배포)
- [TEST.md](./TEST.md) ✅ 현행 — 테스트 전략 (210개 케이스, tests/Feature/Api 중심)
- [SECURITY.md](./SECURITY.md) ✅ 현행 — 인증·보안 규칙 (Sanctum Bearer 기준)
- [DEPLOY.md](./DEPLOY.md) ✅ 현행 — 배포 절차·SSE 실시간 모드 전환
- [DASHBOARD.md](./DASHBOARD.md) 🔶 부분 구식 — 대시보드 허브 규칙 (Blade 기준, 현재 SPA 기반)

### 설계·API

- [BUSINESS.md](./BUSINESS.md) 🔵 설계·초안 — Business Foundation·Order/Dispatch/Settlement 확장 설계
- [DATABASE.md](./DATABASE.md) ⚪ 미갱신 — DB 설계 (SPA 테이블 미반영)
- [API.md](./API.md) ✅ 현행 — 전체 REST API 스펙
- [API_SPLIT.md](./API_SPLIT.md) 🔵 이행 완료된 설계 — 독립 프론트엔드·백엔드 분리 설계 (현재 구현은 ARCHITECTURE.md·API.md 참조)
- [DISPATCH.md](./DISPATCH.md) 🔵 설계·초안 — 배차 엔진 설계 (구현 전)
- [RECOMMENDATION.md](./RECOMMENDATION.md) ✅ 현행 — 운행 추천 알고리즘 (match_score 배점·60% 필터 포함)

### 운행 상태

- [ORDER_FLOW.md](./ORDER_FLOW.md) ✅ 현행 — 운행 상태 흐름 단일 소스 (draft→published→acceptance_pending→accepted→driving→completed→settled, ride_step 포함)

### UI/UX

- [UI.md](./UI.md) 🔶 부분 구식 — UI/UX 규칙·디자인 시스템 (Color System 섹션은 COLORS.md 기준)
- [COLORS.md](./COLORS.md) ✅ 현행 — 색상 토큰 체계 (단일 소스)
- [FOUNDATION.md](./FOUNDATION.md) ✅ 현행 — 공통 컴포넌트 규약

### 상태 관리

- [ROADMAP.md](./ROADMAP.md) 🔶 부분 구식 — 개발 단계·우선순위 (초기 단계 중심)
- [TASKS.md](./TASKS.md) 🔶 부분 구식 — 진행/예정 작업 (최신 현황은 CHANGELOG·작업 세션 참조)

## 운영 원칙

- 기능 변경 시 관련 문서를 함께 갱신한다.
- 설계가 바뀌면 `CHANGELOG.md`에도 남긴다.
- 색상 등 단일 기준이 되는 문서가 있으면 중복 정의하지 않고 해당 문서를 참조한다.
