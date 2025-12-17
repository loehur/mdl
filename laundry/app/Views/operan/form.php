<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="content sticky-top m-3">
            <div class="d-flex align-items-start align-items-end">
              <?php
              $idOperan = $data['idOperan'];
              $idCabang = $data['idCabang'];
              ?>
              <div class="p-1">
                <b>ID Outlet</b><br>REF<b>XX</b># 1-2 Digit
                <input name="idCabang" style="text-transform:uppercase" class="form-control form-control-sm" value="<?= $idCabang ?>" style="width: auto;" required />
              </div>
              <div class="p-1">
                <b>ID Item</b><br>IDXXX-<b>XXX</b> 3 Digit Terkahir
                <input name="idOperan" class="form-control form-control-sm" value="<?= $idOperan ?>" style="width: auto;" required />
              </div>
              <div class="p-1">
                <button onclick="loadDiv()" class="form-control form-control-sm bg-primary">Cek</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="content pl-2 border-0">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto" id="load">
      </div>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  $(document).ready(function() {
    $("input[name=idCabang]").focus();
  });

  function loadDiv() {
    var idOperan = $("input[name=idOperan]").val();
    var idCabang = $("input[name=idCabang]").val();

    if (idOperan != '' && idCabang != '') {
      $("div#load").load("<?= URL::BASE_URL ?>Operan/load/" + idOperan + "/" + idCabang);
    } else {
      $("div#load").html("Pengecekan ditolak, mohon lengkapi ID Outlet/Item");
    }
  }

  $('input[name=idOperan]').keypress(function(event) {
    if (event.keyCode == 13) {
      loadDiv();
    }
  });
</script>