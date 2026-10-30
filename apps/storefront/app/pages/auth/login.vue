<script setup lang="ts">
interface Customer {
  id: string
  email: string
  first_name: string | null
  last_name: string | null
  email_verified: boolean
}

const api = useStorefrontApi()
const route = useRoute()
const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')
const notice = ref('')
const { cart, refresh } = useCart()

async function signIn() {
  busy.value = true
  error.value = ''
  try {
    if (!cart.value) await refresh()
    const redirect = typeof route.query.redirect === 'string'
      && route.query.redirect.startsWith('/')
      && !route.query.redirect.startsWith('//')
      && !route.query.redirect.includes('\\')
      ? route.query.redirect
      : '/account'
    const result = await api.send<{ user: Customer }>('auth/login', 'POST', {
      email: email.value.trim(),
      password: password.value
    })
    useState<Customer | null>('storefront-customer').value = result.user
    await navigateTo(redirect)
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

useSeoMeta({ title: 'Welcome back', description: 'Sign in to your store account.' })

onMounted(() => {
  if (route.query.registered === '1') notice.value = 'Your account is ready. Sign in to continue.'
})
</script>

<template>
  <section class="auth-page">
    <div class="auth-art">
      <div class="auth-art__shape auth-art__shape--one" />
      <div class="auth-art__shape auth-art__shape--two" />
      <span class="auth-art__caption">A place for your favorites.</span>
    </div>
    <div class="auth-panel">
      <NuxtLink class="back-link" to="/">← Back to the store</NuxtLink>
      <span class="eyebrow">Your account</span>
      <h1>Welcome back.</h1>
      <p class="auth-copy">Sign in to see your orders and keep your details close.</p>
      <p v-if="notice" class="success-box" role="status">{{ notice }}</p>
      <form class="auth-form" @submit.prevent="signIn">
        <label class="form-field">
          <span>Email address</span>
          <input v-model="email" type="email" autocomplete="email" required placeholder="you@example.com">
        </label>
        <label class="form-field">
          <span>Password</span>
          <input v-model="password" type="password" autocomplete="current-password" required placeholder="Your password">
        </label>
        <p v-if="error" class="error-box" role="alert">{{ error }}</p>
        <button class="button button--dark button--wide" type="submit" :disabled="busy">
          {{ busy ? 'Signing in…' : 'Sign in' }} <span v-if="!busy">→</span>
        </button>
      </form>
      <p class="auth-footnote">New to the store? <NuxtLink to="/auth/register">Create an account</NuxtLink></p>
    </div>
  </section>
</template>
