import {
  defineEventHandler,
  getRequestHeader,
  getRequestURL,
  getRouterParam,
  readRawBody,
  setResponseHeader,
  setResponseStatus
} from 'h3'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const apiOrigin = config.storefrontApiOrigin
  const path = getRouterParam(event, 'path')
  const requestUrl = getRequestURL(event)
  const tenantHost = getRequestHeader(event, 'host')

  if (!path || !tenantHost) {
    throw createError({ statusCode: 400, statusMessage: 'A storefront host is required.' })
  }
  if (!path.startsWith('storefront/') || path.includes('..')) {
    throw createError({ statusCode: 404, statusMessage: 'Storefront API route not found.' })
  }
  if (typeof apiOrigin !== 'string' || !apiOrigin) {
    throw createError({ statusCode: 500, statusMessage: 'Storefront API origin is not configured.' })
  }
  const normalizedHost = tenantHost.split(',')[0]?.trim()
  if (!normalizedHost) {
    throw createError({ statusCode: 400, statusMessage: 'A storefront host is required.' })
  }

  const headers = new Headers()
  for (const name of ['accept', 'content-type', 'idempotency-key', 'x-csrf-token']) {
    const value = getRequestHeader(event, name)
    if (value) headers.set(name, value)
  }
  const cookies = getRequestHeader(event, 'cookie')
    ?.split(';')
    .map((cookie) => cookie.trim())
    .filter((cookie) => /^(storefront_cart|storefront_csrf|storefront_session)=/.test(cookie))
  if (cookies?.length) headers.set('cookie', cookies.join('; '))
  headers.set('host', normalizedHost)
  headers.set('x-forwarded-host', normalizedHost)

  const body = ['GET', 'HEAD'].includes(event.method) ? undefined : await readRawBody(event)
  const target = new URL(`/api/${path}${requestUrl.search}`, apiOrigin)

  try {
    const response = await $fetch.raw(target.toString(), {
      method: event.method,
      headers,
      body,
      retry: 0,
      timeout: 2000,
      ignoreResponseError: true
    })

    setResponseStatus(event, response.status)
    for (const name of ['content-type', 'cache-control', 'vary', 'etag']) {
      const value = response.headers.get(name)
      if (value) setResponseHeader(event, name, value)
    }
    const cookies = response.headers.getSetCookie?.()
    if (cookies?.length) setResponseHeader(event, 'set-cookie', cookies)

    return response._data
  } catch (error) {
    console.error('Storefront API proxy request failed.', error)
    throw createError({
      statusCode: 503,
      statusMessage: 'The store service is temporarily unavailable.'
    })
  }
})
