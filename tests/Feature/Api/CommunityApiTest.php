<?php

use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create(['name' => '작성자']);
    $this->peer = User::factory()->create(['name' => '다른유저']);

    $this->actingAs($this->actor);
});

test('community feed returns posts with like and comment counts', function () {
    $post = CommunityPost::create([
        'user_id' => $this->actor->id,
        'content' => '피드 테스트 글',
    ]);
    $post->likes()->attach($this->peer->id);
    $post->comments()->create(['user_id' => $this->peer->id, 'content' => '좋아요!']);

    $response = $this->getJson('/api/community/posts')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.content'))->toBe('피드 테스트 글');
    expect($response->json('data.0.likes_count'))->toBe(1);
    expect($response->json('data.0.comments_count'))->toBe(1);
    expect($response->json('data.0.is_liked'))->toBeFalse();
    expect($response->json('data.0.is_mine'))->toBeTrue();
});

test('user can create a post', function () {
    $response = $this->postJson('/api/community/posts', [
        'content' => '새 글입니다.',
    ])->assertStatus(201);

    expect($response->json('data.content'))->toBe('새 글입니다.');
    expect($response->json('data.user.name'))->toBe('작성자');
    expect($this->actor->communityPosts)->toHaveCount(1);
});

test('user can toggle like and comment on another post', function () {
    $post = CommunityPost::create([
        'user_id' => $this->peer->id,
        'content' => '다른 사람 글',
    ]);

    $like = $this->postJson("/api/community/posts/{$post->id}/like")->assertOk();
    expect($like->json('data.liked'))->toBeTrue();
    expect($like->json('data.likes_count'))->toBe(1);

    $unlike = $this->postJson("/api/community/posts/{$post->id}/like")->assertOk();
    expect($unlike->json('data.liked'))->toBeFalse();

    $comment = $this->postJson("/api/community/posts/{$post->id}/comments", [
        'content' => '멋진 글이네요.',
    ])->assertStatus(201);

    expect($comment->json('data.content'))->toBe('멋진 글이네요.');
    expect($comment->json('data.comments_count'))->toBe(1);
    expect($post->comments()->count())->toBe(1);
});

test('only author can delete a post', function () {
    $mine = CommunityPost::create(['user_id' => $this->actor->id, 'content' => '내 글']);
    $theirs = CommunityPost::create(['user_id' => $this->peer->id, 'content' => '남의 글']);

    $this->deleteJson("/api/community/posts/{$mine->id}")->assertOk();
    $this->deleteJson("/api/community/posts/{$theirs->id}")->assertForbidden();

    expect(CommunityPost::find($mine->id))->toBeNull();
    expect(CommunityPost::find($theirs->id))->not->toBeNull();
});

test('community actions grant xp to the right users', function () {
    $post = CommunityPost::create(['user_id' => $this->peer->id, 'content' => 'XP 테스트 글']);

    // 글 작성 +5, 댓글 +2
    $this->postJson('/api/community/posts', ['content' => '내가 쓴 글'])->assertStatus(201);
    $this->postJson("/api/community/posts/{$post->id}/comments", ['content' => '댓글'])->assertStatus(201);

    // 좋아요 받은 글 작성자 +1
    $this->postJson("/api/community/posts/{$post->id}/like")->assertOk();

    expect($this->actor->fresh()->xp)->toBe(7);
    expect($this->peer->fresh()->xp)->toBe(1);

    // 레벨 정보 노출
    $response = $this->getJson("/api/community/users/{$this->actor->id}")->assertOk();
    expect($response->json('data.user.xp'))->toBe(7);
    expect($response->json('data.user.level.level'))->toBe(1);
});

test('feed limits comments to latest 3 and detail returns all', function () {
    $post = CommunityPost::create(['user_id' => $this->peer->id, 'content' => '댓글 최적화 글']);

    foreach (['첫째', '둘째', '셋째', '넷째', '다섯째'] as $i => $text) {
        $post->comments()->create(['user_id' => $this->peer->id, 'content' => $text]);
    }

    $feed = $this->getJson('/api/community/posts')->assertOk();
    $feedPost = collect($feed->json('data'))->firstWhere('id', $post->id);

    expect($feedPost['comments_count'])->toBe(5);
    expect($feedPost['comments'])->toHaveCount(3);

    $detail = $this->getJson("/api/community/posts/{$post->id}")->assertOk();
    expect($detail->json('data.comments'))->toHaveCount(5);
    expect($detail->json('data.comments_count'))->toBe(5);
});

test('user page returns profile badges, posts and registered orders', function () {
    $this->peer->update([
        'is_vehicle_verified' => true,
        'is_license_verified' => true,
        'is_vip' => true,
        'vehicle_info' => '카니발 7인승',
    ]);

    CommunityPost::create(['user_id' => $this->peer->id, 'content' => '유저 페이지 글']);
    $order = Order::factory()->create([
        'user_id' => $this->peer->id,
        'customer_name' => '유저페이지고객',
        'status' => Order::STATUS_PUBLISHED,
        'expected_revenue' => 80000,
    ]);
    Order::factory()->create([
        'user_id' => $this->peer->id,
        'status' => Order::STATUS_DRAFT,
    ]);

    $response = $this->getJson("/api/community/users/{$this->peer->id}")->assertOk();

    expect($response->json('data.user.name'))->toBe('다른유저');
    expect($response->json('data.user.is_vehicle_verified'))->toBeTrue();
    expect($response->json('data.user.is_license_verified'))->toBeTrue();
    expect($response->json('data.user.is_vip'))->toBeTrue();
    expect($response->json('data.user.vehicle_info'))->toBe('카니발 7인승');
    expect($response->json('data.user.posts_count'))->toBe(1);
    expect($response->json('data.posts'))->toHaveCount(1);
    expect($response->json('data.orders'))->toHaveCount(1);
    expect($response->json('data.orders.0.order_number'))->toBe($order->order_number);
});
