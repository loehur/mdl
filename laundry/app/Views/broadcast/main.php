<?php if ($data['mode'] == 1) {
  $target_txt = "<b>Dalam Proses</b>";
} else if ($data['mode'] == 3) {
  $target_txt = "<b>Proses & Non Proses</b>";
} else {
  $target_txt = "<b>Non Proses</b>";
}
?>

<?php
if (isset($data['dateF']) && count($data['dateF']) > 0) {
  $currentMonth =   $data['dateF']['m'];
  $currentYear =   $data['dateF']['Y'];
  $currentDay =   $data['dateF']['d'];

  $currentMonthT =   $data['dateT']['m'];
  $currentYearT =   $data['dateT']['Y'];
  $currentDayT =   $data['dateT']['d'];
} else {
  $currentMonth = date('m');
  $currentYear = date('Y');
  $currentDay = date('d');

  $currentMonthT = date('m');
  $currentYearT = date('Y');
  $currentDayT = date('d');
}

$dBroad = [];
?>

<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title mb-2">Broadcast Pelanggan <?= $target_txt ?></h4>

            <form action="<?= URL::BASE_URL; ?>Broadcast/i/<?= $data['mode'] ?>" method="POST">
              <table class="w-100">
                <tr>
                  <td>
                    <select name="d" class="form-control form-control-sm" style="width: auto;">
                      <option class="text-right" value="01" <?php if ($currentDay == '01') {
                                                              echo 'selected';
                                                            } ?>>01</option>
                      <option class="text-right" value="02" <?php if ($currentDay == '02') {
                                                              echo 'selected';
                                                            } ?>>02</option>
                      <option class="text-right" value="03" <?php if ($currentDay == '03') {
                                                              echo 'selected';
                                                            } ?>>03</option>
                      <option class="text-right" value="04" <?php if ($currentDay == '04') {
                                                              echo 'selected';
                                                            } ?>>04</option>
                      <option class="text-right" value="05" <?php if ($currentDay == '05') {
                                                              echo 'selected';
                                                            } ?>>05</option>
                      <option class="text-right" value="06" <?php if ($currentDay == '06') {
                                                              echo 'selected';
                                                            } ?>>06</option>
                      <option class="text-right" value="07" <?php if ($currentDay == '07') {
                                                              echo 'selected';
                                                            } ?>>07</option>
                      <option class="text-right" value="08" <?php if ($currentDay == '08') {
                                                              echo 'selected';
                                                            } ?>>08</option>
                      <option class="text-right" value="09" <?php if ($currentDay == '09') {
                                                              echo 'selected';
                                                            } ?>>09</option>
                      <option class="text-right" value="10" <?php if ($currentDay == '10') {
                                                              echo 'selected';
                                                            } ?>>10</option>
                      <option class="text-right" value="11" <?php if ($currentDay == '11') {
                                                              echo 'selected';
                                                            } ?>>11</option>
                      <option class="text-right" value="12" <?php if ($currentDay == '12') {
                                                              echo 'selected';
                                                            } ?>>12</option>
                      <option class="text-right" value="13" <?php if ($currentDay == '13') {
                                                              echo 'selected';
                                                            } ?>>13</option>
                      <option class="text-right" value="14" <?php if ($currentDay == '14') {
                                                              echo 'selected';
                                                            } ?>>14</option>
                      <option class="text-right" value="15" <?php if ($currentDay == '15') {
                                                              echo 'selected';
                                                            } ?>>15</option>
                      <option class="text-right" value="16" <?php if ($currentDay == '16') {
                                                              echo 'selected';
                                                            } ?>>16</option>
                      <option class="text-right" value="17" <?php if ($currentDay == '17') {
                                                              echo 'selected';
                                                            } ?>>17</option>
                      <option class="text-right" value="18" <?php if ($currentDay == '18') {
                                                              echo 'selected';
                                                            } ?>>18</option>
                      <option class="text-right" value="19" <?php if ($currentDay == '19') {
                                                              echo 'selected';
                                                            } ?>>19</option>
                      <option class="text-right" value="20" <?php if ($currentDay == '20') {
                                                              echo 'selected';
                                                            } ?>>20</option>
                      <option class="text-right" value="21" <?php if ($currentDay == '21') {
                                                              echo 'selected';
                                                            } ?>>21</option>
                      <option class="text-right" value="22" <?php if ($currentDay == '22') {
                                                              echo 'selected';
                                                            } ?>>22</option>
                      <option class="text-right" value="23" <?php if ($currentDay == '23') {
                                                              echo 'selected';
                                                            } ?>>23</option>
                      <option class="text-right" value="24" <?php if ($currentDay == '24') {
                                                              echo 'selected';
                                                            } ?>>24</option>
                      <option class="text-right" value="25" <?php if ($currentDay == '25') {
                                                              echo 'selected';
                                                            } ?>>25</option>
                      <option class="text-right" value="26" <?php if ($currentDay == '26') {
                                                              echo 'selected';
                                                            } ?>>26</option>
                      <option class="text-right" value="27" <?php if ($currentDay == '27') {
                                                              echo 'selected';
                                                            } ?>>27</option>
                      <option class="text-right" value="28" <?php if ($currentDay == '28') {
                                                              echo 'selected';
                                                            } ?>>28</option>
                      <option class="text-right" value="29" <?php if ($currentDay == '29') {
                                                              echo 'selected';
                                                            } ?>>29</option>
                      <option class="text-right" value="30" <?php if ($currentDay == '30') {
                                                              echo 'selected';
                                                            } ?>>30</option>
                      <option class="text-right" value="31" <?php if ($currentDay == '31') {
                                                              echo 'selected';
                                                            } ?>>31</option>
                    </select>
                  </td>

                  <td>
                    <select name="m" class="form-control form-control-sm" style="width: auto;">
                      <option class="text-right" value="01" <?php if ($currentMonth == '01') {
                                                              echo 'selected';
                                                            } ?>>01</option>
                      <option class="text-right" value="02" <?php if ($currentMonth == '02') {
                                                              echo 'selected';
                                                            } ?>>02</option>
                      <option class="text-right" value="03" <?php if ($currentMonth == '03') {
                                                              echo 'selected';
                                                            } ?>>03</option>
                      <option class="text-right" value="04" <?php if ($currentMonth == '04') {
                                                              echo 'selected';
                                                            } ?>>04</option>
                      <option class="text-right" value="05" <?php if ($currentMonth == '05') {
                                                              echo 'selected';
                                                            } ?>>05</option>
                      <option class="text-right" value="06" <?php if ($currentMonth == '06') {
                                                              echo 'selected';
                                                            } ?>>06</option>
                      <option class="text-right" value="07" <?php if ($currentMonth == '07') {
                                                              echo 'selected';
                                                            } ?>>07</option>
                      <option class="text-right" value="08" <?php if ($currentMonth == '08') {
                                                              echo 'selected';
                                                            } ?>>08</option>
                      <option class="text-right" value="09" <?php if ($currentMonth == '09') {
                                                              echo 'selected';
                                                            } ?>>09</option>
                      <option class="text-right" value="10" <?php if ($currentMonth == '10') {
                                                              echo 'selected';
                                                            } ?>>10</option>
                      <option class="text-right" value="11" <?php if ($currentMonth == '11') {
                                                              echo 'selected';
                                                            } ?>>11</option>
                      <option class="text-right" value="12" <?php if ($currentMonth == '12') {
                                                              echo 'selected';
                                                            } ?>>12</option>
                    </select>
                  </td>
                  <td>
                    <?php
                    $year = date('Y');
                    $oldYear = URL::FIRST_YEAR;
                    ?>
                    <select name="Y" class="form-control form-control-sm" style="width: auto;">
                      <?php
                      while ($year >= $oldYear) { ?>
                        <option class="text-right" value="<?= $year ?>" <?php if ($currentYear == $year) {
                                                                          echo 'selected';
                                                                        } ?>><?= $year ?></option>
                      <?php
                        $year--;
                      } ?>
                    </select>
                  </td>

                  <td class="pr-2 pl-2">s/d</td>

                  <td>
                    <select name="dt" class="form-control form-control-sm" style="width: auto;">
                      <option class="text-right" value="01" <?php if ($currentDayT == '01') {
                                                              echo 'selected';
                                                            } ?>>01</option>
                      <option class="text-right" value="02" <?php if ($currentDayT == '02') {
                                                              echo 'selected';
                                                            } ?>>02</option>
                      <option class="text-right" value="03" <?php if ($currentDayT == '03') {
                                                              echo 'selected';
                                                            } ?>>03</option>
                      <option class="text-right" value="04" <?php if ($currentDayT == '04') {
                                                              echo 'selected';
                                                            } ?>>04</option>
                      <option class="text-right" value="05" <?php if ($currentDayT == '05') {
                                                              echo 'selected';
                                                            } ?>>05</option>
                      <option class="text-right" value="06" <?php if ($currentDayT == '06') {
                                                              echo 'selected';
                                                            } ?>>06</option>
                      <option class="text-right" value="07" <?php if ($currentDayT == '07') {
                                                              echo 'selected';
                                                            } ?>>07</option>
                      <option class="text-right" value="08" <?php if ($currentDayT == '08') {
                                                              echo 'selected';
                                                            } ?>>08</option>
                      <option class="text-right" value="09" <?php if ($currentDayT == '09') {
                                                              echo 'selected';
                                                            } ?>>09</option>
                      <option class="text-right" value="10" <?php if ($currentDayT == '10') {
                                                              echo 'selected';
                                                            } ?>>10</option>
                      <option class="text-right" value="11" <?php if ($currentDayT == '11') {
                                                              echo 'selected';
                                                            } ?>>11</option>
                      <option class="text-right" value="12" <?php if ($currentDayT == '12') {
                                                              echo 'selected';
                                                            } ?>>12</option>
                      <option class="text-right" value="13" <?php if ($currentDayT == '13') {
                                                              echo 'selected';
                                                            } ?>>13</option>
                      <option class="text-right" value="14" <?php if ($currentDayT == '14') {
                                                              echo 'selected';
                                                            } ?>>14</option>
                      <option class="text-right" value="15" <?php if ($currentDayT == '15') {
                                                              echo 'selected';
                                                            } ?>>15</option>
                      <option class="text-right" value="16" <?php if ($currentDayT == '16') {
                                                              echo 'selected';
                                                            } ?>>16</option>
                      <option class="text-right" value="17" <?php if ($currentDayT == '17') {
                                                              echo 'selected';
                                                            } ?>>17</option>
                      <option class="text-right" value="18" <?php if ($currentDayT == '18') {
                                                              echo 'selected';
                                                            } ?>>18</option>
                      <option class="text-right" value="19" <?php if ($currentDayT == '19') {
                                                              echo 'selected';
                                                            } ?>>19</option>
                      <option class="text-right" value="20" <?php if ($currentDayT == '20') {
                                                              echo 'selected';
                                                            } ?>>20</option>
                      <option class="text-right" value="21" <?php if ($currentDayT == '21') {
                                                              echo 'selected';
                                                            } ?>>21</option>
                      <option class="text-right" value="22" <?php if ($currentDayT == '22') {
                                                              echo 'selected';
                                                            } ?>>22</option>
                      <option class="text-right" value="23" <?php if ($currentDayT == '23') {
                                                              echo 'selected';
                                                            } ?>>23</option>
                      <option class="text-right" value="24" <?php if ($currentDayT == '24') {
                                                              echo 'selected';
                                                            } ?>>24</option>
                      <option class="text-right" value="25" <?php if ($currentDayT == '25') {
                                                              echo 'selected';
                                                            } ?>>25</option>
                      <option class="text-right" value="26" <?php if ($currentDayT == '26') {
                                                              echo 'selected';
                                                            } ?>>26</option>
                      <option class="text-right" value="27" <?php if ($currentDayT == '27') {
                                                              echo 'selected';
                                                            } ?>>27</option>
                      <option class="text-right" value="28" <?php if ($currentDayT == '28') {
                                                              echo 'selected';
                                                            } ?>>28</option>
                      <option class="text-right" value="29" <?php if ($currentDayT == '29') {
                                                              echo 'selected';
                                                            } ?>>29</option>
                      <option class="text-right" value="30" <?php if ($currentDayT == '30') {
                                                              echo 'selected';
                                                            } ?>>30</option>
                      <option class="text-right" value="31" <?php if ($currentDayT == '31') {
                                                              echo 'selected';
                                                            } ?>>31</option>
                    </select>
                  </td>

                  <td>
                    <select name="mt" class="form-control form-control-sm" style="width: auto;">
                      <option class="text-right" value="01" <?php if ($currentMonthT == '01') {
                                                              echo 'selected';
                                                            } ?>>01</option>
                      <option class="text-right" value="02" <?php if ($currentMonthT == '02') {
                                                              echo 'selected';
                                                            } ?>>02</option>
                      <option class="text-right" value="03" <?php if ($currentMonthT == '03') {
                                                              echo 'selected';
                                                            } ?>>03</option>
                      <option class="text-right" value="04" <?php if ($currentMonthT == '04') {
                                                              echo 'selected';
                                                            } ?>>04</option>
                      <option class="text-right" value="05" <?php if ($currentMonthT == '05') {
                                                              echo 'selected';
                                                            } ?>>05</option>
                      <option class="text-right" value="06" <?php if ($currentMonthT == '06') {
                                                              echo 'selected';
                                                            } ?>>06</option>
                      <option class="text-right" value="07" <?php if ($currentMonthT == '07') {
                                                              echo 'selected';
                                                            } ?>>07</option>
                      <option class="text-right" value="08" <?php if ($currentMonthT == '08') {
                                                              echo 'selected';
                                                            } ?>>08</option>
                      <option class="text-right" value="09" <?php if ($currentMonthT == '09') {
                                                              echo 'selected';
                                                            } ?>>09</option>
                      <option class="text-right" value="10" <?php if ($currentMonthT == '10') {
                                                              echo 'selected';
                                                            } ?>>10</option>
                      <option class="text-right" value="11" <?php if ($currentMonthT == '11') {
                                                              echo 'selected';
                                                            } ?>>11</option>
                      <option class="text-right" value="12" <?php if ($currentMonthT == '12') {
                                                              echo 'selected';
                                                            } ?>>12</option>
                    </select>
                  </td>
                  <td>
                    <?php
                    $year = date('Y');
                    $oldYear = URL::FIRST_YEAR;
                    ?>
                    <select name="Yt" class="form-control form-control-sm" style="width: auto;">
                      <?php
                      while ($year >= $oldYear) { ?>
                        <option class="text-right" value="<?= $year ?>" <?php if ($currentYearT == $year) {
                                                                          echo 'selected';
                                                                        } ?>><?= $year ?></option>
                      <?php
                        $year--;
                      } ?>
                    </select>
                  </td>

                  <td class="pr-2"><button class="form-control form-control-sm m-1 p-1 bg-success">Cek Pelanggan</button></td>
                  <td id="broad" class="d-none">
                    <button type="button" class="btn btn-sm btn-primary float-right" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      + Broadcast
                    </button>
                  </td>
                </tr>
              </table>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-auto">
        <div class="card">
          <div class="card-body">
            <div class="row">
              <div class="col">
                <?php
                foreach ($data['data'] as $key => $a) {
                  $f17 = $a['id_pelanggan'];
                  $cab = $a['id_cabang'];
                  $modeNotif = 1;
                  foreach ($data['pelanggan'] as $c) {
                    if ($c['id_pelanggan'] == $f17) {
                      $no_pelanggan = $c['nomor_pelanggan'];
                      $pelanggan = $c['nama_pelanggan'];
                    }
                  }

                  $dBroad[$key] = ['no' => $no_pelanggan, 'cab' => $cab, 'mode' => $modeNotif]; ?>
                  <span class="border pr-1 pl-1 rounded"> <?= $pelanggan . " | " . $no_pelanggan ?></span>
                <?php }
                ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="modal" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <form class="ajax" action="<?= URL::BASE_URL; ?>Broadcast/insert" method="POST">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Buat Broadcast</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div id="info" style="text-align: center;">Pesan akan di kirimkan kepada pelanggan pada rentang waktu yang ditentukan<br> (Pelanggan <?= $target_txt ?>)</div>
          <div class="card-body">
            <div class="form-group">
              <label for="exampleInputEmail1">Pesan (Max. 150 karakter)</label><br>
              <textarea class="form-control" name="text" minlength="10" maxlength="150" rows="5" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-sm btn-primary" data-bs-dismiss="modal">Proses</button>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/popper.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  var datab;
  $(document).ready(function() {
    var datab = '<?php echo json_encode($dBroad) ?>';
    if (datab.length > 2) {
      $("td#broad").removeClass("d-none");
    }
  });

  $("form.ajax").on("submit", function(e) {
    e.preventDefault();
    var datab = '<?php echo json_encode($dBroad) ?>';
    var textnya = $("textarea[name='text']").val();

    $.ajax({
      url: $(this).attr('action'),
      data: {
        text: textnya,
        broad: datab
      },
      type: $(this).attr("method"),
      success: function(res) {
        console.log(JSON.stringify(res));
        alert(res);
      },
    });
  });
</script>