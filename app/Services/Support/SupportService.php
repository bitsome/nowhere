<?php

namespace App\Services\Support;

use App\Models\SupportPost;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\OrderNotification;

/**
 * 고객지원(B-4) — 공지·FAQ 게시물(관리자 작성)과 1:1 문의 티켓(작성/답변)을 담당한다.
 * 답변 시 사용자에게 알림, 문의 접수 시 관리자에게 알림으로 전달된다.
 */
class SupportService
{
    /**
     * 공지·FAQ 목록 — 사용자 고객지원 화면용 (종류별 최신순).
     *
     * @return array<int, array<string, mixed>>
     */
    public function posts(?string $kind = null): array
    {
        $query = SupportPost::query()
            ->when($kind !== null && isset(SupportPost::kindOptions()[$kind]), fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('id');

        return $query->limit(100)->get()->map(fn (SupportPost $post) => $this->postRow($post))->all();
    }

    /**
     * 1:1 문의 작성 — 관리자에게 알림을 보내고 대기 상태로 접수한다.
     */
    public function createTicket(User $user, string $title, string $body): SupportTicket
    {
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'title' => trim($title),
            'body' => trim($body),
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        // 관리자(Admin/Super Admin)에게 문의 알림
        User::query()
            ->whereIn('role', User::ADMIN_ROLES)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new OrderNotification(
                '1:1 문의 접수',
                $user->name.'님이 문의를 남겼습니다: '.$ticket->title,
            )));

        return $ticket->fresh();
    }

    /**
     * 내 문의 목록 — 본인 것이므로 본문·답변 전체를 포함해 최신순으로 반환.
     *
     * @return array<int, array<string, mixed>>
     */
    public function myTickets(User $user): array
    {
        return $user->supportTickets()
            ->with(['answerer:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->ticketRow($ticket))
            ->all();
    }

    /**
     * 관리자 문의 목록 — 답변 대기(open) 우선.
     *
     * @return array<int, array<string, mixed>>
     */
    public function adminTickets(?string $status = null): array
    {
        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'answerer:id,name'])
            ->when($status !== null && isset(SupportTicket::statusOptions()[$status]), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(50);

        return $query->get()->map(fn (SupportTicket $ticket) => $this->ticketRow($ticket))->all();
    }

    /**
     * 문의 답변 — 완료 처리하고 결과를 사용자에게 알린다.
     */
    public function answerTicket(User $admin, SupportTicket $ticket, string $answer): SupportTicket
    {
        abort_unless(trim($answer) !== '', 422, '답변 내용을 입력해 주세요.');
        abort_unless($ticket->status === SupportTicket::STATUS_OPEN, 422, '이미 답변된 문의입니다.');

        $ticket->forceFill([
            'status' => SupportTicket::STATUS_ANSWERED,
            'answer' => trim($answer),
            'answered_by' => $admin->id,
            'answered_at' => now(),
        ])->save();

        $user = $ticket->user;

        if ($user !== null) {
            $user->notify(new OrderNotification(
                '1:1 문의 답변',
                $ticket->title.' 문의에 답변이 달렸습니다.',
            ));
        }

        return $ticket->fresh(['user:id,name,email', 'answerer:id,name']);
    }

    /**
     * 공지/FAQ 저장 — 관리자 작성·수정.
     */
    public function savePost(User $admin, string $kind, string $title, string $body, ?SupportPost $post = null): SupportPost
    {
        abort_unless(isset(SupportPost::kindOptions()[$kind]), 422, '게시물 종류가 올바르지 않습니다.');
        abort_unless(trim($title) !== '', 422, '제목을 입력해 주세요.');
        abort_unless(trim($body) !== '', 422, '내용을 입력해 주세요.');

        $attributes = [
            'kind' => $kind,
            'title' => trim($title),
            'body' => trim($body),
        ];

        if ($post !== null) {
            $post->forceFill($attributes)->save();
        } else {
            $post = SupportPost::create($attributes + ['author_id' => $admin->id]);
        }

        return $post;
    }

    /**
     * 공지/FAQ 삭제.
     */
    public function deletePost(SupportPost $post): void
    {
        $post->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function postRow(SupportPost $post): array
    {
        return [
            'id' => $post->id,
            'kind' => $post->kind,
            'kind_label' => SupportPost::kindOptions()[$post->kind] ?? $post->kind,
            'title' => $post->title,
            'body' => $post->body,
            'created_at' => $post->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketRow(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'body' => $ticket->body,
            'status' => $ticket->status,
            'status_label' => SupportTicket::statusOptions()[$ticket->status] ?? $ticket->status,
            'answer' => $ticket->answer,
            'answerer_name' => $ticket->answerer?->name,
            'answered_at' => $ticket->answered_at?->format('Y-m-d H:i'),
            'created_at' => $ticket->created_at?->format('Y-m-d H:i'),
            'user' => $ticket->relationLoaded('user') && $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ] : null,
        ];
    }
}
