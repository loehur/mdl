<div class="text-nowrap text-center">
  <?php if (isset($data['status']) && $data['status']) { ?>
    <i class="far fa-check-circle text-success"></i> Whatsapp Connected
  <?php } else if (isset($data['qr_ready']) && $data['qr_ready']) { ?>
    <img src="<?= $data['qr_string'] ?>" alt="loading" id="qrcode" />
  <?php } else if (isset($data['qr_ready']) && $data['qr_ready'] == false) { ?>
    <i class="fas fa-spinner text-warning"></i> Loading...
  <?php } else { ?>
    <i class="far fa-times-circle text-danger"></i> Server Down
  <?php } ?>
</div>