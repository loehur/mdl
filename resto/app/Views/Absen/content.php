<div class="card mt-1">
  <label class="text-primary px-3 pt-2">Hari Ini</label>
  <table class="table table-sm mb-0" style="width: 100%;">
    <?php foreach ($data['hari_ini'] as $d) {
      $nama = $_SESSION['resto_users'][$d['id_karyawan']]['nama_user'];
    ?>
      <tr>
        <td class="text-end">#<?= $d['id'] ?></td>
        <td><span class="text-success"><i class="far fa-check-circle"></i></span> <?= URL::TUGAS[$d['jenis']] ?></td>
        <td><?= $nama ?></td>
        <td><i class="far fa-clock"></i> <?= $d['jam'] ?></td>
      </tr>
    <?php } ?>
  </table>
</div>

<div class="card mt-1 text-secondary">
  <label class="px-3 pt-2">Kemarin</label>
  <table class="table table-sm mb-0" style="width: 100%;">
    <?php foreach ($data['kemarin'] as $d) {
      $nama = $_SESSION['resto_users'][$d['id_karyawan']]['nama_user']; ?>
      <tr>
        <td class="text-end">#<?= $d['id'] ?></td>
        <td><span class="text-success"><i class="far fa-check-circle"></i></span> <?= URL::TUGAS[$d['jenis']] ?></td>
        <td><?= $nama ?></td>
        <td><i class="far fa-clock"></i> <?= $d['jam'] ?></td>
      </tr>
    <?php } ?>
  </table>
</div>