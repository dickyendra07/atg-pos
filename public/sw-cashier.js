const CACHE_NAME = 'atg-pos-cashier-v1';

const CORE_ASSETS = [
  '/cashier/login',
  '/manifest-cashier.json',
  '/images/atg-icon.png',
  '/images/login-cover.jpg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(CORE_ASSETS))
      .catch(() => null)
  );

  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys
        .filter((key) => key.startsWith('atg-pos-cashier-') && key !== CACHE_NAME)
        .map((key) => caches.delete(key))
    ))
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

  // Jangan cache flow transaksi/API dinamis. Tetap online-first supaya data POS aman.
  const isCashierPage = url.pathname === '/cashier/login'
    || url.pathname === '/cashier/select-outlet'
    || url.pathname === '/cashier';

  const isStaticAsset = url.pathname.startsWith('/images/')
    || url.pathname === '/manifest-cashier.json';

  if (!isCashierPage && !isStaticAsset) {
    return;
  }

  event.respondWith(
    fetch(request)
      .then((response) => {
        const cloned = response.clone();

        caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, cloned).catch(() => null);
        });

        return response;
      })
      .catch(() => caches.match(request))
  );
});
