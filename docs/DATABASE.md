# DATABASE

## DB 설계 원칙
- 현재 프로젝트의 기본 DB 연결은 `sqlite`다.
- 구조 설계는 Laravel Migration 기준으로 관리한다.
- 현재 단계는 `인증/사용자 + 게시판 + 파일관리 + Laravel 기본 인프라` 중심이다.
- 향후 Business Foundation이 시작되면 `Customer`, `Company`, `Vehicle`, `Driver`, `Common Code`를 먼저 추가하고 그 다음 `Order`, `Dispatch`, `Settlement`로 확장한다.

## Naming Rule
- 테이블명은 복수형 snake_case를 사용한다.
- 컬럼명은 snake_case를 사용한다.
- 외래키는 `{singular}_id` 규칙을 사용한다.
- 상태값, 타입값은 문자열 컬럼으로 관리하되 필요 시 인덱스를 함께 둔다.

## 현재 DB 연결
- default connection: `sqlite`
- 기본 DB 파일: `database/database.sqlite`

## Table 목록
- `migrations`
- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `boards`
- `media`
- `orders`
- `order_groups`
- `order_line_items`

## 현재 테이블 구조

### users
- 용도: 사용자 및 운영 계정
- 컬럼
  - `id`
  - `name`
  - `email`
  - `phone`
  - `email_verified_at`
  - `password`
  - `profile_photo_path`
  - `role`
  - `permissions` (`json`)
  - `status`
  - `last_login_at`
  - `login_count`
  - `remember_token`
  - `created_at`
  - `updated_at`
- 인덱스/제약
  - `email` unique
  - `phone` unique
- 비고
  - 현재 권한 구조는 `role` + `permissions(json)` 임시 구조다.

### password_reset_tokens
- 용도: 비밀번호 재설정 토큰 저장
- 컬럼
  - `email` (primary)
  - `token`
  - `created_at`

### sessions
- 용도: 세션 저장
- 컬럼
  - `id` (primary)
  - `user_id`
  - `ip_address`
  - `user_agent`
  - `payload`
  - `last_activity`
- 인덱스/제약
  - `user_id` index
  - `last_activity` index

### cache
- 용도: 캐시 저장
- 컬럼
  - `key` (primary)
  - `value`
  - `expiration`

### cache_locks
- 용도: 캐시 락 저장
- 컬럼
  - `key` (primary)
  - `owner`
  - `expiration`

### jobs
- 용도: 큐 작업 저장
- 컬럼
  - `id`
  - `queue`
  - `payload`
  - `attempts`
  - `reserved_at`
  - `available_at`
  - `created_at`
- 인덱스/제약
  - `queue` index

### job_batches
- 용도: 배치 작업 저장
- 컬럼
  - `id` (primary)
  - `name`
  - `total_jobs`
  - `pending_jobs`
  - `failed_jobs`
  - `failed_job_ids`
  - `options`
  - `cancelled_at`
  - `created_at`
  - `finished_at`

### failed_jobs
- 용도: 실패한 큐 작업 저장
- 컬럼
  - `id`
  - `uuid`
  - `connection`
  - `queue`
  - `payload`
  - `exception`
  - `failed_at`
- 인덱스/제약
  - `uuid` unique
  - `connection`, `queue`, `failed_at` 복합 index

### boards
- 용도: 게시판/공지/문서형 게시글
- 컬럼
  - `id`
  - `type`
  - `title`
  - `content`
  - `user_id`
  - `status`
  - `is_notice`
  - `is_private`
  - `view_count`
  - `created_at`
  - `updated_at`
- 인덱스/제약
  - `type` index
  - `status` index
  - `user_id` foreign key -> `users.id`
- 비고
  - `content`는 Markdown 기반 문서형 본문 저장 용도다.

