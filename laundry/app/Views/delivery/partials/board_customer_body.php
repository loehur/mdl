<?php
/** @var array $customerRequestsSiap */
$isEmptyCustomer = empty($customerRequestsSiap);
?>
<?php if ($isEmptyCustomer) { ?>
  <div class="dlv-empty">
    <i class="fas fa-motorcycle" aria-hidden="true"></i>
    <strong>Belum ada order delivery</strong>
    <span>Request chat (YCloud/Fonnte) dan portal customer tampil di sini setelah jenis Jemput/Antar aktif.</span>
  </div>
<?php } else { ?>
  <div class="dlv-list">
    <?php foreach ($customerRequestsSiap as $rq) {
      include __DIR__ . '/customer_request_entry.php';
    } ?>
  </div>
<?php } ?>
