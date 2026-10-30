<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


  <template>
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
</template>
