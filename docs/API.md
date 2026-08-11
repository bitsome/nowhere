# NoWhere API 문서

- **기본 URL**: `http://localhost:8000/api`
- **인증**: `Authorization: Bearer {token}` (Laravel Sanctum)
- **SSE 실시간**: `GET /api/events?token={token}` — EventSource는 헤더 인증 불가 → 쿼리로 토큰 전달
- **응답 형식**: `{ "data": ... }` / 목록은 `{ "data": [...], "meta": { "pagination": {...} } }`

---

## 1. 인증 (Auth)

### POST /auth/login
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `email` | string | ✅ |
| `password` | string | ✅ |

응답: `{ "data": { "token": "...", "user": {...} } }`

### GET /auth/me
인증 필요. 내 정보 반환. 파라미터 없음.

### PATCH /auth/me
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `name` | string (≤50) | ✅ |
| `phone` | string (≤20) | ❌ |

### POST /auth/logout
인증 필요. 토큰 폐기.

### GET /events (SSE, 인증 밖)
- `?token=` — 액세스 토큰
- 이벤트: `notification` / `message` — 알림·채팅 안읽음 변화 신호
- 20초마다 스트림 종료 후 `retry: 10000` 재연결

---

## 2. 오더 (Order)

### GET /orders — 목록 + 필터
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `scope` | `market`(마켓) / `mine`(내 오더) | `market` |
| `tab` | `내 오더`용: 진행중/완료/취소/초안 | `진행중` |
| `source` | `mine`용: `registered` / 받은 오더 | 받은 오더 |
| `search` | 주문번호·고객명·출발·도착 검색 | — |
| `service_type` | `pickup`·`sending`·`landing` | — |
| `date` | 특정 날짜 `YYYY-MM-DD` | — |
| `region` | 출발·도착 지역 검색 | — |
| `vehicle_type` | 차량명 (스타리아 등) | — |
| `min_amount` | 최소 금액 (expected_revenue) | — |
| `max_amount` | 최대 금액 | — |
| `min_passengers` | 최소 인원 | — |
| `sort` | `latest`·`date`·`amount`·`amount_asc` | `latest` |
| `page` / `per_page` | 페이징 | 1 / 20 |

마켓(`scope=market`)은 status가 공개·거래중·수락대기이고 내 오더가 아닌 것만 노출.
마켓 응답의 각 항목에 `owner`(등록자 신뢰 정보) 포함:
`{ id, name, rating, review_count, completed_count }` — 평점·리뷰 수·완료 오더 수 (N+1 없이 bulk 계산).

### POST /orders — 오더 생성 (인증 + 생성 권한)
| 파라미터 | 설명 |
|---|---|
| `customer_name` | 고객명 |
| `pickup_location` / `dropoff_location` | 출발·도착지 |
| `service_type` | `pickup`·`sending`·`landing` |
| `service_date` / `service_time` / `service_datetime` | 서비스 일시 |
| `flight_number` | 항공편 (공항 오더) |
| `passenger_count` / `luggage_count` | 인원·짐 |
| `expected_revenue` / `amount_value` | 금액 |
| `line_items[]` | 상세 항목 (셋트 구성용) |

### POST /orders/batch — 셋트 일괄 생성
| 파라미터 | 설명 |
|---|---|
| `group_name*` | 셋트 이름 (≤100) |
| `orders*` | 2~30개 배열, 각 항목은 오더 필드 동일 |

### POST /orders/structure — AI 구조화
| 파라미터 | 설명 |
|---|---|
| `summary*` | 원문 요약 텍스트 (≤2000) |

응답: `{ "data": { "structured": {...} } }`

### POST /orders/batch-settle — 완료 오더 일괄 정산
| 파라미터 | 설명 |
|---|---|
| `ids*` | 오더 id 배열 (1~100개, 내 오더+완료 상태만) |

### GET /orders/{id} — 상세
### PATCH /orders/{id} — 수정 (소유자만)
생성과 동일한 오더 필드.

