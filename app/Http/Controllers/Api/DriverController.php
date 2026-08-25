<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Driver\DriverService;
use App\Services\Driver\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 기사 운영 API — HTTP 요청/응답만 담당하고 비즈니스 로직은 Driver 서비스에 위임한다.
 * #3(화면/UX)·#4(실시간·정책) 범위: 기사 본인만 상태 변경·차량 CRUD.
 */
class DriverController extends Controller
{
    /**
     * 내 기사 상태 조회.
     */
    public function show(Request $request, DriverService $driverService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $driverService->payload($driverService->driverFor($user), $user),
        ]);
    }

    /**
     * 내 기사 상태 변경 (온라인/오프라인/휴식).
     */
    public function status(Request $request, DriverService $driverService): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in($driverService->statuses())],
        ]);

        $user = $request->user();
        $driver = $driverService->changeStatus($user, $data['status']);

        return response()->json(['data' => $driverService->payload($driver, $user)]);
    }

    /**
     * 자동 매칭(콜링) 시작/중지 — 매칭 탭의 스위치.
     */
    public function matchEnabled(Request $request, DriverService $driverService): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $driver = $driverService->setMatchEnabled($user, (bool) $data['enabled']);

        return response()->json(['data' => $driverService->payload($driver, $user)]);
    }

    /**
     * 오늘 통계 — 온라인 시간, 완료 운행 수, 수입, 진행 중 운행.
     */
    public function stats(Request $request, DriverService $driverService): JsonResponse
    {
        return response()->json([
            'data' => $driverService->stats($request->user()),
        ]);
    }

    /**
     * 정산 내역 — 기간별 완료 운행 목록 + 합계 (from/to: YYYY-MM-DD).
     */
    public function settlements(Request $request, DriverService $driverService): JsonResponse
    {
        return response()->json([
            'data' => $driverService->settlements($request),
        ]);
    }

    /**
     * 내 차량 목록.
     */
    public function vehicles(Request $request, VehicleService $vehicleService): JsonResponse
    {
        return response()->json([
            'data' => $vehicleService->list($request->user()),
        ]);
    }

    /**
     * 차량 등록.
     */
    public function storeVehicle(Request $request, VehicleService $vehicleService): JsonResponse
    {
        $vehicle = $vehicleService->store($request->user(), $this->validatedVehicle($request));

        return response()->json(['data' => $vehicleService->payload($vehicle)], 201);
    }

    /**
     * 차량 수정.
     */
    public function updateVehicle(Request $request, Vehicle $vehicle, VehicleService $vehicleService): JsonResponse
    {
        $updated = $vehicleService->update($request->user(), $vehicle, $this->validatedVehicle($request));

        return response()->json(['data' => $vehicleService->payload($updated)]);
    }

    /**
     * 차량 삭제.
     */
    public function destroyVehicle(Request $request, Vehicle $vehicle, VehicleService $vehicleService): JsonResponse
    {
        $vehicleService->destroy($request->user(), $vehicle);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedVehicle(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:40'],
            'license_plate' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'luggage_capacity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'insurance_expires_at' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
