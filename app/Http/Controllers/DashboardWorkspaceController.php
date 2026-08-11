<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardWorkspaceController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'businessModules' => self::businessModules(),
            'modules' => self::workspaceModules(),
        ]);
    }

    /**
     * 공개된 오더를 카드/테이블로 보여주는 마켓 화면.
     * 검색 · 상태/차량/금액/시간/구분 필터를 GET 파라미터로 지원한다.
     */
    public function market(Request $request): View
    {
        return $this->marketScreenView($request);
    }

    /**
     * 내 오더를 탭(진행중/완료/취소)으로 보여주는 화면.
     * 내가 등록/가져온 오더만 탭 상태별로 필터링한다.
     */
    public function myOrders(Request $request): View
    {
        $activeTab = in_array((string) $request->string('tab', '진행중'), ['진행중', '완료', '취소'], true)
            ? (string) $request->string('tab')
            : '진행중';

        $filters = $this->marketFilters($request);

        $orders = Order::query()
            ->with('user')
            ->where('user_id', auth()->id())
            ->whereNotNull('claimed_at')
            ->when($activeTab === '완료', fn ($query) => $query->whereIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
            ]))
            ->when($activeTab === '취소', fn ($query) => $query->where('status', Order::STATUS_CANCELLED))
            ->when($activeTab === '진행중', fn ($query) => $query->whereNotIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ]))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($sub) use ($filters) {
                    $sub->where('order_number', 'like', "%{$filters['search']}%")
                        ->orWhere('customer_name', 'like', "%{$filters['search']}%")
                        ->orWhere('pickup_location', 'like', "%{$filters['search']}%")
                        ->orWhere('dropoff_location', 'like', "%{$filters['search']}%");
                });
            })
            ->latest()
            ->get();

        $tabLabels = ['진행중', '완료', '취소'];

        $tabCounts = [
            '진행중' => Order::query()->where('user_id', auth()->id())->whereNotNull('claimed_at')->whereNotIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ])->count(),
            '완료' => Order::query()->where('user_id', auth()->id())->whereNotNull('claimed_at')->whereIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
            ])->count(),
            '취소' => Order::query()->where('user_id', auth()->id())->whereNotNull('claimed_at')->where('status', Order::STATUS_CANCELLED)->count(),
        ];

        $tabs = collect($tabLabels)->map(fn (string $label) => [
            'label' => $label,
            'count' => $tabCounts[$label],
            'active' => $activeTab === $label,
            'url' => route('my-orders', array_filter([
                'tab' => $label,
                'search' => $filters['search'] ?: null,
            ])),
        ])->all();

        return view('my-orders', [
            'orders' => $orders,
            'orderRows' => app(OrderWorkspaceListBuilder::class)->build($orders),
            'filters' => $filters,
            'statusOptions' => Order::statusOptions(),
            'tabs' => $tabs,
        ]);
    }

    /**
     * 로그인 사용자의 오더 마켓 화면을 렌더링한다.
     *
     * 마켓은 가져올 수 있는(공개/거래중/수락 대기) 남의 오더만 보여준다.
     * 가져오기(claim) 후 오더는 수락 상태가 되어 마켓에서 자동으로 빠진다.
     */
    private function marketScreenView(Request $request): View
    {
        $filters = $this->marketFilters($request);
        $orders = $this->claimableOrders($filters);

        return view('market', [
            'orders' => $orders,
            'orderRows' => app(OrderWorkspaceListBuilder::class)->build($orders),
            'filters' => $filters,
            'statusOptions' => Order::statusOptions(),
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, Order>
     */
    private function claimableOrders(array $filters): Collection
    {
        return Order::query()
            ->with('user')
            ->whereIn('status', [
                Order::STATUS_PUBLISHED,
                Order::STATUS_TRADING,
                Order::STATUS_ACCEPTANCE_PENDING,
            ])
            ->when(auth()->check(), fn ($query) => $query->where('user_id', '!=', auth()->id()))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($sub) use ($filters) {
                    $sub->where('order_number', 'like', "%{$filters['search']}%")
                        ->orWhere('customer_name', 'like', "%{$filters['search']}%")
                        ->orWhere('pickup_location', 'like', "%{$filters['search']}%")
                        ->orWhere('dropoff_location', 'like', "%{$filters['search']}%");
                });
            })
            ->latest()
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function marketFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
        ];
    }

    public function notification(): View
    {
        return view('dashboard.modules.notification', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('notification'),
        ]);
    }

    public function dropdown(): View
    {
        return view('dashboard.modules.dropdown', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('dropdown'),
        ]);
    }

    public function tabs(): View
    {
        return view('dashboard.modules.tabs', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('tabs'),
            'demoTabs' => [
                ['label' => '마켓', 'active' => true, 'url' => route('market')],
                ['label' => '내가 받은 오더', 'active' => false, 'url' => route('my-orders')],
                ['label' => '워크스페이스', 'active' => false, 'url' => route('dashboard.business.order')],
                ['label' => '알림', 'count' => 3, 'active' => false, 'url' => '#'],
            ],
        ]);
    }

    public function datatable(): View
    {
        return view('dashboard.modules.datatable', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('datatable'),
        ]);
    }

    public function editor(): View
    {
        return view('dashboard.modules.editor', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('editor'),
        ]);
    }

    public function dialog(): View
    {
        return view('dashboard.modules.dialog', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('dialog'),
        ]);
    }

    public function components(): View
    {
        return view('dashboard.modules.components', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('components'),
        ]);
    }

    public function buttons(): View
    {
        return view('dashboard.modules.buttons', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('buttons'),
        ]);
    }

    public function modal(): View
    {
        return view('dashboard.modules.modal', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('modal'),
        ]);
    }

    public function cards(): View
    {
        return view('dashboard.modules.cards', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('cards'),
        ]);
    }

    public function lists(): View
    {
        return view('dashboard.modules.lists', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('lists'),
        ]);
    }

    public function forms(): View
    {
        return view('dashboard.modules.forms', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('forms'),
        ]);
    }

    public function toast(): View
    {
        return view('dashboard.modules.toast', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('toast'),
        ]);
    }

    public function loading(): View
    {
        return view('dashboard.modules.loading', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('loading'),
        ]);
    }

    public function alert(): View
    {
        return view('dashboard.modules.alert', [
            'modules' => self::workspaceModules(),
            'module' => self::findWorkspaceModule('alert'),
        ]);
    }

    public function nowhere(): View
    {
        return view('dashboard.business.nowhere', [
            'modules' => self::businessModules(),
            'module' => self::findBusinessModule('nowhere'),
            'businessModules' => self::nowhereBusinessModules(),
        ]);
    }

    public function order(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        $orders = Order::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest()
            ->get();

        $orderRows = app(OrderWorkspaceListBuilder::class)->build($orders);

        return view('dashboard.business.order', [
            'filters' => compact('search'),
            'modules' => self::businessModules(),
            'module' => self::findBusinessModule('order'),
            'businessModules' => self::nowhereBusinessModules(),
            'businessModule' => self::findNowhereBusinessModule('order'),
            'orders' => $orders,
            'orderRows' => $orderRows,
            'statusOptions' => Order::statusOptions(),
            'channelOptions' => Order::reservationChannelOptions(),
        ]);
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, status: string, href: string, order: string}>
     */
    public static function workspaceModules(): array
    {
        return [
            [
                'key' => 'notification',
                'title' => '알림',
                'description' => '알림 보내기, 샘플 받기, 읽음 처리, 초기화 흐름을 개별 페이지에서 테스트합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.notification'),
                'order' => '01',
            ],
            [
                'key' => 'components',
                'title' => '컴포넌트',
                'description' => '공통 컴포넌트(드롭다운, 테이블, 에디터, 다이얼로그) 테스트 페이지 모음입니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.components'),
                'order' => '06',
                'children' => [
                    [
                        'key' => 'dropdown',
                        'title' => '드롭다운',
                        'description' => '공통 드롭다운 메뉴와 바로가기 액션 조합을 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.dropdown'),
                        'order' => '06-1',
                    ],
                    [
                        'key' => 'datatable',
                        'title' => '데이터 테이블',
                        'description' => '공통 목록 프레임워크를 기준으로 컬럼, 검색, 필터, 빈 상태, 로딩, 페이지네이션을 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.datatable'),
                        'order' => '06-2',
                    ],
                    [
                        'key' => 'editor',
                        'title' => '에디터',
                        'description' => '공통 Markdown Editor / Viewer 구조를 기준으로 입력, 미리보기, 저장 문자열 흐름을 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.editor'),
                        'order' => '06-3',
                    ],
                    [
                        'key' => 'dialog',
                        'title' => '다이얼로그',
                        'description' => '파괴적 작업 전 확인 문구를 노출하는 공통 다이얼로그를 Vue와 Blade 두 구현체에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.dialog'),
                        'order' => '06-4',
                    ],
                    [
                        'key' => 'buttons',
                        'title' => '버튼&아이콘',
                        'description' => '현재 사용 중인 공용 버튼과 SVG 아이콘을 한눈에 확인하고 개선 포인트를 정리합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.buttons'),
                        'order' => '06-5',
                    ],
                    [
                        'key' => 'modal',
                        'title' => '모달',
                        'description' => '공용 모달(BaseModal)의 크기, 헤더, 본문, 푸터 구조를 실제 사용처(이미지 파일관리) 스타일로 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.modal'),
                        'order' => '06-6',
                    ],
                    [
                        'key' => 'cards',
                        'title' => '카드',
                        'description' => '기본/통계/요약/리스트/액션/클릭/상태 카드 등 실제 사용 중인 카드 유형을 한눈에 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.cards'),
                        'order' => '06-7',
                    ],
                    [
                        'key' => 'lists',
                        'title' => '리스트',
                        'description' => '기본/구분선/아이콘/상태/호버/알림/체크 리스트 등 실제 사용 중인 리스트 유형을 한눈에 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.lists'),
                        'order' => '06-8',
                    ],
                    [
                        'key' => 'forms',
                        'title' => '폼',
                        'description' => '입력/셀렉트/날짜·시간·요일/텍스트에어리어/체크박스/폼그룹/상태/액션 등 실제 사용 중인 폼 유형을 한눈에 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.forms'),
                        'order' => '06-9',
                    ],
                    [
                        'key' => 'toast',
                        'title' => '토스트',
                        'description' => 'info/success/error 토스트 유형과 아이콘·제목·메시지·닫기 구조를 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.toast'),
                        'order' => '06-10',
                    ],
                    [
                        'key' => 'loading',
                        'title' => '로딩·빈 상태',
                        'description' => '인라인/전체 화면 로딩과 빈 상태(Empty State) 유형을 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.loading'),
                        'order' => '06-11',
                    ],
                    [
                        'key' => 'alert',
                        'title' => '알림 배너',
                        'description' => 'info/success/warning/error 알림 배너(Alert) 유형과 아이콘·제목·메시지·닫기 구조를 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.alert'),
                        'order' => '06-12',
                    ],
                    [
                        'key' => 'tabs',
                        'title' => '탭 메뉴',
                        'description' => '공용 탭 메뉴(TabMenu)와 그리드/리스트 보기 전환(ViewToggle)을 개별 페이지에서 확인합니다.',
                        'status' => 'Active',
                        'href' => route('dashboard.modules.tabs'),
                        'order' => '06-13',
                    ],
                ],
            ],
        ];
    }

    /**
     * 비즈니스 모듈 목록 — 언제든 재개발 대상이므로 공통/데모(workspaceModules)와 분리한다.
     *
     * @return array<int, array{key: string, title: string, description: string, status: string, href: string, order: string}>
     */
    public static function businessModules(): array
    {
        return [
            [
                'key' => 'nowhere',
                'title' => 'NoWhere 비즈니스 허브',
                'description' => 'Order, Dispatch, Settlement 핵심 비즈니스 모듈의 현재 준비 상태와 다음 개발 순서를 먼저 정리합니다.',
                'status' => 'Ready',
                'href' => route('dashboard.business.nowhere'),
                'order' => '00',
            ],
            [
                'key' => 'order',
                'title' => '오더 관리',
                'description' => '예약(오더) 목록, 등록, 상세, 수정, 취소와 AI 구조화 흐름을 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.business.order'),
                'order' => '01',
            ],
            [
                'key' => 'boards',
                'title' => '게시판',
                'description' => '공지, 자유, 문의 게시판을 type 기반 공통 구조로 운영하는 게시글 기능을 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.business.boards'),
                'order' => '02',
            ],
            [
                'key' => 'users',
                'title' => '회원관리',
                'description' => '회원 조회, Role 변경, Permission 수정, 활성 상태 관리를 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.business.users'),
                'order' => '03',
            ],
            [
                'key' => 'files',
                'title' => '파일관리',
                'description' => '공통 File Module 기준으로 업로드, 목록, 다운로드, 삭제 흐름을 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.business.files'),
                'order' => '04',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, status: string, href?: string}>
     */
    public static function nowhereBusinessModules(): array
    {
        return [
            [
                'key' => 'order',
                'title' => '오더 관리',
                'description' => '예약과 주문 등록 화면은 선행 참조 데이터가 준비된 뒤 선택 중심 구조로 시작합니다.',
                'status' => '준비중',
                'href' => route('dashboard.business.order'),
            ],
            [
                'key' => 'dispatch',
                'title' => '배차 관리',
                'description' => '오더, 기사, 차량, 공통코드가 정리된 뒤 배차 보드와 상태 흐름을 설계합니다.',
                'status' => '준비중',
            ],
            [
                'key' => 'settlement',
                'title' => '정산 관리',
                'description' => '오더와 배차의 확정 데이터가 쌓인 뒤 정산 집계와 상태 흐름을 붙입니다.',
                'status' => '준비중',
            ],
        ];
    }

    /**
     * @return array{key: string, title: string, description: string, status: string, href: string, order: string}
     */
    public static function findWorkspaceModule(string $key): array
    {
        foreach (self::workspaceModules() as $module) {
            if ($module['key'] === $key) {
                return $module;
            }

            foreach ($module['children'] ?? [] as $child) {
                if ($child['key'] === $key) {
                    return $child;
                }
            }
        }

        abort(404);
    }

    /**
     * @return array{key: string, title: string, description: string, status: string, href: string, order: string}
     */
    public static function findBusinessModule(string $key): array
    {
        foreach (self::businessModules() as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        abort(404);
    }

    /**
     * @return array{key: string, title: string, description: string, status: string, href?: string}
     */
    public static function findNowhereBusinessModule(string $key): array
    {
        foreach (self::nowhereBusinessModules() as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        abort(404);
    }
}
