<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  section: { type: String, required: true },
})

defineEmits(['add-product', 'add-category'])
</script>

<template>
  <div class="page-heading">
    <div>
      <p class="eyebrow">Store workspace</p>
      <h1>{{ title }}</h1>
      <p class="page-description">{{ description }}</p>
    </div>
    <button v-if="section === 'products'" class="button" type="button" @click="$emit('add-product')">Add product</button>
    <button v-if="section === 'categories'" class="button" type="button" @click="$emit('add-category')">Add category</button>
  </div>
</template>
