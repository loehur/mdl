var BASE = new URL('../', self.location).href;
var CACHE_NAME = 'mdl-laundry-v3';
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
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) {
          return caches.delete(key);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }

  try {
    var u = new URL(event.request.url);
    if (u.hostname === 'localhost' || u.hostname === '127.0.0.1') {
      return;
    }
    // Navigasi halaman PHP — biarkan browser handle langsung (hindari error SW di Operasi/dll.)
    if (event.request.mode === 'navigate') {
      return;
    }
  } catch (e) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .catch(function () {
        return caches.match(event.request);
      })
      .then(function (response) {
        if (response) {
          return response;
        }
        return new Response('', {
          status: 504,
          statusText: 'Offline'
        });
      })
  );
});
