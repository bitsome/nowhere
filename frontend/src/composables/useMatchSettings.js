import { reactive, ref } from 'vue';
import {
    apiCreateMatchPreference,
    apiDeleteMatchPreference,
    apiMatchPreferences,
    apiUpdateMatchPreference,
} from '../api/match';
import { getApiErrorMessage } from '../api/client';
import { displayLocation } from '../utils/locations';

const PRESETS_KEY = 'match_form_presets';

const DAY_LABELS = ['월', '화', '수', '목', '금', '토', '일'];

// 날짜 범위 퀵셀렉트 — 비우면 전체
const DATE_RANGES = [
    { label: '전체', value: '' },
    { label: '오늘', value: 'today' },
    { label: '내일', value: 'tomorrow' },
    { label: '오늘+내일', value: 'today_tomorrow' },
];

// 시간대 프리셋 — 빠른 매칭은 주간/야간 2개만. 선택하면 시작/종료 시각이 채워진다.
// 야간은 자정을 넘기므로(20:00 → 06:00) 다음날 배지가 붙는다.
const TIME_PRESETS = [
    { label: '주간', start: '06:00', end: '20:00' },
    { label: '야간', start: '20:00', end: '06:00' },
];

// 금액 프리셋 — 단위 만원. 탭하면 최소 수익이 채워지고, 같은 칩을 다시 누르면 해제
const AMOUNT_PRESETS = [
    { label: '6만', value: 60000 },
    { label: '7만', value: 70000 },
    { label: '8만', value: 80000 },
    { label: '9만', value: 90000 },
    { label: '10만+', value: 100000 },
];

// 인원 프리셋 — 탭하면 최대 인원이 채워지고, 같은 칩을 다시 누르면 해제
const PASSENGER_PRESETS = [
    { label: '3인', value: 3 },
    { label: '5인', value: 5 },
    { label: '7인', value: 7 },
    { label: '9인', value: 9 },
    { label: '11인', value: 11 },
    { label: '12인+', value: 12 },
];

// 'HH:mm' 문자열 <-> naive-ui time-picker(ms 타임스탬프) 변환
const toTimeMs = (time) => {
    if (!time) {
        return null;
    }
    const [hours, minutes] = time.split(':').map(Number);

    return new Date(2000, 0, 1, hours || 0, minutes || 0).getTime();
};

const toTimeString = (ms) => {
    if (!ms) {
        return null;
    }
    const date = new Date(ms);

    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
};

/**
 * 매칭 설정 관리 — 설정 목록/폼/날짜·시간·금액·인원 프리셋/저장 태그를 담당한다.
 *
 * @param {object} options
 * @param {object} options.message naive-ui message
 * @param {() => Promise<void>} options.loadMatchedOrders 설정 저장·삭제 후 매칭 목록 갱신
 */
