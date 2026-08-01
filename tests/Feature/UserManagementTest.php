<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can view the user management list', function () {
    $viewer = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $managedUser = User::factory()->create([
        'name' => '회원 테스트',
        'role' => User::ROLE_OPERATOR,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.modules.users'))
        ->assertSuccessful()
        ->assertSee('회원관리')
        ->assertSee($managedUser->name)
        ->assertSee($managedUser->role);
});

test('authenticated users can filter the user list', function () {
    $viewer = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    User::factory()->create([
        'name' => '운영 기사',
        'role' => User::ROLE_DRIVER,
        'status' => User::STATUS_ACTIVE,
    ]);

    User::factory()->create([
        'name' => '관리 운영자',
        'role' => User::ROLE_OPERATOR,
        'status' => User::STATUS_SUSPENDED,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.modules.users', [
            'role' => User::ROLE_DRIVER,
            'status' => User::STATUS_ACTIVE,
            'search' => '운영',
        ]))
        ->assertSuccessful()
        ->assertSee('운영 기사')
        ->assertDontSee('관리 운영자');
});

test('authenticated users can view the user detail and permission pages', function () {
    $viewer = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $managedUser = User::factory()->create([
        'role' => User::ROLE_OPERATOR,
        'permissions' => ['board.view', 'dispatch.assign'],
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.modules.users.show', $managedUser))
        ->assertSuccessful()
        ->assertSee($managedUser->email)
        ->assertSee($managedUser->role);

    $this->actingAs($viewer)
        ->get(route('dashboard.modules.users.permissions', $managedUser))
        ->assertSuccessful()
        ->assertSee('board.view')
        ->assertSee('dispatch.assign');
});

test('authenticated users can update user role status and permissions', function () {
    $viewer = User::factory()->create([
        'id' => 1,
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $managedUser = User::factory()->create([
        'role' => User::ROLE_OPERATOR,
        'status' => User::STATUS_ACTIVE,
        'permissions' => ['board.view'],
    ]);

    $this->actingAs($viewer)
        ->patch(route('dashboard.modules.users.role.update', $managedUser), [
            'role' => User::ROLE_ADMIN,
        ])
        ->assertSessionHas('status');

    $this->actingAs($viewer)
        ->patch(route('dashboard.modules.users.status.update', $managedUser), [
            'status' => User::STATUS_SUSPENDED,
        ])
        ->assertSessionHas('status');

    $this->actingAs($viewer)
        ->patch(route('dashboard.modules.users.permissions.update', $managedUser), [
            'permissions' => ['board.view', 'board.update', 'dispatch.assign'],
        ])
        ->assertRedirect(route('dashboard.modules.users.permissions', $managedUser))
        ->assertSessionHas('status');

    $managedUser->refresh();

    expect($managedUser->role)->toBe(User::ROLE_ADMIN)
        ->and($managedUser->status)->toBe(User::STATUS_SUSPENDED)
        ->and($managedUser->permissions)->toBe(['board.view', 'board.update', 'dispatch.assign']);
});

test('user id 1 can assign admin but cannot assign super admin', function () {
    $viewer = User::factory()->create([
        'id' => 1,
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $managedUser = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_OPERATOR,
    ]);

    $this->actingAs($viewer)
        ->patch(route('dashboard.modules.users.role.update', $managedUser), [
            'role' => User::ROLE_ADMIN,
        ])
        ->assertSessionHas('status');

    expect($managedUser->fresh()->role)->toBe(User::ROLE_ADMIN);

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.users.show', $managedUser))
        ->patch(route('dashboard.modules.users.role.update', $managedUser), [
            'role' => User::ROLE_SUPER_ADMIN,
        ])
        ->assertSessionHasErrors('role');

    expect($managedUser->fresh()->role)->toBe(User::ROLE_ADMIN);
});

test('user id 1 uses super admin rank even when stored role is lower', function () {
    $viewer = User::factory()->create([
        'id' => 1,
        'role' => User::ROLE_OPERATOR,
    ]);

    expect($viewer->roleRank())->toBe(User::roleRanks()[User::ROLE_SUPER_ADMIN])
        ->and($viewer->assignableRoles())->toBe([
            User::ROLE_ADMIN,
            User::ROLE_OPERATOR,
            User::ROLE_DRIVER,
        ]);
});

test('super admin user id 1 can assign subordinate permissions', function () {
    $viewer = User::factory()->create([
        'id' => 1,
        'role' => User::ROLE_SUPER_ADMIN,
        'permissions' => User::permissionOptions(),
    ]);

    $managedUser = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
        'permissions' => ['board.view'],
    ]);

    $this->actingAs($viewer)
        ->patch(route('dashboard.modules.users.permissions.update', $managedUser), [
            'permissions' => ['board.view', 'board.create', 'dispatch.assign'],
        ])
        ->assertSessionHas('status');

    expect($managedUser->fresh()->permissions)->toBe(['board.view', 'board.create', 'dispatch.assign']);
});

test('delegated user can only assign lower permissions they already have', function () {
    $viewer = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_ADMIN,
        'permissions' => ['board.view', 'order.create'],
    ]);

    $managedUser = User::factory()->create([
        'id' => 3,
        'role' => User::ROLE_OPERATOR,
        'permissions' => ['board.view'],
    ]);

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.users.permissions', $managedUser))
        ->patch(route('dashboard.modules.users.permissions.update', $managedUser), [
            'permissions' => ['board.view', 'dispatch.assign'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($managedUser->fresh()->permissions)->toBe(['board.view']);
});

test('admin cannot assign admin or higher role to another user', function () {
    $viewer = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_ADMIN,
    ]);

    $managedUser = User::factory()->create([
        'role' => User::ROLE_OPERATOR,
    ]);

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.users.show', $managedUser))
        ->patch(route('dashboard.modules.users.role.update', $managedUser), [
            'role' => User::ROLE_ADMIN,
        ])
        ->assertSessionHasErrors('role');

    expect($managedUser->fresh()->role)->toBe(User::ROLE_OPERATOR);
});

test('admin cannot manage user with same or higher role', function () {
    $viewer = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_ADMIN,
    ]);

    $sameLevelUser = User::factory()->create([
        'id' => 3,
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.users.show', $sameLevelUser))
        ->patch(route('dashboard.modules.users.status.update', $sameLevelUser), [
            'status' => User::STATUS_SUSPENDED,
        ])
        ->assertSessionHasErrors('user');

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.users.permissions', $sameLevelUser))
        ->patch(route('dashboard.modules.users.permissions.update', $sameLevelUser), [
            'permissions' => ['board.view'],
        ])
        ->assertSessionHasErrors('user');
});
