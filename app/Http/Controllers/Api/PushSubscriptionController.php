<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * 브라우저 푸시 구독을 저장한다. 같은 엔드포인트면 갱신한다.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'public_key' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
        ]);

        $subscription = $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key' => $data['public_key'] ?? null,
                'auth_token' => $data['auth_token'] ?? null,
            ],
        );

        return response()->json(['data' => $subscription->only('id', 'endpoint')], 201);
    }

    /**
     * 브라우저 푸시 구독을 제거한다 (알림 끄기·구독 만료).
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->pushSubscriptions()
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json(['data' => true]);
    }
}
