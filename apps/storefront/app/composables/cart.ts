export interface StoreCartItem {
  id: string
  product_id: string
  variant_id: string | null
  title: string
  variant_name: string | null
  image_url: string | null
  quantity: number
  unit_price_cents: number
  line_total_cents: number
}

export interface StoreCart {
  id: string
  currency: string
  items: StoreCartItem[]
  subtotal_cents: number
}

let clientRefreshTask: Promise<StoreCart> | null = null

export function useCart() {
  const cart = useState<StoreCart | null>('storefront-cart', () => null)
  const loading = useState('storefront-cart-loading', () => false)
  const error = useState<string | null>('storefront-cart-error', () => null)
  const api = useStorefrontApi()

  async function refresh() {
    if (import.meta.client && clientRefreshTask) return clientRefreshTask

    loading.value = true
    error.value = null
    const request = api.get<StoreCart>('cart')
      .then((result) => {
        cart.value = result
        return result
      })
      .catch((failure) => {
        error.value = apiErrorMessage(failure)
        throw failure
      })
      .finally(() => {
        loading.value = false
        if (import.meta.client) clientRefreshTask = null
      })
    if (import.meta.client) clientRefreshTask = request

    return request
  }

  async function add(productId: string, quantity: number, variantId: string | null = null) {
    if (!cart.value) await refresh()
    await api.send('cart/items', 'POST', {
      product_id: productId,
      variant_id: variantId,
      quantity
    })
    await refresh()
  }

  async function update(itemId: string, quantity: number) {
    await api.send(`cart/items/${encodeURIComponent(itemId)}`, 'PATCH', { quantity })
    await refresh()
  }

  async function remove(itemId: string) {
    await api.send(`cart/items/${encodeURIComponent(itemId)}`, 'DELETE')
    await refresh()
  }

  const count = computed(() => cart.value?.items.reduce((sum, item) => sum + item.quantity, 0) ?? 0)

  return { cart, loading, error, count, refresh, add, update, remove }
}
