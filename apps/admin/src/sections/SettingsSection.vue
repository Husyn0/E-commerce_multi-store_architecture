<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


  <template>
    <div v-if="loading" class="loading">Loading settings…</div>
    <section v-else-if="settings" class="card">
      <div class="card-head"><span class="card-title">Store preferences</span></div>
      <form class="card-body" @submit.prevent="saveSettings">
        <div class="form-grid">
          <div class="field"><label for="tenant-name">Store name</label><input id="tenant-name" v-model.trim="settingsForm.name" maxlength="160" required /></div>
          <div class="field"><label for="tenant-locale">Default locale</label><input id="tenant-locale" v-model.trim="settingsForm.default_locale" maxlength="5" pattern="[a-z]{2}(-[A-Z]{2})?" required /><small>Examples: en, en-US</small></div>
          <div class="field"><label>Default currency</label><input :value="settings.tenant.default_currency" readonly /><small>Currency is immutable after catalog setup.</small></div>
          <div class="field"><label>Tenant status</label><input :value="settings.tenant.status || 'See tenant status above'" readonly /><small>Status is managed by the CLI.</small></div>
          <label class="checkbox-field"><input v-model="settingsForm.require_email_verification" type="checkbox" /> Require email verification</label>
          <label class="checkbox-field"><input v-model="settingsForm.require_phone_verification" type="checkbox" /> Require phone verification</label>
        </div>
        <div class="form-actions"><button class="button" :disabled="busy">{{ busy ? 'Saving…' : 'Save settings' }}</button></div>
      </form>
    </section>
  </template>
</template>
