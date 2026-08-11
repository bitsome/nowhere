<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 테스트 계정(test@example.com)과 운영자(market@example.com) 간 데모 대화를 심는다.
 */
class ChatDemoSeeder extends Seeder
{
    public function run(): void
    {
        $me = User::query()->where('email', 'test@example.com')->first();
        $operator = User::query()->where('email', 'market@example.com')->first();

        if ($me === null || $operator === null) {
            return;
        }

        Conversation::query()->whereHas('users', fn ($query) => $query->where('users.id', $me->id))->delete();

        $order = Order::query()
            ->where('user_id', $operator->id)
            ->first();

        $conversation = Conversation::create([
            'order_id' => $order?->id,
            'last_message_at' => now()->subMinutes(10),
        ]);

        $conversation->users()->attach([$me->id, $operator->id]);

        $messages = [
            ['user_id' => $operator->id, 'body' => '안녕하세요! 인천공항 T1 픽업 건으로 문의드립니다.', 'created_at' => now()->subMinutes(30), 'read_at' => now()->subMinutes(28)],
            ['user_id' => $me->id, 'body' => '네 안녕하세요. 가능합니다. 탑승자 성함과 도착 시간 알려주세요.', 'created_at' => now()->subMinutes(27), 'read_at' => now()->subMinutes(26)],
            ['user_id' => $operator->id, 'body' => '김민수입니다. 오전 10시 30분 인천공항 T1 도착입니다.', 'created_at' => now()->subMinutes(10), 'read_at' => null],
        ];

        foreach ($messages as $message) {
            $conversation->messages()->create($message);
        }
    }
}
