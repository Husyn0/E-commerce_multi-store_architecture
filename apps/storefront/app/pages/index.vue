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
  images: { url?: string; alt_text?: string | null; media_type?: 'image' | 'video' }[]
  variants: { id: string; price_cents: number; stock: number }[]
}

interface Category {
  id: string
  name: string
  slug: string
  icon?: string | null
  image_url?: string | null
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
const homeContent = computed(() => ({
  hero_eyebrow: 'Good things, thoughtfully made',
  hero_title: 'Everyday objects.',
  hero_emphasis: 'Extraordinary feel.',
  hero_description: 'Find considered essentials that bring a little more intention to the everyday.',
  hero_cta_label: 'Explore the collection',
  hero_image_url: null,
  hero_product_id: null,
  story_title: 'Less, but better.',
  story_emphasis: 'Always.',
  story_caption: 'Thoughtful design, made to be lived with.',
  ...tenant.value?.theme_config?.content,
}))
const heroProductId = homeContent.value.hero_product_id
const { data: featuredProduct } = await useAsyncData(
  `storefront-home-featured-${heroProductId || 'none'}`,
  async () => {
    if (!heroProductId) return null
    try {
      const response = await api.get<{ data: Product }>(`catalog/products/id/${encodeURIComponent(heroProductId)}`)
      return unwrapResource(response)
    } catch {
      return null
    }
  }
)
const featuredProductImage = computed(() =>
  featuredProduct.value?.images.find((image) => image.media_type !== 'video' && image.url)?.url || null
)
const heroImageUrl = computed(() => featuredProductImage.value || homeContent.value.hero_image_url)
useSeoMeta({
  title: () => `${catalog.value?.categories[0]?.name || 'Objects for everyday'} — ${tenant.value?.name || 'Store'}`,
  description: 'Explore the latest collection from your neighborhood store.'
})
</script>

<template>
  <div>
    <section class="hero">
      <div class="hero__copy">
        <span class="eyebrow">{{ homeContent.hero_eyebrow }}</span>
        <h1>{{ homeContent.hero_title }}<br><em>{{ homeContent.hero_emphasis }}</em></h1>
        <p>{{ homeContent.hero_description }}</p>
        <NuxtLink class="button button--dark" to="/search">{{ homeContent.hero_cta_label }} <span>↗</span></NuxtLink>
      </div>
      <NuxtLink
        v-if="featuredProduct && featuredProductImage"
        class="hero__art hero__art--image hero__art--featured"
        :to="`/products/${featuredProduct.slug}`"
        :aria-label="`View featured product: ${featuredProduct.title}`"
      >
        <img :src="heroImageUrl || ''" :alt="featuredProduct.title" />
        <span class="hero-featured-label">Featured product · {{ featuredProduct.title }}</span>
      </NuxtLink>
      <div v-else-if="heroImageUrl" class="hero__art hero__art--image" aria-hidden="true">
        <img :src="heroImageUrl" alt="" />
      </div>
      <div v-else class="hero__art" aria-hidden="true">
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
          :class="[`category-tile--${index % 4 + 1}`, { 'category-tile--image': category.image_url }]"
          :to="`/categories/${category.slug}`"
          :style="category.image_url ? { backgroundImage: `linear-gradient(0deg, rgba(25,22,18,.66), rgba(25,22,18,.05)), url(${category.image_url})` } : undefined"
        >
          <span class="category-tile__number">0{{ index + 1 }}</span>
          <span class="category-tile__label"><span v-if="category.icon && !category.image_url">{{ category.icon }} </span>{{ category.name }}</span>
          <span class="category-tile__arrow">↗</span>
        </NuxtLink>
      </div>
    </section>

    <section class="story-band">
      <div class="story-band__mark">V.</div>
      <p>{{ homeContent.story_title }}<br><em>{{ homeContent.story_emphasis }}</em></p>
      <span>{{ homeContent.story_caption }}</span>
    </section>
  </div>
</template>
