<table class="table table-sm m-0">
  <tbody>
    <?php
    $no = 0;
    foreach ($data['penarikan'] as $a) {
      $id = $a['id'];
      $f1 = $a['insertTime'];
      $f2 = $a['note'];
      $f2b = $a['note_primary'];
      $f4 = $a['jumlah'];
      $st = $a['status_mutasi']; ?>
      <tr id="tr<?= $id ?>">
        <td class="text-end">
          <small>#<?= $id ?> <?= date('d M, H:i', strtotime($f1)) ?> </small>
          <br><span class="badge bg-gradient bg-success"><?= $f2b ?></span> <b>Rp<span><?= number_format($f4) ?></span></b></span>
          <br><small><?= $f2 ?></small>
        </td>
        <td class='text-right align-content-center'>
          <span class="btn btn-outline-success" x-on:click="verify(<?= $id ?>,1,<?= $data['key'] ?>)"><i class="fas fa-check"></i></span>
        </td>
      </tr>
    <?php } ?>
  </tbody>
</table>