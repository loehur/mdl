<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="card p-3 mt-2">
          <form method="POST" action="<?= URL::BASE_URL ?>Pindah_Outlet/pindah">
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
                  <?php if (count($this->userCabang) > 0) { ?>
                    <?php foreach ($this->userCabang as $a) { ?>
                      <option value="<?= $a['no_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                    <?php } ?>
                  <?php } ?>
                </select>
              </div>
              <div class="col-auto mt-auto pb-2">
                <span id="req_pin" class="btn btn-sm btn-outline-info">Minta PIN</span>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col">
                <label>PIN</label>
                <input name="pin" type="password" class="form-control form-control-sm" required />
              </div>
              <div class="col">
                <label>&nbsp;</label>
                <button type="submit" class="form-control form-control-sm bg-primary">Pindah ke <b><?= $_SESSION[URL::SESSID]['data']['cabang']['kode_cabang'] ?></b></button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="content pl-2 border-0">
  <div class="container-fluid">
    <div class="row">
      <div class="col" id="load">

      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  $(document).ready(function() {
    $(".tize").selectize();
    load();
  });

  function load() {
    $("div#load").load("<?= URL::BASE_URL ?>Pindah_Outlet/load");
  }

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
            load();
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