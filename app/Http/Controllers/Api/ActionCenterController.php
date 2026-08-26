<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Order\ActionCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 처리할 일(액션 센터) API — 운행 관련 대기 액션(가져오기 승인·요금 제안·채팅 요청)을 한 페이지로 모은다.
 */
class ActionCenterController extends Controller
{
    /**
     * 대기 액션 요약.
     *
     * @return JsonResponse{data: array{claims: array, offers: array, chat_requests: array}}
     */
    public function index(Request $request, ActionCenterService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->summaryFor($request->user()),
        ]);
    }
}
