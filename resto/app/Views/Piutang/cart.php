<div class="accordion mt-1 px-0 mx-0" id="accordionExample">

  <?php foreach ($data['order'] as $key => $d) { ?>
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

<script>
  function load_data(key) {
    const cek = $("div#data" + key).html();
    if (cek == '') {
      $("div#data" + key).load('<?= URL::BASE_URL ?>Load/spinner/2', function() {
        $("div#data" + key).load('<?= URL::BASE_URL ?>Piutang/cart2/<?= $data['pelanggan'] ?>/' + key);
      });
    }
  }
</script>