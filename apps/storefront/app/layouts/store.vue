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

onMounted(() => {
  refresh().catch(() => undefined)
})

watch(() => route.fullPath, () => {
  menuOpen.value = false
})
</script>

<template>
  <div class="store-shell">
    <div class="announcement">Thoughtfully chosen goods, made for everyday living.</div>
    <header class="store-header">
      <NuxtLink class="wordmark" to="/">{{ tenant?.name || 'The Store' }}</NuxtLink>
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
