<?php
$rows = $data['rows'] ?? [];
$layananOk = !empty($data['layanan_ok']);
$n = count($rows);

$avgToday = 0.0;
$avgBesok = 0.0;
if ($n > 0) {
  foreach ($rows as $r) {
    $avgToday += (float) ($r['today_qty'] ?? 0);
    $avgBesok += (float) ($r['besok_qty'] ?? 0);
  }
  $avgToday /= $n;
  $avgBesok /= $n;
}
?>

<?php if (!$layananOk) { ?>
  <div class="dash-alert">Layanan <b>Setrika</b> tidak ditemukan di master layanan.</div>
<?php } ?>

<div class="dash-board dash-board--setrika">
  <div class="dash-board__head">
    <h2 class="dash-board__title">Antri Setrika/Pack — Ringkasan per Cabang</h2>
    <span class="dash-board__badge">Setrika</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="dash-grid">
      <thead>
        <tr>
          <th>Cabang</th>
          <th>Hari Ini</th>
          <th>Besok</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($n === 0) { ?>
          <tr>
            <td colspan="3" class="dash-empty-row">Tidak ada cabang operasional.</td>
          </tr>
        <?php } else {
          foreach ($rows as $row) {
            $today = (float) ($row['today_qty'] ?? 0);
            $besok = (float) ($row['besok_qty'] ?? 0);
            $diffToday = round($avgToday - $today, 2);
            $diffBesok = round($avgBesok - $besok, 2);

            $fmtToday = $this->fmtDecMax2($diffToday);
            $fmtBesok = $this->fmtDecMax2($diffBesok);
            $txtToday = ($fmtToday === '0') ? '(0)' : ('(' . ($diffToday > 0 ? '+' : '') . $fmtToday . ')');
            $txtBesok = ($fmtBesok === '0') ? '(0)' : ('(' . ($diffBesok > 0 ? '+' : '') . $fmtBesok . ')');
            $clsToday = ($fmtToday === '0') ? 'dash-diff--flat' : (($diffToday > 0) ? 'dash-diff--below' : 'dash-diff--above');
            $clsBesok = ($fmtBesok === '0') ? 'dash-diff--flat' : (($diffBesok > 0) ? 'dash-diff--below' : 'dash-diff--above');
            ?>
            <tr>
              <td><span class="dash-cabang"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="dash-metric">
                <span class="dash-diff <?= $clsToday ?>"><?= htmlspecialchars($txtToday, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($today), ENT_QUOTES, 'UTF-8') ?></span>
              </td>
              <td class="dash-metric">
                <span class="dash-diff <?= $clsBesok ?>"><?= htmlspecialchars($txtBesok, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($besok), ENT_QUOTES, 'UTF-8') ?></span>
              </td>
            </tr>
          <?php }
        } ?>
      </tbody>
    </table>
  </div>
</div>
