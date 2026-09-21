import { describe, expect, it, vi } from 'vitest';
import { avatarText, parseVideo, timeAgo } from './communityPost';

/**
 * 피드 목록·글 상세·사용자 페이지가 같은 글을 같은 시각·같은 썸네일로 보여야 한다.
 * 세 화면이 각자 구현하던 계산이라 값이 어긋나기 쉬운 자리였다.
 */
describe('timeAgo', () => {
    it('경과 시간을 구간별 문구로 바꾼다', () => {
        const now = Date.now();
        const ago = (seconds) => new Date(now - seconds * 1000).toISOString();

        expect(timeAgo(ago(10))).toBe('방금 전');
        expect(timeAgo(ago(120))).toBe('2분 전');
        expect(timeAgo(ago(7200))).toBe('2시간 전');
        expect(timeAgo(ago(86400 * 3))).toBe('3일 전');
    });

    it('일주일이 넘으면 날짜로 보여준다', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-09-21T12:00:00Z'));

        expect(timeAgo('2026-09-01T12:00:00Z')).toMatch(/2026/);

        vi.useRealTimers();
    });

    it('빈 값·잘못된 날짜에도 깨지지 않는다', () => {
        expect(timeAgo(null)).toBe('');
        expect(timeAgo('')).toBe('');
        expect(timeAgo('이상한값-20260921')).toBe('이상한값-20260');
    });
});

describe('avatarText', () => {
    it('이름 첫 글자를 대문자로 준다', () => {
        expect(avatarText('kim')).toBe('K');
        expect(avatarText('김민준')).toBe('김');
    });

    it('이름이 null 이면 물음표로 대체한다', () => {
        expect(avatarText(null)).toBe('?');
        expect(avatarText(undefined)).toBe('?');
        // 빈 문자열은 그대로 빈 글자 — 값이 없는 것(null)과 구분한다
        expect(avatarText('')).toBe('');
    });
});

describe('parseVideo', () => {
    it('유튜브 주소는 썸네일·임베드로 바꾼다', () => {
        for (const url of [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/shorts/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
        ]) {
            expect(parseVideo(url)).toMatchObject({
                kind: 'youtube',
                id: 'dQw4w9WgXcQ',
                thumb: 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                embed: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            });
        }
    });

    it('유튜브가 아니면 원본 링크로 돌려준다', () => {
        expect(parseVideo('https://example.com/v.mp4')).toEqual({
            kind: 'link',
            url: 'https://example.com/v.mp4',
        });
    });

    it('빈 값은 null', () => {
        expect(parseVideo(null)).toBeNull();
        expect(parseVideo('')).toBeNull();
    });
});
