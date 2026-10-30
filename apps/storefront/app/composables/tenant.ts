export interface StoreTenant {
  id: string
  name: string
  status: 'active' | 'suspended'
  default_locale: string
  default_currency: string
  host_type: 'storefront'
  theme_config?: {
    colors?: Record<string, string>
    fonts?: { heading?: string; body?: string }
    logo_url?: string | null
    layout_variant?: string
  }
  registration?: {
    require_email_verification: boolean
    require_phone_verification: boolean
    human_check_driver: 'demo' | 'turnstile' | 'hcaptcha'
  }
  password_recovery?: {
    email_enabled: boolean
    phone_enabled: boolean
  }
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
