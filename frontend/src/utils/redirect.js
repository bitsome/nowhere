/**
 * 로그인·가입 후 돌아갈 경로를 안전하게 해석한다.
 *
 * redirect 는 공유 링크의 쿼리로 외부에 노출되고 누구나 바꿀 수 있다.
 * 그대로 신뢰하면 외부 주소로 튕기는 open redirect 가 되므로 내부 경로만 허용한다.
 *
 * @param {unknown} value 쿼리의 redirect 값
 * @returns {string|null} 사용할 수 있는 내부 경로 (없으면 null)
 */
export const safeRedirectPath = (value) => {
    const path = typeof value === 'string' ? value.trim() : '';

    if (path === '') {
        return null;
    }

    // 내부 경로만 — '/'로 시작해야 하고, '//host' 같은 프로토콜 상대 주소는 막는다
    if (!path.startsWith('/') || path.startsWith('//')) {
        return null;
    }

    // 브라우저가 역슬래시를 '/'로 해석하는 경우를 이용한 우회('/\evil.com')도 막는다
    if (path.includes('\\')) {
        return null;
    }

    return path;
};
