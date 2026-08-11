# Business Model

## 목적
- 이 문서는 NoWhere의 비즈니스 모듈 구조와 권장 개발 순서를 정의한다.
- 핵심 원칙은 `Order`를 먼저 만드는 것이 아니라, `Order`를 구성하는 선행 비즈니스 데이터를 먼저 구축하는 것이다.
- Foundation이 충분히 닫힌 상태에서 Business Foundation을 만들고, 그 위에 `Order`, `Dispatch`, `Settlement`를 올리는 순서를 기본 전략으로 삼는다.

## 핵심 비즈니스 모듈
- NoWhere의 핵심 비즈니스 모듈은 아래 3개다.
  - `Order`
  - `Dispatch`
  - `Settlement`
- 다만 실제 개발 착수 순서는 위 3개를 바로 만드는 방식이 아니라, 먼저 공통 기반과 선행 참조 데이터를 구축하는 방식으로 진행한다.

## 권장 개발 순서

### 1. Foundation
- `Auth`
- `User`
- `Role`
- `Permission`
- `Board`
- `File Manager`
- `Markdown Editor`
- `DataTable`
- `Form`
- `Modal`
- `Dialog`
- `Toast`

### 2. Business Foundation
- `Customer`
- `Company`
- `Vehicle`
- `Driver`
- `Common Code`

### 3. Business
- `Order`
- `Dispatch`
- `Settlement`

## 왜 Order를 바로 만들지 않는가
- `Order`는 단독 모듈처럼 보이지만 실제로는 여러 선행 데이터를 참조하는 조합형 비즈니스 모듈이다.
- `Order`를 먼저 만들면 화면과 저장 구조 안에 선택 데이터, 공통 코드, 참조 모델이 뒤섞이기 쉽다.
- 공통 데이터가 없는 상태에서 `Order`를 만들면 임시 입력 필드, 임시 텍스트 값, 임시 선택 구조가 생기고 이후 리팩토링 비용이 크게 증가한다.
- 따라서 `Order`는 비즈니스의 시작처럼 보이지만, 실제로는 Business Foundation이 정리된 뒤에 시작하는 것이 장기적으로 가장 효율적이다.

## Order가 참조하는 주요 데이터
- 예약업체
- 고객
- 탑승객
- 차량종류
- 담당기사
- 출발지
- 도착지
- 결제방법
- 상태

- 위 항목 중 대부분은 `Order` 자체보다 선행 데이터 모듈에 속한다.
- 따라서 `Order` 화면은 가능한 한 직접 입력보다 기존 데이터를 선택하는 구조로 가야 한다.

## Order 이전 최소 선행 모듈

### 1. Customer
- 고객목록
- 고객등록
- 고객검색

### 2. Company
- 여행사
- 파트너
- 예약업체

- 예시
  - `KLOOK`
  - `KKDAY`
  - `Trip.com`
  - `직접예약`

### 3. Vehicle
- 차량종류
- 차량정보

### 4. Driver
- 기사목록
- 기사정보
- 기사상태

## Order 단계의 목표 상태
- `Order`를 만들 때 필요한 참조 데이터는 이미 준비되어 있어야 한다.
- 따라서 `Order` 화면은 아래처럼 기존 데이터를 선택하는 형태로 구성하는 것이 이상적이다.

```text
업체
▼ KLOOK

고객
▼ 홍길동

기사
▼ 김기사

차량
▼ 9인승
```

- 즉 `Order`는 데이터를 생성하는 화면이 아니라, 이미 준비된 비즈니스 기반 데이터를 조합해 예약과 배차 흐름을 만드는 화면이어야 한다.

---

# ORDER BUSINESS MODEL

## 목적

NoWhere는 일반 예약 관리 시스템이 아니다.

NoWhere는 기사와 기사 사이에서 오더를 등록하고 거래하는 플랫폼이다.

모든 기능은 "예약"이 아닌 "오더(Order)"를 중심으로 설계한다.

---

## 핵심 개념

Order는 하나의 운행 업무이다.

Order는 생성되고,
필요하면 Set으로 묶이며,
다른 기사에게 거래될 수 있고,
최종적으로 운행 완료 및 정산된다.

Trade는 Order의 일부 기능이며,
Order보다 상위 개념이 아니다.

---

## Order Life Cycle

Order Create

↓

Order Edit

↓

Single / Set

↓

Publish

↓

Trade

