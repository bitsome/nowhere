/**
 * 아이콘 매핑 — 앱 전체에서 쓰는 아이콘 이름을 @vicons/ionicons5 컴포넌트로 연결한다.
 * 뷰/컴포넌트는 반드시 이 이름(또는 BaseIcon)으로 참조한다. (모든 아이콘은 폰트아이콘 방식)
 */
import { defineComponent, h } from 'vue';
import {
    AddOutline,
    AirplaneOutline,
    AlarmOutline,
    ArrowBackOutline,
    ArrowForwardOutline,
    ArrowUpOutline,
    BagHandleOutline,
    BusinessOutline,
    CalendarOutline,
    CallOutline,
    CarOutline,
    CartOutline,
    CashOutline,
    ChatbubbleEllipsesOutline,
    ChatbubbleOutline,
    CheckmarkDoneOutline,
    CheckmarkOutline,
    ChevronDownOutline,
    ClipboardOutline,
    CloseOutline,
    CompassOutline,
    CreateOutline,
    DiamondOutline,
    DocumentTextOutline,
    DownloadOutline,
    Ellipse,
    EllipsisHorizontalOutline,
    EyeOutline,
    FlashOutline,
    GridOutline,
    Heart,
    HeartOutline,
    HomeOutline,
    ImageOutline,
    InformationCircleOutline,
    ListOutline,
    LocationOutline,
    LockClosedOutline,
    LogOutOutline,
    MailOutline,
    MapOutline,
    MoonOutline,
    NotificationsOutline,
    OptionsOutline,
    PeopleOutline,
    PersonOutline,
    PlayOutline,
    PricetagOutline,
    RefreshOutline,
    ReorderThreeOutline,
    RestaurantOutline,
    SearchOutline,
    SendOutline,
    SettingsOutline,
    ShareSocialOutline,
    ShieldOutline,
    SparklesOutline,
    SpeedometerOutline,
    Star,
    StarOutline,
    StorefrontOutline,
    SunnyOutline,
    TimeOutline,
    TrendingUpOutline,
    TrashOutline,
    VideocamOutline,
    WalletOutline,
    WarningOutline,
} from '@vicons/ionicons5';

/**
 * 동전 아이콘 — ionicons에 코인이 없어 직접 그린다.
 * 금색 원 + 테두리 립 + 하이라이트 링 + '₩' 각인으로 실제 동전처럼 보이게 한다.
 */
export const CoinIcon = defineComponent({
    name: 'CoinIcon',
    render() {
        return h('svg', { viewBox: '0 0 512 512', xmlns: 'http://www.w3.org/2000/svg' }, [
            h('circle', { cx: 256, cy: 256, r: 230, fill: '#f5c542' }),
            h('circle', { cx: 256, cy: 256, r: 230, fill: 'none', stroke: '#8a4f08', 'stroke-width': 14 }),
            h('circle', { cx: 256, cy: 256, r: 196, fill: 'none', stroke: 'rgba(255, 255, 255, 0.6)', 'stroke-width': 10 }),
            h('text', {
                x: 256, y: 340, 'text-anchor': 'middle',
                'font-size': 240, 'font-weight': 800, fill: '#8a5a0e',
                style: 'font-family:inherit;line-height:1',
            }, '₩'),
        ]);
    },
});

/** @type {Record<string, object>} 이름 → 아이콘 컴포넌트 */
export const ICONS = {
    // 내비게이션
    home: HomeOutline,
    market: StorefrontOutline,
    match: CompassOutline,
    'order-create': ListOutline,
    chat: ChatbubbleEllipsesOutline,
    community: PeopleOutline,
    more: EllipsisHorizontalOutline,

    // 더보기/빠른 메뉴
    dashboard: SpeedometerOutline,
    'my-market': CarOutline,
    history: TimeOutline,
    reviews: StarOutline,
    'my-posts': DocumentTextOutline,
    profile: PersonOutline,
    notifications: NotificationsOutline,
    logout: LogOutOutline,
    flash: FlashOutline,

    // 기본 동작
    close: CloseOutline,
    check: CheckmarkOutline,
    'check-done': CheckmarkDoneOutline,
    search: SearchOutline,
    refresh: RefreshOutline,
    list: ListOutline,
    download: DownloadOutline,
    reorder: ReorderThreeOutline,
    'arrow-back': ArrowBackOutline,
    'arrow-forward': ArrowForwardOutline,
    'arrow-up': ArrowUpOutline,
    'chevron-down': ChevronDownOutline,
    send: SendOutline,
    share: ShareSocialOutline,
    add: AddOutline,
    car: CarOutline,
    alarm: AlarmOutline,
    moon: MoonOutline,
    speedometer: SpeedometerOutline,

    // 도형/별점
    star: Star,
    'star-o': StarOutline,
    ellipse: Ellipse,
    shield: ShieldOutline,
    zap: FlashOutline,
    crown: SparklesOutline,
    diamond: DiamondOutline,
    flash2: FlashOutline,

    // 커뮤니티
    location: LocationOutline,
    people: PeopleOutline,
    play: PlayOutline,
    video: VideocamOutline,
    heart: HeartOutline,
    'heart-filled': Heart,
    comment: ChatbubbleOutline,
    image: ImageOutline,
    create: CreateOutline,

    // 프로필/설정
    mail: MailOutline,
    call: CallOutline,
    cash: CashOutline,
    grid: GridOutline,
    settings: SettingsOutline,
    calendar: CalendarOutline,
    business: BusinessOutline,
    lock: LockClosedOutline,
    price: PricetagOutline,
    sunny: SunnyOutline,
    trash: TrashOutline,
    trending: TrendingUpOutline,
    eye: EyeOutline,
    warning: WarningOutline,
    info: InformationCircleOutline,

    // 빈 상태
    inbox: ListOutline,
    edit: CreateOutline,
    bell: NotificationsOutline,
    truck: CarOutline,
    wallet: WalletOutline,

    // 기타 (미정의 폴백)
    airplane: AirplaneOutline,
    map: MapOutline,
    cart: CartOutline,
    survey: ClipboardOutline,
    food: RestaurantOutline,
    options: OptionsOutline,
    help: InformationCircleOutline,
    bag: BagHandleOutline,
    coin: CoinIcon,
};

/**
 * 이름으로 아이콘 컴포넌트를 조회한다. (미정의 시 정보 아이콘 폴백)
 * @param {string} name
 * @returns {object}
 */
export function iconOf(name) {
    return ICONS[name] ?? ICONS.help;
}
