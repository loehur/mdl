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
  <link rel="icon" href="<?= $assets ?>icon/j-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= $assets ?>icon/j-icon.svg">
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
  <link rel="stylesheet" href="<?= $assets ?>css/j-customer.css?v=49">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
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
      <span class="j-logo" aria-hidden="true"><i class="fas fa-tshirt"></i></span>
      <div class="j-brand-text">
        <strong>MDL - <?= htmlspecialchars($kodeCabang) ?></strong>
        <span><?= strtoupper(htmlspecialchars($p['nama_pelanggan'])) ?></span>
      </div>
      <a class="j-classic-link" href="<?= $base ?>I/<?= $id ?>?classic=1" title="Mode klasik">
        <i class="fas fa-exchange-alt"></i>
      </a>
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
      <i class="fas fa-wallet"></i> Deposit
    </a>
    <a href="<?= $base ?>J/paket/<?= $id ?>" data-nav="paket" class="<?= $active === 'paket' ? 'active' : '' ?>">
      <i class="fas fa-box-open"></i> Paket
    </a>
    <a href="<?= $base ?>J/kurir/<?= $id ?>" data-nav="kurir" class="<?= $active === 'kurir' ? 'active' : '' ?>">
      <i class="fas fa-motorcycle"></i> Kurir
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

<!-- Modal Konfirmasi Topup -->
<div class="modal fade" id="jModalTopup" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-sheet">
      <div class="j-sheet-body j-sheet-center" style="padding-top:22px">
        <div class="j-alert-ico" style="background:rgba(26,122,110,0.12);color:#1A7A6E"><i class="fas fa-box-open"></i></div>
        <h5 class="j-sheet-title" style="margin:0 0 6px">Konfirmasi topup?</h5>
        <p class="j-sheet-desc" id="jTopupConfirmInfo"></p>
        <p class="j-sheet-warn">Setelah dibuat, bayar di halaman Tagihan.</p>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnConfirmTopup"><i class="fas fa-check"></i> Ya, topup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Batalkan Topup -->
<div class="modal fade" id="jModalCancelTopup" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content j-sheet">
      <div class="j-sheet-body j-sheet-center" style="padding-top:22px">
        <div class="j-alert-ico"><i class="fas fa-trash-alt"></i></div>
        <h5 class="j-sheet-title" style="margin:0 0 6px">Batalkan topup?</h5>
        <p class="j-sheet-desc" id="jCancelTopupInfo"></p>
        <p class="j-sheet-warn">Topup belum lunas akan dihapus permanen.</p>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Tidak</button>
        <button type="button" class="j-sheet-btn danger" id="jBtnConfirmCancelTopup"><i class="fas fa-trash-alt"></i> Ya, batalkan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Topup Saldo Tunai -->
<div class="modal fade" id="jModalSaldoTopup" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker">Saldo</p>
          <h5 class="j-sheet-title">Topup Saldo</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body">
        <label class="j-field-label" for="jSaldoTopupJumlah">Nominal</label>
        <input type="number" id="jSaldoTopupJumlah" class="j-select j-input-amount" min="1000" step="1000" placeholder="0" inputmode="numeric">
        <p class="j-sheet-desc" id="jSaldoTopupHint" style="margin-top:6px"></p>

        <label class="j-field-label" for="jSaldoTopupMetode" style="margin-top:12px">Metode pembayaran</label>
        <div class="j-select-wrap">
          <select id="jSaldoTopupMetode" class="j-select"></select>
        </div>
        <div class="j-pay-status" id="jSaldoTopupStatus"></div>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnSubmitSaldoTopup">Lanjut bayar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Kurir: pilih lokasi -->
