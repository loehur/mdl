<?php $id_pelanggan = $data['pelanggan'] ?>

<div class="row pl-2 mt-2 mb-1">
  <div>
    <div class="row mx-0">
      <div class="col-auto pe-0 ps-0">
        <select name="p" class="pelanggan" required style="width: 200px;">
          <option value="" selected disabled>...</option>
          <?php foreach ($this->pelanggan as $a) { ?>
            <option id="<?= $a['id_pelanggan'] ?>" value="<?= $a['id_pelanggan'] ?>" <?= ($id_pelanggan == $a['id_pelanggan']) ? 'selected' : '' ?>><?= strtoupper($a['nama_pelanggan']) . " | " . $a['nomor_pelanggan']  ?></option>
          <?php } ?>
        </select>
      </div>
    </div>
  </div>
</div>
<div class="row pl-2" id="saldoRekap"></div>
<div class="row pl-2" id="riwayat"></div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  function loadMemberData(pelanggan) {
    if (pelanggan == null || pelanggan == 0 || pelanggan === '') {
      return;
    }
    $('div#saldoRekap').load('<?= URL::BASE_URL ?>Member/rekapTunggal/' + pelanggan);
    $('div#riwayat').load('<?= URL::BASE_URL ?>Member/tampilkan/' + pelanggan);
  }

  $(document).ready(function() {
    $('select.pelanggan').selectize();

    var pelanggan = <?= (int)$id_pelanggan ?>;
    if (pelanggan > 0) {
      loadMemberData(pelanggan);
    }
  });

  $("select[name=p]").change(function() {
    loadMemberData($(this).val());
  });
</script>
