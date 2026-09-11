import { describe, expect, it } from 'vitest';
import {
    ADMIN_ROLES,
    ROLE_ADMIN,
    ROLE_CUSTOMER,
    ROLE_DRIVER,
    ROLE_LABELS,
    ROLE_OPERATOR,
    ROLE_SUPER_ADMIN,
    roleLabel,
    signupRoleFromQuery,
} from './roles';

describe('data/roles — 역할 상수·라벨 단일 소스 (백엔드 User::ROLE_* · roleLabels()와 동일)', () => {
    it('관리자 접근 역할 묶음은 Admin/Super Admin만 포함한다', () => {
        expect(ADMIN_ROLES).toEqual([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
    });

    it('모든 역할에 한글 라벨이 존재한다', () => {
        for (const role of [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_OPERATOR, ROLE_DRIVER, ROLE_CUSTOMER]) {
            expect(typeof ROLE_LABELS[role]).toBe('string');
        }
    });

    it('roleLabel은 라벨을 반환하고, 모르는 값은 원문 그대로 돌려준다', () => {
        expect(roleLabel(ROLE_DRIVER)).toBe('기사');
        expect(roleLabel(ROLE_CUSTOMER)).toBe('등록자');
        expect(roleLabel('Unknown Role')).toBe('Unknown Role');
    });

    it('역할값은 백엔드 문자열과 동일한 형식이다', () => {
        expect(ROLE_SUPER_ADMIN).toBe('Super Admin');
        expect(ROLE_DRIVER).toBe('Driver');
        expect(ROLE_CUSTOMER).toBe('Customer');
    });
});

describe('signupRoleFromQuery — 가입 화면 초기 역할', () => {
    it('등록자 링크(?role=Customer)로 들어오면 등록자를 선택한다', () => {
        expect(signupRoleFromQuery(ROLE_CUSTOMER)).toBe(ROLE_CUSTOMER);
    });

    it('역할이 없거나 알 수 없는 값이면 기사로 본다', () => {
        expect(signupRoleFromQuery(undefined)).toBe(ROLE_DRIVER);
        expect(signupRoleFromQuery('')).toBe(ROLE_DRIVER);
        expect(signupRoleFromQuery('Admin')).toBe(ROLE_DRIVER);
        expect(signupRoleFromQuery(['Customer'])).toBe(ROLE_DRIVER); // 배열 쿼리 방어
    });
});
