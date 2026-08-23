<?php
$nominal = $data['nominal'] ?? null;
$billNominal = $data['billNominal'] ?? null;

$fmt = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
};
?>
<div class="nta-nominal-pair">
  <span class="nta-nominal-pair__val"><?= $fmt($nominal) ?></span>
  <span class="nta-nominal-pair__bind" title="Binding"><i class="fas fa-link"></i></span>
  <span class="nta-nominal-pair__val"><?= $fmt($billNominal) ?></span>
</div>
