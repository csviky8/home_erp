import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api, { apiError } from '../services/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('home_erp_token'));
    const loading = ref(false);
    const error = ref('');
    const isAuthenticated = computed(() => Boolean(token.value && user.value));
    const isAdmin = computed(() => user.value?.roles?.some((role) => ['super-admin', 'admin'].includes(role)));
    const can = (permission) => user.value?.permissions?.includes(permission) || user.value?.roles?.includes('super-admin');

    const applySession = (payload) => {
        token.value = payload.token;
        user.value = payload.user;
        localStorage.setItem('home_erp_token', payload.token);
    };
    const bootstrap = async () => {
        if (!token.value) return;
        try { user.value = (await api.get('/auth/me')).data.user; } catch { token.value = null; user.value = null; localStorage.removeItem('home_erp_token'); }
    };
    const login = async (credentials) => {
        loading.value = true; error.value = '';
        try { applySession((await api.post('/auth/login', credentials)).data); }
        catch (e) { error.value = apiError(e); throw e; }
        finally { loading.value = false; }
    };
    const logout = async () => { try { await api.post('/auth/logout'); } finally { token.value = null; user.value = null; localStorage.removeItem('home_erp_token'); } };
    const updateUser = (payload) => { user.value = { ...user.value, ...payload }; };

    return { user, token, loading, error, isAuthenticated, isAdmin, can, bootstrap, login, logout, updateUser };
});
