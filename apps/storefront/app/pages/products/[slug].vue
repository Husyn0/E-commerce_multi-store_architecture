<script setup lang="ts">
interface Variant {
  id: string
  name: string
  sku: string | null
  attributes: Record<string, string> | null
  price_cents: number
  stock: number
}
interface Product {
  id: string
  category_id: string | null
  title: string
  slug: string
  description: string | null
  price_cents: number
  compare_at_price_cents: number | null
  currency: string
  stock: number
  images: { url: string; alt_text: string | null }[]
  variants: Variant[]
}

const route = useRoute()
const api = useStorefrontApi()
const slug = String(route.params.slug)
const { data: response, error } = await useAsyncData(`storefront-product-${slug}`, () =>
  api.get<{ data: Product }>(`catalog/products/${encodeURIComponent(slug)}`)
)
if (error.value) {
  const status = (error.value as { statusCode?: number }).statusCode
  throw createError({ statusCode: status === 404 ? 404 : 503, statusMessage: status === 404 ? 'Product not found.' : 'Product unavailable.' })
}
const product = computed(() => response.value ? unwrapResource(response.value) : null)
const selectedVariant = ref('')
const quantity = ref(1)
const pending = ref(false)
const message = ref('')
const { add } = useCart()
const activePrice = computed(() => {
  const variant = product.value?.variants.find((item) => item.id === selectedVariant.value)
  return variant?.price_cents ?? product.value?.price_cents ?? 0
})
const available = computed(() => {
  const variant = product.value?.variants.find((item) => item.id === selectedVariant.value)
  return variant?.stock ?? product.value?.stock ?? 0
})

watch(product, (item) => {
  const firstVariant = item?.variants[0]
  if (firstVariant) selectedVariant.value = firstVariant.id
}, { immediate: true })

async function addToBag() {
  if (!product.value || available.value < 1 || (product.value.variants.length && !selectedVariant.value)) return
  pending.value = true
  message.value = ''
  try {
    await add(product.value.id, quantity.value, selectedVariant.value || null)
    message.value = 'Added to your bag.'
  } catch (failure) {
    message.value = apiErrorMessage(failure)
  } finally {
    pending.value = false
  }
}

useSeoMeta({
  title: () => product.value?.title || 'Product',
  description: () => product.value?.description || 'Discover a considered piece from the store.'
})
</script>

<template>
  <section v-if="product" class="product-detail">
    <div class="product-detail__visual">
      <img v-if="product.images[0]?.url" :src="product.images[0].url" :alt="product.images[0].alt_text || product.title">
      <div v-else class="product-placeholder product-placeholder--detail"><span>{{ product.title.slice(0, 1) }}</span></div>
      <span class="image-caption">A closer look</span>
    </div>
    <div class="product-detail__info">
      <NuxtLink class="back-link" to="/search">← Back to the collection</NuxtLink>
      <span class="eyebrow">Thoughtfully selected</span>
      <h1>{{ product.title }}</h1>
      <div class="detail-price">{{ formatMoney(activePrice, product.currency) }}</div>
      <p class="product-description">{{ product.description || 'A considered essential for everyday living.' }}</p>

      <div v-if="product.variants.length" class="detail-field">
        <label for="variant">Choose an option</label>
        <select id="variant" v-model="selectedVariant">
          <option v-for="variant in product.variants" :key="variant.id" :value="variant.id" :disabled="variant.stock < 1">
            {{ variant.name }}<template v-if="variant.stock < 1"> — Sold out</template>
          </option>
        </select>
      </div>

      <div class="detail-field detail-field--quantity">
        <label for="quantity">Quantity</label>
        <div class="quantity-control">
          <button type="button" aria-label="Decrease quantity" :disabled="quantity <= 1" @click="quantity--">−</button>
          <input id="quantity" v-model.number="quantity" type="number" min="1" :max="available">
          <button type="button" aria-label="Increase quantity" :disabled="quantity >= available" @click="quantity++">+</button>
        </div>
        <span v-if="available > 0" class="stock-note">Ready to ship · {{ available }} available</span>
        <span v-else class="stock-note">Currently unavailable</span>
      </div>

      <button class="button button--dark button--wide" type="button" :disabled="pending || available < 1" @click="addToBag">
        {{ pending ? 'Adding to bag…' : available < 1 ? 'Sold out' : 'Add to bag' }}
        <span v-if="available > 0">{{ formatMoney(activePrice * quantity, product.currency) }}</span>
      </button>
      <p v-if="message" class="inline-message" role="status">{{ message }}</p>
      <div class="product-promise"><span>◇</span><p>Good design, made to be part of your everyday.</p></div>
    </div>
  </section>
</template>
