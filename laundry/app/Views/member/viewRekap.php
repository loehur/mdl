<div class="col ml-2">


  <?php
  if (!isset($data['id_pelanggan'])) { ?>
    <div class="row">
      <div class="col p-0 pl-1 mb-1">
        <input id="searchInput" style="width:200px" class="form-control form-control-sm bg-light" type="text" placeholder="Pelanggan">
      </div>
    </div>
  <?php }
  ?>

  <div class="row pr-3">
      <?php
      $cols = 0;
      foreach ($data['data'] as $z) {
        $id = $z['id_pelanggan'];
        $id_harga = $z['id_harga'];
        $join = $id . $id_harga;
        $kategori = "";
        $layanan = "";
        $durasi = "";
        $unit = "";
        $nama_pelanggan = "";

        foreach ($this->harga as $a) {
          if ($a['id_harga'] == $z['id_harga']) {
            foreach ($this->dPenjualan as $dp) {
              if ($dp['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
                foreach ($this->dSatuan as $ds) {
                  if ($ds['id_satuan'] == $dp['id_satuan']) {
                    $unit = $ds['nama_satuan'];
                  }
                }
              }
            }
            foreach (unserialize($a['list_layanan']) as $b) {
              foreach ($this->dLayanan as $c) {
                if ($b == $c['id_layanan']) {
                  $layanan = $layanan . " " . $c['layanan'];
                }
              }
            }
            foreach ($this->dDurasi as $c) {
              if ($a['id_durasi'] == $c['id_durasi']) {
                $durasi = $durasi . " " . $c['durasi'];
              }
            }

            foreach ($this->itemGroup as $c) {
              if ($a['id_item_group'] == $c['id_item_group']) {
                $kategori = $kategori . " " . $c['item_kategori'];
              }
            }

            foreach ($this->pelanggan as $dp) {
              if ($dp['id_pelanggan'] == $z['id_pelanggan']) {
                $nama_pelanggan = $dp['nama_pelanggan'];
              }
            }
          }
        }

        $saldo = $z['saldo'] - $data['pakai'][$join];

        if ($saldo > 1) {
          $cols += 1;
          echo "<div class='col m-1 backShow pelanggan-" . strtoupper($nama_pelanggan) . " p-0 rounded' style='max-width:350px;'><div class='bg-white rounded'>";
          echo "<table class='table table-sm m-0 rounded w-100'>";
      ?>
          <tr>
            <td class="w-100" nowrap>
              <span class="text-bold"><a class="cek" href="<?= URL::BASE_URL ?>Member/tambah_paket/<?= $z['id_pelanggan'] ?>" data-p="<?= $z['id_pelanggan'] ?>"><?= strtoupper($nama_pelanggan) ?></a> | <span class="text-success"><b>M<?= $id_harga ?></b></span>
                <br></span>
              <?= $kategori ?>, <?= $layanan ?>, <?= $durasi ?>
            </td>
            <td class="text-right"><b><?= number_format($saldo, 2) . $unit ?></b>
              <br>
              <a href="<?= URL::BASE_URL ?>I/m/<?= $id ?>/<?= $id_harga ?>" target="_blank">
                Riwayat</b>
              </a>
            </td>
            <?php
            if (isset($data['id_pelanggan'])) { ?>
              <td id='btnTambah' class="text-right pt-2">
                <button class="btn btn-sm btn-outline-success p-1 buttonTambah" data-id_harga="<?= $id_harga ?>" data-id_pelanggan="<?= $z['id_pelanggan'] ?>" data-bs-target="#modalMemberDeposit"><small><b>Tambah</b></small></button>
              </td>
            <?php } ?>
          </tr>
      <?php
          echo "</table></div></div>";
          if ($cols == 2) {
            echo '<div class="w-100"></div>';
            $cols = 0;
          }
        }
      } ?>
  </div>
</div>

<script>
  $("input#searchInput").on("keyup change", function() {
    pelanggan = $(this).val().toUpperCase();
    if (pelanggan.length > 0) {
      $("div.backShow").addClass('d-none');
      $("[class*=" + pelanggan + "]").removeClass('d-none');
    } else {
      $(".backShow").removeClass('d-none');
    }
  });

  $("button.buttonTambah").on("click", function(e) {
    var id_harga = $(this).attr("data-id_harga");
    var id_pelanggan = $(this).attr("data-id_pelanggan");
    $('div.tambahPaket').load("<?= URL::BASE_URL ?>Member/orderPaket/" + id_pelanggan + "/" + id_harga);

    // Manual modal trigger
    var target = $(this).attr('data-bs-target');
    if(target) {
        var modalEl = document.querySelector(target);
        if(modalEl) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }
  });
</script>