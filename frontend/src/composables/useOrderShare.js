import { ref } from 'vue';
import { useDialog, useMessage } from 'naive-ui';
import { apiShareOrder } from '../api/orders';
import { getApiErrorMessage } from '../api/client';

/**
 * 운행 공유 — 등록자가 외부(카카오 오픈채팅·카페 등)에 뿌릴 공개 링크를 만들고 전달한다.
 *
 * 모바일에서는 공유 시트를 우선 쓰고, 지원하지 않으면 클립보드에 복사한다.
 * 현재 서버는 HTTPS가 아니라 clipboard API를 쓸 수 없으므로 textarea 폴백을 반드시 거친다.
 * 그것도 막히면 링크를 직접 보여줘 사용자가 복사할 수 있게 한다.
 */
export function useOrderShare() {
    const message = useMessage();
    const dialog = useDialog();
    const sharing = ref(false);

    const share = async (orderId) => {
        sharing.value = true;

        try {
            const { data } = await apiShareOrder(orderId);
            const url = `${window.location.origin}${data.data.path}`;

            if (navigator.share) {
                try {
                    await navigator.share({ title: 'NoWhere 운행', url });

                    return;
                } catch (e) {
                    // 사용자가 공유 시트를 그냥 닫은 경우 — 실패로 처리하지 않는다
                    if (e?.name === 'AbortError') {
                        return;
                    }
                }
            }

            if (await copyToClipboard(url)) {
                message.success('공유 링크를 복사했습니다. 원하는 곳에 붙여넣으세요.');
                return;
            }

            // 복사까지 막힌 환경 — 링크를 직접 보여준다
            dialog.info({
                title: '공유 링크',
                content: url,
                positiveText: '확인',
            });
        } catch (e) {
            message.error(getApiErrorMessage(e, '공유 링크를 만들지 못했습니다.'));
        } finally {
            sharing.value = false;
        }
    };

    return { sharing, share };
}

/**
 * 클립보드 복사 — 보안 컨텍스트(HTTPS)가 아니면 clipboard API가 없으므로 textarea 폴백을 쓴다.
 */
async function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // 권한 거부·비보안 컨텍스트 — 아래 폴백으로 넘어간다
        }
    }

    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.top = '0';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.select();

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    document.body.removeChild(el);

    return copied;
}
