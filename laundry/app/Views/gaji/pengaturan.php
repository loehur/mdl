<?php
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

$penjualanMap = [];
foreach ($this->dPenjualan as $p) {
  $penjualanMap[(int) $p['id_penjualan_jenis']] = $p['penjualan_jenis'] ?? ('#' . $p['id_penjualan_jenis']);
}
$layananMap = [];
foreach ($this->dLayanan as $l) {
  $layananMap[(int) $l['id_layanan']] = $l['layanan'] ?? ('#' . $l['id_layanan']);
}
?>
<div class="content mt-3" id="gaji-pengaturan-root" style="max-width: 900px;">
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

<script>
(function () {
  var root = document.getElementById('gaji-pengaturan-root');
  if (!root) return;

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
        alert(res || 'Gagal');
      },
      error: function () {
        $btn.prop('disabled', false);
        alert('Gagal menyimpan');
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
            alert(res || 'Gagal');
            $el.text(old);
          }
        },
        error: function () {
          alert('Gagal update');
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
        alert(res || 'Gagal');
      },
      error: function () {
        alert('Gagal hapus');
      }
    });
  });
})();
</script>
