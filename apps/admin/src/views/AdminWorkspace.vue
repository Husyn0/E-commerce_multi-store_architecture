<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const section = computed(() => route.meta.section)
const busy = ref(false)
const loading = ref(false)
const error = ref('')
const notice = ref('')
const rows = ref([])
const page = ref(1)
const lastPage = ref(1)
const settings = ref(null)
const themeText = ref('')
const showProductForm = ref(false)
const showCategoryForm = ref(false)
const refundingOrder = ref(null)
const refundAmount = ref('')
const refundReason = ref('')
const refundAttempt = ref(null)
const categories = ref([])
const stockDrafts = reactive({})
const dashboard = ref(null)
const selectedOrder = ref(null)
const selectedCustomer = ref(null)
const filters = reactive({
  q: '',
  status: '',
  source: '',
  action: '',
  payment_status: '',
  entity_type: '',
  actor_id: '',
  from: '',
  to: '',
})
const productForm = reactive({
  id: '',
  title: '',
  slug: '',
  description: '',
  sku: '',
  price_cents: 0,
  compare_at_price_cents: '',
  category_id: '',
  is_active: true,
  published_at: '',
})
const categoryForm = reactive({ id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true })
const settingsForm = reactive({
  name: '',
  default_locale: '',
  require_email_verification: true,
  require_phone_verification: false,
})
const pageInfo = {
  dashboard: ['Overview', 'A quick view of your store activity.'],
  products: ['Products', 'Manage the catalog for this tenant.'],
  categories: ['Categories', 'Organize products into storefront categories.'],
  orders: ['Orders', 'Review orders and update their fulfillment status.'],
  customers: ['Customers', 'Search and manage customer accounts for this store.'],
  settings: ['General settings', 'Update tenant-owned settings. Driver and status controls are CLI-managed.'],
  payments: ['Payments', 'Payment configuration is managed by the operator.'],
  verification: ['Verification', 'Review verification requirements and delivery configuration.'],
  appearance: ['Appearance', 'Configure the storefront theme.'],
  audit: ['Audit log', 'Review tenant-scoped administrative and system activity.'],
}

const title = computed(() => pageInfo[section.value]?.[0] || 'Admin')
const description = computed(() => pageInfo[section.value]?.[1] || '')
const previewColors = computed(() => {
  try {
    const config = JSON.parse(themeText.value)
    return {
      primary: config.colors?.primary || '#6b5b4b',
      secondary: config.colors?.secondary || '#f0ece7',
      accent: config.colors?.accent || '#aa7d52',
      background: config.colors?.background || '#ffffff',
      text: config.colors?.text || '#242323',
    }
  } catch {
    return {
      primary: '#6b5b4b',
      secondary: '#f0ece7',
      accent: '#aa7d52',
      background: '#ffffff',
      text: '#242323',
    }
  }
})
const auditCursor = ref(null)
const nextAuditCursor = ref(null)

function resetFeedback() {
  error.value = ''
  notice.value = ''
}

