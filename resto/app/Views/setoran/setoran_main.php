  <div class="row mx-0 bg-white">
    <?php
    $no = 0;
    $cols = 0;
    foreach ($data['list'] as $a) {
      $sts = $a['status_mutasi'];
      if ($sts == 2) {
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
        } ?>
        <div class="col px-1 border">
          <div class='bg-white'>
            <table class="table m-0 mb-1 p-0 table-borderless table-sm w-100">
              <?php
              echo "<tr>";
              echo "<td colspan=2>#" . $id . ", " . $karyawan . "<br><small>" . date("d-m-Y", strtotime($f1)) . "</small></span><br>
                <span data-mode='4' data-id_value='" . $id . "' data-value='" . $f4 . "'></span><span class='text-primary'><b>" . strtoupper($f2) . ", </span> 
                Rp" . number_format($f4) . "</b></td>";
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
      }
    } ?>

  </div>

  <div class="row mt-1 mx-0 bg-white">
    <?php
    $no = 0;
    $cols = 0;
    foreach ($data['list'] as $a) {
      $sts = $a['status_mutasi'];
      if ($sts <> 2) {
        $cols++;
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
        } ?>

        <div class="col px-1 border" style="min-width: 150px;">
          <div class='bg-white rounded'>
            <table class="table m-0 p-0 table-borderless table-sm w-100 mb-1">
              <?php
              echo "<tr>";
              echo "<td colspan=2>#" . $id . ", " . $karyawan . "<br><small>" . date("d-m-Y", strtotime($f1))  . "</small></span><br>
                <span data-mode='4' data-id_value='" . $id . "' data-value='" . $f4 . "'></span><span class='text-primary'><b><span class='text-nowrap'>" . strtoupper($f2) . "</span>, </span> 
                Rp" . number_format($f4) . "</b></td>";
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
      }
    } ?>
  </div>

  <!-- SCRIPT -->
  <script src="<?= URL::ASSETS_URL ?>js/jquery-3.6.0.min.js"></script>
  <script src="<?= URL::ASSETS_URL ?>js/popper.min.js"></script>
  <script src="<?= URL::ASSETS_URL ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= URL::ASSETS_URL ?>plugins/bootstrap/js/bootstrap.min.js"></script>
  <script src="<?= URL::ASSETS_URL ?>plugins/datatables/jquery.dataTables.min.js"></script>

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