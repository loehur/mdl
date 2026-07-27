<?php
$id = (int) $data['data_pelanggan']['id_pelanggan'];
$active = $data['active'] ?? 'home';
$base = $data['base'];
?>
  </main>

  <nav class="j-nav" aria-label="Menu utama">
    <a href="<?= $base ?>J/<?= $id ?>" class="<?= $active === 'home' ? 'active' : '' ?>">
      <i class="fas fa-home"></i> Beranda
    </a>
    <a href="<?= $base ?>J/tagihan/<?= $id ?>" class="<?= $active === 'tagihan' ? 'active' : '' ?>">
      <i class="fas fa-receipt"></i> Tagihan
    </a>
    <a href="<?= $base ?>J/saldo/<?= $id ?>" class="<?= $active === 'saldo' ? 'active' : '' ?>">
      <i class="fas fa-wallet"></i> Deposit
    </a>
    <a href="<?= $base ?>J/paket/<?= $id ?>" class="<?= $active === 'paket' ? 'active' : '' ?>">
      <i class="fas fa-box-open"></i> Paket
    </a>
  </nav>
</div>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= $base ?>Pwa/sw', { scope: '<?= $base ?>' }).catch(function () {});
}
</script>
</body>
</html>
