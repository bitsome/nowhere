# 첫수익 만들기 (등록자 대금 수금 · 정산 · 출금) Spec

## Why
플랫폼이 실제로 돈을 버는 유일한 축(운행 거래 수수료)을 **돈이 실제로 들어오는 상태**까지 끌어올린다.
현재는 수수료가 정산 원장에 숫자로만 남고, 등록자가 플랫폼에 대금을 지불하는 경로가 없어 현금이 한 푼도 들어오지 않는다.
이 스펙은 "등록자가 운행 대금을 입금 → 관리자가 수금 확인 → 수수료 매출 확정"의 첫 수익 흐름을 단일 기준으로 정의한다.

## What Changes
- 정산 원장에 등록자 대금 수금 상태(`collection_status`)와 수금 이력(시각·처리자·메모)을 추가한다. **BREAKING** (기존 `settlements` 행에 컬럼 추가)
- 플랫폼 수수료율·최소 수수료·매입 계좌를 코드 상수에서 `config/settlement.php` + env로 승격한다.
- 등록자 개별 수수료율(`users.fee_rate`)을 관리자가 지정/해제할 수 있게 한다.
- 기사 출금을 **수금 완료분**으로 제한한다(등록자가 입금한 돈만 기사에게 지급).
- 관리자에 '수금 확인' 탭, 등록자에 '정산·입금' 화면, 운영 지표에 '수수료 매출' 그룹을 추가한다.

## Impact
- Affected specs: 수익구조 1축(운행 거래 수수료), 정산·출금, 관리자 운영
- Affected code: `app/Services/Settlement/SettlementService.php`, `app/Models/Settlement.php`, `app/Models/User.php`, `app/Http/Controllers/Api/SettlementController.php`, `app/Http/Controllers/Api/AdminController.php`, `routes/api.php`, `config/settlement.php`, `database/migrations/*`, `frontend/src/views/SettlementView.vue`, `frontend/src/views/RegistrantSettlementView.vue`, `frontend/src/views/AdminView.vue`, `frontend/src/views/MoreView.vue`

## ADDED Requirements

### Requirement: 수수료 정책 config 승격
시스템은 플랫폼 수수료율·최소 수수료·매입 계좌를 `config('settlement.*')`에서 읽어야 한다. 수수료 계산은 한 곳(`SettlementService`)에서만 수행하고, 수수료가 운행금액을 넘지 않도록 보정한다.

#### Scenario: 소액 운행 최소 수수료
- **WHEN** 최소 수수료가 설정되어 있고 요율 계산 수수료가 그보다 작은 경우
- **THEN** 최소 수수료를 적용한다

### Requirement: 정산 시점 요율 스냅샷
정산 원장은 정산이 확정된 시점의 수수료율을 `fee_rate` 컬럼에 남겨야 한다. 이후 운영 중 요율을 바꿔도 이미 확정된 정산 금액은 바뀌지 않는다.

#### Scenario: 요율 변경 후 과거 정산 불변
- **WHEN** 정산 완료 후 운영자가 전역 요율을 올리는 경우
- **THEN** 기존 정산의 fee/net/fee_rate는 그대로 유지된다

### Requirement: 등록자 개별 수수료율
관리자는 등록자(업체)별 개별 수수료율을 지정하거나 해제할 수 있어야 한다. 개별 요율이 있으면 전역 요율 대신 적용하고, 변경은 감사 로그에 남는다.

#### Scenario: 업체 계약 요율 적용
- **WHEN** 등록자에게 개별 요율 2%가 지정된 운행을 정산하는 경우
- **THEN** 해당 운행의 수수료는 2%로 계산되고 원장에 0.02로 스냅샷된다

### Requirement: 등록자 대금 수금(입금 확인)
정산이 생성되면 등록자에게 운행 대금(gross) 입금 안내를 보내고, 관리자가 입금을 확인(`collection_status: pending → paid`)해야 수금이 확정된다. 수금이 확정된 정산만 기사 출금 재원이 된다.

#### Scenario: 첫 수익 전 구간
- **WHEN** 운행 완료 → 정산 생성 → 등록자 입금 안내 → 관리자 입금 확인 → 기사 출금 신청 → 관리자 지급
- **THEN** 각 단계에서 상태가 올바르게 전이되고, 관리자 운영 지표에 수수료 매출이 반영된다

### Requirement: 매출 가시화
관리자 운영 지표는 이번 달 수수료 매출·실효 요율·이번 달 거래액·누적 수수료를 노출해야 한다.

#### Scenario: 수수료 매출 집계
- **WHEN** 이번 달 확정된 정산이 존재하는 경우
- **THEN** 월 수수료 합계와 실효 요율(gross 대비 fee)이 표시된다

## MODIFIED Requirements

### Requirement: 기사 출금
기사 출금 신청은 수금이 완료된(`collection_status = paid`) 미지급 정산만 묶어야 한다. 계좌 미등록·출금 금액 없음·처리 대기 중복은 기존과 동일하게 거절한다.

#### Scenario: 입금 전 출금 차단
- **WHEN** 정산은 생성됐지만 등록자 입금이 아직 확인되지 않은 경우
- **THEN** 기사는 출금을 신청할 수 없다

## REMOVED Requirements
없음
