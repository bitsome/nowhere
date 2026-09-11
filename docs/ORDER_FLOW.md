# 운행 상태 흐름

> 상태: ✅ 현행 — 코드 `app/Models/Order.php::STATUS_FLOW`와 실제 전이 서비스 기준. 운행 상태 흐름의 **단일 소스 문서**다.

운행은 하나의 상태 시스템으로 관리되며, **등록자**가 등록·승인·정산을, **기사**가 신청·운행을 담당한다.

## 1. 상태 목록

| 상태 | 코드 | 설명 |
|---|---|---|
| 초안 | `draft` | 등록자가 운행을 만든 상태 (마켓 미노출) |
| 공개 | `published` | 마켓에 노출 — 기사가 가져오기 신청 가능 |
| 수락 대기 | `acceptance_pending` | 기사 신청 접수 — 등록자의 승인/거절 대기 |
| 거래중 | `trading` | 과거 데이터 대응용 레거시 상태 (신규에서는 사용 안 함) |
| 예약 | `accepted` | 기사 신청이 승인되어 운행 확정 |
| 운행중 | `driving` | 운행 수행 중 — 세부 단계(ride_step) 진행 |
| 완료 | `completed` | 운행 완료 — 정산 대기 |
| 정산 | `settled` | 정산 완료 — 종점 |
| 취소 | `cancelled` | 취소 — 종점 |

## 2. 상태 전이 규칙 (STATUS_FLOW)

```text
draft ──▶ published ──▶ acceptance_pending ──▶ accepted ──▶ driving ──▶ completed ──▶ settled
  ▲           ▲               │                  │  ▲                        │
  │           │               │                  │  └────────── cancelled ────┘
  └───────────┴───────────────┘                  │
        (되돌림·취소)                    (취소 가능: 예약·거래중까지)
```

| 현재 상태 | 다음 상태 |
|---|---|
| `draft` | `published`, `cancelled` |
| `published` | `draft`(비공개 복귀), `cancelled` |
| `acceptance_pending` | `accepted`(승인), `published`(거절·철회로 마켓 복귀), `cancelled` |
| `trading` | `accepted`, `cancelled` |
| `accepted` | `driving`, `cancelled` |
| `driving` | `completed` |
| `completed` | `settled` |
| `settled` | — |
| `cancelled` | — |

> `driving → completed` 이후에는 취소할 수 없다. 완료·정산·취소는 회수 불가능한 종점이다.

## 3. 주요 흐름

### 3-1. 등록·공개 (등록자)

1. 운행 생성 → `draft`
2. 필수 입력(출발지·도착지·차량·구분·서비스 일시·금액) 검사 후 공개 → `published`
3. 공개 즉시 조건 기반 **자동 매칭** 실행 → 적합 기사에게 추천 알림
4. 공개 후 다시 비공개(`draft`)로 되돌리거나 취소할 수 있음

### 3-2. 가져오기 신청·승인 (기사 → 등록자)

1. 기사가 마켓의 `published` 운행을 **가져오기 요청(claim)** → `acceptance_pending`
   - 한 운행에 여러 기사 동시 신청 가능 (`order_claims`)
   - 신청 시 등록자에게 알림 + 채팅에 승인 요청 카드 생성
2. 등록자가 처리:
   - **승인** → `accepted`: 신청 기사에게 운행이 넘어가고, 나머지 대기 신청은 자동 거절
   - **거절** → 해당 신청만 `rejected`, 남은 신청이 없으면 `published`로 복귀 (마켓 재노출)
   - **철회** → 신청 기사 본인이 요청 취소, 마켓 복귀
3. **30분(1,800초) 이내 미승인 시 자동 만료** → 요청 무효, 마켓 복귀

### 3-3. 운행 (기사)

1. 승인 시점에 기사 상태 `on_trip` 자동 전환 + XP +20 (운행 확정 즉시 '운행 중')
2. 승인된 기사가 운행 시작 → `driving`
3. 운행중 세부 단계 진행 (아래 4번 참조)
4. 마지막 단계(도착지 도착) 기록 순간 자동으로 → `completed`
   - 수행 기사는 여기까지가 마지막이며, 정산은 **등록자(원 등록자)** 가 처리한다

### 3-4. 정산·수금 (등록자 → 관리자 → 기사)

1. 등록자가 완료 운행을 선택해 일괄 정산 → `settled` (XP +30)
   - 정산 시점 요율이 `settlements.fee_rate`에 **스냅샷**된다 — 이후 요율을 바꿔도 확정된 금액은 바뀌지 않는다
   - 등록자에게 개별 요율(`users.fee_rate`)이 있으면 그것을 우선 적용한다
