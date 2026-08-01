# API

## API 설계 원칙
- 공통 HTTP 통신은 `resources/js/shared/api/` 계층을 통해 처리한다.
- 화면이나 기능 모듈에서 직접 `axios.create()`를 반복하지 않는다.
- 요청은 공통 `request` 계층을 기준으로 통일한다.
- 응답은 `normalizeApiResponse()` 또는 `requestData()` 기준으로 사용한다.
- 에러는 `normalizeApiError()` 기준으로 처리한다.

## 인증
- 현재 프로젝트는 세션 기반 인증 흐름을 우선 사용한다.
- 추후 `Laravel Sanctum` 확장 시 `ensureCsrfCookie()`를 공통 진입점으로 사용한다.
- 인증 토큰 방식이 필요할 경우 `setApiAuthorization()`과 `clearApiAuthorization()`으로 공통 관리한다.

## Response Format
- 기본 응답 객체 형식

```js
{
    data,
    headers,
    meta,
    ok,
    pagination,
    raw,
    status,
    statusText,
}
```

- `request()`는 위 정규화 객체를 반환한다.
- `requestData()`는 `data`만 바로 반환한다.
- Laravel Pagination 응답은 `pagination`에 공통 추출한다.

## Error Format
- 공통 에러 객체 형식

```js
{
    code,
    data,
    errors,
    firstError,
    isCanceled,
    isClientError,
    isForbidden,
    isNetworkError,
    isNotFound,
    isServerError,
    isTimeout,
    isUnauthorized,
    isValidationError,
    message,
    raw,
    status,
    statusText,
    validationMessages,
}
```

- 검증 실패는 `errors`와 `validationMessages` 기준으로 처리한다.
- 화면 표시용 기본 메시지는 `message` 또는 `firstError`를 우선 사용한다.

## Version

## Route 규칙

## Validation

## Resource

## Pagination

## API 목록

## 예외 처리

## 현황
- 현재 프로젝트는 웹 라우트 중심이며 별도 API 엔드포인트는 아직 없다.
- 공통 프런트엔드 API 계층은 `resources/js/shared/api/`에 생성되어 있다.
- 현재 구성 요소
  - `axios.js`
  - `request.js`
  - `response.js`
  - `interceptor.js`
  - `error.js`
  - `index.js`

## 예정 API

### 인증
- `POST /login`
  - 설명: 사용자 로그인
  - 요청값: `email`, `password`, `remember`

- `POST /logout`
  - 설명: 사용자 로그아웃

## 응답 정책
- API 도입 시 JSON 응답 포맷을 통일한다.
- 인증 실패, 검증 실패, 권한 실패 응답 규칙을 정의한다.

## shared/api 사용 규칙
- 일반 호출:

```js
import { get, post, requestData } from '@/shared/api/index.js';
```

- 정규화 응답이 필요할 때는 `get`, `post`, `put`, `patch`, `destroy`, `request`를 사용한다.
- 데이터만 바로 필요할 때는 `getData`, `postData`, `putData`, `patchData`, `destroyData`, `requestData`를 사용한다.
- 인터셉터 확장이 필요할 때는 `createApiClient()`와 `registerApiInterceptors()`를 사용한다.

## TODO
- 버전 정책 정의 (`/api/v1`)
- 인증 방식 결정 (세션 또는 토큰)
- 에러 응답 스키마 정의
