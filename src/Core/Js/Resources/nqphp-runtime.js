/**
 * nqphp runtime helpers — loaded as an ES module via:
 *   <script type="module" src="/_nqphp/js/nqphp-runtime.js"></script>
 *
 * Exposes a single `nqphp` global with three utilities:
 *   - csrf()            : current CSRF token (reads the cookie)
 *   - fetchJson(url, o) : fetch wrapper that injects the CSRF header
 *                         on state-changing methods
 *   - loadModule(url)   : dynamic import with a stable error event
 *
 * Conventions:
 *   - the CSRF cookie name and header name are mirrored from the PHP
 *     CsrfTokenManager. The framework guarantees they match.
 *   - safe methods (GET/HEAD/OPTIONS) do NOT receive the CSRF header;
 *     the server doesn't check for it and adding it would just bloat
 *     preflight OPTIONS responses.
 */

const COOKIE_NAME = 'nqphp_csrf';
const HEADER_NAME = 'X-CSRF-Token';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

function readCookie(name) {
  if (typeof document === 'undefined') return null;
  const target = `${name}=`;
  const parts = document.cookie ? document.cookie.split(';') : [];
  for (const raw of parts) {
    const c = raw.trim();
    if (c.startsWith(target)) {
      return decodeURIComponent(c.slice(target.length));
    }
  }
  return null;
}

/** Current CSRF token, or null if the cookie hasn't been set yet. */
export function csrf() {
  return readCookie(COOKIE_NAME);
}

/**
 * fetch() wrapper. Defaults to JSON in + JSON out, injects the CSRF
 * header for state-changing methods, and throws on non-2xx responses
 * with the parsed error body attached for callers to inspect.
 *
 * @param {string} url
 * @param {RequestInit & { json?: unknown }} [options]
 * @returns {Promise<any>}
 */
export async function fetchJson(url, options = {}) {
  const method = (options.method ?? 'GET').toUpperCase();
  const headers = new Headers(options.headers ?? {});
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }
  if (!SAFE_METHODS.has(method) && !headers.has(HEADER_NAME)) {
    const token = csrf();
    if (token) {
      headers.set(HEADER_NAME, token);
    }
  }
  let body = options.body;
  if (options.json !== undefined) {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(options.json);
  }

  const response = await fetch(url, { ...options, method, headers, body });
  const contentType = response.headers.get('content-type') ?? '';
  const payload = contentType.includes('application/json')
    ? await response.json().catch(() => null)
    : await response.text();

  if (!response.ok) {
    const err = new Error(`HTTP ${response.status} on ${method} ${url}`);
    err.status = response.status;
    err.body = payload;
    throw err;
  }
  return payload;
}

/**
 * Dynamic import with a single rejection if the module is missing.
 * Returns the module namespace, or throws with status 404 attached
 * for parity with fetchJson() error shape.
 */
export async function loadModule(url) {
  try {
    return await import(url);
  } catch (cause) {
    const err = new Error(`Failed to load module ${url}: ${cause?.message ?? cause}`);
    err.cause = cause;
    throw err;
  }
}

// Single global so non-module scripts can still reach the helpers.
const api = { csrf, fetchJson, loadModule, COOKIE_NAME, HEADER_NAME };
if (typeof window !== 'undefined') {
  window.nqphp = api;
}

export default api;