async function loadSection(nextPage = 1, preserveFeedback = false) {
  if (!preserveFeedback) resetFeedback()
  rows.value = []
  page.value = nextPage
  loading.value = true

  try {
    if (section.value === 'dashboard') {
      dashboard.value = await api.get('/admin/dashboard')
    } else if (section.value === 'products') {
      const [response, categoryResponse] = await Promise.all([
        api.get('/admin/products', { query: { per_page: 20, page: nextPage } }),
        api.get('/admin/categories', { query: { per_page: 100 } }),
      ])
      rows.value = response.data || []
      categories.value = categoryResponse.data || []
      for (const product of rows.value) {
        stockDrafts[product.id] = product.stock
        for (const variant of product.variants || []) {
          stockDrafts[variant.id] = variant.stock
        }
      }
      page.value = response.meta?.current_page || nextPage
      lastPage.value = response.meta?.last_page || 1
    } else if (section.value === 'categories') {
      const response = await api.get('/admin/categories', { query: { page: nextPage } })
      rows.value = response.data || []
      page.value = response.meta?.current_page || nextPage
      lastPage.value = response.meta?.last_page || 1
    } else if (section.value === 'orders') {
      const response = await api.get('/admin/orders', {
        query: {
          per_page: 25,
          page: nextPage,
          status: filters.status,
          payment_status: filters.payment_status,
        },
      })
      rows.value = response.data || []
      page.value = response.meta?.current_page || nextPage
      lastPage.value = response.meta?.last_page || 1
    } else if (section.value === 'customers') {
      const response = await api.get('/admin/customers', {
        query: { q: filters.q, status: filters.status, per_page: 25, page: nextPage },
      })
      rows.value = response.data || []
      page.value = response.meta?.current_page || nextPage
      lastPage.value = response.meta?.last_page || 1
    } else if (['settings', 'payments', 'verification'].includes(section.value)) {
      const [context, tenantSettings] = await Promise.all([
        api.get('/admin/tenant', { authenticated: false }),
        api.get('/admin/settings'),
      ])
      settings.value = tenantSettings
      settings.value.tenant.status = context.status
      if (section.value === 'settings') {
        Object.assign(settingsForm, {
          name: settings.value.tenant.name,
          default_locale: settings.value.tenant.default_locale,
          require_email_verification: settings.value.settings.require_email_verification,
          require_phone_verification: settings.value.settings.require_phone_verification,
        })
      }
    } else if (section.value === 'appearance') {
      const response = await api.get('/admin/theme')
      themeText.value = JSON.stringify(response.theme_config || {}, null, 2)
    } else if (section.value === 'audit') {
      const response = await api.get('/admin/audit-logs', {
        query: {
          source: filters.source,
          action: filters.action,
          entity_type: filters.entity_type,
          actor_id: filters.actor_id,
          from: filters.from ? `${filters.from}T00:00:00.000Z` : '',
          to: filters.to ? `${filters.to}T23:59:59.999Z` : '',
          cursor: auditCursor.value,
          limit: 25,
        },
      })
      rows.value = response.data || []
      nextAuditCursor.value = response.next_cursor
    }
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    loading.value = false
  }
}

