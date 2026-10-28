<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterView } from 'vue-router'
import { api } from './api'
import { router } from './router'
import { auth } from './stores/auth'

const tenant = ref(null)
const appError = ref('')
const isLoading = ref(true)
let statusTimer
let previousStatus = null

async function loadTenant() {
  try {
    const response = await api.get('/admin/tenant', { authenticated: false })
    tenant.value = response
    if (response.status === 'suspended') auth.clear()
    if (response.host_type !== 'admin') auth.clear()
    if (previousStatus === 'suspended' && response.status === 'active') {
      auth.clear()
      await router.replace({ name: 'login' })
    }
    previousStatus = response.status
    appError.value = response.host_type === 'admin'
      ? ''
      : 'This application is available only on the tenant Admin domain.'
  } catch (error) {
    tenant.value = null
    appError.value = error.message
  }
}

onMounted(async () => {
  await loadTenant()

  if (tenant.value?.host_type === 'admin' && tenant.value.status === 'active') {
    try {
      await auth.restore()
    } catch (error) {
      appError.value = error.message
    }
  }

  statusTimer = window.setInterval(loadTenant, 30_000)
  isLoading.value = false
})

onUnmounted(() => window.clearInterval(statusTimer))
</script>

<template>
  <main v-if="isLoading" class="startup-screen" aria-live="polite">
    <span class="startup-mark">V</span>
    <p>Connecting to your store…</p>
  </main>

  <main v-else-if="appError" class="startup-screen">
    <span class="startup-mark startup-mark--error">!</span>
    <h1>Admin unavailable</h1>
    <p>{{ appError }}</p>
    <button class="button button--secondary" type="button" @click="loadTenant">Try again</button>
  </main>

  <main v-else-if="tenant?.status === 'suspended'" class="startup-screen suspended-screen">
    <span class="startup-mark startup-mark--error">!</span>
    <p class="eyebrow">Store access restricted</p>
    <h1>This store is temporarily unavailable</h1>
    <p>Admin access is paused while this tenant is suspended. This page checks status automatically.</p>
    <button class="button button--secondary" type="button" @click="loadTenant">Check status now</button>
  </main>

  <RouterView v-else />
</template>
