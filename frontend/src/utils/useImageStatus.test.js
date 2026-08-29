import { describe, expect, it } from 'vitest';
import { useImageStatus } from './useImageStatus';

describe('useImageStatus', () => {
    it('초기 상태는 loading이다', () => {
        const s = useImageStatus();
        expect(s.statusOf('https://x/a.png')).toBe('loading');
    });

    it('markLoaded 후에는 loaded가 된다', () => {
        const s = useImageStatus();
        s.markLoaded('a');
        expect(s.statusOf('a')).toBe('loaded');
    });

    it('markError 후에는 error가 된다', () => {
        const s = useImageStatus();
        s.markError('a');
        expect(s.statusOf('a')).toBe('error');
    });

    it('URL별 상태가 서로 독립적이다', () => {
        const s = useImageStatus();
        s.markLoaded('a');
        s.markError('b');
        expect(s.statusOf('a')).toBe('loaded');
        expect(s.statusOf('b')).toBe('error');
        expect(s.statusOf('c')).toBe('loading');
    });
});
