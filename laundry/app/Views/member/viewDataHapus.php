<?php
$rows = is_array($data['data_manual'] ?? null) ? $data['data_manual'] : [];
$kasAll = is_array($data['kas'] ?? null) ? $data['kas'] : [];
$total = count($rows);

$statusMutasiLabel = function ($sts) {
  $sts = (string) $sts;
  foreach ($this->dStatusMutasi as $st) {
    if ((string) ($st['id_status_mutasi'] ?? '') === $sts) {
      return (string) ($st['status_mutasi'] ?? '');
    }
  }
  return 'Status ' . $sts;
};
?>
<div id="hd-root">
  <style>
    #hd-root {
      --hd-ink: #0f172a;
      --hd-muted: #1e293b;
      --hd-line: #cbd5e1;
      --hd-green: #16a34a;
      --hd-green-deep: #15803d;
      --hd-yellow: #f59e0b;
      --hd-yellow-deep: #d97706;
      --hd-red: #dc2626;
      --hd-red-deep: #b91c1c;
      color: var(--hd-ink);
      font-family: 'fontku', 'Segoe UI', sans-serif;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }
    #hd-root, #hd-root * { border-radius: 0 !important; box-sizing: border-box; }
    #hd-root .hd-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 10px;
      padding: 10px 12px;
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #hd-root .hd-toolbar__title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }
    #hd-root .hd-toolbar__title .hd-ico {
      width: 28px; height: 28px;
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--hd-yellow); color: #111;
      border: 1px solid var(--hd-yellow-deep);
    }
    #hd-root .hd-toolbar__meta {
      font-size: 0.75rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--hd-muted);
    }
    #hd-root .hd-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      border: 1px solid transparent;
      padding: 8px 12px;
      font-size: 0.84rem;
      font-weight: 900;
      cursor: pointer;
      line-height: 1.1;
      user-select: none;
    }
    #hd-root .hd-btn--danger {
      background: linear-gradient(180deg, var(--hd-red), var(--hd-red-deep));
      border-color: var(--hd-red-deep);
      color: #fff;
    }
    #hd-root .hd-btn--danger:hover { filter: brightness(1.05); color: #fff; }
    #hd-root .hd-btn--ok {
      background: linear-gradient(180deg, var(--hd-green), var(--hd-green-deep));
      border-color: var(--hd-green-deep);
      color: #fff;
    }
    #hd-root .hd-btn--ok:hover { filter: brightness(1.05); color: #fff; }
    #hd-root .hd-btn--ghost {
      background: #fff;
      border-color: var(--hd-line);
      color: var(--hd-ink);
    }
    #hd-root .hd-btn--sm { padding: 6px 10px; font-size: 0.78rem; }
    #hd-root .hd-empty {
      border: 1px solid #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      padding: 24px 14px;
      text-align: center;
      font-weight: 900;
      font-size: 0.9rem;
    }
    #hd-root .hd-empty i { color: var(--hd-green); margin-right: 8px; }
    #hd-root .hd-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      width: 100%;
    }
    @media (min-width: 980px) {
      #hd-root .hd-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (min-width: 1100px) {
      #hd-root .hd-grid {
        grid-template-columns: repeat(auto-fill, 460px);
      }
      #hd-root .hd-card { width: 460px; max-width: 100%; }
    }
    #hd-root .hd-card {
      border: 1px solid #fcd34d;
      background: #fff;
      overflow: hidden;
      min-width: 0;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }
    #hd-root .hd-card__head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
      padding: 10px 12px;
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
      color: #111;
    }
    #hd-root .hd-card__head h3 {
      margin: 0;
      font-size: 0.92rem;
      font-weight: 900;
      letter-spacing: -0.02em;
    }
    #hd-root .hd-card__head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
    }
    #hd-root .hd-card__body { padding: 10px 12px; }
    #hd-root .hd-meta {
      font-size: 0.82rem;
      font-weight: 750;
      color: var(--hd-muted);
      margin-bottom: 8px;
      line-height: 1.4;
    }
    #hd-root .hd-amount {
      font-size: 0.95rem;
      font-weight: 900;
      color: #111;
      white-space: nowrap;
    }
    #hd-root .hd-pay {
      margin-top: 0;
      margin-bottom: 8px;
      padding: 8px 10px;
      border: 1px solid #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #hd-root .hd-pay__label {
      font-size: 0.7rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--hd-muted);
      margin-bottom: 4px;
    }
    #hd-root .hd-pay__row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      font-size: 0.8rem;
      font-weight: 750;
      padding: 1px 0;
      line-height: 1.4;
    }
    #hd-root .hd-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 4px;
    }

    #hdConfirmModal.hd-modal {
      position: fixed;
      inset: 0;
      z-index: 5600;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #hdConfirmModal.hd-modal.is-open { display: flex; }
    #hdConfirmModal .hd-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
    }
    #hdConfirmModal .hd-modal__panel {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: #fff;
      border: 1px solid var(--hd-line);
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      overflow: hidden;
    }
    #hdConfirmModal .hd-modal__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 12px 14px;
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
      color: #fff;
    }
    #hdConfirmModal .hd-modal__head.is-ok {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    }
    #hdConfirmModal .hd-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
    }
    #hdConfirmModal .hd-modal__close {
      border: 0;
      background: rgba(255,255,255,.22);
      color: #fff;
      width: 32px; height: 32px;
      cursor: pointer;
    }
    #hdConfirmModal .hd-modal__body {
      padding: 14px 16px;
      font-size: 0.9rem;
      font-weight: 750;
      line-height: 1.45;
    }
    #hdConfirmModal .hd-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 10px 14px 14px;
      border-top: 1px solid var(--hd-line);
      background: #f8fafc;
    }
  </style>

  <div class="hd-toolbar">
    <h2 class="hd-toolbar__title">
      <span class="hd-ico" aria-hidden="true"><i class="fas fa-box-open"></i></span>
      Hapus Deposit
      <span class="hd-toolbar__meta"><?= (int) $total ?> deposit</span>
    </h2>
    <?php if ($total > 0) { ?>
      <button type="button" class="hd-btn hd-btn--danger" id="hdBtnHapusSemua">
        <i class="fas fa-trash"></i> Hapus Semua
      </button>
    <?php } ?>
  </div>

  <?php if ($total === 0) { ?>
    <div class="hd-empty"><i class="fas fa-check-circle"></i>Tidak ada deposit di antrean hapus</div>
  <?php } else { ?>
    <div class="hd-grid">
      <?php foreach ($rows as $z) {
        $id = (int) ($z['id_member'] ?? 0);
        $idCabang = (int) ($z['id_cabang'] ?? 0);
        $harga = (float) ($z['harga'] ?? 0);
        $qty = $z['qty'] ?? '';
        $idUser = (int) ($z['id_user'] ?? 0);
        $pelangganId = (int) ($z['id_pelanggan'] ?? 0);

        $namaPelanggan = '';
        foreach ($this->pelanggan as $dp) {
          if ((int) ($dp['id_pelanggan'] ?? 0) === $pelangganId) {
            $namaPelanggan = (string) ($dp['nama_pelanggan'] ?? '');
            break;
          }
        }
        if ($namaPelanggan === '') {
          foreach ($this->pelangganLaundry as $dp) {
            if ((int) ($dp['id_pelanggan'] ?? 0) === $pelangganId) {
              $namaPelanggan = (string) ($dp['nama_pelanggan'] ?? '');
              break;
            }
          }
        }

        $cs = '';
        foreach ($this->userMerge as $uM) {
          if ((int) ($uM['id_user'] ?? 0) === $idUser) {
            $cs = (string) ($uM['nama_user'] ?? '');
            break;
          }
        }

        $kategori = '';
        $layanan = '';
        $durasi = '';
        $unit = '';
        foreach ($this->harga as $a) {
          if ((int) ($a['id_harga'] ?? 0) !== (int) ($z['id_harga'] ?? 0)) {
            continue;
          }
          foreach ($this->dPenjualan as $dp) {
            if ((int) ($dp['id_penjualan_jenis'] ?? 0) === (int) ($a['id_penjualan_jenis'] ?? 0)) {
              foreach ($this->dSatuan as $ds) {
                if ((int) ($ds['id_satuan'] ?? 0) === (int) ($dp['id_satuan'] ?? 0)) {
                  $unit = (string) ($ds['nama_satuan'] ?? '');
                }
              }
            }
          }
          $listLay = @unserialize($a['list_layanan'] ?? '');
          if (is_array($listLay)) {
            foreach ($listLay as $b) {
              foreach ($this->dLayanan as $c) {
                if ((int) ($c['id_layanan'] ?? 0) === (int) $b) {
                  $layanan .= ($layanan !== '' ? ' ' : '') . $c['layanan'];
                }
              }
            }
          }
          foreach ($this->dDurasi as $c) {
            if ((int) ($c['id_durasi'] ?? 0) === (int) ($a['id_durasi'] ?? 0)) {
              $durasi = (string) ($c['durasi'] ?? '');
            }
          }
          foreach ($this->itemGroup as $c) {
            if ((int) ($c['id_item_group'] ?? 0) === (int) ($a['id_item_group'] ?? 0)) {
              $kategori = (string) ($c['item_kategori'] ?? '');
            }
          }
        }

        $payRows = [];
        $totalBayar = 0;
        foreach ($kasAll as $ka) {
          if ((int) ($ka['id_cabang'] ?? 0) !== $idCabang) {
            continue;
          }
          if ((int) ($ka['ref_transaksi'] ?? 0) !== $id) {
            continue;
          }
          $payRows[] = $ka;
          $st = (int) ($ka['status_mutasi'] ?? 0);
          if ($st === 2 || $st === 3) {
            $totalBayar += (int) ($ka['jumlah'] ?? 0);
          }
        }

        $paketLabel = trim($kategori . ' · ' . $layanan . ' · ' . $durasi);
        $paketLabel = preg_replace('/\s*·\s*·\s*/', ' · ', $paketLabel);
        $paketLabel = trim($paketLabel, " ·");
      ?>
        <article class="hd-card" data-id="<?= $id ?>" data-id-cabang="<?= $idCabang ?>">
          <div class="hd-card__head">
            <div style="min-width:0;flex:1">
              <?php if ($idCabang > 0) { ?>
                <span class="aa-cabang-badge"><?= htmlspecialchars($this->cabangKodeById($idCabang), ENT_QUOTES, 'UTF-8') ?></span>
              <?php } ?>
              <h3><?= htmlspecialchars(strtoupper($namaPelanggan !== '' ? $namaPelanggan : 'Pelanggan'), ENT_QUOTES, 'UTF-8') ?></h3>
              <small>#<?= $id ?> · <?= htmlspecialchars(substr((string) ($z['insertTime'] ?? ''), 5, 11), ENT_QUOTES, 'UTF-8') ?> · CS <?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
            <span class="hd-amount">Rp<?= number_format($harga) ?></span>
          </div>
          <div class="hd-card__body">
            <div class="hd-meta">
              <?= htmlspecialchars($paketLabel !== '' ? $paketLabel : 'Paket', ENT_QUOTES, 'UTF-8') ?>
              · <strong><?= htmlspecialchars((string) $qty . $unit, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <?php if (count($payRows) > 0) { ?>
              <div class="hd-pay">
                <div class="hd-pay__label">Pembayaran<?= $totalBayar > 0 ? ' · Rp' . number_format($totalBayar) : '' ?></div>
                <?php foreach ($payRows as $ka) {
                  $notePay = trim((string) ($ka['note'] ?? ''));
                  if ($notePay === '') $notePay = trim((string) ($ka['keterangan'] ?? ''));
                  $label = $statusMutasiLabel($ka['status_mutasi'] ?? '');
                  $payUser = '';
                  foreach ($this->userMerge as $usKas) {
                    if ((int) ($usKas['id_user'] ?? 0) === (int) ($ka['id_user'] ?? 0)) {
                      $payUser = (string) ($usKas['nama_user'] ?? '');
                      break;
                    }
                  }
                ?>
                  <div class="hd-pay__row">
                    <span>#<?= (int) ($ka['id_kas'] ?? 0) ?> <?= htmlspecialchars(trim($notePay . ' · ' . $label . ' · ' . $payUser), ENT_QUOTES, 'UTF-8') ?></span>
                    <span>Rp<?= number_format((float) ($ka['jumlah'] ?? 0)) ?></span>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>

            <div class="hd-actions">
              <button type="button" class="hd-btn hd-btn--ok hd-btn--sm hd-btn-restore" data-id="<?= $id ?>" data-id-cabang="<?= $idCabang ?>">
                <i class="fas fa-recycle"></i> Restore
              </button>
              <button type="button" class="hd-btn hd-btn--danger hd-btn--sm hd-btn-hapus" data-id="<?= $id ?>" data-id-cabang="<?= $idCabang ?>">
                <i class="fas fa-trash"></i> Hapus
              </button>
            </div>
          </div>
        </article>
      <?php } ?>
    </div>
  <?php } ?>

  <div id="hdConfirmModal" class="hd-modal" aria-hidden="true">
    <div class="hd-modal__backdrop" data-hd-close></div>
    <div class="hd-modal__panel" role="dialog" aria-modal="true">
      <div class="hd-modal__head" id="hdConfirmHead">
        <h3 id="hdConfirmTitle">Konfirmasi</h3>
        <button type="button" class="hd-modal__close" data-hd-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="hd-modal__body">
        <p id="hdConfirmMsg">Lanjutkan?</p>
      </div>
      <div class="hd-modal__foot">
        <button type="button" class="hd-btn hd-btn--ghost" data-hd-close>Batal</button>
        <button type="button" class="hd-btn hd-btn--danger" id="hdConfirmOk">Hapus</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var BASE = <?= json_encode(URL::BASE_URL) ?>;
  var $root = $('#hd-root');
  if (!$root.length) return;

  var pendingAction = null;

  function toast(type, msg) {
    if (!window.MdlToast) return;
    if (type === 'ok' || type === 'success') MdlToast.ok(msg);
    else if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
    else if (type === 'error' || type === 'danger') MdlToast.error(msg);
    else MdlToast.info(msg);
  }

  function reloadList() {
    if (typeof window.loadContent === 'function') {
      window.loadContent('HapusDeposit');
      return;
    }
    $('div#load').load(BASE + 'HapusDeposit');
  }

  function openConfirm(opts) {
    opts = opts || {};
    pendingAction = opts;
    $('#hdConfirmTitle').text(opts.title || 'Konfirmasi');
    $('#hdConfirmMsg').text(opts.message || 'Lanjutkan?');
    $('#hdConfirmHead').toggleClass('is-ok', opts.okHead === true);
    var $ok = $('#hdConfirmOk');
    $ok.text(opts.okLabel || 'Hapus');
    $ok.removeClass('hd-btn--danger hd-btn--ok').addClass(opts.okHead ? 'hd-btn--ok' : 'hd-btn--danger');
    $('#hdConfirmModal').addClass('is-open').attr('aria-hidden', 'false');
  }

  function closeConfirm() {
    pendingAction = null;
    $('#hdConfirmModal').removeClass('is-open').attr('aria-hidden', 'true');
  }

  $root.on('click', '[data-hd-close]', function(e) {
    e.preventDefault();
    closeConfirm();
  });

  $('#hdConfirmOk').on('click', function() {
    if (!pendingAction || !pendingAction.run) {
      closeConfirm();
      return;
    }
    var run = pendingAction.run;
    closeConfirm();
    run();
  });

  function postJson(url, data, doneMsg) {
    $.ajax({
      url: url,
      type: 'POST',
      data: data || {},
      dataType: 'json'
    }).done(function(res) {
      if (!res || typeof res !== 'object') {
        toast('error', 'Respons tidak valid');
        return;
      }
      if (res.status === 'success') {
        toast('ok', res.message || doneMsg || 'Berhasil');
        reloadList();
      } else {
        toast('error', res.message || 'Gagal');
      }
    }).fail(function(xhr) {
      var msg = 'Gagal memproses';
      try {
        var j = JSON.parse(xhr.responseText || '{}');
        if (j && j.message) msg = j.message;
      } catch (e) {}
      toast('error', msg);
    });
  }

  $('#hdBtnHapusSemua').on('click', function() {
    openConfirm({
      title: 'Hapus Semua',
      message: 'Hapus permanen semua deposit di antrean? Pembayaran dihapus dulu; jika gagal (QRIS berhasil/pending), proses dihentikan.',
      okLabel: 'Ya, Hapus Semua',
      run: function() {
        postJson(BASE + 'HapusDeposit/hapusSemua', {}, 'Semua deposit dihapus');
      }
    });
  });

  $root.on('click', '.hd-btn-restore', function() {
    var id = $(this).attr('data-id') || '';
    var idCabang = $(this).attr('data-id-cabang') || $(this).closest('.hd-card').attr('data-id-cabang') || '';
    if (!id) return;
    openConfirm({
      title: 'Restore Deposit',
      message: 'Kembalikan deposit #' + id + ' ke daftar aktif?',
      okLabel: 'Ya, Restore',
      okHead: true,
      run: function() {
        postJson(BASE + 'HapusDeposit/restoreRef', { id: id, id_cabang: idCabang }, 'Deposit dikembalikan');
      }
    });
  });

  $root.on('click', '.hd-btn-hapus', function() {
    var id = $(this).attr('data-id') || '';
    var idCabang = $(this).attr('data-id-cabang') || $(this).closest('.hd-card').attr('data-id-cabang') || '';
    if (!id) return;
    openConfirm({
      title: 'Hapus Deposit',
      message: 'Hapus permanen deposit #' + id + '? Pembayaran terkait dihapus dulu; jika gagal, deposit tidak dihapus.',
      okLabel: 'Ya, Hapus',
      run: function() {
        postJson(BASE + 'HapusDeposit/hapusItem', { id: id, id_cabang: idCabang }, 'Deposit dihapus');
      }
    });
  });
})();
</script>
