@props([
    'title' => '',
    'size' => 'md',
])

<div {{ $attributes->merge(['class' => 'modal', 'hidden' => '']) }}>
    <div class="modal__backdrop" data-modal-close></div>

    <div class="modal__panel modal__panel--{{ $size }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="modal__header">
            <h3 class="modal__title">{{ $title }}</h3>

            <button type="button" class="btn-secondary" data-modal-close title="닫기" aria-label="닫기">
                닫기
            </button>
        </div>

        <div class="modal__body">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="modal__footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
