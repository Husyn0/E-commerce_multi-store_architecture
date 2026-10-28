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

const api = useStorefrontApi()
const { data: catalog, error } = await useAsyncData('storefront-home-catalog', async () => {
  const [productsResponse, categoriesResponse] = await Promise.all([
    api.get<{ data: Product[]; meta?: { last_page?: number } }>('catalog/products', { per_page: 12 }),
    api.get<{ data: Category[] }>('catalog/categories')
  ])
  return {
    products: unwrapResource(productsResponse as { data: Product[] }),
    categories: unwrapResource(categoriesResponse as { data: Category[] })
  }
})

if (error.value) {
  throw createError({ statusCode: 503, statusMessage: 'The catalog could not be loaded.' })
}

const { data: tenant } = useTenant()
useSeoMeta({
  title: () => `${catalog.value?.categories[0]?.name || 'Objects for everyday'} — ${tenant.value?.name || 'Store'}`,
  description: 'Explore the latest collection from your neighborhood store.'
})
</script>

<template>
  <div>
    <section class="hero">
      <div class="hero__copy">
        <span class="eyebrow">Good things, thoughtfully made</span>
        <h1>Everyday objects.<br><em>Extraordinary feel.</em></h1>
        <p>Find considered essentials that bring a little more intention to the everyday.</p>
        <NuxtLink class="button button--dark" to="/search">Explore the collection <span>↗</span></NuxtLink>
      </div>
      <div class="hero__art" aria-hidden="true">
        <div class="hero-sun" />
        <div class="hero-vase hero-vase--large" />
        <div class="hero-vase hero-vase--small" />
        <div class="hero-table" />
        <span class="hero-note">Objects<br>with purpose</span>
      </div>
    </section>

    <section class="section collection-section">
      <div class="section-heading">
        <div>
          <span class="eyebrow">The edit</span>
          <h2>Considered for you</h2>
        </div>
        <NuxtLink class="underlined-link" to="/search">View all products <span>↗</span></NuxtLink>
      </div>
      <div v-if="catalog?.products.length" class="product-grid">
        <ProductCard v-for="product in catalog.products" :key="product.id" :product="product" />
      </div>
      <div v-else class="empty-state">
        <h3>A new collection is on its way.</h3>
        <p>There are no published products to show just yet.</p>
      </div>
    </section>

    <section v-if="catalog?.categories.length" class="category-strip">
      <div class="section-heading">
        <div><span class="eyebrow">Browse by feeling</span><h2>Find your something</h2></div>
      </div>
      <div class="category-grid">
        <NuxtLink
          v-for="(category, index) in catalog.categories"
          :key="category.id"
          class="category-tile"
          :class="`category-tile--${index % 4 + 1}`"
          :to="`/categories/${category.slug}`"
        >
          <span class="category-tile__number">0{{ index + 1 }}</span>
          <span>{{ category.name }}</span>
          <span class="category-tile__arrow">↗</span>
        </NuxtLink>
      </div>
    </section>

    <section class="story-band">
      <div class="story-band__mark">V.</div>
      <p>Less, but better.<br><em>Always.</em></p>
      <span>Thoughtful design, made to be lived with.</span>
    </section>
  </div>
</template>
