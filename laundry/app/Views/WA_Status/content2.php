<div class="text-nowrap">
  <?php $d = $data[1] ?>
  <b>Fonnte</b><br>
  <?php if (isset($d['device'])) { ?>
    <table class="">
      <tr>
        <td class="">Status</td>
        <td class="">: <?= $d['device_status'] ?></td>
      </tr>
      <tr>
        <td class="">Device</td>
        <td class="">: <?= $d['device'] ?></td>
      </tr>
      <tr>
        <td class="">Name</td>
        <td class="">: <?= $d['name'] ?></td>
      </tr>
      <tr>
        <td class="">Expired</td>
        <td class="">: <?= $d['expired'] ?></td>
      </tr>
      <tr>
        <td class="">Quota</td>
        <td class="">: <?= $d['quota'] ?></td>
      </tr>
    </table>
  <?php } else { ?>
    <?php print_r(json_encode($d)) ?>
  <?php } ?>
</div>