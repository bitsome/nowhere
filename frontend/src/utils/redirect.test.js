import { describe, expect, it } from 'vitest';
import { safeRedirectPath } from './redirect';

/**
 * redirect 쿼리는 공유 링크에 그대로 실려 외부에 노출되고 누구나 조작할 수 있다.
 * 이 값으로 외부 주소로 튕기면 피싱 통로가 되므로, 내부 경로만 통과해야 한다.
 */
describe('safeRedirectPath — 내부 경로만 허용한다', () => {
    it('내부 경로는 그대로 돌려준다', () => {
        expect(safeRedirectPath('/share/order/abc123')).toBe('/share/order/abc123');
        expect(safeRedirectPath('/orders/12')).toBe('/orders/12');
        expect(safeRedirectPath('/')).toBe('/');
    });

    it('앞뒤 공백은 무시한다', () => {
        expect(safeRedirectPath('  /market  ')).toBe('/market');
    });

    it('외부 주소는 거부한다', () => {
        expect(safeRedirectPath('https://evil.example.com')).toBeNull();
        expect(safeRedirectPath('http://evil.example.com/path')).toBeNull();
        expect(safeRedirectPath('javascript:alert(1)')).toBeNull();
    });

    it('프로토콜 상대 주소(//host)는 거부한다', () => {
        expect(safeRedirectPath('//evil.example.com')).toBeNull();
    });

    it('역슬래시 우회는 거부한다', () => {
        expect(safeRedirectPath('/\\evil.example.com')).toBeNull();
        expect(safeRedirectPath('/share\\order')).toBeNull();
    });

    it('빈 값·문자열이 아닌 값은 null 이다', () => {
        expect(safeRedirectPath('')).toBeNull();
        expect(safeRedirectPath('   ')).toBeNull();
        expect(safeRedirectPath(undefined)).toBeNull();
        expect(safeRedirectPath(null)).toBeNull();
        expect(safeRedirectPath(123)).toBeNull();
        expect(safeRedirectPath(['/orders/1'])).toBeNull();
    });
});
