<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BehaviorEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BehaviorEventController extends Controller
{
    public function __construct(
        private readonly BehaviorEventService $behaviorService,
    ) {}

    /**
     * 앱에서 보낸 노출·클릭 행동을 일괄 저장한다.
     *
     * 신청·거절·완료 같은 확정 행동은 서버 서비스가 직접 기록하므로
     * 여기서는 클라이언트가 아는 화면 신호(impression/click)만 받는다.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.event' => ['required', 'string', 'in:impression,click'],
            'events.*.order_id' => ['nullable', 'integer'],
            'events.*.meta' => ['nullable', 'array'],
        ]);

        $stored = $this->behaviorService->recordMany($request->user(), $data['events']);

        return response()->json(['stored' => $stored]);
    }
}
