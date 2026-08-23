var CACHE_NAME = 'mdl-laundry-v4';

self.addEventListener('install', function (event) {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) {
          return caches.delete(key);
        })
      );
    })
  );
});
