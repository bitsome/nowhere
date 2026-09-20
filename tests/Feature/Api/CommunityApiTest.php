<?php

use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    expect($comment->json('data.is_mine'))->toBeTrue();
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

test('only author can update a post', function () {
    $mine = CommunityPost::create(['user_id' => $this->actor->id, 'content' => '원본 내용']);
    $theirs = CommunityPost::create(['user_id' => $this->peer->id, 'content' => '남의 글']);

    $updated = $this->putJson("/api/community/posts/{$mine->id}", [
        'content' => '수정된 내용',
        'category' => 'route',
        'video_url' => 'https://youtube.com/watch?v=abc123',
    ])->assertOk();

    expect($updated->json('data.content'))->toBe('수정된 내용');
    expect($updated->json('data.category'))->toBe('route');
    expect($updated->json('data.video_url'))->toBe('https://youtube.com/watch?v=abc123');

    $this->putJson("/api/community/posts/{$theirs->id}", ['content' => '훔쳐보기'])->assertForbidden();

    expect($mine->fresh()->content)->toBe('수정된 내용');
    expect($theirs->fresh()->content)->toBe('남의 글');
});

test('only comment author can delete a comment', function () {
    $post = CommunityPost::create(['user_id' => $this->peer->id, 'content' => '댓글 삭제 글']);
    $mine = $post->comments()->create(['user_id' => $this->actor->id, 'content' => '내 댓글']);
    $theirs = $post->comments()->create(['user_id' => $this->peer->id, 'content' => '남의 댓글']);

    $deleted = $this->deleteJson("/api/community/posts/{$post->id}/comments/{$mine->id}")->assertOk();
    expect($deleted->json('data.comments_count'))->toBe(1);

    $this->deleteJson("/api/community/posts/{$post->id}/comments/{$theirs->id}")->assertForbidden();

    expect($post->comments()->count())->toBe(1);
});

test('comments expose is_mine flag for the current user', function () {
    $post = CommunityPost::create(['user_id' => $this->peer->id, 'content' => 'is_mine 테스트']);
    $post->comments()->create(['user_id' => $this->actor->id, 'content' => '내 댓글']);
    $post->comments()->create(['user_id' => $this->peer->id, 'content' => '남의 댓글']);

    $detail = $this->getJson("/api/community/posts/{$post->id}")->assertOk();
    $comments = collect($detail->json('data.comments'));

    expect($comments->firstWhere('content', '내 댓글')['is_mine'])->toBeTrue();
    expect($comments->firstWhere('content', '남의 댓글')['is_mine'])->toBeFalse();
});

test('feed search matches content or author name', function () {
    CommunityPost::create(['user_id' => $this->actor->id, 'content' => '강남에서 인천공항 가는 분 계신가요?']);
    CommunityPost::create(['user_id' => $this->peer->id, 'content' => '부산 노선 문의입니다.']);

    $byContent = $this->getJson('/api/community/posts?search=인천공항')->assertOk();
    expect($byContent->json('data'))->toHaveCount(1);

    $byAuthor = $this->getJson('/api/community/posts?author=다른유저')->assertOk();
    expect($byAuthor->json('data'))->toHaveCount(1);
    expect($byAuthor->json('data.0.content'))->toBe('부산 노선 문의입니다.');

    $none = $this->getJson('/api/community/posts?author=없는사람')->assertOk();
    expect($none->json('data'))->toHaveCount(0);
});

