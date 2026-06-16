<link rel="manifest" href="<?= URL::BASE_URL ?>manifest.webmanifest">
<meta name="theme-color" content="#ffc107">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="MDL Laundry">
<link rel="apple-touch-icon" href="<?= URL::IN_ASSETS ?>icon/logo.png">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= URL::BASE_URL ?>sw.js', {
            scope: '<?= URL::BASE_URL ?>'
        });
    });
}
</script>
