<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('dashboard hub shows file management module link', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('파일관리')
        ->assertSee(route('dashboard.modules.files'), false);
});

test('authenticated users can access file management dashboard module page', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.modules.files'))
        ->assertSuccessful()
        ->assertSee('파일 선택, 업로드, 이미지 미리보기')
        ->assertSee('업로드된 파일 관리');
});

test('file management page uses shared flash toast marker for uploaded status', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->withSession([
            'status' => '파일이 업로드되었습니다.',
        ])
        ->get(route('dashboard.modules.files'))
        ->assertSuccessful()
        ->assertSee('data-toast-flash', false)
        ->assertSee('업로드 완료')
        ->assertSee('파일이 업로드되었습니다.');
});

test('authenticated users can upload files in chunks in file manager module', function () {
    Storage::fake('public');
    Storage::fake('local');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.modules.files.store'), [
            'file' => UploadedFile::fake()->createWithContent('sample-image.part1', 'hello '),
            'upload_id' => 'upload-test-1',
            'chunk_index' => 0,
            'total_chunks' => 2,
            'original_name' => 'sample-image.txt',
            'total_size' => 11,
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('completed', false);

    $this->actingAs($user)
        ->post(route('dashboard.modules.files.store'), [
            'file' => UploadedFile::fake()->createWithContent('sample-image.part2', 'world'),
            'upload_id' => 'upload-test-1',
            'chunk_index' => 1,
            'total_chunks' => 2,
            'original_name' => 'sample-image.txt',
            'total_size' => 11,
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('completed', true);

    expect($user->fresh()->getMedia('file-manager'))->toHaveCount(1);
});

test('authenticated users can upload editor images through file manager api and receive url json', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $response = $this->actingAs($user)
        ->post(route('dashboard.modules.files.store'), [
            'files' => [
                UploadedFile::fake()->image('editor-image.jpg'),
            ],
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'files' => [
                [
                    'id',
                    'name',
                    'file_name',
                    'url',
                    'download_url',
                ],
            ],
        ]);

    expect($response->json('files.0.url'))->not->toBeEmpty();
    expect($user->fresh()->getMedia('file-manager'))->toHaveCount(1);
});

test('authenticated users can fetch image library json for editor modal', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $imageMedia = $user
        ->addMedia(UploadedFile::fake()->image('library-image.jpg'))
        ->toMediaCollection('file-manager', 'public');

    $user
        ->addMedia(UploadedFile::fake()->create('document.pdf', 12, 'application/pdf'))
        ->toMediaCollection('file-manager', 'public');

    $response = $this->actingAs($user)
        ->get(route('dashboard.modules.files.library', ['search' => 'library']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', $imageMedia->id)
        ->assertJsonPath('files.0.delete_url', route('dashboard.modules.files.destroy', $imageMedia));
});

test('authenticated users can download uploaded files in file manager module', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $media = $user
        ->addMedia(UploadedFile::fake()->create('sample-file.txt', 12))
        ->toMediaCollection('file-manager', 'public');

    $this->actingAs($user)
        ->get(route('dashboard.modules.files.download', $media))
        ->assertSuccessful()
        ->assertHeader('content-disposition');
});

test('authenticated users can delete uploaded files in file manager module', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $media = $user
        ->addMedia(UploadedFile::fake()->create('delete-target.txt', 10))
        ->toMediaCollection('file-manager', 'public');

    $this->actingAs($user)
        ->delete(route('dashboard.modules.files.destroy', $media))
        ->assertRedirect(route('dashboard.modules.files'))
        ->assertSessionHas('status');

    expect($user->fresh()->getMedia('file-manager'))->toHaveCount(0);
});

test('authenticated users can delete uploaded files with json response for editor modal', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'id' => 2,
    ]);

    $media = $user
        ->addMedia(UploadedFile::fake()->image('delete-target-image.jpg'))
        ->toMediaCollection('file-manager', 'public');

    $this->actingAs($user)
        ->delete(route('dashboard.modules.files.destroy', $media), [], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', '파일이 삭제되었습니다.');

    expect($user->fresh()->getMedia('file-manager'))->toHaveCount(0);
});