2. 정산 원장 생성 시 등록자에게 **운행 대금 입금 안내**(플랫폼 매입 계좌)가 간다 → 수금 상태 `pending`
3. 등록자가 매입 계좌로 입금 → 관리자가 **'수금 확인'**으로 `pending → paid` 확정 (`collected_at`·`collected_by`·`collection_note` 기록)
4. **기사 출금은 수금 완료(`paid`)분만 가능** — 등록자 입금이 확인되기 전에는 출금할 수 없다
5. 기사 출금 신청 → 지급 → 지급 완료

### 3-5. 취소

- `draft` / `published` / `acceptance_pending` / `accepted`(예약) 상태에서 취소 가능
- 취소 시 사유(`cancel_reason`) 기록
- `driving` 이후로는 취소 불가 (강제 종료는 별도 처리 필요)

### 3-6. 자기 수행 (등록자 = 수행자)

등록자가 자기 공개 운행을 기사 모집 없이 본인이 수행한다 (`OrderClaimService::selfDrive`, `claim.cause = self_drive`).

- 가능 조건: 본인 소유 + `published` + 다른 기사의 신청이 없을 때
- 불가: 남의 운행, 아직 공개하지 않은 초안, 이미 기사 신청이 들어온 운행
- 완료·정산하면 등록자와 수행자가 같아 **원장은 하나만** 만들어지고, 위 3-4 수금·출금 흐름에 그대로 올라탄다
- 미확정: 등록자=수행자인 경우의 수금 처리(입금·지급 순환을 없애고 수수료만 납부하는 방향)는 **정책 결정 대기** — 결정 전까지는 동일한 입금 확인 흐름을 따른다

## 4. 운행중 세부 단계 (ride_step)

`driving` 상태 안에서 기사가 단계 스테퍼로 진행한다. 각 단계의 기록 시각이 `ride_step_times`에 남는다.

```text
운행시작 → 픽업장소 도착 → 승객 도착 → 출발 → 도착지로 이동중 → 도착지 도착(= 완료)
```

- 진행 가능 주체: 운행 수행자(기사) 본인만
- **도착지 도착(`arrived`) 기록 = `completed` 전이 자동 처리** (완료 시각·실제 수익 기록)

## 5. 가져오기 신청 상태 (order_claims)

| 상태 | 코드 | 설명 |
|---|---|---|
| 대기 | `pending` | 신청 접수, 승인 대기 |
| 승인 | `approved` | 등록자가 승인 — 이 신청자가 운행 수행 |
| 거절 | `rejected` | 등록자가 거절 (승인 시 나머지 대기 신청 일괄 거절) |
| 철회 | `withdrawn` | 신청 기사 본인이 취소 |

## 6. 전이 시 부수 효과

| 전이 | 부수 효과 |
|---|---|
| `published` | 자동 매칭(`MatchService::matchForOrder`) |
| `accepted` | 남은 요금 제안 정리, 기사 상태 `on_trip`, XP +20, 승인 시각(`approved_at`) 기록 |
| `completed` | 완료 시각(`completed_at`)·실제 수익(`actual_revenue`) 기록, 기사 상태 `online` 복귀, XP +50 |
| `settled` | XP +30 |
| `cancelled` | 취소 사유 기록, 기사 상태 `online` 복귀 |
| 모든 전이 | 운행 소유자에게 상태 변경 알림 |

## 7. 권한 요약

| 액션 | 주체 |
|---|---|
| 등록·공개·비공개·취소 | 등록자 (운행 소유자) |
| 가져오기 요청(claim) | **기사(Driver) 역할만** — 본인 등록 운행 불가 |
| 직접 수행(self_drive) | 등록자 — **본인 공개 운행**이고 기사 신청이 없을 때만 |
| 승인·거절 | 등록자 (운행 소유자) |
| 신청 철회 | 신청한 기사 본인 |
| 운행 시작·단계 진행·완료 | 운행 수행자(기사) 본인 |
| 정산 | 등록자 (원 등록자 `original_owner_id` 또는 미이전 본인 등록) |
| 수금 확인(`pending → paid`) | 관리자만 |
| 출금 신청·지급 | 수행 기사 본인 / 관리자 지급 |

## 8. 구현 위치

- 상태 상수·전이 규칙: `app/Models/Order.php` (`STATUS_FLOW`, `canTransitionTo`, `transitionTo`)
- 신청/승인/거절/철회/만료/직접 수행: `app/Services/Order/OrderClaimService.php` (`selfDrive`)
- 운행 전이·정산·자동 매칭: `app/Services/Order/OrderTransitionService.php`
- 수수료 계산·수금·출금: `app/Services/Settlement/SettlementService.php` (`feeRateFor`, `calculateFee`, 수금 확인)
- 수금 상태 상수: `app/Models/Settlement.php` (`COLLECTION_PENDING`, `COLLECTION_PAID`)
- 상태 라벨: `Order::statusOptions()` (초안/공개/거래중/예약/운행중/완료/정산/취소/수락 대기)
