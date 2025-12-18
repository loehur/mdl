<div class="content">
  <div class="container-fluid">
    <?php
    foreach ($data as $a) {
      $time = $a['insertTime'];
      $time_e = base64_encode($time);
      $text = $a['text'];
    ?>
      <div class="row mb-1 bg-white">
        <div class="col border pr-1 pl-1 rounded">
          <div class="row border-bottom">
            <div class="col">Time: <?= $time . ", Target: <b>" . $a['c'] . "</b><br>Message: <b>" . $text ?></b></div>
          </div>
          <?php
          $res = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('notif', "insertTime = '" . $time . "'");
          $rec = [];
          $st = [];
          foreach ($res as $r) {

            $st[$r['phone']] = [$r['proses'], $r['state']];

            if (isset($rec['app'][$r['proses']])) {
              $rec['app'][$r['proses']] += 1;
            } else {
              $rec['app'][$r['proses']] = 1;
            }
            if (isset($rec['client'][$r['state']])) {
              $rec['client'][$r['state']] += 1;
            } else {
              $rec['client'][$r['state']] = 1;
            }
          }

          foreach ($rec['app'] as $k => $v) { ?>
            <div class="row">
              <div class="col">System -<?= $k ?></div>
              <div class="col text-right"><?= $v ?></div>
              <div class="col pl-1 pr-1 pb-0 text-center" style="cursor: pointer;">
                <span class="cekProses" data-time="<?= $time_e ?>" data-st="<?= $k ?>" data-toggle="modal" data-target="#exampleModal"><small>CEK</small></span>
              </div>
            </div>
            <hr class="p-0 m-0">
          <?php }
          foreach ($rec['client'] as $k => $v) { ?>
            <div class="row">
              <div class="col">Client -<?= $k ?></div>
              <div class="col text-right"><?= $v ?></div>
              <div class="col pl-1 pr-1 pb-0 text-center" style="cursor: pointer;">
                <span class="cekState" data-time="<?= $time ?>" data-st="<?= $k ?>" data-toggle="modal" data-target="#exampleModal"><small>CEK</small></span>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    <?php }
    ?>
  </div>
</div>

<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Targets/Status</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="loadRes" style="height: 400px; overflow-y:scroll"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>

<script>
  $("span.cekProses").click(function() {
    var time = $(this).attr("data-time");
    var st = $(this).attr("data-st");
    $("div#loadRes").load("<?= URL::BASE_URL ?>Broadcast/load/1/" + time + "/" + st);
  })
  $("span.cekState").click(function() {
    var time = $(this).attr("data-time");
    var st = $(this).attr("data-st");
    $("div#loadRes").load("<?= URL::BASE_URL ?>Broadcast/load/2/" + time + "/" + st);
  })
</script>