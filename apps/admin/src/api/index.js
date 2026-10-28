const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')
let accessToken = null
let refreshRequest = null

export function setAccessToken(token) {
  accessToken = token
}

function csrfToken() {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith('admin_csrf='))

  return cookie ? decodeURIComponent(cookie.slice('admin_csrf='.length)) : ''
}

function headersFor(options) {
  const headers = new Headers(options.headers || {})

  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  if (options.authenticated !== false && accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }
  if (options.csrf) {
    const token = csrfToken()
    if (token) headers.set('X-CSRF-TOKEN', token)
  }

  return headers
}

async function send(path, options = {}, retry = true) {
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    credentials: 'include',
    headers: headersFor(options),
  })

  if (response.status === 401 && retry && options.authenticated !== false && path !== '/admin/auth/refresh') {
    const refreshed = await refreshAccessToken()
    if (refreshed) return send(path, options, false)
  }

  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json')
    ? await response.json()
    : await response.text()

  if (!response.ok) {
    const validationMessage = payload && typeof payload === 'object'
      ? Object.values(payload.errors || {}).flat().find(Boolean)
      : null
    const message = validationMessage || payload?.message || `Request failed (${response.status}).`
    const error = new Error(message)
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export async function refreshAccessToken() {
  if (refreshRequest) return refreshRequest

  refreshRequest = performTokenRefresh()
  try {
    return await refreshRequest
  } finally {
    refreshRequest = null
  }
}

async function performTokenRefresh() {
  const token = csrfToken()
  if (!token) {
    setAccessToken(null)
    return false
  }

  try {
    const response = await send('/admin/auth/refresh', {
      method: 'POST',
      authenticated: false,
      headers: { 'X-CSRF-TOKEN': token },
    }, false)
    setAccessToken(response.access_token)
    return true
  } catch (error) {
    if (error.status === 401) {
      setAccessToken(null)
      return false
    }
    throw error
  }
}

function encodeQuery(params = {}) {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') query.set(key, value)
  }
  const result = query.toString()
  return result ? `?${result}` : ''
}

export const api = {
  get(path, options = {}) {
    return send(`${path}${encodeQuery(options.query)}`, { authenticated: options.authenticated })
  },
  post(path, body, options = {}) {
    return send(path, {
      method: 'POST',
      body: JSON.stringify(body),
      authenticated: options.authenticated,
      csrf: options.csrf,
      headers: options.headers,
    })
  },
  patch(path, body, options = {}) {
    return send(path, {
      method: 'PATCH',
      body: JSON.stringify(body),
      csrf: options.csrf,
    })
  },
  put(path, body, options = {}) {
    return send(path, {
      method: 'PUT',
      body: JSON.stringify(body),
      csrf: options.csrf,
    })
  },
  delete(path, options = {}) {
    return send(path, { method: 'DELETE', csrf: options.csrf })
  },
}
