# NoWhere API 문서

- **기본 URL**: `http://localhost:8000/api`
- **인증**: `Authorization: Bearer {token}` (Laravel Sanctum) — 별도 표기 없는 모든 엔드포인트는 인증 필요
- **SSE 실시간**: `GET /api/events?token={token}` — EventSource는 헤더 인증 불가 → 쿼리로 토큰 전달
- **응답 형식**: `{ "data": ... }` / 목록은 `{ "data": [...], "meta": { "pagination": {...} } }`
- **권한 표기**: (기사) = Driver 역할만 / (등록자) = 운행 소유자만 / (관리자) = Admin·Super Admin만
- **운행 상태 흐름**: [ORDER_FLOW.md](./ORDER_FLOW.md) 참조

---

## 1. 인증 (Auth)

### POST /auth/register — 회원가입 (인증 밖)
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `name` | string (≤50) | ✅ |
| `email` | string (≤255, unique) | ✅ |
| `password` | string (≥8자) | ✅ |

신규 가입자는 **Driver** 역할로 생성된다. 응답 201: `{ "data": { "token": "...", "user": {...} } }`

### POST /auth/login (인증 밖)
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `login` | string | ✅ (`email`과 택1) |
| `email` | string | ✅ (`login`과 택1) |
| `password` | string | ✅ |

`login`에는 **이메일 또는 전화번호**를 넣는다. 전화번호는 하이픈·공백·점 유무와 무관하게 매칭된다. 같은 번호를 여러 계정이 쓰면 관리자(Admin/Super Admin)를 우선 매칭한다.

응답: `{ "data": { "token": "...", "user": {...} } }`

### GET /auth/me
내 정보 반환. `user`에는 역할·권한·레벨(XP)·차량·면허 인증 여부·최근 XP 이벤트가 포함된다.

### PATCH /auth/me — 프로필 수정
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `name` | string (≤50) | ✅ |
| `phone` | string (≤20) | ❌ |

### POST /auth/logout
토큰 폐기.

### GET /events (SSE, 인증 밖)
- `?token=` — 액세스 토큰
- 이벤트: `notification` / `message` — 알림·채팅 안읽음 변화 신호
- 서버: 20초마다 스트림 종료 후 `retry: 10000` 재연결 힌트 전송 (클라이언트는 오류 시 5초 뒤 재연결)

---

## 2. 공통 옵션

### GET /options/orders — 프론트 드롭다운 옵션
응답: `{ "data": { "statusOptions", "serviceOptions", "channelOptions", "companyOptions" } }`

---

## 3. 운행 (Order)

### GET /orders — 목록 + 필터
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `scope` | `market`(마켓) / `mine`(내 운행) | `market` |
| `tab` | `내 운행`용: 진행중/완료/취소/초안 | `진행중` |
| `source` | `mine`용: `registered`(등록) / `received`(받은 운행, 기본) / `history`(히스토리) / `all` | `received` |
| `search` | 운행번호·고객명·출발·도착 검색 | — |
| `service_type` | `pickup`·`sending`·`landing` | — |
| `date` | 특정 날짜 `YYYY-MM-DD` | — |
| `region` | 출발·도착 지역 검색 | — |
| `vehicle_type` | 차량명 (스타리아 등) | — |
| `min_amount` | 최소 금액 (expected_revenue) | — |
| `max_amount` | 최대 금액 | — |
| `min_passengers` | 최소 인원 | — |
| `sort` | `latest`·`date`·`amount`·`amount_asc` | `latest` |
| `page` / `per_page` | 페이징 | 1 / 20 |

마켓(`scope=market`)은 status가 공개·거래중·수락대기이고 내 운행이 아닌 것만 노출.
마켓 응답의 각 항목에 `owner`(등록자 신뢰 정보) 포함:
`{ id, name, rating, review_count, completed_count }` — 평점·리뷰 수·완료 운행 수 (N+1 없이 bulk 계산).

### GET /orders/return-routes — 왕복 노선 추천
내가 맡은 운행의 하차지 근처에서 시작하는 마켓 운행을 왕복 추천으로 반환한다. 파라미터 없음.

