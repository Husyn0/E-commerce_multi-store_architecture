<script setup lang="ts">
const { cart, loading, error, refresh, update, remove } = useCart()
const working = ref<string | null>(null)
const message = ref('')

onMounted(() => {
  if (!cart.value) refresh().catch(() => undefined)
})

async function changeQuantity(itemId: string, quantity: number) {
  working.value = itemId
  message.value = ''
  try {
    await update(itemId, quantity)
  } catch (failure) {
    message.value = apiErrorMessage(failure)
  } finally {
    working.value = null
  }
}

async function removeItem(itemId: string) {
  working.value = itemId
  message.value = ''
  try {
    await remove(itemId)
  } catch (failure) {
    message.value = apiErrorMessage(failure)
  } finally {
    working.value = null
  }
}

useSeoMeta({ title: 'Your bag', description: 'Review the pieces in your shopping bag.' })
</script>

<template>
  <section class="page-section cart-page">
    <div class="page-intro page-intro--compact">
      <span class="eyebrow">Saved for later? Not quite.</span>
      <h1>Your bag<span v-if="cart?.items.length"> ({{ cart.items.length }})</span></h1>
      <p>A few good things, all in one place.</p>
    </div>

    <div v-if="loading && !cart" class="loading-state">Opening your bag…</div>
    <div v-else-if="error && !cart" class="error-state" role="alert">
      <p>{{ error }}</p>
      <button class="button button--outline" type="button" @click="refresh()">Try again</button>
    </div>
    <div v-else-if="!cart?.items.length" class="empty-state empty-state--bag">
      <div class="empty-bag-icon">◇</div>
      <h2>It's a little quiet in here.</h2>
      <p>Your next favorite might be just around the corner.</p>
      <NuxtLink class="button button--dark" to="/search">Explore the collection</NuxtLink>
    </div>
    <div v-else class="cart-layout">
      <div class="cart-lines">
        <article v-for="item in cart.items" :key="item.id" class="cart-line">
          <div class="cart-line__image">
            <img v-if="item.image_url" :src="item.image_url" :alt="item.title">
            <span v-else>{{ item.title.slice(0, 1) }}</span>
          </div>
          <div class="cart-line__info">
            <strong class="cart-line__title">{{ item.title }}</strong>
            <span v-if="item.variant_name" class="muted-copy">{{ item.variant_name }}</span>
            <span class="muted-copy">{{ formatMoney(item.unit_price_cents, cart.currency) }} each</span>
            <button class="remove-link" type="button" :disabled="working === item.id" @click="removeItem(item.id)">Remove</button>
          </div>
          <div class="cart-line__qty">
            <div class="quantity-control quantity-control--small">
              <button type="button" :disabled="working === item.id || item.quantity <= 1" :aria-label="`Decrease ${item.title} quantity`" @click="changeQuantity(item.id, item.quantity - 1)">−</button>
              <output>{{ item.quantity }}</output>
              <button type="button" :disabled="working === item.id" :aria-label="`Increase ${item.title} quantity`" @click="changeQuantity(item.id, item.quantity + 1)">+</button>
            </div>
          </div>
          <strong class="cart-line__total">{{ formatMoney(item.line_total_cents, cart.currency) }}</strong>
        </article>
        <p v-if="message" class="error-box" role="alert">{{ message }}</p>
      </div>
      <aside class="cart-summary">
        <span class="eyebrow">A little summary</span>
        <div class="summary-row"><span>Subtotal</span><strong>{{ formatMoney(cart.subtotal_cents, cart.currency) }}</strong></div>
        <div class="summary-row summary-row--muted"><span>Shipping</span><span>Calculated at checkout</span></div>
        <div class="summary-row summary-row--muted"><span>Taxes</span><span>Calculated at checkout</span></div>
        <p class="summary-note">Shipping and taxes are not included. The store will calculate your final total before payment.</p>
        <NuxtLink class="button button--dark button--wide" to="/checkout/address">Continue to checkout <span>→</span></NuxtLink>
        <NuxtLink class="continue-link" to="/search">← Keep looking</NuxtLink>
      </aside>
    </div>
  </section>
</template>
