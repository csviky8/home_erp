import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppShell from './components/AppShell.vue';
const LoginPage = () => import('./views/LoginPage.vue');
const DashboardPage = () => import('./views/DashboardPage.vue');
const ResourcePage = () => import('./views/ResourcePage.vue');
const ReportsPage = () => import('./views/ReportsPage.vue');
const SettingsPage = () => import('./views/SettingsPage.vue');
const ProfilePage = () => import('./views/ProfilePage.vue');
const NotificationsPage = () => import('./views/NotificationsPage.vue');
const SearchPage = () => import('./views/SearchPage.vue');

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        { path: '/login', name: 'login', component: LoginPage, meta: { public: true } },
        { path: '/', component: AppShell, children: [
            { path: '', component: DashboardPage },
            { path: 'search', component: SearchPage },
            { path: 'modules/:module', component: ResourcePage, props: (route) => ({ moduleKey: route.params.module }) },
            { path: 'modules/:module/:record', component: ResourcePage, props: (route) => ({ moduleKey: route.params.module }) },
            { path: 'reports', component: ReportsPage },
            { path: 'settings', component: SettingsPage },
            { path: 'profile', component: ProfilePage },
            { path: 'notifications', component: NotificationsPage },
        ] },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});
router.beforeEach(async (to) => { const auth = useAuthStore(); if (auth.token && !auth.user) await auth.bootstrap(); if (!to.meta.public && !auth.token) return { name: 'login' }; if (to.meta.public && auth.token) return '/'; return true; });
export default router;
