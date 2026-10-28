<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { api } from '../api'
import { auth } from '../stores/auth'

const router = useRouter()
const tenant = ref(null)
const warning = ref(false)
const loadError = ref('')
const isLoading = ref(true)

const navigation = [
  { label: 'Overview', name: 'dashboard', icon: '⌂', group: 'STORE' },
  { label: 'Products', name: 'products', icon: '▦' },
  { label: 'Categories', name: 'categories', icon: '◇' },
  { label: 'Orders', name: 'orders', icon: '▤' },
  { label: 'Customers', name: 'customers', icon: '♙' },
  { label: 'General', name: 'settings', icon: '⚙', group: 'CONFIGURATION' },
  { label: 'Payments', name: 'payments', icon: '◈' },
  { label: 'Verification', name: 'verification', icon: '✓' },
  { label: 'Appearance', name: 'appearance', icon: '◐' },
  { label: 'Audit log', name: 'audit', icon: '≡' },
]

const initials = computed(() => tenant.value?.name?.slice(0, 1).toUpperCase() || 'S')

async function loadContext() {
  try {
    const [context, settings] = await Promise.all([
      api.get('/admin/tenant', { authenticated: false }),
      api.get('/admin/settings'),
    ])
    if (context.host_type !== 'admin') {
      auth.clear()
      loadError.value = 'This application is available only on the tenant Admin domain.'
      return
    }
    tenant.value = context
    warning.value = Boolean(settings.driver_warning)
    loadError.value = ''
  } catch (error) {
    loadError.value = error.message
  } finally {
    isLoading.value = false
  }
}

async function logout() {
  try {
    await auth.logout()
  } catch (error) {
    loadError.value = error.message
  } finally {
    await router.replace({ name: 'login' })
  }
}

onMounted(loadContext)
</script>

<template>
  <div class="admin-shell">
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark">V</span><span>Violet commerce</span></div>
      <div class="tenant-chip">
        <span class="tenant-avatar">{{ initials }}</span>
        <span class="tenant-chip-copy">
          <span class="tenant-name">{{ tenant?.name || 'Your store' }}</span>
          <span class="tenant-label">Store administration</span>
        </span>
      </div>
      <nav class="nav-list" aria-label="Admin navigation">
        <div v-for="item in navigation" :key="item.name">
          <p v-if="item.group" class="nav-caption">{{ item.group }}</p>
          <RouterLink :to="{ name: item.name }" class="nav-link">
            <span class="nav-icon" aria-hidden="true">{{ item.icon }}</span>
            {{ item.label }}
          </RouterLink>
        </div>
      </nav>
      <div class="sidebar-bottom">
        <div class="admin-email">{{ auth.user?.email }}</div>
        <button class="text-button" type="button" @click="logout">Sign out</button>
      </div>
    </aside>

    <div class="main-area">
      <header class="topbar">
        <span class="topbar-meta">{{ tenant?.status }} · {{ tenant?.default_currency }} · {{ tenant?.default_locale }}</span>
      </header>
      <main class="page-wrap">
        <div v-if="warning" class="driver-banner" role="status">
          <span aria-hidden="true">⚠</span>
          <span><strong>Development provider configuration</strong>
            This tenant uses a demo payment/verification driver or log delivery provider.
            Change provider settings through the approved CLI workflow; these controls are intentionally read-only here.
          </span>
        </div>
        <div v-if="loadError" class="error-box" role="alert">{{ loadError }}</div>
        <div v-else-if="isLoading" class="loading" role="status">Loading store administration…</div>
        <RouterView v-else />
      </main>
    </div>
  </div>
</template>
