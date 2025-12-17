<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div id="waitReady" class="col invisible">
        <div class="card p-1">
          <form id="main">
            <div class="d-flex align-items-start align-items-end">
              <div class="p-1">
                <button type="button" data-id_penjualan='1' class="btn btn-sm btn-outline-primary orderPenjualanForm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                  Kiloan
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='2' class="btn btn-sm btn-outline-info orderPenjualanForm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                  Satuan
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='3' class="btn btn-sm btn-outline-dark orderPenjualanForm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                  Bidang
                </button>
              </div>
              <div class="p-1">
                <button type="button" data-id_penjualan='4' class="btn btn-sm btn-outline-danger orderPenjualanForm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                  Volume
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="row" id="cart"></div>

  </div>
</div>

<div class="modal" id="exampleModal">
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

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  $(document).ready(function() {
    $("div#waitReady").removeClass("invisible");
    $('div#cart').load('<?= URL::BASE_URL ?>SetHargaPaket/cart');
    selectList();
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

    $("button.orderPenjualanForm").on("click", function(e) {
      var id_penjualan = $(this).attr('data-id_penjualan');
      $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>SetHargaPaket/form/' + id_penjualan);
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

  function selectList() {
    $('select.karyawan').select2();
    $('select.proses').select2();
  }
</script>