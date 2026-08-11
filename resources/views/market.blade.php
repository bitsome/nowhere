<x-layouts.app title="오더 마켓">
    @auth
        @include('partials.order-market-screen', [
            'screenEyebrow' => 'Order Market',
            'screenTitle' => '오더 마켓',
            'screenDescription' => '가져올 수 있는 오더를 한눈에 확인하고 내 오더로 가져옵니다.',
            'orders' => $orders,
            'orderRows' => $orderRows,
            'filterAction' => route('market'),
            'filters' => $filters,
            'statusOptions' => $statusOptions,
        ])
    @else
        <section class="page-panel">
            <x-alert variant="info" title="로그인이 필요합니다" message="오더 마켓을 보려면 로그인 후 이용해주세요.">
                <div class="mt-3">
                    <a href="{{ route('login') }}" class="btn-primary" title="로그인">로그인</a>
                </div>
            </x-alert>
        </section>
    @endauth
</x-layouts.app>
