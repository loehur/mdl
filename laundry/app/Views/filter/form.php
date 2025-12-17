<style>
  table {
    border-radius: 15px;
    overflow: hidden
  }
</style>

<?php $modeView = $data['modeView'];
?>

<div class="content w-100 sticky-top" style="max-width:840px">
  <header>
    <div class="container-fluid">
      <div class="bg-white py-2 rounded border">
        <div class="row px-2">
          <div class="col">
            <div class="row">
              <div class="col mb-1">
                <div class="input-group input-group-sm">
                  <span class="input-group-text text-primary">Dari</span>
                  <input name="start_date" type="date" onclick="max()" min="2023-07-01" max="<?= date("Y-m-d") ?>" placeholder="" class="form-control" required>
                </div>
              </div>
              <div class="col">
                <div class="input-group input-group-sm">
                  <span class="input-group-text text-primary">Ke</span>
                  <input name="end_date" type="date" min="2023-07-02" max="<?= date("Y-m-d") ?>" placeholder="" class="form-control" required>
                  <button type="submit" onclick="cek()" class="btn btn-primary">Cek</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  </header>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
  function cek() {
    var from = $("input[name=start_date]").val();
    var to = $("input[name=end_date]").val();
    var days = count(from, to);

    if (from == "" || to == "") {
      alert("Isi tanggal dulu");
      return;
    }

    if (days > 7) {
      alert("Rentang Tidak boleh lebih dari 7 hari");
      return;
    }

    $(".loaderDiv").fadeIn("fast");
    $("div#load").load("<?= URL::BASE_URL ?>Filter/loadList/" + <?= $modeView ?> + "/" + from + "/" + to);
    $(".loaderDiv").fadeOut("slow");
  }

  $("input[name=start_date]").change(function() {
    $("input[name=end_date]").attr("min", $(this).val());
  })

  $("input[name=end_date]").change(function() {
    $("input[name=start_date]").attr("max", $(this).val());
  })

  function count(from, to) {
    let date1 = new Date(from);
    let date2 = new Date(to);
    let Difference_In_Time =
      date2.getTime() - date1.getTime();
    let Difference_In_Days =
      Math.round(Difference_In_Time / (1000 * 3600 * 24));
    return Difference_In_Days;
  }
</script>