<section class="page-panel panel-gray">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">파일 목록</p>
            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">내 업로드 목록</h3>
        </div>
        <span class="status-badge">{{ $uploadFiles->count() }}건</span>
    </div>

    @php
        $galleryItems = $uploadFiles->map(fn ($media) => [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
            'size' => number_format($media->size / 1024, 1).' KB',
            'mimeType' => $media->mime_type ?? '알 수 없음',
            'imageUrl' => str_starts_with($media->mime_type ?? '', 'image/')
                ? ($media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl())
                : null,
            'actions' => [
                ['icon' => 'download', 'label' => '다운로드', 'href' => route('dashboard.business.files.download', $media)],
                ['icon' => 'trash', 'label' => '삭제', 'danger' => true, 'type' => 'delete', 'url' => route('dashboard.business.files.destroy', $media), 'confirm' => '파일을 삭제하시겠습니까?'],
            ],
        ])->values()->all();
    @endphp

    <div
        class="mt-4"
        data-upload-gallery
        data-csrf-token="{{ csrf_token() }}"
        data-items='@json($galleryItems)'
        data-empty-title="업로드된 파일이 없습니다."
        data-empty-description="상단 Upload UI에서 파일을 업로드하면 이 목록에 바로 표시됩니다."
    ></div>
</section>
