<x-layouts.app title="내가 받은 오더">
    @auth
        @include('partials.order-market-screen', [
            'screenEyebrow' => 'My Received Orders',
            'screenTitle' => '내가 받은 오더',
            'screenDescription' => '마켓에서 받아온 오더를 진행중 · 완료 · 취소 탭으로 확인합니다.',
            'orders' => $orders,
            'orderRows' => $orderRows,
            'tabs' => $tabs,
            'cardVariant' => 'line',
            'filterAction' => route('my-orders'),
            'filters' => $filters,
            'statusOptions' => $statusOptions,
        ])
    @else
        <section class="page-panel">
            <x-alert variant="info" title="로그인이 필요합니다" message="내 오더를 보려면 로그인 후 이용해주세요.">
                <div class="mt-3">
                    <a href="{{ route('login') }}" class="btn-primary" title="로그인">로그인</a>
                </div>
            </x-alert>
        </section>
    @endauth
</x-layouts.app>
