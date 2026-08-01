import { postData } from '../../../api/index.js';

function resolveFileName(blob) {
    if (blob instanceof File && blob.name) {
        return blob.name;
    }

    const extension = blob.type?.split('/')[1] || 'png';

    return `editor-image.${extension}`;
}

export async function uploadImagesToFileManager(files, uploadUrl) {
    if (!uploadUrl) {
        return [];
    }

    const formData = new FormData();

    files.forEach((blob) => {
        const file = blob instanceof File
            ? blob
            : new File([blob], resolveFileName(blob), {
                type: blob.type || 'image/png',
            });

        formData.append('files[]', file, file.name);
    });

    const response = await postData(uploadUrl, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });

    return Array.isArray(response.files) ? response.files : [];
}

export async function uploadImageToFileManager(blob, uploadUrl) {
    const uploadedFiles = await uploadImagesToFileManager([blob], uploadUrl);
    const uploadedFile = uploadedFiles[0];

    if (!uploadedFile?.url) {
        throw new Error('이미지 업로드 URL을 받을 수 없습니다.');
    }

    return {
        altText: uploadedFile.name || blob.name || 'image',
        url: uploadedFile.url,
    };
}

export function buildImageMarkdown(files = []) {
    return files
        .filter((file) => typeof file?.url === 'string' && file.url !== '')
        .map((file) => {
            const altText = file.name || file.file_name || 'image';

            return `![${altText}](${file.url})`;
        })
        .join('\n\n');
}
