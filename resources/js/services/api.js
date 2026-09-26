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

export const apiError = (error) => error.response?.data?.errors || error.response?.data?.message || 'Something went wrong. Please try again.';
export default api;
