<div class="row mx-0">
  <div class="col">
    <div class="card mb-2">
      <div class="card-body p-1">
        <table class="table table-sm w-auto">
          <thead>
            <tr>
              <th class="text-right">#</th>
              <th>Produk</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $modal = "data-bs-toggle='modal' data-bs-target='#exampleModal'";
            $no = 0;
            foreach ($data['list'] as $a) {
              $name = $a['product_name'];
              $limit = $a['monthly_limit'];
              $no++;
              $nameAttr = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
              echo "<tr>";
              echo "<td class='text-right'>" . $no . "</td>";
              echo "<td>" . $name . "<br><span class='text-primary'><small>Limit Bulanan " . number_format($limit) . "</small></span></td>";
              echo "<td class='pt-2'><span data-name='" . $nameAttr . "' " . $modal . " class='btn btn-sm btn-success modal_pre'>Beli</span></td>";
              echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="row mx-0" id="data"></div>

<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title w-100 text-center" id="exampleModalLabel">Pembelian Token</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small text-center mb-1" id="modal_product_name"></p>
        <p class="text-center mb-3" style="line-height: 1.5;">
          Pembelian token saat ini dilakukan melalui <strong>WhatsApp Laundry</strong>. Silakan kirim pesan berikut ke nomor resmi kami, lalu ikuti petunjuk yang diberikan.
        </p>
        <div class="rounded p-3 mb-3 text-center" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6;">
          <span class="d-block text-uppercase text-muted small mb-2" style="letter-spacing: 0.05em;">Format pesan</span>
          <code class="d-inline-block px-2 py-1 rounded bg-white border user-select-all fw-bold text-dark" style="font-size: 0.95rem;">{ CEK TOKEN LAUNDRY }</code>
        </div>
        <p class="small text-muted text-center mb-0">
          <i class="fab fa-whatsapp text-success me-1"></i>
          Pastikan Anda menghubungi nomor WhatsApp Laundry yang terdaftar agar transaksi berjalan aman dan tertib.
        </p>
      </div>
      <div class="modal-footer border-0 pt-0 justify-content-center">
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    load_data();
  });

  function load_data() {
    $("#data").load("<?= URL::BASE_URL ?>Prepaid/load_data");
  }

  $("span.modal_pre").on('click', function() {
    var name = $(this).attr('data-name');
    if (name) {
      $("#modal_product_name").html('<span class="fw-semibold text-dark">' + $('<div/>').text(name).html() + '</span>');
    } else {
      $("#modal_product_name").empty();
    }
  });
</script>