### orders
- 용도: 오더(예약/운행) 핵심 테이블
- 컬럼
  - `id`
  - `group_id` (nullable FK → `order_groups.id`, nullOnDelete)
  - `order_number` (string 32, unique)
  - `request_label`
  - `service_date`
  - `service_time`
  - `group_type` (nullable — 단일 예약은 null, 셋트는 "셋트")
  - `vehicle_type`
  - `service_type`
  - `original_summary` (text, nullable — AI 구조화 전 원본 입력)
  - `structured_payload` (json, nullable — AI 구조화 결과 원본)
  - `reservation_company` (string 100, index)
  - `customer_name` (string 100, index)
  - `reservation_channel` (string 40, index)
  - `passenger_count` (unsignedInteger, default 1)
  - `luggage_count` (unsignedInteger, nullable)
  - `amount_text` (nullable)
  - `amount_value` (unsignedInteger, nullable)
  - `extra_options` (json, nullable)
  - `pickup_location` (string 150, nullable)
  - `dropoff_location` (string 150, nullable)
  - `flight_number` (string 50, nullable)
  - `scheduled_at` (datetime, nullable)
  - `order_type` (string 30, nullable)
  - `estimated_duration_minutes` (unsignedInteger, nullable)
  - `distance_km` (decimal 8,1, nullable)
  - `expected_revenue` (unsignedInteger, nullable)
  - `status` (string 20, default 'draft', index)
  - `claimed_at` (timestamp, nullable)
  - `user_id` (foreignId → `users.id`, cascadeOnDelete)
  - `created_at`
  - `updated_at`
- 인덱스/제약
  - `order_number` unique
  - `reservation_company` index
  - `customer_name` index
  - `reservation_channel` index
  - `status` index
  - `group_id` foreign key -> `order_groups.id` (nullOnDelete)
  - `user_id` foreign key -> `users.id` (cascadeOnDelete)
- 비고
  - `group_id`가 null이면 Single Order, 값이 있으면 해당 Set에 속한 Order다.
  - Order Life Cycle: draft → published → trading → accepted → driving → completed → settled (취소는 cancelled)
  - `original_summary` / `structured_payload`는 AI 구조화 입출력 보관 용도다.

### order_groups
- 용도: Order를 묶는 Set(그룹) 정보만 관리한다. Order를 소유하지 않는다.
- 컬럼
  - `id`
  - `name` (nullable)
  - `type` (default '셋트')
  - `created_at`
  - `updated_at`
- 규칙
  - Set은 최소 2개의 Order를 가져야 하며, Order가 1개 이하가 되면 자동 해제한다.
  - Order는 동시에 두 개 이상의 Set에 속할 수 없다.
  - Set이 비어있으면 자동으로 삭제한다.

### order_line_items
- 용도: 오더의 복수 일정(line item) 저장
- 컬럼
  - `id`
  - `order_id` (foreignId → `orders.id`, cascadeOnDelete)
  - `scheduled_time`
  - `service_date`
  - `service_month`
  - `service_day`
  - `service_weekday`
  - `service_type`
  - `location` (nullable — 구간 통합 표시)
  - `pickup_location` (string 150, nullable)
  - `dropoff_location` (string 150, nullable)
  - `flight_number` (string 50, nullable)
  - `amount_value` (unsignedInteger, nullable)
  - `amount_text` (string 50, nullable)
  - `passenger_count` (unsignedInteger, nullable)
  - `luggage_count` (unsignedInteger, nullable)
  - `created_at`
  - `updated_at`
- 인덱스/제약
  - `order_id` foreign key -> `orders.id` (cascadeOnDelete)

### media
- 용도: Spatie Media Library 파일 저장
- 컬럼
  - `id`
  - `model_type`
  - `model_id`
  - `uuid`
  - `collection_name`
  - `name`
  - `file_name`
  - `mime_type`
  - `disk`
  - `conversions_disk`
  - `size`
  - `manipulations` (`json`)
  - `custom_properties` (`json`)
  - `generated_conversions` (`json`)
  - `responsive_images` (`json`)
  - `order_column`
  - `created_at`
  - `updated_at`
- 인덱스/제약
  - `model_type`, `model_id` morph index
  - `uuid` unique
  - `order_column` index
