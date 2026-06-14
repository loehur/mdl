<div class="row mx-0">
  <div class="col" style="max-width:450px">
    <div class="card p-3 mt-2">
      <form method="POST" action="<?= URL::BASE_URL ?>Absen/absen">
        <div class="row">
          <div class="col text-center text-danger">
            <?= date('Y-m-d') ?>
            <h1>
              <span id="jam"><?= date('H') ?></span>:<span id="menit"><?= date('i') ?></span>:<span id="detik"><?= date('s') ?></span>
            </h1>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <p id="info"></p>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col">
            <label>Karyawan</label>
            <input style="visibility: hidden; height:0">
            <select name="karyawan" class="form-control tize form-control-sm" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <optgroup label="MDL <?= $this->dCabang['kode_cabang'] ?>">
                <?php foreach ($this->user as $a) { ?>
                  <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
              <?php if (count($this->userCabang) > 0) { ?>
                <optgroup label="Cabang Lain">
                  <?php foreach ($this->userCabang as $a) { ?>
                    <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col">
            <label>Tugas</label>
            <select name="jenis" class="form-control form-control-sm" required>
              <option value="" selected disabled></option>
              <option value="0">Cuci</option>
              <option value="1">Jaga Malam</option>
              <option value="2">Delivery</option>
              <option value="3">Maintenance</option>
            </select>
          </div>
          <div class="col">
            <label>Tanggal</label>
            <select name="tgl" class="form-control form-control-sm" required>
              <option value="0" selected>Hari ini</option>
              <option value="1">Kemarin</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <button type="submit" class="form-control form-control-sm bg-primary">Absen</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalKonfirmasiAbsen" tabindex="-1" aria-labelledby="modalKonfirmasiAbsenLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold" id="modalKonfirmasiAbsenLabel">Konfirmasi Absen</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Pastikan data berikut sudah benar sebelum absen:</p>
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <td class="text-muted" style="width:38%">Nama Karyawan</td>
            <td class="fw-bold" id="konfirm_nama">-</td>
          </tr>
          <tr>
            <td class="text-muted">Tugas</td>
            <td class="fw-bold" id="konfirm_tugas">-</td>
          </tr>
          <tr>
            <td class="text-muted">Tanggal Absen</td>
            <td>
              <span class="badge fs-6" id="konfirm_tgl_badge">-</span>
              <span class="fw-bold ms-1" id="konfirm_tgl_date">-</span>
            </td>
          </tr>
        </table>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnKonfirmasiAbsen">Ya, Absen</button>
      </div>
    </div>
  </div>
</div>

<div class="row mx-0">
  <div class="col" style="max-width:450px" id="load">
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  var tglAbsenInfo = {
    '0': { label: 'HARI INI', date: '<?= date('Y-m-d') ?>', badge: 'bg-success' },
    '1': { label: 'KEMARIN', date: '<?= date('Y-m-d', strtotime('-1 day')) ?>', badge: 'bg-warning text-dark' }
  };

  var modalKonfirmasiAbsen;

  $(document).ready(function() {
    $(".tize").selectize();
    $("div#load").load("<?= URL::BASE_URL ?>Absen/load");
    modalKonfirmasiAbsen = new bootstrap.Modal(document.getElementById('modalKonfirmasiAbsen'));
  });

  function submitAbsen() {
    var $form = $("form");
    $(".loaderDiv").fadeIn("fast");
    $.ajax({
      url: $form.attr('action'),
      data: $form.serialize(),
      type: $form.attr("method"),
      success: function(res) {
        try {
          data = JSON.parse(res);
          if (data.code == 0) {
            $("#info").hide();
            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
            $("#info").fadeIn();
            $(".loaderDiv").fadeOut("slow");
          } else if ((data.code == 1)) {
            $("#info").hide();
            $("#info").html('<div class="alert alert-success" role="alert">' + data.msg + '</div>')
            $("#info").fadeIn();
            $(".loaderDiv").fadeOut("slow");
            $("div#load").load("<?= URL::BASE_URL ?>Absen/load");
          }
        } catch (e) {
          $("#info").hide();
          $("#info").html('<div class="alert alert-danger" role="alert">' + res + '</div>')
          $("#info").fadeIn();
          $(".loaderDiv").fadeOut("slow");
        }
      },
    });
  }

  $("form").on("submit", function(e) {
    e.preventDefault();

    var karyawan = $('select[name=karyawan]').val();
    var jenis = $('select[name=jenis]').val();
    var tgl = $('select[name=tgl]').val();

    if (!karyawan || !jenis || tgl === null || tgl === '') {
      $("#info").hide();
      $("#info").html('<div class="alert alert-danger" role="alert">Lengkapi Karyawan, Tugas, dan Tanggal terlebih dahulu</div>');
      $("#info").fadeIn();
      return;
    }

    var namaKaryawan = $('select[name=karyawan] option[value="' + karyawan + '"]').text().trim();
    var namaTugas = $('select[name=jenis] option[value="' + jenis + '"]').text().trim();
    var infoTgl = tglAbsenInfo[tgl];

    $("#konfirm_nama").text(namaKaryawan);
    $("#konfirm_tugas").text(namaTugas);
    $("#konfirm_tgl_badge").text(infoTgl.label).removeClass('bg-success bg-warning text-dark').addClass(infoTgl.badge);
    $("#konfirm_tgl_date").text('(' + infoTgl.date + ')');

    modalKonfirmasiAbsen.show();
  });

  $("#btnKonfirmasiAbsen").on("click", function() {
    modalKonfirmasiAbsen.hide();
    submitAbsen();
  });

  window.setTimeout("waktu()", 1000);

  function waktu() {
    setTimeout("waktu()", 1000);
    $("#jam").load('<?= URL::BASE_URL . 'Time/get/H' ?>');
    $("#menit").load('<?= URL::BASE_URL . 'Time/get/i' ?>');
    $("#detik").load('<?= URL::BASE_URL . 'Time/get/s' ?>');
  }
</script>