↓

Accepted

↓

Driving

↓

Completed

↓

Settlement

---

## 개발 원칙

- 모든 기능은 Order를 중심으로 개발한다.
- Trade는 Order의 상태 변화 중 하나이다.
- Set은 Order를 묶는 그룹일 뿐이며 Order를 소유하지 않는다.
- Order는 항상 독립 객체이다.
- Order는 Single 또는 Set 상태가 될 수 있다.
- Set은 언제든 생성, 분리, 병합, 해제할 수 있다.
- Order 데이터는 변경하지 않고 그룹 정보(Set)만 변경한다.
- 하나의 Order는 동시에 하나의 Set에만 속할 수 있다.

---

## Order Module

Order

├── Order List
├── Order Detail
├── Order Create
├── Order Edit
├── Order Delete
├── Order Copy
├── Order Status
├── Order Timeline
└── Order History

---

## Order Set Module

Order Set

├── Set Create
├── Set Split
├── Set Merge
├── Set Dissolve
├── Order Add
├── Order Remove
└── Order Sort

---

## Trade Module

Trade

├── Publish
├── Take Order
├── Transfer
├── Cancel Transfer
├── Trade History
└── Complete

---

## 개발 순서

Phase 1

- Order List
- Order Detail
- Order Create
- Order Edit

Commit

↓

Phase 2

- Single
- Set
- Set Split
- Set Merge
- Set Dissolve

Commit

↓

Phase 3

- Publish
- Take Order
- Transfer
- Trade History

Commit

↓

Phase 4

- Driving
- Completed
- Settlement

---

## UI 원칙

Order 화면은 일반 CRUD 화면이 아니다.

운영자가 오더를 빠르게 판단하고 거래할 수 있도록 구성한다.

목록에서는 최소한의 정보만 제공한다.

표시 항목

- 오더번호
- 노선
- 픽업일시
- 금액
- 상태

상세 화면에서만

- 항공편
- 메모
- 수하물
- 상세주소
- 기타 정보를 표시한다.

---

## 공용 데이터 계약(API Rule)

- 비즈니스 모델은 API 규칙 기반 공용 데이터 계약으로 만든다.
- 백엔드와 프론트엔드는 그 계약을 같이 사용한다.
- Blade, Vue, Controller, Service, Builder, API 응답은 같은 구조를 유지한다.
- 목록 표시 데이터는 화면별로 조립하지 않고 공통 builder가 만든 계약 구조를 그대로 사용한다.
- `Order`, `Order Set`, `Trade`의 데이터 표현 규칙도 이 공용 계약을 기준으로 확장한다.

---

## AI 개발 규칙

- Order를 중심으로 개발한다.
- 새로운 기능은 반드시 Order Life Cycle 안에서 위치를 결정한다.
- 완료된 기능은 수정하지 않는다.
- 하나의 기능만 개발한다.
- 하나의 기능 완료 후 Commit한다.
- 공통 컴포넌트를 우선 사용한다.
- 동일한 기능의 컴포넌트를 새로 만들지 않는다.
- Order는 항상 독립 객체이며 Set은 그룹 기능만 담당한다.
- Trade는 Order의 상태 변화이며 별도의 독립 비즈니스가 아니다.
- 공용 데이터 계약이 먼저 정의된 뒤 백엔드/프론트엔드 구현을 시작한다.

---

## 최종 구조

```text
Foundation
│
├── Auth
├── User
├── Permission
├── Board
├── File
├── Editor
├── DataTable
├── Form
└── UI Components

Business Foundation
│
├── Customer
├── Company
├── Vehicle
├── Driver
└── Common Code

Business
│
├── Order
├── Dispatch
└── Settlement
```

## 개발 원칙
- Foundation이 완전히 끝나기 전에는 Business 모듈에 깊게 들어가지 않는다.
- Business Foundation이 준비되기 전에는 `Order`를 본격 개발하지 않는다.
- `Order`는 참조 데이터 선택 중심으로 설계하고, 임시 텍스트 입력 기반 구조를 만들지 않는다.
- `Dispatch`는 `Order`, `Driver`, `Vehicle`, `Common Code`가 정리된 뒤에 설계한다.
- `Settlement`는 `Order`와 `Dispatch`의 확정 데이터가 쌓인 뒤에 설계한다.
- 즉 NoWhere는 `Foundation -> Business Foundation -> Business` 순서로 확장한다.
