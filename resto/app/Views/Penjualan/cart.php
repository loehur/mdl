<table class="table table-sm mx-0">
  <thead style="cursor: pointer;" id="pesan">
    <tr>
      <th class="text-purple border-top-0">Pesanan (+)</th>
      <th class="text-end border-top-0">Total</th>
    </tr>
  </thead>

  <tbody id="ubah_pesanan" style="cursor: pointer;">
    <?php
    $total = 0;;
    foreach ($data['order'] as $key => $d) { ?>
      <?php
      $total_awal = ($d['harga'] * $d['qty']);
      $subTotal = ($d['harga'] * $d['qty']) - $d['diskon'];
      $total += $subTotal;
      ?>
      <tr>
        <td>
          <span class="fw-bold"><?= $data['menu'][$key]['nama'] ?></span><br>
          <?= $d['qty'] ?>x @<?= number_format($d['harga']) ?> <?= number_format($total_awal) ?>
        </td>
        <td class="text-end">
          <?php if ($d['diskon'] > 0) { ?>
            <small class="text-success">Disc. <?= number_format($d['diskon']) ?></small><br>
          <?php } ?>
          <?= number_format($subTotal) ?>
        </td>
      </tr>
    <?php } ?>
  </tbody>
  <tr class="table-borderless">
    <th class="text-end">
      TOTAL
    </th>
    <th class="text-end"><?= number_format($total) ?></th>
  </tr>
  <?php
  $dibayar = 0;
  foreach ($data['bayar'] as $b) {
    $dibayar += $b['jumlah'] ?>
    <tr>
      <td class="text-end"><?= URL::METOD_BAYAR[$b['metode_mutasi']] ?></td>
      <td class="text-end">-<?= number_format($b['jumlah'])  ?></td>
    </tr>
  <?php } ?>

  <?php if (count($data['bayar']) > 0) { ?>
    <tr class="table-borderless">
      <th class="text-end">
        SISA
      </th>
      <th class="text-end"><?= number_format($total - $dibayar) ?></th>
    </tr>
  <?php } ?>
</table>
<?php if ($total > 0 && $_SESSION['resto_user']['id_privilege'] >= 30) { ?>
  <div class="d-flex flex-row justify-content-between px-1">
    <div class="piutang" onclick="load_piutang('<?= $data['ref']['id'] ?>')"><button class="btn rounded-0 btn-outline-danger">Jadikan Piutang</button></div>
    <div class="bayar" onclick="load_bayar('<?= $data['ref']['id'] ?>')"><button class="btn rounded-0 btn-outline-success">Pembayaran</button></div>
  </div>
<?php } ?>

<div class="pb-5"></div>

<script>
  $("#pesan").click(function() {
    buka_canvas('offcanvasRight');
  })

  $("#ubah_pesanan").click(function() {
    buka_canvas('offcanvasRight1');
  })


  $(".bayar").click(function() {
    buka_canvas('offcanvasRight2');
  })

  $(".piutang").click(function() {
    buka_canvas('offcanvasRight3');
  })
</script>