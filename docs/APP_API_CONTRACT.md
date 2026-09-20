# 앱 API 계약서 (APP API CONTRACT)

> 상태: 🔵 **확정 초안** — 현재 백엔드 구현을 근거로 기사 앱·업체(등록자) 앱이 의존할 계약을 고정한다.
>
> 근거: [routes/api.php](../routes/api.php) · [API.md](./API.md)(엔드포인트 레퍼런스) · [API_SPLIT.md](./API_SPLIT.md)(SPA 분리 설계) · [ORDER_FLOW.md](./ORDER_FLOW.md)(상태 흐름)

## 1. 목적과 범위

웹 SPA와 **별도 네이티브 앱**(기사 앱 / 업체 앱)을 붙일 때, 두 앱이 의존하는 API 계약을 확정한다.

- 여기서 "확정"은 두 가지다.
  1. **이미 구현된 계약 고정** — 앱이 기대해도 되는 경로·응답·상태값·권한
  2. **미확정 항목 명시** — 앱 착수 전에 반드시 결정해야 하는 것(7장)
- **변경 규칙**: 이 문서의 경로·필드·상태값을 바꾸면 앱이 깨진다. 서버 변경 시 앱 배포 일정과 함께 이 문서를 갱신한다.
- 상세 필드·파라미터는 [API.md](./API.md)를 따른다. 이 문서는 **앱이 쓰는 범위와 계약 규약**만 다룬다.

## 2. 공통 계약

### 2.1 기본

| 항목 | 값 | 근거 |
|---|---|---|
| Base URL | `{host}/api` | `routes/api.php` |
| 버전 | **없음** (경로에 `/v1` 없음) → 7장 | — |
| 인증 | `Authorization: Bearer {token}` (Sanctum) | `config/sanctum.php` |
| 토큰 이름 | `frontend` (로그인·가입 시 `createToken('frontend')`) | `AuthController` |
| 토큰 만료 | `expiration = null` → **무기한** → 7장 | `config/sanctum.php` |
| 본문 형식 | JSON (`Accept: application/json`) | — |
| CORS | 웹 SPA 전용(`FRONTEND_URL`) — 네이티브 앱은 CORS 미적용 | `config/cors.php` |

### 2.2 응답 형태

```json
// 단건
{ "data": { ... } }

// 목록
{ "data": [ ... ], "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 30, "last_page": 2 } } }
```

### 2.3 에러

| 상태 | 본문 | 앱 처리 |
|---|---|---|
| 401 | `{ "message": "Unauthenticated." }` — `Accept` 헤더와 무관하게 **항상 JSON** | 토큰 폐기 → 로그인 화면 |
| 403 | 권한 없음 | 안내 후 이전 화면 |
| 404 | `{ "message": "요청한 API를 찾을 수 없습니다." }` | 안내 |
| 422 | `{ "message": ..., "errors": { "필드": ["..."] } }` | 필드별 오류 표시 |
| 429 | 로그인 시도 제한(`LoginThrottleService`) | 남은 시간 안내 후 재시도 |
| 500 | 일반 오류 | 일반 오류 안내 |

### 2.4 페이지네이션

- 요청: `page`(기본 1), `per_page`(기본 20, **1~100으로 clamp** — 100 초과 요청은 100으로 처리)
- 응답: `meta.pagination`

### 2.5 날짜·시각

- `service_date` — `YYYY-MM-DD`, **KST 벽시계** 기준
- `service_time` — `HH:mm` 24시간. 저장 시 한 자리 시각(`7:30`)은 두 자리(`07:30`)로 정규화된다
- **앱은 시각을 KST 문자열로 다룬다.** 서버도 같은 기준으로 마켓 커트오프·자동 취소를 판정하므로, 앱에서 기기 타임존으로 변환해 다시 계산하지 않는다

### 2.6 역할·권한

