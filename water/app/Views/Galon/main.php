<link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />
<div class="mt-3"></div>
<table id="dt_tb" class="w-100 table table-sm mt-2">
  <thead>
    <tr>
      <th>
        Pelanggan
      </th>
      <th class="text-end">
        Galon/Lama
      </th>
    </tr>
  </thead>
  <tbody>
    <?php
    foreach ($data['pelanggan'] as $key => $r) { ?>

      <?php
      $t1 = date_create(date('Y-m-d', strtotime($r['last_order'])));
      $t2 = date_create(date("Y-m-d"));

      $diff = date_diff($t1, $t2);
      $beda = $diff->format('%R%a') + 0;

      if ($beda < 1) {
        continue;
      }
      ?>

      <tr id="row<?= $r['id'] ?>" data-id="<?= $r['id'] ?>" data-pelanggan="<?= strtoupper($r['nama']) ?>" data-titip="<?= $r['titip'] ?>" class="mx-0 py-1 cekPesanan" style="cursor: pointer;" aria-controls="offcanvasRight">
        <td>
          <b class="text-success"><?= strtoupper($r['nama']) ?></b><br>
          <span class=""><?= date('d M y', strtotime($r['last_order'])) ?></span>
        </td>
        <td class="text-end pe-2">
          <span class="fw-bold"><i class="fa-light fa-bottle-water"></i> <?= $r['titip'] ?></span><br>
          <span class="fw-bold text-danger"><?= $beda ?> Hari</span>
        </td>
      </tr>
    <?php } ?>
  </tbody>
</table>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">

    <div class="row mt-5">
      <div class="col text-center">
        <h4 class="fw-bold text-success" id="pelanggan"></h4>
        <h5 class="fw-bold text-purple"><span id="galon"></span> Galon</h5>
      </div>
    </div>

    <div class="row mt-5">
      <div class="col text-center">
        <button class="btn btn-danger w-100" onclick="ambilGalon()">Ambil Galon</button>
      </div>
    </div>

  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<script src="<?= URL::ASSETS_URL ?>plugins/DataTables/datatables.min.js"></script>
<script>
  $(document).ready(function() {
    $('#dt_tb').dataTable({
      "bLengthChange": false,
      "bFilter": true,
      "bInfo": false,
      "ordering": false,
      "bAutoWidth": false,
      "pageLength": 100,
      "scrollY": 530,
      "dom": "lfrti"
    });
  });

  var id_pelanggan = 0;
  $(".cekPesanan").click(function() {
    buka_canvas('offcanvasRight');
    id_pelanggan = $(this).data('id');
    $("#pelanggan").html($(this).data('pelanggan'));
    $("#galon").html($(this).data('titip'));
  })

  function ambilGalon() {
    $.ajax({
      url: '<?= URL::BASE_URL ?>Galon/ambilGalon',
      type: "POST",
      data: {
        id_pelanggan: id_pelanggan
      },
      success: function(response) {
        response = JSON.parse(response);
        if (response.status) {
          $("#row" + id_pelanggan).remove();
          $('.offcanvas.show').each(function() {
            $(this).offcanvas('hide');
          });
        } else {
          alert(response.message);
        }
      },
    })
  }
</script>