- 비고
  - 현재 게시판 첨부파일, 파일관리, 에디터 이미지 선택 구조가 이 테이블을 기준으로 동작한다.

## 관계 요약
- `users` 1 : N `boards`
- `users` 1 : N `orders`
- `orders` 1 : N `order_line_items`
- `order_groups` 1 : N `orders` (nullable, nullOnDelete)
- `users`, `boards`, `orders` 등 업무 모델 N : N 성격의 첨부는 `media` morph 구조로 연결
- `sessions.user_id` 는 사용자 세션 참조용

## ERD

```text
users
 ├──< boards
 ├──< orders
 └──< sessions

order_groups
 └──< orders (nullable)

orders
 └──< order_line_items

users / boards / orders / other models
 └──< media (morph: model_type, model_id)
```

## Index
- `users.email` unique
- `users.phone` unique
- `sessions.user_id` index
- `sessions.last_activity` index
- `cache.expiration` index
- `cache_locks.expiration` index
- `jobs.queue` index
- `failed_jobs.uuid` unique
- `failed_jobs(connection, queue, failed_at)` index
- `boards.type` index
- `boards.status` index
- `orders.order_number` unique
- `orders.reservation_company` index
- `orders.customer_name` index
- `orders.reservation_channel` index
- `orders.status` index
- `media.uuid` unique
- `media.order_column` index

## Foreign Key
- `boards.user_id` -> `users.id` (`cascadeOnDelete`)
- `orders.user_id` -> `users.id` (`cascadeOnDelete`)
- `orders.group_id` -> `order_groups.id` (`nullOnDelete`)
- `order_line_items.order_id` -> `orders.id` (`cascadeOnDelete`)
- 그 외 파일 연결은 정적 FK가 아니라 `media`의 polymorphic 구조로 관리한다.

## UUID
- 현재 `media.uuid`에서만 사용한다.
- 일반 업무 테이블은 아직 `id` bigint auto increment 기반이다.

## SoftDelete
- 현재 기준으로 SoftDelete가 적용된 테이블은 없다.
- 삭제 정책이 필요한 비즈니스 모듈은 향후 모듈 성격에 따라 별도 검토한다.

## Migration 규칙
- 모든 스키마 변경은 Migration으로 관리한다.
- 컬럼 추가는 기존 테이블 `alter` migration으로 누적한다.
- 상태/타입 컬럼은 실제 조회가 많은 경우 index를 함께 검토한다.
- 비즈니스 Foundation 진입 시 `Customer`, `Company`, `Vehicle`, `Driver`, `Common Code`를 우선 테이블화한다.

## Seeder
- 현재 `DatabaseSeeder`, `OrderDemoSeeder`, `OrderSetDemoSeeder`가 존재한다.
- 본격적인 Business Foundation 단계에서는 공통코드, 기본 역할, 초기 업체/차량 종류 시드가 필요하다.

## Factory
- 현재 `UserFactory`, `BoardFactory`, `OrderFactory`, `OrderGroupFactory`가 있다.
- 이후 `CustomerFactory`, `CompanyFactory`, `VehicleFactory`, `DriverFactory` 순서로 확장하는 것이 자연스럽다.

## 성능 최적화
- 현재는 Foundation 단계라 구조 단순성과 명확성을 우선한다.
- 향후 `Order`, `Dispatch`, `Settlement` 단계에서는 아래를 중점 검토한다.
  - 검색 컬럼 인덱스
  - 상태 컬럼 인덱스
  - 날짜 범위 조회 인덱스
  - 조인 빈도가 높은 참조 컬럼 최적화

## 향후 추가 예정 테이블 방향
- `customers`
- `companies`
- `vehicles`
- `drivers`
- `common_codes`
- `dispatches`
- `settlements`

## 비고
- 현재 DB는 Laravel 기본 인프라 + 사용자/게시판/파일관리 + 오더/오더그룹/일정 중심 구조다.
- 향후 Business Foundation(`Customer`, `Company`, `Vehicle`, `Driver`, `Common Code`) 진입 시 관련 테이블을 추가한다.
