<?php
$dbCr = strtoupper(trim((string) ($data['dbCr'] ?? '')));
if ($dbCr === '') {
    echo '<span class="text-muted">—</span>';
    return;
}

$badgeClass = $dbCr === 'CR' ? 'nta-badge--cr' : 'nta-badge--db';
?>
<span class="nta-badge <?= $badgeClass ?>"><?= htmlspecialchars($dbCr) ?></span>