### GET /orders/recommendations — 홈 추천
매칭 설정·운행 이력 기반으로 홈에 노출할 추천 운행을 반환한다. 각 항목에 `match_score`(조건%)·`match_reasons`(근거 체크리스트)가 포함된다. 선호도(조건 일치율)가 `recommendation.min_match_score`(기본 60%) 미만인 운행은 제외된다.

### POST /orders — 운행 생성 (인증 + 생성 권한)
| 파라미터 | 설명 |
|---|---|
| `customer_name` | 고객명 (≤100) |
| `customer_phone` | 고객 연락처 (≤40) |
| `pickup_location` / `dropoff_location` | 출발·도착지 (≤200) |
| `service_type` | `pickup`·`sending`·`landing` |
| `service_date` / `service_time` / `service_datetime` | 서비스 일시 |
| `flight_number` | 항공편 (≤20, 공항 운행) |
| `vehicle_type` | 차량명 (≤50) |
| `passenger_count` / `luggage_count` | 인원·짐 (≥0) |
| `expected_revenue` | 금액 (≥0) |
| `reservation_company` / `reservation_channel` | 예약처·채널 |
| `is_priority` | 긴급 배지 (boolean) |
| `line_items[]` | 상세 항목 (셋트 구성용) |

응답 201: `{ "data": { "id", "orderNumber", "status" } }` — 등록 시 XP +10.

### POST /orders/batch — 셋트 일괄 생성
| 파라미터 | 설명 |
|---|---|
| `group_name*` | 셋트 이름 (≤100) |
| `orders*` | 2~30개 배열, 각 항목은 운행 필드 동일 (line_items 포함 가능) |

### POST /orders/structure — AI 구조화
| 파라미터 | 설명 |
|---|---|
| `summary*` | 원문 요약 텍스트 (≤2000) |

응답: `{ "data": { "structured": {...} } }` (구조화 실패 시 422)

### POST /orders/batch-settle — 완료 운행 일괄 정산 (등록자)
| 파라미터 | 설명 |
|---|---|
| `ids*` | 운행 id 배열 (1~100개, 등록자 본인+완료 상태만) |

응답: `{ "data": { "settled": N } }`

### POST /orders/batch-claim — 왕복 체인 일괄 가져오기 (기사)
| 파라미터 | 설명 |
|---|---|
| `order_ids*` | 운행 id 배열 (1~10개) |

응답: `{ "data": [{ id, ok, message }], "summary": { requested, succeeded, failed } }`

### POST /orders/claims/summary — 가져오기 요청 상태 요약
| 파라미터 | 설명 |
|---|---|
| `order_ids*` | 운행 id 배열 (≤20) — 일괄요청중 카드용 |

응답: `{ "data": [{ id, status, claimedAt, claimBatchId }] }`

### GET /orders/{order} — 상세
라인아이템, 셋트면 그룹 전체 일정 포함. 응답에 `claims`(대기 신청+기사 정보·평점), `my_review`, `group`, `statusOptions`, `nextTransitions` 포함.

### PATCH /orders/{order} — 수정 (등록자)
생성과 동일한 운행 필드.

### POST /orders/{order}/status — 상태 전환
| 파라미터 | 설명 |
|---|---|
| `status*` | [ORDER_FLOW.md](./ORDER_FLOW.md) 전이 규칙 내 상태 |
| `cancel_reason` | 취소 사유 (≤500, 취소 시) |
| `actual_revenue` | 실제 수익 (≥0, 완료 시) |

응답: `{ "data": { id, status, ride_step, ride_step_times } }`

### POST /orders/{order}/ride-step — 운행중 단계 진행 (기사, 운행 수행자)
| 파라미터 | 설명 |
|---|---|
| `actual_revenue` | 실제 수익 (≥0, 선택) |

운행시작→픽업 도착→승객 도착→출발→이동중→도착지 도착 순서로 진행한다. **도착지 도착 = 자동 완료 처리**.
응답: `{ "data": { id, status, ride_step, ride_step_times } }`

