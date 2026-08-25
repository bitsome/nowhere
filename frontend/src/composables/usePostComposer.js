import { ref } from 'vue';
import { apiCreateCommunityPost } from '../api/community';
import { getApiErrorMessage } from '../api/client';

/**
 * 커뮤니티 글 작성 모달 — 내용/사진(리사이즈)/영상 URL과 등록을 담당한다.
 *
 * @param {object} options
 * @param {object} options.message naive-ui message
 * @param {import('vue').Ref<Array>} options.posts 게시 후 목록 맨 위에 삽입할 ref
 */
export function usePostComposer({ message, posts }) {
    const showComposer = ref(false);
    const composing = ref(false);
    const draftContent = ref('');
    const draftCategory = ref('free');
    const draftImage = ref(null);
    const draftPreviewUrl = ref('');
    const draftVideoUrl = ref('');

    // 카테고리 기본값 — 현재 보고 있는 탭의 카테고리를 그대로 사용 (작성 편의)
    const openComposer = (category = 'free') => {
        draftContent.value = '';
        draftCategory.value = category ?? 'free';
        draftImage.value = null;
        draftPreviewUrl.value = '';
        draftVideoUrl.value = '';
        showComposer.value = true;
    };

    const pickImage = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        // 업로드 전 최대 1080px로 리사이즈 (대용량 원본 그대로 업로드 방지 → 업로드/로딩 최적화)
        const resized = await resizeImage(file, 1080);

        draftImage.value = resized ?? file;
        draftPreviewUrl.value = URL.createObjectURL(draftImage.value);
    };

    /**
     * 이미지를 canvas로 리사이즈해 JPEG Blob으로 변환한다.
     * 실패(비이미지 등)하면 null — 원본 그대로 사용.
     */
    const resizeImage = (file, maxSize) =>
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

    const submitPost = async () => {
        const content = draftContent.value.trim();

        if (content === '' && !draftImage.value) {
            message.warning('글 내용을 입력해주세요.');

            return;
        }

        composing.value = true;

        try {
            const { data } = await apiCreateCommunityPost({
                content,
                category: draftCategory.value,
                image: draftImage.value,
                video_url: draftVideoUrl.value.trim(),
            });
            posts.value.unshift(data.data);
            showComposer.value = false;
            message.success('글이 게시되었습니다.');
        } catch (e) {
            message.error(getApiErrorMessage(e, '글 작성에 실패했습니다.'));
        } finally {
            composing.value = false;
        }
    };

    return {
        showComposer,
        composing,
        draftContent,
        draftCategory,
        draftImage,
        draftPreviewUrl,
        draftVideoUrl,
        openComposer,
        pickImage,
        resizeImage,
        submitPost,
    };
}
