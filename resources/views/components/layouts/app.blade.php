@props(['title' => null])

@php
    $toastItems = [];
    $statusMessage = session('status');
    $statusTitle = '저장되었습니다.';

    if (is_string($statusMessage)) {
        if (str_contains($statusMessage, '삭제')) {
            $statusTitle = '삭제되었습니다.';
        } elseif (str_contains($statusMessage, '업로드')) {
            $statusTitle = '업로드 완료';
        }
    }

    if ($statusMessage) {
        $toastItems[] = [
            'id' => 'flash-status',
            'title' => $statusTitle,
            'message' => $statusMessage,
            'type' => 'success',
        ];
    }

    if (session('error')) {
        $toastItems[] = [
            'id' => 'flash-error',
            'title' => '오류 발생',
            'message' => session('error'),
            'type' => 'error',
        ];
    }

    if (! empty($errors) && $errors->any()) {
        $toastItems[] = [
            'id' => 'flash-validation-error',
            'title' => '오류 발생',
            'message' => $errors->first(),
            'type' => 'error',
        ];
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name', 'Nowhere') }}</title>
        <script>
            (() => {
                const storedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = storedTheme === 'dark' || (storedTheme !== 'light' && storedTheme !== 'dark' && prefersDark);

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.dataset.theme = storedTheme ?? 'system';
            })();
        </script>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="app-shell">
        <div class="app-shell__container">
            <header class="ui-divider mb-6 flex items-center justify-between border-b pb-4">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#d6d6d6] bg-[#f1f1f1] text-sm font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#1a1a1a] dark:text-[#d6d6dd]">
                        N
                    </span>
                    <div>
                        <p class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-[#1f1f1f] dark:text-[#d6d6dd]">Nowhere</p>
                        <p class="m-0 text-xs leading-5 text-[#6a6a6a] dark:text-[#9ea1a8]">Business Operation Platform</p>
                    </div>
                </a>

                <nav class="flex items-center gap-3">
                    <button
                        type="button"
                        class="icon-button"
                        data-theme-toggle
                        aria-label="다크 모드로 전환"
                        title="다크 모드로 전환"
                    >
                        <span class="sr-only" data-theme-toggle-label>다크 모드로 전환</span>
                        <svg
                            data-theme-icon="moon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
                        </svg>
                        <svg
                            data-theme-icon="sun"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="hidden h-5 w-5"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="4" />
                            <path stroke-linecap="round" d="M12 2v2.2M12 19.8V22M4.93 4.93l1.55 1.55M17.52 17.52l1.55 1.55M2 12h2.2M19.8 12H22M4.93 19.07l1.55-1.55M17.52 6.48l1.55-1.55" />
                        </svg>
                    </button>

                    @auth
                        <div
                            data-header-notification
                            data-notification-url="{{ route('dashboard.modules.notification') }}"
                        ></div>

                        <div
                            data-header-action-dropdown
                            data-trigger-label="바로가기 메뉴"
                            data-title="{{ auth()->user()->name }}"
                            data-description="{{ auth()->user()->email }}"
                            data-dashboard-url="{{ route('dashboard') }}"
                            data-notification-url="{{ route('dashboard.modules.notification') }}"
                            data-my-orders-url="{{ route('my-orders') }}"
                            data-profile-url="{{ route('profile.edit') }}"
                            data-logout-url="{{ route('logout') }}"
                            data-csrf-token="{{ csrf_token() }}"
                        ></div>
                    @else
                        <a href="{{ url('/') }}" class="action-button action-button--secondary">홈</a>
                        <a href="{{ route('register') }}" class="action-button action-button--secondary">회원가입</a>
                        <a href="{{ route('login') }}" class="action-button action-button--primary">로그인</a>
                    @endauth
                </nav>
            </header>

            @if (! empty($toastItems))
                <div
                    data-toast-flash
                    data-toast-items='@json($toastItems, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)'
                ></div>
            @endif

            {{ $slot }}
        </div>
        <script>
            (() => {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                const toggleButton = document.querySelector('[data-theme-toggle]');
                const toggleLabel = document.querySelector('[data-theme-toggle-label]');
                const moonIcon = document.querySelector('[data-theme-icon="moon"]');
                const sunIcon = document.querySelector('[data-theme-icon="sun"]');

                const applyTheme = (theme) => {
                    const shouldUseDark = theme === 'dark' || (theme === 'system' && mediaQuery.matches);
                    const activeTheme = shouldUseDark ? 'dark' : 'light';
                    const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    const nextThemeLabel = nextTheme === 'dark' ? '다크 모드로 전환' : '라이트 모드로 전환';

                    document.documentElement.classList.toggle('dark', shouldUseDark);
                    document.documentElement.dataset.theme = theme;

                    if (theme === 'system') {
                        localStorage.removeItem('theme');
                    } else {
                        localStorage.setItem('theme', theme);
                    }

                    if (toggleButton) {
                        toggleButton.setAttribute('aria-label', nextThemeLabel);
                        toggleButton.setAttribute('title', nextThemeLabel);
                    }

                    if (toggleLabel) {
                        toggleLabel.textContent = nextThemeLabel;
                    }

                    if (moonIcon && sunIcon) {
                        moonIcon.classList.toggle('hidden', activeTheme !== 'light');
                        sunIcon.classList.toggle('hidden', activeTheme !== 'dark');
                    }
                };

                const resolveTheme = () => localStorage.getItem('theme') ?? 'system';

                if (toggleButton) {
                    toggleButton.addEventListener('click', () => {
                        const currentTheme = document.documentElement.dataset.theme || resolveTheme();
                        const useDark = currentTheme === 'dark' || (currentTheme === 'system' && mediaQuery.matches);

                        applyTheme(useDark ? 'light' : 'dark');
                    });
                }

                applyTheme(resolveTheme());

                const handleSystemChange = () => {
                    if (!localStorage.getItem('theme')) {
                        applyTheme('system');
                    }
                };

                if (typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', handleSystemChange);
                } else if (typeof mediaQuery.addListener === 'function') {
                    mediaQuery.addListener(handleSystemChange);
                }
            })();
        </script>
    </body>
</html>
