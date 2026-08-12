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

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>
<script>
  window.print_ms = <?= isset($this->mdl_setting['print_ms']) ? (float) $this->mdl_setting['print_ms'] : 0 ?>;
  window.ViewLoadConfig = {
    baseUrl: '<?= URL::BASE_URL ?>',
    modeView: '0',
    idPelanggan: '<?= (int) $id_pelanggan ?>',
    kodeCabang: '<?= htmlspecialchars($this->dCabang['kode_cabang'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
    nonTunaiGuide: <?= json_encode(URL::NON_TUNAI_GUIDE) ?>,
    loadRekap: {},
    arrTuntas: [],
    arrTuntasSerial: <?= json_encode(serialize([])) ?>,
    marginTop: <?= $this->mdl_setting['margin_printer_top'] ?? 0 ?>,
    feedLines: <?= $this->mdl_setting['margin_printer_bottom'] ?? 0 ?>,
    saldoTunaiView: true
  };
</script>
<script src="<?= URL::IN_ASSETS ?>js/print_server.js?v=<?= time() ?>"></script>
<script src="<?= URL::IN_ASSETS ?>js/operasi/view_load.js?v=<?= time() ?>"></script>

<script>
  function loadSaldoTunaiData(pelanggan) {
    if (pelanggan == null || pelanggan == 0 || pelanggan === '') {
      return;
    }
    $('div#saldoRekap').load('<?= URL::BASE_URL ?>SaldoTunai/tampil_rekap/0/' + pelanggan);
    $('div#riwayat').load('<?= URL::BASE_URL ?>SaldoTunai/tampilkan/' + pelanggan);
  }

  $(document).ready(function() {
    $('select.pelanggan').select2({
      theme: "classic"
    });

    var pelanggan = <?= (int) $id_pelanggan ?>;
    if (pelanggan > 0) {
      loadSaldoTunaiData(pelanggan);
    }
  });

  $("select[name=p]").change(function() {
    loadSaldoTunaiData($(this).val());
  });
</script>
