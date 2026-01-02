<link rel="stylesheet" href="<?= URL::ASSETS_URL ?>plugins/DataTables/datatables.min.css" rel="stylesheet" />
<pre>
  <?php
  $tgl_order = $data['tgl_order'];
  $t2 = date_create(date("Y-m-d"));
  ?>
</pre>
<table id="dt_tb" class="w-100 table table-sm mt-2">
  <thead>
    <tr>
      <td>Nama</td>
      <td>H</td>
      <td class="text-end">Jumlah</td>
    </tr>
  </thead>
  <tbody>
    <?php
    foreach ($data['data'] as $key => $r) {
      $t1 = date_create(min($tgl_order[$key]));
      $diff = date_diff($t1, $t2);
      $tempo = $diff->format('%R%a') + 0; ?>
      <tr>
        <td class="cekPesanan" style="cursor: pointer;" data-ref="<?= $key ?>">
          <span class="text-purple fw-bold"><?= strtoupper($data['pelanggan'][$key]['nama']) ?></span>
        </td>
        <td><?= $tempo ?></td>
        <td class="text-end bayarPiutang pe-2" style="cursor: pointer;" data-ref="<?= $key ?>">
          <span class="fw-bold">Rp<?= number_format($r) ?></span><br>
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
    <div class="px-1 pt-2" id="cart"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="offcanvasRight2" aria-labelledby="offcanvasRightLabel">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1 pt-2" id="bayarPiutang"></div>
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
      "ordering": true,
      "order": [
        [1, 'desc']
      ],
      "bAutoWidth": false,
      "pageLength": 100,
      "scrollY": 530,
      "dom": "lfrti"
    });
  });

  $(".cekPesanan").click(function() {
    buka_canvas('offcanvasRight');
    var ref = $(this).attr('data-ref');
    $("div#cart").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#cart").load('<?= URL::BASE_URL ?>Piutang/cart/' + ref);
    });
  })

  $(".bayarPiutang").click(function() {
    buka_canvas('offcanvasRight2');
    var ref = $(this).attr('data-ref');
    $("div#bayarPiutang").load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
      $("div#bayarPiutang").load('<?= URL::BASE_URL ?>Piutang/cek_bayar/' + ref);
    });
  })
</script>