### POST /orders/{order}/claim — 마켓 운행 가져오기 (기사)
파라미터 없음. 상태가 공개/거래중/수락대기인 남의 운행만 가능. 같은 운행에 여러 기사 동시 신청 가능.
가져오기 시 원 등록자(`original_owner_id`)가 기록되어 상호 리뷰의 대상이 된다.
응답: `{ "data": { id, status } }`

### POST /orders/{order}/claim/withdraw — 가져오기 신청 철회 (신청 기사)
파라미터 없음. 남은 대기 신청이 없으면 운행이 마켓으로 복귀.

### POST /orders/{order}/claim/withdraw-expired — 만료 신청 철회 (신청 기사)
파라미터 없음. 30분(1,800초) 만료된 요청만 철회 가능.

### POST /orders/{order}/claim/{claim}/approve — 신청 승인 (등록자)
파라미터 없음. 해당 신청 기사에게 운행이 넘어가고(`accepted`), 나머지 대기 신청은 자동 거절된다.

### POST /orders/{order}/claim/{claim}/reject — 신청 거절 (등록자)
파라미터 없음. 남은 대기 신청이 없으면 운행이 마켓으로 복귀.

### POST /orders/{order}/request-details — 상세 정보 요청 (기사 → 등록자)
| 파라미터 | 설명 |
|---|---|
| `reason` | 요청 사유 (≤100, 선택) |
| `message` | 요청 메모 (≤500, 선택) |

운행 정보가 부족할 때 등록자에게 DB/웹 푸시 알림을 보낸다. 본인 운행에는 요청 불가.

### POST /orders/{order}/duplicate — 복사
동일 내용을 초안 상태로 새로 만든다. 응답 201: `{ "data": { "id" } }`

### POST /orders/{order}/detach — 셋트에서 분리
셋트 그룹에서 개별 운행을 분리해 단일 운행으로 전환.

### POST /orders/{order}/review — 리뷰 작성
| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `rating` | integer 1~5 | ✅ | 별점 |
| `content` | string (≤500) | ✅ | 후기 |

- 완료(`completed`) 또는 정산(`settled`) 운행에서만 가능
- 운행 당사자(등록자 ↔ 수행자)가 상대에게 리뷰
- 운행당 작성자 1회 제한 (중복 시 422)

### GET /reviews — 리뷰 목록 + 평점 요약
| 파라미터 | 설명 |
|---|---|
| `user_id` | 받은 리뷰 조회 (reviewee) — 지정 시 summary 포함 |
| `reviewer_id` | 내가 쓴 리뷰 조회 |

응답: `{ "data": [...], "summary": { rating, count, distribution(5~1별 개수) } | null }` (최근 50건)

### GET /actions — 처리할 일 (Action Center)
운행 관련 대기 액션(가져오기 승인·요금 제안·채팅 요청)을 한 페이지로 모은 요약.
응답: `{ "data": { claims, offers, chat_requests } }`

### GET /stats/orders — 기간 통계
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `days` | 최근 N일 집계 (1~90) | 7 |
| `mine` | `true`면 본인 운행만 집계 (드라이버 대시보드) | false |

응답: `{ data: { period, upcoming(오늘/내일), summary, daily, monthly, statusDistribution } }`

---

## 4. 요금 제안 (Offer)

### GET /offers/inbox — 제안 받은 편지함 (등록자)
대기 제안이 있는 내 공개 운행 목록 (비교·수락용).

### GET /orders/{order}/offers — 제안 목록
등록자는 전체, 기사는 본인 제안만 조회.

### POST /orders/{order}/offers — 요금 제안 (기사)
| 파라미터 | 설명 |
|---|---|
| `amount*` | 제안 금액 (1000 ~ 10,000,000) |
| `message` | 제안 메모 (≤500, 선택) |

응답 201: `{ "data": { id, order_id, amount, status } }`

### POST /orders/{order}/offers/{offer}/accept — 제안 수락 (등록자)
파라미터 없음. 제안한 기사에게 운행이 넘어간다.

