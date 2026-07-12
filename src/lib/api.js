const API_BASE = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')

export function shareRunUrl(runId) {
  const base = import.meta.env.VITE_PUBLIC_APP_URL || window.location.origin
  return `${base.replace(/\/$/, '')}/runs/${runId}`
}

export async function createRun(launcherSlug, sourceUrl, apiKey = '') {
  const provider = { id: 'openai' }
  if (apiKey.trim()) provider.api_key = apiKey.trim()

  const res = await fetch(`${API_BASE}/api/runs`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ launcher: launcherSlug, source_url: sourceUrl.trim(), provider }),
  })
  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    const msg = body.message || (body.errors ? Object.values(body.errors).flat().join(' ') : null) || `Request failed (${res.status})`
    throw new Error(msg)
  }
  return body
}

export async function fetchRun(runId) {
  const res = await fetch(`${API_BASE}/api/runs/${runId}`, { headers: { Accept: 'application/json' } })
  const json = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new Error(json.message || `Failed to load run (${res.status})`)
  }
  return json.data ?? json
}

/**
 * Subscribe to run progress via SSE. Returns a cleanup function.
 */
export function streamRun(runId, { onSnapshot, onTerminal, onDisconnect }) {
  const url = `${API_BASE}/api/runs/${runId}/stream`
  const source = new EventSource(url)

  const handle = (event) => {
    try {
      const snapshot = JSON.parse(event.data)
      onSnapshot?.(snapshot, event.type)
      if (event.type === 'completed' || event.type === 'failed') {
        onTerminal?.(snapshot, event.type)
        source.close()
      }
    } catch {
      /* ignore malformed events */
    }
  }

  source.addEventListener('progress', handle)
  source.addEventListener('completed', handle)
  source.addEventListener('failed', handle)
  source.onerror = () => {
    source.close()
    onDisconnect?.()
  }

  return () => source.close()
}

export function parseGithubRepo(url) {
  const trimmed = url.trim()
  const match = trimmed.match(/github\.com\/([^/]+)\/([^/#?]+)/i)
  if (!match) return null
  return `${match[1]}/${match[2].replace(/\.git$/, '')}`
}

export function isValidGithubUrl(url) {
  return /^https:\/\/(?:www\.)?github\.com\/[^/\s]+\/[^/\s]+/i.test(url.trim())
}
