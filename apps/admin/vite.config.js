import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    proxy: {
      '/api': {
        target: process.env.ADMIN_API_ORIGIN || 'http://127.0.0.1:8000',
        changeOrigin: false,
      },
    },
  },
})
