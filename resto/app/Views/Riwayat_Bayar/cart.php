<div class="accordion mt-1 px-0 mx-0" id="accordionExample">
  <?php
  $total_bayar = 0;
  foreach ($data['order'] as $key => $d) {
    $total_bayar += $data['total'][$key]; ?>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" onclick="load_data('<?= $key ?>')" type="button" data-bs-toggle="collapse" data-bs-target="#a<?= $key ?>">
          <table class="w-100 me-3">
            <tr>
              <td><?= date('d M y', strtotime($key . " 00:00:00")) ?></td>
              <td class="text-end">Rp<?= number_format($data['total'][$key]) ?></td>
            </tr>
          </table>
        </button>
      </h2>
      <div id="a<?= $key ?>" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
        <div class="accordion-body" id="data<?= $key ?>"></div>
      </div>
    </div>
  <?php } ?>
</div>

<div class="row mt-4">
  <div class="col text-center">
    <span class="fw-bold">
      <span class="text-purple fw-bold">#<?= $data['refbayar'] ?> </span><br>
      <span class="fs-5">Total Bayar Rp<?= number_format($total_bayar) ?></span>
    </span>
  </div>
</div>

<?php $today = substr($data['refbayar'], 0, 4) ?>

<?php if ($today == date('md') && $this->book == date('Y')) { ?>
  <div class="row mt-4">
    <div class="col text-center">
      <span class="btn btn-danger" onclick="batalkan('<?= $data['refbayar'] ?>')">
        Batalkan Pembayaran
      </span>
    </div>
  </div>
<?php } ?>

<script>
  function load_data(key) {
    const cek = $("div#data" + key).html();
    if (cek == '') {
      $("div#data" + key).load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
        $("div#data" + key).load('<?= URL::BASE_URL ?>Piutang/cart2/<?= $data['pelanggan'] ?>/' + key);
      });
    }
  }

  function batalkan(ref) {
    if (confirm('Batalkan pembayaran ' + ref + '?')) {
      $.post('<?= URL::BASE_URL ?>Riwayat_Bayar/batalkan', {
        ref: ref
      }, function(data) {
        if (data == 0) {
          location.reload(true);
        } else {
          alert(data);
        }
      });
    }
  }
</script>