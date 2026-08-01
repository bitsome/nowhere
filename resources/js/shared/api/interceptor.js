import { normalizeApiError } from './error.js';

function hasContentTypeHeader(headers = {}) {
    return Object.keys(headers).some((headerName) => headerName.toLowerCase() === 'content-type');
}

function usesRequestBody(method = 'get') {
    return ['patch', 'post', 'put'].includes(String(method).toLowerCase());
}

function isFormPayload(data) {
    return data instanceof FormData || data instanceof URLSearchParams;
}

export function applyRequestDefaults(config = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(config?.headers || {}),
    };

    if (usesRequestBody(config?.method) && !hasContentTypeHeader(headers) && !isFormPayload(config?.data)) {
        headers['Content-Type'] = 'application/json';
    }

    return {
        ...config,
        headers,
    };
}

export function registerApiInterceptors(client, options = {}) {
    const {
        onRequest,
        onRequestError,
        onResponse,
        onResponseError,
    } = options;

    client.interceptors.request.use(
        (config) => {
            const nextConfig = applyRequestDefaults(config);

            if (typeof onRequest === 'function') {
                return onRequest(nextConfig) || nextConfig;
            }

            return nextConfig;
        },
        (error) => {
            if (typeof onRequestError === 'function') {
                return Promise.reject(onRequestError(error) || error);
            }

            return Promise.reject(error);
        },
    );

    client.interceptors.response.use(
        (response) => {
            if (typeof onResponse === 'function') {
                return onResponse(response) || response;
            }

            return response;
        },
        (error) => {
            const normalizedError = normalizeApiError(error);

            if (typeof onResponseError === 'function') {
                return Promise.reject(onResponseError(normalizedError, error) || normalizedError);
            }

            return Promise.reject(normalizedError);
        },
    );

    return client;
}
