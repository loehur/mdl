<?php
$karyawan = is_array($data['karyawan'] ?? null) ? $data['karyawan'] : [];
$jenis = [3 => 'Harian', 4 => 'Tunjangan Bulanan', 6 => 'Cuci', 5 => 'Jaga Malam'];
?>
<div class="content mt-3" style="max-width: 1100px;">
  <div class="card border-0">
    <div class="card-body p-3">
      <h6 class="mb-1">Pengaturan Karyawan</h6>
      <p class="small text-muted mb-3">CRUD fee pengali untuk karyawan aktif di cabang yang sedang dipilih. Fee Cuci dan Jaga Malam adalah minimum khusus karyawan.</p>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="feeKaryawanTable">
          <thead class="table-light">
            <tr>
              <th>Karyawan</th>
              <?php foreach ($jenis as $label) { ?><th class="text-end"><?= htmlspecialchars($label) ?></th><?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($karyawan === []) { ?>
              <tr><td colspan="5" class="text-muted p-3">Tidak ada karyawan aktif di cabang ini.</td></tr>
            <?php } foreach ($karyawan as $k) {
              $idUser = (int) ($k['id_user'] ?? 0);
              $fees = is_array($k['fee_pengali'] ?? null) ? $k['fee_pengali'] : [];
            ?>
              <tr data-user="<?= $idUser ?>">
                <td><b><?= htmlspecialchars(strtoupper((string) ($k['nama_user'] ?? ''))) ?></b> <small class="text-muted">#<?= $idUser ?></small></td>
                <?php foreach ($jenis as $idPengali => $label) {
                  $has = array_key_exists($idPengali, $fees);
                  $value = $has ? (int) $fees[$idPengali] : '';
                ?>
                  <td class="text-end" data-pengali="<?= $idPengali ?>">
                    <div class="d-inline-flex gap-1 align-items-center">
                      <input type="number" min="0" step="1" value="<?= htmlspecialchars((string) $value) ?>" placeholder="Belum diatur" class="form-control form-control-sm text-end fee-input" style="width:110px">
                      <button type="button" class="btn btn-sm btn-primary fee-save" title="Simpan"><i class="fas fa-save"></i></button>
                      <button type="button" class="btn btn-sm btn-outline-danger fee-delete <?= $has ? '' : 'd-none' ?>" title="Hapus"><i class="fas fa-times"></i></button>
                    </div>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var $table = $('#feeKaryawanTable');
  function notice(msg, type) {
    if (window.MdlToast) { MdlToast[type === 'error' ? 'error' : 'ok'](msg); } else { alert(msg); }
  }
  function meta(el) {
    var $cell = $(el).closest('td');
    return { $cell: $cell, id_user: parseInt($cell.closest('tr').data('user'), 10), id_pengali: parseInt($cell.data('pengali'), 10) };
  }
  $table.on('click', '.fee-save', function () {
    var m = meta(this), $btn = $(this), fee = parseInt(m.$cell.find('.fee-input').val(), 10);
    if (!m.id_user || !m.id_pengali || isNaN(fee) || fee < 0) { notice('Fee harus bernilai 0 atau lebih.', 'error'); return; }
    $btn.prop('disabled', true);
    $.post('<?= URL::BASE_URL ?>GajiPengaturan/saveFeePengaliKaryawan', { id_user: m.id_user, id_pengali: m.id_pengali, gaji_pengali: fee })
      .done(function (res) { if (String(res).trim() === '1') { m.$cell.find('.fee-delete').removeClass('d-none'); notice('Fee tersimpan.', 'ok'); } else notice(res || 'Gagal menyimpan.', 'error'); })
      .fail(function () { notice('Gagal menyimpan.', 'error'); })
      .always(function () { $btn.prop('disabled', false); });
  });
  $table.on('click', '.fee-delete', function () {
    var m = meta(this), $btn = $(this);
    if (!confirm('Hapus fee ini?')) return;
    $btn.prop('disabled', true);
    $.post('<?= URL::BASE_URL ?>GajiPengaturan/deleteFeePengaliKaryawan', { id_user: m.id_user, id_pengali: m.id_pengali })
      .done(function (res) { if (String(res).trim() === '1') { m.$cell.find('.fee-input').val(''); $btn.addClass('d-none'); notice('Fee dihapus.', 'ok'); } else notice(res || 'Gagal menghapus.', 'error'); })
      .fail(function () { notice('Gagal menghapus.', 'error'); })
      .always(function () { $btn.prop('disabled', false); });
  });
})();
</script>
