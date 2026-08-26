<?php
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$pengaliRef = is_array($data['pengali_ref'] ?? null) ? $data['pengali_ref'] : [1 => 0, 2 => 0];
$feeTerimaRef = (int) ($pengaliRef[1] ?? 0);
$feeKembaliRef = (int) ($pengaliRef[2] ?? 0);

$feeFormula = is_array($data['fee_formula'] ?? null) ? $data['fee_formula'] : [
  'malam' => ['pengali' => 1, 'clamp_min' => 14000, 'clamp_max' => 32000],
  'cuci' => ['pengali' => 4, 'clamp_min' => 65000, 'clamp_max' => 85000],
];
$fmtPengali = function ($v) {
  $s = rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
  return $s === '' ? '0' : $s;
};

$penjualanMap = [];
foreach ($this->dPenjualan as $p) {
  $penjualanMap[(int) $p['id_penjualan_jenis']] = $p['penjualan_jenis'] ?? ('#' . $p['id_penjualan_jenis']);
}
$layananMap = [];
foreach ($this->dLayanan as $l) {
  $layananMap[(int) $l['id_layanan']] = $l['layanan'] ?? ('#' . $l['id_layanan']);
}
?>
<style>
  #gaji-pengaturan-root .gp-fee-btn {
    height: 28px;
    padding: 0 10px;
    border: 1px solid var(--mdl-line);
    background: #fff;
    color: var(--mdl-ink-soft);
    font-family: 'fontku', sans-serif;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }
  #gaji-pengaturan-root .gp-fee-btn:hover {
    background: var(--mdl-accent-soft);
    border-color: var(--mdl-accent);
    color: var(--mdl-accent-deep);
  }
  #gaji-pengaturan-root .gp-fee-btn i {
    font-size: 11px;
  }

  .gp-fee-modal {
    position: fixed;
    inset: 0;
    z-index: 2100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .gp-fee-modal.is-open { display: flex; }
  .gp-fee-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
  }
  .gp-fee-modal__panel {
    position: relative;
    width: 100%;
    max-width: 720px;
    max-height: min(86vh, 720px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--mdl-ink);
    box-shadow: var(--mdl-shadow);
    z-index: 1;
  }
  .gp-fee-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--mdl-line-soft);
    background: linear-gradient(180deg, var(--mdl-surface-2), #fff);
  }
  .gp-fee-modal__head h3 {
    margin: 0;
    font-family: 'fontku', sans-serif;
    font-size: 15px;
    font-weight: 900;
    color: var(--mdl-ink);
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .gp-fee-modal__close {
    border: 1px solid var(--mdl-line);
    background: #fff;
    color: var(--mdl-ink-soft);
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .gp-fee-modal__close:hover {
    background: #f1f5f9;
    color: var(--mdl-ink);
  }
  .gp-fee-modal__meta {
    padding: 10px 14px;
    border-bottom: 1px solid var(--mdl-line-soft);
    background: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    color: var(--mdl-ink-soft);
    line-height: 1.45;
  }
  .gp-fee-modal__meta b { color: var(--mdl-ink); font-weight: 900; }
  .gp-fee-modal__body {
    padding: 0;
    overflow: auto;
    flex: 1;
  }
  .gp-fee-modal__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .gp-fee-modal__table th,
  .gp-fee-modal__table td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--mdl-line-soft);
    vertical-align: middle;
  }
  .gp-fee-modal__table th {
    position: sticky;
    top: 0;
    background: #f1f5f9;
    font-family: 'fontku', sans-serif;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--mdl-ink-soft);
    text-align: left;
  }
  .gp-fee-modal__table th.text-end,
  .gp-fee-modal__table td.text-end { text-align: right; }
  .gp-fee-modal__kode {
    font-family: 'fontku', sans-serif;
    font-weight: 900;
    color: var(--mdl-ink);
  }
  .gp-fee-modal__sub {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
  }
  .gp-fee-modal__fee {
    font-family: monospace;
    font-weight: 800;
    color: var(--mdl-accent-deep);
  }
  .gp-fee-modal__badge {
    display: inline-block;
    padding: 2px 7px;
    border: 1px solid var(--mdl-line);
    background: #fff;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.02em;
    color: var(--mdl-ink-soft);
  }
  .gp-fee-modal__badge--ok {
    border-color: var(--mdl-live-deep);
    background: #f0fdf4;
    color: var(--mdl-live-deep);
  }
  .gp-fee-modal__badge--miss {
    border-color: var(--mdl-train-deep);
    background: var(--mdl-train-surface);
    color: var(--mdl-train-deep);
  }
  .gp-fee-modal__badge--clamp {
    border-color: var(--mdl-accent);
    background: var(--mdl-accent-soft);
    color: var(--mdl-accent-deep);
  }
  .gp-fee-modal__empty {
    padding: 28px 16px;
    text-align: center;
    color: var(--mdl-ink-soft);
    font-weight: 700;
  }
  .gp-fee-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid var(--mdl-line-soft);
    background: #f8fafc;
  }
  .gp-fee-modal__btn {
    height: 36px;
    padding: 0 14px;
    border: 1px solid var(--mdl-line);
    background: #fff;
    color: var(--mdl-ink-soft);
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
  }
  .gp-fee-modal__btn:hover {
    background: #e2e8f0;
    color: var(--mdl-ink);
  }
