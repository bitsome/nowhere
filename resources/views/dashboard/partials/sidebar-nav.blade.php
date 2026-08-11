@php
    $currentKey = $module['key'] ?? '';
    $flatModules = array_values(array_filter($modules, fn ($item) => empty($item['children'])));
    $groupModules = array_values(array_filter($modules, fn ($item) => !empty($item['children'])));
    $groupChildKeys = collect($groupModules)->flatMap(fn ($item) => collect($item['children'] ?? [])->pluck('key'))->all();
    $inGroup = in_array($currentKey, $groupChildKeys, true);
    $inactiveClass = 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]';
    $activeClass = 'border-[#cfcfcf] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]';
@endphp

<nav class="mt-4 space-y-2">
    <a
        href="{{ route('dashboard') }}"
        title="대시보드 허브"
        class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#1f1f1f] transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#d6d6dd] dark:hover:bg-[#222222]"
    >
        <span>허브 홈</span>
        <span class="text-xs">00</span>
    </a>

    @foreach ($flatModules as $moduleItem)
        <a
            href="{{ $moduleItem['href'] }}"
            title="{{ $moduleItem['title'] }}"
            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $currentKey === $moduleItem['key'] ? $activeClass : $inactiveClass }}"
        >
            <span>{{ $moduleItem['title'] }}</span>
            <span class="text-xs">{{ $moduleItem['order'] }}</span>
        </a>
    @endforeach

    @foreach ($groupModules as $group)
        @php
            $groupExpanded = $currentKey === $group['key'] || $inGroup;
        @endphp
        <div>
            <a
                href="{{ $group['href'] }}"
                title="{{ $group['title'] }}"
                class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $groupExpanded ? $activeClass : $inactiveClass }}"
            >
                <span>{{ $group['title'] }}</span>
                <span class="text-xs">{{ $group['order'] }}</span>
            </a>

            @if ($groupExpanded)
                <div class="ml-3 mt-1 space-y-1 border-l border-[#d8d8d8] pl-2 dark:border-[#2a2a2a]">
                    @foreach ($group['children'] as $child)
                        @php
                            $childActive = $currentKey === $child['key'];
                        @endphp
                        <a
                            href="{{ $child['href'] }}"
                            title="{{ $child['title'] }}"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $childActive ? $activeClass : 'border-transparent bg-transparent text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]' }}"
                        >
                            <span>{{ $child['title'] }}</span>
                            <span class="text-xs">{{ $child['order'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</nav>
