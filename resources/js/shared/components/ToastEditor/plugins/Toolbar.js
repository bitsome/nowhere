export function createFileManagerToolbarButton({ onClick } = {}) {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'toastui-editor-toolbar-icons image nowhere-editor-toolbar-image';
    button.setAttribute('aria-label', '이미지 파일관리');
    button.setAttribute('title', '이미지 파일관리');

    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        onClick?.();
    });

    return button;
}
