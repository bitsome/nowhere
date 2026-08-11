<x-layouts.app title="예약 수정">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-order-edit-page>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Edit</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">예약 수정</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    예약의 기본 정보를 수정하고 저장하면 상세 화면으로 돌아갑니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard.business.nowhere') }}"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="NoWhere 비즈니스 허브"
                >
                    <span>NoWhere 허브</span>
                    <span class="text-xs">00</span>
                </a>
                <a
                    href="{{ route('dashboard.business.order') }}"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="오더 워크스페이스"
                >
                    <span>오더 워크스페이스</span>
                    <span class="text-xs">01</span>
                </a>
                <a
                    href="{{ route('dashboard.business.order.show', $order) }}"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="예약 상세"
                >
                    <span>예약 상세</span>
                    <span class="text-xs">02</span>
                </a>
                <a
                    href="{{ route('dashboard.business.order.edit', $order) }}"
                    class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]"
                    title="예약 수정"
                >
                    <span>예약 수정</span>
                    <span class="text-xs">03</span>
                </a>
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">예약 번호</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $order->order_number }}</p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="page-panel panel-dark">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">수정</p>
                <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Order Edit</h2>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    일정 단위로 경로, 픽업 일시, 항공편, 인원, 금액과 예약 상태를 수정할 수 있습니다.
                </p>
            </section>

            <section class="page-panel">
                @include('dashboard.business.orders._form', [
                    'formAction' => route('dashboard.business.order.update', $order),
                    'formMethod' => 'PATCH',
                    'formShowLineItems' => true,
                    'formSubmitLabel' => '변경사항 저장',
                    'formSubmitTitle' => '변경사항 저장',
                    'formCancelUrl' => route('dashboard.business.order.show', $order),
                    'formCancelLabel' => '취소',
                    'formCancelTitle' => '예약 상세',
                ])
            </section>
        </div>
    </section>
</x-layouts.app>
