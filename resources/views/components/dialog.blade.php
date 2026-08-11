@props([
    'ariaLabel' => '',
    'cancelLabel' => '취소',
    'confirmLabel' => '확인',
    'description' => '',
    'showCancel' => true,
    'size' => 'sm',
    'title' => '',
    'variant' => 'confirm',
])

<div {{ $attributes->merge(['class' => 'dialog', 'hidden' => '']) }} role="alertdialog" aria-modal="true" @if ($ariaLabel !== '') aria-label="{{ $ariaLabel }}" @endif>
    <div class="dialog__backdrop" data-dialog-close></div>

    <div class="dialog__panel dialog__panel--{{ $size }}" role="document">
        <header class="dialog__header">
            <h3 class="dialog__title">{{ $title }}</h3>

            <button type="button" class="dialog__close" data-dialog-close title="닫기" aria-label="닫기">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </header>

        @if ($description !== '' || $slot->isNotEmpty())
            <div class="dialog__body">
                @if ($description !== '')
                    <p class="dialog__description">{{ $description }}</p>
                @endif

                {{ $slot }}
            </div>
        @endif

        <footer class="dialog__footer">
            @if ($showCancel)
                <button type="button" class="dialog__button dialog__button--secondary" data-dialog-close>{{ $cancelLabel }}</button>
            @endif

            <button type="button" class="dialog__button dialog__button--{{ $variant }}" data-dialog-confirm>{{ $confirmLabel }}</button>
        </footer>
    </div>
</div>
