<script setup lang="ts">
const { data: tenant, error } = await useTenant()
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode ?? 404
  throw createError({
    statusCode: status === 503 ? 503 : 404,
    statusMessage: status === 503 ? 'This store is temporarily unavailable.' : 'Store not found.'
  })
}

const route = useRoute()
const { count, refresh } = useCart()
const menuOpen = ref(false)
const themeStyle = computed(() => {
  const theme = tenant.value?.theme_config || {}
  const colors = theme.colors || {}
  return {
    '--store-primary': colors.primary || '#543c30',
    '--store-secondary': colors.secondary || '#efe5dc',
    '--store-accent': colors.accent || '#855b45',
    '--store-background': colors.background || '#fbfaf7',
    '--store-text': colors.text || '#24231f',
    '--store-heading-font': theme.fonts?.heading || 'Playfair Display, Georgia, serif',
    '--store-body-font': theme.fonts?.body || 'DM Sans, sans-serif',
  }
})
const layoutClass = computed(() => `store-shell--${tenant.value?.theme_config?.layout_variant || 'classic'}`)

onMounted(() => {
  refresh().catch(() => undefined)
})

watch(() => route.fullPath, () => {
  menuOpen.value = false
})
</script>

<template>
  <div class="store-shell" :class="layoutClass" :style="themeStyle">
    <div class="announcement">Thoughtfully chosen goods, made for everyday living.</div>
    <header class="store-header">
      <NuxtLink class="wordmark" to="/">
        <img v-if="tenant?.theme_config?.logo_url" class="store-logo" :src="tenant.theme_config.logo_url" :alt="tenant?.name || 'Store logo'" />
        <span v-else>{{ tenant?.name || 'The Store' }}</span>
      </NuxtLink>
      <button class="menu-toggle" type="button" aria-label="Toggle navigation" @click="menuOpen = !menuOpen">
        {{ menuOpen ? 'Close' : 'Menu' }}
      </button>
      <nav class="store-nav" :class="{ 'store-nav--open': menuOpen }" aria-label="Main navigation">
        <NuxtLink to="/">Shop</NuxtLink>
        <NuxtLink to="/search">Discover</NuxtLink>
        <NuxtLink to="/account">My account</NuxtLink>
        <NuxtLink class="cart-link" to="/cart">Bag <span>{{ count }}</span></NuxtLink>
      </nav>
    </header>
    <main>
      <slot />
    </main>
    <footer class="store-footer">
      <NuxtLink class="footer-brand" to="/">{{ tenant?.name || 'The Store' }}</NuxtLink>
      <p>Made for the things you keep.</p>
      <div class="footer-links">
        <NuxtLink to="/account/orders">Order history</NuxtLink>
        <NuxtLink to="/cart">Your bag</NuxtLink>
      </div>
      <small>© {{ new Date().getFullYear() }} {{ tenant?.name || 'The Store' }}</small>
    </footer>
  </div>
</template>