| 역할 | 값 | 순위 | 기본 권한 |
|---|---|---|---|
| 최고 관리자 | `Super Admin` | 40 | 전체 |
| 관리자 | `Admin` | 30 | 전체 |
| 운영자 | `Operator` | 20 | `order.create`, `order.status.update`, `dispatch.assign` |
| 기사 | `Driver` | 10 | 없음 (가져오기·요금 제안은 역할 규칙으로 허용) |
| 등록자 | `Customer` | 10 | `order.create` |

- 권한 문자열: `order.create` / `order.status.update` / `dispatch.assign`
- **앱은 역할·권한을 하드코딩하지 않고 `GET /auth/me`의 `permissions`를 따른다.** (역할 문자열도 응답값을 그대로 사용)
- 계정 제재: `moderation_status` = `active` / `watch` / `restricted` / `suspended` — `restricted`·`suspended`는 운행 활동이 막힌다
- 계정 상태: `status` = `active` / `inactive` / `suspended`

### 2.7 실시간

- `GET /api/events?token={token}` (SSE, **미들웨어 밖**) — EventSource가 헤더 인증을 못 해 쿼리로 토큰을 전달한다
- 이벤트: `notification`(알림 변화) / `message`(채팅 안읽음 변화) — **내용이 아니라 신호**다. 신호를 받으면 앱이 해당 API를 다시 조회한다
- 서버는 20초마다 스트림을 끝내고 `retry: 10000` 힌트를 보낸다 → 클라이언트는 재연결
- 네이티브 앱의 수신 방식은 7장

### 2.8 푸시

- 현재 알림 채널: `database`(앱 내 알림 목록) + `WebPushChannel`(**브라우저 웹푸시**)
- **FCM/APNs·디바이스 토큰 테이블 없음** → 7장
- `POST /push-subscriptions`는 웹푸시 구독 전용이라 앱에서 쓰지 않는다

### 2.9 이미지

| 용도 | 경로 | 인증 |
|---|---|---|
| 커뮤니티 이미지 | `GET /api/community/images/{filename}` | 없음 (공개) |
| 채팅 이미지 | `GET /api/chat/images/{filename}` | 없음 (공개) |
| 증빙 이미지 | `GET /api/verification/images/{filename}` | 없음 (공개) |
| 채팅 이미지 업로드 | `POST /api/chats/images` | 필요 |

- 공개 경로는 `<img>` 태그가 `Authorization` 헤더를 못 보내는 제약 때문에 열려 있다. 앱은 헤더를 쓸 수 있으므로 인증 경로로 좁히는 것을 검토한다(7장)

### 2.10 공유 링크

- `GET /api/public/orders/{token}` — 인증 없이 접근(토큰을 아는 사람만), `throttle:60,1`
- 발급: `POST /api/orders/{order}/share` (등록자만)

## 3. 기사 앱 소비 API

역할: `Driver`

