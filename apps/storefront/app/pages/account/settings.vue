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
const busy = ref(false)
const message = ref('')
const failed = ref(false)

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
        <small>Email address can't be changed here.</small>
      </label>
      <div class="profile-form__row">
        <label class="form-field"><span>First name</span><input v-model="form.first_name" type="text" autocomplete="given-name" maxlength="100"></label>
        <label class="form-field"><span>Last name</span><input v-model="form.last_name" type="text" autocomplete="family-name" maxlength="100"></label>
      </div>
      <label class="form-field"><span>Phone number for password recovery</span><input v-model.trim="form.phone" type="tel" autocomplete="tel" maxlength="32" placeholder="+14155552671"><small>Include your country code. A one-time code will be sent here if you choose phone recovery.</small></label>
      <p v-if="message" :class="failed ? 'error-box' : 'success-box'" role="status">{{ message }}</p>
      <div class="form-footer">
        <NuxtLink class="underlined-link" to="/account">Cancel</NuxtLink>
        <button class="button button--dark" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save details' }}</button>
      </div>
    </form>
  </section>
</template>
