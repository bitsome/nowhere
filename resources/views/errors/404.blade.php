<x-layouts.app title="404 Not Found">
    <div class="flex min-h-[calc(100vh-10rem)] items-center justify-center">
        <section class="w-full max-w-xl rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-6 py-8 text-center dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#6a6a6a] dark:text-[#9ea1a8]">
                Error
            </p>
            <h1 class="mt-3 text-[32px] font-semibold text-[#202020] dark:text-[#d6d6dd]">
                404 Not Found
            </h1>
            <div class="ui-divider mx-auto mt-6 max-w-md border-t"></div>
            <p class="mt-6 text-sm leading-7 text-[#6a6a6a] dark:text-[#9ea1a8]">
                요청한 페이지를 찾을 수 없습니다.
            </p>
            <div class="mt-6 flex justify-center gap-3">
                <a href="{{ url('/') }}" class="btn-secondary">홈으로</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary">대시보드</a>
                @endauth
            </div>
        </section>
    </div>
</x-layouts.app>