### POST /orders/{order}/offers/{offer}/reject — 제안 거절 (등록자)
파라미터 없음. 운행은 마켓에 남는다.

### DELETE /orders/{order}/offers/{offer} — 제안 철회 (제안 기사)
본인의 대기 제안만 철회 가능.

---

## 5. 채팅 (Chat)

### GET /chats — 대화 목록
응답 항목: `id`, `order_id`, `counterpart`, `order`(연결된 운행 카드: 노선·날짜·금액·상태), `last_message`, `unread_count`, `last_message_at`
> 최적화: 대화당 모든 메시지를 로드하지 않고 **최신 1건만** 로드한다.

### POST /chats — 대화 시작
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `user_id` | integer | ✅ (자기 자신 불가) |
| `order_id` | integer | ❌ |

### GET /chats/{conversation} — 메시지 목록
- 상대 메시지를 **읽음 처리** (read_at 갱신)
- 메시지 항목: `id`, `user_id`, `body`, `created_at`, `read`

### GET /chats/{conversation}/sync — 증분 동기화
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `after_id` | 이 id 이후의 새 메시지만 | 0 |

3초 폴링용. 응답: `{ "data": { "messages": [...], "read_ids": [...] } }`

### POST /chats/{conversation}/messages — 메시지 전송
| 파라미터 | 설명 |
|---|---|
| `body` | 텍스트 (≤2000, 이미지만 보낼 땐 생략) |
| `image` | 단일 이미지 (≤5MB, jpeg/jpg/png/webp/gif) |
| `images` | 여러 장 배열 (≤9) — 한 말풍선에 묶음 |
| `image_path` / `image_paths` | 사전 업로드한 경로 재사용 (≤9) |

### DELETE /chats/{conversation}/messages/{message} — 메시지 삭제
본인 메시지만. 이미지 메시지면 참조되지 않는 파일도 함께 정리.

### POST /chats/{conversation}/requests — 구조화 요청 전송
| 파라미터 | 설명 |
|---|---|
| `type*` | `approval`(승인)·`time_change`(시간 변경)·`route_change`(경로 변경)·`payment_change`(요금 협의)·`cancel`(취소) |
| `payload*` | 요청 데이터 (object) |

운행 업무센터의 승인/시간 변경/경로 변경/요금 협의/취소 카드 생성.

### GET /chats/images/archive — 내 이미지 보관함
내가 보낸 이미지를 페이지네이션(24장)으로 조회. 응답 항목: `{ id, image_path, url, created_at }`

### POST /chats/images — 이미지 사전 업로드
| 파라미터 | 설명 |
|---|---|
| `image*` | 이미지 (≤5MB) |

응답 201: `{ "data": { image_path, url } }` — 내용 지문으로 중복 저장 방지.

### DELETE /chats/images/archive/{message} — 보관함 이미지 삭제
| 파라미터 | 설명 |
|---|---|
| `image_path` | 삭제할 이미지 경로 (query) |

본인 업로드 이미지만 삭제 가능. 파일도 함께 정리.

### GET /chat/images/{filename} (인증 밖)
채팅 이미지 공개 서빙 — `<img>` 태그는 Authorization 헤더를 못 보내므로 인증 밖 경로.

---

## 6. 커뮤니티 (Community)

### GET /community/posts — 목록
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `page` / `per_page` | 페이징 (per_page ≤50) | 1 / 20 |
| `tab` | `my`면 내 글만 | — |
| `sort` | `latest`(최신)·`popular`(좋아요순) | `latest` |
| `category` | 카테고리 필터 | `all` |
| `search` | 내용 검색 | — |
| `author` | 작성자 이름·이메일 검색 | — |
| `period` | `today`·`week`·`month` (작성일 기준) | — |

### POST /community/posts — 글 작성
| 파라미터 | 설명 |
|---|---|
| `content*` | string (≤2000) |
| `category` | 카테고리 (≤20, 기본 `free`) |
| `image` | 파일 (≤5MB) |
| `video_url` | string (≤500) |

