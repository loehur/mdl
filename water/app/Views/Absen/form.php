<div class="row mx-0">
  <div class="col">
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
            <select name="karyawan" class="form-control form-control-sm" style="width: 100%;" required>
              <option value="" selected disabled></option>
              <?php foreach ($_SESSION[URL::SESSID]['users'] as $a) { ?>
                <option value="<?= $a['no_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col">
            <label>Tugas</label>
            <select name="jenis" class="form-control form-control-sm" required>
              <option value="" selected disabled></option>
              <?php foreach (URL::TUGAS as $key => $t) { ?>
                <option value="<?= $key ?>"><?= $t ?></option>
              <?php } ?>
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

<div class="row mx-0">
  <div class="col" id="load"></div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::ASSETS_URL ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::ASSETS_URL ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
  $(document).ready(function() {
    $("div#load").load("<?= URL::BASE_URL ?>Absen/load");
  });

  $("form").on("submit", function(e) {
    e.preventDefault();
    $(".loaderDiv").fadeIn("fast");
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
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
  });

  $("#req_pin").on("click", function(e) {
    e.preventDefault();

    var hp_input = $('select[name=karyawan]').val();
    if (hp_input == '') {
      $("#info").hide();
      $("#info").html('<div class="alert alert-danger" role="alert">Pilih Karyawan sebelum request PIN</div>')
      $("#info").fadeIn();
      return;
    }

    $(".loaderDiv").fadeIn("fast");
    $.ajax({
      url: '<?= URL::BASE_URL ?>Login/req_pin',
      data: {
        hp: hp_input
      },
      type: 'POST',

      success: function(res) {
        try {
          data = JSON.parse(res);
          if (data.code == 0) {
            $("#info").hide();
            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
            $("#info").fadeIn();
            $(".loaderDiv").fadeOut("slow");
          } else if (data.code == 1) {
            $("#info").hide();
            $("#info").html('<div class="alert alert-success" role="alert">' + data.msg + '</div>')
            $("#info").fadeIn();
            $(".loaderDiv").fadeOut("slow");
          }
        } catch (e) {
          $("#info").hide();
          $("#info").html('<div class="alert alert-danger" role="alert">' + res + '</div>')
          $("#info").fadeIn();
          $(".loaderDiv").fadeOut("slow");
        }
      },
    });
  });
</script>