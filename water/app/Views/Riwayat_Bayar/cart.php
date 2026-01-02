<table class="table table-sm mx-0 mt-2">
  <thead style="cursor: pointer;">
    <tr>
      <th class="border-top-0">Pesanan</th>
      <th class="text-end border-top-0">Total</th>
    </tr>
  </thead>

  <tbody id="ubah_pesanan" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight1" aria-controls="offcanvasRight" style="cursor: pointer;">
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
      Total
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
        Sisa
      </th>
      <th class="text-end"><?= number_format($total - $dibayar) ?></th>
    </tr>
  <?php } ?>
</table>