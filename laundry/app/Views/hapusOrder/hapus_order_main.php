<?php
$dataMain = is_array($data['data_main'] ?? null) ? $data['data_main'] : [];
$operasiAll = is_array($data['operasi'] ?? null) ? $data['operasi'] : [];
$kasAll = is_array($data['kas'] ?? null) ? $data['kas'] : [];
$surcasAll = is_array($data['surcas'] ?? null) ? $data['surcas'] : [];
$notifBonAll = is_array($data['notif_bon'] ?? null) ? $data['notif_bon'] : [];

$byRef = [];
foreach ($dataMain as $row) {
  $ref = trim((string) ($row['no_ref'] ?? ''));
  if ($ref === '') {
    $ref = '_id_' . (int) ($row['id_penjualan'] ?? 0);
  }
  if (!isset($byRef[$ref])) {
    $byRef[$ref] = [];
  }
  $byRef[$ref][] = $row;
}

$operasiBySale = [];
foreach ($operasiAll as $op) {
  $sid = (string) ($op['id_penjualan'] ?? '');
  if ($sid === '') {
    continue;
  }
  if (!isset($operasiBySale[$sid])) {
    $operasiBySale[$sid] = [];
  }
  $operasiBySale[$sid][] = $op;
}

$userName = function ($id) {
  $id = (int) $id;
  foreach ($this->user as $u) {
    if ((int) ($u['id_user'] ?? 0) === $id) {
      return (string) ($u['nama_user'] ?? '');
    }
  }
  return '';
};

$pelangganName = function ($id) {
  $id = (int) $id;
  foreach ($this->pelanggan as $p) {
    if ((int) ($p['id_pelanggan'] ?? 0) === $id) {
      return (string) ($p['nama_pelanggan'] ?? '');
    }
  }
  return '';
};

$layananName = function ($idLayanan) {
  $idLayanan = (int) $idLayanan;
  foreach ($this->dLayanan as $l) {
    if ((int) ($l['id_layanan'] ?? 0) === $idLayanan) {
      return (string) ($l['layanan'] ?? '');
    }
  }
  return 'Layanan #' . $idLayanan;
};

$statusMutasiLabel = function ($sts) {
  $sts = (string) $sts;
  foreach ($this->dStatusMutasi as $st) {
    if ((string) ($st['id_status_mutasi'] ?? '') === $sts) {
      return (string) ($st['status_mutasi'] ?? '');
    }
  }
  return 'Status ' . $sts;
};

