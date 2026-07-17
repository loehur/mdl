<?php
$p = $data['data_pelanggan'];
$id = (int) $p['id_pelanggan'];
$active = $data['active'] ?? 'home';
$page = $data['page'] ?? 'home';
$extra = $data['extra'] ?? '';
$cabang = $data['cabang'] ?? [];
$base = $data['base'];
$assets = $data['assets'];
$kodeCabang = $cabang['kode_cabang'] ?? '00';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <title>MDL · <?= strtoupper(htmlspecialchars($p['nama_pelanggan'])) ?></title>
  <link rel="icon" href="<?= $assets ?>icon/logo.png">
  <link rel="apple-touch-icon" href="<?= $assets ?>icon/logo.png">
  <link rel="manifest" href="<?= $base ?>J/manifest/<?= $id ?>">
  <meta name="theme-color" content="#0B3D3A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="MDL">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= $assets ?>css/j-customer.css?v=7">
</head>
<body>
<div class="j-app"
     id="jApp"
     data-base="<?= htmlspecialchars($base) ?>"
     data-id="<?= $id ?>"
     data-page="<?= htmlspecialchars($page) ?>"
     data-extra="<?= htmlspecialchars((string) $extra) ?>">
  <header class="j-top">
    <div class="j-top-row">
      <img class="j-logo" src="<?= $assets ?>icon/logo.png" alt="MDL">
      <div class="j-brand-text">
        <strong>MDL - <?= htmlspecialchars($kodeCabang) ?></strong>
        <span>Customer Portal</span>
      </div>
    </div>
  </header>

  <main class="j-main" id="jContent">
    <div class="j-loading" id="jLoading">
      <div class="j-spinner"></div>
      <span>Memuat data...</span>
    </div>
  </main>

  <nav class="j-nav" aria-label="Menu utama">
    <a href="<?= $base ?>J/<?= $id ?>" data-nav="home" class="<?= $active === 'home' ? 'active' : '' ?>">
      <i class="fas fa-home"></i> Beranda
    </a>
    <a href="<?= $base ?>J/tagihan/<?= $id ?>" data-nav="tagihan" class="<?= $active === 'tagihan' ? 'active' : '' ?>">
      <i class="fas fa-receipt"></i> Tagihan
    </a>
    <a href="<?= $base ?>J/saldo/<?= $id ?>" data-nav="saldo" class="<?= $active === 'saldo' ? 'active' : '' ?>">
      <i class="fas fa-wallet"></i> Saldo
    </a>
    <a href="<?= $base ?>J/paket/<?= $id ?>" data-nav="paket" class="<?= $active === 'paket' ? 'active' : '' ?>">
      <i class="fas fa-box-open"></i> Paket
    </a>
  </nav>
</div>

<!-- Toast -->
<div id="jToast" class="j-toast" role="status"></div>

<!-- Modal Bayar -->
<div class="modal fade" id="jModalBayar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content j-modal">
      <div class="modal-header">
        <h5 class="modal-title">Pembayaran Tagihan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Metode Pembayaran</label>
        <select class="form-select mb-3" id="jMetodeBayar"></select>
        <label class="form-label">Pilih Tagihan</label>
        <div id="jListTagihanBayar" class="j-pay-list"></div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <strong>Total Bayar</strong>
          <strong id="jTotalBayarModal">Rp0</strong>
        </div>
        <div class="text-danger small mt-2" id="jBayarStatus"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="jBtnSubmitBayar">Bayar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal QR -->
<div class="modal fade" id="jModalQR" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-modal">
      <div class="modal-header">
        <h6 class="modal-title">Scan QRIS</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <div id="jQrcode" class="d-flex justify-content-center mb-3"></div>
        <p class="mb-0 fw-bold" id="jQrTotal"></p>
        <p class="mb-0 small text-muted" id="jQrNama"></p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-warning btn-sm" id="jBtnCekStatusQR"><i class="fas fa-sync"></i> Cek Status</button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Status Transfer -->
<div class="modal fade" id="jModalStatus" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content j-modal">
      <div class="modal-header">
        <h5 class="modal-title">Status Pembayaran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="jStatusModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cancel -->
<div class="modal fade" id="jModalCancel" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-modal border-0">
      <div class="modal-body text-center p-4">
        <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size:2.4rem"></i>
        <h5 class="mb-2">Batalkan Pembayaran?</h5>
        <p class="text-muted mb-2" id="jCancelPaymentInfo"></p>
        <p class="small text-danger mb-3">Data pembayaran akan dihapus.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger" id="jBtnConfirmCancel"><i class="fas fa-trash-alt"></i> Hapus</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= $assets ?>js/j-customer.js?v=2"></script>
<script src="<?= $assets ?>js/j-payment.js?v=1"></script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= $base ?>Pwa/sw', { scope: '<?= $base ?>' }).catch(function () {});
}
</script>
</body>
</html>
