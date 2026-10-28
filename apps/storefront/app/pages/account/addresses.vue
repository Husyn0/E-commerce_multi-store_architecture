<script setup lang="ts">
interface Address {
  id: string
  label: string | null
  recipient_name: string
  phone: string | null
  line1: string
  line2: string | null
  city: string
  state: string | null
  postal_code: string
  country_code: string
  is_default_shipping: boolean
  is_default_billing: boolean
}
interface Account { user: { id: string }; addresses: Address[] }

const api = useStorefrontApi()
const { data, error, refresh } = await useAsyncData('storefront-addresses', () => api.get<Account>('account'))
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  if (status === 401) await navigateTo('/auth/login?redirect=%2Faccount%2Faddresses')
  else throw createError({ statusCode: 503, statusMessage: 'Saved addresses are unavailable.' })
}
const editing = ref<string | null>(null)
const busy = ref(false)
const message = ref('')
const failed = ref(false)
const form = reactive({
  label: '', recipient_name: '', phone: '', line1: '', line2: '', city: '',
  state: '', postal_code: '', country_code: '', is_default_shipping: false,
  is_default_billing: false
})

function clearForm() {
  editing.value = null
  Object.assign(form, {
    label: '', recipient_name: '', phone: '', line1: '', line2: '', city: '',
    state: '', postal_code: '', country_code: '', is_default_shipping: false,
    is_default_billing: false
  })
}

function editAddress(address: Address) {
  editing.value = address.id
  Object.assign(form, address, {
    label: address.label || '', phone: address.phone || '', line2: address.line2 || '',
    state: address.state || ''
  })
}

async function saveAddress() {
  busy.value = true
  message.value = ''
  try {
    await api.send(editing.value ? `account/addresses/${editing.value}` : 'account/addresses', editing.value ? 'PATCH' : 'POST', form)
    await refresh()
    clearForm()
  } catch (failure) {
    failed.value = true
    message.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

async function deleteAddress(id: string) {
  if (!window.confirm('Remove this saved address?')) return
  busy.value = true
  try {
    await api.send(`account/addresses/${id}`, 'DELETE')
    await refresh()
  } catch (failure) {
    failed.value = true
    message.value = apiErrorMessage(failure)
  } finally {
    busy.value = false
  }
}

useSeoMeta({ title: 'Saved addresses', description: 'Manage your delivery and billing addresses.' })
</script>

<template>
  <section class="page-section">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/account">← Your account</NuxtLink>
      <span class="eyebrow">A place for the important details</span>
      <h1>Saved addresses.</h1>
      <p>Keep your delivery details ready for next time.</p>
    </div>
    <div class="address-layout">
      <div class="saved-addresses">
        <article v-for="address in data?.addresses || []" :key="address.id" class="saved-address">
          <div>
            <span class="eyebrow">{{ address.label || 'Address' }}</span>
            <strong>{{ address.recipient_name }}</strong>
            <p>{{ address.line1 }}<template v-if="address.line2">, {{ address.line2 }}</template><br>{{ address.city }}, {{ address.postal_code }}<br>{{ address.country_code }}</p>
            <div class="address-tags">
              <span v-if="address.is_default_shipping">Default shipping</span>
              <span v-if="address.is_default_billing">Default billing</span>
            </div>
          </div>
          <div class="address-actions">
            <button class="text-action" type="button" @click="editAddress(address)">Edit</button>
            <button class="text-action text-action--danger" type="button" :disabled="busy" @click="deleteAddress(address.id)">Remove</button>
          </div>
        </article>
        <div v-if="!data?.addresses.length" class="empty-state"><p>No saved addresses yet. Add one for easier checkout.</p></div>
      </div>
      <form class="address-form" @submit.prevent="saveAddress">
        <span class="eyebrow">{{ editing ? 'Make it right' : 'Add something useful' }}</span>
        <h2>{{ editing ? 'Edit address' : 'New address' }}</h2>
        <label class="form-field"><span>Label (optional)</span><input v-model="form.label" maxlength="100" placeholder="Home, work…"></label>
        <label class="form-field"><span>Recipient name</span><input v-model="form.recipient_name" autocomplete="name" required maxlength="160"></label>
        <label class="form-field"><span>Phone (optional)</span><input v-model="form.phone" autocomplete="tel" maxlength="32"></label>
        <label class="form-field"><span>Address line 1</span><input v-model="form.line1" autocomplete="address-line1" required maxlength="255"></label>
        <label class="form-field"><span>Address line 2 (optional)</span><input v-model="form.line2" autocomplete="address-line2" maxlength="255"></label>
        <div class="profile-form__row">
          <label class="form-field"><span>City</span><input v-model="form.city" autocomplete="address-level2" required maxlength="120"></label>
          <label class="form-field"><span>State / region</span><input v-model="form.state" autocomplete="address-level1" maxlength="120"></label>
        </div>
        <div class="profile-form__row">
          <label class="form-field"><span>Postal code</span><input v-model="form.postal_code" autocomplete="postal-code" required maxlength="32"></label>
          <label class="form-field"><span>Country code</span><input v-model="form.country_code" autocomplete="country" required minlength="2" maxlength="2" placeholder="US"></label>
        </div>
        <label class="check-field"><input v-model="form.is_default_shipping" type="checkbox"> Default shipping address</label>
        <label class="check-field"><input v-model="form.is_default_billing" type="checkbox"> Default billing address</label>
        <p v-if="message" :class="failed ? 'error-box' : 'success-box'" role="status">{{ message }}</p>
        <div class="form-footer">
          <button v-if="editing" class="text-action" type="button" @click="clearForm">Cancel edit</button>
          <button class="button button--dark" type="submit" :disabled="busy">{{ busy ? 'Saving…' : editing ? 'Save changes' : 'Add address' }}</button>
        </div>
      </form>
    </div>
  </section>
</template>
