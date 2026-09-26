/**
 * Edge cache for public GET JSON. POST reservations pass through.
 * Locale is Accept-Language; Origin is in the cache key so CORS stays correct.
 * Outbound Cache-Control is no-store so the zone CDN cannot mix locales.
 */
const TTL = 60;

export default {
  async fetch(request, _env, ctx) {
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return fetch(request);
    }

    const cache = caches.default;
    const key = cacheKey(request);
    const hit = await cache.match(key);

    if (hit) {
      return clientResponse(hit, 'HIT');
    }

    const origin = await fetch(request);

    if (!origin.ok) {
      return origin;
    }

    const body = await origin.arrayBuffer();
    const stored = new Response(body, {
      status: origin.status,
      headers: storeHeaders(origin.headers),
    });
    ctx.waitUntil(cache.put(key, stored.clone()));
    return clientResponse(stored, 'MISS');
  },
};

function cacheKey(request) {
  const url = new URL(request.url);
  url.searchParams.set('_al', request.headers.get('Accept-Language') || '');
  url.searchParams.set('_or', request.headers.get('Origin') || '');
  return new Request(url.toString(), { method: 'GET' });
}

function storeHeaders(originHeaders) {
  const headers = new Headers(originHeaders);
  headers.set('Cache-Control', `public, max-age=${TTL}`);
  return headers;
}

function clientResponse(response, edge) {
  const headers = new Headers(response.headers);
  headers.set('Cache-Control', 'private, no-store');
  headers.set('X-Edge-Cache', edge);
  return new Response(response.body, { status: response.status, headers });
}
