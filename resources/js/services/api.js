import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('home_erp_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && !error.config?.url?.includes('/auth/login')) {
            localStorage.removeItem('home_erp_token');
            if (window.location.pathname !== '/login') window.location.assign('/login');
        }
        return Promise.reject(error);
    },
);

/** Per-field validation messages as { fieldName: 'message' }, empty when it is not a 422. */
export const apiErrors = (error) => {
    const errors = error.response?.data?.errors;
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) return {};
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : String(messages)]));
};

/** A single human readable line describing what went wrong. */
export const apiMessage = (error) => {
    const data = error.response?.data;
    if (data?.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)) {
        const messages = Object.values(data.errors).flat();
        if (messages.length) return messages.join(' · ');
    }
    return data?.message || 'Something went wrong. Please try again.';
};

export const apiError = (error) => apiMessage(error);
export default api;
