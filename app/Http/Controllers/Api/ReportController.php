<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 신고/분쟁 API — 사용자는 신고 접수, 관리자는 목록 조회·처리 단계 진행.
 * 비즈니스 로직(대상 검증·알림)은 Report 서비스에 위임한다.
 */
class ReportController extends Controller
{
    use AuthorizesAdmin;

    /**
     * 신고 화면용 옵션 — 대상별 유형(카테고리)·처리 상태 라벨.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'data' => [
                'targets' => Report::targetOptions(),
                'categories' => collect(Report::targetOptions())
                    ->mapWithKeys(fn (string $label, string $type): array => [$type => Report::categoriesFor($type)]),
                'statuses' => Report::statusOptions(),
            ],
        ]);
    }

    /**
     * 신고 접수 (모든 사용자).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request, ReportService $reportService): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(array_keys(Report::targetOptions()))],
            'target_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', Rule::in(array_keys(Report::categoryOptions()))],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $report = $reportService->store(
            $request->user(),
            $data['target_type'],
            (int) $data['target_id'],
            $data['category'],
            $data['reason'],
        );

        return response()->json([
            'data' => $reportService->serialize($report),
        ], 201);
    }

    /**
     * 관리자 — 신고 목록 (상태 필터 지원, 최신순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function adminIndex(Request $request, ReportService $reportService): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Report::statusOptions()))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $reports = Report::query()
            ->with(['reporter', 'subject', 'processor'])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($reports->items())
                ->map(fn (Report $report): array => $reportService->serialize($report))
                ->all(),
            'meta' => [
                'total' => $reports->total(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    /**
     * 관리자 — 처리 단계 진행 (접수→확인→조사→처리→완료, 이후 단계로만 이동).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function advance(Request $request, Report $report, ReportService $reportService): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Report::statusOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $reportService->advance(
            $report,
            $request->user(),
            $data['status'],
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => $reportService->serialize($updated),
        ]);
    }
}
