<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


  <section class="card">
    <div class="card-head"><span class="card-title">Verification configuration</span><span class="status-pill">Read only</span></div>
    <div v-if="loading" class="loading">Loading verification settings…</div>
    <div v-else-if="settings" class="card-body">
      <div class="detail-list">
        <div class="detail-item"><div class="detail-label">Email verification</div><div class="detail-value">{{ settings.settings.require_email_verification ? 'Required' : 'Optional' }}</div></div>
        <div class="detail-item"><div class="detail-label">Phone verification</div><div class="detail-value">{{ settings.settings.require_phone_verification ? 'Required' : 'Optional' }}</div></div>
        <div class="detail-item"><div class="detail-label">Code verification driver</div><div class="detail-value">{{ settings.settings.verification_driver }}</div></div>
        <div class="detail-item"><div class="detail-label">Human-check driver</div><div class="detail-value">{{ settings.settings.human_check_driver }}</div></div>
      </div>
      <p class="notice section-notice">Driver settings are read-only and changed through the approved tenant CLI workflow.</p>
    </div>
  </section>
</template>
