/**
 * 커뮤니티 글 표시용 순수 함수 — 상대 시간·아바타 글자·영상 URL 해석.
 *
 * 같은 계산이 피드 목록·글 상세·사용자 페이지에 각각 복사돼 있었다. 화면이 셋이라
 * 한쪽만 고치면 같은 글이 화면마다 다른 시각·다른 썸네일로 보인다. 한 벌만 둔다.
 */

/** 상대 시간 — 잘못된 날짜는 원문 앞 10자로 떨어뜨린다 (NaN 방어) */
export function timeAgo(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (isNaN(date.getTime())) {
        return String(iso).slice(0, 10);
    }

    const diff = (Date.now() - date.getTime()) / 1000;

    if (diff < 60) {
        return '방금 전';
    }
    if (diff < 3600) {
        return `${Math.floor(diff / 60)}분 전`;
    }
    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}시간 전`;
    }
    if (diff < 86400 * 7) {
        return `${Math.floor(diff / 86400)}일 전`;
    }

    return date.toLocaleDateString('ko-KR');
}

/** 아바타에 넣을 첫 글자 */
export const avatarText = (name) => (name ?? '?').charAt(0).toUpperCase();

/**
 * 영상 URL 해석 — 유튜브(영상/숏츠)는 썸네일·임베드 주소를, 그 외는 원본 링크를 준다.
 * 빈 값이면 null.
 *
 * @returns {{kind: 'youtube', id: string, thumb: string, embed: string}|{kind: 'link', url: string}|null}
 */
export function parseVideo(url) {
    if (!url) {
        return null;
    }

    const match = String(url).match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]{6,})/);

    if (match) {
        return {
            kind: 'youtube',
            id: match[1],
            thumb: `https://i.ytimg.com/vi/${match[1]}/hqdefault.jpg`,
            embed: `https://www.youtube.com/embed/${match[1]}`,
        };
    }

    return { kind: 'link', url };
}
