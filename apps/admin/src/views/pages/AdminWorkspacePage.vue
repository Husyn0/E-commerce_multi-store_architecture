<script setup>
import { computed, provide, reactive, ref, watch } from 'vue'
import OverviewSection from '../../sections/OverviewSection.vue'
import ProductsSection from '../../sections/ProductsSection.vue'
import CategoriesSection from '../../sections/CategoriesSection.vue'
import OrdersSection from '../../sections/OrdersSection.vue'
import CustomersSection from '../../sections/CustomersSection.vue'
import SettingsSection from '../../sections/SettingsSection.vue'
import PaymentsSection from '../../sections/PaymentsSection.vue'
import VerificationSection from '../../sections/VerificationSection.vue'
import AppearanceSection from '../../sections/AppearanceSection.vue'
import AuditSection from '../../sections/AuditSection.vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AdminFeedback from '../../components/admin/AdminFeedback.vue'
import { adminSections } from '../../sections/adminSections'

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
const themeForm = reactive({
  colors: {
    primary: '#6b5b4b',
    secondary: '#f0ece7',
    accent: '#aa7d52',
    background: '#ffffff',
    text: '#242323',
  },
  fonts: { heading: 'Georgia', body: 'Arial' },
  logo_url: '',
  layout_variant: 'classic',
})
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
  media_files: [],
  images: [],
})
const categoryForm = reactive({ id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true, icon: '', image_url: '', image_file: null, remove_image: false })
const settingsForm = reactive({
  name: '',
  default_locale: '',
  require_email_verification: true,
  require_phone_verification: false,
})
const title = computed(() => adminSections[section.value]?.[0] || 'Admin')
const description = computed(() => adminSections[section.value]?.[1] || '')
const previewColors = computed(() => themeForm.colors)
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
      const config = response.theme_config || {}
      Object.assign(themeForm.colors, config.colors || {})
      Object.assign(themeForm.fonts, config.fonts || {})
      themeForm.logo_url = config.logo_url || ''
      themeForm.layout_variant = config.layout_variant || 'classic'
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
    media_files: [],
    images: product.images || [],
  })
  showProductForm.value = true
}

function newProduct() {
  Object.assign(productForm, {
    id: '', title: '', slug: '', description: '', sku: '', price_cents: 0,
    compare_at_price_cents: '', category_id: '', is_active: true, published_at: '', media_files: [], images: [],
  })
  showProductForm.value = true
}

function newCategory() {
  Object.assign(categoryForm, { id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true, icon: '', image_url: '', image_file: null, remove_image: false })
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
    icon: category.icon || '',
    image_url: category.image_url || '',
    image_file: null,
    remove_image: false,
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
      const created = await api.post('/admin/products', body)
      productForm.id = created.data?.id || created.id
    }
    for (const file of productForm.media_files) {
      const form = new FormData()
      form.append('file', file)
      await api.upload(`/admin/products/${productForm.id}/images`, form)
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

async function removeProductImage(image) {
  if (!productForm.id || !window.confirm('Remove this product media?')) return
  busy.value = true
  resetFeedback()
  try {
    await api.delete(`/admin/products/${productForm.id}/images/${image.id}`)
    productForm.images = productForm.images.filter((item) => item.id !== image.id)
    notice.value = 'Product media removed.'
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
      icon: categoryForm.icon || null,
    }
    if (categoryForm.remove_image) body.image_url = null
    if (categoryForm.id) {
      await api.patch(`/admin/categories/${categoryForm.id}`, body)
    } else {
      const created = await api.post('/admin/categories', body)
      categoryForm.id = created.data?.id || created.id
    }
    if (categoryForm.image_file) {
      const form = new FormData()
      form.append('image', categoryForm.image_file)
      await api.upload(`/admin/categories/${categoryForm.id}/image`, form)
    }
    Object.assign(categoryForm, { id: '', parent_id: '', name: '', slug: '', position: 0, is_active: true, icon: '', image_url: '', image_file: null, remove_image: false })
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
    await api.patch('/admin/theme', {
      theme_config: {
        colors: { ...themeForm.colors },
        fonts: { ...themeForm.fonts },
        logo_url: themeForm.logo_url.trim() || null,
        layout_variant: themeForm.layout_variant,
      },
    })
    notice.value = 'Theme saved.'
    await loadSection(1, true)
  } catch (requestError) {
    error.value = requestError.message
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

provide('adminWorkspace', {
  route, router, section, busy, loading, error, notice, rows, page, lastPage, settings,
  themeForm, showProductForm, showCategoryForm, refundingOrder, refundAmount,
  refundReason, refundAttempt, categories, stockDrafts, dashboard, selectedOrder,
  selectedCustomer, filters, productForm, categoryForm, settingsForm, title,
  description, previewColors, auditCursor, nextAuditCursor, resetFeedback,
  loadSection, removeProduct, removeProductImage, openOrder, openCustomer, editProduct, newProduct,
  newCategory, editCategory, saveProduct, saveCategory, removeCategory,
  saveSettings, saveTheme, formatLocalDateTime, updateOrderStatus, startRefund,
  submitRefund, updateInventory, toggleCustomer, searchCustomers, filterAudit,
  nextAuditPage, resetAuditFilters, money, formatAddress, productVisibility,
  orderStatusOptions,
})

</script>


<template>
  <AdminPageHeading :title="title" :description="description" :section="section" @add-product="newProduct" @add-category="newCategory" />
  <AdminFeedback :error="error" :notice="notice" />

  <OverviewSection v-if="section === 'dashboard'" />
  <ProductsSection v-if="section === 'products'" />
  <CategoriesSection v-if="section === 'categories'" />
  <OrdersSection v-if="section === 'orders'" />
  <CustomersSection v-if="section === 'customers'" />
  <SettingsSection v-if="section === 'settings'" />
  <PaymentsSection v-if="section === 'payments'" />
  <VerificationSection v-if="section === 'verification'" />
  <AppearanceSection v-if="section === 'appearance'" />
  <AuditSection v-if="section === 'audit'" />
</template>


