<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderTerm;
use App\Services\Admin\AuditService;
use App\Services\Order\OrderTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 미매핑 용어 관리 API — 사전에 없던 중국어 표기를 관리자가 한국어로 매핑한다.
 */
class OrderTermController extends Controller
{
    use AuthorizesAdmin;

    /**
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request, OrderTermService $service): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(OrderTerm::statusOptions()))],
            'field' => ['nullable', Rule::in(array_keys(OrderTerm::fieldOptions()))],
            'q' => ['nullable', 'string', 'max:100'],
            // 1회성 표기는 기본으로 숨긴다
            'min_occurrences' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $terms = $service->index($filters);

        return response()->json([
            'data' => $terms->getCollection()->map(fn (OrderTerm $term): array => $term->serialize())->all(),
            'meta' => [
                'total' => $terms->total(),
                'current_page' => $terms->currentPage(),
                'last_page' => $terms->lastPage(),
                'statuses' => OrderTerm::statusOptions(),
                'fields' => OrderTerm::fieldOptions(),
            ],
        ]);
    }

    /**
     * 용어 직접 등록 — 아직 유입되지 않은 표기도 미리 사전에 넣어 둔다.
     * 같은 분야·원문이 이미 있으면 매핑값만 갱신한다.
     *
     * @return JsonResponse{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function store(Request $request, OrderTermService $service): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys(OrderTerm::fieldOptions()))],
            'term' => ['required', 'string', 'max:200'],
            'mapped_to' => ['required', 'string', 'max:200'],
        ]);

        $result = $service->create($request->user(), $data);
        $term = $result['term'];

        AuditService::record(
            $request->user(),
            'order-term.update',
            ($result['created'] ? '용어 등록: ' : '용어 수정: ').$term->term.' → '.$term->mapped_to,
            [
                'term_id' => $term->id,
                'field' => $term->field,
                'term' => $term->term,
                'status' => $term->status,
                'mapped_to' => $term->mapped_to,
                'created' => $result['created'],
            ],
        );

        return response()->json([
            'data' => $term->serialize(),
            'meta' => ['created' => $result['created']],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * 매핑값 저장 — 한국어 지정(mapped) / 무시(ignored) / 되돌리기(pending).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function update(Request $request, OrderTerm $orderTerm, OrderTermService $service): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(OrderTerm::statusOptions()))],
            'mapped_to' => ['nullable', 'string', 'max:200', 'required_if:status,'.OrderTerm::STATUS_MAPPED],
        ]);

        $term = $service->save($request->user(), $orderTerm, $data);

        AuditService::record(
            $request->user(),
            'order-term.update',
            '용어 매핑: '.$term->term.' → '.($term->mapped_to ?? $term->statusLabel()),
            [
                'term_id' => $term->id,
                'field' => $term->field,
                'term' => $term->term,
                'status' => $term->status,
                'mapped_to' => $term->mapped_to,
            ],
        );

        return response()->json(['data' => $term->serialize()]);
    }
}
