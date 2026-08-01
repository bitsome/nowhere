# PROJECT

## 프로젝트 목적

## 서비스 소개

## 대상 사용자

## 개발 목표

## 핵심 기능

## 서비스 플로우

## 프로젝트 구조

## 개발 원칙

## 폴더 구조

## 향후 계획

## 개요
- 프로젝트명: `nowhere`
- 스택: Laravel 13, PHP 8.3+, MySQL 8, Sanctum, Vue 3, JavaScript (ES6), Vite, Vue Router, Axios
- 목적: 인증과 기본 대시보드를 시작점으로 확장 가능한 웹 애플리케이션 구축

## 현재 상태
- 로그인/로그아웃 기능 구현
- 랜딩 페이지 및 대시보드 UI 구성
- 기본 사용자 모델 및 세션 기반 인증 사용
- `docs/` 기반 설계 문서 구조 구성

## 기술 스택

### Backend
- Laravel 13
- PHP 8.3+
- MySQL 8
- Redis (2차)
- Laravel Sanctum

### Frontend
- Vue 3
- JavaScript (ES6)
- Vite
- Vue Router
- Axios
- CSS (직접 작성)

### Server
- Ubuntu
- Nginx

### Version Control
- Git
- GitHub

## 개발 규칙
- ✓ JavaScript만 사용
- ✓ 기존 CSS만 사용
- ✓ 컴포넌트 재사용
- ✓ 기능별 모듈화
- ✓ 하나의 기능 완료 후 Commit
- ✓ 하나의 기능만 개발
- ✓ Laravel 13 기준
- ✓ Vue3 Composition API (`script setup`)
- ✓ CSS Framework 사용 금지
- ✓ TypeScript 사용 금지

## 주요 디렉터리
- `app/`: 컨트롤러, 모델, 요청 객체
- `resources/views/`: Blade 화면
- `routes/`: 웹 라우트
- `database/`: 마이그레이션, 팩토리, 시더
- `tests/`: Pest 테스트

## 다음 목표
- 회원가입 화면 추가
- 실제 도메인 모델 설계
- API 및 화면 확장
- 배차 알고리즘 규칙 구체화
- 보안 및 테스트 정책 구체화