<div class="modal fade" id="jModalKurirLokasi" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker" id="jKurirLokasiKicker">Sameday</p>
          <h5 class="j-sheet-title" id="jKurirLokasiTitle">Pilih lokasi</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body">
        <p class="j-sheet-desc" id="jKurirJenisHint" style="margin-top:0"></p>
        <div id="jKurirLokasiPick">
          <p class="j-sheet-desc" style="margin-top:0">Pilih alamat untuk permintaan ini.</p>
          <div class="j-kurir-lokasi-list" id="jKurirLokasiList">
            <div class="j-kurir-sales-empty">Memuat lokasi…</div>
          </div>
          <button type="button" class="j-btn j-btn-soft j-btn-block" id="jBtnKurirLokasiAdd" style="margin-top:12px">
            <i class="fas fa-plus"></i> Tambah lokasi
          </button>
        </div>
        <div id="jKurirLokasiForm" hidden>
          <p class="j-sheet-desc" id="jKurirLokasiFormDesc" style="margin-top:0">Isi nama &amp; detail, lalu set titik di peta.</p>
          <input type="hidden" id="jLokasiEditId" value="">
          <label class="j-field-label" for="jLokasiNama">Nama</label>
          <input type="text" id="jLokasiNama" class="j-select" maxlength="50" placeholder="Rumah, Kantor, Kos">
          <label class="j-field-label" for="jLokasiDetail" style="margin-top:10px">Detail</label>
          <input type="text" id="jLokasiDetail" class="j-select" maxlength="255" placeholder="Perum. Graha Nusa, No. 31, Pagar Hitam atau Merek Usaha">
          <div class="j-kurir-map-tools">
            <button type="button" class="j-btn j-btn-soft" id="jBtnLokasiGps">
              <i class="fas fa-location-arrow"></i> Titik saya
            </button>
            <small id="jLokasiMapHint">Klik peta untuk geser pin</small>
          </div>
          <div id="jKurirMap" class="j-kurir-map" aria-label="Peta lokasi"></div>
          <input type="hidden" id="jLokasiLatt" value="">
          <input type="hidden" id="jLokasiLongt" value="">
        </div>
      </div>
      <div class="j-sheet-foot" id="jKurirLokasiFootPick">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnKurirLokasiNext">
          Lanjut
        </button>
      </div>
      <div class="j-sheet-foot" id="jKurirLokasiFootForm" hidden>
        <button type="button" class="j-sheet-btn ghost" id="jBtnKurirLokasiBack">Kembali</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnKurirLokasiSave">
          <i class="fas fa-save"></i> <span id="jBtnKurirLokasiSaveLabel">Simpan</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Kurir Antar: pilih item -->
<div class="modal fade" id="jModalKurirAntar" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker" id="jKurirAntarKicker">Sameday</p>
          <h5 class="j-sheet-title">Antar laundry</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body">
        <p class="j-sheet-desc" style="margin-top:0">Mengantar Pakaian dari Laundry ke Lokasi Anda. Pilih item yang ingin diantar.</p>
        <div class="j-kurir-lokasi-chosen" id="jKurirAntarLokasi"></div>
        <div class="j-kurir-sales" id="jKurirSalesBox">
          <div class="j-kurir-sales-empty">Memuat item…</div>
        </div>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnSubmitKurirAntar">
          <i class="fas fa-truck"></i> <span id="jBtnSubmitKurirAntarLabel">Kirim permintaan</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Kurir Jemput: konfirmasi -->
<div class="modal fade" id="jModalKurirJemput" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content j-sheet">
      <div class="j-sheet-body j-sheet-center" style="padding-top:22px">
        <div class="j-sheet-ico"><i class="fas fa-hand-holding"></i></div>
        <p class="j-sheet-kicker" id="jKurirJemputKicker" style="margin:0 0 4px">Sameday</p>
        <h5 class="j-sheet-title" style="margin:0 0 6px">Jemput laundry?</h5>
        <p class="j-sheet-desc">Menjemput Pakaian dari Lokasi Anda dan dikirimkan ke Laundry. Item dipilih petugas saat selesai.</p>
        <div class="j-kurir-lokasi-chosen" id="jKurirJemputLokasi"></div>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnConfirmKurirJemput">
          <i class="fas fa-check"></i> <span id="jBtnConfirmKurirJemputLabel">Ya, jemput</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Kurir Instant: pilih kurir -->
<div class="modal fade" id="jModalKurirCourier" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content j-sheet">
      <div class="j-sheet-head">
        <div>
          <p class="j-sheet-kicker">Instant</p>
          <h5 class="j-sheet-title">Pilih kurir</h5>
        </div>
        <button type="button" class="j-sheet-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="j-sheet-body">
        <p class="j-sheet-desc" style="margin-top:0">Ongkir sesuai tarif Gojek/Grab. Bayar dulu sebelum order jalan.</p>
        <div class="j-kurir-lokasi-chosen" id="jKurirCourierLokasi"></div>
        <div class="j-kurir-sales" id="jKurirCourierBox">
          <div class="j-kurir-sales-empty">Memuat kurir…</div>
        </div>
        <div class="j-field" id="jKurirCourierPayWrap" style="margin-top:12px;display:none">
          <label class="j-field-label" for="jKurirCourierMetode">Metode pembayaran</label>
          <select id="jKurirCourierMetode" class="j-select">
            <option value="QRIS">QRIS</option>
          </select>
          <p class="j-sheet-desc" id="jKurirCourierSaldoHint" style="margin:6px 0 0"></p>
        </div>
      </div>
      <div class="j-sheet-foot">
        <button type="button" class="j-sheet-btn ghost" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="j-sheet-btn primary" id="jBtnSubmitKurirCourier" disabled>
          <i class="fas fa-wallet"></i> <span id="jBtnSubmitKurirCourierLabel">Bayar ongkir</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= URL::EX_ASSETS ?>js/qrcode.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/html2canvas.min.js"></script>
<script src="<?= $assets ?>js/j-customer.js?v=21"></script>
<script src="<?= $assets ?>js/j-payment.js?v=5"></script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= $base ?>Pwa/sw', { scope: '<?= $base ?>' }).catch(function () {});
}
</script>
</body>
</html>
