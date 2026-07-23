var BASE = new URL('../', self.location).href;
var CACHE_NAME = 'mdl-laundry-v2';
var PRECACHE = [
  BASE + 'Pwa/manifest',
  BASE + 'in_assets/icon/j-icon.svg'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  // Jangan intercept print bridge / local print server (Chrome PNA + SW)
  try {
    var u = new URL(event.request.url);
    if (u.hostname === 'localhost' || u.hostname === '127.0.0.1') {
      return;
    }
  } catch (e) { }
  event.respondWith(
    fetch(event.request).catch(function () {
      return caches.match(event.request);
    })
  );
});
