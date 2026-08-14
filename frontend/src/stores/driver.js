import { defineStore } from 'pinia';
import { apiMyDriver, apiSetDriverStatus } from '../api/driver';

/**
 * 기사 가용 상태 스토어 — 프로필 토글, 앱 하단 '운행 중' 필로트, 대시보드 카드에서 공유한다.
 * 상태 값: offline / online / on_trip / rest
 */
export const useDriverStore = defineStore('driver', {
    state: () => ({
        loaded: false,
        status: 'offline',
        statusLabel: '오프라인',
        statusUpdatedAt: null,
    }),
    getters: {
        isOnline: (state) => state.status === 'online' || state.status === 'on_trip',
        isOnTrip: (state) => state.status === 'on_trip',
    },
    actions: {
        async load() {
            try {
                const { data } = await apiMyDriver();
                const driver = data.data;

                this.status = driver.status ?? 'offline';
                this.statusLabel = driver.status_label ?? '오프라인';
                this.statusUpdatedAt = driver.status_updated_at ?? null;
                this.loaded = true;

                return driver;
            } catch {
                this.loaded = true;

                return null;
            }
        },
        async setStatus(status) {
            const { data } = await apiSetDriverStatus(status);
            const driver = data.data;

            this.status = driver.status;
            this.statusLabel = driver.status_label;
            this.statusUpdatedAt = driver.status_updated_at ?? null;

            return driver;
        },
    },
});
