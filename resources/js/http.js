import axios from 'axios';

/**
 * The single HTTP client (Frontend 2.2, ADR-066).
 *
 * jQuery $.ajax is deliberately not used. Two HTTP stacks means two
 * interceptor chains for X-Company-Id, X-Request-Id, CSRF and
 * Idempotency-Key, and one of them will eventually be missed. A write that
 * skips the idempotency header duplicates inventory or breakdown records
 * under retry, which is exactly what ADR-024 exists to prevent.
 */
const http = axios.create({
    baseURL: '/api/v1',
    timeout: 30000,
    headers: { Accept: 'application/json' },
});

http.interceptors.request.use((config) => {
    const app = window.App ?? {};

    config.headers['X-Request-Id'] = crypto.randomUUID();
    config.headers['Accept-Language'] = app.locale ?? 'en';

    if (app.companyId) {
        config.headers['X-Company-Id'] = app.companyId;
    }

    if (app.csrf) {
        config.headers['X-CSRF-TOKEN'] = app.csrf;
    }

    // Set per request by callers that need it; never generated here, because
    // a retry must reuse the key from the original attempt (API 32).
    if (config.idempotencyKey) {
        config.headers['Idempotency-Key'] = config.idempotencyKey;
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const code = error.response?.data?.code;

        // The session expired underneath the page. Reloading lands on login
        // rather than leaving the user clicking a dead screen.
        if (error.response?.status === 401 && code === 'UNAUTHENTICATED') {
            window.location.reload();
        }

        return Promise.reject(error);
    },
);

export default http;
