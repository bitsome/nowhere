@props([
    'label' => '',
    'for' => '',
    'required' => false,
    'description' => '',
    'error' => null,
])

@php
    $errorMessage = $error ?? ($for !== '' ? $errors->first($for) : '');
@endphp

<div class="form-framework__group">
    @if ($label !== '' || $description !== '')
        <div class="form-framework__head">
            @if ($label !== '')
                <label class="form-framework__label" @if ($for !== '') for="{{ $for }}" @endif>
                    <span>{{ $label }}</span>
                    @if ($required)
                        <span class="form-framework__required">필수</span>
                    @endif
                </label>
            @endif

            @if ($description !== '')
                <p class="form-framework__description">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="form-framework__body">
        {{ $slot }}

        @if ($errorMessage !== '')
            <p class="form-framework__error">{{ $errorMessage }}</p>
        @endif
    </div>
</div>
