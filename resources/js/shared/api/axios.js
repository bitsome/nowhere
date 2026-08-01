import axios from 'axios';

import { registerApiInterceptors } from './interceptor.js';

const DEFAULT_API_BASE_URL = '/';
const DEFAULT_API_TIMEOUT = 10000;
const DEFAULT_API_HEADERS = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function resolveApiBaseUrl() {
    return import.meta.env.VITE_API_BASE_URL || DEFAULT_API_BASE_URL;
}

function resolveApiTimeout() {
    return Number(import.meta.env.VITE_API_TIMEOUT || DEFAULT_API_TIMEOUT);
}

export function createApiClient(config = {}, interceptorOptions = {}) {
    const { headers = {}, ...restConfig } = config;
    const client = axios.create({
        baseURL: resolveApiBaseUrl(),
        headers: {
            ...DEFAULT_API_HEADERS,
            ...headers,
        },
        timeout: resolveApiTimeout(),
        withCredentials: true,
        withXSRFToken: true,
        xsrfCookieName: 'XSRF-TOKEN',
        xsrfHeaderName: 'X-XSRF-TOKEN',
        ...restConfig,
    });

    registerApiInterceptors(client, interceptorOptions);

    return client;
}

export function setApiAuthorization(token, type = 'Bearer') {
    if (!token) {
        clearApiAuthorization();

        return;
    }

    apiClient.defaults.headers.common.Authorization = `${type} ${token}`;
}

export function clearApiAuthorization() {
    delete apiClient.defaults.headers.common.Authorization;
}

const apiClient = createApiClient();

export { apiClient, DEFAULT_API_HEADERS, DEFAULT_API_TIMEOUT };
