<?php

namespace App\Support\Orders;

use App\Models\User;

/**
 * 파이프라인(위챗 유입)으로 등록되는 운행의 소유 계정을 찾는다.
 *
 * 유입은 특정 등록자 개인이 아니라 운영 계정이 받는다 — 누가 보냈든 같은 계정에 쌓여야
 * '오늘 몇 건이 들어왔는지'와 '누가 등록한 것인지'가 흔들리지 않는다.
 */
final class PipelineOwner
{
    /**
     * 설정된 소유 계정 — 계정이 없으면 관리자 역할의 첫 계정으로 대체한다.
     */
    public static function resolve(): ?User
    {
        $email = trim((string) config('orders.pipeline_owner_email'));

        if ($email !== '') {
            $owner = User::query()->where('email', $email)->first();

            if ($owner !== null) {
                return $owner;
            }
        }

        return User::query()->whereIn('role', User::ADMIN_ROLES)->orderBy('id')->first();
    }

    /**
     * 소유 계정 id — 계정을 찾지 못하면 null (호출 측이 요청자 계정으로 대체한다).
     */
    public static function id(): ?int
    {
        return self::resolve()?->id;
    }
}
