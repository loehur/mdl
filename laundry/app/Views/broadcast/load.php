<?php
foreach ($data as $a) {
  $hp = $a['phone'];
  $st_p = $a['proses'];
  $st_s = $a['state'];
?>
  <div class="row">
    <div class="col"><?= $hp ?></div>
    <div class="col"><?= $st_p ?></div>
    <div class="col"><?= $st_s ?></div>
  </div>
<?php }
?>