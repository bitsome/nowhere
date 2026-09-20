<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Orders\ChineseTextNormalizer;
use App\Support\Orders\OrderListRowBuilder;
use Illuminate\Http\Response;

/**
 * 공유 링크(/s/order/{token})의 미리보기 카드.
 *
 * SPA는 모든 경로가 같은 정적 index.html을 쓰기 때문에 운행마다 다른 OG 메타를 만들 수 없다.
 * 그래서 공유 주소만 Laravel이 받아 운행 정보가 담긴 OG 태그를 서버에서 렌더하고,
 * 사람은 SPA 공개 화면(/share/order/{token})으로 넘긴다.
 * (카카오톡·SNS 미리보기는 OG 메타를 읽고, 실제 방문자는 곧바로 앱 화면을 본다)
 */
class ShareController extends Controller
{
    public function order(string $token): Response
    {
        $order = Order::query()
            ->where('share_token', $token)
            ->first();

        return response()
            ->view('share.order', [
                'title' => $this->title($order),
                'description' => $this->description($order),
                // 접속한 그대로의 도메인을 쓴다 — APP_URL이 임시 주소여도 미리보기가 깨지지 않는다
                'image' => request()->getSchemeAndHttpHost().'/og-cover.jpg',
                'url' => request()->fullUrl(),
                'spaPath' => '/share/order/'.$token,
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * 미리보기 제목 — "출발 → 도착 | 날짜 시간 | 금액".
     */
    private function title(?Order $order): string
    {
        if ($order === null) {
            return 'NoWhere — 오늘 받을 운행을 앱이 먼저 골라드립니다';
        }

        $rowBuilder = app(OrderListRowBuilder::class);

        // 표시용 노선 — 유입 원문의 중국어가 공유 미리보기에 노출되지 않도록 변환한다
        $pickup = ChineseTextNormalizer::displayLocation($order->pickup_location);
        $dropoff = ChineseTextNormalizer::displayLocation($order->dropoff_location);
        $route = ($pickup === '-' && $dropoff === '-') ? '' : $pickup.' → '.$dropoff;

        $parts = array_filter([
            $route !== '' ? $route : null,
            $rowBuilder->formatPickupDateTime($order) ?: null,
            $rowBuilder->formatAmount($order) !== '-' ? $rowBuilder->formatAmount($order) : null,
        ]);

        return $parts === []
            ? 'NoWhere 운행'
            : implode(' | ', $parts);
    }

    /**
     * 미리보기 설명 — 기사가 판단에 필요한 최소 조건.
     */
    private function description(?Order $order): string
    {
        if ($order === null) {
            return '기사님 판단 피로도를 줄이는 운행 최적화 플랫폼. 추천 → 신청 → 승인 → 운행 → 정산까지.';
        }

        $conditions = array_filter([
            $order->vehicle_type ?: null,
            $order->passenger_count ? '승객 '.$order->passenger_count.'명' : null,
            $order->luggage_count ? '캐리어 '.$order->luggage_count : null,
            $order->flight_number ?: null,
        ]);

        $head = $order->status === Order::STATUS_PUBLISHED
            ? '기사님 구합니다'
            : '마감된 운행입니다';

        return $conditions === []
            ? $head.' — NoWhere에서 확인하세요.'
            : $head.' · '.implode(' · ', $conditions);
    }
}
