<script setup lang="ts">
interface ProductImage {
  url?: string
  alt_text?: string | null
  media_type?: 'image' | 'video'
}

interface Product {
  id: string
  title: string
  slug: string
  description?: string | null
  price_cents: number
  compare_at_price_cents?: number | null
  currency: string
  stock: number
  images?: ProductImage[]
  variants?: { id: string; price_cents: number; stock: number }[]
}

const props = defineProps<{ product: Product }>()
const { add } = useCart()
const pending = ref(false)
const message = ref('')
const available = computed(() => props.product.variants?.length
  ? props.product.variants.some((variant) => variant.stock > 0)
  : props.product.stock > 0)
const price = computed(() => props.product.variants?.length
  ? Math.min(...props.product.variants.map((variant) => variant.price_cents))
  : props.product.price_cents)

async function addToBag() {
  pending.value = true
  message.value = ''
  try {
    await add(props.product.id, 1)
    message.value = 'Added to your bag'
  } catch (error) {
    message.value = apiErrorMessage(error)
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <article class="product-card">
    <NuxtLink class="product-card__media" :to="`/products/${product.slug}`">
      <video v-if="product.images?.[0]?.media_type === 'video' && product.images[0]?.url" :src="product.images[0].url" muted playsinline preload="metadata" />
      <img v-else-if="product.images?.[0]?.url" :src="product.images[0].url" :alt="product.images[0].alt_text || product.title" loading="lazy">
      <div v-else class="product-placeholder" aria-hidden="true"><span>{{ product.title.slice(0, 1) }}</span></div>
      <span v-if="!available" class="product-badge">Sold out</span>
    </NuxtLink>
    <div class="product-card__details">
      <NuxtLink class="product-card__title" :to="`/products/${product.slug}`">{{ product.title }}</NuxtLink>
      <div class="product-card__price">
        <span>{{ formatMoney(price, product.currency) }}<template v-if="product.variants?.length">+</template></span>
        <del v-if="product.compare_at_price_cents">{{ formatMoney(product.compare_at_price_cents, product.currency) }}</del>
      </div>
      <button
        v-if="available && !product.variants?.length"
        class="text-action"
        type="button"
        :disabled="pending"
        @click="addToBag"
      >{{ pending ? 'Adding…' : 'Quick add +' }}</button>
      <NuxtLink v-else-if="available" class="text-action" :to="`/products/${product.slug}`">Choose options ↗</NuxtLink>
      <span v-else class="sold-out-copy">Currently unavailable</span>
      <p v-if="message" class="inline-message" role="status">{{ message }}</p>
    </div>
  </article>
</template>