### POST /orders/{id}/claim — 마켓 오더 가져오기
파라미터 없음. 상태가 공개/거래중/수락대기인 남의 오더만 가능.
가져오기 시 원 등록자(`original_owner_id`)가 기록되어 상호 리뷰의 대상이 된다.

### POST /orders/{id}/review — 리뷰 작성
| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `rating` | integer 1~5 | ✅ | 별점 |
| `content` | string (≤500) | ✅ | 후기 |

- 완료(`completed`) 또는 정산(`settled`) 오더에서만 가능
- 오더 당사자(등록자 ↔ 수행자)가 상대에게 리뷰
- 오더당 작성자 1회 제한 (중복 시 422)

### POST /orders/{id}/status — 상태 전환
| 파라미터 | 설명 |
|---|---|
| `status*` | 전이 규칙 내 상태: `published`→`trading`→`accepted`→`driving`→`completed`→`settled` |

### POST /orders/{id}/duplicate — 복사
### POST /orders/{id}/detach — 셋트에서 분리

### GET /stats/orders — 기간 통계
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `days` | 최근 N일 집계 (1~90) | 7 |

---

## 3. 채팅 (Chat)

### GET /chats — 대화 목록
응답 항목: `id`, `order_id`, `counterpart`, `order`(연결된 오더 카드: 노선·날짜·금액·상태), `last_message`, `unread_count`, `last_message_at`
> 최적화: 대화당 모든 메시지를 로드하지 않고 **최신 1건만** 로드한다.

### POST /chats — 대화 시작
| 파라미터 | 타입 | 필수 |
|---|---|---|
| `user_id` | integer | ✅ (자기 자신 불가) |
| `order_id` | integer | ❌ |

### GET /chats/{id} — 메시지 목록
- 상대 메시지를 **읽음 처리** (read_at 갱신)
- 메시지 항목: `id`, `user_id`, `body`, `created_at`, `read`

### POST /chats/{id}/messages — 메시지 전송
| 파라미터 | 설명 |
|---|---|
| `body*` | string (≤2000) |

---

## 4. 커뮤니티 (Community)

### GET /community/posts — 목록
| 파라미터 | 설명 | 기본 |
|---|---|---|
| `page` / `per_page` | 페이징 | 1 / 20 |

> 참고: "내 글만 보기", "인기순 정렬"은 **프론트 로컬 필터**로 처리 (백엔드 파라미터 아님)

### POST /community/posts — 글 작성
| 파라미터 | 설명 |
|---|---|
| `content*` | string (≤2000) |
| `image` | 파일 (≤5MB) |
| `video_url` | string (≤500) |

### GET /community/posts/{id} — 상세
### GET /community/users/{id} — 공개 프로필
응답에 다음이 포함된다:
- `user` — 프로필 (레벨·인증 배지·차량 등)
- `posts` / `orders` — 올린 글 / 등록한 오더 (최근 N건)
- `reviewSummary` — `{ avg, count, breakdown(1~5별 개수) }`
- `reviews` — 받은 리뷰 (작성자·별점·내용)
- `stats` — `{ completed_orders, total_revenue }` 완료 오더 수·누적 매출
### POST /community/posts/{id}/like — 좋아요 토글
### POST /community/posts/{id}/comments — 댓글
| 파라미터 | 설명 |
|---|---|
| `content*` | string (≤500) |

### DELETE /community/posts/{id} — 글 삭제 (작성자만)
### GET /community/images/{filename} — 이미지 서빙 (인증 밖)

---

## 5. 알림 (Notification)

### GET /notifications
| 파라미터 | 설명 |
|---|---|
| `limit` | 개수 제한 (기본 30) |

### POST /notifications/read — 읽음 처리
| 파라미터 | 설명 |
|---|---|
| `all` | boolean, `true`면 전체 읽음 (기본 20건만) |

---

## 오더 상태 흐름

```
초안(draft) → 공개(published) → 거래중(trading) → 수락(accepted)
→ 운행중(driving) → 완료(completed) → 정산(settled)
                    ↘ 취소(cancelled)
```
