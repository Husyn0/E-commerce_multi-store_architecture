<script setup lang="ts">
interface User {
  id: string
  email: string
  first_name: string | null
  last_name: string | null
  phone: string | null
  email_verified: boolean
  phone_verified: boolean
}

const api = useStorefrontApi()
const { data: tenant } = await useTenant()
const { data, error, refresh } = await useAsyncData('storefront-settings', () => api.get<{ user: User }>('account'))
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  if (status === 401) await navigateTo('/auth/login?redirect=%2Faccount%2Fsettings')
  else throw createError({ statusCode: 503, statusMessage: 'Account settings are unavailable.' })
}
const form = reactive({ first_name: '', last_name: '', phone: '' })
watch(data, (value) => {
  if (value) {
    form.first_name = value.user.first_name || ''
    form.last_name = value.user.last_name || ''
    form.phone = value.user.phone || ''
  }
}, { immediate: true })
watch(() => form.phone, (phone) => {
  if (phone !== data.value?.user.phone) {
    phoneCodeSent.value = false
    phoneCode.value = ''
    phoneMessage.value = ''
  }
})
const busy = ref(false)
const message = ref('')
const failed = ref(false)
const emailCode = ref('')
const phoneCode = ref('')
const emailBusy = ref(false)
const phoneBusy = ref(false)
const emailMessage = ref('')
const phoneMessage = ref('')
const emailCodeSent = ref(false)
const phoneCodeSent = ref(false)
const smsAvailable = computed(() => Boolean(tenant.value?.password_recovery?.phone_enabled))

async function save() {
  busy.value = true
  message.value = ''
  try {
    await api.send('account', 'PATCH', form)
    await refresh()
    message.value = 'Your details have been updated.'
  } catch (failure) {
    failed.value = true
    message.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

async function requestEmailCode() {
  emailBusy.value = true
  emailMessage.value = ''
  try {
    await api.send('account/email-verification/request', 'POST')
    emailCodeSent.value = true
    emailMessage.value = 'A verification code was sent to your email.'
  } catch (failure) {
    emailMessage.value = apiErrorMessage(failure)
  } finally {
    emailBusy.value = false
  }
}

async function verifyEmail() {
  emailBusy.value = true
  emailMessage.value = ''
  try {
    await api.send('account/email-verification', 'POST', { code: emailCode.value.trim() })
    await refresh()
    emailMessage.value = 'Your email is verified.'
  } catch (failure) {
    emailMessage.value = apiErrorMessage(failure)
  } finally {
    emailBusy.value = false
  }
}

async function requestPhoneCode() {
  phoneBusy.value = true
  phoneMessage.value = ''
  try {
    await api.send('account/phone-verification/request', 'POST')
    phoneCodeSent.value = true
    phoneMessage.value = 'A verification code was sent to your phone.'
  } catch (failure) {
    phoneMessage.value = apiErrorMessage(failure)
  } finally {
    phoneBusy.value = false
  }
}

async function verifyPhone() {
  phoneBusy.value = true
  phoneMessage.value = ''
  try {
    await api.send('account/phone-verification', 'POST', { code: phoneCode.value.trim() })
    await refresh()
    phoneMessage.value = 'Your phone number is verified.'
  } catch (failure) {
    phoneMessage.value = apiErrorMessage(failure)
  } finally {
    phoneBusy.value = false
  }
}

useSeoMeta({ title: 'Your details', description: 'Update your customer profile.' })
</script>

<template>
  <section class="page-section settings-page">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/account">← Your account</NuxtLink>
      <span class="eyebrow">A little about you</span>
      <h1>Your details.</h1>
      <p>Keep your account information up to date.</p>
    </div>
    <form class="profile-form" @submit.prevent="save">
      <label class="form-field">
        <span>Email address</span>
        <input :value="data?.user.email" type="email" disabled>
        <small>Email address can't be changed here. Verification is required to place orders.</small>
      </label>
      <section class="verification-panel">
        <div><strong>Email verification</strong><span :class="data?.user.email_verified ? 'verification-state verification-state--verified' : 'verification-state'">{{ data?.user.email_verified ? 'Verified' : 'Not verified' }}</span></div>
        <template v-if="!data?.user.email_verified">
          <button v-if="!emailCodeSent" class="button button--outline" type="button" :disabled="emailBusy" @click="requestEmailCode">{{ emailBusy ? 'Sending…' : 'Send email code' }}</button>
          <div v-else class="verification-code-form"><label class="form-field"><span>6-digit email code</span><input v-model="emailCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><button class="button button--outline" type="button" :disabled="emailBusy || !/^[0-9]{6}$/.test(emailCode)" @click="verifyEmail">{{ emailBusy ? 'Checking…' : 'Verify email' }}</button><button class="text-action" type="button" :disabled="emailBusy" @click="requestEmailCode">Send another code</button></div>
        </template>
        <p v-if="emailMessage" class="verification-message" role="status">{{ emailMessage }}</p>
      </section>
      <div class="profile-form__row">
        <label class="form-field"><span>First name</span><input v-model="form.first_name" type="text" autocomplete="given-name" maxlength="100"></label>
        <label class="form-field"><span>Last name</span><input v-model="form.last_name" type="text" autocomplete="family-name" maxlength="100"></label>
      </div>
      <label class="form-field"><span>Phone number for orders</span><input v-model.trim="form.phone" type="tel" autocomplete="tel" maxlength="32" placeholder="+961…"><small>Include your country code. You must verify this number before placing an order.</small></label>
      <section class="verification-panel">
        <div><strong>Phone verification</strong><span :class="data?.user.phone_verified ? 'verification-state verification-state--verified' : 'verification-state'">{{ data?.user.phone_verified ? 'Verified' : 'Not verified' }}</span></div>
        <template v-if="!data?.user.phone_verified">
          <p v-if="data?.user.phone !== form.phone" class="verification-message">Save the phone number before requesting a code.</p>
          <template v-else-if="smsAvailable">
            <button v-if="!phoneCodeSent" class="button button--outline" type="button" :disabled="phoneBusy || !form.phone" @click="requestPhoneCode">{{ phoneBusy ? 'Sending…' : 'Send phone code' }}</button>
            <div v-else class="verification-code-form"><label class="form-field"><span>6-digit SMS code</span><input v-model="phoneCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><button class="button button--outline" type="button" :disabled="phoneBusy || !/^[0-9]{6}$/.test(phoneCode)" @click="verifyPhone">{{ phoneBusy ? 'Checking…' : 'Verify phone' }}</button><button class="text-action" type="button" :disabled="phoneBusy" @click="requestPhoneCode">Send another code</button></div>
          </template>
          <p v-else class="verification-message">Phone verification is unavailable until the store activates SMS delivery.</p>
        </template>
        <p v-if="phoneMessage" class="verification-message" role="status">{{ phoneMessage }}</p>
      </section>
      <p v-if="message" :class="failed ? 'error-box' : 'success-box'" role="status">{{ message }}</p>
      <div class="form-footer">
        <NuxtLink class="underlined-link" to="/account">Cancel</NuxtLink>
        <button class="button button--dark" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save details' }}</button>
      </div>
    </form>
  </section>
</template>
