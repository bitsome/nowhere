<x-layouts.app title="파일관리">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $module['description'] }}
                </p>
            </div>

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">현재 단계</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    업로드 엔진과 공통 서비스 기반을 바탕으로 1차 Upload UI와 Manager UI를 연결한 상태입니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            @if ($errors->any())
                <div class="page-panel border border-[#dddddd] bg-[#f7f7f7] text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">
                    <p class="font-medium">입력값을 확인해주세요.</p>
                    <ul class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">File Module</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">공통 파일관리 워크스페이스</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500 dark:text-gray-400">
                        `spatie/laravel-medialibrary`를 업로드 엔진으로 사용하고, NoWhere의 파일 목록, 업로드, 미리보기, 다운로드,
                        삭제 화면은 공통 File Module로 분리해서 재사용합니다.
                    </p>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">모듈 요약</p>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">적용 대상</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">Board / Profile / Order / Driver</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">업로드 엔진</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">Spatie Media Library</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">서비스 계층</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">App\Services\FileService</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">내 업로드 수</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $uploadFiles->count() }}건</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">전체 관리 수</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $managerFiles->count() }}건</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article class="stat-card">
                    <p class="text-sm text-gray-500 dark:text-gray-400">1차 범위</p>
                    <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">업로드 / 목록 / 삭제</p>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        파일 업로드, 목록, 삭제, 다운로드, 이미지 미리보기를 공통 흐름으로 정리합니다.
                    </p>
                </article>
                <article class="stat-card">
                    <p class="text-sm text-gray-500 dark:text-gray-400">구조 원칙</p>
                    <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Upload + Manager</p>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        일반 첨부 화면과 관리자 파일관리 화면을 분리해 재사용성과 유지보수성을 높입니다.
                    </p>
                </article>
                <article class="stat-card">
                    <p class="text-sm text-gray-500 dark:text-gray-400">현재 상태</p>
                    <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Upload + Manager</p>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        업로드 화면과 관리 화면을 분리한 기본 구조가 준비되었고, 이후 도메인 모듈이 이 흐름을 재사용합니다.
                    </p>
                </article>
            </section>

            @include('dashboard.business.files.partials.upload-panel')

            @include('dashboard.business.files.partials.upload-list')

            @include('dashboard.business.files.partials.manager-table')
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-chunk-upload-form]');
            const input = document.querySelector('[data-file-preview-input]');
            const emptyState = document.querySelector('[data-file-preview-empty]');
            const previewList = document.querySelector('[data-file-preview-list]');
            const uploadButton = document.querySelector('[data-upload-button]');
            const uploadStatus = document.querySelector('[data-upload-status]');
            const progressBar = document.querySelector('[data-upload-progress-bar]');

            if (!form || !input || !emptyState || !previewList || !uploadButton || !uploadStatus || !progressBar) {
                return;
            }

            const chunkSize = 1024 * 1024;
            const csrfToken = form.dataset.csrfToken;
            let selectedFiles = [];

            const updateStatus = (message) => {
                uploadStatus.textContent = message;
            };

            const updateProgress = (percent) => {
                progressBar.style.width = `${percent}%`;
            };

            const previewCard = (file, index) => {
                const card = document.createElement('article');
                card.className = 'rounded-[10px] border border-[#dddddd] bg-[#f3f3f3] p-2.5 dark:border-[#2a2a2a] dark:bg-[#181818]';
                card.dataset.previewIndex = String(index);

                const header = document.createElement('div');
                const fileTitle = document.createElement('p');
                fileTitle.className = 'truncate text-sm font-medium text-gray-900 dark:text-gray-100';
                fileTitle.textContent = file.name;

                const fileMeta = document.createElement('p');
                fileMeta.className = 'mt-1 truncate text-[11px] text-gray-500 dark:text-gray-400';
                fileMeta.textContent = `${Math.max(1, Math.ceil(file.size / 1024))} KB / ${file.type || '알 수 없음'}`;

                header.appendChild(fileTitle);
                header.appendChild(fileMeta);

                const preview = document.createElement(file.type.startsWith('image/') ? 'img' : 'div');

                if (file.type.startsWith('image/')) {
                    preview.className = 'mt-2.5 h-[96px] w-full rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] object-contain dark:border-[#2a2a2a] dark:bg-[#171717]';
                    preview.alt = file.name;
                    preview.title = file.name;

                    const reader = new FileReader();
                    reader.onload = (event) => {
                        preview.src = String(event.target?.result ?? '');
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.className = 'mt-2.5 flex h-[96px] items-center justify-center rounded-[10px] border border-dashed border-[#d8d8d8] px-3 text-center text-xs text-gray-500 dark:border-[#2a2a2a] dark:text-gray-400';
                    preview.textContent = '이미지 미리보기를 지원하지 않는 파일입니다.';
                }

                const progressWrap = document.createElement('div');
                progressWrap.className = 'mt-2.5';
                const progressOuter = document.createElement('div');
                progressOuter.className = 'h-2 overflow-hidden rounded-full bg-[#e5e5e5] dark:bg-[#262626]';

                const progressInner = document.createElement('div');
                progressInner.className = 'h-full w-0 rounded-full bg-[#6a6a6a] transition-all';
                progressInner.dataset.previewProgress = '';

                const progressText = document.createElement('p');
                progressText.className = 'mt-2 text-xs text-gray-500 dark:text-gray-400';
                progressText.dataset.previewStatus = '';
                progressText.textContent = '대기 중';

                progressOuter.appendChild(progressInner);
                progressWrap.appendChild(progressOuter);
                progressWrap.appendChild(progressText);

                card.appendChild(header);
                card.appendChild(preview);
                card.appendChild(progressWrap);

                return card;
            };

            const renderPreviews = () => {
                previewList.innerHTML = '';

                if (selectedFiles.length === 0) {
                    emptyState.classList.remove('hidden');
                    previewList.classList.add('hidden');
                    updateStatus('파일을 선택한 뒤 업로드 버튼을 눌러주세요.');
                    updateProgress(0);

                    return;
                }

                emptyState.classList.add('hidden');
                previewList.classList.remove('hidden');

                selectedFiles.forEach((file, index) => {
                    previewList.appendChild(previewCard(file, index));
                });

                updateStatus(`선택된 파일 ${selectedFiles.length}건`);
            };

            const previewElements = (index) => {
                const card = previewList.querySelector(`[data-preview-index="${index}"]`);

                if (!card) {
                    return null;
                }

                return {
                    progress: card.querySelector('[data-preview-progress]'),
                    status: card.querySelector('[data-preview-status]'),
                };
            };

            const uploadFileInChunks = async (file, index, progressState) => {
                const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
                const uploadId = `file-${Date.now()}-${index}-${window.crypto?.randomUUID ? window.crypto.randomUUID() : Math.random().toString(16).slice(2)}`;

                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
                    const start = chunkIndex * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);
                    const formData = new FormData();

                    formData.append('file', chunk, file.name);
                    formData.append('upload_id', uploadId);
                    formData.append('chunk_index', String(chunkIndex));
                    formData.append('total_chunks', String(totalChunks));
                    formData.append('original_name', file.name);
                    formData.append('total_size', String(file.size));

                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        const message = payload.message || '청크 업로드 중 오류가 발생했습니다.';
                        throw new Error(message);
                    }

                    progressState.completedChunks += 1;

                    const filePercent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                    const overallPercent = Math.round((progressState.completedChunks / progressState.totalChunks) * 100);
                    const elements = previewElements(index);

                    if (elements?.progress) {
                        elements.progress.style.width = `${filePercent}%`;
                    }

                    if (elements?.status) {
                        elements.status.textContent = payload.completed ? '업로드 완료' : `${chunkIndex + 1}/${totalChunks} 청크 업로드`;
                    }

                    updateProgress(overallPercent);
                    updateStatus(`전체 업로드 ${overallPercent}%`);
                }
            };

            input.addEventListener('change', (event) => {
                selectedFiles = Array.from(event.target.files || []);
                renderPreviews();
            });

            uploadButton.addEventListener('click', async () => {
                if (selectedFiles.length === 0) {
                    updateStatus('먼저 업로드할 파일을 선택해주세요.');

                    return;
                }

                uploadButton.disabled = true;
                updateProgress(0);
                updateStatus('청크 업로드를 시작합니다.');

                const progressState = {
                    completedChunks: 0,
                    totalChunks: selectedFiles.reduce((sum, file) => {
                        return sum + Math.max(1, Math.ceil(file.size / chunkSize));
                    }, 0),
                };

                try {
                    for (let index = 0; index < selectedFiles.length; index += 1) {
                        await uploadFileInChunks(selectedFiles[index], index, progressState);
                    }

                    updateProgress(100);
                    updateStatus('모든 파일 업로드가 완료되었습니다. 목록을 새로고침합니다.');

                    window.setTimeout(() => {
                        window.location.href = form.action;
                    }, 600);
                } catch (error) {
                    updateStatus(error instanceof Error ? error.message : '파일 업로드 중 오류가 발생했습니다.');
                } finally {
                    uploadButton.disabled = false;
                }
            });
        });
    </script>
</x-layouts.app>
