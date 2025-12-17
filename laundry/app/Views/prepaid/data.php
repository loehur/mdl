<div class="col" style="min-width:200px">
  <div class="card mb-2">
    <div class="card-body p-1">
      <table class="table table-sm w-100" id="dtTable">
        <thead>
          <tr>
            <th>Pembelian</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 0;
          foreach ($data['pre'] as $a) {
            $id = $a['id'];
            $code = $a['product_code'];
            $customer_id = $a['customer_id'];
            $message = $a['message'];
            $sn = $a['sn'];
            $ref_id = $a['ref_id'];
            $date = substr($a['insertTime'], 0, 10);
            $tr_status = $a['tr_status'];
            $icon_status = '<i class="fas fa-circle text-warning"></i>';
            if ($tr_status == 1) {
              $icon_status = '<i class="fas fa-check-circle text-success"></i>';
            } else if ($tr_status == 2) {
              $icon_status = '<i class="fas fa-times-circle text-danger"></i>';
            }

            echo "<tr>";
            echo "<td><span class='text-secondary fw-bold'>" . strtoupper($code) . "</span> #" . $id . "<br>" . $date . " " . $icon_status . "<br>";
            echo $customer_id . " Rp" . number_format($a['price']) . "<br>";
            echo $message . "<br>";
            if ($tr_status == 0) { ?>
              <span data-ref="<?= $ref_id ?>" class="text-primary fw-bold" id="cek_status" style="cursor: pointer;">Cek Status</span>
            <?php
            } else if ($tr_status == 2) { ?>
              <span class="text-danger">TRANSACTION FAILED</span>
          <?php } else {
              echo "<span class='text-success'>" . $sn . "</span></td>";
            }
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-1">
      <table class="table table-sm w-100" id="dtTable">
        <thead>
          <tr>
            <th>Pembayaran</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 0;
          foreach ($data['post'] as $a) {
            $id = $a['id'];
            $code = $a['code'];
            $customer_id = $a['customer_id'];
            $tr_name = $a['tr_name'];
            $message = $a['message'];
            $sn = $a['noref'];
            $ref_id = $a['ref_id'];
            $date = substr($a['insertTime'], 0, 10);
            $tr_status = $a['tr_status'];
            $icon_status = '<i class="fas fa-circle text-warning"></i>';
            if ($tr_status == 1) {
              $icon_status = '<i class="fas fa-check-circle text-success"></i>';
            } else if ($tr_status == 2) {
              $icon_status = '<i class="fas fa-times-circle text-danger"></i>';
            }

            echo "<tr>";
            echo "<td><span class='text-secondary fw-bold'>" . $code . "</span> #" . $id . " " . $date . " " . $icon_status . "<br>";
            echo $customer_id . " " . strtoupper($tr_name) . "<br>Rp" . number_format($a['price']) . "<br>" . $message . "<br>";
            if ($tr_status == 0 || $tr_status == 3) { ?>
              <span data-ref="<?= $ref_id ?>" class="text-primary fw-bold" id="cek_status_post" style="cursor: pointer;">Cek Status</span>
            <?php
            } else if ($tr_status == 2) { ?>
              <span class="text-danger">TRANSACTION FAILED</span>
          <?php } else {
              echo "<span class='text-success'>" . $sn . "</span></td>";
            }
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  $("#cek_status").on("click", function(e) {
    var ref = $(this).attr('data-ref');
    $(".loaderDiv").fadeIn("fast");
    e.preventDefault();
    $.ajax({
      url: '<?= URL::BASE_URL ?>Prepaid/cek_status',
      data: {
        ref_id: ref
      },
      type: 'POST',
      success: function(res) {
        $(".loaderDiv").fadeOut("slow");
        if (res == 0) {
          load_data();
        } else {
          $(this).html(res);
        }
      },
    });
  });

  $("#cek_status_post").on("click", function(e) {
    var ref = $(this).attr('data-ref');
    $(".loaderDiv").fadeIn("fast");
    e.preventDefault();
    $.ajax({
      url: '<?= URL::BASE_URL ?>Prepaid/cek_status_post',
      data: {
        ref_id: ref
      },
      type: 'POST',
      success: function(res) {
        $(".loaderDiv").fadeOut("slow");
        if (res == 0) {
          load_data();
        } else {
          $(this).html(res);
        }
      },
    });
  });
</script>