<script setup lang="ts">
const api = useStorefrontApi()
const { data: tenant } = await useTenant()
const { cart, refresh } = useCart()
const channel = ref<'email' | 'sms'>('email')
const email = ref('')
const phone = ref('')
const code = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const codeRequested = ref(false)
const sendingCode = ref(false)
const resetting = ref(false)
const error = ref('')
const notice = ref('')
const recovery = computed(() => tenant.value?.password_recovery)
const phoneAvailable = computed(() => Boolean(recovery.value?.phone_enabled))
const emailAvailable = computed(() => recovery.value?.email_enabled !== false)
const identifier = computed(() => channel.value === 'email' ? email.value.trim() : phone.value.trim())

watch(channel, () => {
  codeRequested.value = false
  code.value = ''
  error.value = ''
  notice.value = ''
})

async function requestCode() {
  sendingCode.value = true
  error.value = ''
  notice.value = ''
  try {
    if (!cart.value) await refresh()
    await api.send('auth/password-reset/request', 'POST', {
      channel: channel.value,
      ...(channel.value === 'email' ? { email: identifier.value } : { phone: identifier.value }),
    })
    code.value = ''
    codeRequested.value = true
    notice.value = `If an eligible account exists, a reset code will be sent to that ${channel.value === 'email' ? 'email address' : 'phone number'}.`
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    sendingCode.value = false
  }
}

async function resetPassword() {
  resetting.value = true
  error.value = ''
  notice.value = ''
  try {
    if (!cart.value) await refresh()
    await api.send('auth/password-reset', 'POST', {
      channel: channel.value,
      ...(channel.value === 'email' ? { email: identifier.value } : { phone: identifier.value }),
      code: code.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await navigateTo('/auth/login?reset=1')
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    resetting.value = false
  }
}

useSeoMeta({ title: 'Reset your password', description: 'Recover access to your store account.' })
</script>

<template>
  <section class="auth-page">
    <div class="auth-visual">
      <div class="auth-visual__top"><span class="auth-visual__mark">✳</span><span>MEMBER STUDIO <b>01</b></span></div>
      <div class="auth-visual__message">
        <span class="auth-visual__eyebrow">A little more personal</span>
        <h2>Good things,<br>kept close.</h2>
        <p>Your orders, addresses, and favorite finds in one thoughtful place.</p>
      </div>
      <div class="auth-visual__benefits" aria-label="Account benefits">
        <div><span>01</span><p><b>Order history</b><small>Every parcel, easy to find</small></p><span aria-hidden="true">↗</span></div>
        <div><span>02</span><p><b>Quicker checkout</b><small>Your details ready when you are</small></p><span aria-hidden="true">↗</span></div>
      </div>
      <span class="auth-visual__foot">Thoughtfully chosen goods · Made for everyday living</span>
    </div>
    <div class="auth-panel auth-panel--register">
      <NuxtLink class="back-link" to="/auth/login">← Back to sign in</NuxtLink>
      <span class="eyebrow">Account recovery</span>
      <h1>Let’s get you back.</h1>
      <p class="auth-copy">Choose where to receive a one-time reset code.</p>

      <form class="auth-form" @submit.prevent="codeRequested ? resetPassword() : requestCode()">
        <fieldset class="recovery-channel">
          <legend>Send code to</legend>
          <label><input v-model="channel" type="radio" value="email" :disabled="!emailAvailable"> Email</label>
          <label><input v-model="channel" type="radio" value="sms" :disabled="!phoneAvailable"> Phone <small v-if="!phoneAvailable">(not set up)</small></label>
        </fieldset>

        <label v-if="channel === 'email'" class="form-field"><span>Email address</span><input v-model.trim="email" type="email" autocomplete="email" :readonly="codeRequested" required placeholder="you@example.com" /></label>
        <label v-else class="form-field"><span>Phone number</span><input v-model.trim="phone" type="tel" autocomplete="tel" :readonly="codeRequested" required placeholder="+14155552671" /><small>Enter the number saved in your account, including country code.</small></label>
        <p v-if="!phoneAvailable" class="recovery-hint">SMS recovery isn’t configured for this store yet.</p>

        <template v-if="codeRequested">
          <label class="form-field"><span>One-time code</span><input v-model="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" /></label>
          <label class="form-field"><span>New password</span><input v-model="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required placeholder="At least 12 characters" /></label>
          <label class="form-field"><span>Confirm new password</span><input v-model="passwordConfirmation" type="password" autocomplete="new-password" minlength="12" maxlength="255" required placeholder="Enter it again" /></label>
          <button class="text-action" type="button" :disabled="sendingCode" @click="requestCode">{{ sendingCode ? 'Sending…' : 'Send another code' }}</button>
        </template>

        <p v-if="notice" class="success-box" role="status">{{ notice }}</p>
        <p v-if="error" class="error-box" role="alert">{{ error }}</p>
        <p v-if="!emailAvailable && !phoneAvailable" class="register-notice" role="status">Password recovery is not configured for this store yet.</p>
        <button class="button button--dark button--wide" type="submit" :disabled="sendingCode || resetting || (!emailAvailable && channel === 'email') || (channel === 'sms' && !phoneAvailable)">
          {{ codeRequested ? (resetting ? 'Updating password…' : 'Reset password') : (sendingCode ? 'Sending code…' : 'Send reset code') }} <span v-if="!sendingCode && !resetting">→</span>
        </button>
      </form>
      <p class="auth-footnote">Remembered your password? <NuxtLink to="/auth/login">Sign in</NuxtLink></p>
    </div>
  </section>
</template>