### GET /community/posts/{post} — 상세
모든 댓글 포함, 내 좋아요 여부(`is_liked`) 포함.

### PUT /community/posts/{post} — 글 수정
본인 글만. 파라미터는 작성과 동일 (content 필수).

### DELETE /community/posts/{post} — 글 삭제
본인 글만.

### POST /community/posts/{post}/like — 좋아요 토글
응답: `{ "data": { id, is_liked, likes_count } }`

### POST /community/posts/{post}/comments — 댓글
| 파라미터 | 설명 |
|---|---|
| `content*` | string (≤500) |

### DELETE /community/posts/{post}/comments/{comment} — 댓글 삭제
본인 댓글만.

### GET /community/users/{user} — 공개 프로필
응답에 다음이 포함된다:
- `user` — 프로필 (레벨·인증 배지·차량 등)
- `posts` / `orders` — 올린 글 / 등록한 운행 (최근 N건)
- `reviewSummary` — `{ avg, count, breakdown(1~5별 개수) }`
- `reviews` — 받은 리뷰 (작성자·별점·내용)
- `stats` — `{ completed_orders, total_revenue }` 완료 운행 수·누적 매출

### GET /community/images/{filename} (인증 밖)
커뮤니티 이미지 공개 서빙.

---

## 7. 알림 (Notification)

### GET /notifications
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `limit` | 개수 제한 (1~50) | 20 |

응답: `{ "data": [...], "unread_count": N, "total": N }` — 운행 카드용 `order_route`·`order_status` 포함.

### POST /notifications/read — 읽음 처리
| 파라미터 | 설명 |
|---|---|
| `all` | boolean, `true`면 전체 읽음 |
| `ids` | 특정 알림 id 배열 (`all` 아니면) |

### POST /push-subscriptions — 푸시 구독 저장
| 파라미터 | 설명 |
|---|---|
| `endpoint*` | string (≤500, 같은 값이면 갱신) |
| `public_key` | string (≤255) |
| `auth_token` | string (≤255) |

### DELETE /push-subscriptions — 푸시 구독 제거
| 파라미터 | 설명 |
|---|---|
| `endpoint*` | string (≤500) |

---

## 8. 기사 (Driver)

### GET /me/driver — 내 기사 상태
응답: `{ "data": { ... } }` — 상태·매칭 활성화·온라인 시간 등.

### PATCH /me/driver/status — 기사 상태 변경
| 파라미터 | 설명 |
|---|---|
| `status*` | `offline`·`online`·`on_trip`·`rest` |

### PATCH /me/driver/match — 자동 매칭(콜링) 시작/중지
| 파라미터 | 설명 |
|---|---|
| `enabled*` | boolean |

### GET /me/driver/stats — 오늘 통계
온라인 시간·완료 운행 수·수입·진행 중 운행.

### GET /me/settlements — 정산 내역
| 파라미터 | 설명 |
|---|---|
| `from` / `to` | 기간 `YYYY-MM-DD` (선택) |

기간별 완료 운행 목록 + 합계.

### GET /me/vehicles — 내 차량 목록
### POST /me/vehicles — 차량 등록
| 파라미터 | 설명 |
|---|---|
| `name*` | 차량명 (≤100) |
| `type` | 차종 (≤40) |
| `license_plate` | 번호판 (≤30) |
| `color` | 색상 (≤30) |
| `capacity` | 좌석 수 (0~99) |
| `luggage_capacity` | 짐 수용 (0~99) |
| `insurance_expires_at` | 보험 만료일 (date) |
| `photo_path` | 사진 경로 (≤255) |
| `is_default` | 기본 차량 (boolean) |

### PATCH /me/vehicles/{vehicle} — 차량 수정
등록과 동일 필드. 본인 차량만.

### DELETE /me/vehicles/{vehicle} — 차량 삭제
본인 차량만.

---

## 9. 매칭 설정 (Match)

