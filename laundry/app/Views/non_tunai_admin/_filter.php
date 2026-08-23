<?php
$startDate = $data['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
$endDate = $data['endDate'] ?? date('Y-m-d');
$maxRangeDays = (int) ($data['maxRangeDays'] ?? 7);
$filterAction = $data['filterAction'] ?? '';
$filterTitle = $data['filterTitle'] ?? 'Non Tunai';
$filterIcon = $data['filterIcon'] ?? 'fa-credit-card';
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
  </style>

  <div class="nta-shell">
    <div class="nta-head">
      <h5 class="nta-title"><i class="fas <?= htmlspecialchars($filterIcon) ?> me-2"></i><?= htmlspecialchars($filterTitle) ?></h5>
      <span class="nta-count"><?= (int) ($data['rowCount'] ?? 0) ?> binding</span>
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
