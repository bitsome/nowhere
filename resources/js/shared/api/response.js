function extractApiMeta(data) {
    if (data?.meta && typeof data.meta === 'object') {
        return data.meta;
    }

    return {};
}

function extractApiPagination(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    const meta = extractApiMeta(data);
    const currentPage = data?.current_page ?? meta?.current_page ?? null;
    const lastPage = data?.last_page ?? meta?.last_page ?? null;
    const perPage = data?.per_page ?? meta?.per_page ?? null;
    const total = data?.total ?? meta?.total ?? null;

    if (
        currentPage === null
        && lastPage === null
        && perPage === null
        && total === null
    ) {
        return null;
    }

    return {
        currentPage,
        from: data?.from ?? meta?.from ?? null,
        lastPage,
        links: data?.links ?? meta?.links ?? [],
        path: data?.path ?? meta?.path ?? '',
        perPage,
        to: data?.to ?? meta?.to ?? null,
        total,
    };
}

export function normalizeApiResponse(response) {
    const data = response?.data || null;

    return {
        data,
        headers: response?.headers || {},
        meta: extractApiMeta(data),
        ok: Boolean(response?.status >= 200 && response?.status < 300),
        pagination: extractApiPagination(data),
        raw: response,
        status: response?.status || 0,
        statusText: response?.statusText || '',
    };
}

export function unwrapApiResponse(response) {
    return normalizeApiResponse(response).data;
}

export function unwrapApiMeta(response) {
    return normalizeApiResponse(response).meta;
}
