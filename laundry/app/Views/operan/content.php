<?php
$idOperan = $data['idOperan'];
?>

<div class="opn-result">
  <style>
    #operan-root .opn-result { min-width: 0; }
    #operan-root .opn-list {
      display: grid;
      gap: 10px;
    }
    #operan-root .opn-card {
      border: 1px solid #cbd5e1;
      background: #fff;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    #operan-root .opn-card.is-warn {
      border-color: #fcd34d;
      background: linear-gradient(180deg, #fffbeb, #fff);
    }
    #operan-root .opn-card__head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px 12px;
      padding: 12px 14px;
      border-bottom: 1px solid #e2e8f0;
      background: linear-gradient(180deg, #f8fafc, #fff);
    }
    #operan-root .opn-card__id {
      font-size: 0.84rem;
      font-weight: 800;
      color: #334155;
    }
    #operan-root .opn-card__name {
      display: block;
      margin-top: 2px;
      font-size: 0.95rem;
      font-weight: 900;
      color: #0f172a;
      text-transform: uppercase;
    }
    #operan-root .opn-card__meta {
      font-size: 0.78rem;
      font-weight: 750;
      color: #1e293b;
      text-align: right;
    }
    #operan-root .opn-card__body {
      padding: 12px 14px;
      display: grid;
      gap: 10px;
    }
    @media (min-width: 760px) {
      #operan-root .opn-card__body {
        grid-template-columns: 1.2fr 0.8fr 0.8fr;
      }
    }
    #operan-root .opn-k {
      font-size: 0.72rem;
      font-weight: 900;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 4px;
    }
    #operan-root .opn-v {
      font-size: 0.88rem;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.35;
    }
    #operan-root .opn-v--muted { color: #334155; font-weight: 750; font-size: 0.82rem; }
    #operan-root .opn-price del {
      color: #64748b;
      font-weight: 700;
      margin-right: 4px;
    }
    #operan-root .opn-badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      border: 1px solid #93c5fd;
      background: #eff6ff;
      color: #1d4ed8;
      font-size: 0.72rem;
      font-weight: 900;
      margin: 0 4px 4px 0;
    }
    #operan-root .opn-badge--item {
      border-color: #cbd5e1;
      background: #f8fafc;
      color: #0f172a;
    }
    #operan-root .opn-badge--member {
      border-color: #86efac;
      background: #f0fdf4;
      color: #15803d;
    }
    #operan-root .opn-card__ops {
      padding: 12px 14px;
      border-top: 1px solid #e2e8f0;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      border-top-color: #86efac;
    }
    #operan-root .opn-card.is-warn .opn-card__ops {
      background: linear-gradient(180deg, #fffbeb, #fff);
      border-top-color: #fcd34d;
    }
    #operan-root .opn-ops-title {
      font-size: 0.72rem;
      font-weight: 900;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 8px;
    }
    #operan-root .opn-ops-done {
      display: flex;
      align-items: flex-start;
      gap: 6px;
      margin-bottom: 6px;
      font-size: 0.82rem;
      font-weight: 800;
      color: #0f172a;
    }
    #operan-root .opn-ops-done i {
      color: #16a34a;
      margin-top: 2px;
    }
    #operan-root .opn-ops-done small {
      font-weight: 750;
      color: #334155;
      white-space: pre;
    }
    #operan-root .opn-svc {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin: 0 6px 6px 0;
      padding: 8px 10px;
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
      color: #1d4ed8;
      font-size: 0.82rem;
      font-weight: 900;
      cursor: pointer;
    }
    #operan-root .opn-svc:hover {
      border-color: #2563eb;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
    }
    #operan-root .opn-pack {
      margin-top: 6px;
      font-size: 0.8rem;
      font-weight: 800;
      color: #0f172a;
    }

    /* Modal — UI theme */
    #operan-root .opn-modal {
      position: fixed;
      inset: 0;
      z-index: 5300;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #operan-root .opn-modal.is-open { display: flex; }
    #operan-root .opn-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      cursor: pointer;
    }
    #operan-root .opn-modal__panel {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: #fff;
      border: 1px solid #cbd5e1;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      overflow: visible;
    }
    #operan-root .opn-modal__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      padding: 14px 16px;
      background: linear-gradient(180deg, #16a34a, #15803d);
      color: #fff;
    }
    #operan-root .opn-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
    }
    #operan-root .opn-modal__head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
      opacity: 0.95;
    }
    #operan-root .opn-modal__close {
      border: 0;
      background: rgba(255,255,255,.22);
      color: #fff;
      width: 32px;
      height: 32px;
      cursor: pointer;
      flex: 0 0 auto;
    }
    #operan-root .opn-modal__body {
      padding: 16px;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.08), transparent 50%),
        linear-gradient(180deg, #f8fafc, #fff);
    }
    #operan-root .opn-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      border-top: 1px solid #e2e8f0;
      background: #f8fafc;
    }
    #operan-root .opn-field { margin-bottom: 12px; }
    #operan-root .opn-field:last-child { margin-bottom: 0; }
    #operan-root .opn-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    #operan-root select.tize,
    #operan-root select.selectized {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      padding: 0 !important;
    }
    #operan-root .selectize-control,
    #operan-root .selectize-control.single {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      margin: 0;
    }
    #operan-root .selectize-control.single .selectize-input {
      border: 1px solid #94a3b8 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      background: #fff !important;
      font-weight: 800;
      min-height: 42px;
      padding: 10px 12px !important;
    }
    #operan-root .selectize-control.single .selectize-input.focus {
      border-color: #2563eb !important;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
    }
    #operan-root .selectize-control.single .selectize-input:after {
      border: 0 !important;
    }
    #operan-root .selectize-dropdown {
      border: 1px solid #94a3b8 !important;
      border-radius: 0 !important;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
      z-index: 40 !important;
    }
  </style>

  <div class="opn-list">
    <?php
    $prevRef = '';
    $arrRef = array();
    $countRef = 0;

    foreach ($data['data_main'] as $a) {
      $ref = $a['no_ref'];
      if ($prevRef <> $a['no_ref']) {
        $countRef = 0;
        $countRef++;
        $arrRef[$ref] = $countRef;
      } else {
        $countRef++;
        $arrRef[$ref] = $countRef;
      }
      $prevRef = $ref;
    }

    $no = 0;
    $arrCount = 0;
    $arrTotalPoin = array();
    $enHapus = true;

    foreach ($data['data_main'] as $a) {
      $no++;
      $id = $a['id_penjualan'];
      $f10 = $a['id_penjualan_jenis'];
      $f3 = $a['id_item_group'];
      $f4 = $a['list_item'];
      $f5 = $a['list_layanan'];
      $f11 = $a['id_durasi'];
      $f6 = round((float) $a['qty'], 2);
      $f7 = $a['harga'];
      $f8 = $a['note'];
      $f1 = $a['insertTime'];
      $f12 = $a['hari'];
      $f13 = $a['jam'];
      $f14 = $a['diskon_qty'];
      $f15 = $a['diskon_partner'];
      $f16 = round(isset($a['min_order']) ? (float) $a['min_order'] : 0.0, 2);
      $f17 = $a['id_pelanggan'];
      $f18 = $a['id_user'];
      $noref = $a['no_ref'];
      $pack = $a['pack'];
      $hanger = $a['hanger'];
      $id_ambil = $a['id_user_ambil'];
      $tgl_ambil = $a['tgl_ambil'];
      $id_cabang = $a['id_cabang'];
      $member = $a['member'];
      $phpdate = strtotime($f1);
      $idCabangAsal = $a['id_cabang'];
      $f1 = date('d-m-Y H:i:s', $phpdate);

      $pelanggan = '';
      $no_pelanggan = '';
      foreach ($this->pelangganLaundry as $c) {
        if ($c['id_pelanggan'] == $f17) {
          $pelanggan = $c['nama_pelanggan'];
          $no_pelanggan = $c['nomor_pelanggan'];
        }
      }

      $penjualan = "";
      $satuan = "";
      foreach ($this->dPenjualan as $l) {
        if ($l['id_penjualan_jenis'] == $f10) {
          $penjualan = $l['penjualan_jenis'];
          foreach ($this->dSatuan as $sa) {
            if ($sa['id_satuan'] == $l['id_satuan']) {
              $satuan = $sa['nama_satuan'];
            }
          }
        }
      }

      $show_qty = "";
      $qty_real = 0;
      if ($f6 < $f16) {
        $qty_real = $f16;
        $show_qty = $this->fmtDecMax2($f6) . $satuan . " (Min. " . $this->fmtDecMax2($f16) . $satuan . ")";
      } else {
        $qty_real = $f6;
        $show_qty = $this->fmtDecMax2($f6) . $satuan;
      }

      if ($no == 1) {
        $totalBayar = 0;
        $subTotal = 0;
        $enHapus = true;
      }

      $kategori = "";
      foreach ($this->itemGroup as $b) {
        if ($b['id_item_group'] == $f3) {
          $kategori = $b['item_kategori'];
        }
      }

      $durasi = "";
      foreach ($this->dDurasi as $b) {
        if ($b['id_durasi'] == $f11) {
          $durasi = strtoupper($b['durasi']);
        }
      }

      $userAmbil = "";
      $endLayananDone = false;
      $list_layanan = "";
      $arrList_layanan = unserialize($f5);
      $endLayanan = end($arrList_layanan);
      foreach ($arrList_layanan as $b) {
        $check = 0;
        foreach ($this->dLayanan as $c) {
          if ($c['id_layanan'] == $b) {
            foreach ($data['operasi'] as $o) {
              if ($o['id_penjualan'] == $id && $o['jenis_operasi'] == $b) {
                $user = "";
                $check++;
                foreach ($this->userMerge as $p) {
                  if ($p['id_user'] == $o['id_user_operasi']) {
                    $user = $p['nama_user'];
                  }
                  if ($p['id_user'] == $id_ambil) {
                    $userAmbil = $p['nama_user'];
                  }
                }
                $list_layanan .= '<div class="opn-ops-done"><i class="fas fa-check"></i><div><b>' . htmlspecialchars($c['layanan']) . '</b> ' . htmlspecialchars($user) . ' <small>(' . htmlspecialchars(substr($o['insertTime'], 5, 11)) . ')</small></div></div>';
                if ($b == $endLayanan) {
                  $endLayananDone = true;
                }
                $enHapus = false;
              }
            }
            if ($check == 0) {
              $list_layanan .= '<button type="button" id="' . htmlspecialchars($id . $b) . '" data-layanan="' . htmlspecialchars($c['layanan']) . '" data-cabang="' . htmlspecialchars((string) $id_cabang) . '" data-value="' . htmlspecialchars((string) $c['id_layanan']) . '" data-id="' . htmlspecialchars((string) $id) . '" class="opn-svc addOperasi"><i class="fas fa-play"></i> ' . htmlspecialchars($c['layanan']) . '</button>';
            }
          }
        }
      }

      if ($id_ambil > 0) {
        $list_layanan .= '<div class="opn-ops-done"><i class="fas fa-check"></i><div><b>Ambil</b> ' . htmlspecialchars($userAmbil) . ' <small>(' . htmlspecialchars(substr($tgl_ambil, 5, 11)) . ')</small></div></div>';
      }
      $list_layanan .= '<span class="operasiAmbil' . $id . '"></span>';

      $diskon_qty = $f14;
      $diskon_partner = $f15;
      $show_diskon_qty = $diskon_qty > 0 ? $diskon_qty . "%" : "";
      $show_diskon_partner = $diskon_partner > 0 ? $diskon_partner . "%" : "";
      $plus = ($diskon_qty > 0 && $diskon_partner > 0) ? " + " : "";
      $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;

      $itemList = "";
      if (strlen($f4) > 0) {
        $arrItemList = unserialize($f4);
        if (is_array($arrItemList) && count($arrItemList) > 0) {
          foreach ($arrItemList as $key => $k) {
            foreach ($this->dItem as $b) {
              if ($b['id_item'] == $key) {
                $itemList .= '<span class="opn-badge opn-badge--item">' . htmlspecialchars($b['item']) . '[' . htmlspecialchars((string) $k) . ']</span>';
              }
            }
          }
        }
      }

      $total = 0;
      if ($diskon_qty > 0) {
        $total = round(($f7 * $qty_real) - (($f7 * $qty_real) * ($diskon_qty / 100)));
      } else {
        $total = ($f7 * $qty_real);
      }
      $subTotal = $subTotal + $total;

      foreach ($arrRef as $key => $m) {
        if ($key == $noref) {
          $arrCount = $m;
        }
      }

      $tampilDiskon = "";
      if ($member == 0) {
        if (strlen($show_diskon) > 0) {
          $tampilDiskon = "(Disc. " . $show_diskon . ")";
          $show_total = "<del>Rp" . number_format($f7 * $qty_real) . "</del> Rp" . number_format($total);
        } else {
          $show_total = "Rp" . number_format($total);
        }
      } else {
        $show_total = '<span class="opn-badge opn-badge--member">Member</span>';
      }

      $dataPack = "";
      if ($endLayananDone == true) {
        $dataPack = "Pack: " . $pack . ", Hanger: " . $hanger;
      }

      $isWarn = ((int) $f11 !== 11);
      $cardClass = $isWarn ? 'opn-card is-warn' : 'opn-card';
      ?>
      <article class="<?= $cardClass ?>" id="tr<?= htmlspecialchars((string) $id) ?>">
        <div class="opn-card__head">
          <div>
            <span class="opn-card__id">#<?= htmlspecialchars((string) $id) ?></span>
            <span class="opn-card__name"><?= htmlspecialchars(strtoupper($pelanggan)) ?></span>
          </div>
          <div class="opn-card__meta">
            <div><b><?= htmlspecialchars(substr($f1, 0, 5)) ?></b> <?= htmlspecialchars(substr($f1, 11, 5)) ?></div>
            <?php if (strlen($f8) > 0) { ?>
              <div><?= htmlspecialchars($f8) ?></div>
            <?php } ?>
          </div>
        </div>
        <div class="opn-card__body">
          <div>
            <div class="opn-k">Item</div>
            <div class="opn-v">
              <?= htmlspecialchars($kategori) ?>
              <span class="opn-badge"><?= htmlspecialchars($penjualan) ?></span>
              <?php if ($itemList !== '') { ?>
                <div style="margin-top:6px"><?= $itemList ?></div>
              <?php } ?>
              <?php if ($dataPack !== '') { ?>
                <div class="opn-pack"><?= htmlspecialchars($dataPack) ?></div>
              <?php } ?>
            </div>
          </div>
          <div>
            <div class="opn-k">Durasi</div>
            <div class="opn-v"><b><?= htmlspecialchars($durasi) ?></b></div>
            <div class="opn-v opn-v--muted">(<?= (int) $f12 ?>h <?= (int) $f13 ?>j)</div>
          </div>
          <div>
            <div class="opn-k">Total</div>
            <div class="opn-v opn-price"><?= $show_total ?></div>
            <div class="opn-v"><b><?= htmlspecialchars($show_qty) ?></b> <?= htmlspecialchars($tampilDiskon) ?></div>
          </div>
        </div>
        <div class="opn-card__ops">
          <div class="opn-ops-title">Operasi</div>
          <?= $list_layanan ?>
        </div>
      </article>
      <span class="d-none selesai<?= $id ?>" data-hp="<?= htmlspecialchars($no_pelanggan) ?>"><?= strtoupper($pelanggan) ?> _#<?= $idCabangAsal ?>-|STAFF|_
<?= "#" . $id ?> Selesai. |TOTAL|
<?= URL::HOST_URL ?>/I/<?= $f17 ?></span>
      <?php
      if ($arrCount == $no) {
        $totalBayar = 0;
        $no = 0;
        $subTotal = 0;
        $enHapus = true;
      }
    }
    ?>
  </div>

  <form class="jq" data-operasi="" action="<?= URL::BASE_URL; ?>Operan/operasiOperan" method="POST">
    <div class="opn-modal" id="opnOperasiModal" aria-hidden="true">
      <div class="opn-modal__backdrop" data-opn-close></div>
      <div class="opn-modal__panel" role="dialog" aria-modal="true" aria-labelledby="opnOperasiTitle">
        <div class="opn-modal__head">
          <div>
            <h3 id="opnOperasiTitle">Selesai <span class="operasi"></span></h3>
            <small>Pilih karyawan &amp; isi pack/hanger</small>
          </div>
          <button type="button" class="opn-modal__close" data-opn-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="opn-modal__body">
          <div class="opn-field">
            <label class="opn-label" for="opnKaryawan">Karyawan</label>
            <select id="opnKaryawan" name="f1" class="tize karyawan" style="width:100%" required>
              <option value="" selected disabled></option>
              <optgroup label="<?= htmlspecialchars($this->dCabang['nama'] ?? '') ?> [<?= htmlspecialchars($this->dCabang['kode_cabang'] ?? '') ?>]">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] ?>-<?= htmlspecialchars(strtoupper($a['nama_user'])) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="---- Cabang Lain ----">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] ?>-<?= htmlspecialchars(strtoupper($a['nama_user'])) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
            <input type="hidden" class="idItem" name="f2" required>
            <input type="hidden" class="valueItem" name="f3" required>
            <input type="hidden" class="idCabang" name="idCabang" required>
            <input type="hidden" class="textNotif" name="text" required>
            <input type="hidden" class="hpNotif" name="hp" required>
          </div>
          <div class="opn-field letakRAK">
            <div class="opn-row">
              <div>
                <label class="opn-label" for="opnPack">Pack</label>
                <input id="opnPack" type="number" min="0" value="1" name="pack" class="opn-input" required>
              </div>
              <div>
                <label class="opn-label" for="opnHanger">Hanger</label>
                <input id="opnHanger" type="number" min="0" value="0" name="hanger" class="opn-input" required>
              </div>
            </div>
          </div>
        </div>
        <div class="opn-modal__foot">
          <button type="button" class="opn-btn opn-btn--ghost" data-opn-close>Batal</button>
          <button type="submit" class="opn-btn opn-btn--primary"><i class="fas fa-check"></i> Selesai</button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  // ========== JS ERROR LOGGER ==========
  (function() {
    const LOG_ENDPOINT = '<?= URL::BASE_URL ?>Operan/jsLog';
    function sendLog(type, message, url, line, column, stack) {
      try {
        fetch(LOG_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: type,
            message: message,
            url: url || window.location.href,
            line: line || 'N/A',
            column: column || 'N/A',
            stack: stack || '',
            userAgent: navigator.userAgent
          })
        }).catch(function() {});
      } catch (e) {}
    }
    window.onerror = function(message, source, lineno, colno, error) {
      sendLog('ERROR', message, source, lineno, colno, (error && error.stack) || '');
      return false;
    };
    window.addEventListener('unhandledrejection', function(event) {
      sendLog('PROMISE_ERROR', (event.reason && event.reason.message) || String(event.reason),
        window.location.href, 'N/A', 'N/A', (event.reason && event.reason.stack) || '');
    });
  })();
  // ========== END JS ERROR LOGGER ==========

  (function initOperanContent() {
    var $modal = $("#opnOperasiModal");
    var karyawanSelectize = null;

    function openOperanModal() {
      $modal.addClass("is-open").attr("aria-hidden", "false");
      initKaryawanSelectize();
    }
    function closeOperanModal() {
      $modal.removeClass("is-open").attr("aria-hidden", "true");
      if (karyawanSelectize) {
        karyawanSelectize.clear(true);
      }
    }
    function initKaryawanSelectize() {
      var $sel = $("#opnKaryawan");
      if (!$sel.length || typeof $sel.selectize !== "function") return;
      if ($sel[0].selectize) {
        karyawanSelectize = $sel[0].selectize;
        return;
      }
      karyawanSelectize = $sel.selectize({
        allowEmptyOption: true
      })[0].selectize;
    }

    $(document).off("click.opnOps", "#operan-root .addOperasi").on("click.opnOps", "#operan-root .addOperasi", function() {
      var $btn = $(this);
      var layanan = $btn.attr("data-layanan");
      $("form.jq").attr("data-operasi", "operasi");
      $("input.idItem").val($btn.attr("data-id"));
      $("input.valueItem").val($btn.attr("data-value"));
      $("input.idCabang").val($btn.attr("data-cabang"));
      $("span.operasi, .operasi").text(layanan);

      var idNya = $btn.attr("data-id");
      $("input.textNotif").val($("span.selesai" + idNya).html());
      $("input.hpNotif").val($("span.selesai" + idNya).attr("data-hp"));
      openOperanModal();
    });

    $modal.off("click.opnClose").on("click.opnClose", "[data-opn-close]", function() {
      closeOperanModal();
    });
    $(document).off("keydown.opnEsc").on("keydown.opnEsc", function(e) {
      if (e.key === "Escape" && $modal.hasClass("is-open")) {
        closeOperanModal();
      }
    });

    $("form.jq").off("submit.opn").on("submit.opn", function(e) {
      e.preventDefault();
      var $form = $(this);
      var $submit = $form.find('button[type="submit"]');
      var prev = $submit.html();
      $submit.prop("disabled", true).html('<span class="opn-spin" aria-hidden="true"></span> Menyimpan…');

      $.ajax({
        url: $form.attr("action"),
        data: $form.serialize(),
        type: $form.attr("method"),
        success: function(response) {
          closeOperanModal();
          $submit.prop("disabled", false).html(prev);
          if (response == 0 || response === "" || response === "0") {
            if (typeof loadDiv === "function") loadDiv();
          } else {
            alert(response);
          }
        },
        error: function(xhr) {
          $submit.prop("disabled", false).html(prev);
          alert("Gagal: " + (xhr.responseText || xhr.status));
        }
      });
    });
  })();
</script>
