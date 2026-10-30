<script setup>
defineProps({
  error: { type: String, default: '' },
  notice: { type: String, default: '' },
})
</script>

<template>
  <div v-if="error" class="error-box feedback-message" role="alert">{{ error }}</div>
  <div v-if="notice" class="success-box feedback-message" role="status">{{ notice }}</div>
</template>
