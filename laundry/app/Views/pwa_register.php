<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= URL::BASE_URL ?>Pwa/sw', {
        scope: '<?= URL::BASE_URL ?>'
    }).catch(function (err) {
        console.error('PWA service worker gagal:', err);
    });
}
</script>
