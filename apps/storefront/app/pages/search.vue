<script setup lang="ts">
interface Product {
  id: string
  title: string
  slug: string
  description: string | null
  price_cents: number
  compare_at_price_cents: number | null
  currency: string
  stock: number
  images: { url?: string; alt_text?: string | null }[]
  variants: { id: string; price_cents: number; stock: number }[]
}
interface Category {
  id: string
  name: string
  slug: string
}

const route = useRoute()
const router = useRouter()
const term = ref(String(route.query.q || ''))
const selectedCategory = ref(String(route.query.category || ''))
const query = computed(() => ({
  ...(term.value.trim() ? { q: term.value.trim() } : {}),
  ...(selectedCategory.value ? { category: selectedCategory.value } : {}),
  per_page: 48,
}))
const api = useStorefrontApi()
const categoriesRequest = useAsyncData('storefront-search-categories', async () => {
  try {
    const response = await api.get<{ data: Category[] }>('catalog/categories')
    return unwrapResource(response)
  } catch {
    return []
  }
})
const searchRequest = useAsyncData(
  'storefront-search',
  async () => {
    const response = await api.get<{ data: Product[] }>('catalog/products', query.value)
    return unwrapResource(response)
  },
  { watch: [query] }
)
const [categoryState, searchState] = await Promise.all([categoriesRequest, searchRequest])
const categories = computed(() => categoryState.data.value || [])
const result = searchState.data
const pending = searchState.pending
const error = searchState.error
const refresh = searchState.refresh
const categoryMenuOpen = ref(false)
const categorySearch = ref('')
const categoryDropdown = ref<HTMLElement | null>(null)
const categorySearchInput = ref<HTMLInputElement | null>(null)
const selectedCategoryName = computed(() => categories.value.find((category) => category.slug === selectedCategory.value)?.name || '')
const filteredCategories = computed(() => {
  const search = categorySearch.value.trim().toLocaleLowerCase()
  return search
    ? categories.value.filter((category) => category.name.toLocaleLowerCase().includes(search))
    : categories.value
})

watch([term, selectedCategory], ([value, category]) => {
  router.replace({ query: {
    ...(value.trim() ? { q: value.trim() } : {}),
    ...(category ? { category } : {}),
  } })
})

watch(categoryMenuOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    categorySearchInput.value?.focus()
  } else {
    categorySearch.value = ''
  }
})

function chooseCategory(slug: string) {
  selectedCategory.value = slug
  categoryMenuOpen.value = false
}

function handleCategoryOutsideClick(event: PointerEvent) {
  if (!categoryDropdown.value?.contains(event.target as Node)) categoryMenuOpen.value = false
}

onMounted(() => document.addEventListener('pointerdown', handleCategoryOutsideClick))
onBeforeUnmount(() => document.removeEventListener('pointerdown', handleCategoryOutsideClick))

useSeoMeta({ title: 'Discover', description: 'Search the store collection.' })
</script>

<template>
  <section class="page-section">
    <div class="page-intro">
      <span class="eyebrow">The collection</span>
      <h1>Find your something.</h1>
      <p>Explore every considered piece in the store.</p>
    </div>
    <div class="discover-filters">
      <label class="search-bar">
        <span aria-hidden="true">⌕</span>
        <input v-model="term" type="search" placeholder="Search products…" aria-label="Search products">
        <kbd>↵</kbd>
      </label>
      <div ref="categoryDropdown" class="category-filter">
        <span class="category-filter__label">Category</span>
        <button
          class="category-filter__trigger"
          type="button"
          aria-haspopup="listbox"
          :aria-expanded="categoryMenuOpen"
          @click="categoryMenuOpen = !categoryMenuOpen"
        >
          {{ selectedCategoryName || 'All categories' }} <span aria-hidden="true">⌄</span>
        </button>
        <div v-if="categoryMenuOpen" class="category-filter__menu">
          <label class="category-filter__search">
            <input ref="categorySearchInput" v-model="categorySearch" type="search" aria-label="Search categories" placeholder="Find a category…" @keydown.esc="categoryMenuOpen = false">
          </label>
          <div class="category-filter__options" role="listbox" aria-label="Product categories">
            <button class="category-filter__option" type="button" role="option" :aria-selected="!selectedCategory" @click="chooseCategory('')">All categories</button>
            <button
              v-for="category in filteredCategories"
              :key="category.id"
              class="category-filter__option"
              type="button"
              role="option"
              :aria-selected="selectedCategory === category.slug"
              @click="chooseCategory(category.slug)"
            >{{ category.name }}</button>
            <p v-if="!filteredCategories.length" class="category-filter__empty">No matching categories.</p>
          </div>
        </div>
      </div>
    </div>
    <div v-if="pending" class="loading-state">Finding good things…</div>
    <div v-else-if="error" class="error-state" role="alert">
      <p>We couldn't load the collection.</p>
      <button class="text-action" type="button" @click="refresh()">Try again</button>
    </div>
    <div v-else-if="result?.length" class="product-grid product-grid--listing">
      <ProductCard v-for="product in result" :key="product.id" :product="product" />
    </div>
    <div v-else class="empty-state">
      <h3>No pieces found.</h3>
      <p>Try a different search or explore the full collection.</p>
      <button class="button button--outline" type="button" @click="term = ''; selectedCategory = ''">Clear filters</button>
    </div>
  </section>
</template>
