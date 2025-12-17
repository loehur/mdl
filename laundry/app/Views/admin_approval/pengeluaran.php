<div class="row mx-0">
  <?php
  $no = 0;
  $cols = 0;
  foreach ($data[0] as $a) {
    $sts = $a['status_mutasi'];
    $cols++;
    foreach ($this->dStatusMutasi as $st) {
      if ($sts == $st['id_status_mutasi']) {
        $stBayar = $st['status_mutasi'];
      }
    }
    $id = $a['id_kas'];
    $f1 = $a['insertTime'];
    $f2 = $a['note'];
    $f3 = $a['id_user'];
    $f4 = $a['jumlah'];
    $f17 = $a['id_client'];
    $note = $a['note_primary'];
    $karyawan = '';
    foreach ($this->userMerge as $c) {
      if ($c['id_user'] == $f3) {
        $karyawan = $c['nama_user'];
      }
    }

    $pelanggan = '';
    foreach ($this->pelanggan as $c) {
      if ($c['id_pelanggan'] == $f17) {
        $pelanggan = $c['nama_pelanggan'];
      }
    }
  ?>
    <div class="col px-1 mb-2" style='min-width:300px;'>
      <div class='bg-white border'>
        <table class="table m-0 mb-1 p-0 table-sm w-100 table-borderless">
          <?php
          echo "<tr>";
          echo "<td nowrap colspan=2>#" . $id . ", " . $karyawan . " <small>" . $f1 . "</small></span><br>
                <span data-mode='4' data-id_value='" . $id . "' data-value='" . $f4 . "'></span><span class='text-primary'><b>" . strtoupper($note) . "</span></b> 
                 <span>" . ucwords($f2) . ", </span> 
                <br><b class='float-right'>Rp" . number_format($f4) . "</b></td>";
          echo "</tr>";
          ?>
          <tr>
            <td>
              <span class="btn btn-sm btn-danger nTunai" data-id="<?= $id ?>" data-target="<?= URL::BASE_URL; ?>Setoran/operasi/4">Tolak</span>
            </td>
            <td class='text-right'>
              <span class="btn btn-sm btn-success nTunai" data-id="<?= $id ?>" data-target="<?= URL::BASE_URL; ?>Setoran/operasi/3">Konfirmasi</span>
            </td>
          </tr>
        </table>
      </div>
    </div>
  <?php
    if ($cols == 4) {
      echo '<div class="w-100"></div>';
      $cols = 0;
    }
  } ?>
</div>

<div class="row mx-0">
  <?php
  $no = 0;
  $cols = 0;
  foreach ($data[1] as $a) {
    $cols++;
    $sts = $a['status_mutasi'];
    foreach ($this->dStatusMutasi as $st) {
      if ($sts == $st['id_status_mutasi']) {
        $stBayar = $st['status_mutasi'];
      }
    }

    switch ($sts) {
      case "3":
        $cls = "table-success text-success";
        break;
      case "4";
        $cls = "table-danger text-danger";
        break;
    }

    $id = $a['id_kas'];
    $f1 = $a['insertTime'];
    $f2 = $a['note'];
    $f3 = $a['id_user'];
    $f4 = $a['jumlah'];
    $f17 = $a['id_client'];
    $note = $a['note_primary'];

    $karyawan = '';
    foreach ($this->userMerge as $c) {
      if ($c['id_user'] == $f3) {
        $karyawan = $c['nama_user'];
      }
    }

    $pelanggan = '';
    foreach ($this->pelanggan as $c) {
      if ($c['id_pelanggan'] == $f17) {
        $pelanggan = $c['nama_pelanggan'];
      }
    }
  ?>
    <div class="col px-1 mb-2" style='min-width:300px;'>
      <div class='bg-white border'>
        <table class="table m-0 table-sm w-100 table-borderless">
          <?php
          echo "<tr>";
          echo "<td nowrap colspan=2>#" . $id . ", " . $karyawan . " <small>" . $f1 . "</small></span><br>
                <span data-mode='4' data-id_value='" . $id . "' data-value='" . $f4 . "'></span><span class='text-primary'><b>" . strtoupper($note) . "</span></b> 
                 <span>" . ucwords($f2) . ", </span> 
                <br><b class='float-right'>Rp" . number_format($f4) . "</b></td>";
          echo "</tr>";
          ?>
          <tr>
            <td colspan="2" class="<?= $cls ?> text-center text-bold">
              <?= $stBayar ?>
            </td>
          </tr>
        </table>
      </div>
    </div>
  <?php
    if ($cols == 4) {
      echo '<div class="w-100"></div>';
      $cols = 0;
    }
  } ?>
</div>

<script>
  $("span.nTunai").on("click", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr("data-target"),
      data: {
        id: $(this).attr('data-id'),
      },
      type: "POST",
      success: function(response) {
        location.reload(true);
      },
    });
  });
</script>