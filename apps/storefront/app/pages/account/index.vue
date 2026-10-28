<script setup lang="ts">
interface Account {
  user: { id: string; email: string; first_name: string | null; last_name: string | null; phone?: string | null; email_verified: boolean }
  addresses: unknown[]
}

const api = useStorefrontApi()
const { data, error, refresh } = await useAsyncData('storefront-account', () =>
  api.get<Account>('account')
)
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  if (status === 401) await navigateTo('/auth/login?redirect=%2Faccount')
  else throw createError({ statusCode: 503, statusMessage: 'Your account is temporarily unavailable.' })
}

const customerState = useState<{ id: string; email: string; first_name: string | null } | null>('storefront-customer', () => null)
if (data.value) customerState.value = data.value.user
const busy = ref(false)
const notice = ref('')

async function signOut() {
  busy.value = true
  try {
    await api.send('auth/logout', 'POST')
    customerState.value = null
    await navigateTo('/')
  } catch (failure) {
    notice.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

useSeoMeta({ title: 'My account', description: 'Manage your store account and orders.' })
</script>

<template>
  <section class="page-section account-page">
    <div class="page-intro">
      <span class="eyebrow">Your corner of the store</span>
      <h1>Hello{{ data?.user.first_name ? `, ${data.user.first_name}` : '' }}.</h1>
      <p>Everything you need, all in one place.</p>
    </div>
    <div v-if="data" class="account-grid">
      <NuxtLink class="account-card account-card--orders" to="/account/orders">
        <span class="account-card__icon">↗</span>
        <span class="eyebrow">Keep track</span>
        <h2>Your orders</h2>
        <p>See what's on its way and revisit past purchases.</p>
        <span class="account-card__link">View order history →</span>
      </NuxtLink>
      <div class="account-card">
        <span class="account-card__icon">◎</span>
        <span class="eyebrow">Your details</span>
        <h2>{{ data.user.first_name || 'Customer' }} {{ data.user.last_name || '' }}</h2>
        <p>{{ data.user.email }}</p>
        <p class="muted-copy">{{ data.user.email_verified ? 'Email verified' : 'Email not verified' }}</p>
        <NuxtLink class="account-card__link" to="/account/settings">Edit profile →</NuxtLink>
      </div>
      <div class="account-card">
        <span class="account-card__icon">⌂</span>
        <span class="eyebrow">The details that matter</span>
        <h2>Addresses</h2>
        <p>{{ data.addresses.length }} saved {{ data.addresses.length === 1 ? 'address' : 'addresses' }}</p>
        <NuxtLink class="account-card__link" to="/account/addresses">Manage addresses →</NuxtLink>
      </div>
    </div>
    <div v-if="notice" class="error-box" role="alert">{{ notice }}</div>
    <div class="account-actions">
      <button class="text-action" type="button" :disabled="busy" @click="signOut">{{ busy ? 'Signing out…' : 'Sign out' }}</button>
      <button class="text-action" type="button" @click="refresh()">Refresh account</button>
    </div>
  </section>
</template>
