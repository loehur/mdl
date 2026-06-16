var BASE = new URL('../', self.location).href;
var CACHE_NAME = 'mdl-laundry-v1';
var PRECACHE = [
  BASE + 'Pwa/manifest',
  BASE + 'in_assets/icon/icon-192.png',
  BASE + 'in_assets/icon/logo.png'
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
  event.respondWith(
    fetch(event.request).catch(function () {
      return caches.match(event.request);
    })
  );
});
