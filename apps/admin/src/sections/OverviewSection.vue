<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


    <div v-if="loading" class="loading">Loading store activity…</div>
    <template v-else-if="dashboard">
      <div class="dashboard-grid">
        <article class="metric-card">
          <div class="metric-label">Paid sales</div>
          <div class="metric-value">{{ money(dashboard.sales.total_cents, dashboard.period.currency) }}</div>
          <div class="metric-foot">Succeeded, non-cancelled orders</div>
        </article>
        <article class="metric-card">
          <div class="metric-label">Paid orders</div>
          <div class="metric-value">{{ dashboard.sales.order_count }}</div>
          <div class="metric-foot">For the selected reporting period</div>
        </article>
        <article class="metric-card">
          <div class="metric-label">Needs fulfillment</div>
          <div class="metric-value">{{ dashboard.pending_fulfillment_count }}</div>
          <div class="metric-foot">Paid orders awaiting shipment</div>
        </article>
      </div>
      <div class="dashboard-charts">
        <section class="card dashboard-card">
          <div class="card-head">
            <div><span class="card-title">Sales trend</span><p class="metric-foot">Paid revenue · {{ dashboard.period.currency }}</p></div>
            <button class="text-button" type="button" @click="loadSection()">Refresh</button>
          </div>
          <div class="sales-chart">
            <div v-for="day in dashboard.sales.by_day" :key="day.date" class="sales-day">
              <div class="sales-bar-track"><div class="sales-bar" :style="{ height: `${Math.max(4, dashboard.sales.total_cents ? day.total_cents / Math.max(...dashboard.sales.by_day.map((entry) => entry.total_cents), 1) * 100 : 4)}%` }" :title="money(day.total_cents, dashboard.period.currency)" /></div>
              <span class="sales-day-label">{{ new Date(`${day.date}T12:00:00Z`).toLocaleDateString(undefined, { weekday: 'short', timeZone: 'UTC' }) }}</span>
            </div>
          </div>
          <div class="chart-total">{{ money(dashboard.sales.total_cents, dashboard.period.currency) }} <span>over 7 days</span></div>
        </section>

        <section class="card dashboard-card">
          <div class="card-head">
            <div><span class="card-title">Order volume</span><p class="metric-foot">Paid orders per day · {{ dashboard.period.timezone }}</p></div>
            <span class="chart-period">7 days</span>
          </div>
          <div class="sales-chart sales-chart--orders">
            <div v-for="day in dashboard.sales.by_day" :key="day.date" class="sales-day">
              <div class="sales-bar-track"><div class="sales-bar sales-bar--orders" :style="{ height: `${Math.max(4, dashboard.sales.order_count ? day.order_count / Math.max(...dashboard.sales.by_day.map((entry) => entry.order_count), 1) * 100 : 4)}%` }" :title="`${day.order_count} paid orders`" /></div>
              <span class="sales-day-label">{{ new Date(`${day.date}T12:00:00Z`).toLocaleDateString(undefined, { weekday: 'short', timeZone: 'UTC' }) }}</span>
            </div>
          </div>
          <div class="chart-total">{{ dashboard.sales.order_count }} <span>paid orders over 7 days</span></div>
        </section>
      </div>
      <p class="period-note">Reporting period: {{ new Date(dashboard.period.from).toLocaleDateString(undefined, { timeZone: 'UTC' }) }} – {{ new Date(dashboard.period.to).toLocaleDateString(undefined, { timeZone: 'UTC' }) }} (UTC). Only succeeded payments from non-cancelled orders are included.</p>
      <section class="card dashboard-card">
        <div class="card-head"><span class="card-title">Recent orders</span><button class="text-button" type="button" @click="router.push({ name: 'orders' })">View all</button></div>
        <div v-if="dashboard.recent_orders.length === 0" class="empty-state">Orders will appear here as customers check out.</div>
        <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Fulfillment</th></tr></thead><tbody>
          <tr v-for="order in dashboard.recent_orders" :key="order.id" class="clickable-row" @click="openOrder(order)">
            <td class="primary-cell">{{ order.order_number }}</td><td>{{ order.customer_email }}</td><td>{{ money(order.total_cents, order.currency) }}</td><td><span class="status-pill" :class="`status-pill--${order.payment_status}`">{{ order.payment_status.replaceAll('_', ' ') }}</span></td><td><span class="status-pill">{{ order.status }}</span></td>
          </tr>
        </tbody></table></div>
      </section>
    </template>
</template>
