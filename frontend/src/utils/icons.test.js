import { describe, expect, it } from 'vitest';
import { ICONS, iconOf } from './icons';

describe('icons - BaseIcon 이름 매핑', () => {
    it('운행 카드 공유 버튼이 쓰는 share 아이콘이 등록되어 있다', () => {
        expect(ICONS.share).toBeTruthy();
    });

    it('share 는 폴백(help)이 아니라 전용 아이콘을 반환한다', () => {
        expect(iconOf('share')).not.toBe(ICONS.help);
    });

    it('미정의 이름은 정보 아이콘으로 폴백한다', () => {
        expect(iconOf('이런-아이콘-없음')).toBe(ICONS.help);
    });
});
