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
  images: { id: string; url: string; alt_text: string | null; media_type?: 'image' | 'video' }[]
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
const activeMediaIndex = ref(0)
const mediaTrack = ref<HTMLElement | null>(null)
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

function selectMedia(index: number) {
  const media = product.value?.images[index]
  if (!media) return
  activeMediaIndex.value = index
  mediaTrack.value?.children[index]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' })
}

function updateActiveMedia(event: Event) {
  const track = event.currentTarget as HTMLElement
  activeMediaIndex.value = Math.round(track.scrollLeft / track.clientWidth)
}

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
      <div v-if="product.images.length" class="product-detail__gallery">
        <div ref="mediaTrack" class="product-detail__gallery-track" @scroll.passive="updateActiveMedia">
          <div v-for="media in product.images" :key="media.id" class="product-detail__gallery-slide">
            <video v-if="media.media_type === 'video'" :src="media.url" controls playsinline preload="metadata" />
            <img v-else :src="media.url" :alt="media.alt_text || product.title">
          </div>
        </div>
        <div v-if="product.images.length > 1" class="product-detail__gallery-controls">
          <button type="button" aria-label="Previous product image" :disabled="activeMediaIndex === 0" @click="selectMedia(activeMediaIndex - 1)">←</button>
          <span>{{ activeMediaIndex + 1 }} / {{ product.images.length }}</span>
          <button type="button" aria-label="Next product image" :disabled="activeMediaIndex >= product.images.length - 1" @click="selectMedia(activeMediaIndex + 1)">→</button>
        </div>
        <div v-if="product.images.length > 1" class="product-detail__thumbnails" aria-label="Choose product image">
          <button v-for="(media, index) in product.images" :key="`thumb-${media.id}`" type="button" :aria-label="`Show product media ${index + 1}`" :aria-current="activeMediaIndex === index ? 'true' : undefined" @click="selectMedia(index)">
            <video v-if="media.media_type === 'video'" :src="media.url" muted preload="metadata" />
            <img v-else :src="media.url" :alt="media.alt_text || `${product.title} ${index + 1}`">
            <span v-if="media.media_type === 'video'" aria-hidden="true">▶</span>
          </button>
        </div>
      </div>
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
