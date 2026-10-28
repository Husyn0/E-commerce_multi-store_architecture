export interface StoreTenant {
  id: string
  name: string
  status: 'active' | 'suspended'
  default_locale: string
  default_currency: string
  host_type: 'storefront'
}

export function useTenant() {
  return useAsyncData('storefront-tenant', async () => {
    const api = useStorefrontApi()
    return await api.get<StoreTenant>('tenant')
  }, {
    server: true,
    dedupe: 'defer'
  })
}