### GET /me/match-preferences — 내 매칭 설정 목록
### POST /me/match-preferences — 매칭 설정 등록
| 파라미터 | 설명 |
|---|---|
| `name*` | 설정 이름 (≤100) |
| `start_time` / `end_time` | 시간대 (`H:i`) |
| `date_range` | `today`·`tomorrow`·`today_tomorrow` |
| `days` | 요일 배열 (1~7) |
| `area` | 지역 (≤100) |
| `max_passengers` | 최대 인원 (1~99) |
| `min_revenue` | 최소 금액 (≥0) |
| `is_active` | 활성 여부 (boolean, 기본 true) |

활성 설정으로 등록하면 현재 열려 있는 매칭 운행을 즉시 알린다.

### PATCH /me/match-preferences/{preference} — 매칭 설정 수정
등록과 동일 필드. 본인 설정만. 활성화 시 보류 매칭 재스캔.

### DELETE /me/match-preferences/{preference} — 매칭 설정 삭제
본인 설정만.

---

## 10. 인증 (Verification)

### POST /verification/request — 차량·면허 인증 신청
| 파라미터 | 설명 |
|---|---|
| `vehicle` | boolean, 차량 인증 신청 |
| `license` | boolean, 면허 인증 신청 |

관리자에게 알림. 이미 인증 완료된 항목만 신청하면 422.

### PATCH /admin/users/{user}/verification — 인증 상태 변경 (관리자)
| 파라미터 | 설명 |
|---|---|
| `vehicle` | boolean, 차량 인증 상태 |
| `license` | boolean, 면허 인증 상태 |

변경 시 대상 사용자에게 알림.

---

## 11. 관리자 (Admin)

> 전부 **관리자(Admin/Super Admin) 전용** — 그 외 역할은 403.

### GET /admin/users — 사용자 목록
페이징(20). 응답 항목: `id`, `name`, `email`, `role`, `is_vehicle_verified`, `is_license_verified`, `created_at`, `completed_count`.

### GET /admin/drivers — 기사 목록
페이징(20). 응답 항목: 기본 정보 + `status`, `status_label`, `status_updated_at`, `vehicle_count`, `today_completed`, `today_income`.

### PATCH /admin/drivers/{user}/status — 기사 상태 강제 전환
| 파라미터 | 설명 |
|---|---|
| `status*` | `offline`·`online`·`on_trip`·`rest` |

기사(Driver) 역할 사용자만 대상. 온라인 시간 누적 처리 포함.

### GET /admin/auto-order-settings — 자동 운행 등록 설정 조회
### PATCH /admin/auto-order-settings — 자동 운행 등록 설정 변경
| 파라미터 | 설명 |
|---|---|
| `is_active` | boolean |
| `min_count` | 최소 등록 건수 (1~50) |
| `max_count` | 최대 등록 건수 (1~50) |
| `owner_user_id` | 등록 계정 (users.id, nullable) |

### GET /admin/auto-orders — 자동 등록 운행 이력
최신순 50건. 응답: `{ data: [{ id, order_number, customer_name, route, service_date, service_time, expected_revenue, status, status_label, created_at }] }`

### POST /admin/auto-orders/delete — 자동 등록 운행 삭제
| 파라미터 | 설명 |
|---|---|
| `ids` | 삭제할 운행 id 배열 |
| `all` | boolean, `true`면 전체 삭제 |

응답: `{ "data": { "deleted": N } }`

---

## 12. 운행 템플릿 (Order Template)

### GET /order-templates — 내 템플릿 목록
최신순 50건.

### POST /order-templates — 템플릿 저장
| 파라미터 | 설명 |
|---|---|
| `name*` | 템플릿 이름 (≤100) |
| `service_type` | 구분 (≤20) |
| `vehicle_type` | 차량 (≤50) |
| `pickup_location` / `dropoff_location` | 출발·도착 (≤255) |
| `passenger_count` | 인원 (0~99) |
| `expected_revenue` | 금액 (0~100,000,000) |
| `flight_number` | 항공편 (≤20) |
| `reservation_company` | 예약처 (≤100) |
| `memo` | 메모 (≤500) |

### DELETE /order-templates/{template} — 템플릿 삭제
본인 템플릿만.
