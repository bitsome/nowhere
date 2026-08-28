/**
 * 이미지 리사이즈 유틸 — 업로드 전 canvas로 최대 maxSize(긴 변)로 줄여 JPEG Blob으로 변환한다.
 * 대용량 원본 그대로 업로드되는 것을 방지해 업로드/로딩을 최적화한다.
 * 실패(비이미지 등)하면 null을 반환 — 호출부에서 원본 그대로 사용한다.
 *
 * @param {File} file
 * @param {number} maxSize 긴 변 최대 픽셀 (기본 1080)
 * @returns {Promise<File|null>}
 */
export const resizeImage = (file, maxSize = 1080) =>
    new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const width = Math.max(1, Math.round(img.width * scale));
            const height = Math.max(1, Math.round(img.height * scale));
            const canvas = document.createElement('canvas');

            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(url);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        resolve(null);

                        return;
                    }

                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                },
                'image/jpeg',
                0.82,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };

        img.src = url;
    });
