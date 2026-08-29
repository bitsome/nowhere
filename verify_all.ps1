# NoWhere 통합 검증 스크립트
# RULES.md '기능 검증 규칙'의 정적·동적 검증(1·2단계)을 한 번에 실행한다.
# 실패한 단계만 모아 출력하고, 성공 시 0 / 실패 시 1을 반환한다.
#
# 사용법:
#   .\verify_all.ps1                 # 전체 검증
#   .\verify_all.ps1 -Filter 채팅    # Pest 테스트만 필터 지정
param(
    [string]$Filter = ''
)

$script:failed = @()

function Step {
    param(
        [string]$Name,
        [scriptblock]$Body
    )
    Write-Host "`n=== $Name ===" -ForegroundColor Cyan
    & $Body
    if ($LASTEXITCODE -ne 0) {
        $script:failed += $Name
        Write-Host ">>> [$Name] 실패 (exit $LASTEXITCODE)" -ForegroundColor Red
    }
}

# ── 1) 백엔드 스타일 (Pint) ──
Step 'Pint (백엔드 코드 스타일 정리)' {
    php vendor/bin/pint --dirty --format agent
}

# ── 2) 백엔드 테스트 (Pest) ──
if ($Filter) {
    Step 'Pest (백엔드 테스트 · 필터)' {
        php artisan test --compact --filter=$Filter
    }
}
else {
    Step 'Pest (백엔드 테스트 전체)' {
        php artisan test --compact
    }
}

# ── 3) 프론트 테스트 (vitest) ──
Step 'Vitest (프론트 테스트)' {
    npm test --prefix frontend
}

# ── 4) 프론트 빌드 (컴파일 오류 검출) ──
Step 'Vite build (프론트 빌드)' {
    npm run build --prefix frontend
}

# ── 5) 템플릿 참조 검사 ──
Step 'check:refs (프론트 템플릿 참조)' {
    npm run check:refs --prefix frontend
}

# ── 결과 ──
Write-Host "`n===== 검증 결과 =====" -ForegroundColor Cyan
if ($script:failed.Count -eq 0) {
    Write-Host "모든 검증 통과" -ForegroundColor Green
    exit 0
}
else {
    Write-Host "실패한 단계: $($script:failed -join ', ')" -ForegroundColor Red
    exit 1
}
