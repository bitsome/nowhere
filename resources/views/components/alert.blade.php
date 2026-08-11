@props([
    'title' => '',
    'message' => '',
    'variant' => 'info',
    'dismissible' => false,
])

@php
    $iconPaths = [
        'info' => '<path d="M14.86 17H9.14a2 2 0 0 0-1.94 1.53L7 19h10l-.2-.47A2 2 0 0 0 14.86 17Z" /><path d="M18 11a6 6 0 1 0-12 0c0 2.18-.7 3.41-1.37 4.28-.32.42-.13 1.04.41 1.22h13.92c.54-.18.73-.8.41-1.22C18.7 14.41 18 13.18 18 11Z" />',
        'success' => '<path d="M5 12l4.5 4.5L19 7" />',
        'warning' => '<path d="M12 3.5L21.5 20h-19L12 3.5Z" /><path d="M12 9.5v4.5" /><path d="M12 17.5h.01" />',
        'error' => '<path d="M6 6l12 12M18 6L6 18" />',
    ];
    $iconPath = $iconPaths[$variant] ?? $iconPaths['info'];
@endphp

<div {{ $attributes->merge(['class' => 'alert is-' . $variant, 'role' => 'alert']) }}>
    <span class="alert__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
            {!! $iconPath !!}
        </svg>
    </span>

    <div class="alert__body">
        @if ($title !== '')
            <p class="alert__title">{{ $title }}</p>
        @endif

        @if ($slot->isNotEmpty())
            <div class="alert__message">{{ $slot }}</div>
        @elseif ($message !== '')
            <p class="alert__message">{{ $message }}</p>
        @endif
    </div>

    @if ($dismissible)
        <button type="button" class="alert__close" data-alert-close title="알림 닫기" aria-label="알림 닫기">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    @endif
</div>
