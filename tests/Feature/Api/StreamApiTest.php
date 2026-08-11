<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('api events emits initial state event', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->get('/api/events?once=1');
    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('event: state');
    expect($content)->toContain('unread_notifications');
});

test('api events accepts the token via query string', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->get("/api/events?once=1&token={$token}");
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});

test('api events requires authentication', function () {
    $this->get('/api/events?once=1')->assertUnauthorized();
});
