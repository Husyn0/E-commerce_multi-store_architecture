<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../../api'
import { auth } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const mode = computed(() => route.meta.mode)
const busy = ref(false)
const error = ref('')
const notice = ref(route.query.reset === '1' ? 'Your password was updated. Sign in with the new password.' : '')
const showPassword = ref(false)
const form = reactive({
  email: '',
  password: '',
  code: '',
  newPassword: '',
  confirmPassword: '',
})

const heading = computed(() => ({
  login: 'Welcome back',
  forgot: 'Reset your password',
  reset: 'Choose a new password',
}[mode.value]))

watch(mode, () => {
  form.password = ''
  form.code = ''
  form.newPassword = ''
  form.confirmPassword = ''
  error.value = ''
  notice.value = ''
})

watch(() => route.query.reset, (value) => {
  if (value === '1') notice.value = 'Your password was updated. Sign in with the new password.'
})

async function submit() {
  busy.value = true
  error.value = ''
  notice.value = ''

  try {
    if (mode.value === 'login') {
      await auth.login(form.email, form.password)
      form.password = ''
      await router.replace({ name: 'dashboard' })
    } else if (mode.value === 'forgot') {
      const result = await api.post('/admin/auth/password-reset/request', {
        email: form.email,
      }, { authenticated: false })
      notice.value = result.message
      await router.replace({ name: 'reset-password', query: { email: form.email, requested: '1' } })
    } else {
      await api.post('/admin/auth/password-reset', {
        email: form.email,
        code: form.code,
        password: form.newPassword,
        password_confirmation: form.confirmPassword,
      }, { authenticated: false })
      await router.replace({ name: 'login', query: { reset: '1' } })
    }
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

if (typeof route.query.email === 'string') form.email = route.query.email
</script>

<template>
  <main class="auth-shell">
    <aside class="auth-visual" aria-hidden="true">
      <div class="auth-visual-top">
        <div class="auth-brand"><span class="auth-brand-mark">V</span><span>Violet commerce</span></div>
        <span class="auth-visual-caption">TENANT ADMIN</span>
      </div>
      <div class="auth-visual-copy">
        <p class="auth-kicker"><span class="auth-kicker-dot" /> YOUR STORE, IN GOOD HANDS</p>
        <h2>Make room for<br />the <em>next</em> big thing.</h2>
        <p>One thoughtful workspace for the products, orders, and customers that make your business yours.</p>
      </div>
      <div class="auth-art">
        <div class="art-orbit art-orbit--one" />
        <div class="art-orbit art-orbit--two" />
        <div class="art-sun" />
        <div class="art-card art-card--main">
          <div class="art-card-top"><span class="art-dot" /><span class="art-top-line" /><span class="art-menu">···</span></div>
          <div class="art-card-label">YOUR STORE</div>
          <div class="art-card-title">A little more<br />wonderful.</div>
          <div class="art-card-bottom"><span>PRODUCTS</span><span>ORDERS</span><span>PEOPLE</span></div>
        </div>
        <div class="art-card art-card--small">
          <span class="art-sparkle">✳</span>
          <span>Made for<br />your momentum</span>
        </div>
        <div class="art-card art-card--tag">✦ &nbsp; YOUR NEXT CHAPTER</div>
      </div>
      <div class="auth-visual-footer"><span>BUILT AROUND YOUR BUSINESS</span><span>01 — 03</span></div>
    </aside>

    <section class="auth-panel">
      <header class="auth-panel-top">
        <span class="auth-secure"><span class="secure-mark">✓</span> Secure tenant access</span>
      </header>
      <div class="auth-content">
        <div class="auth-mobile-brand"><span class="auth-brand-mark">V</span><span>Violet commerce</span></div>
        <RouterLink v-if="mode !== 'login'" class="auth-back" :to="{ name: 'login' }"><span aria-hidden="true">←</span> Back to sign in</RouterLink>
        <div class="auth-heading">
          <p class="eyebrow">{{ mode === 'login' ? 'WELCOME TO YOUR WORKSPACE' : 'ACCOUNT SECURITY' }}</p>
          <h1>{{ heading }}</h1>
          <p v-if="mode === 'login'">Good to have you back. Sign in to continue to your store.</p>
          <p v-else-if="mode === 'forgot'">Enter your Admin email and we’ll send a one-time reset code.</p>
          <p v-else>Enter the one-time code sent to your Admin email and choose a new password.</p>
        </div>

        <form class="login-form" @submit.prevent="submit">
          <div class="field">
            <label for="admin-email">Email address</label>
            <input id="admin-email" v-model.trim="form.email" type="email" autocomplete="username" placeholder="you@yourstore.com" required />
          </div>
          <div v-if="mode === 'login'" class="field">
            <div class="field-label-row"><label for="admin-password">Password</label><RouterLink class="auth-inline-link" :to="{ name: 'forgot-password' }">Forgot password?</RouterLink></div>
            <div class="password-control">
              <input id="admin-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" required />
              <button class="password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <template v-else-if="mode === 'reset'">
            <div class="field">
              <label for="reset-code">One-time code</label>
              <input id="reset-code" v-model.trim="form.code" class="code-input" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" placeholder="6-digit code" required />
            </div>
            <div class="field">
              <label for="new-password">New password</label>
              <input id="new-password" v-model="form.newPassword" type="password" autocomplete="new-password" minlength="12" placeholder="At least 12 characters" required />
            </div>
            <div class="field">
              <label for="confirm-password">Confirm new password</label>
              <input id="confirm-password" v-model="form.confirmPassword" type="password" autocomplete="new-password" minlength="12" placeholder="Enter the password again" required />
            </div>
            <p class="auth-hint"><span aria-hidden="true">◷</span> Reset codes expire after 10 minutes and can only be used once.</p>
          </template>
          <button class="button auth-submit" type="submit" :disabled="busy">
            <span>{{ busy ? 'Please wait…' : mode === 'login' ? 'Sign in to your store' : mode === 'forgot' ? 'Send reset code' : 'Update password' }}</span>
            <span v-if="!busy" class="submit-arrow" aria-hidden="true">→</span>
          </button>
        </form>

        <div v-if="error" class="error-box auth-message" role="alert">{{ error }}</div>
        <div v-if="notice" class="success-box auth-message" role="status">{{ notice }}</div>
        <div v-if="mode === 'reset' && route.query.requested === '1'" class="notice auth-message">If an eligible Admin account exists, a reset code has been sent.</div>
        <p v-if="mode === 'login'" class="auth-footnote">Access is limited to your store’s authorized Admin account.</p>
        <p v-else-if="mode === 'forgot'" class="auth-footnote">For security, we never reveal whether an email belongs to an account.</p>
        <RouterLink v-if="mode === 'login'" class="auth-alternate-link" :to="{ name: 'reset-password' }">Already have a reset code? <span>Enter it here</span></RouterLink>
      </div>
      <footer class="auth-panel-footer"><span>VIOLET COMMERCE</span><span>PRIVATE STORE ACCESS</span></footer>
    </section>
  </main>
</template>
