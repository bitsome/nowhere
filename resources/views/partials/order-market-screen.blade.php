@php
    $screenEyebrow = $screenEyebrow ?? '오더 마켓';
    $screenTitle = $screenTitle ?? '오더 목록';
    $screenDescription = $screenDescription ?? '기사가 3초 안에 오더를 판단할 수 있도록 단순하고 일관된 화면을 유지합니다.';
    $tabs = $tabs ?? [];
    $cardVariant = $cardVariant ?? 'grid';
    $filterAction = $filterAction ?? '';
    $filters = $filters ?? [];
    $filterSearch = $filters['search'] ?? '';
@endphp

<section class="surface-card surface-card--raised" data-dashboard-order-list>
    {{-- ① Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $screenEyebrow }}</p>
            <h1 class="mt-2 text-[28px] font-semibold text-gray-900 dark:text-gray-100">{{ $screenTitle }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-500 dark:text-gray-400">{{ $screenDescription }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="status-badge">{{ $orders->count() }}건</span>
            <a href="{{ route('dashboard.business.order') }}" class="btn-secondary" title="오더 워크스페이스">전체 보기</a>
        </div>
    </div>

    {{-- ② Toolbar + ③ Tab/Filter — GET 검색/필터 폼 --}}
    <form method="GET" action="{{ $filterAction }}" class="mt-5" data-order-market-filter-form>
        <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-[220px] flex-1">
                <input
                    type="search"
                    name="search"
                    value="{{ $filterSearch }}"
                    class="input-field w-full"
                    placeholder="노선 · 고객명 검색"
                    aria-label="오더 검색"
                >
            </div>
            <button type="submit" class="icon-button" title="검색" aria-label="검색">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M20 20l-3.5-3.5" />
                </svg>
            </button>
            <a href="{{ $filterAction }}" class="icon-button" title="새로고침" aria-label="새로고침">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                    <path d="M21 3v6h-6" />
                </svg>
            </a>
        <div data-order-view-toggle></div>
        <a href="{{ route('dashboard.business.order.create') }}" class="btn-primary" title="오더 등록">+ 오더 등록</a>
    </div>

    {{-- ③ Tab (My Orders 등) — 공용 TabMenu --}}
    @if (count($tabs) > 0)
        <div class="mt-4" data-order-tabs data-tabs='@json($tabs, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)'></div>
    @endif
    </form>

    {{-- ④ Content --}}
    <div class="mt-4">
        <div data-order-card-list data-order-card-variant="{{ $cardVariant }}"></div>
        <div class="hidden" data-order-datatable></div>
    </div>

    <script type="application/json" data-order-rows>
        {!! json_encode($orderRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
</section>