| 기능 | Method | Path |
|---|---|---|
| 로그인/가입/로그아웃/내 정보 | POST/GET/PATCH | `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/me`, `/auth/me` |
| 비밀번호 찾기 | POST | `/auth/password/forgot`, `/auth/password/reset` |
| 마켓 목록 | GET | `/orders?scope=market` |
| 홈 추천 | GET | `/orders/recommendations` |
| 왕복 노선 추천 | GET | `/orders/return-routes` |
| 찜 목록/토글 | GET/POST | `/orders/favorites`, `/orders/{order}/favorite` |
| 상세 | GET | `/orders/{order}` |
| 가져오기(claim) | POST | `/orders/{order}/claim` |
| 왕복 일괄 가져오기 | POST | `/orders/batch-claim` |
| 신청 철회 / 만료 철회 | POST | `/orders/{order}/claim/withdraw`, `/orders/{order}/claim/withdraw-expired` |
| 신청 상태 요약 | POST | `/orders/claims/summary` |
| 상세 정보 요청 | POST | `/orders/{order}/request-details` |
| 요금 제안 | GET/POST/DELETE | `/orders/{order}/offers`, `/orders/{order}/offers/{offer}` |
| 상태 전환 | POST | `/orders/{order}/status` |
| 운행 단계 진행 | POST | `/orders/{order}/ride-step` |
| 처리할 일 | GET | `/actions` |
| 기사 상태/매칭 켬끔 | GET/PATCH | `/me/driver`, `/me/driver/status`, `/me/driver/match` |
| 기사 통계 | GET | `/me/driver/stats` |
| 차량 | GET/POST/PATCH/DELETE | `/me/vehicles`, `/me/vehicles/{vehicle}` |
| 매칭 설정 | GET/POST/PATCH/DELETE | `/me/match-preferences`, `/me/match-preferences/{preference}` |
| 정산 요약/정산 내역 | GET | `/me/settlement`, `/me/settlements` |
| 출금 재원/계좌/출금 신청·내역 | GET/POST | `/me/payables`, `/me/bank-account`, `/me/payouts` |
| 차량·면허 인증 | POST/GET | `/verification/request`, `/verification/requests/mine` |
| 리뷰 작성/목록 | POST/GET | `/orders/{order}/review`, `/reviews` |
| 행동 이벤트(추천 원료) | POST | `/behavior-events` |

## 4. 업체(등록자) 앱 소비 API

역할: `Operator` / `Customer` (권한 `order.create` 보유)

| 기능 | Method | Path |
|---|---|---|
| 인증·프로필 | — | 3장과 동일 |
| 운행 등록(단건) | POST | `/orders` |
| 셋트(묶음) 등록 | POST | `/orders/batch` |
| N건 일괄 등록 | POST | `/orders/bulk` |
| 요약 구조화(위챗 문구 → 운행) | POST | `/orders/structure` |
| 내가 등록/진행 중인 운행 | GET | `/orders?scope=mine&source=registered` |
| 상세/수정/복사 | GET/PATCH/POST | `/orders/{order}`, `/orders/{order}/duplicate` |
| 셋트에서 분리 | POST | `/orders/{order}/detach` |
| 상태 전환 | POST | `/orders/{order}/status` |
| 신청 승인/거절 | POST | `/orders/{order}/claim/{claim}/approve`, `/orders/{order}/claim/{claim}/reject` |
| 요금 제안 수락/거절·편지함 | POST/GET | `/orders/{order}/offers/{offer}/accept`, `/orders/{order}/offers/{offer}/reject`, `/offers/inbox` |
| 완료 운행 일괄 정산 | POST | `/orders/batch-settle` |
| 공유 링크 발급 | POST | `/orders/{order}/share` |
| 운행 템플릿 | GET/POST/DELETE | `/order-templates`, `/order-templates/{template}` |
| 기간 통계 | GET | `/stats/orders` |
| 리뷰 목록 | GET | `/reviews` |
| 행동 이벤트 | POST | `/behavior-events` |

## 5. 공용 API (두 앱 모두)

| 기능 | Method | Path |
|---|---|---|
| 공통 옵션(드롭다운) | GET | `/options/orders` |
| 알림 목록/읽음 | GET/POST | `/notifications`, `/notifications/read` |
| 실시간 신호(SSE) | GET | `/events?token={token}` |
| 채팅 목록/시작/방 | GET/POST | `/chats`, `/chats/{conversation}` |
| 채팅 증분 동기화 | GET | `/chats/{conversation}/sync` |
| 메시지 전송/삭제 | POST/DELETE | `/chats/{conversation}/messages`, `/chats/{conversation}/messages/{message}` |
| 모두 읽음 | POST | `/chats/read-all` |
| 구조화 요청(채팅 발의) | POST | `/chats/{conversation}/requests`, `/chats/{conversation}/requests/{message}/resolve` |
| 이미지 업로드/보관함 | POST/GET/DELETE | `/chats/images`, `/chats/images/archive`, `/chats/images/archive/{message}` |
| 커뮤니티 | GET/POST/PUT/DELETE | `/community/posts`, `/community/posts/{post}`, `/community/posts/{post}/like`, `/community/posts/{post}/vote`, `/community/posts/{post}/comments`, `/community/users/{user}` |
| 고객지원 | GET/POST | `/support/posts`, `/support/tickets`, `/support/tickets/mine` |
| 신고 | GET/POST | `/reports/options`, `/reports` |

