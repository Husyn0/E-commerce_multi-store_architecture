<script setup>
defineProps({
  title: { type: String, required: true },
  message: { type: String, required: true },
  confirmLabel: { type: String, default: 'Got it' },
  cancelLabel: { type: String, default: '' },
  danger: { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div class="admin-dialog-backdrop" @click.self="$emit('cancel')">
    <section class="admin-dialog" role="alertdialog" aria-modal="true" aria-labelledby="admin-dialog-title" aria-describedby="admin-dialog-message">
      <span class="admin-dialog__mark" :class="{ 'admin-dialog__mark--danger': danger }" aria-hidden="true">{{ danger ? '!' : 'i' }}</span>
      <h2 id="admin-dialog-title">{{ title }}</h2>
      <p id="admin-dialog-message">{{ message }}</p>
      <div class="admin-dialog__actions">
        <button v-if="cancelLabel" class="button button--secondary" type="button" @click="$emit('cancel')">{{ cancelLabel }}</button>
        <button class="button" :class="{ 'button--danger': danger }" type="button" @click="$emit('confirm')">{{ confirmLabel }}</button>
      </div>
    </section>
  </div>
</template>