$totalItem = count($dataMain);
$totalRef = count($byRef);
?>
<div id="ho-root">
  <style>
    #ho-root {
      --ho-ink: #0f172a;
      --ho-muted: #1e293b;
      --ho-line: #cbd5e1;
      --ho-blue: #2563eb;
      --ho-blue-deep: #1d4ed8;
      --ho-green: #16a34a;
      --ho-green-deep: #15803d;
      --ho-yellow: #f59e0b;
      --ho-yellow-deep: #d97706;
      --ho-red: #dc2626;
      --ho-red-deep: #b91c1c;
      color: var(--ho-ink);
      font-family: 'fontku', 'Segoe UI', sans-serif;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }
    #ho-root, #ho-root * { border-radius: 0 !important; box-sizing: border-box; }
    #ho-root .ho-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 10px;
      padding: 10px 12px;
      border: 1px solid #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
    }
    #ho-root .ho-toolbar__title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      color: var(--ho-ink);
    }
    #ho-root .ho-toolbar__title .ho-ico {
      width: 28px; height: 28px;
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--ho-red); color: #fff; border: 1px solid var(--ho-red-deep);
    }
    #ho-root .ho-toolbar__meta {
      font-size: 0.75rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--ho-muted);
    }
    #ho-root .ho-btn {
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
      text-decoration: none;
      user-select: none;
    }
    #ho-root .ho-btn:disabled { opacity: .55; cursor: not-allowed; }
    #ho-root .ho-btn--danger {
      background: linear-gradient(180deg, var(--ho-red), var(--ho-red-deep));
      border-color: var(--ho-red-deep);
      color: #fff;
    }
    #ho-root .ho-btn--danger:hover { filter: brightness(1.05); color: #fff; }
    #ho-root .ho-btn--ok {
      background: linear-gradient(180deg, var(--ho-green), var(--ho-green-deep));
      border-color: var(--ho-green-deep);
      color: #fff;
    }
    #ho-root .ho-btn--ok:hover { filter: brightness(1.05); color: #fff; }
    #ho-root .ho-btn--ghost {
      background: #fff;
      border-color: var(--ho-line);
      color: var(--ho-ink);
    }
    #ho-root .ho-btn--ghost:hover { border-color: var(--ho-ink); }
    #ho-root .ho-btn--sm { padding: 6px 10px; font-size: 0.78rem; }
    #ho-root .ho-btn--icon {
      width: 30px; height: 30px; padding: 0;
    }
    #ho-root .ho-empty {
      border: 1px solid #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      padding: 24px 14px;
      text-align: center;
      font-weight: 900;
      font-size: 0.9rem;
      color: var(--ho-ink);
    }
    #ho-root .ho-empty i { color: var(--ho-green); margin-right: 8px; }
    #ho-root .ho-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      width: 100%;
    }
    @media (min-width: 980px) {
      #ho-root .ho-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (min-width: 1100px) {
      #ho-root .ho-grid {
        grid-template-columns: repeat(auto-fill, 460px);
      }
      #ho-root .ho-card { width: 460px; max-width: 100%; }
    }
    #ho-root .ho-card {
      border: 1px solid #fca5a5;
      background: #fff;
      padding: 0;
      overflow: hidden;
      min-width: 0;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }
    #ho-root .ho-card__head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
      padding: 10px 12px;
      border-bottom: 1px solid #fecaca;
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
      color: #fff;
    }
    #ho-root .ho-card__head h3 {
      margin: 0;
      font-size: 0.92rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #ho-root .ho-card__head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
      opacity: .92;
    }
    #ho-root .ho-card__body { padding: 10px 12px; background: #fff; }
    #ho-root .ho-alasan {
      display: inline-block;
      margin-bottom: 8px;
      padding: 4px 8px;
      border: 1px solid #fca5a5;
      background: #fef2f2;
      color: var(--ho-red-deep);
      font-weight: 900;
      font-size: 0.78rem;
    }
    #ho-root .ho-pay {
      margin-bottom: 8px;
      padding: 8px 10px;
      border: 1px solid #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #ho-root .ho-pay__label {
      font-size: 0.7rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--ho-muted);
      margin-bottom: 4px;
    }
    #ho-root .ho-pay__row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 0.8rem;
      font-weight: 750;
      padding: 4px 0;
      line-height: 1.4;
    }
    #ho-root .ho-pay__row + .ho-pay__row {
      border-top: 1px dashed #fcd34d;
    }
    #ho-root .ho-pay__row > span:first-child {
      min-width: 0;
      flex: 1;
    }
    #ho-root .ho-pay__side {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
    }
    #ho-root .ho-item {
      border: 1px solid #93c5fd;
      padding: 8px 10px;
      margin-bottom: 6px;
      background: linear-gradient(180deg, #eff6ff, #fff);
    }
    #ho-root .ho-item:last-child { margin-bottom: 0; }
    #ho-root .ho-item__top {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
    }
    #ho-root .ho-item__title {
      font-size: 0.88rem;
      font-weight: 900;
      color: var(--ho-ink);
      line-height: 1.3;
    }
    #ho-root .ho-item__meta {
      font-size: 0.78rem;
      font-weight: 750;
      color: var(--ho-muted);
      margin-top: 2px;
      line-height: 1.35;
    }
    #ho-root .ho-item__price {
      font-size: 0.88rem;
      font-weight: 900;
      color: var(--ho-red-deep);
      white-space: nowrap;
    }
    #ho-root .ho-item__side {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 6px;
      flex-shrink: 0;
    }
    #ho-root .ho-ops {
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px dashed #93c5fd;
    }
    #ho-root .ho-ops__label {
      font-size: 0.7rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--ho-muted);
      margin-bottom: 4px;
    }
    #ho-root .ho-op {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 5px 8px;
      margin-bottom: 4px;
      border: 1px solid #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      font-size: 0.8rem;
      font-weight: 750;
      line-height: 1.35;
    }
    #ho-root .ho-op:last-child { margin-bottom: 0; }
    #ho-root .ho-chip {
      display: inline-block;
      padding: 1px 6px;
      border: 1px solid var(--ho-line);
      background: #fff;
      font-size: 0.7rem;
      font-weight: 900;
      margin: 2px 2px 0 0;
      line-height: 1.4;
    }
    #ho-root .ho-chip--warn {
      border-color: #fcd34d;
      background: #fffbeb;
      color: var(--ho-yellow-deep);
    }
    #ho-root .ho-foot {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid var(--ho-line);
    }
    #ho-root .ho-foot__total {
      font-size: 0.95rem;
      font-weight: 900;
      color: var(--ho-red-deep);
    }

    #hoConfirmModal.ho-modal {
      position: fixed;
      inset: 0;
      z-index: 5600;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #hoConfirmModal.ho-modal.is-open { display: flex; }
    #hoConfirmModal .ho-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
    }
    #hoConfirmModal .ho-modal__panel {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: #fff;
      border: 1px solid var(--ho-line);
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      overflow: hidden;
    }
    #hoConfirmModal .ho-modal__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 12px 14px;
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
      color: #fff;
    }
    #hoConfirmModal .ho-modal__head.is-ok {
      background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    }
    #hoConfirmModal .ho-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #hoConfirmModal .ho-modal__close {
      border: 0;
      background: rgba(255,255,255,.22);
      color: #fff;
      width: 32px; height: 32px;
      cursor: pointer;
      flex: 0 0 auto;
    }
    #hoConfirmModal .ho-modal__body {
      padding: 14px 16px;
      font-size: 0.9rem;
      font-weight: 750;
      color: var(--ho-ink);
      line-height: 1.45;
    }
    #hoConfirmModal .ho-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 10px 14px 14px;
      border-top: 1px solid var(--ho-line);
      background: #f8fafc;
    }
  </style>

  <div class="ho-toolbar">
    <h2 class="ho-toolbar__title">
      <span class="ho-ico" aria-hidden="true"><i class="fas fa-trash-alt"></i></span>
      Hapus Order
      <span class="ho-toolbar__meta"><?= (int) $totalItem ?> item · <?= (int) $totalRef ?> nota</span>
    </h2>
    <?php if ($totalItem > 0) { ?>
      <button type="button" class="ho-btn ho-btn--danger" id="hoBtnHapusSemua">
        <i class="fas fa-trash"></i> Hapus Semua
      </button>
    <?php } ?>
  </div>

  <?php if ($totalItem === 0) { ?>
    <div class="ho-empty"><i class="fas fa-check-circle"></i>Tidak ada order di antrean hapus</div>
  <?php } else { ?>
    <div class="ho-grid">
      <?php foreach ($byRef as $refKey => $items) {
        $first = $items[0];
        $refDisplay = (strpos($refKey, '_id_') === 0) ? '-' : $refKey;
        $pelanggan = $pelangganName($first['id_pelanggan'] ?? 0);
        $kasir = $userName($first['id_user'] ?? 0);
        $waktu = substr((string) ($first['insertTime'] ?? ''), 5, 11);
        $alasan = trim((string) ($first['bin_note'] ?? ''));

        $subTotal = 0;
        $payRows = [];
        $totalBayar = 0;
        foreach ($kasAll as $ka) {
          if ((string) ($ka['ref_transaksi'] ?? '') !== (string) $refDisplay && $refDisplay !== '-') {
            continue;
          }
          if ($refDisplay === '-') {
            continue;
          }
          $payRows[] = $ka;
          $st = (int) ($ka['status_mutasi'] ?? 0);
          if ($st === 2 || $st === 3) {
            $totalBayar += (int) ($ka['jumlah'] ?? 0);
          }
        }

        $hasNotifBon = false;
        foreach ($notifBonAll as $nb) {
          if ((string) ($nb['no_ref'] ?? '') === (string) $refDisplay) {
            $hasNotifBon = true;
            break;
          }
        }
        $hasSurcas = false;
        foreach ($surcasAll as $sc) {
          if ((string) ($sc['no_ref'] ?? '') === (string) $refDisplay) {
            $hasSurcas = true;
            $subTotal += (int) ($sc['jumlah'] ?? 0);
          }
        }
      ?>
        <article class="ho-card" data-ref="<?= htmlspecialchars((string) $refDisplay, ENT_QUOTES, 'UTF-8') ?>">
          <div class="ho-card__head">
            <div>
              <h3><?= htmlspecialchars(strtoupper($pelanggan !== '' ? $pelanggan : 'Pelanggan'), ENT_QUOTES, 'UTF-8') ?></h3>
              <small>REF #<?= htmlspecialchars((string) $refDisplay, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($waktu, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($kasir, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
            <?php if ($refDisplay !== '-') { ?>
              <button type="button" class="ho-btn ho-btn--ok ho-btn--sm ho-btn-restore"
                data-ref="<?= htmlspecialchars((string) $refDisplay, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-recycle"></i> Restore
              </button>
            <?php } ?>
          </div>
          <div class="ho-card__body">
            <?php if ($alasan !== '') { ?>
              <div class="ho-alasan"><?= htmlspecialchars($alasan, ENT_QUOTES, 'UTF-8') ?></div>
            <?php } ?>

            <?php if (count($payRows) > 0 || $hasNotifBon || $hasSurcas) { ?>
              <div class="ho-pay">
                <div class="ho-pay__label">Terkait nota</div>
                <?php if ($hasNotifBon) { ?>
                  <span class="ho-chip ho-chip--warn">Notif bon</span>
                <?php } ?>
                <?php if ($hasSurcas) { ?>
                  <span class="ho-chip ho-chip--warn">Surcas</span>
                <?php } ?>
                <?php foreach ($payRows as $ka) {
                  $sts = (string) ($ka['status_mutasi'] ?? '');
                  $label = $statusMutasiLabel($sts);
                  $notePay = (string) ($ka['note'] ?? '');
                  $payUser = $userName($ka['id_user'] ?? 0);
                  $idKas = trim((string) ($ka['id_kas'] ?? ''));
                  $isQris = strtoupper(trim($notePay)) === 'QRIS';
                  $payLabel = trim($notePay . ' · ' . $label . ($payUser !== '' ? ' · ' . $payUser : ''));
                  $payAmount = (float) ($ka['jumlah'] ?? 0);
                ?>
                  <div class="ho-pay__row" data-id-kas="<?= htmlspecialchars($idKas, ENT_QUOTES, 'UTF-8') ?>">
                    <span><?= htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="ho-pay__side">
                      <span>Rp<?= number_format($payAmount) ?></span>
                      <?php if ($idKas !== '') { ?>
                        <button type="button"
                          class="ho-btn ho-btn--danger ho-btn--sm ho-btn--icon ho-btn-hapus-pay"
                          title="Hapus pembayaran"
                          data-id-kas="<?= htmlspecialchars($idKas, ENT_QUOTES, 'UTF-8') ?>"
                          data-qris="<?= $isQris ? '1' : '0' ?>"
                          data-label="<?= htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8') ?>"
                          data-amount="<?= htmlspecialchars((string) number_format($payAmount), ENT_QUOTES, 'UTF-8') ?>">
                          <i class="fas fa-trash"></i>
                        </button>
                      <?php } ?>
                    </span>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>

            <?php foreach ($items as $a) {
              $id = (int) ($a['id_penjualan'] ?? 0);
              $f10 = $a['id_penjualan_jenis'] ?? 0;
              $f3 = $a['id_item_group'] ?? 0;
              $f4 = $a['list_item'] ?? '';
              $f5 = $a['list_layanan'] ?? '';
              $f11 = $a['id_durasi'] ?? 0;
              $f6 = round((float) ($a['qty'] ?? 0), 2);
              $f7 = (float) ($a['harga'] ?? 0);
              $f14 = (float) ($a['diskon_qty'] ?? 0);
              $f15 = (float) ($a['diskon_partner'] ?? 0);
              $f16 = round((float) ($a['min_order'] ?? 0), 2);

              $penjualan = '';
              $satuan = '';
              foreach ($this->dPenjualan as $l) {
                if ((int) ($l['id_penjualan_jenis'] ?? 0) === (int) $f10) {
                  $penjualan = (string) ($l['penjualan_jenis'] ?? '');
                  foreach ($this->dSatuan as $sa) {
                    if ((int) ($sa['id_satuan'] ?? 0) === (int) ($l['id_satuan'] ?? 0)) {
                      $satuan = (string) ($sa['nama_satuan'] ?? '');
                    }
                  }
                }
              }
              $durasi = '';
              foreach ($this->dDurasi as $b) {
                if ((int) ($b['id_durasi'] ?? 0) === (int) $f11) {
                  $durasi = (string) ($b['durasi'] ?? '');
                }
              }
              $kategori = '';
              foreach ($this->itemGroup as $b) {
                if ((int) ($b['id_item_group'] ?? 0) === (int) $f3) {
                  $kategori = (string) ($b['item_kategori'] ?? '');
                }
              }

              $qtyReal = ($f6 < $f16) ? $f16 : $f6;
              $showQty = $this->fmtDecMax2($f6) . $satuan;
              if ($f6 < $f16) {
                $showQty .= ' (Min. ' . $this->fmtDecMax2($f16) . $satuan . ')';
              }

              $total = ($f7 * $qtyReal) - (($f7 * $qtyReal) * ($f14 / 100));
              if ($f15 > 0) {
                $total -= $total * ($f15 / 100);
              }
              $subTotal += (int) round($total);

              $showDiskon = '';
              if ($f14 > 0) {
                $showDiskon .= $f14 . '%';
              }
              if ($f15 > 0) {
                $showDiskon .= ($showDiskon !== '' ? ' + ' : '') . $f15 . '%';
              }

              $itemListHtml = '';
              if (strlen((string) $f4) > 0) {
                $arrItemList = @unserialize($f4);
                if (is_array($arrItemList) && count($arrItemList) > 0) {
                  foreach ($arrItemList as $key => $k) {
                    foreach ($this->dItem as $b) {
                      if ((string) ($b['id_item'] ?? '') === (string) $key) {
                        $itemListHtml .= '<span class="ho-chip">' . htmlspecialchars($b['item'] . '[' . $k . ']', ENT_QUOTES, 'UTF-8') . '</span>';
                      }
                    }
                  }
                }
              }

              $ops = $operasiBySale[(string) $id] ?? [];
              $hasOps = count($ops) > 0;

              $layananPending = [];
              if (strlen((string) $f5) > 0) {
                $arrLay = @unserialize($f5);
                if (is_array($arrLay)) {
                  foreach ($arrLay as $lid) {
                    $done = false;
                    foreach ($ops as $op) {
                      if ((int) ($op['jenis_operasi'] ?? 0) === (int) $lid) {
                        $done = true;
                        break;
                      }
                    }
                    if (!$done) {
                      $layananPending[] = $layananName($lid);
                    }
                  }
                }
              }
            ?>
              <div class="ho-item" data-id="<?= $id ?>">
                <div class="ho-item__top">
                  <div style="min-width:0;flex:1">
                    <div class="ho-item__title">#<?= $id ?> <?= htmlspecialchars($penjualan . ' ' . $kategori, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="ho-item__meta"><?= htmlspecialchars(trim($durasi . ' · ' . $showQty . ($showDiskon !== '' ? ' · Diskon ' . $showDiskon : '')), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($itemListHtml !== '') { ?>
                      <div style="margin-top:4px"><?= $itemListHtml ?></div>
                    <?php } ?>
                    <?php if (count($layananPending) > 0) { ?>
                      <div class="ho-item__meta" style="margin-top:2px">Belum selesai: <?= htmlspecialchars(implode(', ', $layananPending), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                  </div>
                  <div class="ho-item__side">
                    <span class="ho-item__price">Rp<?= number_format((float) round($total)) ?></span>
                    <button type="button"
                      class="ho-btn ho-btn--danger ho-btn--sm ho-btn-hapus-item"
                      data-id="<?= $id ?>"
                      <?= $hasOps ? 'disabled title="Hapus penyelesai dulu"' : '' ?>>
                      <i class="fas fa-trash"></i> Hapus
                    </button>
                  </div>
                </div>

                <?php if ($hasOps) { ?>
                  <div class="ho-ops">
                    <div class="ho-ops__label">Penyelesai</div>
                    <?php foreach ($ops as $op) {
                      $idOp = (string) ($op['id_operasi'] ?? '');
                      $jenis = (int) ($op['jenis_operasi'] ?? 0);
                      $namaLay = $layananName($jenis);
                      $namaOp = $userName($op['id_user_operasi'] ?? 0);
                      $tOp = substr((string) ($op['insertTime'] ?? ''), 5, 11);
                    ?>
                      <div class="ho-op">
                        <div>
                          <i class="fas fa-check-circle" style="color:var(--ho-green)"></i>
                          <strong><?= htmlspecialchars($namaLay, ENT_QUOTES, 'UTF-8') ?></strong>
                          · <?= htmlspecialchars($namaOp, ENT_QUOTES, 'UTF-8') ?>
                          <span style="opacity:.8">(<?= htmlspecialchars($tOp, ENT_QUOTES, 'UTF-8') ?>)</span>
                        </div>
                        <button type="button"
                          class="ho-btn ho-btn--danger ho-btn--sm ho-btn--icon ho-btn-hapus-op"
                          title="Hapus penyelesai"
                          data-id-operasi="<?= htmlspecialchars($idOp, ENT_QUOTES, 'UTF-8') ?>"
                          data-label="<?= htmlspecialchars($namaLay . ' · ' . $namaOp, ENT_QUOTES, 'UTF-8') ?>">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    <?php } ?>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>

            <div class="ho-foot">
              <div class="ho-foot__total">Rp<?= number_format((float) $subTotal) ?></div>
              <?php if ($totalBayar > 0) { ?>
                <div class="ho-item__meta">Terbayar Rp<?= number_format((float) $totalBayar) ?></div>
              <?php } ?>
            </div>
          </div>
        </article>
      <?php } ?>
    </div>
  <?php } ?>

  <div id="hoConfirmModal" class="ho-modal" aria-hidden="true">
    <div class="ho-modal__backdrop" data-ho-close></div>
    <div class="ho-modal__panel" role="dialog" aria-modal="true" aria-labelledby="hoConfirmTitle">
      <div class="ho-modal__head" id="hoConfirmHead">
        <h3 id="hoConfirmTitle">Konfirmasi</h3>
        <button type="button" class="ho-modal__close" data-ho-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="ho-modal__body">
        <p id="hoConfirmMsg">Lanjutkan?</p>
      </div>
      <div class="ho-modal__foot">
        <button type="button" class="ho-btn ho-btn--ghost" data-ho-close>Batal</button>
        <button type="button" class="ho-btn ho-btn--danger" id="hoConfirmOk">Hapus</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var BASE = <?= json_encode(URL::BASE_URL) ?>;
  var $root = $('#ho-root');
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
      window.loadContent('HapusOrder');
      return;
    }
    $('div#load').load(BASE + 'HapusOrder');
  }

  function openConfirm(opts) {
    opts = opts || {};
    pendingAction = opts;
    $('#hoConfirmTitle').text(opts.title || 'Konfirmasi');
    $('#hoConfirmMsg').text(opts.message || 'Lanjutkan?');
    var $head = $('#hoConfirmHead');
    $head.toggleClass('is-ok', opts.okHead === true);
    var $ok = $('#hoConfirmOk');
    $ok.text(opts.okLabel || 'Hapus');
    $ok.removeClass('ho-btn--danger ho-btn--ok').addClass(opts.okHead ? 'ho-btn--ok' : 'ho-btn--danger');
    $('#hoConfirmModal').addClass('is-open').attr('aria-hidden', 'false');
  }

  function closeConfirm() {
    pendingAction = null;
    $('#hoConfirmModal').removeClass('is-open').attr('aria-hidden', 'true');
  }

  $root.on('click', '[data-ho-close]', function(e) {
    e.preventDefault();
    closeConfirm();
  });

  $('#hoConfirmOk').on('click', function() {
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

  $('#hoBtnHapusSemua').on('click', function() {
    openConfirm({
      title: 'Hapus Semua',
      message: 'Hapus permanen semua order di antrean? Pembayaran dihapus dulu; jika gagal (QRIS berhasil/pending), proses dihentikan dan layanan tidak dihapus.',
      okLabel: 'Ya, Hapus Semua',
      run: function() {
        postJson(BASE + 'HapusOrder/hapusSemua', {}, 'Semua antrean dihapus');
      }
    });
  });

  $root.on('click', '.ho-btn-restore', function() {
    var ref = $(this).attr('data-ref') || '';
    if (!ref) return;
    openConfirm({
      title: 'Restore Nota',
      message: 'Kembalikan REF #' + ref + ' ke Operasi (bin=0)?',
      okLabel: 'Ya, Restore',
      okHead: true,
      run: function() {
        postJson(BASE + 'HapusOrder/restoreRef', { ref: ref }, 'Nota dikembalikan');
      }
    });
  });

  $root.on('click', '.ho-btn-hapus-pay', function() {
    var idKas = $(this).attr('data-id-kas') || '';
    var label = $(this).attr('data-label') || idKas;
    var amount = $(this).attr('data-amount') || '';
    var isQris = $(this).attr('data-qris') === '1';
    if (!idKas) return;
    var msg = isQris
      ? 'Hapus pembayaran QRIS Rp' + amount + '? Status di gateway akan dicek; jika sudah berhasil atau masih aktif, pembayaran tidak dihapus.'
      : 'Hapus pembayaran ' + label + (amount ? ' Rp' + amount : '') + '?';
    openConfirm({
      title: 'Hapus Pembayaran',
      message: msg,
      okLabel: 'Ya, Hapus Pembayaran',
      run: function() {
        postJson(BASE + 'HapusOrder/hapusPembayaran', { id_kas: idKas }, 'Pembayaran dihapus');
      }
    });
  });

  $root.on('click', '.ho-btn-hapus-item', function() {
    if ($(this).is(':disabled')) {
      toast('warn', 'Hapus penyelesai item ini dulu');
      return;
    }
    var id = $(this).attr('data-id') || '';
    if (!id) return;
    openConfirm({
      title: 'Hapus Item',
      message: 'Hapus permanen item #' + id + '? Pastikan tidak overpay dan tidak ada penyelesai.',
      okLabel: 'Ya, Hapus Item',
      run: function() {
        postJson(BASE + 'HapusOrder/hapusItemPermanent', { id: id }, 'Item dihapus');
      }
    });
  });

  $root.on('click', '.ho-btn-hapus-op', function() {
    var idOp = $(this).attr('data-id-operasi') || '';
    var label = $(this).attr('data-label') || idOp;
    if (!idOp) return;
    openConfirm({
      title: 'Hapus Penyelesai',
      message: 'Hapus penyelesai: ' + label + '?',
      okLabel: 'Ya, Hapus Penyelesai',
      run: function() {
        postJson(BASE + 'HapusOrder/hapusOperasi', { id_operasi: idOp }, 'Penyelesai dihapus');
      }
    });
  });
})();
</script>
