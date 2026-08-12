<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTemplateController extends Controller
{
    /**
     * 내 운행 등록 템플릿 목록 (최신순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $templates = $request->user()
            ->orderTemplates()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $templates->map(fn (OrderTemplate $template) => $this->serialize($template)),
        ]);
    }

    /**
     * 템플릿 저장.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'service_type' => ['nullable', 'string', 'max:20'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
            'passenger_count' => ['nullable', 'integer', 'min:0', 'max:99'],
            'expected_revenue' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'reservation_company' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
        ]);

        $template = $request->user()->orderTemplates()->create($data);

        return response()->json([
            'data' => $this->serialize($template),
        ], 201);
    }

    /**
     * 템플릿 삭제 (본인 것만).
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function destroy(Request $request, OrderTemplate $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 403);

        $template->delete();

        return response()->json(['data' => ['deleted' => $template->id]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OrderTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'service_type' => $template->service_type,
            'vehicle_type' => $template->vehicle_type,
            'pickup_location' => $template->pickup_location,
            'dropoff_location' => $template->dropoff_location,
            'passenger_count' => $template->passenger_count,
            'expected_revenue' => $template->expected_revenue,
            'flight_number' => $template->flight_number,
            'reservation_company' => $template->reservation_company,
            'memo' => $template->memo,
            'updated_at' => $template->updated_at?->diffForHumans(),
        ];
    }
}