</style>

<div class="content mt-3" id="gaji-pengaturan-root" style="max-width: 960px;">
  <div class="card border-0 mb-3">
    <div class="card-body p-3">
      <h6 class="mb-2">Rumus Fee Snapshot</h6>
      <p class="small text-muted mb-3">Fee = round((pendapatan bulan lalu / 1000) × pengali), lalu clamp. Pembagi /1000 tetap.</p>
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Jenis</th>
            <th class="text-end">Pengali</th>
            <th class="text-end">Clamp Min</th>
            <th class="text-end">Clamp Max</th>
            <th style="width:1%"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $formulaLabels = ['malam' => 'Jaga Malam', 'cuci' => 'Cuci'];
          foreach ($formulaLabels as $kode => $label) {
            $f = $feeFormula[$kode] ?? ['pengali' => 1, 'clamp_min' => 0, 'clamp_max' => 0];
          ?>
            <tr data-kode="<?= htmlspecialchars($kode) ?>">
              <td><?= htmlspecialchars($label) ?></td>
              <td class="text-end">
                <span class="formula-edit" data-kode="<?= htmlspecialchars($kode) ?>" data-col="pengali"><?= htmlspecialchars($fmtPengali($f['pengali'])) ?></span>
              </td>
              <td class="text-end">
                <span class="formula-edit" data-kode="<?= htmlspecialchars($kode) ?>" data-col="clamp_min"><?= (int) $f['clamp_min'] ?></span>
              </td>
              <td class="text-end">
                <span class="formula-edit" data-kode="<?= htmlspecialchars($kode) ?>" data-col="clamp_max"><?= (int) $f['clamp_max'] ?></span>
              </td>
              <td class="text-end">
                <button type="button" class="gp-fee-btn btn-preview-fee" data-kode="<?= htmlspecialchars($kode) ?>" data-label="<?= htmlspecialchars($label) ?>" title="Fee semua cabang bulan ini">
                  <i class="fas fa-store"></i> Fee Cabang
                </button>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
      <small class="text-muted">Double-click nilai untuk edit. Pengali boleh desimal (contoh 4.1). Tombol Fee Cabang menampilkan fee bulan ini per outlet.</small>
    </div>
  </div>

  <div class="card border-0 mb-3">
    <div class="card-body p-3">
      <h6 class="mb-3">Fee Laundry Terima / Kembali (global)</h6>
      <table class="table table-sm mb-0" style="max-width: 360px;">
        <thead class="table-light">
          <tr>
            <th>Jenis</th>
            <th class="text-end">Fee (Rp)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Laundry Terima</td>
            <td class="text-end">
              <span class="pengali-ref-edit" data-id_pengali="1"><?= $feeTerimaRef ?></span>
            </td>
          </tr>
          <tr>
            <td>Laundry Kembali</td>
            <td class="text-end">
              <span class="pengali-ref-edit" data-id_pengali="2"><?= $feeKembaliRef ?></span>
            </td>
          </tr>
        </tbody>
      </table>
      <small class="text-muted">Double-click fee untuk edit. Berlaku untuk semua karyawan.</small>
    </div>
  </div>

  <div class="card border-0 mb-3">
    <div class="card-body p-3">
      <h6 class="mb-3">Tambah Fee Layanan Laundry</h6>
      <form id="formGajiLaundryRef" method="POST" action="<?= URL::BASE_URL ?>GajiPengaturan/insert">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">Jenis Penjualan</label>
            <select name="jenis_penjualan" class="form-control form-control-sm" required>
              <option value="" selected disabled>--</option>
              <?php foreach ($this->dPenjualan as $a) { ?>
                <option value="<?= (int) $a['id_penjualan_jenis'] ?>"><?= htmlspecialchars($a['penjualan_jenis']) ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Layanan</label>
            <select name="id_layanan" class="form-control form-control-sm" required>
              <option value="" selected disabled>--</option>
              <?php foreach ($this->dLayanan as $a) { ?>
                <option value="<?= (int) $a['id_layanan'] ?>"><?= htmlspecialchars($a['layanan']) ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Fee (Rp)</label>
            <input type="number" name="gaji_laundry" class="form-control form-control-sm" min="0" value="0" required>
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">Target</label>
            <input type="number" name="target" class="form-control form-control-sm" min="0" value="0" required>
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">Max</label>
            <input type="number" name="max_target" class="form-control form-control-sm" min="0" value="0" required>
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">Bonus</label>
            <input type="number" name="bonus_target" class="form-control form-control-sm" min="0" value="0" required>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">Simpan</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0">
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Jenis</th>
            <th>Layanan</th>
            <th class="text-end">Fee</th>
            <th class="text-end">Target</th>
            <th class="text-end">Max Target</th>
            <th class="text-end">Bonus/Target</th>
            <th style="width:56px"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($rows) < 1) { ?>
            <tr>
              <td colspan="7" class="text-muted p-3">Belum ada acuan fee. Tambah di form atas.</td>
            </tr>
          <?php } else {
            foreach ($rows as $r) {
              $id = (int) ($r['id'] ?? 0);
              $jenisNama = $penjualanMap[(int) ($r['jenis_penjualan'] ?? 0)] ?? ('#' . ($r['jenis_penjualan'] ?? ''));
              $layananNama = $layananMap[(int) ($r['id_layanan'] ?? 0)] ?? ('#' . ($r['id_layanan'] ?? ''));
          ?>
            <tr data-id="<?= $id ?>">
              <td><?= htmlspecialchars($jenisNama) ?></td>
              <td><?= htmlspecialchars($layananNama) ?></td>
              <td class="text-end">
                <span class="ref-edit" data-col="gaji_laundry" data-id="<?= $id ?>"><?= (int) $r['gaji_laundry'] ?></span>
              </td>
              <td class="text-end">
                <span class="ref-edit" data-col="target" data-id="<?= $id ?>"><?= (int) $r['target'] ?></span>
              </td>
              <td class="text-end">
                <span class="ref-edit" data-col="max_target" data-id="<?= $id ?>"><?= (int) $r['max_target'] ?></span>
              </td>
              <td class="text-end">
                <span class="ref-edit" data-col="bonus_target" data-id="<?= $id ?>"><?= (int) $r['bonus_target'] ?></span>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-ref" data-id="<?= $id ?>" title="Hapus">&times;</button>
              </td>
            </tr>
          <?php }
          } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="gp-fee-modal" id="modalGpFeeCabang" aria-hidden="true">
  <div class="gp-fee-modal__backdrop" data-gp-fee-close></div>
  <div class="gp-fee-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalGpFeeCabangLabel">
    <div class="gp-fee-modal__head">
      <h3 id="modalGpFeeCabangLabel"><i class="fas fa-calculator"></i> <span id="gpFeeTitle">Fee Cabang</span></h3>
      <button type="button" class="gp-fee-modal__close" data-gp-fee-close aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="gp-fee-modal__meta" id="gpFeeMeta">Memuat...</div>
    <div class="gp-fee-modal__body">
      <div id="gpFeeEmpty" class="gp-fee-modal__empty d-none">Tidak ada cabang.</div>
      <table class="gp-fee-modal__table" id="gpFeeTable">
        <thead>
          <tr>
            <th>Cabang</th>
            <th class="text-end">Pendapatan</th>
            <th class="text-end">Fee</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="gpFeeTbody"></tbody>
      </table>
    </div>
    <div class="gp-fee-modal__foot">
      <button type="button" class="gp-fee-modal__btn" data-gp-fee-close>Tutup</button>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('gaji-pengaturan-root');
  if (!root) return;

  function notify(msg, type) {
    if (window.MdlToast) {
      if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
      else if (type === 'error' || type === 'danger') MdlToast.error(msg);
      else if (type === 'ok' || type === 'success') MdlToast.ok(msg);
      else MdlToast.info(msg);
    } else {
      alert(msg);
    }
  }

  function fmtRp(n) {
    var x = Math.round(Number(n) || 0);
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function fmtYm(ym) {
    var p = String(ym || '').split('-');
    if (p.length < 2) return ym;
    var bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var m = parseInt(p[1], 10);
    return (bulan[m] || p[1]) + ' ' + p[0];
  }

  var $modal = $('#modalGpFeeCabang');
  function openFeeModal() {
    $modal.addClass('is-open').attr('aria-hidden', 'false');
  }
  function closeFeeModal() {
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
  }
  $modal.on('click', '[data-gp-fee-close]', closeFeeModal);
  $(document).on('keydown.gpFee', function (e) {
    if (e.key === 'Escape' && $modal.hasClass('is-open')) closeFeeModal();
  });

  function renderFeeRows(res) {
    var rows = res.rows || [];
    var $tb = $('#gpFeeTbody');
    $tb.empty();
    if (!rows.length) {
      $('#gpFeeTable').addClass('d-none');
      $('#gpFeeEmpty').removeClass('d-none');
      return;
    }
    $('#gpFeeEmpty').addClass('d-none');
    $('#gpFeeTable').removeClass('d-none');

    rows.forEach(function (r) {
      var alamat = String(r.alamat || '').trim();
      if (alamat.length > 36) alamat = alamat.slice(0, 36) + '…';
      var pend = r.has_snapshot == 1 ? fmtRp(r.pendapatan) : '—';
      var badge;
      if (r.has_snapshot != 1) {
        badge = '<span class="gp-fee-modal__badge gp-fee-modal__badge--miss">Tanpa snapshot → clamp min</span>';
      } else if (r.clamped == 1) {
        badge = '<span class="gp-fee-modal__badge gp-fee-modal__badge--clamp">Clamp</span>';
      } else {
        badge = '<span class="gp-fee-modal__badge gp-fee-modal__badge--ok">Snapshot</span>';
      }
      $tb.append(
        '<tr>' +
          '<td><span class="gp-fee-modal__kode">' + $('<div>').text(r.kode_cabang).html() + '</span>' +
            (alamat ? '<span class="gp-fee-modal__sub">' + $('<div>').text(alamat).html() + '</span>' : '') +
          '</td>' +
          '<td class="text-end">' + pend + '</td>' +
          '<td class="text-end"><span class="gp-fee-modal__fee">' + fmtRp(r.fee) + '</span></td>' +
          '<td>' + badge + '</td>' +
        '</tr>'
      );
    });
  }

  $(root).on('click', '.btn-preview-fee', function () {
    var kode = $(this).data('kode');
    var label = $(this).data('label') || kode;
    $('#gpFeeTitle').text('Fee ' + label);
    $('#gpFeeMeta').text('Memuat data semua cabang...');
    $('#gpFeeTbody').empty();
    openFeeModal();

    $.ajax({
      url: '<?= URL::BASE_URL ?>GajiPengaturan/previewFeeCabang',
      type: 'GET',
      data: { kode: kode },
      dataType: 'json',
      success: function (res) {
        if (!res || res.ok != 1) {
          $('#gpFeeMeta').text((res && res.msg) || 'Gagal memuat');
          notify((res && res.msg) || 'Gagal memuat', 'error');
          return;
        }
        var pengali = res.pengali;
        var pengaliTxt = String(pengali);
        if (pengaliTxt.indexOf('.') >= 0) {
          pengaliTxt = pengaliTxt.replace(/0+$/, '').replace(/\.$/, '');
        }
        $('#gpFeeMeta').html(
          'Fee berlaku <b>' + fmtYm(res.ym) + '</b> · dari pendapatan <b>' + fmtYm(res.periode_lalu) + '</b><br>' +
          'Rumus: round((pendapatan / 1000) × <b>' + pengaliTxt + '</b>), clamp <b>' +
          fmtRp(res.clamp_min) + '</b> – <b>' + fmtRp(res.clamp_max) + '</b> · ' +
          '<b>' + (res.rows || []).length + '</b> cabang'
        );
        renderFeeRows(res);
      },
      error: function () {
        $('#gpFeeMeta').text('Gagal memuat data');
        notify('Gagal memuat fee cabang', 'error');
      }
    });
  });

  $('#formGajiLaundryRef').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type=submit]');
    $btn.prop('disabled', true);
    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: $form.serialize(),
      success: function (res) {
        $btn.prop('disabled', false);
        if (String(res).trim() === '1') {
          location.reload();
          return;
        }
        notify(res || 'Gagal', 'error');
      },
      error: function () {
        $btn.prop('disabled', false);
        notify('Gagal menyimpan', 'error');
      }
    });
  });

  $(root).on('dblclick', '.formula-edit', function () {
    var $el = $(this);
    if ($el.find('input').length) return;
    var kode = $el.data('kode');
    var col = $el.data('col');
    var old = $el.text().trim();
    var step = col === 'pengali' ? '0.0001' : '1';
    var $inp = $('<input type="number" class="form-control form-control-sm text-end" min="0" step="' + step + '" style="width:110px;display:inline-block">');
    $inp.val(old);
    $el.empty().append($inp);
    $inp.focus().select();

    var save = function () {
      var raw = $inp.val();
      var val;
      if (col === 'pengali') {
        val = parseFloat(raw);
        if (isNaN(val) || val <= 0) {
          $el.text(old);
          return;
        }
      } else {
        val = parseInt(raw, 10);
        if (isNaN(val) || val < 0) {
          $el.text(old);
          return;
        }
      }
      $.ajax({
        url: '<?= URL::BASE_URL ?>GajiPengaturan/updateFormula',
        type: 'POST',
        data: { kode: kode, col: col, value: val },
        success: function (res) {
          if (String(res).trim() === '1') {
            if (col === 'pengali') {
              var s = String(val);
              if (s.indexOf('.') >= 0) {
                s = s.replace(/0+$/, '').replace(/\.$/, '');
              }
              $el.text(s);
            } else {
              $el.text(val);
            }
          } else {
            notify(res || 'Gagal', 'error');
            $el.text(old);
          }
        },
        error: function () {
          notify('Gagal update', 'error');
          $el.text(old);
        }
      });
    };

    $inp.on('blur', save);
    $inp.on('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        $inp.blur();
      }
      if (ev.key === 'Escape') {
        $el.text(old);
      }
    });
  });

  $(root).on('dblclick', '.pengali-ref-edit', function () {
    var $el = $(this);
    if ($el.find('input').length) return;
    var idPengali = $el.data('id_pengali');
    var old = $el.text().trim();
    var $inp = $('<input type="number" class="form-control form-control-sm text-end" min="0" style="width:110px;display:inline-block">');
    $inp.val(old);
    $el.empty().append($inp);
    $inp.focus().select();

    var save = function () {
      var val = parseInt($inp.val(), 10);
      if (isNaN(val) || val < 0) {
        $el.text(old);
        return;
      }
      $.ajax({
        url: '<?= URL::BASE_URL ?>GajiPengaturan/upsertPengali',
        type: 'POST',
        data: { id_pengali: idPengali, gaji_pengali: val },
        success: function (res) {
          if (String(res).trim() === '1') {
            $el.text(val);
          } else {
            notify(res || 'Gagal', 'error');
            $el.text(old);
          }
        },
        error: function () {
          notify('Gagal update', 'error');
          $el.text(old);
        }
      });
    };

    $inp.on('blur', save);
    $inp.on('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        $inp.blur();
      }
      if (ev.key === 'Escape') {
        $el.text(old);
      }
    });
  });

  $(root).on('dblclick', '.ref-edit', function () {
    var $el = $(this);
    if ($el.find('input').length) return;
    var id = $el.data('id');
    var col = $el.data('col');
    var old = $el.text().trim();
    var $inp = $('<input type="number" class="form-control form-control-sm text-end" min="0" style="width:90px;display:inline-block">');
    $inp.val(old);
    $el.empty().append($inp);
    $inp.focus().select();

    var save = function () {
      var val = parseInt($inp.val(), 10);
      if (isNaN(val) || val < 0) {
        $el.text(old);
        return;
      }
      $.ajax({
        url: '<?= URL::BASE_URL ?>GajiPengaturan/update',
        type: 'POST',
        data: { id: id, col: col, value: val },
        success: function (res) {
          if (String(res).trim() === '1') {
            $el.text(val);
          } else {
            notify(res || 'Gagal', 'error');
            $el.text(old);
          }
        },
        error: function () {
          notify('Gagal update', 'error');
          $el.text(old);
        }
      });
    };

    $inp.on('blur', save);
    $inp.on('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        $inp.blur();
      }
      if (ev.key === 'Escape') {
        $el.text(old);
      }
    });
  });

  $(root).on('click', '.btn-hapus-ref', function () {
    var id = $(this).data('id');
    if (!confirm('Hapus acuan fee ini?')) return;
    $.ajax({
      url: '<?= URL::BASE_URL ?>GajiPengaturan/delete',
      type: 'POST',
      data: { id: id },
      success: function (res) {
        if (String(res).trim() === '1') {
          location.reload();
          return;
        }
        notify(res || 'Gagal', 'error');
      },
      error: function () {
        notify('Gagal hapus', 'error');
      }
    });
  });

})();
</script>
