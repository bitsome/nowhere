<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests can view the registration screen', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee('회원가입');
});

test('users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => '홍길동',
        'email' => 'hong@example.com',
        'phone' => '01012345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'hong@example.com')->where('phone', '01012345678')->exists())->toBeTrue();
});

test('users cannot register with a duplicate email or phone', function () {
    User::factory()->create([
        'email' => 'hong@example.com',
        'phone' => '01012345678',
    ]);

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => '홍길동',
            'email' => 'hong@example.com',
            'phone' => '01012345678',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email', 'phone']);
});