async function removeProduct(product) {
  if (!window.confirm(`Delete "${product.title}"? Products with cart or order history must be deactivated instead.`)) return
  busy.value = true
  resetFeedback()
  try {
    await api.delete(`/admin/products/${product.id}`)
    await loadSection(1, true)
    notice.value = 'Product deleted.'
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function openOrder(order) {
  busy.value = true
  resetFeedback()
  try {
    const response = await api.get(`/admin/orders/${order.id}`)
    selectedOrder.value = response.data || response
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function openCustomer(customer) {
  busy.value = true
  resetFeedback()
  try {
    const response = await api.get(`/admin/customers/${customer.id}`)
    selectedCustomer.value = response.data || response
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

function editProduct(product) {
  Object.assign(productForm, {
    id: product.id,
    title: product.title,
    slug: product.slug,
    description: product.description,
    sku: product.sku || '',
    price_cents: product.price_cents,
    compare_at_price_cents: product.compare_at_price_cents ?? '',
    category_id: product.category_id || '',
    is_active: product.is_active,
    published_at: product.published_at || '',
  })
  showProductForm.value = true
}

function newProduct() {
  Object.assign(productForm, {
    id: '', title: '', slug: '', description: '', sku: '', price_cents: 0,
    compare_at_price_cents: '', category_id: '', is_active: true, published_at: '',
  })
  showProductForm.value = true
}

function newCategory() {
  Object.assign(categoryForm, { id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true })
  showCategoryForm.value = true
}

function editCategory(category) {
  Object.assign(categoryForm, {
    id: category.id,
    parent_id: category.parent_id || '',
    name: category.name,
    slug: category.slug,
    position: category.position,
    is_active: category.is_active,
  })
  showCategoryForm.value = true
}

async function saveProduct() {
  busy.value = true
  resetFeedback()
  try {
    const body = {
      title: productForm.title,
      slug: productForm.slug,
      description: productForm.description,
      sku: productForm.sku || null,
      price_cents: Number(productForm.price_cents),
      compare_at_price_cents: productForm.compare_at_price_cents === '' ? null : Number(productForm.compare_at_price_cents),
      category_id: productForm.category_id || null,
      is_active: productForm.is_active,
      published_at: productForm.published_at
        ? new Date(productForm.published_at).toISOString()
        : null,
    }
    if (productForm.id) {
      await api.patch(`/admin/products/${productForm.id}`, body)
    } else {
      await api.post('/admin/products', body)
    }
    showProductForm.value = false
    notice.value = 'Product saved.'
    await loadSection(page.value, true)
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function saveCategory() {
  busy.value = true
  resetFeedback()
  const updating = Boolean(categoryForm.id)
  try {
    const body = {
      parent_id: categoryForm.parent_id || null,
      name: categoryForm.name,
      slug: categoryForm.slug,
      position: Number(categoryForm.position),
      is_active: categoryForm.is_active,
    }
    if (categoryForm.id) {
      await api.patch(`/admin/categories/${categoryForm.id}`, body)
    } else {
      await api.post('/admin/categories', body)
    }
    Object.assign(categoryForm, { id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true })
    showCategoryForm.value = false
    notice.value = updating ? 'Category updated.' : 'Category created.'
    await loadSection(1, true)
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function removeCategory(category) {
  if (!window.confirm(`Delete category "${category.name}"?`)) return
  busy.value = true
  resetFeedback()
  try {
    await api.delete(`/admin/categories/${category.id}`)
    await loadSection(1, true)
    notice.value = 'Category deleted.'
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function saveSettings() {
  busy.value = true
  resetFeedback()
  try {
    await api.patch('/admin/settings', {
      name: settingsForm.name,
      default_locale: settingsForm.default_locale,
      require_email_verification: settingsForm.require_email_verification,
      require_phone_verification: settingsForm.require_phone_verification,
    })
    notice.value = 'General settings saved.'
    await loadSection(1, true)
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function saveTheme() {
  busy.value = true
  resetFeedback()
  try {
    const themeConfig = JSON.parse(themeText.value)
    await api.patch('/admin/theme', { theme_config: themeConfig })
    notice.value = 'Theme saved.'
    await loadSection(1, true)
  } catch (requestError) {
    error.value = requestError instanceof SyntaxError
      ? 'Theme configuration must be valid JSON.'
      : requestError.message
  } finally {
    busy.value = false
  }
}

function formatLocalDateTime(value) {
  if (!value) return ''
  const date = new Date(value)
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
}

async function updateOrderStatus(order, status) {
  if (!status || status === order.status) return
  busy.value = true
  resetFeedback()
  try {
    await api.patch(`/admin/orders/${order.id}/status`, { status })
    notice.value = `Order ${order.order_number} updated.`
    await loadSection(page.value, true)
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

function startRefund(order) {
  if (refundAttempt.value && refundAttempt.value.orderId !== order.id) return
  if (refundAttempt.value?.orderId === order.id) {
    refundingOrder.value = order
    return
  }
  refundingOrder.value = order
  refundAmount.value = String(order.total_cents - order.refunded_total_cents)
  refundReason.value = ''
}

async function submitRefund() {
  const order = refundingOrder.value
  const amount = Number(refundAmount.value)
  if (!order || !Number.isInteger(amount) || amount < 1) {
    error.value = 'Enter a positive refund amount in minor currency units.'
    return
  }

  busy.value = true
  resetFeedback()
  const reason = refundReason.value.trim()
  if (!refundAttempt.value) {
    refundAttempt.value = {
      orderId: order.id,
      amount,
      reason,
      key: crypto.randomUUID(),
    }
  }

  try {
    const result = await api.post(`/admin/orders/${order.id}/refunds`, {
      amount_cents: amount,
      reason: reason || null,
    }, {
      headers: { 'Idempotency-Key': refundAttempt.value.key },
    })
    refundingOrder.value = null
    refundAttempt.value = null
    await loadSection(page.value, true)
    notice.value = `Refund request ${result.status}.`
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function updateInventory(product, variant = null) {
  const recordId = variant?.id || product.id
  const stock = Number(stockDrafts[recordId])
  if (!Number.isInteger(stock) || stock < 0) {
    error.value = 'Stock must be a non-negative whole number.'
    return
  }

  busy.value = true
  resetFeedback()
  try {
    const path = variant
      ? `/admin/products/${product.id}/variants/${variant.id}/inventory`
      : `/admin/products/${product.id}/inventory`
    await api.patch(path, { stock })
    await loadSection(page.value, true)
    notice.value = `${variant ? variant.name : product.title} stock updated.`
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

async function toggleCustomer(customer) {
  busy.value = true
  resetFeedback()
  const status = customer.status === 'active' ? 'suspended' : 'active'
  try {
    await api.patch(`/admin/customers/${customer.id}/status`, { status })
    notice.value = `Customer status changed to ${status}.`
    await loadSection(page.value, true)
  } catch (requestError) {
    error.value = requestError.message
  } finally {
    busy.value = false
  }
}

function searchCustomers() {
  loadSection(1)
}

function filterAudit() {
  auditCursor.value = null
  loadSection(1)
}

function nextAuditPage() {
  auditCursor.value = nextAuditCursor.value
  loadSection(1)
}

function resetAuditFilters() {
  Object.assign(filters, {
    action: '',
    entity_type: '',
    actor_id: '',
    source: '',
    from: '',
    to: '',
  })
  filterAudit()
}

function money(cents, currency) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format((cents || 0) / 100)
}

function formatAddress(address) {
  if (!address || typeof address !== 'object') return '—'
  return [
    address.recipient_name,
    address.line1,
    address.line2,
    address.city,
    address.region,
    address.postal_code,
    address.country_code,
  ].filter(Boolean).join(', ') || '—'
}

function productVisibility(product) {
  if (!product.is_active) return 'inactive'
  if (!product.published_at) return 'draft'
  if (new Date(product.published_at).getTime() > Date.now()) return 'scheduled'
  return 'published'
}

function orderStatusOptions(order) {
  const transitions = {
    pending: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered'],
    delivered: [],
    cancelled: [],
  }
  const next = transitions[order.status] || []
  const paid = ['succeeded', 'refunded'].includes(order.payment_status)
  return [order.status, ...next.filter((status) => status !== 'cancelled' || !paid)]
}

watch(section, () => {
  showProductForm.value = false
  showCategoryForm.value = false
  refundingOrder.value = null
  selectedOrder.value = null
  selectedCustomer.value = null
  auditCursor.value = null
  dashboard.value = null
  loadSection()
}, { immediate: true })
</script>

<template>
  <div class="page-heading">
    <div>
      <p class="eyebrow">Store workspace</p>
      <h1>{{ title }}</h1>
      <p class="page-description">{{ description }}</p>
    </div>
    <button v-if="section === 'products'" class="button" type="button" @click="newProduct">Add product</button>
    <button v-if="section === 'categories'" class="button" type="button" @click="newCategory">Add category</button>
  </div>

  <div v-if="error" class="error-box feedback-message" role="alert">{{ error }}</div>
  <div v-if="notice" class="success-box feedback-message" role="status">{{ notice }}</div>

  <template v-if="section === 'dashboard'">
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
      <section class="card dashboard-card">
        <div class="card-head">
          <div><span class="card-title">Sales trend</span><p class="metric-foot">Last 7 calendar days · {{ dashboard.period.timezone }} · {{ dashboard.period.currency }}</p></div>
          <button class="text-button" type="button" @click="loadSection()">Refresh</button>
        </div>
        <div class="sales-chart">
          <div v-for="day in dashboard.sales.by_day" :key="day.date" class="sales-day">
            <div class="sales-bar-track"><div class="sales-bar" :style="{ height: `${Math.max(4, dashboard.sales.total_cents ? day.total_cents / Math.max(...dashboard.sales.by_day.map((entry) => entry.total_cents), 1) * 100 : 4)}%` }" :title="money(day.total_cents, dashboard.period.currency)" /></div>
            <span class="sales-day-label">{{ new Date(`${day.date}T12:00:00Z`).toLocaleDateString(undefined, { weekday: 'short', timeZone: 'UTC' }) }}</span>
          </div>
        </div>
        <div class="period-note">From {{ new Date(dashboard.period.from).toLocaleString(undefined, { timeZone: 'UTC' }) }} to {{ new Date(dashboard.period.to).toLocaleString(undefined, { timeZone: 'UTC' }) }} (UTC). Only `payment_status=succeeded` and `status!=cancelled` orders contribute to sales.</div>
      </section>
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

  <template v-else-if="section === 'products'">
    <p class="notice product-capability-note">Product and category data is tenant-scoped. Inventory can be adjusted here. Product image upload and variant create/edit are not exposed by the current API; existing variant stock can still be managed.</p>
    <section v-if="showProductForm" class="card form-card">
      <div class="card-head"><span class="card-title">{{ productForm.id ? 'Edit product' : 'New product' }}</span></div>
      <form class="card-body" @submit.prevent="saveProduct">
        <div class="form-grid">
          <div class="field"><label for="product-title">Title</label><input id="product-title" v-model.trim="productForm.title" maxlength="240" required /></div>
          <div class="field"><label for="product-slug">Slug</label><input id="product-slug" v-model.trim="productForm.slug" maxlength="240" required /></div>
          <div class="field"><label for="product-sku">SKU</label><input id="product-sku" v-model.trim="productForm.sku" maxlength="100" /></div>
          <div class="field"><label for="product-price">Price (minor currency units)</label><input id="product-price" v-model.number="productForm.price_cents" type="number" min="0" step="1" required /></div>
          <div class="field"><label for="product-compare">Compare-at price (minor units)</label><input id="product-compare" v-model="productForm.compare_at_price_cents" type="number" min="0" step="1" /></div>
          <div class="field"><label for="product-category">Category</label><select id="product-category" v-model="productForm.category_id"><option value="">Uncategorized</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
          <div class="field"><label for="product-published-at">Publish date/time</label><input id="product-published-at" :value="formatLocalDateTime(productForm.published_at)" type="datetime-local" @input="productForm.published_at = $event.target.value ? new Date($event.target.value).toISOString() : ''" /><small>Leave blank to keep this product unpublished.</small></div>
          <div class="field field--wide"><label for="product-description">Description</label><textarea id="product-description" v-model="productForm.description" required /></div>
          <label class="checkbox-field"><input v-model="productForm.is_active" type="checkbox" /> Active in catalog</label>
        </div>
        <div class="form-actions"><button class="button button--secondary" type="button" @click="showProductForm = false">Cancel</button><button class="button" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save product' }}</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><span class="card-title">Product catalog</span><button class="text-button" type="button" @click="loadSection(page)">Refresh</button></div>
      <div v-if="loading" class="loading">Loading products…</div>
      <div v-else-if="rows.length === 0" class="empty-state">No products in this tenant yet.</div>
      <div v-else class="table-scroll"><table class="data-table">
        <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody><tr v-for="product in rows" :key="product.id">
          <td><div class="primary-cell">{{ product.title }}</div><div class="secondary-cell">/{{ product.slug }}</div></td>
          <td>{{ product.sku || '—' }}</td><td>{{ money(product.price_cents, product.currency) }}</td>
          <td>
            <template v-if="product.variants?.length">
              <div v-for="variant in product.variants" :key="variant.id" class="inventory-line">
                <span>{{ variant.name }}</span>
                <input v-model.number="stockDrafts[variant.id]" class="search-input inventory-input" type="number" min="0" aria-label="Variant stock" />
                <button class="text-button" type="button" :disabled="busy" @click="updateInventory(product, variant)">Save</button>
              </div>
            </template>
            <form v-else class="inventory-line" @submit.prevent="updateInventory(product)">
              <input v-model.number="stockDrafts[product.id]" class="search-input inventory-input" type="number" min="0" :aria-label="`${product.title} stock`" />
              <button class="text-button" type="submit" :disabled="busy">Save</button>
            </form>
          </td>
          <td><span class="status-pill" :class="`status-pill--${productVisibility(product)}`">{{ productVisibility(product) }}</span></td>
          <td><div class="inline-actions"><button class="text-button" type="button" @click="editProduct(product)">Edit</button><button class="text-button text-button--danger" type="button" :disabled="busy" @click="removeProduct(product)">Delete</button></div></td>
        </tr></tbody>
      </table></div>
      <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1 || loading" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage || loading" @click="loadSection(page + 1)">Next</button></div></div>
    </section>
  </template>

  <template v-else-if="section === 'categories'">
    <section v-if="showCategoryForm" class="card form-card">
      <div class="card-head"><span class="card-title">{{ categoryForm.id ? 'Edit category' : 'New category' }}</span></div>
      <form class="card-body" @submit.prevent="saveCategory">
        <div class="form-grid">
          <div class="field"><label for="category-parent">Parent category</label><select id="category-parent" v-model="categoryForm.parent_id"><option value="">No parent</option><option v-for="category in rows" :key="category.id" :value="category.id" :disabled="category.id === categoryForm.id">{{ category.name }}</option></select></div>
          <div class="field"><label for="category-name">Name</label><input id="category-name" v-model.trim="categoryForm.name" maxlength="160" required /></div>
          <div class="field"><label for="category-slug">Slug</label><input id="category-slug" v-model.trim="categoryForm.slug" maxlength="180" required /></div>
          <div class="field"><label for="category-position">Position</label><input id="category-position" v-model.number="categoryForm.position" type="number" min="0" /></div>
          <label class="checkbox-field"><input v-model="categoryForm.is_active" type="checkbox" /> Active</label>
        </div>
        <div class="form-actions"><button class="button button--secondary" type="button" @click="showCategoryForm = false">Cancel</button><button class="button" :disabled="busy">{{ categoryForm.id ? 'Save category' : 'Create category' }}</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><span class="card-title">Categories</span></div>
      <div v-if="loading" class="loading">Loading categories…</div>
      <div v-else-if="rows.length === 0" class="empty-state">No categories created.</div>
      <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Category</th><th>Slug</th><th>Position</th><th>Status</th><th></th></tr></thead><tbody>
        <tr v-for="category in rows" :key="category.id"><td class="primary-cell">{{ category.name }}</td><td>{{ category.slug }}</td><td>{{ category.position }}</td><td><span class="status-pill" :class="`status-pill--${category.is_active ? 'active' : 'suspended'}`">{{ category.is_active ? 'active' : 'inactive' }}</span></td><td><div class="inline-actions"><button class="text-button" type="button" @click="editCategory(category)">Edit</button><button class="text-button text-button--danger" type="button" :disabled="busy" @click="removeCategory(category)">Delete</button></div></td></tr>
      </tbody></table></div>
      <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage" @click="loadSection(page + 1)">Next</button></div></div>
    </section>
  </template>

  <template v-else-if="section === 'orders'">
  <div class="order-filters">
    <label class="filter-label">Fulfillment
      <select v-model="filters.status" class="search-input"><option value="">All statuses</option><option v-for="status in ['pending','processing','shipped','delivered','cancelled']" :key="status" :value="status">{{ status }}</option></select>
    </label>
    <label class="filter-label">Payment
      <select v-model="filters.payment_status" class="search-input"><option value="">All payments</option><option v-for="status in ['requires_payment','processing','succeeded','failed','refunded']" :key="status" :value="status">{{ status.replaceAll('_', ' ') }}</option></select>
    </label>
    <button class="button button--secondary" type="button" @click="loadSection(1)">Apply filters</button>
    <button class="text-button" type="button" @click="filters.status = ''; filters.payment_status = ''; loadSection(1)">Clear</button>
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

  <section v-else-if="section === 'customers'" class="card">
    <div v-if="selectedCustomer" class="customer-detail">
      <div class="card-head"><span class="card-title">Customer details</span><button class="button button--secondary button--small" type="button" @click="selectedCustomer = null">Close</button></div>
      <div class="card-body">
        <div class="detail-list">
          <div class="detail-item"><div class="detail-label">Name</div><div class="detail-value">{{ [selectedCustomer.first_name, selectedCustomer.last_name].filter(Boolean).join(' ') || '—' }}</div></div>
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">{{ selectedCustomer.email }}</div></div>
          <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">{{ selectedCustomer.phone || '—' }}</div></div>
          <div class="detail-item"><div class="detail-label">Account status</div><div class="detail-value">{{ selectedCustomer.status }}</div></div>
          <div class="detail-item"><div class="detail-label">Email verified</div><div class="detail-value">{{ selectedCustomer.email_verified_at ? new Date(selectedCustomer.email_verified_at).toLocaleString() : 'Not verified' }}</div></div>
          <div class="detail-item"><div class="detail-label">Phone verified</div><div class="detail-value">{{ selectedCustomer.phone_verified_at ? new Date(selectedCustomer.phone_verified_at).toLocaleString() : 'Not verified' }}</div></div>
          <div class="detail-item"><div class="detail-label">Customer since</div><div class="detail-value">{{ new Date(selectedCustomer.created_at).toLocaleString() }}</div></div>
        </div>
      </div>
    </div>
    <div class="card-head"><span class="card-title">Customer accounts</span><div class="filter-row">
      <input v-model="filters.q" class="search-input" placeholder="Search name, email or phone" @keyup.enter="searchCustomers" />
      <select v-model="filters.status" class="search-input status-filter" @change="searchCustomers"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="deleted">Deleted</option></select>
      <button class="button button--secondary" @click="searchCustomers">Search</button>
    </div></div>
    <div v-if="loading" class="loading">Loading customers…</div>
    <div v-else-if="rows.length === 0" class="empty-state">No matching customers.</div>
    <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Customer</th><th>Phone</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="customer in rows" :key="customer.id"><td><button class="text-button primary-cell" type="button" @click="openCustomer(customer)">{{ [customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email }}</button><div class="secondary-cell">{{ customer.email }}</div></td><td>{{ customer.phone || '—' }}</td><td>{{ new Date(customer.created_at).toLocaleDateString() }}</td><td><span class="status-pill" :class="`status-pill--${customer.status}`">{{ customer.status }}</span></td><td><button v-if="customer.status !== 'deleted'" class="text-button" type="button" :disabled="busy" @click="toggleCustomer(customer)">{{ customer.status === 'active' ? 'Suspend' : 'Reactivate' }}</button></td></tr>
    </tbody></table></div>
    <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage" @click="loadSection(page + 1)">Next</button></div></div>
  </section>

  <template v-else-if="section === 'settings'">
    <div v-if="loading" class="loading">Loading settings…</div>
    <section v-else-if="settings" class="card">
      <div class="card-head"><span class="card-title">Store preferences</span></div>
      <form class="card-body" @submit.prevent="saveSettings">
        <div class="form-grid">
          <div class="field"><label for="tenant-name">Store name</label><input id="tenant-name" v-model.trim="settingsForm.name" maxlength="160" required /></div>
          <div class="field"><label for="tenant-locale">Default locale</label><input id="tenant-locale" v-model.trim="settingsForm.default_locale" maxlength="5" pattern="[a-z]{2}(-[A-Z]{2})?" required /><small>Examples: en, en-US</small></div>
          <div class="field"><label>Default currency</label><input :value="settings.tenant.default_currency" readonly /><small>Currency is immutable after catalog setup.</small></div>
          <div class="field"><label>Tenant status</label><input :value="settings.tenant.status || 'See tenant status above'" readonly /><small>Status is managed by the CLI.</small></div>
          <label class="checkbox-field"><input v-model="settingsForm.require_email_verification" type="checkbox" /> Require email verification</label>
          <label class="checkbox-field"><input v-model="settingsForm.require_phone_verification" type="checkbox" /> Require phone verification</label>
        </div>
        <div class="form-actions"><button class="button" :disabled="busy">{{ busy ? 'Saving…' : 'Save settings' }}</button></div>
      </form>
    </section>
  </template>

  <section v-else-if="section === 'payments'" class="card">
    <div class="card-head"><span class="card-title">Payment configuration</span><span class="status-pill">Read only</span></div>
    <div v-if="loading" class="loading">Loading provider settings…</div>
    <div v-else-if="settings" class="card-body">
      <div class="detail-list">
        <div class="detail-item"><div class="detail-label">Payment driver</div><div class="detail-value">{{ settings.settings.payment_driver }}</div></div>
        <div class="detail-item"><div class="detail-label">Tenant connected account</div><div class="detail-value">{{ settings.settings.stripe_account_id_masked || 'Not configured' }}</div></div>
        <div class="detail-item"><div class="detail-label">Email provider</div><div class="detail-value">{{ settings.settings.email_provider }}</div></div>
        <div class="detail-item"><div class="detail-label">SMS provider</div><div class="detail-value">{{ settings.settings.sms_provider }}</div></div>
      </div>
      <p class="notice section-notice">Provider and connected-account settings are operator-managed. This screen intentionally has no edit controls.</p>
    </div>
  </section>

  <section v-else-if="section === 'verification'" class="card">
    <div class="card-head"><span class="card-title">Verification configuration</span><span class="status-pill">Read only</span></div>
    <div v-if="loading" class="loading">Loading verification settings…</div>
    <div v-else-if="settings" class="card-body">
      <div class="detail-list">
        <div class="detail-item"><div class="detail-label">Email verification</div><div class="detail-value">{{ settings.settings.require_email_verification ? 'Required' : 'Optional' }}</div></div>
        <div class="detail-item"><div class="detail-label">Phone verification</div><div class="detail-value">{{ settings.settings.require_phone_verification ? 'Required' : 'Optional' }}</div></div>
        <div class="detail-item"><div class="detail-label">Code verification driver</div><div class="detail-value">{{ settings.settings.verification_driver }}</div></div>
        <div class="detail-item"><div class="detail-label">Human-check driver</div><div class="detail-value">{{ settings.settings.human_check_driver }}</div></div>
      </div>
      <p class="notice section-notice">Driver settings are read-only and changed through the approved tenant CLI workflow.</p>
    </div>
  </section>

  <section v-else-if="section === 'appearance'" class="card">
    <div class="card-head"><span class="card-title">Storefront theme</span><span class="status-pill">Tenant scoped</span></div>
    <div v-if="loading" class="loading">Loading theme…</div>
    <form v-else class="card-body" @submit.prevent="saveTheme">
      <p class="page-description theme-description">Edit the supported theme JSON (colors, fonts, logo_url, layout_variant). Invalid shapes are rejected by the API.</p>
      <div class="theme-editor-grid">
        <div class="field"><label for="theme-json">Theme configuration</label><textarea id="theme-json" v-model="themeText" class="theme-editor" spellcheck="false" rows="18" /></div>
        <div class="theme-preview" :style="{
          '--theme-primary': previewColors.primary,
          '--theme-secondary': previewColors.secondary,
          '--theme-accent': previewColors.accent,
          '--theme-background': previewColors.background,
          '--theme-text': previewColors.text,
        }">
          <div class="theme-preview-head"><span class="theme-preview-logo">V</span><span>Storefront preview</span><span class="preview-dot" /></div>
          <div class="theme-preview-content">
            <p class="preview-eyebrow">A LITTLE SOMETHING SPECIAL</p>
            <h2>Find your<br />everyday favorite.</h2>
            <p>A considered collection, made for the way you live.</p>
            <button type="button" class="preview-button">Explore the collection <span>→</span></button>
          </div>
          <div class="theme-preview-swatch-row"><span v-for="(value, key) in previewColors" :key="key" class="theme-swatch" :title="`${key}: ${value}`" :style="{ backgroundColor: value }" /></div>
        </div>
      </div>
      <div class="form-actions"><button class="button" :disabled="busy">{{ busy ? 'Saving…' : 'Save theme' }}</button></div>
    </form>
  </section>

  <section v-else-if="section === 'audit'" class="card">
    <div class="card-head"><span class="card-title">Audit history</span><button class="text-button" type="button" @click="loadSection()">Refresh</button></div>
    <div class="audit-filters">
      <div class="field"><label for="audit-action">Action</label><input id="audit-action" v-model.trim="filters.action" class="search-input" placeholder="Exact action" /></div>
      <div class="field"><label for="audit-entity">Entity type</label><input id="audit-entity" v-model.trim="filters.entity_type" class="search-input" placeholder="product, order…" /></div>
      <div class="field"><label for="audit-actor">Actor UUID</label><input id="audit-actor" v-model.trim="filters.actor_id" class="search-input" placeholder="Optional" /></div>
      <div class="field"><label for="audit-source">Source</label><select id="audit-source" v-model="filters.source" class="search-input"><option value="">All sources</option><option value="web">Web</option><option value="cli">CLI</option><option value="webhook">Webhook</option></select></div>
      <div class="field"><label for="audit-from">From (UTC)</label><input id="audit-from" v-model="filters.from" class="search-input" type="date" /></div>
      <div class="field"><label for="audit-to">To (UTC)</label><input id="audit-to" v-model="filters.to" class="search-input" type="date" /></div>
      <div class="filter-actions"><button class="button" type="button" @click="filterAudit">Apply filters</button><button class="button button--secondary" type="button" @click="resetAuditFilters">Clear</button></div>
    </div>
    <div v-if="loading" class="loading">Loading audit events…</div>
    <div v-else-if="rows.length === 0" class="empty-state">No audit events match these filters.</div>
    <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Action</th><th>Entity</th><th>Actor</th><th>Source / host</th><th>Time (UTC)</th></tr></thead><tbody>
      <tr v-for="event in rows" :key="event.id"><td class="primary-cell">{{ event.action }}</td><td>{{ event.entity_type }}<div class="secondary-cell">{{ event.entity_id }}</div></td><td>{{ event.actor?.email || 'System / CLI' }}</td><td><span class="status-pill">{{ event.source }}</span><div class="secondary-cell">{{ event.host_type || '—' }}</div></td><td>{{ new Date(event.created_at).toLocaleString(undefined, { timeZone: 'UTC' }) }}</td></tr>
    </tbody></table></div>
    <div class="pagination"><span>Newest events first · Date range is inclusive UTC · tenant scoped</span><button class="button button--secondary button--small" :disabled="!nextAuditCursor || loading" @click="nextAuditPage">Next page</button></div>
  </section>
</template>

<style lang="scss" scoped>
.theme-editor {
  min-height: 340px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  line-height: 1.55;
}

.field small { color: var(--muted); font-size: 10px; }
</style>
