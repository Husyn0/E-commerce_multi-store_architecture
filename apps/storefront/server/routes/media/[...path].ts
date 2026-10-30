import { getRouterParam, setResponseHeader, setResponseStatus } from 'h3'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const path = getRouterParam(event, 'path')

  if (
    !path
    || path.includes('..')
    || !/^tenants\/[0-9a-f-]{36}\/(products|categories)\/[A-Za-z0-9._/-]+$/i.test(path)
  ) {
    throw createError({ statusCode: 404, statusMessage: 'Media not found.' })
  }

  let response: Response
  try {
    response = await fetch(new URL(`/storage/${path}`, config.storefrontApiOrigin), {
      method: event.method === 'HEAD' ? 'HEAD' : 'GET',
      redirect: 'error',
    })
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Store media is temporarily unavailable.' })
  }

  setResponseStatus(event, response.status)
  for (const name of ['content-type', 'content-length', 'last-modified', 'etag']) {
    const value = response.headers.get(name)
    if (value) setResponseHeader(event, name, value)
  }
  setResponseHeader(event, 'cache-control', 'public, max-age=3600')
  setResponseHeader(event, 'x-content-type-options', 'nosniff')

  return event.method === 'HEAD' ? null : response.body
})
