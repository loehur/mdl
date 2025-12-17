<div class="card p-3 mt-1">
  <label class="text-primary">Hari Ini</label>
  <table class="table table-sm mb-0" style="width: 100%;">
    <?php foreach ($data['hari_ini'] as $d) {

      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $d['id_karyawan']) {
          $nama = "" . $c['nama_user'] . "";
        }
      }

      $jenis = "NaN - " . $d['jenis'];

      if ($d['jenis'] == 0) {
        $jenis = "Cuci";
      } else if ($d['jenis'] == 1) {
        $jenis = "Jaga Malam";
      } else if ($d['jenis'] == 2) {
        $jenis = "Delivery";
      } else if ($d['jenis'] == 3) {
        $jenis = "Maintenance";
      }

    ?>
      <tr>
        <td class="text-end">#<?= $d['id'] ?></td>
        <td><span class="text-success"><i class="far fa-check-circle"></i></span> <?= $jenis ?></td>
        <td><?= $nama ?></td>
        <td><i class="far fa-clock"></i> <?= $d['jam'] ?></td>
      </tr>
    <?php } ?>
  </table>
</div>

<div class="card p-3 mt-1 text-secondary">
  <label class="">Kemarin</label>
  <table class="table table-sm mb-0" style="width: 100%;">
    <?php foreach ($data['kemarin'] as $d) {

      foreach ($this->userMerge as $c) {
        if ($c['id_user'] == $d['id_karyawan']) {
          $nama = "" . $c['nama_user'] . "";
        }
      }

      $jenis = "NaN - " . $d['jenis'];

      if ($d['jenis'] == 0) {
        $jenis = "Cuci";
      } else if ($d['jenis'] == 1) {
        $jenis = "Jaga Malam";
      } else if ($d['jenis'] == 2) {
        $jenis = "Delivery";
      } else if ($d['jenis'] == 3) {
        $jenis = "Maintenance";
      }

    ?>
      <tr>
        <td class="text-end">#<?= $d['id'] ?></td>
        <td><span class="text-success"><i class="far fa-check-circle"></i></span> <?= $jenis ?></td>
        <td><?= $nama ?></td>
        <td><i class="far fa-clock"></i> <?= $d['jam'] ?></td>
      </tr>
    <?php } ?>
  </table>
</div>