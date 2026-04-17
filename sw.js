// =============================================
// Service Worker – المساعد الذّكاليّ
// =============================================
const CACHE_NAME = 'dhakali-v5';
const STATIC_ASSETS = [
  '/',
  '/assets/css/style.css',
  '/assets/css/game-enhanced.css',
  '/assets/js/app.js',
  '/assets/js/game-enhanced.js',
  '/assets/js/game-map-simple.js',
  '/manifest.json',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap',
];

// Install – cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
  );
  self.skipWaiting();
});

// Activate – remove old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch – cache-first for static, network-first for navigation/API/PHP
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip cross-origin requests except fonts/icons
  if (url.origin !== location.origin && !url.hostname.includes('fonts.google') && !url.hostname.includes('cdnjs')) {
    return;
  }

  // Network-first for page navigations (e.g. /?page=login), PHP files, and API routes
  if (
    event.request.mode === 'navigate' ||
    url.pathname.endsWith('.php') ||
    url.pathname.startsWith('/api/')
  ) {
    event.respondWith(
      fetch(event.request).catch(() =>
        caches.match('/').then(r => r || new Response('Service temporarily unavailable', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        }))
      )
    );
    return;
  }

  // Stale-while-revalidate for static assets (serve cached, update in background)
  event.respondWith(
    caches.open(CACHE_NAME).then(cache =>
      cache.match(event.request).then(cached => {
        const networkFetch = fetch(event.request).then(response => {
          if (response.ok) cache.put(event.request, response.clone());
          return response;
        }).catch(err => {
          console.warn('[SW] Static asset fetch failed:', event.request.url, err);
          return cached || new Response('', { status: 503 });
        });
        return cached || networkFetch;
      })
    )
  );
});
