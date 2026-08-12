<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommunityDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $me = User::query()->where('email', 'test@example.com')->first();
        $partner = User::query()->where('email', 'market@example.com')->first();

        if ($me === null || $partner === null) {
            return;
        }

        // 인증 배지 데모 — 운영자(VIP) / 드라이버(차량·면허 인증)
        $me->update([
            'is_vehicle_verified' => true,
            'is_license_verified' => true,
            'vehicle_info' => '스타리아 9인승',
        ]);

        $partner->update([
            'is_vip' => true,
            'is_license_verified' => true,
            'is_vehicle_verified' => true,
            'vehicle_info' => '카니발 7인승',
        ]);

        // 데모 레벨 — 경험치 기준: 운영자(VIP 마스터) / 테스트 유저(스타 드라이버)
        $partner->forceFill(['xp' => 6400])->save();
        $me->forceFill(['xp' => 1200])->save();

        $posts = [
            [
                'user_id' => $me->id,
                'content' => "오늘 인천공항 첫 픽업 완료! 손님 분위기 좋았어요 🚗\n다들 하루 수고 많으셨습니다.",
                'hours_ago' => 2,
            ],
            [
                'user_id' => $partner->id,
                'content' => '김포 → 강남 저녁 픽업 자리 남았습니다. 관심 있으신 분 연락 주세요!',
                'hours_ago' => 5,
            ],
            [
                'user_id' => $me->id,
                'content' => '이번 주 셋트 운행 12건 모두 소화 완료. 팀원들 고생했습니다 🙌',
                'hours_ago' => 26,
            ],
        ];

        foreach ($posts as $post) {
            $model = CommunityPost::create([
                'user_id' => $post['user_id'],
                'content' => $post['content'],
            ]);

            $model->forceFill([
                'created_at' => now()->subHours($post['hours_ago']),
                'updated_at' => now()->subHours($post['hours_ago']),
            ])->save();
        }
    }
}
