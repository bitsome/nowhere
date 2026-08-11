import { defineStore } from 'pinia';

/**
 * 다크모드 상태 — 헤더 드롭다운과 라이브러리 테마가 공유한다.
 */
export const useThemeStore = defineStore('theme', {
    state: () => ({
        isDark: localStorage.getItem('theme') === 'dark',
    }),
    actions: {
        apply(dark) {
            this.isDark = dark;
            localStorage.setItem('theme', dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', dark);
        },
        init() {
            this.apply(this.isDark);
        },
        toggle() {
            this.apply(!this.isDark);
        },
    },
});
