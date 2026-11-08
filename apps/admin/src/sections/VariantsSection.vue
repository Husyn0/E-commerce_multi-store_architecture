<script>
import { computed, defineComponent, inject, ref } from 'vue'

export default defineComponent({
  setup() {
    const workspace = inject('adminWorkspace')
    const productSearch = ref('')
    const productPickerOpen = ref(false)
    const filteredProducts = computed(() => {
      const query = productSearch.value.trim().toLowerCase()
      return workspace.variantProducts.value.filter((product) => !query || product.title.toLowerCase().includes(query))
    })
    const filteredVariants = computed(() => {
      const query = workspace.variantSearch.value?.trim().toLowerCase()
      if (!query) return workspace.variantRows.value
      return workspace.variantRows.value.filter((variant) => [
        variant.product_title, variant.sku, variant.name,
        ...Object.values(variant.attributes || {}),
      ].some((value) => String(value || '').toLowerCase().includes(query)))
    })
    function chooseProduct(product) {
      workspace.newVariant.product_id = product.id
      productSearch.value = product.title
      productPickerOpen.value = false
    }

    function addOption(source) {
      source.extraAttributes.push({ label: '', value: '' })
    }

    function removeOption(source, index) {
      source.extraAttributes.splice(index, 1)
    }

    return { ...workspace, filteredVariants, filteredProducts, productSearch, productPickerOpen, chooseProduct, addOption, removeOption }
  },
})
</script>

<template>
  <section class="card form-card">
    <div class="card-head"><span class="card-title">Add a variant</span></div>
    <form class="card-body variant-create-form" @submit.prevent="saveVariant()">
      <div class="field product-picker"><span>Product</span><input v-model="productSearch" type="search" placeholder="Search products…" autocomplete="off" required :aria-expanded="productPickerOpen" @focus="productPickerOpen = true" @input="newVariant.product_id = ''; productPickerOpen = true" @keydown.esc="productPickerOpen = false" /><div v-if="productPickerOpen" class="product-picker__list" role="listbox"><button v-for="product in filteredProducts" :key="product.id" class="product-picker__option" type="button" role="option" @click="chooseProduct(product)">{{ product.title }}</button><span v-if="!filteredProducts.length" class="product-picker__empty">No matching products.</span></div></div>
      <label class="field"><span>SKU</span><input v-model.trim="newVariant.sku" maxlength="100" required /></label>
      <label class="field"><span>Variant name</span><input v-model.trim="newVariant.name" maxlength="160" placeholder="Sage / Medium" required /></label>
      <label class="field"><span>Color</span><input v-model.trim="newVariant.color" maxlength="120" placeholder="e.g. Sage green" /></label>
      <label class="field"><span>Size</span><input v-model.trim="newVariant.size" maxlength="120" placeholder="e.g. Medium" /></label>
      <label class="field"><span>Price</span><input v-model.number="newVariant.price" type="number" min="0" step="0.01" required /></label>
      <label class="field"><span>Stock</span><input v-model.number="newVariant.stock" type="number" min="0" step="1" required /></label>
      <div class="field field--wide variant-extra-options"><div class="variant-extra-options__heading"><span>Additional options</span><button class="text-button" type="button" @click="addOption(newVariant)">+ Add parameter</button></div><div v-for="(attribute, index) in newVariant.extraAttributes" :key="index" class="variant-extra-option"><label class="field"><span>Label</span><input v-model.trim="attribute.label" maxlength="120" placeholder="e.g. Material" /></label><label class="field"><span>Value</span><input v-model.trim="attribute.value" maxlength="120" placeholder="e.g. Cotton" /></label><button class="text-button text-button--danger" type="button" @click="removeOption(newVariant, index)">Remove</button></div></div>
      <div class="form-actions"><button class="button" type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Create variant' }}</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><span class="card-title">All variants <span class="variant-count">{{ filteredVariants.length }}</span></span><div class="filter-row"><input v-model.trim="variantSearch" class="search-input table-search-input" placeholder="Search product, SKU, option" /><button class="text-button" type="button" :disabled="loading" @click="loadSection(1)">Refresh</button></div></div>
    <div v-if="loading" class="loading">Loading variants…</div>
    <div v-else-if="!filteredVariants.length" class="empty-state">{{ variantRows.length ? 'No variants match your search.' : 'No product variants yet.' }}</div>
    <div v-else class="table-scroll"><table class="data-table variants-table">
      <thead><tr><th>Product</th><th>Variant</th><th>SKU</th><th>Options</th><th>Price</th><th>Stock</th><th></th></tr></thead>
      <tbody><tr v-for="variant in filteredVariants" :key="variant.id">
        <template v-if="editingVariantId === variant.id">
          <td><div class="primary-cell">{{ variant.product_title }}</div></td>
          <td><input v-model.trim="variantDraft.name" class="search-input" aria-label="Variant name" /></td>
          <td><input v-model.trim="variantDraft.sku" class="search-input" aria-label="Variant SKU" /></td>
          <td><div class="variant-edit-options"><label>Color<input v-model.trim="variantDraft.color" class="search-input" aria-label="Variant color" /></label><label>Size<input v-model.trim="variantDraft.size" class="search-input" aria-label="Variant size" /></label><div v-for="(attribute, index) in variantDraft.extraAttributes" :key="index" class="variant-extra-option"><label>Label<input v-model.trim="attribute.label" class="search-input" /></label><label>Value<input v-model.trim="attribute.value" class="search-input" /></label><button class="text-button text-button--danger" type="button" @click="removeOption(variantDraft, index)">Remove</button></div><button class="text-button" type="button" @click="addOption(variantDraft)">+ Add parameter</button></div></td>
          <td><input v-model.number="variantDraft.price" class="search-input" type="number" min="0" step="0.01" aria-label="Variant price" /></td>
          <td><input v-model.number="variantDraft.stock" class="search-input inventory-input" type="number" min="0" step="1" aria-label="Variant stock" /></td>
          <td><div class="inline-actions"><button class="text-button" type="button" :disabled="busy" @click="saveVariant(variant)">Save</button><button class="text-button" type="button" @click="editingVariantId = ''">Cancel</button></div></td>
        </template>
        <template v-else>
          <td><div class="primary-cell">{{ variant.product_title }}</div></td>
          <td>{{ variant.name || '—' }}</td><td>{{ variant.sku }}</td>
          <td><div class="variant-option-list"><span v-for="(value, key) in variant.attributes" :key="key">{{ key }}: {{ value }}</span><span v-if="!Object.keys(variant.attributes || {}).length">—</span></div></td>
          <td>{{ money(variant.price_cents, variant.currency) }}</td>
          <td><form class="inventory-line" @submit.prevent="saveVariantInventory(variant)"><input v-model.number="variant.stock" class="search-input inventory-input" type="number" min="0" step="1" :aria-label="`${variant.sku} stock`" /><button class="text-button" type="submit" :disabled="busy">Save</button></form></td>
          <td><div class="inline-actions"><button class="text-button" type="button" @click="beginVariantEdit(variant)">Edit</button><button class="text-button text-button--danger" type="button" :disabled="busy" @click="deleteVariant(variant)">Delete</button></div></td>
        </template>
      </tr></tbody>
    </table></div>
  </section>
</template>