test('feed filters by period', function () {
    $today = CommunityPost::create(['user_id' => $this->actor->id, 'content' => '오늘 글']);
    $recent = CommunityPost::create(['user_id' => $this->actor->id, 'content' => '며칠 전 글']);

    CommunityPost::whereKey($today->id)->update(['created_at' => now()]);
    CommunityPost::whereKey($recent->id)->update(['created_at' => now()->subDays(5)]);

    $todayFeed = $this->getJson('/api/community/posts?period=today')->assertOk();
    expect($todayFeed->json('data'))->toHaveCount(1);
    expect($todayFeed->json('data.0.content'))->toBe('오늘 글');

    $month = $this->getJson('/api/community/posts?period=month')->assertOk();
    // 'month'는 달력 기준(이번 달 1일 이후). 5일 전 글이 전달로 넘어가면(매달 1~5일) month는 1건만 남는다.
    expect($month->json('data'))->toHaveCount(now()->subDays(5)->gte(now()->startOfMonth()) ? 2 : 1);
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

test('카테고리 집계 쿼리가 ONLY_FULL_GROUP_BY 를 위반하지 않는다', function () {
    // 운영(MySQL)은 sql_mode 에 ONLY_FULL_GROUP_BY 가 켜져 있다(strict). 집계 쿼리에 community_posts.* 가
    // 함께 나가면 1055 오류로 커뮤니티 피드 전체가 500이 된다 — SQLite 로컬 테스트에서 드러나지 않던 운영 장애.
    CommunityPost::create(['user_id' => $this->actor->id, 'content' => '자유 글', 'category' => 'free']);
    CommunityPost::create(['user_id' => $this->peer->id, 'content' => '노선 글', 'category' => 'route']);

    $executed = [];
    DB::listen(function ($query) use (&$executed) {
        $executed[] = $query->sql;
    });

    $response = $this->getJson('/api/community/posts')->assertOk();

    $countSql = collect($executed)->first(fn (string $sql) => str_contains($sql, 'count(*) as total'));

    expect($countSql)->not->toBeNull();
    expect($countSql)->not->toContain('.*');          // community_posts.* 가 함께 선택되면 안 된다
    expect($countSql)->not->toContain('likes_count'); // withCount 집계 서브쿼리도 함께 나가면 안 된다

    expect($response->json('meta.category_counts.free'))->toBe(1);
    expect($response->json('meta.category_counts.route'))->toBe(1);
});

test('설문 글을 작성하면 선택지와 함께 피드에 노출된다', function () {
    $created = $this->postJson('/api/community/posts', [
        'content' => '다음 달 인천공항 주차장 어디로 옮길까요?',
        'category' => 'survey',
        'survey_options' => ['제1여객터미널', '제2여객터미널', '장기주차장'],
    ])->assertStatus(201);

    expect($created->json('data.survey.options'))->toHaveCount(3);
    expect($created->json('data.survey.options.0.text'))->toBe('제1여객터미널');
    expect($created->json('data.survey.options.0.votes'))->toBe(0);
    expect($created->json('data.survey.total'))->toBe(0);
    expect($created->json('data.survey.my_option'))->toBeNull();
    expect($created->json('data.survey.closed'))->toBeFalse();

    $feed = $this->getJson('/api/community/posts')->assertOk();

    expect($feed->json('data.0.survey.options.2.text'))->toBe('장기주차장');
});

test('설문 선택지가 2개 미만이면 거부된다', function () {
    $this->postJson('/api/community/posts', [
        'content' => '선택지 하나짜리 설문',
        'category' => 'survey',
        'survey_options' => ['하나뿐'],
    ])->assertStatus(422)->assertJsonValidationErrors('survey_options');
});

test('설문에는 1인 1표 — 다시 투표하면 선택만 바뀐다', function () {
    $post = CommunityPost::create([
        'user_id' => $this->actor->id,
        'category' => 'survey',
        'content' => '어느 노선이 가장 좋으세요?',
        'survey_options' => ['공항', '관광', '장거리'],
    ]);

    $first = $this->postJson("/api/community/posts/{$post->id}/vote", ['option_id' => 0])->assertOk();
    expect($first->json('data.my_option'))->toBe(0);
    expect($first->json('data.total'))->toBe(1);
    expect($first->json('data.options.0.votes'))->toBe(1);

    // 같은 사람이 다시 투표 — 표가 늘지 않고 선택만 옮겨간다
    $second = $this->postJson("/api/community/posts/{$post->id}/vote", ['option_id' => 2])->assertOk();
    expect($second->json('data.my_option'))->toBe(2);
    expect($second->json('data.total'))->toBe(1);
    expect($second->json('data.options.0.votes'))->toBe(0);
    expect($second->json('data.options.2.votes'))->toBe(1);

    expect(DB::table('community_survey_votes')->where('post_id', $post->id)->count())->toBe(1);

    // 다른 사람이 투표하면 득표가 함께 쌓인다
    $this->actingAs($this->peer);
    $third = $this->postJson("/api/community/posts/{$post->id}/vote", ['option_id' => 0])->assertOk();
    expect($third->json('data.total'))->toBe(2);
    expect($third->json('data.my_option'))->toBe(0);
});

test('마감된 설문과 설문이 아닌 글에는 투표할 수 없다', function () {
    $closed = CommunityPost::create([
        'user_id' => $this->actor->id,
        'category' => 'survey',
        'content' => '마감된 설문',
        'survey_options' => ['A', 'B'],
        'survey_closes_at' => now()->subDay(),
    ]);

    $normal = CommunityPost::create(['user_id' => $this->actor->id, 'content' => '일반 글']);

    $this->postJson("/api/community/posts/{$closed->id}/vote", ['option_id' => 0])->assertStatus(422);
    $this->postJson("/api/community/posts/{$normal->id}/vote", ['option_id' => 0])->assertStatus(422);

    // 없는 선택지도 거부
    $open = CommunityPost::create([
        'user_id' => $this->actor->id,
        'category' => 'survey',
        'content' => '진행 중 설문',
        'survey_options' => ['A', 'B'],
    ]);

    $this->postJson("/api/community/posts/{$open->id}/vote", ['option_id' => 9])->assertStatus(422);
});

test('여행지 맛집 글은 장소 카드 정보로 노출된다', function () {
    $created = $this->postJson('/api/community/posts', [
        'content' => '제주 여행 중 들른 흑돼지집 — 웨이팅 있지만 회전 빠름',
        'category' => 'food',
        'place_name' => '제주 흑돼지 명가',
        'place_region' => '제주 서귀포',
        'place_address' => '제주특별자치도 서귀포시 중문로 12',
        'place_map_url' => 'https://map.example.com/jeju-blackpork',
    ])->assertStatus(201);

    expect($created->json('data.place.name'))->toBe('제주 흑돼지 명가');
    expect($created->json('data.place.region'))->toBe('제주 서귀포');
    expect($created->json('data.place.address'))->toBe('제주특별자치도 서귀포시 중문로 12');
    expect($created->json('data.place.map_url'))->toBe('https://map.example.com/jeju-blackpork');

    // 장소 정보가 없는 일반 글은 카드가 아니다
    $plain = $this->postJson('/api/community/posts', ['content' => '그냥 잡담'])->assertStatus(201);
    expect($plain->json('data.place'))->toBeNull();
});
