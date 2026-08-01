<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagementController extends Controller
{
    private const COLLECTION_NAME = 'file-manager';

    public function __construct(private FileService $fileService) {}

    public function index(): View
    {
        $user = $this->resolveActor();

        return view('dashboard.modules.files', [
            'managerFiles' => Media::query()->latest()->get(),
            'module' => DashboardWorkspaceController::findWorkspaceModule('files'),
            'modules' => DashboardWorkspaceController::workspaceModules(),
            'uploadFiles' => $this->fileService->getFiles($user, self::COLLECTION_NAME),
            'user' => $user,
        ]);
    }

    public function library(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search')->value());

        $files = Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('file_name', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->limit(60)
            ->get();

        return response()->json([
            'files' => $files->map(fn (Media $media) => $this->serializeMedia($media))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->resolveActor();

        if ($request->has('upload_id')) {
            return $this->storeChunk($request, $user);
        }

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:10240'],
        ]);

        $mediaItems = $this->fileService->uploadMany(
            $user,
            $validated['files'],
            self::COLLECTION_NAME,
            config('media-library.disk_name'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'files' => $mediaItems->map(fn (Media $media) => $this->serializeMedia($media))->values(),
                'message' => '파일이 업로드되었습니다.',
            ]);
        }

        return redirect()
            ->route('dashboard.modules.files')
            ->with('status', '파일이 업로드되었습니다.');
    }

    public function download(Request $request, Media $media): StreamedResponse
    {
        return $media->toResponse($request);
    }

    public function destroy(Media $media): JsonResponse|RedirectResponse
    {
        $this->fileService->delete($media);

        if (request()->expectsJson()) {
            return response()->json([
                'message' => '파일이 삭제되었습니다.',
            ]);
        }

        return redirect()
            ->route('dashboard.modules.files')
            ->with('status', '파일이 삭제되었습니다.');
    }

    private function storeChunk(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'upload_id' => ['required', 'string', 'max:100'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'original_name' => ['required', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.config('media-library.max_file_size')],
        ]);

        if ($validated['chunk_index'] >= $validated['total_chunks']) {
            return response()->json([
                'message' => '청크 인덱스가 올바르지 않습니다.',
            ], 422);
        }

        $media = $this->fileService->storeChunk(
            $user,
            $validated['file'],
            $validated['upload_id'],
            $validated['chunk_index'],
            $validated['total_chunks'],
            $validated['original_name'],
            self::COLLECTION_NAME,
            config('media-library.disk_name'),
        );

        return response()->json([
            'completed' => $media !== null,
            'message' => $media !== null ? '파일 업로드가 완료되었습니다.' : '청크가 저장되었습니다.',
            'uploaded_chunks' => $validated['chunk_index'] + 1,
            'total_chunks' => $validated['total_chunks'],
        ]);
    }

    private function resolveActor(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMedia(Media $media): array
    {
        return [
            'collection_name' => $media->collection_name,
            'created_at' => $media->created_at?->toDateTimeString(),
            'delete_url' => route('dashboard.modules.files.destroy', $media),
            'download_url' => route('dashboard.modules.files.download', $media),
            'file_name' => $media->file_name,
            'id' => $media->id,
            'mime_type' => $media->mime_type,
            'name' => $media->name,
            'size' => $media->size,
            'url' => $this->fileService->previewUrl($media),
        ];
    }
}
