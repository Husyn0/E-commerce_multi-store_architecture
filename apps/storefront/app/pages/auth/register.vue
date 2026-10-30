<script setup lang="ts">
interface Customer {
  id: string
  email: string
  first_name: string | null
  last_name: string | null
  email_verified: boolean
}

interface ChallengeWidget {
  render: (target: string, options: Record<string, unknown>) => string
}

const api = useStorefrontApi()
const route = useRoute()
const { data: tenant } = await useTenant()
const { cart, refresh } = useCart()
const runtimeConfig = useRuntimeConfig()
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const emailCode = ref('')
const codeSent = ref(false)
const demoHumanConfirmed = ref(false)
const humanToken = ref('')
const humanError = ref('')
const error = ref('')
const notice = ref('')
const busy = ref(false)
const sendingCode = ref(false)
const registration = computed(() => tenant.value?.registration)
const needsEmailVerification = computed(() => Boolean(registration.value?.require_email_verification))
const needsPhoneVerification = computed(() => Boolean(registration.value?.require_phone_verification))
const humanDriver = computed(() => registration.value?.human_check_driver || 'demo')

declare global {
  interface Window {
    turnstile?: ChallengeWidget
    hcaptcha?: ChallengeWidget
  }
}

function loadChallengeWidget() {
  if (import.meta.server || humanDriver.value === 'demo') return

  const isTurnstile = humanDriver.value === 'turnstile'
  const siteKey = isTurnstile
    ? runtimeConfig.public.turnstileSiteKey
    : runtimeConfig.public.hcaptchaSiteKey
  if (!siteKey) {
    humanError.value = `This store needs ${isTurnstile ? 'Turnstile' : 'hCaptcha'} site-key configuration before sign-up can continue.`
    return
  }

  const render = () => {
    const widget = isTurnstile ? window.turnstile : window.hcaptcha
    if (!widget) return
    widget.render('#register-human-check', {
      sitekey: siteKey,
      ...(isTurnstile ? { action: 'register' } : {}),
      callback: (token: string) => {
        humanToken.value = token
        humanError.value = ''
      },
      'expired-callback': () => { humanToken.value = '' },
      'error-callback': () => { humanToken.value = '' },
    })
  }

  if ((isTurnstile && window.turnstile) || (!isTurnstile && window.hcaptcha)) {
    render()
    return
  }

  const scriptId = isTurnstile ? 'turnstile-api' : 'hcaptcha-api'
  const existingScript = document.getElementById(scriptId) as HTMLScriptElement | null
  if (existingScript) {
    existingScript.addEventListener('load', render, { once: true })
    return
  }
  const script = document.createElement('script')
  script.id = scriptId
  script.src = isTurnstile
    ? 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    : 'https://js.hcaptcha.com/1/api.js?render=explicit'
  script.async = true
  script.defer = true
  script.onload = render
  script.onerror = () => { humanError.value = 'The human verification challenge could not be loaded.' }
  document.head.appendChild(script)
}

async function sendEmailCode() {
  if (!email.value.trim()) {
    error.value = 'Enter your email address first.'
    return
  }
  sendingCode.value = true
  error.value = ''
  notice.value = ''
  try {
    if (!cart.value) await refresh()
    await api.send('verification/email', 'POST', { email: email.value.trim() })
    codeSent.value = true
    notice.value = 'If email delivery is configured, a verification code will arrive shortly.'
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    sendingCode.value = false
  }
}

async function register() {
  busy.value = true
  error.value = ''
  notice.value = ''
  try {
    if (needsPhoneVerification.value) {
      throw new Error('This store requires phone verification, which is not available for online registration yet.')
    }
    if (!cart.value) await refresh()

    const responseToken = humanDriver.value === 'demo' ? 'demo-pass' : humanToken.value
    if (humanDriver.value === 'demo' && !demoHumanConfirmed.value) {
      throw new Error('Confirm that you are ready to create an account.')
    }
    if (humanDriver.value !== 'demo' && !responseToken) {
      throw new Error('Complete the human verification challenge before continuing.')
    }

    await api.send('verification/human', 'POST', {
      action: 'register',
      response_token: responseToken,
    })

    const result = await api.send<{ user: Customer }>('auth/register', 'POST', {
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      first_name: firstName.value.trim() || null,
      last_name: lastName.value.trim() || null,
      ...(needsEmailVerification.value ? { email_verification_code: emailCode.value.trim() } : {}),
    })
    useState<Customer | null>('storefront-customer').value = result.user
    await navigateTo(typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
      && !route.query.redirect.startsWith('//') && !route.query.redirect.includes('\\')
      ? route.query.redirect
      : '/account')
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

useSeoMeta({ title: 'Create an account', description: 'Create your customer account.' })
onMounted(loadChallengeWidget)
</script>

<template>
  <section class="auth-page">
    <div class="auth-art">
      <div class="auth-art__shape auth-art__shape--one" />
      <div class="auth-art__shape auth-art__shape--two" />
      <span class="auth-art__caption">A place for your favorites.</span>
    </div>
    <div class="auth-panel auth-panel--register">
      <NuxtLink class="back-link" to="/">← Back to the store</NuxtLink>
      <span class="eyebrow">Your account</span>
      <h1>Make yourself at home.</h1>
      <p class="auth-copy">Create an account to keep track of your orders and make checkout easier.</p>

      <p v-if="needsPhoneVerification" class="register-notice" role="status">This store requires phone verification. Online account creation is currently unavailable.</p>
      <p v-else-if="needsEmailVerification" class="register-notice" role="note">Email verification is required. You’ll need access to your inbox to finish creating your account.</p>
      <form class="auth-form" @submit.prevent="register">
        <div class="register-name-row">
          <label class="form-field"><span>First name <small>Optional</small></span><input v-model.trim="firstName" autocomplete="given-name" maxlength="100" /></label>
          <label class="form-field"><span>Last name <small>Optional</small></span><input v-model.trim="lastName" autocomplete="family-name" maxlength="100" /></label>
        </div>
        <label class="form-field"><span>Email address</span><input v-model="email" type="email" autocomplete="email" required placeholder="you@example.com" /></label>
        <div v-if="needsEmailVerification" class="verification-code-row">
          <label class="form-field"><span>Email verification code</span><input v-model="emailCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" /></label>
          <button class="button button--outline code-button" type="button" :disabled="sendingCode" @click="sendEmailCode">{{ sendingCode ? 'Sending…' : codeSent ? 'Send again' : 'Send code' }}</button>
        </div>
        <label class="form-field"><span>Password</span><input v-model="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="At least 8 characters" /></label>
        <label class="form-field"><span>Confirm password</span><input v-model="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="Enter your password again" /></label>

        <label v-if="humanDriver === 'demo'" class="register-human-demo"><input v-model="demoHumanConfirmed" type="checkbox" /> I’m ready to create my account</label>
        <div v-else id="register-human-check" class="register-human-check" />
        <p v-if="humanError" class="error-box" role="alert">{{ humanError }}</p>
        <p v-if="notice" class="success-box" role="status">{{ notice }}</p>
        <p v-if="error" class="error-box" role="alert">{{ error }}</p>
        <button class="button button--dark button--wide" type="submit" :disabled="busy || needsPhoneVerification || Boolean(humanError)">
          {{ busy ? 'Creating account…' : 'Create account' }} <span v-if="!busy">→</span>
        </button>
      </form>
      <p class="auth-footnote">Already have an account? <NuxtLink to="/auth/login">Sign in</NuxtLink></p>
    </div>
  </section>
</template>
