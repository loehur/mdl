<table class="table">
  <thead>
    <tr>
      <th>Date</th>
      <th class="text-end">Sales</th>
    </tr>
  </thead>
  <?php foreach ($data['tgl'] as $c) {
    $day = date('l, d M Y', strtotime($c)) ?>
    <tr>
      <td><?= $day ?></td>
      <td class="text-end"><?= $data['qty'][$c]['t'] ?></td>
    </tr>
  <?php } ?>
</table>
<div class="offcanvas offcanvas-end overflow-hidden" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="canvas1">
  <div class="bg-light bg-gradient mb-2" style="box-shadow: 0px 1px 10px silver;">
    <div class="row py-2" style="cursor: pointer;" data-bs-dismiss="offcanvas">
      <div class="col py-2 w-100 text-dark text-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
  <div class="offcanvas-body pt-0">
    <div class="px-1 pt-2" id="load1"></div>
  </div>
  <div style="max-height: 50px; cursor:pointer" class="w-100 mt-1 bg-light bg-gradient" data-bs-dismiss="offcanvas">
    <div class="d-flex justify-content-center" style="box-shadow: 0px -1px 10px silver; height:50px">
      <div class="align-self-center"><i class="fas fa-arrow-left"></i> &nbsp; Kembali</div>
    </div>
  </div>
</div>