<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('authenticated users can view the profile screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertSee('프로필')
        ->assertSee($user->email)
        ->assertSee($user->phone);
});

test('users can update their profile information', function () {
    $user = User::factory()->create();

    Storage::fake('public');

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => '새 이름',
            'email' => 'updated@example.com',
            'phone' => '01087654321',
        ])->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status');

    $user->refresh();

    expect($user->name)->toBe('새 이름')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->phone)->toBe('01087654321');
});

test('users can update their password and profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status');

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->profile_photo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($user->profile_photo_path);
});
