<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


    <section v-if="showProductForm" class="card form-card">
      <div class="card-head"><span class="card-title">{{ productForm.id ? 'Edit product' : 'New product' }}</span></div>
      <form class="card-body" @submit.prevent="saveProduct">
        <div class="form-grid">
          <div class="field"><label for="product-title">Title</label><input id="product-title" v-model.trim="productForm.title" maxlength="240" required /></div>
          <div class="field"><label for="product-slug">Slug</label><input id="product-slug" v-model.trim="productForm.slug" maxlength="240" required /></div>
          <div class="field"><label for="product-sku">SKU</label><input id="product-sku" v-model.trim="productForm.sku" maxlength="100" /><small>Internal stock code used to identify this product. Shoppers won’t see it.</small></div>
          <div class="field"><label for="product-price">Selling price</label><input id="product-price" v-model.number="productForm.price" type="number" min="0" step="0.01" required /><small>Enter a normal price with up to two decimal places, for example 12.99.</small></div>
          <div class="field"><label for="product-compare">Compare-at price</label><input id="product-compare" v-model.number="productForm.compare_at_price" type="number" min="0" step="0.01" /><small>Optional original price shown crossed out. It must be higher than the selling price.</small></div>
          <div class="field"><label for="product-category">Category</label><select id="product-category" v-model="productForm.category_id"><option value="">Uncategorized</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
          <div class="field"><label for="product-published-at">Publish date/time</label><input id="product-published-at" :value="formatLocalDateTime(productForm.published_at)" type="datetime-local" @input="productForm.published_at = $event.target.value ? new Date($event.target.value).toISOString() : ''" /><small>Leave blank to keep this product unpublished.</small></div>
          <div class="field field--wide"><label for="product-description">Description</label><textarea id="product-description" v-model="productForm.description" required /></div>
          <div class="field field--wide variant-editor">
            <div class="variant-editor__heading">
              <div><strong>Options and variants</strong><small>Add combinations such as color and size. Each combination has its own SKU, price, and stock.</small></div>
              <button class="button button--secondary button--small" type="button" @click="addProductVariant">Add variant</button>
            </div>
            <p v-if="!productForm.variants.length" class="variant-empty">No variants. Leave this empty for a product with one price and stock count.</p>
            <div v-for="(variant, index) in productForm.variants" :key="variant.id || `new-${index}`" class="variant-row">
              <label class="field"><span>Color</span><input v-model.trim="variant.color" maxlength="120" placeholder="e.g. Sage green" /></label>
              <label class="field"><span>Size</span><input v-model.trim="variant.size" maxlength="120" placeholder="e.g. Medium" /></label>
              <label class="field"><span>Variant SKU</span><input v-model.trim="variant.sku" maxlength="100" placeholder="Unique stock code" required /></label>
              <label class="field"><span>Price</span><input v-model.number="variant.price" type="number" min="0" step="0.01" required /></label>
              <label class="field"><span>Stock</span><input v-model.number="variant.stock" type="number" min="0" step="1" required /></label>
              <button class="text-button text-button--danger variant-row__remove" type="button" @click="removeProductVariant(index)">Remove</button>
            </div>
          </div>
          <div class="field field--wide"><label for="product-media">Product photos and short videos</label><input id="product-media" type="file" accept="image/jpeg,image/png,image/webp,image/avif,video/mp4,video/webm,video/quicktime" multiple @change="productForm.media_files = Array.from($event.target.files || [])" /><small>Choose photos up to 10 MB each and videos up to 25 MB each. New media uploads when you save.</small><div v-if="productForm.media_files.length" class="media-file-list">Selected: {{ productForm.media_files.map((file) => file.name).join(', ') }}</div><div v-if="productForm.images.length" class="admin-media-list"><div v-for="image in productForm.images" :key="image.id" class="admin-media-item"><img v-if="image.media_type !== 'video'" :src="image.url" :alt="image.alt_text || ''" class="admin-media-preview" /><video v-else :src="image.url" class="admin-media-preview" muted controls /><button class="text-button text-button--danger" type="button" :disabled="busy" @click="removeProductImage(image)">Remove</button></div></div></div>
          <label class="checkbox-field"><input v-model="productForm.is_active" type="checkbox" /> Active in catalog</label>
        </div>
        <div class="form-actions"><button class="button button--secondary" type="button" @click="showProductForm = false">Cancel</button><button class="button" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save product' }}</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><span class="card-title">Product catalog</span><div class="filter-row"><input v-model.trim="filters.product_q" class="search-input table-search-input" placeholder="Search title, slug or SKU" @keyup.enter="searchTable" /><button class="button button--secondary button--small" type="button" @click="searchTable">Search</button><label class="page-size-control">Rows<select class="search-input" :value="pageSizes.products" @change="changePageSize('products', $event.target.value)"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option></select></label><button class="text-button" type="button" @click="loadSection(page)">Refresh</button></div></div>
      <div v-if="loading" class="loading">Loading products…</div>
      <div v-else-if="rows.length === 0" class="empty-state">{{ filters.product_q ? 'No products match your search.' : 'No products in this tenant yet.' }}</div>
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
