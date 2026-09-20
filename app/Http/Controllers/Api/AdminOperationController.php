<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Admin\AdminOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 관리자 개입(B-2) API — 문제 운행·사용자만 관리자가 개입하는 운영 업무.
 * 운행 숨김/보류/강제취소, 사용자 제재, 채팅 확인·중재, 정산 보류, 일일 운영 요약.
 */
class AdminOperationController extends Controller
{
    use AuthorizesAdmin;

    /**
     * 감사 로그 행위 라벨 — 관리자 화면 표시용 (단일 소스).
     */
    private const AUDIT_ACTION_LABELS = [
        'user.moderation' => '사용자 제재',
        'user.role-change' => '역할 변경',
        'order.hide' => '운행 숨김',
        'order.unhide' => '숨김 해제',
        'order.hold' => '운행 보류',
        'order.release' => '보류 해제',
        'order.force-cancel' => '운행 강제 취소',
        'chat.moderate' => '채팅 중재',
        'settlement.hold' => '정산 보류',
        'settlement.release' => '정산 보류 해제',
        'payout.pay' => '출금 지급',
        'payout.reject' => '출금 거절',
        'report.advance' => '신고 처리',
        'verification.review' => '증빙 심사',
        'order-term.update' => '용어 매핑',
    ];

    /**
     * 관리자 화면 옵션 — 제재 상태·운행 상태 라벨 (단일 소스).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function meta(): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => [
                'moderation' => User::moderationOptions(),
                'order_statuses' => Order::statusOptions(),
                'role_labels' => AdminOperationService::userRoleLabels(),
            ],
        ]);
    }

    /**
     * 사용자 제재 — 기사·등록자 상태(정상/주의/운행 제한/정지)를 사유와 함께 변경한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function moderateUser(Request $request, User $user, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(User::moderationOptions()))],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $service->setUserModeration($request->user(), $user, $data['status'], $data['note']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'moderation_status' => $user->moderation_status,
                'moderation_label' => User::moderationOptions()[$user->moderation_status] ?? $user->moderation_status,
                'moderation_note' => $user->moderation_note,
            ],
        ]);
    }

    /**
     * 운행 목록 — 관리자 개입 대상 검색.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function orders(Request $request, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->orderList($request),
        ]);
    }

    /**
     * 운행 숨김 토글.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function hide(Request $request, Order $order, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'hidden' => ['required', 'boolean'],
            'reason' => ['required_with:1', 'nullable', 'string', 'max:1000'],
        ]);

        $service->setOrderVisibility($request->user(), $order, (bool) $data['hidden'], $data['reason'] ?? '');

        return response()->json(['data' => $this->orderActionRow($order)]);
    }

    /**
     * 운행 보류 토글.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function hold(Request $request, Order $order, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'hold' => ['required', 'boolean'],
            'reason' => ['required_with:1', 'nullable', 'string', 'max:1000'],
        ]);

        $service->setOrderHold($request->user(), $order, (bool) $data['hold'], $data['reason'] ?? '');

        return response()->json(['data' => $this->orderActionRow($order)]);
    }

    /**
     * 운행 강제 취소.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function forceCancel(Request $request, Order $order, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->forceCancelOrder($request->user(), $order, $data['reason']);

        return response()->json(['data' => $this->orderActionRow($order)]);
    }

    /**
     * 대화 목록 — 문제 확인용 (최근 메시지순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function conversations(Request $request, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->conversationList($request),
        ]);
    }

    /**
     * 대화 내용 읽기 — 관리자는 참가자 메시지를 그대로 확인할 수 있다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function conversationMessages(Conversation $conversation, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->conversationMessages($conversation),
        ]);
    }

    /**
     * 관리자 중재 메시지 — 대화에 운영팀 메시지로 남긴다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function moderate(Request $request, Conversation $conversation, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $service->moderate($request->user(), $conversation, $data['body']);

        return response()->json([
            'data' => $service->chatSerialize($message),
        ], 201);
    }

    /**
     * 미지급 정산 목록 (보류 포함).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function settlements(AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->pendingSettlements(),
        ]);
    }

    /**
     * 정산 보류 토글.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function holdSettlement(Request $request, Settlement $settlement, AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        $data = $request->validate([
            'hold' => ['required', 'boolean'],
            'reason' => ['required_with:1', 'nullable', 'string', 'max:1000'],
        ]);

        $service->setSettlementHold($request->user(), $settlement, (bool) $data['hold'], $data['reason'] ?? '');

        return response()->json(['data' => $this->settlementRow($settlement)]);
    }

    /**
     * 일일 운영 요약 — 🔴 즉시 처리 > 🟡 확인 필요 > 🟢 정상.
     *
     * @return JsonResponse{data: array<string, array<string, int>>}
     */
    public function daily(AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->dailySummary(),
        ]);
    }

    /**
     * 운영 지표 — 정산·매칭·신고·운행 파이프라인 요약 (Q-6).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function metrics(AdminOperationService $service): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => $service->metrics(),
        ]);
    }

    /**
     * 감사 로그 — 관리자 개입·변경 행위 최신 100건.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function audit(): JsonResponse
    {
        $this->assertAdmin();

        return response()->json([
            'data' => AuditLog::query()
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'admin_name' => $log->admin_name,
                    'action' => $log->action,
                    'action_label' => self::AUDIT_ACTION_LABELS[$log->action] ?? $log->action,
                    'message' => $log->message,
                    'meta' => $log->meta,
                    'created_at_iso' => $log->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /**
     * 액션 후 운행 행 — hide/hold/force-cancel 응답 공용.
     *
     * @return array<string, mixed>
     */
    private function orderActionRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => Order::statusOptions()[$order->status] ?? $order->status,
            'is_hidden' => (bool) $order->is_hidden,
            'admin_hold' => (bool) $order->admin_hold,
            'admin_hold_reason' => $order->admin_hold_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementRow(Settlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'hold_reason' => $settlement->hold_reason,
        ];
    }
}
