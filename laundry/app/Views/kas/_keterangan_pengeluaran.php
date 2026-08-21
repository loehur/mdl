<div class="form-group pg-ket-wrap">
  <label class="pg-ket-label">Keterangan/Banyak</label>
  <div class="pg-ket-normal">
    <input type="text" name="f1" class="form-control pg-ket-input-free" placeholder="">
  </div>
  <div class="pg-ket-minyak d-none">
    <select name="f1_kendaraan" class="form-control form-control-sm pg-ket-kendaraan-select" disabled>
      <option value="" selected disabled>Pilih kendaraan</option>
      <?php
      $kendaraanList = is_array($this->dPengeluaranKendaraan ?? null) ? $this->dPengeluaranKendaraan : [];
      foreach ($kendaraanList as $k) {
        $kid = (int) ($k['id_kendaraan'] ?? 0);
        $nama = (string) ($k['nama_kendaraan'] ?? '');
        $lainnya = (int) ($k['is_lainnya'] ?? 0);
        if ($kid <= 0 || $nama === '') {
          continue;
        }
      ?>
        <option value="<?= $kid ?>" data-lainnya="<?= $lainnya ?>"><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></option>
      <?php } ?>
    </select>
    <input type="text" name="f1_lainnya" class="form-control form-control-sm mt-2 pg-ket-lainnya d-none" placeholder="Isi keterangan kendaraan lainnya" maxlength="80" disabled>
  </div>
</div>
