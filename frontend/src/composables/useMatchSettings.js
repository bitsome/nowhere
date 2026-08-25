import { reactive, ref } from 'vue';
import {
    apiCreateMatchPreference,
    apiDeleteMatchPreference,
    apiMatchPreferences,
    apiUpdateMatchPreference,
} from '../api/match';
import { getApiErrorMessage } from '../api/client';

const PRESETS_KEY = 'match_form_presets';

const DAY_LABELS = ['월', '화', '수', '목', '금', '토', '일'];

// 날짜 범위 퀵셀렉트 — 비우면 전체
const DATE_RANGES = [
    { label: '전체', value: '' },
    { label: '오늘', value: 'today' },
    { label: '내일', value: 'tomorrow' },
    { label: '오늘+내일', value: 'today_tomorrow' },
];

// 시간대 프리셋 — 선택하면 시작/종료 시각이 채워진다
const TIME_PRESETS = [
    { label: '새벽', start: '03:00', end: '06:00' },
    { label: '아침', start: '06:00', end: '09:00' },
    { label: '오전', start: '09:00', end: '11:00' },
    { label: '정오', start: '11:00', end: '13:00' },
    { label: '오후', start: '13:00', end: '17:00' },
    { label: '저녁', start: '17:00', end: '20:00' },
    { label: '밤', start: '20:00', end: '00:00' },
    { label: '심야', start: '00:00', end: '03:00' },
];

// 금액 프리셋 — 단위 만원. 탭하면 최소 수익이 채워지고, 같은 칩을 다시 누르면 해제
const AMOUNT_PRESETS = [
    { label: '5만', value: 50000 },
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
            message.warning('설정 이름을 입력해주세요.');

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

    const matchSummary = (pref) => {
        const parts = [];

        if (pref.start_time && pref.end_time) {
            // 종료 시각이 시작보다 이르면 자정을 넘는 야간 시간대 → '다음날' 표시
            const overnight = pref.start_time > pref.end_time;

            parts.push(overnight ? `${pref.start_time}~다음날 ${pref.end_time}` : `${pref.start_time}~${pref.end_time}`);
        }

        parts.push(matchDayLabel(pref.days));

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
        matchSummary,
    };
}
