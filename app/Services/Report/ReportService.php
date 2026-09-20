<?php

namespace App\Services\Report;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\Admin\AuditService;
use App\Support\Orders\ChineseTextNormalizer;

/**
 * 신고/분쟁 — 접수(대상·유형 검증, 관리자 알림)와 관리자 처리(단계 진행, 신고자 알림), 직렬화.
 * 처리 흐름은 Report::statusFlow()가 단일 소스다.
 */
class ReportService
{
    /**
     * 신고 접수 — 대상 검증 후 저장하고 관리자(Admin/Super Admin)에게 접수 알림을 보낸다.
     */
    public function store(User $reporter, string $targetType, int $targetId, string $category, string $reason): Report
    {
        abort_unless(isset(Report::targetOptions()[$targetType]), 422, '신고 대상이 올바르지 않습니다.');
        abort_unless(isset(Report::categoriesFor($targetType)[$category]), 422, '신고 유형이 올바르지 않습니다.');

        $subjectUserId = $this->resolveSubject($reporter, $targetType, $targetId);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'subject_user_id' => $subjectUserId,
            'category' => $category,
            'reason' => $reason,
            'status' => Report::STATUS_PENDING,
        ]);

        // 관리자에게 접수 알림 — 일일 운영 화면의 🔴 즉시 처리 대상으로 묶인다
        $admins = User::query()
            ->whereIn('role', User::ADMIN_ROLES)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new ReportNotification(
                '신고 접수',
                $reporter->name.'님이 '.$this->targetSummary($report).' 신고를 접수했습니다.',
                $report->id,
            ));
        }

        return $report;
    }

    /**
     * 관리자 처리 — 처리 흐름을 따라 '이후 단계'로만 진행한다.
     * 처리·완료로 넘어가는 순간 신고자에게 결과(메모 포함)를 한 번 알린다.
     */
    public function advance(Report $report, User $admin, string $status, ?string $note = null): Report
    {
        $allowed = $report->nextStatuses();
        abort_unless(in_array($status, $allowed, true), 422, '처리 단계가 올바르지 않습니다. (현재: '.$report->status.')');

        $wasTerminal = in_array($report->status, [Report::STATUS_HANDLED, Report::STATUS_COMPLETED], true);
        $nowTerminal = in_array($status, [Report::STATUS_HANDLED, Report::STATUS_COMPLETED], true);

        $report->update([
            'status' => $status,
            'note' => is_string($note) && trim($note) !== '' ? trim($note) : $report->note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        // 처리·완료에 도달한 시점에만 한 번 알린다 (이전 알림과 중복 없이 결과를 전달)
        if (! $wasTerminal && $nowTerminal && $report->reporter_id !== $admin->id) {
            $reporter = $report->reporter()->first();
            $message = trim((string) $note) !== ''
                ? '신고가 처리되었습니다. ('.$note.')'
                : '신고가 처리 완료되었습니다. 감사합니다.';

            $reporter?->notify(new ReportNotification('신고 처리 결과', $message, $report->id));
        }

        AuditService::record(
            $admin,
            'report.advance',
            "신고(#{$report->id}) 처리 단계를 '".(Report::statusOptions()[$status] ?? $status)."'(으)로 변경"
                .(trim((string) $note) !== '' ? " (메모: {$note})" : ''),
            ['report_id' => $report->id],
        );

        return $report->fresh();
    }

    /**
     * 대상 실체를 검증하고 신고 대상 사용자(subject_user_id)를 결정한다.
     * - order: 존재 확인 (본인 운행 신고 금지, 대상자는 상황에 따라 없음)
     * - user: 기사·등록자만 (본인·직원 신고 금지)
     * - chat: 대화 멤버만 (상대방이 신고 대상)
     */
    private function resolveSubject(User $reporter, string $targetType, int $targetId): ?int
    {
        if ($targetType === Report::TARGET_ORDER) {
            $order = Order::query()->find($targetId);
            abort_unless($order !== null, 422, '신고할 운행을 찾을 수 없습니다.');
            abort_if((int) $order->user_id === (int) $reporter->id, 422, '내가 등록한 운행은 신고할 수 없습니다.');

            return null;
        }

        if ($targetType === Report::TARGET_USER) {
            $target = User::query()->find($targetId);
            abort_unless($target !== null, 422, '신고할 사용자를 찾을 수 없습니다.');
            abort_if((int) $target->id === (int) $reporter->id, 422, '본인은 신고할 수 없습니다.');
            abort_unless(in_array($target->role, [User::ROLE_DRIVER, User::ROLE_CUSTOMER], true), 422, '기사·등록자만 신고할 수 있습니다.');

            return $target->id;
        }

        // chat — 대화 멤버 검증, 상대방이 신고 대상
        $conversation = Conversation::query()->find($targetId);
        abort_unless($conversation !== null, 422, '신고할 채팅을 찾을 수 없습니다.');
        abort_unless($conversation->users()->where('users.id', $reporter->id)->exists(), 422, '내가 참여한 채팅만 신고할 수 있습니다.');

        $counterpart = $conversation->users()
            ->where('users.id', '!=', $reporter->id)
            ->first();

        return $counterpart?->id;
    }

    /**
     * 알림·관리자 화면용 대상 한 줄 요약 — 운행은 노선, 사용자는 이름·역할, 채팅은 상대방.
     */
    public function targetSummary(Report $report): string
    {
        return match ($report->target_type) {
            Report::TARGET_ORDER => $this->orderSummary((int) $report->target_id),
            Report::TARGET_CHAT => '채팅',
            default => $report->subject ? $this->userSummary($report->subject) : '사용자',
        };
    }

    public function orderSummary(int $orderId): string
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return '운행';
        }

        $route = ChineseTextNormalizer::routeLabel($order->pickup_location ?? '', $order->dropoff_location ?? '');

        return "운행 [{$route}]";
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return User::roleLabels();
    }

    private function userSummary(User $user): string
    {
        $label = self::roleLabels()[$user->role] ?? $user->role;

        return $label.' '.$user->name;
    }

    /**
     * 신고 상세 — 관리자 처리 화면이 읽는 형태로 직렬화한다.
     *
     * @return array<string, mixed>
     */
    public function serialize(Report $report): array
    {
        $target = null;
        $subject = null;

        if ($report->target_type === Report::TARGET_ORDER) {
            $order = Order::query()->with('user:id,name,role')->find($report->target_id);
            $target = $order ? [
                'route' => ChineseTextNormalizer::routeLabel($order->pickup_location ?? '', $order->dropoff_location ?? ''),
                'date' => $order->service_date,
                'time' => $order->service_time,
                'owner_name' => $order->user?->name,
            ] : ['route' => '', 'date' => null, 'time' => null, 'owner_name' => null];
        } elseif ($report->target_type === Report::TARGET_USER && $report->subject) {
            $target = [
                'name' => $report->subject->name,
                'role_label' => self::roleLabels()[$report->subject->role] ?? $report->subject->role,
            ];
        }

        if ($report->subject) {
            $subject = [
                'id' => $report->subject->id,
                'name' => $report->subject->name,
                'role_label' => self::roleLabels()[$report->subject->role] ?? $report->subject->role,
            ];
        }

        $reporter = $report->reporter;

        return [
            'id' => $report->id,
            'reporter' => $reporter ? [
                'id' => $reporter->id,
                'name' => $reporter->name,
            ] : null,
            'target_type' => $report->target_type,
            'target_label' => Report::targetOptions()[$report->target_type] ?? $report->target_type,
            'target' => $target,
            'subject' => $subject,
            'category' => $report->category,
            'category_label' => Report::categoryOptions()[$report->category] ?? $report->category,
            'reason' => $report->reason,
            'status' => $report->status,
            'status_label' => Report::statusOptions()[$report->status] ?? $report->status,
            'next_statuses' => $report->nextStatuses(),
            'note' => $report->note,
            'processed_by_name' => $report->processor?->name,
            'processed_at_iso' => $report->processed_at?->toIso8601String(),
            'created_at_iso' => $report->created_at?->toIso8601String(),
        ];
    }
}
