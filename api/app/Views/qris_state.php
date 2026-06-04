<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QRIS State — <?= htmlspecialchars($log['ref_finance'] ?? '') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --surface: #ffffff;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: var(--surface);
            width: 100%;
            max-width: 420px;
            border-radius: 24px;
            box-shadow: 0 20px 40px -8px rgba(79, 70, 229, 0.1),
                        0 12px 20px -10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 32px 24px 24px;
            text-align: center;
        }

        .icon-wrapper {
            width: 64px;
            height: 64px;
            background: #EEF2FF;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
            word-break: break-all;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .card-body { padding: 0 24px 32px; }

        .info-group {
            background: #F9FAFB;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            gap: 12px;
        }

        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row:first-child { padding-top: 0; }

        .label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            flex-shrink: 0;
        }

        .value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
            word-break: break-word;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .badge.success { background: #DCFCE7; color: #166534; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.danger { background: #FEE2E2; color: #991B1B; }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #9CA3AF;
        }

        .is-warning .icon-wrapper { color: var(--warning); background: #FFFBEB; }
        .is-danger .icon-wrapper { color: var(--danger); background: #FEF2F2; }
        .is-success .icon-wrapper { color: var(--success); background: #ECFDF5; }
    </style>
</head>
<body>

    <div class="card <?= htmlspecialchars($status_theme) ?>">
        <div class="card-header">
            <div class="icon-wrapper"><?= $status_icon ?></div>
            <h1>#<?= htmlspecialchars($log['ref_finance'] ?? '') ?></h1>
            <div class="subtitle">MDL QRIS State</div>
        </div>

        <div class="card-body">
            <div class="info-group">
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="badge <?= htmlspecialchars($status_badge) ?>"><?= htmlspecialchars($status_label) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Tanggal Kas</span>
                    <span class="value"><?= !empty($log['date']) ? date('d M Y H:i', strtotime($log['date'])) : '-' ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Total Bayar</span>
                    <span class="value">Rp<?= number_format((int) ($log['jumlah'] ?? 0), 0, ',', '.') ?></span>
                </div>
                <?php if (!empty($nama_pelanggan)) : ?>
                <div class="info-row">
                    <span class="label">Pelanggan</span>
                    <span class="value"><?= htmlspecialchars($nama_pelanggan) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($raw_decoded['trx_id'])) : ?>
                <div class="info-row">
                    <span class="label">Trx ID</span>
                    <span class="value"><?= htmlspecialchars($raw_decoded['trx_id']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Tanggal Update</span>
                    <span class="value"><?= !empty($tanggal_update) ? htmlspecialchars($tanggal_update) : '-' ?></span>
                </div>
            </div>

            <div class="footer">QRIS_State · ref_finance</div>
        </div>
    </div>

</body>
</html>
