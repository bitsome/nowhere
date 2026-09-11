import { afterEach, describe, expect, it } from 'vitest';
import { authGuard } from './index';

/**
 * 비로그인 상태로 로그인 필요 딥링크(/orders/12 등)를 열면 목적지를 기억해
 * 로그인 후 그 화면으로 돌려보내야 한다. 기억하지 않으면 위챗방에서 받은
 * 운행 링크로 들어온 기사가 로그인 직후 홈으로 떨어져 그 운행을 잃는다.
 */
const route = (name, path, extraMeta = {}) => ({
    name,
    fullPath: path,
    meta: extraMeta,
});

afterEach(() => {
    localStorage.clear();
});

describe('authGuard — 비로그인 딥링크 복귀', () => {
    it('비로그인 딥링크는 목적지를 redirect로 실어 로그인으로 보낸다', () => {
        expect(authGuard(route('order-detail', '/orders/12', { requiresAuth: true }))).toEqual({
            name: 'login',
            query: { redirect: '/orders/12' },
        });
    });

    it('쿼리가 붙은 딥링크도 그대로 기억한다', () => {
        expect(authGuard(route('chat', '/chat?room=3', { requiresAuth: true }))).toEqual({
            name: 'login',
            query: { redirect: '/chat?room=3' },
        });
    });

    it('홈은 홍보 유입을 위해 랜딩으로 보낸다 (기존 동작 유지)', () => {
        expect(authGuard(route('home', '/', { requiresAuth: true }))).toEqual({ name: 'welcome' });
    });

    it('로그인 상태면 딥링크를 그대로 통과시킨다', () => {
        localStorage.setItem('auth_token', 'token');

        expect(authGuard(route('order-detail', '/orders/12', { requiresAuth: true }))).toBeUndefined();
    });

    it('로그인 상태에서 로그인 화면을 열면 마켓으로 보낸다', () => {
        localStorage.setItem('auth_token', 'token');

        expect(authGuard(route('login', '/login'))).toEqual({ name: 'market' });
    });

    it('관리자 전용 화면은 역할이 맞지 않으면 마켓으로 보낸다', () => {
        localStorage.setItem('auth_token', 'token');
        localStorage.setItem('auth_user_role', 'Driver');

        expect(authGuard(route('admin', '/admin', { requiresAuth: true, adminOnly: true }))).toEqual({
            name: 'market',
        });
    });
});
