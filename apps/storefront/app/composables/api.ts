export function useStorefrontApi() {
  const requestFetch = import.meta.server ? useRequestFetch() : $fetch
  const requestHeaders = import.meta.server ? useRequestHeaders(['host', 'x-forwarded-host']) : {}
  const serverHost = requestHeaders['x-forwarded-host'] || requestHeaders.host
  const ssrHeaders = import.meta.server && serverHost ? { 'x-forwarded-host': serverHost } : undefined

  async function get<T>(path: string, query?: Record<string, string | number>) {
    return await requestFetch<T>(`/api/backend/storefront/${path}`, {
      query,
      headers: ssrHeaders
    })
  }

  async function send<T>(
    path: string,
    method: 'POST' | 'PATCH' | 'DELETE',
    body?: unknown,
    extraHeaders: Record<string, string> = {}
  ) {
    const headers = new Headers(extraHeaders)
    const csrf = csrfCookie()
    if (csrf) headers.set('X-CSRF-TOKEN', csrf)
    if (body !== undefined) headers.set('Content-Type', 'application/json')

    return await $fetch<T>(`/api/backend/storefront/${path}`, {
      method,
      body: body === undefined ? undefined : JSON.stringify(body),
      headers,
      credentials: 'include'
    })
  }

  return { get, send }
}

function csrfCookie() {
  if (!import.meta.client) return ''

  const entry = document.cookie.split('; ').find((value) => value.startsWith('storefront_csrf='))
  return entry ? decodeURIComponent(entry.slice('storefront_csrf='.length)) : ''
}

export function apiErrorMessage(error: unknown) {
  const failure = error as { data?: { message?: string; errors?: Record<string, string[]> }; message?: string }
  const validation = Object.values(failure.data?.errors ?? {}).flat()[0]
  return validation || failure.data?.message || failure.message || 'Something went wrong. Please try again.'
}

export function unwrapResource<T>(response: { data: T }): T
export function unwrapResource<T>(response: T): T
export function unwrapResource<T>(response: T | { data: T }): T {
  if (response && typeof response === 'object' && 'data' in response) {
    return response.data as T
  }

  return response as T
}
