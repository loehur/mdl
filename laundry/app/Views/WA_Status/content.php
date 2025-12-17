<div class="text-nowrap">
  <b>Server</b><br>
  <?php if (isset($data[0]['status']) && $data[0]['status']) { ?>
    <i class="far fa-check-circle text-success"></i> Whatsapp Connected
  <?php } else if (isset($data[0]['qr_ready']) && $data[0]['qr_ready']) { ?>
    <img src="<?= $data[0]['qr_string'] ?>" alt="loading" id="qrcode" />
  <?php } else if (isset($data[0]['qr_ready']) && $data[0]['qr_ready'] == false) { ?>
    <i class="fas fa-spinner text-warning"></i> Loading...
  <?php } else { ?>
    <i class="far fa-times-circle text-danger"></i> Server Down
  <?php } ?>
</div>