<?php
$nominal = $data['nominal'] ?? null;
$billNominal = $data['billNominal'] ?? null;

$fmt = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
};

$left = $fmt($nominal);
$right = $fmt($billNominal);
?>
<div class="nta-nominal-pair">
  <span class="nta-nominal-pair__val"><?= $left ?></span>
  <span class="nta-nominal-pair__bind" title="Binding"><i class="fas fa-link"></i></span>
  <span class="nta-nominal-pair__val"><?= $right ?></span>
</div>
