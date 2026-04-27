const CACHE_NAME = 'atg-pos-pwa-v1';

const STATIC_ASSETS = [
  '/',
  '/cashier',
  '/manifest.json',
  '/images/atg-icon.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();

  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => true);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cacheName) => cacheName !== CACHE_NAME)
          .map((cacheName) => caches.delete(cacheName))
      );
    })
  );

  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (
    url.pathname.includes('/cashier/cart') ||
    url.pathname.includes('/cashier/checkout') ||
    url.pathname.includes('/backoffice') ||
    url.pathname.includes('/login') ||
    url.pathname.includes('/logout')
  ) {
    return;
  }

  event.respondWith(
    fetch(request).catch(() => {
      return caches.match(request);
    })
  );
});
