<?php
// Framework tidak auto-extract, semua ada di $data array
$grouped = $data['grouped'] ?? [];
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$isEmpty = empty($grouped);
?>

<div class="container-fluid p-3">
  <!-- Header & Filter -->
  <div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-check-circle me-2 text-success"></i>Order Selesai</h5>
        <span class="text-success fw-medium">
          <?= count($grouped) ?> Transaksi
        </span>
      </div>
      
      <!-- Filter Tanggal -->
      <form method="GET" action="<?= URL::BASE_URL ?>Sales/operasi_tuntas" class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Dari Tanggal</label>
          <input type="date" name="start" class="form-control" value="<?= $startDate ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Sampai Tanggal</label>
          <input type="date" name="end" class="form-control" value="<?= $endDate ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter me-2"></i>Filter
          </button>
          <a href="<?= URL::BASE_URL ?>Sales/operasi_tuntas" class="btn btn-secondary ms-2">
            <i class="fas fa-redo me-2"></i>Reset
          </a>
        </div>
      </form>
      
      <p class="text-muted small mt-3 mb-0 py-1">
        <i class="fas fa-info-circle me-1"></i>Maksimal rentang tanggal: 1 Minggu (7 hari)
      </p>
    </div>
  </div>

  <!-- Data List -->
  <?php if ($isEmpty) { ?>
    <div class="card shadow-sm border-0">
      <div class="card-body text-center py-5">
        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada transaksi selesai pada periode ini</p>
      </div>
    </div>
  <?php } else { ?>
    
    <div class="row g-3">
    <?php foreach ($grouped as $ref => $group) { ?>
      <div class="col-md-6">
      <div class="card mb-3 shadow-sm border-0">
        <!-- Header -->
        <div class="card-header bg-dark text-white py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <span class="small">Ref:</span> <span class="fw-bold">#<?= $ref ?></span>
              <span class="text-success" style="font-size: 0.7rem;">
                <i class="fas fa-check me-1"></i>Lunas
              </span>
            </div>
            <div class="text-end">
              <span class="small text-white-50"><?= date('d/m/y H:i', strtotime($group['date'])) ?></span>
            </div>
          </div>
        </div>
        
        <!-- Body - Items -->
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tbody>
              <?php foreach ($group['items'] as $item) { 
                $margin = $item['margin'] ?? 0;
                $displayPrice = $item['price'] + $margin;
                $qtyFmt = rtrim(rtrim(number_format($item['qty'], 1, ',', '.'), '0'), ',');
                $unitLbl = trim($item['unit_nama'] ?? '');
                $descBar = trim($item['deskripsi_barang'] ?? '');
              ?>
                <tr>
                  <td class="ps-3 py-2">
                    <span class="fw-medium"><?= $item['nama_barang'] ?></span>
                    <?php if ($descBar !== '') { ?>
                    <div class="text-muted" style="font-size: 0.7rem; line-height: 1.25;"><?= htmlspecialchars($descBar, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                    <?php if ($item['denom'] != 1) { ?>
                      <span class="text-muted ms-1" style="font-size: 0.75rem;">@<?= $item['denom'] ?></span>
                    <?php } ?>
                    <div class="text-muted small"><?= $qtyFmt ?><?= $unitLbl !== '' ? ' ' . htmlspecialchars($unitLbl, ENT_QUOTES, 'UTF-8') : '' ?> x Rp<?= number_format($displayPrice) ?></div>
                  </td>
                  <td class="text-end pe-3 py-2 align-middle">
                    <span class="fw-bold">Rp<?= number_format($displayPrice * $item['qty']) ?></span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
            <tfoot class="border-top">
              <tr>
                <td class="ps-3 py-2 fw-bold">TOTAL</td>
                <td class="text-end pe-3 py-2 fw-bold text-dark">Rp<?= number_format($group['total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <?php if (!empty($group['payments'])) { ?>
        <div class="card-footer py-2 px-3 bg-light border-top">
          <small class="text-muted fw-bold"><i class="fas fa-history me-1"></i>Riwayat pembayaran</small>
          <table class="table table-sm table-borderless mb-0 mt-1" style="font-size: 0.9rem;">
            <?php foreach ($group['payments'] as $payment) {
              $st = (int) ($payment['status_mutasi'] ?? 0);
              $statusClass = $st === 3 ? 'text-success' : 'text-warning';
              $statusText = $st === 3 ? 'Lunas' : 'Pending';
            ?>
            <tr>
              <td class="py-1 ps-0"><?= date('d/m/y H:i', strtotime($payment['insertTime'])) ?></td>
              <td class="py-1">
                <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                <?php if (!empty($payment['note'])) { ?>
                  <span class="text-muted">(<?= htmlspecialchars($payment['note'], ENT_QUOTES, 'UTF-8') ?>)</span>
                <?php } ?>
              </td>
              <td class="py-1 text-end pe-0"><span class="fw-bold">Rp<?= number_format($payment['jumlah']) ?></span></td>
            </tr>
            <?php } ?>
            <tr class="border-top">
              <td colspan="2" class="py-1 ps-0"><span class="text-muted">Total dibayar</span></td>
              <td class="py-1 text-end pe-0"><span class="fw-bold text-success">Rp<?= number_format($group['total_paid'] ?? 0) ?></span></td>
            </tr>
            <?php if (($group['sisa'] ?? 0) != 0) { ?>
            <tr>
              <td colspan="2" class="py-1 ps-0"><span class="fw-bold text-danger">Sisa</span></td>
              <td class="py-1 text-end pe-0"><span class="fw-bold text-danger">Rp<?= number_format($group['sisa']) ?></span></td>
            </tr>
            <?php } ?>
          </table>
        </div>
        <?php } ?>
        
        <!-- Footer Actions -->
        <div class="card-footer p-2 text-end">
           <button type="button" class="btn btn-sm btn-outline-secondary" data-print-ref="<?= $ref ?>" data-print-pelanggan="">
             <i class="fas fa-print me-1"></i>Cetak
           </button>
        </div>
        
        <!-- Hidden Print Template for view_load.js - ID MUST be print + REF -->
        <div id="print<?= $ref ?>" class="d-none">
          <table style="width: 100%;">
            <tr>
              <td colspan="2" style="text-align: center;">
                <b><?= $this->dCabang['nama'] ?? 'LAUNDRY' ?> - <?= $this->dCabang['kode_cabang'] ?? '' ?></b><br>
                <?= $this->dCabang['alamat'] ?? '' ?><br>
                <?= $this->dCabang['phone_number'] ?? '' ?>
              </td>
            </tr>
            <tr id="dashRow"><td></td></tr>
            <tr>
              <td><b>#<?= $ref ?></b><br><?= date('d/m/y H:i', strtotime($group['date'])) ?></td>
              <td style="text-align: right;"></td>
            </tr>
            <tr id="dashRow"><td></td></tr>
            <?php foreach ($group['items'] as $item) { 
              $margin = $item['margin'] ?? 0;
              $price = $item['price'] + $margin;
              $subtotal = $price * $item['qty'];
              $qtyFmtP = rtrim(rtrim(number_format($item['qty'], 1, ',', '.'), '0'), ',');
              $unitLblP = trim($item['unit_nama'] ?? '');
              $descBarP = trim($item['deskripsi_barang'] ?? '');
            ?>
            <tr><td><?= $item['nama_barang'] ?><?php if ($descBarP !== '') { ?><br><span style="font-size: 9px; color: #666;"><?= htmlspecialchars($descBarP, ENT_QUOTES, 'UTF-8') ?></span><?php } ?></td><td>&#8203;</td></tr>
            <tr>
              <td><?= $qtyFmtP ?><?= $unitLblP !== '' ? ' ' . htmlspecialchars($unitLblP, ENT_QUOTES, 'UTF-8') : '' ?> x <?= number_format($price) ?></td>
              <td style="text-align: right;"><?= number_format($subtotal) ?></td>
            </tr>
            <?php } ?>
            <tr id="dashRow"><td></td></tr>
            <tr>
              <td><b>TOTAL</b></td>
              <td style="text-align: right;"><b><?= number_format($group['total']) ?></b></td>
            </tr>
            <?php if (!empty($group['payments'])) { ?>
            <tr id="dashRow"><td></td></tr>
            <?php foreach ($group['payments'] as $payment) { ?>
            <tr>
              <td><?= date('d/m H:i', strtotime($payment['insertTime'])) ?> <?= htmlspecialchars($payment['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td style="text-align: right;"><?= number_format($payment['jumlah']) ?></td>
            </tr>
            <?php } ?>
            <tr>
              <td><small>Total dibayar</small></td>
              <td style="text-align: right;"><small><?= number_format($group['total_paid'] ?? 0) ?></small></td>
            </tr>
            <?php } ?>
            <tr>
              <td colspan="2" style="text-align: center;">Terima Kasih</td>
            </tr>
          </table>
        </div>
      </div>
      </div> <!-- /col-md-6 -->
    <?php } ?>
    </div> <!-- /row -->
  <?php } ?>
</div>

<script>
  window.ViewLoadConfig = {
    baseUrl: '<?= URL::BASE_URL ?>',
    kodeCabang: '<?= $this->dCabang['id_cabang'] ?? '' ?>',
    marginTop: <?= $this->mdl_setting["margin_printer_top"] ?? 0 ?>,
    feedLines: <?= $this->mdl_setting["margin_printer_bottom"] ?? 0 ?>
  };
</script>
<script src="<?= URL::IN_ASSETS ?>js/operasi/view_load.js?v=<?= time() ?>"></script>
