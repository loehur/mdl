<?php
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$maxRangeDays = (int) ($data['maxRangeDays'] ?? 7);
$filterAction = $data['filterAction'] ?? '';
$filterTitle = $data['filterTitle'] ?? 'Non Tunai';
$filterIcon = $data['filterIcon'] ?? 'fa-credit-card';
$rowCount = (int) ($data['rowCount'] ?? 0);
$unboundCount = (int) ($data['unboundCount'] ?? 0);
$unboundTotalNominal = (float) ($data['unboundTotalNominal'] ?? 0);
$fmtRp = static function ($value): string {
    return number_format((float) $value, 0, ',', '.');
};
?>
<div class="content" id="nta-root">
  <style>
    #nta-root {
      --nta-ink: #0f172a;
      --nta-muted: #475569;
      --nta-line: #cbd5e1;
      --nta-blue: #2563eb;
      font-family: 'fontku', 'Segoe UI', sans-serif;
      max-width: 1200px;
    }
    #nta-root .nta-shell {
      border: 1px solid var(--nta-line);
      background: linear-gradient(180deg, #eef4ff 0%, #fff 100%);
      padding: 14px;
    }
    #nta-root .nta-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 12px;
    }
    #nta-root .nta-title {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 900;
      color: var(--nta-ink);
    }
    #nta-root .nta-filter {
      background: #fff;
      border: 1px solid var(--nta-line);
      padding: 12px;
      margin-bottom: 12px;
    }
    #nta-root .nta-table-wrap {
      background: #fff;
      border: 1px solid var(--nta-line);
      overflow-x: auto;
      margin-top: 14px;
    }
    #nta-root table.nta-table {
      width: 100%;
      margin: 0;
      font-size: 0.84rem;
    }
    #nta-root table.nta-table th {
      background: #f1f5f9;
      font-weight: 800;
      white-space: nowrap;
    }
    #nta-root table.nta-table td,
    #nta-root table.nta-table th {
      border-color: var(--nta-line);
      vertical-align: middle;
    }
    #nta-root .nta-empty {
      text-align: center;
      padding: 36px 16px;
      color: var(--nta-muted);
      font-weight: 600;
    }
    #nta-root .nta-badge {
      display: inline-block;
      border: 1px solid var(--nta-line);
      padding: 2px 8px;
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
    }
    #nta-root .nta-badge--type {
      background: #eff6ff;
      color: #1d4ed8;
    }
    #nta-root .nta-ket {
      max-width: 280px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    #nta-root .nta-count {
      font-size: 0.82rem;
      color: var(--nta-muted);
      font-weight: 700;
    }
    #nta-root .nta-count__unbound {
      color: #b45309;
    }
    #nta-root .nta-section {
      margin-top: 16px;
    }
    #nta-root .nta-section-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: 0 0 8px;
      font-size: 0.92rem;
      font-weight: 900;
      color: var(--nta-ink);
    }
    #nta-root .nta-badge--unbound {
      background: #fff7ed;
      color: #b45309;
      border-color: #fdba74;
    }
    #nta-root .nta-badge--cr {
      background: #ecfdf5;
      color: #047857;
      border-color: #6ee7b7;
    }
    #nta-root .nta-badge--db {
      background: #fef2f2;
      color: #b91c1c;
      border-color: #fca5a5;
    }
    #nta-root .nta-nominal-single {
      font-weight: 800;
      white-space: nowrap;
    }
    #nta-root .nta-plg-link {
      font-weight: 800;
      color: var(--nta-blue);
      text-decoration: none;
    }
    #nta-root .nta-plg-link:hover {
      text-decoration: underline;
    }
    #nta-root .nta-detail-btn {
      font-size: 0.78rem;
      font-weight: 800;
      padding: 2px 10px;
    }
    #nta-root .nta-link-id {
      font-weight: 800;
      color: var(--nta-blue);
      text-decoration: none;
      font-size: 0.84rem;
      border: 0;
    }
    #nta-root .nta-link-id:hover {
      text-decoration: underline;
      color: #1d4ed8;
    }
  </style>
  <style>
    #ntaDetailModal .nta-detail-list .nta-detail-row {
      display: grid;
      grid-template-columns: 130px 1fr;
      gap: 8px 12px;
      padding: 10px 14px;
      border-bottom: 1px solid #cbd5e1;
    }
    #ntaDetailModal .nta-detail-list .nta-detail-row:last-child {
      border-bottom: 0;
    }
    #ntaDetailModal .nta-detail-list dt {
      margin: 0;
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      color: #475569;
    }
    #ntaDetailModal .nta-detail-list dd {
      margin: 0;
      font-size: 0.88rem;
      font-weight: 600;
      color: #0f172a;
      word-break: break-word;
    }
    #ntaDetailModal .nta-plg-link {
      font-weight: 800;
      color: #2563eb;
      text-decoration: none;
    }
    #ntaDetailModal .nta-plg-link:hover {
      text-decoration: underline;
    }
  </style>

  <div class="nta-shell">
    <div class="nta-head">
      <h5 class="nta-title"><i class="fas <?= htmlspecialchars($filterIcon) ?> me-2"></i><?= htmlspecialchars($filterTitle) ?></h5>
      <span class="nta-count">
        <?= $rowCount ?> binding
        <?php if ($unboundCount > 0) { ?>
          · <span class="nta-count__unbound"><?= $unboundCount ?> belum bind (<?= $fmtRp($unboundTotalNominal) ?>)</span>
        <?php } ?>
      </span>
    </div>

    <div class="nta-filter">
      <form method="GET" action="<?= htmlspecialchars($filterAction) ?>" class="row g-2 align-items-end" id="ntaFilterForm">
        <div class="col-md-3">
          <label class="form-label small mb-1">Dari Tanggal</label>
          <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Sampai Tanggal</label>
          <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>" required>
        </div>
        <div class="col-md-6">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
          <a href="<?= htmlspecialchars($filterAction) ?>" class="btn btn-secondary btn-sm ms-1"><i class="fas fa-redo me-1"></i>Reset</a>
          <small class="text-muted ms-2"><i class="fas fa-info-circle"></i> Maks. <?= $maxRangeDays ?> hari</small>
        </div>
      </form>
    </div>
