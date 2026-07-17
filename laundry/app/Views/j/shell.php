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
  <link rel="stylesheet" href="<?= $assets ?>css/j-customer.css?v=11">
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
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker">Checkout</p>
          <h5 class="j-sheet-title">Pembayaran Tagihan</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body">
        <label class="j-field-label" for="jMetodeBayar">Metode pembayaran</label>
        <div class="j-select-wrap">
          <select id="jMetodeBayar" class="j-select"></select>
        </div>

        <label class="j-field-label">Pilih tagihan</label>
        <div id="jListTagihanBayar" class="j-pay-list"></div>

        <div class="j-pay-total">
          <span>Total bayar</span>
          <strong id="jTotalBayarModal">Rp0</strong>
        </div>
        <div class="j-pay-status" id="jBayarStatus"></div>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnSubmitBayar">Bayar sekarang</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal QR -->
<div class="modal fade" id="jModalQR" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker">QRIS</p>
          <h5 class="j-sheet-title">Scan untuk bayar</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body j-sheet-center">
        <div id="jQrcode" class="j-qr-box"></div>
        <p class="j-qr-total" id="jQrTotal"></p>
        <p class="j-qr-nama" id="jQrNama"></p>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnCekStatusQR"><i class="fas fa-sync"></i> Cek status</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Status Transfer -->
<div class="modal fade" id="jModalStatus" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker">Transfer</p>
          <h5 class="j-sheet-title">Status pembayaran</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body" id="jStatusModalBody"></div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn primary" data-bs-dismiss="modal" style="flex:1">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cancel -->
<div class="modal fade" id="jModalCancel" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-sheet">
      <div class="j-sheet-body j-sheet-center" style="padding-top:22px">
        <div class="j-alert-ico"><i class="fas fa-exclamation"></i></div>
        <h5 class="j-sheet-title" style="margin:0 0 6px">Batalkan pembayaran?</h5>
        <p class="j-sheet-desc" id="jCancelPaymentInfo"></p>
        <p class="j-sheet-warn">Data pembayaran akan dihapus.</p>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn danger" id="jBtnConfirmCancel"><i class="fas fa-trash-alt"></i> Hapus</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= $assets ?>js/j-customer.js?v=2"></script>
<script src="<?= $assets ?>js/j-payment.js?v=3"></script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= $base ?>Pwa/sw', { scope: '<?= $base ?>' }).catch(function () {});
}
</script>
</body>
</html>
