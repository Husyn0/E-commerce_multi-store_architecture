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
    <div class="card-head"><span class="card-title">Payment configuration</span><span class="status-pill">Read only</span></div>
    <div v-if="loading" class="loading">Loading provider settings…</div>
    <div v-else-if="settings" class="card-body">
      <div class="detail-list">
        <div class="detail-item"><div class="detail-label">Payment driver</div><div class="detail-value">{{ settings.settings.payment_driver }}</div></div>
        <div class="detail-item"><div class="detail-label">Tenant connected account</div><div class="detail-value">{{ settings.settings.stripe_account_id_masked || 'Not configured' }}</div></div>
        <div class="detail-item"><div class="detail-label">Email provider</div><div class="detail-value">{{ settings.settings.email_provider }}</div></div>
        <div class="detail-item"><div class="detail-label">SMS provider</div><div class="detail-value">{{ settings.settings.sms_provider }}</div></div>
      </div>
      <p class="notice section-notice">Provider and connected-account settings are operator-managed. This screen intentionally has no edit controls.</p>
    </div>
  </section>
</template>
