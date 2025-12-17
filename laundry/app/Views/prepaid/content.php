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
              echo "<tr>";
              echo "<td class='text-right'>" . $no . "</td>";
              echo "<td>" . $name . "<br><span class='text-primary'><small>Limit Bulanan " . number_format($limit) . "</small></span></td>";
              echo "<td class='pt-2'><span data-id='" . $a['pre_id'] . "' " . $modal . " class='btn btn-sm btn-success modal_pre'>Beli</span></td>";
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

<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- ====================== FORM ========================= -->
        <form action="<?= URL::BASE_URL ?>Prepaid/buy" method="POST">
          <div class="card-body">
            <p id="info"></p>
            <label class="">PIN</label>
            <div class="input-group mb-3">
              <input type="password" name="pin" class="form-control" required>
              <div class="input-group-append">
                <div class="input-group-text" id="req_pin" style="cursor: pointer;">
                  <span<i class="fas fa-mobile-alt"></i></span>
                </div>
              </div>
              <input type="hidden" name="id" id="pre_id" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-sm btn-primary py-2 w-100">Proses</button>
          </div>
        </form>
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

  $("#req_pin").on("click", function(e) {
    var hp_input = '<?= $this->user_login['no_user'] ?>';
    $(".loaderDiv").fadeIn("fast");
    e.preventDefault();
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

  $("span.modal_pre").on('click', function() {
    var id = $(this).attr('data-id');
    $("input#pre_id").val(id);
    $("input[name=pin]").focus();
  })

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
            location.reload(true);
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