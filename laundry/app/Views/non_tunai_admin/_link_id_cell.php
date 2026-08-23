<?php
$linkId = (int) ($data['linkId'] ?? 0);
$detailJson = (string) ($data['detailJson'] ?? '');
if ($linkId < 1 || $detailJson === '') {
    echo '<span class="text-muted">—</span>';
    return;
}
?>
<button type="button" class="btn btn-link btn-sm p-0 nta-detail-btn nta-link-id"
  data-detail="<?= htmlspecialchars($detailJson, ENT_QUOTES, 'UTF-8') ?>">#<?= $linkId ?></button>
