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

const route = useRoute()
const router = useRouter()
const term = ref(String(route.query.q || ''))
const query = computed(() => ({ q: term.value.trim(), per_page: 48 }))
const api = useStorefrontApi()
const { data: result, pending, error, refresh } = await useAsyncData(
  'storefront-search',
  async () => {
    const response = await api.get<{ data: Product[] }>('catalog/products', query.value)
    return unwrapResource(response)
  },
  { watch: [query] }
)

watch(term, (value) => {
  router.replace({ query: value.trim() ? { q: value.trim() } : {} })
})

useSeoMeta({ title: 'Discover', description: 'Search the store collection.' })
</script>

<template>
  <section class="page-section">
    <div class="page-intro">
      <span class="eyebrow">The collection</span>
      <h1>Find your something.</h1>
      <p>Explore every considered piece in the store.</p>
    </div>
    <label class="search-bar">
      <span aria-hidden="true">⌕</span>
      <input v-model="term" type="search" placeholder="Search products…" aria-label="Search products">
      <kbd>↵</kbd>
    </label>
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
      <button class="button button--outline" type="button" @click="term = ''">Clear search</button>
    </div>
  </section>
</template>
