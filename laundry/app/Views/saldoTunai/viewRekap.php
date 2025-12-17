<div class="col ml-2">

  <?php
  if ($data['client'] == 0) { ?>
    <div class="row">
      <div class="col p-0 pl-1 mb-1">
        <input id="searchInput" style="width:200px" class="form-control form-control-sm bg-light" type="text" placeholder="Pelanggan">
      </div>
    </div>
  <?php }
  ?>

  <div class="row pr-3">
    <tbody>
      <?php
      $cols = 0;
      foreach ($data['saldo'] as $z => $val) {
        $nama_pelanggan = "";
        foreach ($this->pelanggan as $dp) {
          if ($dp['id_pelanggan'] == $z) {
            $nama_pelanggan = $dp['nama_pelanggan'];
          }
        }

        $saldo = $val - $data['pakai'][$z];

        if ($saldo > 1) {
          $cols += 1;
          echo "<div class='col m-1 backShow pelanggan-" . strtoupper($nama_pelanggan) . " p-0 rounded' style='max-width:350px;'><div class='bg-white rounded'>";
          echo "<table class='table table-sm m-0 rounded w-100'>";
      ?>
          <tr>
            <td nowrap>
              <span class="text-bold"><a class="cek" href="<?= URL::BASE_URL ?>SaldoTunai/tambah/<?= $z ?>" data-p="<?= $z ?>"><?= strtoupper($nama_pelanggan) ?></a></b></span>
            </td>
            <td class="text-right"><b>Rp<?= number_format($saldo) ?></b><a href="<?= URL::BASE_URL ?>I/s/<?= $z ?>" target="_blank">
                Riwayat</b>
              </a>
            </td>
            <?php
            if (isset($data['id_pelanggan'])) { ?>
              <td id='btnTambah' class="text-right pt-2">
                <button class="btn btn-sm btn-outline-success p-1 buttonTambah" data-id_harga="<?= $id_harga ?>" data-id_pelanggan="<?= $z['id_pelanggan'] ?>" data-bs-toggle="modal" data-bs-target="#exampleModal"><small><b>Tambah</b></small></button>
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

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>

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
    $('div.tambahPaket').load("<?= URL::BASE_URL ?>SaldoTunai/orderPaket/" + id_pelanggan + "/" + id_harga);
  });
</script>