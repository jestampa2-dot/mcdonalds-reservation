import axios from 'axios'

const CACHE_TTL = 60 * 1000
const MAX_VISIBLE_LINKS = 12

const pageCache = new Map()
const pendingRequests = new Map()

let currentVersion = null
let isInstalled = false
let warmupQueued = false

const clonePayload = (payload) => JSON.parse(JSON.stringify(payload))

const normalizeUrl = (url) => {
  const absoluteUrl = new URL(url, window.location.origin)
  absoluteUrl.hash = ''

  return absoluteUrl.href
}

const isSameOriginPage = (url) => {
  try {
    const absoluteUrl = new URL(url, window.location.origin)

    return absoluteUrl.origin === window.location.origin
  } catch {
    return false
  }
}

const isFresh = (entry) => entry && (Date.now() - entry.timestamp) < CACHE_TTL

const readInitialPage = () => {
  const appElement = document.getElementById('app')

  if (!appElement?.dataset?.page) {
    return null
  }

  try {
    return JSON.parse(appElement.dataset.page)
  } catch {
    return null
  }
}

const isCacheableRequest = (config) => {
  const method = String(config.method ?? 'get').toLowerCase()
  const headers = config.headers ?? {}

  return method === 'get'
    && Boolean(headers['X-Inertia'] || headers['x-inertia'])
    && Boolean(config.url)
}

const cacheResponse = (url, response) => {
  pageCache.set(url, {
    data: clonePayload(response.data),
    headers: { ...response.headers },
    status: response.status,
    timestamp: Date.now(),
  })
}

const buildCachedAdapter = (config, cachedEntry) => async () => ({
  data: clonePayload(cachedEntry.data),
  status: cachedEntry.status,
  statusText: 'OK',
  headers: { ...cachedEntry.headers },
  config,
  request: { fromCache: true },
})

const prefetchHeaders = () => ({
  Accept: 'text/html, application/xhtml+xml',
  'X-Requested-With': 'XMLHttpRequest',
  'X-Inertia': true,
  ...(currentVersion ? { 'X-Inertia-Version': currentVersion } : {}),
})

export const prefetchPage = async (url) => {
  if (typeof window === 'undefined' || !url || !isSameOriginPage(url)) {
    return
  }

  const key = normalizeUrl(url)
  const cachedEntry = pageCache.get(key)

  if (isFresh(cachedEntry) || pendingRequests.has(key) || key === normalizeUrl(window.location.href)) {
    return
  }

  const request = axios.get(key, {
    headers: prefetchHeaders(),
  }).then((response) => {
    if (response.headers?.['x-inertia']) {
      cacheResponse(key, response)
    }
  }).catch(() => {
    // Silent by design: prefetching should never interrupt navigation.
  }).finally(() => {
    pendingRequests.delete(key)
  })

  pendingRequests.set(key, request)

  await request
}

const warmVisibleLinks = () => {
  const anchors = Array.from(document.querySelectorAll('a[href]'))
    .filter((anchor) => {
      const href = anchor.getAttribute('href')

      if (!href || href.startsWith('#') || !isSameOriginPage(anchor.href)) {
        return false
      }

      const rect = anchor.getBoundingClientRect()

      return rect.width > 0
        && rect.height > 0
        && rect.bottom >= 0
        && rect.top <= window.innerHeight
    })
    .slice(0, MAX_VISIBLE_LINKS)

  anchors.forEach((anchor) => {
    prefetchPage(anchor.href)
  })
}

const queueWarmVisibleLinks = () => {
  if (warmupQueued) {
    return
  }

  warmupQueued = true

  const schedule = window.requestIdleCallback
    ? window.requestIdleCallback.bind(window)
    : (callback) => window.setTimeout(callback, 180)

  schedule(() => {
    warmupQueued = false
    warmVisibleLinks()
  })
}

const handleLinkIntent = (event) => {
  const anchor = event.target?.closest?.('a[href]')

  if (!anchor) {
    return
  }

  prefetchPage(anchor.href)
}

export const installFastNavigation = () => {
  if (typeof window === 'undefined' || isInstalled) {
    return
  }

  isInstalled = true
  currentVersion = readInitialPage()?.version ?? null

  axios.interceptors.request.use((config) => {
    if (!isCacheableRequest(config)) {
      return config
    }

    const cacheKey = normalizeUrl(config.url)
    const cachedEntry = pageCache.get(cacheKey)

    if (isFresh(cachedEntry)) {
      config.adapter = buildCachedAdapter(config, cachedEntry)
    }

    return config
  })

  axios.interceptors.response.use((response) => {
    if (!isCacheableRequest(response.config) || !response.headers?.['x-inertia']) {
      return response
    }

    cacheResponse(normalizeUrl(response.config.url), response)

    return response
  })

  document.addEventListener('mouseover', handleLinkIntent, true)
  document.addEventListener('focusin', handleLinkIntent, true)
  document.addEventListener('inertia:navigate', (event) => {
    currentVersion = event.detail?.page?.version ?? currentVersion
    queueWarmVisibleLinks()
  })

  window.addEventListener('load', queueWarmVisibleLinks, { once: true })
  queueWarmVisibleLinks()
}
