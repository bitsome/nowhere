function normalizeValidationErrors(errors) {
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
        return {};
    }

    return errors;
}

function extractValidationMessages(errors) {
    return Object.values(errors).flat().filter(Boolean);
}

export function normalizeApiError(error) {
    const response = error?.response || null;
    const data = response?.data || null;
    const errors = normalizeValidationErrors(data?.errors);
    const validationMessages = extractValidationMessages(errors);
    const status = response?.status || 0;
    const statusText = response?.statusText || '';
    const message = validationMessages[0] || data?.message || error?.message || '요청 처리 중 오류가 발생했습니다.';

    return {
        code: error?.code || '',
        data,
        errors,
        firstError: validationMessages[0] || message,
        isCanceled: error?.code === 'ERR_CANCELED',
        isClientError: status >= 400 && status < 500,
        isForbidden: response?.status === 403,
        isNetworkError: !response,
        isNotFound: response?.status === 404,
        isServerError: status >= 500,
        isTimeout: error?.code === 'ECONNABORTED',
        isUnauthorized: response?.status === 401,
        isValidationError: response?.status === 422,
        message,
        raw: error,
        status,
        statusText,
        validationMessages,
    };
}

export function getApiErrorMessage(error, fallbackMessage = '요청 처리 중 오류가 발생했습니다.') {
    return normalizeApiError(error).message || fallbackMessage;
}
