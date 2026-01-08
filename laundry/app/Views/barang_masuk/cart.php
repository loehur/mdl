<?php if (empty($data['cart'])) { ?>
  <div class="text-center text-muted py-3">
    <i class="fas fa-cart-arrow-down"></i> Keranjang kosong
  </div>
<?php } else { ?>
  <div class="list-group list-group-flush">
    <?php foreach ($data['cart'] as $key => $item) { 
        $hargaJual = ($item['harga'] ?? 0) + ($item['margin'] ?? 0);
    ?>
      <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-1 cart-item" 
           data-harga="<?= $hargaJual ?>" data-qty="<?= $item['qty'] ?>">
        <div class="flex-grow-1">
          <span class="fw-medium text-sm"><?= $item['nama'] ?></span>
          <?php 
          // Tampilkan denom jika denom != 1
          $denom = $item['denom'] ?? 1;
          if ($denom != 1) { 
              // Format denom - jika desimal tampilkan sebagai fraksi atau angka biasa
              if ($denom < 1) {
                  $denomDisplay = rtrim(rtrim(number_format($denom, 2), '0'), '.');
              } else {
                  $denomDisplay = $denom;
              }
          ?>
            <span class="badge bg-info ms-1">@<?= $denomDisplay ?></span>
          <?php } ?>
          <br>
          <small class="text-muted">
            <?= $item['qty'] ?> x Rp<?= number_format($hargaJual) ?>
          </small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success">Rp<?= number_format($hargaJual * $item['qty']) ?></span>
          <button type="button" class="btn btn-sm btn-outline-danger removeFromCart" data-key="<?= $key ?>">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } ?>
