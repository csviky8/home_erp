import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api, { apiError } from '../services/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('home_erp_token'));
    const loading = ref(false);
    const error = ref('');
    const isAuthenticated = computed(() => Boolean(token.value && user.value));
    const isSuperAdmin = computed(() => Boolean(user.value?.roles?.includes('super-admin')));
    const isAdmin = computed(() => isSuperAdmin.value || Boolean(user.value?.roles?.includes('admin')));
    // Permission driven, mirroring App\Support\AccessMap on the server. super-admin is a
    // full bypass, exactly like the backend's `hasRole('super-admin') ||` guards.
    const can = (permission) => isSuperAdmin.value || Boolean(user.value?.permissions?.includes(permission));
    const canAny = (...permissions) => permissions.flat().some(can);
    // Area helpers resolve through the ability map the API sends with the user, so the UI
    // shows exactly what the backend will allow for the signed-in role.
    const canArea = (area, ability = 'view') => {
        if (user.value?.abilities?.[area]?.[ability] !== undefined) return user.value.abilities[area][ability];
        const permission = user.value?.areas?.[area]?.permissions?.[ability];
        return permission ? can(permission) : false;
    };

    // Super admins work across families. The server sends the saved preference (falling back to
    // the first family), so a new session always lands on the family chosen in Settings.
    const households = ref([]);
    const activeHouseholdId = ref(user.value?.active_household_id || Number(localStorage.getItem('home_erp_household')) || null);
    const activeHousehold = computed(() => {
        if (!isSuperAdmin.value) return user.value?.household ?? null;
        return households.value.find((item) => item.id === activeHouseholdId.value) || user.value?.household || null;
    });
    const setActiveHousehold = (id) => {
        activeHouseholdId.value = id ? Number(id) : null;
        if (activeHouseholdId.value) localStorage.setItem('home_erp_household', String(activeHouseholdId.value));
        else localStorage.removeItem('home_erp_household');
    };
    // Persist to the account so the choice survives a new session and a different browser.
    const saveActiveHousehold = async (id) => {
        setActiveHousehold(id);
        if (!isSuperAdmin.value) return;
        const response = await api.put('/auth/working-family', { default_household_id: activeHouseholdId.value });
        if (response.data?.user) user.value = { ...user.value, ...response.data.user };
    };
    const loadHouseholds = async () => {
        if (!isSuperAdmin.value) return;
        try {
            // The endpoint wraps its list in { data: [...] }, so unwrap it or the select renders nothing.
            const response = await api.get('/households');
            households.value = Array.isArray(response.data?.data) ? response.data.data : [];
            if (!activeHouseholdId.value) setActiveHousehold(user.value?.active_household_id || households.value[0]?.id);
        } catch { households.value = []; }
    };

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

    return { user, token, loading, error, isAuthenticated, isAdmin, isSuperAdmin, can, canAny, canArea, households, activeHouseholdId, activeHousehold, setActiveHousehold, saveActiveHousehold, loadHouseholds, bootstrap, login, logout, updateUser };
});
