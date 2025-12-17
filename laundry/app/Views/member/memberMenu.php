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
      <div class="col-auto pe-0">
        <a class="hrfop" href="<?= URL::BASE_URL ?>Operasi/i/0/<?= $id_pelanggan ?>/0"><span class="btn btn-sm btn-outline-secondary form-control form-control-sm">OP</span></a>
      </div>
      <div class="col-auto pe-0">
        <button id="cekR" class="btn btn-sm btn-secondary form-control form-control-sm">
          SP
        </button>
      </div>
      <div class="col-auto">
        <a class="hrfsd" href="<?= URL::BASE_URL ?>SaldoTunai/tambah/<?= $id_pelanggan ?>"><span class="btn btn-sm btn-outline-secondary form-control form-control-sm">SD</span></a>
      </div>
    </div>
  </div>
</div>
<div class="row pl-2" id="saldoRekap"></div>
<div class="row pl-2" id="riwayat"></div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    $('select.pelanggan').selectize();

    var pelanggan = <?= (int)$id_pelanggan ?>;
    if (pelanggan > 0) {
      $('div#saldoRekap').load('<?= URL::BASE_URL ?>Member/rekapTunggal/' + pelanggan);
      $('div#riwayat').load('<?= URL::BASE_URL ?>Member/tampilkan/' + pelanggan);
    }
  });

  $("button#cekR").click(function() {
    var pelanggan = $("select[name=p]").val();
    if (pelanggan == null || pelanggan == 0) {
      return;
    }
    $('.hrfop').attr('href', '<?= URL::BASE_URL ?>Operasi/i/0/' + pelanggan + '/0')
    $('.hrfsd').attr('href', '<?= URL::BASE_URL ?>SaldoTunai/tambah/' + pelanggan)

    $('div#saldoRekap').load('<?= URL::BASE_URL ?>Member/rekapTunggal/' + pelanggan);
    $('div#riwayat').load('<?= URL::BASE_URL ?>Member/tampilkan/' + pelanggan);
  })

  $("select[name=p]").change(function() {
    $("button#cekR").click();
  });
</script>