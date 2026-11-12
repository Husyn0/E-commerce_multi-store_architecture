import { reactive } from 'vue'
import { api, refreshAccessToken, setAccessToken } from '../api'

export const auth = reactive({
  user: null,
  isAuthenticated: false,

  async login(email, password) {
    const result = await api.post('/admin/auth/login', { email, password }, { authenticated: false })
    setAccessToken(result.access_token)
    this.user = result.user
    this.isAuthenticated = true
  },

  async restore() {
    try {
      const restored = await refreshAccessToken()
      if (!restored) {
        this.clear()
        return false
      }
      const session = await api.get('/admin/auth/session')
      this.user = session.user
      this.isAuthenticated = true
      return true
    } catch (error) {
      this.clear()
      throw error
    }
  },

  async logout() {
    try {
      await api.post('/admin/auth/logout', {}, { csrf: true })
    } finally {
      this.clear()
    }
  },

  clear() {
    setAccessToken(null)
    this.user = null
    this.isAuthenticated = false
  },
})
