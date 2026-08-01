<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private function assertCanManageUser(User $actor, User $targetUser): void
    {
        if (! $actor->canManageUser($targetUser)) {
            throw ValidationException::withMessages([
                'user' => '자신의 권한보다 동등하거나 상위 권한 사용자에게는 변경 작업을 수행할 수 없습니다.',
            ]);
        }
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $role = trim((string) $request->string('role'));
        $status = trim((string) $request->string('status'));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        return view('dashboard.modules.users.index', [
            'module' => DashboardWorkspaceController::findWorkspaceModule('users'),
            'modules' => DashboardWorkspaceController::workspaceModules(),
            'roleOptions' => User::roleOptions(),
            'statusOptions' => User::statusOptions(),
            'users' => $users,
            'filters' => [
                'role' => $role,
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(User $user): View
    {
        $actor = $this->resolveActor($user);

        return view('dashboard.modules.users.show', [
            'actor' => $actor,
            'assignablePermissions' => $actor->assignablePermissions(),
            'assignableRoles' => $actor->assignableRoles(),
            'canManageUser' => $actor->canManageUser($user),
            'module' => DashboardWorkspaceController::findWorkspaceModule('users'),
            'modules' => DashboardWorkspaceController::workspaceModules(),
            'permissionOptions' => User::permissionOptions(),
            'roleOptions' => User::roleOptions(),
            'statusOptions' => User::statusOptions(),
            'user' => $user,
        ]);
    }

    public function permissions(User $user): View
    {
        $actor = $this->resolveActor($user);

        return view('dashboard.modules.users.permissions', [
            'actor' => $actor,
            'assignablePermissions' => $actor->assignablePermissions(),
            'canManageUser' => $actor->canManageUser($user),
            'module' => DashboardWorkspaceController::findWorkspaceModule('users'),
            'modules' => DashboardWorkspaceController::workspaceModules(),
            'permissionOptions' => User::permissionOptions(),
            'user' => $user,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $this->resolveActor($user);
        $this->assertCanManageUser($actor, $user);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', User::roleOptions())],
        ]);

        if (! $actor->canAssignRole($validated['role'])) {
            throw ValidationException::withMessages([
                'role' => '현재 권한으로는 요청한 Role을 부여할 수 없습니다.',
            ]);
        }

        $user->forceFill([
            'role' => $validated['role'],
        ])->save();

        return back()->with('status', '회원 Role이 변경되었습니다.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $this->resolveActor($user);
        $this->assertCanManageUser($actor, $user);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(User::statusOptions()))],
        ]);

        $user->forceFill([
            'status' => $validated['status'],
        ])->save();

        return back()->with('status', '회원 상태가 변경되었습니다.');
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $actor = $this->resolveActor($user);
        $this->assertCanManageUser($actor, $user);
        $assignablePermissions = $actor->assignablePermissions();

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', User::permissionOptions())],
        ]);

        $permissions = collect($validated['permissions'] ?? [])
            ->unique()
            ->values()
            ->all();

        $disallowedPermissions = array_diff($permissions, $assignablePermissions);

        if (count($disallowedPermissions) > 0) {
            throw ValidationException::withMessages([
                'permissions' => '현재 로그인 사용자가 가진 하위 권한만 다른 사용자에게 부여할 수 있습니다.',
            ]);
        }

        $user->forceFill([
            'permissions' => $permissions,
        ])->save();

        return redirect()
            ->route('dashboard.modules.users.permissions', $user)
            ->with('status', '회원 Permission이 저장되었습니다.');
    }

    private function resolveActor(User $fallbackUser): User
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return $fallbackUser;
        }

        return $actor;
    }
}
