<?php
/** @var array $customerRequestsSiap */
/** @var array $customerRequestsBelum */
$isEmptyCustomer = empty($customerRequestsSiap) && empty($customerRequestsBelum);
?>
<?php if ($isEmptyCustomer) { ?>
  <div class="dlv-empty">
    <i class="fas fa-motorcycle" aria-hidden="true"></i>
    <strong>Belum ada order delivery</strong>
    <span>Request chat (YCloud/Fonnte) dan portal customer tampil di sini setelah jenis Jemput/Antar aktif.</span>
  </div>
<?php } else { ?>
  <?php if (!empty($customerRequestsSiap)) { ?>
    <div class="dlv-board-section dlv-board-section--siap">
      <h3 class="dlv-board-section__title">
        <span><i class="fas fa-check-circle"></i> Siap diselesaikan</span>
        <span class="dlv-board-section__count"><?= (int) count($customerRequestsSiap) ?></span>
      </h3>
      <div class="dlv-list">
        <?php foreach ($customerRequestsSiap as $rq) {
          include __DIR__ . '/customer_request_entry.php';
        } ?>
      </div>
    </div>
  <?php } ?>
  <?php if (!empty($customerRequestsBelum)) { ?>
    <div class="dlv-board-section dlv-board-section--belum">
      <h3 class="dlv-board-section__title">
        <span><i class="fas fa-hourglass-half"></i> Belum bisa diselesaikan</span>
        <span class="dlv-board-section__count"><?= (int) count($customerRequestsBelum) ?></span>
      </h3>
      <div class="dlv-list">
        <?php foreach ($customerRequestsBelum as $rq) {
          include __DIR__ . '/customer_request_entry.php';
        } ?>
      </div>
    </div>
  <?php } ?>
<?php } ?>
