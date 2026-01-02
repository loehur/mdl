  <?php
  $total_bon = [];
  foreach ($data['order'] as $ref => $a) {
    $total_bon[$ref] = 0; ?>
    <div class="border-bottom py-2">
      <table class="w-100 mx-0">
        <thead>
          <tr>
            <th colspan="2" class="text-purple">#<?= $ref ?></th>
          </tr>
        </thead>
        <?php
        foreach ($a as $key => $d) {
          $total_awal = ($d['harga'] * $d['qty']);
          $subTotal = ($d['harga'] * $d['qty']) - $d['diskon'];
          $total_bon[$ref] += $subTotal; ?>
          <tr>
            <td>
              <small></small><span class="fw-bold"><?= $data['menu'][$key]['nama'] ?></span><br>
              <?= $d['qty'] ?>x @<?= number_format($d['harga']) ?> <?= number_format($total_awal) ?>
            </td>
            <td class="text-end">
              <?php if ($d['diskon'] > 0) { ?>
                <small class="text-success">Disc. <?= number_format($d['diskon']) ?></small>
              <?php } ?>
              <br><?= number_format($subTotal) ?>
            </td>
          </tr>
        <?php } ?>
        <tr class="table-borderless">
          <th class="text-end">
            Total
          </th>
          <th class="text-end"><?= number_format($total_bon[$ref]) ?></th>
        </tr>
        <?php
        $dibayar[$ref] = 0;
        foreach ($data['bayar'][$ref] as $b) {
          $dibayar[$ref] += $b['jumlah']; ?>
          <tr>
            <td class="text-end"><?= URL::METOD_BAYAR[$b['metode_mutasi']] ?></td>
            <td class="text-end">-<?= number_format($b['jumlah'])  ?></td>
          </tr>
        <?php } ?>

        <?php if (count($data['bayar'][$ref]) > 0) { ?>
          <tr class="table-borderless">
            <th class="text-end">
              Sisa
            </th>
            <th class="text-end"><?= number_format($total_bon[$ref] - $dibayar[$ref]) ?></th>
          </tr>
        <?php } ?>
      </table>
    </div>
  <?php } ?>