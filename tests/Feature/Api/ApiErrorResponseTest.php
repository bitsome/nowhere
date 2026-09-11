<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('비로그인 API 요청은 Accept 헤더와 무관하게 401 JSON으로 응답한다', function () {
    // 브라우저·크롤러처럼 Accept: text/html 로 들어오면 기본 동작은 route('login') 리다이렉트를
    // 시도해 500을 낸다 — login 라우트가 없기 때문. 항상 401 JSON이어야 한다.
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/actions')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('비로그인 API 요청은 JSON Accept 헤더에서도 401 JSON으로 응답한다', function () {
    $this->getJson('/api/actions')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('존재하지 않는 API 경로는 SPA index.html 대신 404 JSON으로 응답한다', function () {
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/no-such-endpoint-xyz')
        ->assertStatus(404)
        ->assertJson(['message' => '요청한 API를 찾을 수 없습니다.']);
});
