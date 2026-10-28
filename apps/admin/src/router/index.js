import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import AuthPage from '../views/auth/AuthPage.vue'
import AdminWorkspace from '../views/AdminWorkspace.vue'
import { auth } from '../stores/auth'

const sections = [
  ['dashboard', 'Overview'],
  ['products', 'Products'],
  ['categories', 'Categories'],
  ['orders', 'Orders'],
  ['customers', 'Customers'],
  ['settings', 'General settings'],
  ['payments', 'Payments'],
  ['verification', 'Verification'],
  ['appearance', 'Appearance'],
  ['audit', 'Audit log'],
]

const children = sections.map(([section]) => ({
  path: section === 'dashboard' ? '' : section,
  name: section,
  component: AdminWorkspace,
  meta: { requiresAuth: true, section },
}))

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: AuthPage, meta: { guestOnly: true, mode: 'login' } },
    { path: '/forgot-password', name: 'forgot-password', component: AuthPage, meta: { guestOnly: true, mode: 'forgot' } },
    { path: '/reset-password', name: 'reset-password', component: AuthPage, meta: { guestOnly: true, mode: 'reset' } },
    { path: '/', component: AdminLayout, children },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login' }
  if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'dashboard' }
})
