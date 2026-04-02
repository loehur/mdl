<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview Nota Sales | MDL</title>
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
  <style>
    body { font-family: system-ui, sans-serif; background: #f4f6f9; }
  </style>
</head>
<body class="p-2 p-md-3">
<?php
$error = $data['error'] ?? null;
$group = $data['group'] ?? null;
?>
  <?php if ($error) { ?>
    <div class="alert alert-danger mb-0 shadow-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php } elseif ($group) {
    $ref = $group['ref'];
    $type = $group['type'] ?? 1;
    $state = $group['items'][0]['state'] ?? 0;
    $sourceId = (int) ($group['items'][0]['source_id'] ?? 0);
    $targetId = (int) ($group['items'][0]['target_id'] ?? 0);
    $currentCabang = (int) ($this->id_cabang ?? 0);
    $isTargetTransfer = ($type === 2 && $targetId === $currentCabang && $sourceId !== $currentCabang);
  ?>
  <div class="card shadow-sm border-0 mx-auto" style="max-width: 720px;">
    <div class="card-header bg-dark text-white py-2 px-3">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <span class="text-white-50 small">Ref</span>
          <span class="fw-bold ms-1">#<?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?></span>
          <?php if ($type === 2 && $isTargetTransfer) { ?>
            <span class="badge bg-info text-dark ms-1">Terima Transfer</span>
          <?php } ?>
          <?php if ((int) $state === 3) { ?>
            <span class="badge bg-warning text-dark ms-1">Piutang</span>
          <?php } ?>
        </div>
        <span class="text-white-50 small"><?= !empty($group['date']) ? date('d/m/y H:i', strtotime($group['date'])) : '' ?></span>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <tbody>
          <?php foreach ($group['items'] as $item) {
            $margin = $item['margin'] ?? 0;
            $displayPrice = $item['price'] + $margin;
          ?>
          <tr>
            <td class="ps-3 py-2">
              <span class="fw-medium"><?= htmlspecialchars($item['nama_barang'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (($item['denom'] ?? 1) != 1) { ?>
                <span class="text-muted ms-1 small">@<?= htmlspecialchars((string)($item['denom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
              <?php } ?>
              <div class="text-muted small"><?= rtrim(rtrim(number_format($item['qty'], 1, ',', '.'), '0'), ',') ?> x Rp<?= number_format($displayPrice) ?></div>
            </td>
            <td class="text-end pe-3 py-2 align-middle">
              <span class="fw-bold">Rp<?= number_format($displayPrice * $item['qty']) ?></span>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer border-top py-2 px-3">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">TOTAL</span>
        <span class="fw-bold text-dark">Rp<?= number_format($group['total']) ?></span>
      </div>
    </div>
    <?php if (!empty($group['payments'])) { ?>
    <div class="card-footer py-2 px-3 bg-light">
      <small class="text-muted fw-bold"><i class="fas fa-history me-1"></i>Riwayat pembayaran</small>
      <table class="table table-sm table-borderless mb-0 mt-1" style="font-size: 0.9rem;">
        <?php foreach ($group['payments'] as $payment) {
          $st = (int) ($payment['status_mutasi'] ?? 0);
          $statusClass = $st === 3 ? 'text-success' : 'text-warning';
          $statusText = $st === 3 ? 'Lunas' : 'Pending';
        ?>
        <tr>
          <td class="py-1 ps-0"><?= date('d/m H:i', strtotime($payment['insertTime'])) ?></td>
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
          <td colspan="2" class="py-1 ps-0"><span class="fw-bold text-danger">SISA</span></td>
          <td class="py-1 text-end pe-0"><span class="fw-bold text-danger">Rp<?= number_format($group['sisa']) ?></span></td>
        </tr>
      </table>
    </div>
    <?php } ?>
  </div>
  <?php } ?>
</body>
</html>
