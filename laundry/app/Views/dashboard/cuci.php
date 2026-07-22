<?php
$rows = $data['rows'] ?? [];
$layananOk = !empty($data['layanan_ok']);
$n = count($rows);

$avgToday = 0.0;
$avgYesterday = 0.0;
if ($n > 0) {
  foreach ($rows as $r) {
    $avgToday += (float) ($r['today_qty'] ?? 0);
    $avgYesterday += (float) ($r['yesterday_qty'] ?? 0);
  }
  $avgToday /= $n;
  $avgYesterday /= $n;
}
?>

<?php if (!$layananOk) { ?>
  <div class="dash-alert">Layanan <b>Cuci</b> tidak ditemukan di master layanan.</div>
<?php } ?>

<div class="dash-board dash-board--cuci">
  <div class="dash-board__head">
    <h2 class="dash-board__title">Antri Cuci — Ringkasan per Cabang</h2>
    <span class="dash-board__badge">Cuci</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="dash-grid">
      <thead>
        <tr>
          <th>Cabang</th>
          <th>Hari Ini</th>
          <th>Kemarin</th>
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
            $yesterday = (float) ($row['yesterday_qty'] ?? 0);
            $diffToday = round($avgToday - $today, 2);
            $diffYesterday = round($avgYesterday - $yesterday, 2);

            $fmtToday = $this->fmtDecMax2($diffToday);
            $fmtYesterday = $this->fmtDecMax2($diffYesterday);
            $txtToday = ($fmtToday === '0') ? '(0)' : ('(' . ($diffToday > 0 ? '+' : '') . $fmtToday . ')');
            $txtYesterday = ($fmtYesterday === '0') ? '(0)' : ('(' . ($diffYesterday > 0 ? '+' : '') . $fmtYesterday . ')');
            $clsToday = ($fmtToday === '0') ? 'dash-diff--flat' : (($diffToday > 0) ? 'dash-diff--below' : 'dash-diff--above');
            $clsYesterday = ($fmtYesterday === '0') ? 'dash-diff--flat' : (($diffYesterday > 0) ? 'dash-diff--below' : 'dash-diff--above');
            ?>
            <tr>
              <td><span class="dash-cabang"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="dash-metric">
                <span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($today), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="dash-diff <?= $clsToday ?>"><?= htmlspecialchars($txtToday, ENT_QUOTES, 'UTF-8') ?></span>
              </td>
              <td class="dash-metric">
                <span class="dash-qty"><?= htmlspecialchars($this->fmtDecMax2($yesterday), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="dash-diff <?= $clsYesterday ?>"><?= htmlspecialchars($txtYesterday, ENT_QUOTES, 'UTF-8') ?></span>
              </td>
            </tr>
          <?php }
        } ?>
      </tbody>
    </table>
  </div>
</div>
