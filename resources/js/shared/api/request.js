import { apiClient } from './axios.js';
import { normalizeApiResponse, unwrapApiResponse } from './response.js';

export async function request(config = {}) {
    const response = await apiClient.request(config);

    return normalizeApiResponse(response);
}

export async function requestData(config = {}) {
    const response = await apiClient.request(config);

    return unwrapApiResponse(response);
}

export async function ensureCsrfCookie(config = {}) {
    const response = await apiClient.get('/sanctum/csrf-cookie', config);

    return normalizeApiResponse(response);
}

export function get(url, config = {}) {
    return request({
        ...config,
        method: 'get',
        url,
    });
}

export function post(url, data = {}, config = {}) {
    return request({
        ...config,
        data,
        method: 'post',
        url,
    });
}

export function put(url, data = {}, config = {}) {
    return request({
        ...config,
        data,
        method: 'put',
        url,
    });
}

export function patch(url, data = {}, config = {}) {
    return request({
        ...config,
        data,
        method: 'patch',
        url,
    });
}

export function destroy(url, config = {}) {
    return request({
        ...config,
        method: 'delete',
        url,
    });
}

export function getData(url, config = {}) {
    return requestData({
        ...config,
        method: 'get',
        url,
    });
}

export function postData(url, data = {}, config = {}) {
    return requestData({
        ...config,
        data,
        method: 'post',
        url,
    });
}

export function putData(url, data = {}, config = {}) {
    return requestData({
        ...config,
        data,
        method: 'put',
        url,
    });
}

export function patchData(url, data = {}, config = {}) {
    return requestData({
        ...config,
        data,
        method: 'patch',
        url,
    });
}

export function destroyData(url, config = {}) {
    return requestData({
        ...config,
        method: 'delete',
        url,
    });
}
