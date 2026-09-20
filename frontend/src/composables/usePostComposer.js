import { computed, ref } from 'vue';
import { apiCreateCommunityPost } from '../api/community';
import { getApiErrorMessage } from '../api/client';
import { resizeImage } from '../utils/imageResize';

/**
 * 커뮤니티 글 작성 모달 — 내용/사진(리사이즈)/영상 URL과 등록을 담당한다.
 * 설문조사(선택지·마감)와 여행지 맛집(장소 정보) 입력도 함께 관리한다.
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

    // 설문 — 선택지는 2~10개, 마감일은 선택
    const draftSurveyOptions = ref(['', '']);
    const draftSurveyCloses = ref('');

    // 여행지 맛집 — 장소 정보
    const draftPlaceName = ref('');
    const draftPlaceRegion = ref('');
    const draftPlaceAddress = ref('');
    const draftPlaceMapUrl = ref('');

    const isSurveyCategory = computed(() => draftCategory.value === 'survey');
    const isPlaceCategory = computed(() => draftCategory.value === 'food');

    // 카테고리 기본값 — 현재 보고 있는 탭의 카테고리를 그대로 사용 (작성 편의)
    const openComposer = (category = 'free') => {
        draftContent.value = '';
        draftCategory.value = category ?? 'free';
        draftImage.value = null;
        draftPreviewUrl.value = '';
        draftVideoUrl.value = '';
        draftSurveyOptions.value = ['', ''];
        draftSurveyCloses.value = '';
        draftPlaceName.value = '';
        draftPlaceRegion.value = '';
        draftPlaceAddress.value = '';
        draftPlaceMapUrl.value = '';
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

    const addSurveyOption = () => {
        if (draftSurveyOptions.value.length < 10) {
            draftSurveyOptions.value.push('');
        }
    };

    const removeSurveyOption = (index) => {
        if (draftSurveyOptions.value.length > 2) {
            draftSurveyOptions.value.splice(index, 1);
        }
    };

    // 마감일은 그날 끝까지 유효하도록 23:59:59로 보낸다 (날짜만 보내면 자정에 마감된다)
    const surveyClosesAt = () =>
        draftSurveyCloses.value ? `${draftSurveyCloses.value}T23:59:59` : null;

    const submitPost = async () => {
        const content = draftContent.value.trim();

        if (content === '' && !draftImage.value) {
            message.warning('글 내용을 입력해 주세요.');

            return;
        }

        const options = draftSurveyOptions.value
            .map((option) => option.trim())
            .filter((option) => option !== '');

        if (isSurveyCategory.value && options.length < 2) {
            message.warning('설문 선택지를 2개 이상 입력해 주세요.');

            return;
        }

        if (isPlaceCategory.value && draftPlaceName.value.trim() === '') {
            message.warning('맛집 이름을 입력해 주세요.');

            return;
        }

        composing.value = true;

        try {
            const { data } = await apiCreateCommunityPost({
                content,
                category: draftCategory.value,
                image: draftImage.value,
                video_url: draftVideoUrl.value.trim(),
                survey_options: options,
                survey_closes_at: surveyClosesAt(),
                place_name: draftPlaceName.value.trim(),
                place_region: draftPlaceRegion.value.trim(),
                place_address: draftPlaceAddress.value.trim(),
                place_map_url: draftPlaceMapUrl.value.trim(),
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
        draftSurveyOptions,
        draftSurveyCloses,
        draftPlaceName,
        draftPlaceRegion,
        draftPlaceAddress,
        draftPlaceMapUrl,
        isSurveyCategory,
        isPlaceCategory,
        openComposer,
        pickImage,
        resizeImage,
        addSurveyOption,
        removeSurveyOption,
        submitPost,
    };
}
