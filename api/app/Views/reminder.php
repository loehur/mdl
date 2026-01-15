<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MDL Reminder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --surface: #ffffff;
            --background: #f3f4f6;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

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
            position: relative;
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
            color: var(--primary);
            font-size: 28px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .card-body {
            padding: 0 24px 32px;
        }

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
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
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

        .note-box {
            background: #FFFBEB;
            border: 1px solid #FEF3C7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .note-title {
            font-size: 12px;
            font-weight: 700;
            color: #92400E;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .note-content {
            font-size: 14px;
            color: #78350F;
            line-height: 1.5;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 16px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-disabled {
            background: #E5E7EB;
            color: #9CA3AF;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn.loading .spinner {
            display: block;
        }

        .btn.loading span {
            display: none;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #9CA3AF;
        }

        /* Status colors for header icon */
        .is-warning .icon-wrapper { color: var(--warning); background: #FFFBEB; }
        .is-danger .icon-wrapper { color: var(--danger); background: #FEF2F2; }
        .is-success .icon-wrapper { color: var(--success); background: #ECFDF5; }

    </style>
</head>
<body>

    <?php
    // Determine status class for styling
    $statusTheme = 'is-success';
    $statusBadge = 'success';
    
    if ($dates <= 7) {
        if ($dates > 0) {
            $statusTheme = 'is-warning';
            $statusBadge = 'warning';
        } else {
            $statusTheme = 'is-danger';
            $statusBadge = 'danger';
        }
    } else {
        $statusTheme = 'is-success';
        $statusBadge = 'success';
    }
    ?>

    <div class="card <?= $statusTheme ?>">
        <div class="card-header">
            <div class="icon-wrapper">
                <?php if($statusBadge == 'success'): ?>
                    ✅
                <?php elseif($statusBadge == 'warning'): ?>
                    ⚠️
                <?php else: ?>
                    ⏰
                <?php endif; ?>
            </div>
            <h1><?= htmlspecialchars($name) ?></h1>
            <div class="subtitle">MDL Reminder System</div>
        </div>

        <div class="card-body">
            <div class="info-group">
                <div class="info-row">
                    <span class="label">Target Date</span>
                    <span class="value"><?= date('d M Y', strtotime($next_date)) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="badge <?= $statusBadge ?>"><?= $warning ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Siklus</span>
                    <span class="value"><?= $cycle ?> <?= $cycle_type ?></span>
                </div>
            </div>

            <?php if (!empty($note)) : ?>
            <div class="note-box">
                <div class="note-title">Catatan</div>
                <div class="note-content"><?= nl2br(htmlspecialchars($note)) ?></div>
            </div>
            <?php endif; ?>

            <div class="action-area">
                <?php if ($dates <= $range) : ?>
                <button class="btn btn-primary" onclick="markComplete(<?= $id ?>)" id="btnComplete">
                    <div class="spinner"></div>
                    <span>Tandai Selesai</span>
                </button>
                <?php else : ?>
                <button class="btn btn-disabled" disabled>
                    <span>Sudah Selesai</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function markComplete(id) {
            const btn = document.getElementById('btnComplete');
            
            // Set loading state
            btn.classList.add('loading');
            btn.disabled = true;
            
            // Build absolute URL for API call
            const apiUrl = window.location.origin + '/R/update';

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.text())
            .then(res => {
                if (res == '0' || res.trim() == '0') {
                    location.reload(true);
                } else {
                    alert('Error: ' + res);
                    resetBtn();
                }
            })
            .catch(err => {
                alert('Network error: ' + err);
                resetBtn();
            });

            function resetBtn() {
                btn.classList.remove('loading');
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
