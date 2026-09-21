<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\Settlement;
use App\Services\Admin\AuditService;
use App\Services\Settlement\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 정산·출금 API — 기사 정산 화면(원장·계좌·출금 신청)과 관리자 출금 처리를 담당한다.
 */
class SettlementController extends Controller
{
    use AuthorizesAdmin;

    /**
     * 내 정산 요약 — 출금 가능, 이번 달 정산, 최근 정산 내역, 등록 계좌.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function summary(Request $request, SettlementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->summaryFor($request->user()),
        ]);
    }

    /**
     * 출금 계좌 등록/갱신.
     *
     * @return JsonResponse{data: array<string, string>}
     */
    public function saveAccount(Request $request, SettlementService $service): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'max:60'],
            'account_holder' => ['required', 'string', 'max:60'],
        ]);

        return response()->json([
            'data' => $service->saveAccount($request->user(), $data),
        ]);
    }

    /**
     * 등록자 정산(청구) 화면 — 입금 대기(미수금) 운행·합계와 매입 계좌 안내.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function payables(Request $request, SettlementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->payablesFor($request->user()),
        ]);
    }

    /**
     * 출금 신청 — 출금 가능한 정산 전부를 한 건으로 묶는다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function requestPayout(Request $request, SettlementService $service): JsonResponse
    {
        $payout = $service->requestPayout($request->user());

        return response()->json([
            'data' => $this->payoutPayload($payout),
        ], 201);
    }

    /**
     * 내 출금 신청 내역.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function myPayouts(Request $request, SettlementService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->payoutsOf($request->user()),
        ]);
    }

    /**
     * 관리자 — 처리 대기 출금 신청 목록.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function adminPayouts(Request $request, SettlementService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        return response()->json([
            'data' => $service->pendingPayouts(),
        ]);
    }

    /**
     * 관리자 — 입금 확인 대기(수금 전) 정산 원장 목록.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function adminCollections(Request $request, SettlementService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        return response()->json([
            'data' => $service->pendingCollections(),
        ]);
    }

    /**
     * 관리자 — 등록자 입금을 확인해 수금을 확정한다.
     *
     * @return JsonResponse{data: bool}
     */
    public function collect(Request $request, Settlement $settlement, SettlementService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $service->confirmCollection($request->user(), $settlement, $data['note'] ?? null);

        AuditService::record(
            $request->user(),
            'settlement.collect',
            '정산(#'.$settlement->id.') 운행 대금 입금을 확인했습니다',
            ['settlement_id' => $settlement->id],
        );

        return response()->json(['data' => true]);
    }

    /**
     * 관리자 — 출금 신청을 지급 완료 처리한다.
     *
     * @return JsonResponse{data: bool}
     */
    public function pay(Request $request, PayoutRequest $payout, SettlementService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $service->payPayout($request->user(), $payout);

        AuditService::record(
            $request->user(),
            'payout.pay',
            '출금 신청(#'.$payout->id.') '.number_format((int) $payout->amount).'원을 지급 처리했습니다',
            ['payout_id' => $payout->id, 'amount' => $payout->amount],
        );

        return response()->json(['data' => true]);
    }

    /**
     * 관리자 — 출금 신청을 거절한다.
     *
     * @return JsonResponse{data: bool}
     */
    public function reject(Request $request, PayoutRequest $payout, SettlementService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $service->rejectPayout($request->user(), $payout, $data['reason'] ?? null);

        AuditService::record(
            $request->user(),
            'payout.reject',
            '출금 신청(#'.$payout->id.')을 거절했습니다'
                .(isset($data['reason']) && trim((string) $data['reason']) !== '' ? ' (사유: '.$data['reason'].')' : ''),
            ['payout_id' => $payout->id],
        );

        return response()->json(['data' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutPayload(PayoutRequest $payout): array
    {
        return [
            'id' => $payout->id,
            'status' => $payout->status,
            'amount' => (int) $payout->amount,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'account_holder' => $payout->account_holder,
            'created_at_iso' => $payout->created_at?->toIso8601String(),
        ];
    }
}
