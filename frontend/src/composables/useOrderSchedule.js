import { computed, ref } from 'vue';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { koreanHolidayName } from '../utils/koreanHolidays';

/**
 * 내 운행 스케줄 — 탭/검색/페이지네이션과 날짜(일·월·연)별 그룹핑을 담당한다.
 *
 * @param {{ error: import('vue').Ref<string> }} options 목록 로드 실패 메시지를 담을 ref
 */
export function useOrderSchedule({ error }) {
    const myOrders = ref([]);
    const myOrdersLoading = ref(false);

    // 목록 페이지네이션 (선언 누락 시 목록 로드가 매번 실패한다)
    const page = ref(1);
    const pagination = ref(null);

    // 내 운행은 내가 운행할(받은) 운행만 보여준다 — 등록 운행은 '내 마켓', 끝난 운행은 '히스토리'에서 관리
    const listTab = ref('진행중');
    const listSearch = ref('');
    const STATUS_TABS = [
        { label: '진행중', value: '진행중' },
        { label: '예약', value: '예약' },
        { label: '운행중', value: '운행중' },
    ];

    // 날짜별 그룹 열림/펼침 — 기본은 전부 닫힘, 오늘(이번 달/올해) 스케줄만 펼침
    const collapsedGroups = ref(new Set());
    let collapsedInitialized = false;

    // 묶음 단위: 'day'(일별) / 'month'(월별)
    const groupUnit = ref('day');
    const GROUP_UNITS = [
        { label: '일별', value: 'day' },
        { label: '월별', value: 'month' },
    ];

    const setGroupUnit = (unit) => {
        if (groupUnit.value === unit) {
            return;
        }

        groupUnit.value = unit;
        collapsedInitialized = false;
        collapsedGroups.value = new Set();
        initCollapsedOnce();
    };

    const toggleGroup = (key) => {
        const next = new Set(collapsedGroups.value);

        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        collapsedGroups.value = next;
    };

    // 첫 로드 후 기본 상태 설정: 오늘/이번 달/올해 그룹만 펼치고 나머지는 닫는다
    const initCollapsedOnce = () => {
        if (collapsedInitialized) {
            return;
        }

        collapsedInitialized = true;

        const now = new Date();
        const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const openKey = groupUnit.value === 'month'
            ? todayKey.slice(0, 7)
            : groupUnit.value === 'year'
                ? todayKey.slice(0, 4)
                : todayKey;
        const closed = new Set();

        for (const group of dateGroups.value) {
            if (group.sortDate !== openKey) {
                closed.add(group.key);
            }
        }

        collapsedGroups.value = closed;
    };

    const loadMyOrders = async () => {
        myOrdersLoading.value = true;

        try {
            // 스케줄러 — 서비스 날짜·시간순으로 정렬 (그룹 내 시간순)
            const params = { scope: 'mine', source: 'received', tab: listTab.value, per_page: 100, page: page.value, sort: 'date' };

            if (listSearch.value.trim()) {
                params.search = listSearch.value.trim();
            }

            const { data } = await apiOrders(params);
            myOrders.value = data.data;
            pagination.value = data.meta.pagination;

            // 첫 로드 후: 기본 = 전부 닫힘, 오늘 운행만 펼침
            initCollapsedOnce();
        } catch (e) {
            error.value = getApiErrorMessage(e, '운행 목록을 불러오지 못했습니다.');
        } finally {
            myOrdersLoading.value = false;
        }
    };

    // 필터·탭 변경은 1페이지부터 다시 로드
    const loadFirstPage = () => {
        page.value = 1;
        loadMyOrders();
    };

    const handlePage = (p) => {
        page.value = p;
        loadMyOrders();
    };

    const switchTab = (tab) => {
        listTab.value = tab;
        loadMyOrders();
    };

    // 셋트/단일 운행 분리
    const setRows = computed(() => myOrders.value.filter((o) => o.kind === 'set'));
    const singleRows = computed(() => myOrders.value.filter((o) => o.kind !== 'set'));

    // 스케줄러 — 받은 운행을 단위(일/월/연)별로 그룹화
    const scheduleRows = computed(() => [...singleRows.value, ...setRows.value]);

    const dateGroups = computed(() => {
        const map = new Map();

        // 오늘 기준 — 지난 날짜/월/연은 '지난' 카테고리로 따로 분류
        const now = new Date();
        const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const pastKey = (k) => {
            if (groupUnit.value === 'month') return k < todayKey.slice(0, 7);
            if (groupUnit.value === 'year') return k < todayKey.slice(0, 4);

            return k < todayKey;
        };

        for (const row of scheduleRows.value) {
            // 묶음 키: day=YYYY-MM-DD / month=YYYY-MM / year=YYYY
            const raw = row.sortDate || '';
            let key = raw
                ? groupUnit.value === 'month' ? raw.slice(0, 7) : groupUnit.value === 'year' ? raw.slice(0, 4) : raw
                : '__미정__';

            // 오늘보다 지난 날짜 → 별도 '지난' 카테고리 (날짜 미정 제외)
            if (raw && pastKey(key)) {
                key = '지난';
            }

            if (!map.has(key)) {
                map.set(key, { key, sortDate: key === '__미정__' || key === '지난' ? '' : key, rows: [] });
            }

            map.get(key).rows.push(row);
        }

        return [...map.values()].sort((a, b) => {
            // 날짜 있는 그룹 먼저, 오름차순(과거→미래); 지난/날짜 미정은 마지막
            if (!a.sortDate) return 1;
            if (!b.sortDate) return -1;

            return a.sortDate.localeCompare(b.sortDate);
        });
    });

    // 그룹 헤더 라벨 — 단위별 (일별: 날짜+공휴일, 월별: 2026년 9월, 연별: 2026년)
    const groupLabel = (key) => {
        if (key === '지난') {
            return groupUnit.value === 'day' ? '지난 운행' : '지난 일정';
        }

        if (groupUnit.value === 'month') {
            const [y, m] = key.split('-').map(Number);

            return `${y}년 ${m}월`;
        }

        if (groupUnit.value === 'year') {
            return `${key}년`;
        }

        return dateLabel(key);
    };

    // 날짜 헤더 라벨 — 오늘/내일 강조 + 한국 공휴일·명절 표시
    const dateLabel = (sortDate) => {
        if (!sortDate) {
            return '날짜 미정';
        }

        const [y, m, d] = sortDate.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        const weekdays = ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'];
        let label = `${m}월 ${d}일 (${weekdays[date.getDay()]})`;

        const now = new Date();
        const isToday = y === now.getFullYear() && m === now.getMonth() + 1 && d === now.getDate();
        const tomorrow = new Date(now);
        tomorrow.setDate(now.getDate() + 1);
        const isTomorrow = y === tomorrow.getFullYear() && m === tomorrow.getMonth() + 1 && d === tomorrow.getDate();

        if (isToday) label = `오늘 · ${label}`;
        if (isTomorrow) label = `내일 · ${label}`;

        // 한국 공휴일·명절 (예: 광복절, 추석, 설날 연휴)
        const holiday = koreanHolidayName(sortDate);

        if (holiday) {
            label += ` · ${holiday}`;
        }

        return label;
    };

    // 안내 문구 — 묶음 단위에 따라 다르게
    const scheduleHint = computed(() => {
        if (groupUnit.value === 'month') {
            return '기본은 접혀 있고 이번 달 운행만 펼쳐져 있습니다. 헤더를 탭하면 전환할 수 있습니다.';
        }

        if (groupUnit.value === 'year') {
            return '기본은 접혀 있고 올해 운행만 펼쳐져 있습니다. 헤더를 탭하면 전환할 수 있습니다.';
        }

        return '기본은 접혀 있고 오늘 운행만 펼쳐져 있습니다. 날짜 헤더를 탭하면 전환할 수 있습니다.';
    });

    // ── 캘린더 보기 — 월 그리드 + 날짜별 운행 (일|월|연 단위는 목록 모드에서만 노출) ──
    const viewMode = ref('list');
    const setViewMode = (mode) => {
        viewMode.value = mode;
    };

    const todayIso = (() => {
        const n = new Date();

        return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
    })();

    const calCursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    const selectedDate = ref(todayIso);

    const calTitle = computed(() => `${calCursor.value.getFullYear()}년 ${calCursor.value.getMonth() + 1}월`);

    // 월 그리드 셀 — 앞/뒤 빈(이월) 셀 포함, 월요일 시작 6주
    const calDays = computed(() => {
        const y = calCursor.value.getFullYear();
        const m = calCursor.value.getMonth();
        // 월요일 시작 — 일요일(0)이 마지막 열이 되도록 앞 여유 계산
        const leading = (new Date(y, m, 1).getDay() + 6) % 7;
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const cells = [];

        for (let i = leading - 1; i >= 0; i--) {
            cells.push({ date: '', day: new Date(y, m, -i).getDate(), muted: true });
        }
        for (let day = 1; day <= daysInMonth; day++) {
            cells.push({ date: `${y}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`, day, muted: false });
        }
        for (let lead = 1; cells.length < 42; lead++) {
            cells.push({ date: '', day: lead, muted: true });
        }

        return cells;
    });

    // 운행이 있는 날짜 (점 표시) — 로드된 목록 기준
    const driveDates = computed(() => new Set(myOrders.value.map((o) => o.sortDate).filter(Boolean)));

    const selectedDateOrders = computed(() => myOrders.value.filter((o) => o.sortDate === selectedDate.value));

    const selectedDateLabel = computed(() => {
        if (!selectedDate.value) {
            return '';
        }
        const [, m, d] = selectedDate.value.split('-').map(Number);

        return `${m}월 ${d}일 운행`;
    });

    const prevMonth = () => {
        calCursor.value = new Date(calCursor.value.getFullYear(), calCursor.value.getMonth() - 1, 1);
    };

    const nextMonth = () => {
        calCursor.value = new Date(calCursor.value.getFullYear(), calCursor.value.getMonth() + 1, 1);
    };

    const selectDay = (date) => {
        if (date) {
            selectedDate.value = date;
        }
    };

    return {
        myOrders,
        myOrdersLoading,
        page,
        pagination,
        listTab,
        listSearch,
        STATUS_TABS,
        collapsedGroups,
        groupUnit,
        GROUP_UNITS,
        setGroupUnit,
        toggleGroup,
        setRows,
        singleRows,
        scheduleRows,
        dateGroups,
        groupLabel,
        dateLabel,
        scheduleHint,
        loadMyOrders,
        loadFirstPage,
        handlePage,
        switchTab,
        viewMode,
        setViewMode,
        todayIso,
        calTitle,
        calDays,
        driveDates,
        selectedDate,
        selectedDateOrders,
        selectedDateLabel,
        prevMonth,
        nextMonth,
        selectDay,
    };
}