## 6. 앱이 쓰지 않는 API (관리자 웹 전용)

`/admin/*` 전체 — 사용자·기사 관리, 증빙 심사, 자동 등록 설정, 출금·수금 처리, 신고 처리, 운영 개입(제재·숨김·보류·강제취소), 채팅 운영, 미매핑 용어 관리, 공지·문의 관리.

앱에서 관리자 기능을 제공할 계획이 없다면 이 그룹은 **웹 전용 계약**으로 유지한다.

## 7. 미확정 — 앱 착수 전 결정 목록

| # | 항목 | 현재 상태 | 결정할 것 |
|---|---|---|---|
| 7-1 | **API 버전닝** | 경로에 버전 없음 | 앱 배포 주기가 웹과 달라지면 하위 호환 깨짐. `/api/v1` 도입 여부와 시점 |
| 7-2 | **토큰 만료·갱신** | `expiration = null`(무기한), 갱신 수단 없음 | 만료 기간, 리프레시 토큰 도입 여부, 강제 로그아웃(기기 분실) 수단 |
| 7-3 | **다중 기기 토큰** | 토큰 이름이 `frontend` 고정 | 앱은 기기별 이름(`app-ios`/`app-android` + 기기 식별자)로 발급해 기기별 폐기 가능하게 |
| 7-4 | **푸시 채널** | 웹푸시(`WebPushChannel`)만 존재 | `device_tokens` 테이블 + FCM/APNs 어댑터, 카카오 알림톡·이메일 필요 여부 |
| 7-5 | **실시간 수신 방식** | SSE(EventSource 전용) | 앱에서 SSE를 스트리밍으로 구현할지, `once=1` 폴링(현재 5초)으로 갈지 — 배터리·지연 트레이드오프 |
| 7-6 | **앱 전용 최소 응답** | 목록 행에 웹 카드용 필드가 함께 내려옴(`OrderWorkspaceListBuilder`) | 앱 화면에 불필요한 필드를 줄일지(대역폭), 아니면 웹과 동일 계약을 유지할지 |
| 7-7 | **이미지 접근** | 커뮤니티·채팅·증빙 이미지가 인증 없이 공개 | 앱은 헤더 인증이 가능하므로 인증 경로로 좁힐지(공개 URL 유출 위험) |
| 7-8 | **역할·권한 단일 소스** | 백엔드 `App\Models\User` 상수와 프런트 `frontend/src/data/roles.js`가 중복 | 앱이 들어오면 3중 중복 — 선행 작업 4번(단일 소스화)에서 정리 |
| 7-9 | **오류 코드 세분화** | 상태 코드 + `message` 문자열뿐 | 앱이 분기할 안정적 오류 코드(문자열 message에 의존하지 않도록) 필요 여부 |

## 8. 이 문서 이후 (선행 작업 연결)

| 순서 | 작업 | 이 계약서와의 관계 |
|---|---|---|
| 4 | 역할·권한 단일 소스화 | 2.6 · 7-8 확정 |
| 5 | 인증·토큰 정책 확정 | 2.1 · 7-2 · 7-3 확정 |
| 6 | `device_tokens` + FCM/APNs | 2.8 · 7-4 확정 |
| 7 | 실시간 방식 확정 | 2.7 · 7-5 확정 |
| 8 | 도메인·푸시 계정·비밀번호 정리 | Base URL·푸시 자격 증명 확보(사용자 작업) |
