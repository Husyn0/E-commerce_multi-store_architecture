<script setup lang="ts">
interface Product {
  id: string; title: string; slug: string; description: string | null
  price_cents: number; compare_at_price_cents: number | null; currency: string
  stock: number; images: { url?: string; alt_text?: string | null }[]
  variants: { id: string; price_cents: number; stock: number }[]
}
interface Category { id: string; name: string; slug: string }

const route = useRoute()
const slug = computed(() => String(route.params.slug))
const api = useStorefrontApi()
const { data: result, error } = await useAsyncData(`storefront-category-${slug.value}`, async () => {
  const [categoriesResponse, productsResponse] = await Promise.all([
    api.get<{ data: Category[] }>('catalog/categories'),
    api.get<{ data: Product[] }>('catalog/products', { category: slug.value, per_page: 48 })
  ])
  return {
    categories: unwrapResource(categoriesResponse),
    products: unwrapResource(productsResponse)
  }
})
const category = computed(() => result.value?.categories.find((item) => item.slug === slug.value))

if (error.value) throw createError({ statusCode: 503, statusMessage: 'This collection is unavailable.' })
if (result.value && !category.value) throw createError({ statusCode: 404, statusMessage: 'Collection not found.' })

useSeoMeta({
  title: () => category.value?.name || 'Collection',
  description: () => `Explore ${category.value?.name || 'this collection'} from the store.`
})
</script>

<template>
  <section class="page-section">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/">← All collections</NuxtLink>
      <span class="eyebrow">The collection</span>
      <h1>{{ category?.name }}</h1>
      <p>{{ result?.products.length || 0 }} thoughtful pieces to explore.</p>
    </div>
    <div v-if="result?.products.length" class="product-grid product-grid--listing">
      <ProductCard v-for="product in result.products" :key="product.id" :product="product" />
    </div>
    <div v-else class="empty-state"><h3>Nothing here just yet.</h3><p>Check back soon for new pieces.</p></div>
  </section>
</template>
