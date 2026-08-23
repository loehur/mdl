<script>
(function () {
  if (!('serviceWorker' in navigator)) return;

  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?= URL::BASE_URL ?>Pwa/sw', {
      scope: '<?= URL::BASE_URL ?>',
      updateViaCache: 'none'
    }).catch(function (err) {
      console.error('PWA service worker gagal:', err);
    });
  });
})();
</script>
