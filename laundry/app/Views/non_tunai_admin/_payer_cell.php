<?php
$payer = $data['payer'] ?? null;
if (!is_array($payer) || trim((string) ($payer['name'] ?? '')) === '') {
    echo '<span class="text-muted">—</span>';
    return;
}

$name = trim((string) $payer['name']);
$nameUpper = mb_strtoupper($name, 'UTF-8');
$url = trim((string) ($payer['url'] ?? ''));
$badge = trim((string) ($payer['badge'] ?? ''));

if ($url !== '') {
    echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="nta-plg-link">'
        . htmlspecialchars($nameUpper) . '</a>';
} else {
    echo '<span class="nta-plg-link nta-plg-link--plain">' . htmlspecialchars($nameUpper) . '</span>';
}

if ($badge !== '') {
    echo ' <small class="text-muted">(' . htmlspecialchars($badge) . ')</small>';
}
