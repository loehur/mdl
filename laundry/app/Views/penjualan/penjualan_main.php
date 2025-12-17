<div class="row mt-3 mx-0">
  <div class="col" style="max-width: 500px;">
    <form class="orderProses" action="<?= URL::BASE_URL ?>Penjualan/proses" method="POST">
      <div class="row mx-0">
        <div class="col">
          <label>Pelanggan</label> <span class="float-right addPelanggan" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#addPelanggan"><i class="fas fa-plus-square"></i> Tambah</span>
          <select id="pelanggan_submit" name="f1" class="proses form-control tize" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <?php foreach ($this->pelanggan as $a) { ?>
              <option id=" <?= $a['id_pelanggan'] ?>" value="<?= $a['id_pelanggan'] ?>"><?= strtoupper($a['nama_pelanggan']) . ", " . $a['nomor_pelanggan']  ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col">
          <label>Karyawan</label><br>
          <select name="f2" class="form-control tize karyawan" style="width: 100%;" required>
            <option value="" selected disabled></option>
            <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
              <?php foreach ($this->user as $a) { ?>
                <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
            <?php if (count($this->userCabang) > 0) { ?>
              <optgroup label="----- Cabang Lain -----">
                <?php foreach ($this->userCabang as $a) { ?>
                  <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
        </div>
        <div class="row mt-1 mx-0 px-2 mb-2">
          <div class="col px-0">
            <button id="proses" type="submit" class="btn btn-success w-100" disabled>
              Proses Order
            </button>
          </div>
        </div>
      </form>
    </div>
    <div class="row mx-0">
      <div class="col" style="max-width: 500px;">
        <div class="p-1">
          <label class="m-0">Layanan Paling Sering</label><br>
          <div id="sering"></div>
        </div>
      </div>
    </div>
    <div class="row mx-0">
      <div id="waitReady" class="col invisible" style="max-width: 500px;">
        <div class="card p-1 mb-1">
          <form id="main">
            <div class="d-flex align-items-start align-items-end">
              <div class="p-1">
                <button type="button" data-id_penjualan='1' class="btn btn-outline-success orderPenjualanForm" data-bs-target="#modalPenjualan">
                  Kiloan
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='2' class="btn btn-outline-info orderPenjualanForm" data-bs-target="#modalPenjualan">
                  Satuan
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='3' class="btn btn-outline-dark orderPenjualanForm" data-bs-target="#modalPenjualan">
                  Bidang
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='4' class="btn btn-outline-danger orderPenjualanForm" data-bs-target="#modalPenjualan">
                  Volume
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="row mb-2 mx-0">
      <div class="col" id="saldoMember"></div>
    </div>
  </div>
</div>
<div class="row mx-0 px-2">
   <label class="form-label text-primary">List Order</label>
  <div class="col mx-1 px-0 border shadow-sm" id="cart" style="max-width: 500px; height: 300px; overflow-y:scroll"></div>
</div>

<div class="modal" id="modalPenjualan">
  <div class="modal-dialog" role="document">
    <div class="modal-content orderPenjualanForm">
    </div>
  </div>
</div>

<div class="modal" id="exampleModal2">
  <div class="modal-dialog modal-sm">
    <div class="modal-content addItemForm">
    </div>
  </div>
</div>

<div class="modal" id="addPelanggan">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Pelanggan Baru</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body p-1" id="divPelanggan"></div>
    </div>
  </div>
</div>

<script script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $("form.orderProses").on("submit", function(e) {
    var pelanggan_submit = $('select#pelanggan_submit').val();
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(result) {
        window.location.href = "<?= URL::BASE_URL ?>Operasi/i/0/" + pelanggan_submit + "/0";
      },
    });
  });

  $(document).ready(function() {
    $(".orderProses .tize").selectize();
    $("div#waitReady").removeClass("invisible");
    $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');

    $(".removeRow").on("click", function(e) {
      e.preventDefault();
      var id_value = $(this).attr('data-id_value');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/RemoveRow",
        data: {
          'id': id_value,
        },
        type: 'POST',
        success: function(response) {
          $('tr.tr' + id_value).remove();
          location.reload(true);
        },
      });
    });

    $(".addItem").on("click", function(e) {
      e.preventDefault();
      var id_group = $(this).attr('data-id_group');
      var id_penjualan = $(this).attr('data-id_penjualan');
      var data = id_group + "|" + id_penjualan;
      $('div.addItemForm').load('<?= URL::BASE_URL ?>Penjualan/addItemForm/' + data);
    });

    $("span.addPelanggan").on("click", function(e) {
      e.preventDefault();
      $('#divPelanggan').load('<?= URL::BASE_URL ?>Penjualan/loadPelanggan');
    });

    $("button.orderPenjualanForm").on("click", function(e) {
      var id_penjualan = $(this).attr('data-id_penjualan');
      var id_harga = 0;
      var saldo = 0;
      $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/' + id_penjualan + '/' + id_harga + '/' + saldo);
      
      // Manual trigger for modal
      var target = $(this).attr('data-bs-target');
      if(target) {
          var modalEl = document.querySelector(target);
          if(modalEl) {
              var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
              modal.show();
          }
      }
    });

    $("a.removeItem").on('click', function(e) {
      e.preventDefault();
      var idNya = $(this).attr('id');
      var keyNya = $(this).attr('data-key');

      $.ajax({
        url: '<?= URL::BASE_URL ?>Penjualan/removeItem',
        data: {
          'id': idNya,
          'key': keyNya
        },
        type: 'POST',
        success: function() {
          $("#item" + idNya + "" + keyNya).remove();
          location.reload(true);
        },
      });
    });
  });

  $('select.proses').on('change', function() {
    var id_pelanggan = this.value;
    if(id_pelanggan == "") {
      $("#saldoMember").html("");
      $("#sering").html("");
      return;
    }
    $("#saldoMember").load('<?= URL::BASE_URL ?>Member/cekRekap/' + id_pelanggan)
    $("#sering").load('<?= URL::BASE_URL ?>Penjualan/sering/' + id_pelanggan)
  });
</script>