export function useMatchSettings({ message, loadMatchedOrders }) {
    const matchPrefs = ref([]);
    const matchLoading = ref(true);
    const matchFormOpen = ref(false);
    const editingMatchId = ref(null);
    const savingMatch = ref(false);

    // ── 저장된 설정 태그 — 설정을 저장하면 폼 위에 태그로 보존하고, 클릭 시 다시 채운다 ──
    const formPresets = ref([]);

    const loadFormPresets = () => {
        try {
            const raw = localStorage.getItem(PRESETS_KEY);
            formPresets.value = raw ? JSON.parse(raw) : [];
        } catch {
            formPresets.value = [];
        }
    };

    const persistFormPresets = () => {
        try {
            localStorage.setItem(PRESETS_KEY, JSON.stringify(formPresets.value));
        } catch {
            // 저장 공간 부족 등 — 무시
        }
    };

    // 설정 저장 성공 시 현재 폼 구성을 태그로 기억 (같은 구성은 중복 저장 안 함)
    const rememberPreset = () => {
        const preset = {
            name: matchForm.name.trim(),
            start_time: toTimeString(matchForm.start_time),
            end_time: toTimeString(matchForm.end_time),
            date_range: matchForm.date_range || null,
            days: matchForm.days.length ? [...matchForm.days] : [],
            area: matchForm.area.trim() || '',
            tags: matchForm.tags.length ? [...matchForm.tags] : [],
            service_type: matchForm.service_type || '',
            origin: matchForm.origin.trim() || '',
            destination: matchForm.destination.trim() || '',
            vehicle_id: matchForm.vehicle_id || null,
            max_passengers: matchForm.max_passengers || null,
            min_revenue: matchForm.min_revenue || 0,
            is_active: matchForm.is_active,
        };
        const signature = JSON.stringify(preset);
        const exists = formPresets.value.some((p) => JSON.stringify(p) === signature);

        if (!exists) {
            formPresets.value.unshift(preset);
            if (formPresets.value.length > 10) {
                formPresets.value.pop();
            }
            persistFormPresets();
        }
    };

    // 태그 클릭 — 저장된 설정대로 폼을 채운다
    const applyFormPreset = (preset) => {
        matchForm.name = preset.name;
        matchForm.start_time = toTimeMs(preset.start_time);
        matchForm.end_time = toTimeMs(preset.end_time);
        matchForm.date_range = preset.date_range || '';
        matchForm.days = Array.isArray(preset.days) ? [...preset.days] : [];
        matchForm.area = preset.area || '';
        matchForm.tags = Array.isArray(preset.tags) ? [...preset.tags] : [];
        matchForm.service_type = preset.service_type || '';
        matchForm.origin = preset.origin || '';
        matchForm.destination = preset.destination || '';
        matchForm.vehicle_id = preset.vehicle_id || null;
        matchForm.max_passengers = preset.max_passengers || null;
        matchForm.min_revenue = Number(preset.min_revenue) || 0;
        matchForm.is_active = preset.is_active !== false;
    };

    const removeFormPreset = (index) => {
        formPresets.value.splice(index, 1);
        persistFormPresets();
    };

    const matchForm = reactive({
        name: '',
        start_time: null,
        end_time: null,
        date_range: '',
        days: [],
        area: '',
        tags: [],
        service_type: '',
        origin: '',
        destination: '',
        vehicle_id: null,
        max_passengers: null,
        min_revenue: 0,
        is_active: true,
    });

    const applyTimePreset = (preset) => {
        matchForm.start_time = toTimeMs(preset.start);
        matchForm.end_time = toTimeMs(preset.end);
    };

    const applyAmountPreset = (value) => {
        matchForm.min_revenue = matchForm.min_revenue === value ? 0 : value;
    };

    const applyPassengerPreset = (value) => {
        matchForm.max_passengers = matchForm.max_passengers === value ? null : value;
    };

    const loadMatchPrefs = async () => {
        matchLoading.value = true;

        try {
            const { data } = await apiMatchPreferences();
            // 서버 미배포 시 200 HTML(SPA 폴백)이 올 수 있어 배열인지 확인
            matchPrefs.value = Array.isArray(data?.data) ? data.data : [];
        } catch {
            matchPrefs.value = [];
        } finally {
            matchLoading.value = false;
        }
    };

    const openMatchForm = (pref = null) => {
        // pref 데이터 형태가 달라도 모달이 반드시 열리도록 방어 처리
        editingMatchId.value = pref?.id ?? null;
        matchForm.name = typeof pref?.name === 'string' ? pref.name : '';
        matchForm.start_time = toTimeMs(pref?.start_time);
        matchForm.end_time = toTimeMs(pref?.end_time);
        matchForm.date_range = pref?.date_range || '';
        matchForm.days = Array.isArray(pref?.days) ? [...pref.days] : [];
        matchForm.area = typeof pref?.area === 'string' ? pref.area : '';
        matchForm.tags = Array.isArray(pref?.tags) ? [...pref.tags] : [];
        matchForm.service_type = typeof pref?.service_type === 'string' ? pref.service_type : '';
        matchForm.origin = typeof pref?.origin === 'string' ? pref.origin : '';
        matchForm.destination = typeof pref?.destination === 'string' ? pref.destination : '';
        matchForm.vehicle_id = pref?.vehicle_id || null;
        matchForm.max_passengers = Number(pref?.max_passengers) || null;
        matchForm.min_revenue = Number(pref?.min_revenue) || 0;
        matchForm.is_active = pref?.is_active !== false;
        loadFormPresets();
        matchFormOpen.value = true;
    };

    const closeMatchForm = () => {
        matchFormOpen.value = false;
        editingMatchId.value = null;
    };

    const saveMatch = async () => {
        if (!matchForm.name.trim()) {
            message.warning('설정 이름을 입력해 주세요.');

            return;
        }

        savingMatch.value = true;

        try {
            const payload = {
                name: matchForm.name.trim(),
                start_time: toTimeString(matchForm.start_time),
                end_time: toTimeString(matchForm.end_time),
                date_range: matchForm.date_range || null,
                days: matchForm.days.length ? matchForm.days : null,
                area: matchForm.area.trim() || null,
                tags: matchForm.tags.length ? matchForm.tags : null,
                service_type: matchForm.service_type || null,
                origin: matchForm.origin.trim() || null,
                destination: matchForm.destination.trim() || null,
                vehicle_id: matchForm.vehicle_id || null,
                max_passengers: matchForm.max_passengers || null,
                min_revenue: matchForm.min_revenue || 0,
                is_active: matchForm.is_active,
            };

            let result;
            if (editingMatchId.value) {
                result = await apiUpdateMatchPreference(editingMatchId.value, payload);
            } else {
                result = await apiCreateMatchPreference(payload);
            }

            // 서버 미배포 시 200 HTML이 올 수 있어 실제 JSON 응답인지 확인
            if (!result?.data?.data) {
                throw new Error('서버에 매칭 기능이 배포되지 않았습니다.');
            }

            message.success(editingMatchId.value ? '매칭 설정이 수정되었습니다.' : '매칭 설정이 등록되었습니다.');
            rememberPreset();

            closeMatchForm();
            await Promise.all([loadMatchPrefs(), loadMatchedOrders()]);
        } catch (e) {
            message.error(getApiErrorMessage(e, '매칭 설정 저장에 실패했습니다.'));
        } finally {
            savingMatch.value = false;
        }
    };

    const removeMatch = async (pref) => {
        try {
            await apiDeleteMatchPreference(pref.id);
            message.success('매칭 설정이 삭제되었습니다.');
            await Promise.all([loadMatchPrefs(), loadMatchedOrders()]);
        } catch (e) {
            message.error(getApiErrorMessage(e, '매칭 설정 삭제에 실패했습니다.'));
        }
    };

    const toggleMatchActive = async (pref) => {
        try {
            await apiUpdateMatchPreference(pref.id, { is_active: !pref.is_active });
            pref.is_active = !pref.is_active;
            message.success(pref.is_active ? '매칭이 활성화되었습니다.' : '매칭이 비활성화되었습니다.');
            loadMatchedOrders();
        } catch (e) {
            message.error(getApiErrorMessage(e, '매칭 상태 변경에 실패했습니다.'));
        }
    };

    const matchDayLabel = (days) => {
        if (!days || !days.length) {
            return '매일';
        }

        return days.map((day) => DAY_LABELS[day - 1]).join('·');
    };

    // 매칭 설정 요약 — 시간대는 별도(다음날 배지)로 표시하므로 나머지만 묶는다
    const matchRestSummary = (pref) => {
        const parts = [];

        parts.push(matchDayLabel(pref.days));

        const serviceLabels = { pickup: '픽업', sending: '샌딩', landing: '랜딩' };
        if (pref.service_type && serviceLabels[pref.service_type]) {
            parts.push(serviceLabels[pref.service_type]);
        }

        if (pref.tags?.length) {
            parts.push(`태그 ${pref.tags.join('·')}`);
        }

        if (pref.origin) {
            parts.push(`출발 ${displayLocation(pref.origin)}`);
        }

        if (pref.destination) {
            parts.push(`도착 ${displayLocation(pref.destination)}`);
        }

        if (pref.area) {
            parts.push(`출발 ${pref.area}`);
        }

        if (pref.max_passengers > 0) {
            parts.push(`최대 ${pref.max_passengers}인`);
        }

        if (pref.min_revenue > 0) {
            parts.push(`${pref.min_revenue.toLocaleString()}원 이상`);
        }

        return parts.join(' · ');
    };

    return {
        matchPrefs,
        matchLoading,
        matchFormOpen,
        editingMatchId,
        savingMatch,
        DAY_LABELS,
        DATE_RANGES,
        TIME_PRESETS,
        AMOUNT_PRESETS,
        PASSENGER_PRESETS,
        applyTimePreset,
        applyAmountPreset,
        applyPassengerPreset,
        formPresets,
        loadFormPresets,
        applyFormPreset,
        removeFormPreset,
        matchForm,
        loadMatchPrefs,
        openMatchForm,
        closeMatchForm,
        saveMatch,
        removeMatch,
        toggleMatchActive,
        matchDayLabel,
        matchRestSummary,
    };
}
