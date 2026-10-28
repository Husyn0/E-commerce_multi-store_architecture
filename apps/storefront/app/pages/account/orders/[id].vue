<script setup lang="ts">
interface OrderItem {
  id: string
  product_id: string | null
  variant_id: string | null
  product_snapshot: { title?: string; variant?: { name?: string } | null }
  quantity: number
  unit_price_cents: number
  total_cents: number
}
interface Order {
  id: string
  order_number: string
  status: string
  payment_status: string
  total_cents: number
  refunded_total_cents: number
  currency: string
  customer_email: string
  shipping_address: Record<string, string | null>
  items: OrderItem[]
  created_at: string
}

const route = useRoute()
const api = useStorefrontApi()
const id = String(route.params.id)
const { data: response, error } = await useAsyncData(`storefront-order-${id}`, () =>
  api.get<{ data: Order }>(`account/orders/${encodeURIComponent(id)}`)
)
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  if (status === 401) await navigateTo(`/auth/login?redirect=${encodeURIComponent(route.fullPath)}`)
  else throw createError({ statusCode: status === 404 ? 404 : 503, statusMessage: status === 404 ? 'Order not found.' : 'Order details are unavailable.' })
}
const order = computed(() => response.value ? unwrapResource(response.value) : null)
useSeoMeta({ title: () => order.value ? `Order ${order.value.order_number}` : 'Order details' })
</script>

<template>
  <section v-if="order" class="page-section order-detail-page">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/account/orders">← Order history</NuxtLink>
      <span class="eyebrow">A little update</span>
      <h1>Order {{ order.order_number }}.</h1>
      <p>Placed {{ new Date(order.created_at).toLocaleDateString() }} · {{ order.status.replaceAll('_', ' ') }}</p>
    </div>
    <div class="order-detail-grid">
      <div class="order-detail-items">
        <article v-for="item in order.items" :key="item.id" class="order-item">
          <div class="order-item__mark">{{ item.product_snapshot.title?.slice(0, 1) || '◇' }}</div>
          <div class="order-item__description">
            <strong>{{ item.product_snapshot.title || 'Store item' }}</strong>
            <span v-if="item.product_snapshot.variant?.name">{{ item.product_snapshot.variant.name }}</span>
            <span>Qty {{ item.quantity }} · {{ formatMoney(item.unit_price_cents, order.currency) }} each</span>
          </div>
          <strong>{{ formatMoney(item.total_cents, order.currency) }}</strong>
        </article>
      </div>
      <aside class="order-summary">
        <span class="eyebrow">Order summary</span>
        <div class="summary-row"><span>Payment</span><strong>{{ order.payment_status.replaceAll('_', ' ') }}</strong></div>
        <div class="summary-row"><span>Total</span><strong>{{ formatMoney(order.total_cents, order.currency) }}</strong></div>
        <div v-if="order.refunded_total_cents > 0" class="summary-row"><span>Refunded</span><strong>{{ formatMoney(order.refunded_total_cents, order.currency) }}</strong></div>
        <div class="address-block">
          <span class="eyebrow">Delivering to</span>
          <p>{{ order.shipping_address.recipient_name }}<br>{{ order.shipping_address.line1 }}<br>{{ order.shipping_address.city }}, {{ order.shipping_address.postal_code }}<br>{{ order.shipping_address.country_code }}</p>
        </div>
      </aside>
    </div>
  </section>
</template>
