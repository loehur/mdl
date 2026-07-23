<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<?php $b = unserialize($data['data']); ?>

<form action="<?= URL::BASE_URL ?>Penjualan/addItem/<?= $data['id'] ?>" method="POST">
  <div class="modal-header">
    <h5 class="modal-title">Tambah Item</h5>
  </div>
  <div class="modal-body">
    <div class="card-body">
      <div class="form-group">
        <label>Item</label>
        <select name="f1" class="select2a form-control form-control-sm" style="width: 100%" required>
          <option value="" selected></option>
          <?php foreach ($b as $a) { ?>
            <option value="<?= $a ?>">
              <?php foreach ($this->dItem as $c) {
                if ($c['id_item'] == $a) {
                  echo $c['item'];
                }
              } ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group">
        <div class="form-group">
          <label for="exampleInputEmail1">Banyak</label>
          <input type="number" name="f2" min="1" class="form-control" id="exampleInputEmail1" value="1" placeholder="">
        </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-sm btn-primary">Tambah</button>
  </div>
</form>

<script>
  $(document).ready(function() {
    $("form").on("submit", function(e) {
      e.preventDefault();
      var $form = $(this);
      var $btn = $form.find('button[type="submit"]');
      if ($btn.data("loading")) return;
      var prevHtml = $btn.html();
      $btn.data("loading", 1).prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Memuat…');
      if (typeof window.setOrdCartLoading === "function") {
        window.setOrdCartLoading(true);
      }
      $.ajax({
        url: $form.attr('action'),
        data: $form.serialize(),
        type: $form.attr("method"),
        success: function() {
          if (typeof window.reloadOrdCart === "function") {
            window.reloadOrdCart();
          } else {
            $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart', function () {
              if (typeof window.setOrdCartLoading === "function") {
                window.setOrdCartLoading(false);
              }
            });
          }
          $(".modal").hide();
        },
        error: function () {
          alert("Gagal menambah item.");
          $btn.data("loading", 0).prop("disabled", false).html(prevHtml);
          if (typeof window.setOrdCartLoading === "function") {
            window.setOrdCartLoading(false);
          }
        }
      });
    });
  });
</script>