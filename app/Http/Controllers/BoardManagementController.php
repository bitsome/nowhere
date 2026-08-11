<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BoardManagementController extends Controller
{
    public function __construct(private FileService $fileService) {}

    public function index(Request $request): View
    {
        $actor = $this->resolveActor();

        $search = trim((string) $request->string('search'));
        $type = trim((string) $request->string('type'));
        $status = trim((string) $request->string('status'));

        $boards = Board::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        return view('dashboard.business.boards.index', [
            'boards' => $boards,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'module' => DashboardWorkspaceController::findBusinessModule('boards'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'statusOptions' => Board::statusOptions(),
            'typeLabels' => Board::typeLabels(),
            'typeOptions' => Board::typeOptions(),
            'user' => $actor,
        ]);
    }

    public function show(Board $board): View
    {
        $actor = $this->resolveActor();

        $board->load('user');
        $board->increment('view_count');
        $board->refresh()->load('user');

        return view('dashboard.business.boards.show', [
            'attachmentFiles' => $this->fileService->getFiles($board, Board::ATTACHMENT_COLLECTION),
            'board' => $board,
            'canDelete' => $actor->hasPermission('board.delete'),
            'canUpdate' => $actor->hasPermission('board.update'),
            'module' => DashboardWorkspaceController::findBusinessModule('boards'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'statusOptions' => Board::statusOptions(),
            'typeLabels' => Board::typeLabels(),
        ]);
    }

    public function create(): View
    {
        $actor = $this->resolveActor();

        return view('dashboard.business.boards.create', [
            'attachmentFiles' => collect(),
            'board' => new Board([
                'status' => Board::STATUS_PUBLISHED,
                'type' => Board::TYPE_NOTICE,
            ]),
            'module' => DashboardWorkspaceController::findBusinessModule('boards'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'statusOptions' => Board::statusOptions(),
            'typeLabels' => Board::typeLabels(),
            'typeOptions' => Board::typeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->resolveActor();

        $validated = $this->validateBoard($request);

        $board = Board::create([
            ...$this->boardPayload($validated),
            'is_notice' => $validated['type'] === Board::TYPE_NOTICE,
            'user_id' => $actor->id,
        ]);

        if (array_key_exists('attachments', $validated)) {
            $this->fileService->uploadMany(
                $board,
                $validated['attachments'],
                Board::ATTACHMENT_COLLECTION,
                config('media-library.disk_name'),
            );
        }

        return redirect()
            ->route('dashboard.business.boards.show', $board)
            ->with('status', '게시글이 등록되었습니다.');
    }

    public function edit(Board $board): View
    {
        $actor = $this->resolveActor();

        $board->load('user');

        return view('dashboard.business.boards.edit', [
            'attachmentFiles' => $this->fileService->getFiles($board, Board::ATTACHMENT_COLLECTION),
            'board' => $board,
            'module' => DashboardWorkspaceController::findBusinessModule('boards'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'statusOptions' => Board::statusOptions(),
            'typeLabels' => Board::typeLabels(),
            'typeOptions' => Board::typeOptions(),
        ]);
    }

    public function update(Request $request, Board $board): RedirectResponse
    {
        $actor = $this->resolveActor();

        $validated = $this->validateBoard($request);

        $board->forceFill([
            ...$this->boardPayload($validated),
            'is_notice' => $validated['type'] === Board::TYPE_NOTICE,
        ])->save();

        $this->deleteSelectedAttachments($board, $validated['remove_attachment_ids'] ?? []);

        if (array_key_exists('attachments', $validated)) {
            $this->fileService->uploadMany(
                $board,
                $validated['attachments'],
                Board::ATTACHMENT_COLLECTION,
                config('media-library.disk_name'),
            );
        }

        return redirect()
            ->route('dashboard.business.boards.show', $board)
            ->with('status', '게시글이 수정되었습니다.');
    }

    public function destroy(Board $board): RedirectResponse
    {
        $actor = $this->resolveActor();

        $board->delete();

        return redirect()
            ->route('dashboard.business.boards')
            ->with('status', '게시글이 삭제되었습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBoard(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', Board::typeOptions())],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Board::statusOptions()))],
            'is_private' => ['nullable', 'boolean'],
            'attachments' => ['sometimes', 'array', 'min:1'],
            'attachments.*' => ['required', 'file', 'max:10240'],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['required', 'integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function boardPayload(array $validated): array
    {
        return collect($validated)
            ->except(['attachments', 'remove_attachment_ids'])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $removeAttachmentIds
     */
    private function deleteSelectedAttachments(Board $board, array $removeAttachmentIds): void
    {
        if ($removeAttachmentIds === []) {
            return;
        }

        $board
            ->getMedia(Board::ATTACHMENT_COLLECTION)
            ->filter(fn (Media $media) => in_array($media->id, $removeAttachmentIds, false))
            ->each(fn (Media $media) => $this->fileService->delete($media));
    }

    private function resolveActor(): User
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
