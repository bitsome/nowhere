# 서버 배포 계획 — 첫 수익(수수료·수금) 시스템 반영

## Summary
수수료 정책 승격 · 수금 원장 · 출금 게이트 · 등록자/관리자 화면 · 매출 지표 코드는 **이미 구현·로컬 검증 완료**(테스트 352 pass, vite build 성공, pint pass)다. 로컬 `.env`에도 매입 계좌(NH농협 / 3010374548781 / JINLONG) 반영을 마쳤다.

이 계획은 남은 **운영·배포 단계**를 다룬다: 서버 `.env` 반영(Task 1.3) → 프론트+백엔드 배포·마이그레이션(Task 2) → 헬스 체크 → 첫 수익 운영 검증(Task 3).

## Current State Analysis
- **배포 구조**: Laravel API(`/var/www/nowhere`, 8080) + 독립 SPA(`/var/www/frontend/dist`) — `docs/DEPLOY.md` 참조.
- **로컬→서버 배포 수단**: `.deploy/deploy_all.ps1` — 프론트 `npm run build` → `dist` tar → `scp` → 서버 `deploy_all.sh` 실행(`/tmp` tar 압축 해제 + `migrate --force` + 캐시 클리어 + php-fpm 리로드 + 헬스체크).
- **서버 접속**: `ubuntu@114.132.240.52` (SSH 키·askpass·known_hosts는 `.deploy/`에 준비됨).
- **미반영 변경 파일**(git status 기준):
  - 백엔드 8 + 마이그레이션 3
  - 프론트(빌드로 처리): api/admin.js, api/settlement.js, router/index.js, AdminView, MoreView, SettlementView, RegistrantSettlementView(신규)
- **서버 `.env`**: `/var/www/nowhere/.env`에 `SETTLEMENT_PLATFORM_*` 미반영.

## Proposed Changes

### Step 1 — 서버 `.env` 매입 계좌 반영 (Task 1.3)
서버 `/var/www/nowhere/.env`에 아래 키를 upsert(있으면 교체, 없으면 추가)한다.
한글(은행명 "NH농협")이 SSH 인라인 명령에서 깨지지 않도록, **UTF-8 셸 파일을 만들어 scp 후 실행**하는 방식으로 처리한다.

```bash
SETTLEMENT_FEE_RATE=0.05
SETTLEMENT_MIN_FEE=0
SETTLEMENT_PLATFORM_BANK_NAME=NH농협
SETTLEMENT_PLATFORM_ACCOUNT_NUMBER=3010374548781
SETTLEMENT_PLATFORM_ACCOUNT_HOLDER=JINLONG
```

절차:
1. `.deploy/settlement_env.sh`(UTF-8) 생성 — 백업 후 각 키를 `grep`/`sed`로 upsert.
2. `scp` → 서버 `/tmp/settlement_env.sh`, `ssh ... 'sudo bash /tmp/settlement_env.sh'` 실행.
3. `grep -nE '^SETTLEMENT_' /var/www/nowhere/.env`로 반영 확인.

### Step 2 — 프론트 + 백엔드 배포 (Task 2)
`.deploy/deploy_all.ps1`로 프론트 빌드·배포와 백엔드 파일 tar 배포를 한 번에 수행한다.

```powershell
cd d:\wwwroot\nowhere
.\deploy_all.ps1 -Frontend -BackendFiles "app/Http/Controllers/Api/AdminController.php,app/Http/Controllers/Api/SettlementController.php,app/Models/Settlement.php,app/Models/User.php,app/Services/Admin/AdminOperationService.php,app/Services/Settlement/SettlementService.php,config/settlement.php,routes/api.php,database/migrations/2026_09_10_000000_add_fee_rate_to_settlements_table.php,database/migrations/2026_09_10_010000_add_fee_rate_to_users_table.php,database/migrations/2026_09_10_020000_add_collection_to_settlements_table.php"
```

- 서버 `deploy_all.sh`가 `migrate --force`로 3개 마이그레이션을 적용하고 `config:clear`/`route:clear`/`view:clear` + `php8.3-fpm reload` + `/up`·루트 헬스체크를 수행한다.
- 프론트는 `npm run build` 결과물(`dist`)을 `/var/www/frontend/dist`에 반영한다.

### Step 3 — 배포 검증 (Task 2.4)
1. `/up` → 200, 루트 `/` → 200 (`deploy_all.sh` 출력으로 확인).
2. 신규 SPA 번들(RegistrantSettlementView 포함) 서빙 확인.
3. 서버 DB에 컬럼 존재 확인:
   ```bash
   sudo mariadb -e "USE nowhere; SHOW COLUMNS FROM settlements LIKE 'fee_rate'; SHOW COLUMNS FROM settlements LIKE 'collection_status'; SHOW COLUMNS FROM users LIKE 'fee_rate';"
   ```
4. 서버 config에서 매입 계좌 로드 확인:
   ```bash
   sudo -u www-data bash -c "cd /var/www/nowhere && php artisan config:show settlement"
   ```

### Step 4 — 첫 수익 운영 검증 (Task 3, 운영 단계)
배포 후 실제 데이터로 1건을 돌려 머니 플로우를 확정한다.
1. 실제 운행 1건 완료 → 정산 생성(`collection_status=pending`).
2. 등록자가 입금 안내(매입 계좌 포함) 수신 확인.
3. 관리자 '수금 확인' 탭에서 입금 확정 → `collection_status=paid`.
4. 관리자 운영 지표 '수수료 매출' 그룹에 fee 반영 확인.

## Assumptions & Decisions
- **배포 수단은 tar 기반 surgical deploy**(`deploy_all.ps1`)를 쓴다. git commit/push 없이 변경 파일만 서버에 반영한다(별도 commit/push 지시 전에는 수행하지 않음).
- 서버 `.env`의 기존 값은 건드리지 않고 `SETTLEMENT_*` 키만 upsert한다.
- 백엔드 tar에는 `.env`를 포함하지 않으므로 서버 `.env`가 덮어써질 일이 없다.
- Task 3(실제 운행 검증)은 실제 사용·입금이 필요한 운영 단계라 코드 검증과 분리해 안내만 한다.

## Verification steps
- [x] 로컬: `php artisan test --compact` 352 passed
- [x] 로컬: `npm run build` 성공
- [x] 로컬: `vendor/bin/pint --dirty --format agent` passed
- [x] 로컬: `config:show settlement`에서 platform_account 로드 확인
- [ ] 서버: `.env`에 SETTLEMENT_PLATFORM_* 반영 확인
- [ ] 서버: 마이그레이션 3건 적용 확인
- [ ] 서버: `/up`·루트 200, 신규 SPA 번들 서빙 확인
- [ ] 운영: 실제 운행 1건으로 수수료 매출 확정 확인
