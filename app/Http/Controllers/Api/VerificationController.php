<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Notifications\OrderNotification;
use App\Services\Verification\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * 차량·면허 증빙 심사 플로우 (B-3).
 * - 사용자: 증빙 사진 업로드로 인증 신청 (multipart) / 내 신청 현황 조회
 * - 관리자(Admin/Super Admin): 심사 대기 목록 → 승인/거절(사유) → 사용자 인증 상태 반영·결과 알림
 * - 기존 관리자 즉시 인증 토글(PATCH /admin/users/{user}/verification)은 유지한다.
 */
class VerificationController extends Controller
{
    use AuthorizesAdmin;

    /**
     * 인증 신청 — 증빙 사진(multipart)을 올리면 심사 대기 요청이 생성된다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function request(Request $request, VerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(VerificationRequest::typeOptions()))],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $verification = $service->submit($request->user(), $data['type'], $data['image'], $data['note'] ?? null);

        return response()->json([
            'data' => $this->row($verification),
        ], 201);
    }

    /**
     * 내 인증 현황 — 차량·면허별 최신 요청 상태(대기/승인/거절 + 메모).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function myRequests(Request $request, VerificationService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->mySummary($request->user()),
        ]);
    }

    /**
     * 관리자 심사 목록 — 대기(즉시 처리) 우선, 상태·이름 필터.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function adminIndex(Request $request, VerificationService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        return response()->json([
            'data' => $service->adminIndex($request->string('status')->toString(), (string) $request->string('q')),
        ]);
    }

    /**
     * 심사 처리 — 승인(인증 반영)/거절(사유 필수), 결과를 사용자에게 알림.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function review(Request $request, VerificationRequest $verification, VerificationService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'status' => ['required', Rule::in([VerificationRequest::STATUS_APPROVED, VerificationRequest::STATUS_REJECTED])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $verification = $service->review($request->user(), $verification, $data['status'], $data['note'] ?? null);

        return response()->json([
            'data' => $this->row($verification),
        ]);
    }

    /**
     * 증빙 사진 서빙 — 관리자 화면의 <img>가 Authorization 헤더를 못 보내므로 인증 밖(공개)에서 제공.
     * 파일명은 내용 해시라 접근 불가 추측으로 보호된다.
     */
    public function image(string $filename): StreamedResponse
    {
        $path = 'verification/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')
            ->response($path)
            ->header('Content-Type', Storage::disk('public')->mimeType($path) ?? 'application/octet-stream')
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }

    /**
     * 사용자 인증 상태 즉시 변경 (관리자 직접 처리 — 기존 토글 유지).
     * vehicle/license(기사)와 business/account(등록자) 인증을 한 번에 받는다 (Q-4).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'vehicle' => ['sometimes', 'boolean'],
            'license' => ['sometimes', 'boolean'],
            'business' => ['sometimes', 'boolean'],
            'account' => ['sometimes', 'boolean'],
        ]);

        $user->forceFill([
            'is_vehicle_verified' => $data['vehicle'] ?? $user->is_vehicle_verified,
            'is_license_verified' => $data['license'] ?? $user->is_license_verified,
            'is_business_verified' => $data['business'] ?? $user->is_business_verified,
            'is_account_verified' => $data['account'] ?? $user->is_account_verified,
        ])->save();

        $user->notify(new OrderNotification(
            '인증 처리 완료',
            '인증 상태가 업데이트되었습니다.',
        ));

        return response()->json([
            'data' => [
                'id' => $user->id,
                'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                'is_license_verified' => (bool) $user->is_license_verified,
                'is_business_verified' => (bool) $user->is_business_verified,
                'is_account_verified' => (bool) $user->is_account_verified,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(VerificationRequest $request): array
    {
        $request->loadMissing([
            'user:id,name,email,is_vehicle_verified,is_license_verified,is_business_verified,is_account_verified',
            'reviewer:id,name',
        ]);

        $typeLabel = VerificationRequest::typeOptions()[$request->type] ?? $request->type;
        $statusLabel = VerificationRequest::statusOptions()[$request->status] ?? $request->status;

        return [
            'id' => $request->id,
            'type' => $request->type,
            'type_label' => $typeLabel,
            'status' => $request->status,
            'status_label' => $statusLabel,
            'image_url' => '/api/verification/images/'.basename($request->image_path),
            'note' => $request->note,
            'review_note' => $request->review_note,
            'created_at_iso' => $request->created_at?->toIso8601String(),
            'reviewed_at_iso' => $request->reviewed_at?->toIso8601String(),
            'reviewer_name' => $request->reviewer?->name,
        ];
    }
}
