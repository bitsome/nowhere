<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardWorkspaceController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'modules' => self::workspaceModules(),
        ]);
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

    /**
     * @return array<int, array{key: string, title: string, description: string, status: string, href: string, order: string}>
     */
    public static function workspaceModules(): array
    {
        return [
            [
                'key' => 'notification',
                'title' => 'Notification 테스트',
                'description' => '알림 보내기, 샘플 받기, 읽음 처리, 초기화 흐름을 개별 페이지에서 테스트합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.notification'),
                'order' => '01',
            ],
            [
                'key' => 'users',
                'title' => '회원관리',
                'description' => '회원 조회, Role 변경, Permission 수정, 활성 상태 관리를 개별 페이지에서 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.users'),
                'order' => '02',
            ],
            [
                'key' => 'boards',
                'title' => '게시판',
                'description' => '공지, 자유, 문의 게시판을 type 기반 공통 구조로 운영하기 위한 1차 게시글 기능을 처리합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.boards'),
                'order' => '03',
            ],
            [
                'key' => 'files',
                'title' => '파일관리',
                'description' => '공통 File Module 기준으로 업로드, 목록, 미리보기, 다운로드, 삭제 흐름을 확장하기 위한 진입 페이지입니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.files'),
                'order' => '04',
            ],
            [
                'key' => 'dropdown',
                'title' => 'Shared Dropdown 테스트',
                'description' => '공통 드롭다운 메뉴와 바로가기 액션 조합을 개별 페이지에서 확인합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.dropdown'),
                'order' => '05',
            ],
            [
                'key' => 'datatable',
                'title' => 'Shared DataTable 테스트',
                'description' => '공통 목록 프레임워크를 기준으로 컬럼, 검색, 필터, 빈 상태, 로딩, 페이지네이션을 개별 페이지에서 확인합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.datatable'),
                'order' => '06',
            ],
            [
                'key' => 'editor',
                'title' => 'Toast UI Editor 테스트',
                'description' => '공통 Markdown Editor / Viewer 구조를 기준으로 입력, 미리보기, 저장 문자열 흐름을 개별 페이지에서 확인합니다.',
                'status' => 'Active',
                'href' => route('dashboard.modules.editor'),
                'order' => '07',
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
        }

        abort(404);
    }
}
