<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


  <div class="order-filters">
    <label class="filter-label">Search orders
      <input v-model.trim="filters.order_q" class="search-input table-search-input" placeholder="Order number or customer email" @keyup.enter="searchTable" />
    </label>
    <label class="filter-label">Fulfillment
      <select v-model="filters.status" class="search-input"><option value="">All statuses</option><option v-for="status in ['pending','processing','shipped','delivered','cancelled']" :key="status" :value="status">{{ status }}</option></select>
    </label>
    <label class="page-size-control">Rows<select class="search-input" :value="pageSizes.orders" @change="changePageSize('orders', $event.target.value)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
    <button class="button button--secondary" type="button" @click="searchTable">Search</button>
    <label class="filter-label">Payment
      <select v-model="filters.payment_status" class="search-input"><option value="">All payments</option><option v-for="status in ['requires_payment','processing','succeeded','failed','refunded']" :key="status" :value="status">{{ status.replaceAll('_', ' ') }}</option></select>
    </label>
    <button class="button button--secondary" type="button" @click="loadSection(1)">Apply filters</button>
    <button class="text-button" type="button" @click="filters.order_q = ''; filters.status = ''; filters.payment_status = ''; loadSection(1)">Clear</button>
  </div>
  <section v-if="selectedOrder" class="card detail-card">
    <div class="card-head"><div><span class="card-title">Order {{ selectedOrder.order_number }}</span><div class="metric-foot">Placed {{ new Date(selectedOrder.created_at).toLocaleString() }}</div></div><button class="button button--secondary button--small" type="button" @click="selectedOrder = null">Close details</button></div>
    <div class="card-body">
      <div class="detail-list">
        <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">{{ selectedOrder.customer_email }}</div></div>
        <div class="detail-item"><div class="detail-label">Payment status</div><div class="detail-value">{{ selectedOrder.payment_status }}</div></div>
        <div class="detail-item"><div class="detail-label">Payment provider</div><div class="detail-value">{{ selectedOrder.payment_provider }}</div></div>
        <div class="detail-item"><div class="detail-label">Total / refunded</div><div class="detail-value">{{ money(selectedOrder.total_cents, selectedOrder.currency) }} / {{ money(selectedOrder.refunded_total_cents, selectedOrder.currency) }}</div></div>
        <div class="detail-item"><div class="detail-label">Shipping address snapshot</div><div class="detail-value">{{ formatAddress(selectedOrder.shipping_address) }}</div></div>
        <div class="detail-item"><div class="detail-label">Billing address snapshot</div><div class="detail-value">{{ formatAddress(selectedOrder.billing_address) }}</div></div>
      </div>
      <div class="detail-section">
        <h2 class="subheading">Items</h2>
        <div v-if="!selectedOrder.items?.length" class="empty-state">No item details returned.</div>
        <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Item snapshot</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead><tbody><tr v-for="item in selectedOrder.items" :key="item.id"><td class="primary-cell">{{ item.product_snapshot?.title || item.product_snapshot?.name || 'Historical item' }}</td><td>{{ item.quantity }}</td><td>{{ money(item.unit_price_cents, selectedOrder.currency) }}</td><td>{{ money(item.total_cents, selectedOrder.currency) }}</td></tr></tbody></table></div>
      </div>
      <div class="detail-section">
        <h2 class="subheading">Refund requests</h2>
        <div v-if="!selectedOrder.refunds?.length" class="empty-state">No refunds recorded.</div>
        <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Amount</th><th>Status</th><th>Reason</th><th>Created</th></tr></thead><tbody><tr v-for="refund in selectedOrder.refunds" :key="refund.id"><td>{{ money(refund.amount_cents, selectedOrder.currency) }}</td><td><span class="status-pill" :class="`status-pill--${refund.status}`">{{ refund.status }}</span></td><td>{{ refund.reason || '—' }}</td><td>{{ refund.created_at ? new Date(refund.created_at).toLocaleString() : '—' }}</td></tr></tbody></table></div>
      </div>
    </div>
  </section>
  <section v-if="refundingOrder" class="card form-card">
    <div class="card-head"><span class="card-title">Refund order {{ refundingOrder.order_number }}</span></div>
    <form class="card-body" @submit.prevent="submitRefund">
      <p class="page-description form-description">Remaining refundable balance: {{ money(refundingOrder.total_cents - refundingOrder.refunded_total_cents, refundingOrder.currency) }}. Enter the amount in minor units.</p>
      <div class="form-grid">
        <div class="field"><label for="refund-amount">Amount (minor units)</label><input id="refund-amount" v-model="refundAmount" :readonly="Boolean(refundAttempt)" type="number" min="1" :max="refundingOrder.total_cents - refundingOrder.refunded_total_cents" step="1" required /></div>
        <div class="field"><label for="refund-reason">Reason (optional)</label><input id="refund-reason" v-model.trim="refundReason" :readonly="Boolean(refundAttempt)" maxlength="500" /></div>
      </div>
      <p v-if="refundAttempt" class="page-description form-description">Retrying keeps the same idempotency key and refund details.</p>
      <div class="form-actions"><button v-if="!refundAttempt" class="button button--secondary" type="button" @click="refundingOrder = null">Cancel</button><button class="button" type="submit" :disabled="busy">{{ busy ? 'Sending…' : refundAttempt ? 'Retry same refund' : 'Submit refund' }}</button></div>
    </form>
  </section>
  <section class="card">
    <div class="card-head"><span class="card-title">Recent orders</span><button class="text-button" type="button" @click="loadSection(page)">Refresh</button></div>
    <div v-if="loading" class="loading">Loading orders…</div>
    <div v-else-if="rows.length === 0" class="empty-state">No orders found.</div>
    <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Total / refunded</th><th>Payment</th><th>Fulfillment</th><th>Update status</th><th></th></tr></thead><tbody>
      <tr v-for="order in rows" :key="order.id">
        <td><button class="text-button primary-cell" type="button" @click="openOrder(order)">{{ order.order_number }}</button><div class="secondary-cell">{{ new Date(order.created_at).toLocaleString() }}</div></td>
        <td>{{ order.customer_email || 'Guest' }}</td>
        <td><div class="primary-cell">{{ money(order.total_cents, order.currency) }}</div><div class="secondary-cell">Refunded {{ money(order.refunded_total_cents, order.currency) }}</div></td>
        <td><span class="status-pill" :class="`status-pill--${order.payment_status}`">{{ order.payment_status.replaceAll('_', ' ') }}</span></td>
        <td><span class="status-pill">{{ order.status }}</span></td>
        <td><select class="search-input order-status-select" :value="order.status" :disabled="busy || orderStatusOptions(order).length === 1" aria-label="Update order status" @change="updateOrderStatus(order, $event.target.value)"><option v-for="status in orderStatusOptions(order)" :key="status" :value="status">{{ status }}</option></select></td>
        <td><button v-if="order.payment_status === 'succeeded' && order.total_cents > order.refunded_total_cents" class="text-button" type="button" :disabled="busy || (refundAttempt && refundAttempt.orderId !== order.id)" @click="startRefund(order)">Refund</button></td>
      </tr>
    </tbody></table></div>
    <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage" @click="loadSection(page + 1)">Next</button></div></div>
  </section>
</template>
