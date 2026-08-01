# DATABASE

## DB 설계 원칙

## Naming Rule

## Table 목록

## ERD

## Index

## Foreign Key

## UUID

## SoftDelete

## Migration 규칙

## Seeder

## Factory

## 성능 최적화


## 현재 테이블

### users
- `id`
- `name`
- `email`
- `email_verified_at`
- `password`
- `remember_token`
- `created_at`
- `updated_at`

### password_reset_tokens
- `email`
- `token`
- `created_at`

### sessions
- `id`
- `user_id`
- `ip_address`
- `user_agent`
- `payload`
- `last_activity`

## 비고
- 현재 DB는 기본 Laravel 인증 및 세션 저장 구조를 사용한다.
- 향후 도메인 엔티티 추가 시 관계와 인덱스를 본 문서에 갱신한다.
