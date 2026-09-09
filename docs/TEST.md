# TEST

> 상태: ✅ 현행 — 현재 210개 테스트 케이스(Pest) 보유.

## 테스트 원칙
- 기능 추가나 회귀 위험이 있는 변경에는 테스트를 우선 검토한다.
- Laravel 프로젝트에서는 Pest 기반 Feature 테스트를 기본으로 한다.
- 테스트는 구현 세부보다 사용자 동작과 계약을 검증한다.

## 우선순위
- 1순위: 인증, 권한, 핵심 비즈니스 로직
- 2순위: 유효성 검사, 실패 케이스, 예외 처리
- 3순위: 단순 화면 출력, 정적 문구

## 현재 테스트 범위 (210건 · `tests/Feature/Api` 중심)
- 인증: 로그인(이메일·전화번호)·프로필·로그아웃·토큰 (AuthApiTest)
- 운행: 목록 필터·마켓·claim·상태 전이·셋트 규칙 (OrderApiTest, OrderGroupRulesTest)
- 추천: 왕복 노선·홈 추천·조건 일치율 (ReturnRouteApiTest, RecommendationApiTest)
- 채팅: 대화·메시지·이미지 보관함·증분 동기화 (ChatApiTest)
- 커뮤니티: 피드·글·댓글·좋아요·검색·기간 (CommunityApiTest)
- 알림·통계·리뷰·액션 센터·매칭·템플릿·자동 등록·SSE (NotificationApiTest, StatsApiTest, ReviewApiTest, ActionCenterApiTest, MatchNotificationTest, OrderTemplateApiTest, AutoOrderSettingApiTest, StreamApiTest)

## 작성 규칙
- 새 테스트는 `tests/Feature` 또는 `tests/Unit`에 배치한다.
- 팩토리를 우선 사용한다.
- 테스트 이름은 동작 중심으로 작성한다.
- 가능한 한 좁은 범위의 테스트부터 실행한다.

## 실행 예시
- `php artisan test --compact`
- `php artisan test --compact tests/Feature/Api/AuthApiTest.php`
- `php artisan test --compact --filter=OrderApiTest`

## TODO
- 회원가입 기능 테스트 보강
- 대시보드 데이터 표시 테스트 추가
- 배차/추천 알고리즘 테스트 전략 추가
