<div class="card p-3 mt-1">
  <label class="text-primary">Data Karyawan</label>
  <table class="table table-sm mb-0" style="width: 100%;">
    <?php foreach ($data as $d) { ?>
      <tr>
        <td class="text-end">#<?= $d['id_user'] ?></td>
        <td><span class="text-success"></span> <?= $d['nama_user'] ?></td>
      </tr>
    <?php } ?>
  </table>
</div>