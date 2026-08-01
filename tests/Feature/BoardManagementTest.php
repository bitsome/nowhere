<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('users with board view permission can access board list and search', function () {
    $viewer = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_OPERATOR,
        'permissions' => ['board.view'],
    ]);

    Board::factory()->create([
        'title' => '공지 테스트',
        'type' => Board::TYPE_NOTICE,
    ]);

    Board::factory()->create([
        'title' => '자유 테스트',
        'type' => Board::TYPE_FREE,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.modules.boards', ['search' => '공지']))
        ->assertSuccessful()
        ->assertSee('공지 테스트')
        ->assertDontSee('자유 테스트');
});

test('board create edit and detail pages render shared toast editor and viewer markers', function () {
    $editor = User::factory()->create([
        'id' => 2,
        'permissions' => ['board.view', 'board.create', 'board.update'],
    ]);

    $board = Board::factory()->create([
        'content' => "# 공지\n\n게시글 본문입니다.",
    ]);

    $this->actingAs($editor)
        ->get(route('dashboard.modules.boards.create'))
        ->assertSuccessful()
        ->assertSee('data-toast-editor-field', false)
        ->assertSee('data-allow-images="true"', false)
        ->assertSee(route('dashboard.modules.files.library'), false)
        ->assertSee(route('dashboard.modules.files.store'), false)
        ->assertSee('게시글 본문은 공통 ToastEditor 기준으로 작성하며, NoWhere 이미지 버튼은 File Manager 모달을 열어 다중 선택 이미지를 Markdown으로 삽입합니다.');

    $this->actingAs($editor)
        ->get(route('dashboard.modules.boards.edit', $board))
        ->assertSuccessful()
        ->assertSee('data-toast-editor-field', false);

    $this->actingAs($editor)
        ->get(route('dashboard.modules.boards.show', $board))
        ->assertSuccessful()
        ->assertSee('data-toast-viewer-field', false)
        ->assertSee('data-toast-viewer-source', false);
});

test('users can create board post with board create permission', function () {
    Storage::fake('public');

    $writer = User::factory()->create([
        'id' => 2,
        'permissions' => ['board.view', 'board.create'],
    ]);

    $this->actingAs($writer)
        ->post(route('dashboard.modules.boards.store'), [
            'type' => Board::TYPE_NOTICE,
            'title' => '신규 공지',
            'content' => "# 신규 공지\n\n게시글 본문입니다.",
            'status' => Board::STATUS_PUBLISHED,
            'is_private' => 0,
            'attachments' => [
                UploadedFile::fake()->image('board-attachment.jpg'),
            ],
        ])
        ->assertSessionHas('status');

    $board = Board::query()->where('title', '신규 공지')->first();

    expect($board)->not->toBeNull();
    expect($board?->content)->toBe("# 신규 공지\n\n게시글 본문입니다.");
    expect($board?->getMedia(Board::ATTACHMENT_COLLECTION))->toHaveCount(1);
});

test('users can update board post with board update permission', function () {
    Storage::fake('public');

    $editor = User::factory()->create([
        'id' => 2,
        'permissions' => ['board.view', 'board.update'],
    ]);

    $board = Board::factory()->create([
        'title' => '수정 전 제목',
    ]);

    $media = $board
        ->addMedia(UploadedFile::fake()->image('existing-board.jpg'))
        ->toMediaCollection(Board::ATTACHMENT_COLLECTION, 'public');

    $this->actingAs($editor)
        ->patch(route('dashboard.modules.boards.update', $board), [
            'type' => $board->type,
            'title' => '수정 후 제목',
            'content' => $board->content,
            'status' => Board::STATUS_PUBLISHED,
            'is_private' => 0,
            'attachments' => [
                UploadedFile::fake()->create('board-doc.pdf', 120, 'application/pdf'),
            ],
            'remove_attachment_ids' => [$media->id],
        ])
        ->assertSessionHas('status');

    expect($board->fresh()->title)->toBe('수정 후 제목');
    expect($board->fresh()->getMedia(Board::ATTACHMENT_COLLECTION))->toHaveCount(1);
});

test('users can delete board post with board delete permission', function () {
    $manager = User::factory()->create([
        'id' => 2,
        'permissions' => ['board.view', 'board.delete'],
    ]);

    $board = Board::factory()->create();

    $this->actingAs($manager)
        ->delete(route('dashboard.modules.boards.destroy', $board))
        ->assertSessionHas('status');

    expect(Board::query()->whereKey($board->id)->exists())->toBeFalse();
});

test('users with board view permission can create board post but cannot update or delete without each permission', function () {
    $viewer = User::factory()->create([
        'id' => 2,
        'permissions' => ['board.view'],
    ]);

    $board = Board::factory()->create();

    $this->actingAs($viewer)
        ->post(route('dashboard.modules.boards.store'), [
            'type' => Board::TYPE_NOTICE,
            'title' => '권한 없는 등록',
            'content' => '본문',
            'status' => Board::STATUS_PUBLISHED,
            'is_private' => 0,
        ])
        ->assertSessionHas('status');

    expect(Board::query()->where('title', '권한 없는 등록')->exists())->toBeTrue();

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.boards.show', $board))
        ->patch(route('dashboard.modules.boards.update', $board), [
            'type' => $board->type,
            'title' => '권한 없는 수정',
            'content' => $board->content,
            'status' => $board->status,
            'is_private' => 0,
        ])
        ->assertSessionHasErrors('permission');

    $this->actingAs($viewer)
        ->from(route('dashboard.modules.boards.show', $board))
        ->delete(route('dashboard.modules.boards.destroy', $board))
        ->assertSessionHasErrors('permission');
});
