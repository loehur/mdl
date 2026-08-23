<?php
$p = $data['p'] ?? null;
if (!is_array($p) || empty($p['id_pelanggan'])) {
    echo '<span class="text-muted">—</span>';
    return;
}

$id = (int) $p['id_pelanggan'];
$nama = trim((string) ($p['nama_pelanggan'] ?? ''));
if ($nama === '') {
    $nama = (string) $id;
}
$namaUpper = mb_strtoupper($nama, 'UTF-8');
$url = 'https://ml.nalju.com/J/tagihan/' . $id;
?>
<a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer" class="nta-plg-link"><?= htmlspecialchars($namaUpper) ?></a>
