// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    storefrontApiOrigin: process.env.STOREFRONT_API_ORIGIN || 'http://127.0.0.1:8000'
  },
  routeRules: {
    '/api/backend/**': { cache: false }
  },
  nitro: {
    routeRules: {
      '/api/backend/**': { cors: false }
    }
  },
  app: {
    head: {
      titleTemplate: '%s · Store',
      meta: [
        { name: 'theme-color', content: '#f7f5f0' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' }
      ]
    }
  }
})
