<script setup lang="ts">
interface Order {
  id: string
  order_number: string
  status: string
  payment_status: string
  total_cents: number
  currency: string
  created_at: string
}

const api = useStorefrontApi()
const { data: response, error, refresh } = await useAsyncData('storefront-account-orders', () =>
  api.get<{ data: Order[] }>('account/orders')
)
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  if (status === 401) await navigateTo('/auth/login?redirect=%2Faccount%2Forders')
  else throw createError({ statusCode: 503, statusMessage: 'Order history is unavailable.' })
}
const orders = computed(() => response.value ? unwrapResource(response.value) : [])

function dateLabel(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

useSeoMeta({ title: 'Order history', description: 'Review your orders from this store.' })
</script>

<template>
  <section class="page-section">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/account">← Your account</NuxtLink>
      <span class="eyebrow">The things you've found</span>
      <h1>Order history.</h1>
      <p>Every order, right where you left it.</p>
    </div>
    <div v-if="orders.length" class="order-list">
      <NuxtLink v-for="order in orders" :key="order.id" class="order-row" :to="`/account/orders/${order.id}`">
        <div>
          <span class="eyebrow">Order {{ order.order_number }}</span>
          <strong>{{ dateLabel(order.created_at) }}</strong>
        </div>
        <span class="order-status">{{ order.status.replaceAll('_', ' ') }}</span>
        <span class="order-payment">{{ order.payment_status.replaceAll('_', ' ') }}</span>
        <strong class="order-total">{{ formatMoney(order.total_cents, order.currency) }}</strong>
        <span class="order-arrow">↗</span>
      </NuxtLink>
    </div>
    <div v-else class="empty-state">
      <h2>No orders just yet.</h2>
      <p>When you find something you love, your orders will live here.</p>
      <NuxtLink class="button button--dark" to="/search">Explore the collection</NuxtLink>
    </div>
    <button v-if="error" class="text-action" type="button" @click="refresh()">Try loading again</button>
  </section>
</template>
