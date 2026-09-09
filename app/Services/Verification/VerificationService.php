<?php

namespace App\Services\Verification;

use App\Models\User;
use App\Models\VerificationRequest;
use App\Notifications\OrderNotification;
use App\Services\Admin\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 증빙 심사(B-3) — 인증 서류 사진 신청과 관리자 승인/거절을 담당한다.
 * 기사(Driver): 차량/면허, 등록자(업체, Customer): 사업자등록증/대표 계좌.
 * 승인 결과는 users의 해당 인증 컬럼에 반영되고 사용자에게 알림으로 전달된다 (Q-4 확장).
 */
class VerificationService
{
    /**
     * 인증 신청 — 증빙 사진을 저장하고 심사 대기 요청을 만든다.
     * 같은 유형의 대기 요청이 있으면 중복 신청을 막는다 (관리자 알림 1회 유지).
     */
    public function submit(User $user, string $type, UploadedFile $image, ?string $note = null): VerificationRequest
    {
        abort_unless(isset(VerificationRequest::typeOptions()[$type]), 422, '심사 유형이 올바르지 않습니다.');
        abort_unless(
            in_array($type, VerificationRequest::typesForRole($user->role), true),
            422,
            '이 역할에서는 신청할 수 없는 심사 유형입니다.',
        );

        $verifiedColumn = VerificationRequest::verifiedColumnFor($type);

        abort_if((bool) $user->{$verifiedColumn}, 422, '이미 인증 완료된 항목입니다.');
        abort_if(
            VerificationRequest::query()->where('user_id', $user->id)
                ->where('type', $type)
                ->where('status', VerificationRequest::STATUS_PENDING)
                ->exists(),
            422,
            '이미 심사 대기 중인 신청이 있습니다. 처리가 끝난 뒤 다시 신청해 주세요.',
        );

        $imagePath = $this->storeImage($image);

        $request = VerificationRequest::create([
            'user_id' => $user->id,
            'type' => $type,
            'image_path' => $imagePath,
            'status' => VerificationRequest::STATUS_PENDING,
            'note' => trim((string) $note) !== '' ? trim($note) : null,
        ]);

        // 관리자(Admin/Super Admin)에게 심사 알림
        User::query()
            ->whereIn('role', User::ADMIN_ROLES)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new OrderNotification(
                '인증 심사 요청',
                $user->name.'님이 '.VerificationRequest::typeOptions()[$type].' 증빙을 신청했습니다.',
            )));

        return $request;
    }

    /**
     * 내 인증 현황 — 역할별 허용 유형(business/account 또는 vehicle/license)의 최신 요청 1건씩.
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function mySummary(User $user): array
    {
        $latest = $user->latestVerificationRequests()->limit(6)->get()->groupBy('type');
        $summary = [];

        foreach (VerificationRequest::typesForRole($user->role) as $type) {
            $summary[$type] = $latest->has($type) ? $this->row($latest[$type]->first()) : null;
        }

        return $summary;
    }

    /**
     * 관리자 심사 목록 — 대기(즉시 처리) 우선, 상태·이름 검색 가능.
     *
     * @return array<int, array<string, mixed>>
     */
    public function adminIndex(?string $status = null, string $q = ''): array
    {
        $status = trim((string) $status);
        $q = trim($q);

        $query = VerificationRequest::query()
            ->with(['user:id,name,email,is_vehicle_verified,is_license_verified', 'reviewer:id,name'])
            ->when($status !== '' && isset(VerificationRequest::statusOptions()[$status]), fn ($q2) => $q2->where('status', $status))
            ->when($q !== '', fn ($q2) => $q2->whereHas('user', fn ($q3) => $q3->where('name', 'like', "%{$q}%")))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(50);

        return $query->get()->map(fn (VerificationRequest $request) => $this->row($request))->all();
    }

    /**
     * 심사 처리 — 승인 시 사용자 인증 상태 반영, 거절 시 사유를 남기고 결과를 알린다.
     */
    public function review(User $admin, VerificationRequest $request, string $status, ?string $note = null): VerificationRequest
    {
        abort_unless(isset(VerificationRequest::statusOptions()[$status]), 422, '심사 상태가 올바르지 않습니다.');
        abort_unless($request->status === VerificationRequest::STATUS_PENDING, 422, '이미 처리된 요청입니다.');

        if ($status === VerificationRequest::STATUS_REJECTED) {
            abort_unless(trim((string) $note) !== '', 422, '거절 사유를 입력해 주세요.');
        }

        $user = $request->user;

        // 승인/거절 결과를 사용자 인증 상태에 반영 (거절이면 인증 해제 상태로 유지)
        $column = VerificationRequest::verifiedColumnFor($request->type);
        $user->forceFill([$column => $status === VerificationRequest::STATUS_APPROVED])->save();

        $request->forceFill([
            'status' => $status,
            'review_note' => trim((string) $note) !== '' ? trim($note) : null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $typeLabel = VerificationRequest::typeOptions()[$request->type];
        $resultLabel = VerificationRequest::statusOptions()[$status];

        $user->notify(new OrderNotification(
            '인증 심사 결과',
            $typeLabel.' 인증이 '.$resultLabel.'되었습니다.'
                .(trim((string) $note) !== '' ? ' 사유: '.trim($note) : ''),
        ));

        AuditService::record(
            $admin,
            'verification.review',
            $request->user?->name.'님의 '.$typeLabel.' 인증을 '.$resultLabel.' 처리했습니다',
            ['verification_id' => $request->id, 'type' => $request->type],
        );

        return $request->fresh(['user:id,name,email,is_vehicle_verified,is_license_verified', 'reviewer:id,name']);
    }

    /**
     * 증빙 사진을 저장하고 저장 경로를 반환한다.
     * 파일 내용 해시(지문)를 파일명으로 사용해 같은 사진을 다시 올려도 중복 저장하지 않는다.
     */
    public function storeImage(UploadedFile $image): string
    {
        $hash = hash_file('sha256', (string) $image->getRealPath());
        $extension = $image->extension() ?: 'jpg';
        $path = 'verification/'.$hash.'.'.$extension;

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->putFileAs('verification', $image, $hash.'.'.$extension);
        }

        return $path;
    }

    /**
     * 심사 요청 공개 이미지 경로 — 관리자 화면 <img> 표시용.
     */
    public function publicImageUrl(string $path): string
    {
        return '/api/verification/images/'.basename($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(VerificationRequest $request): array
    {
        $user = $request->user;

        return [
            'id' => $request->id,
            'type' => $request->type,
            'type_label' => VerificationRequest::typeOptions()[$request->type] ?? $request->type,
            'status' => $request->status,
            'status_label' => VerificationRequest::statusOptions()[$request->status] ?? $request->status,
            'image_url' => $this->publicImageUrl($request->image_path),
            'note' => $request->note,
            'review_note' => $request->review_note,
            'created_at_iso' => $request->created_at?->toIso8601String(),
            'reviewed_at_iso' => $request->reviewed_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                'is_license_verified' => (bool) $user->is_license_verified,
                'is_business_verified' => (bool) $user->is_business_verified,
                'is_account_verified' => (bool) $user->is_account_verified,
            ] : null,
            'reviewer_name' => $request->reviewer?->name,
        ];
    }
}
