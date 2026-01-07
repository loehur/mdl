<link rel="stylesheet" href="<?= URL::ASSETS_URL ?>css/selectize.bootstrap3.min.css">

<?php $kas = $data['saldo_kas_besar']; ?>
<div class="row mx-0">
  <div class="col p-1">
    <div class="d-flex flex-row">
      <div class="mr-auto">
        <small>Saldo Kas Besar</small><br>
        <span class="text-bold text-success">Rp. <?= number_format($kas); ?></span>
      </div>
      <div class="p-0 pr-0 pb-2 pt-2">
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalPengeluaran">
          <i class="fas fa-minus-circle"></i> Pengeluaran
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Riwayat Pengeluaran -->
<div class="row mx-0">
  <div class="col w-100">
    <table class="table table-sm m-0">
      <tr>
        <th class="pt-2 text-center" colspan="2">
          Riwayat Pengeluaran
        </th>
      </tr>
      <tbody>
        <?php
        if (count($data['riwayat']) > 0) {
          foreach ($data['riwayat'] as $a) {
            $id = $a['id'];
            $tgl = substr($a['insertTime'], 5, 11);
            $jenis = $a['note_primary'];
            $ket = $a['note'];
            $jumlah = $a['jumlah'];
            $st = $a['status_mutasi'];

            echo "<tr>";
            echo "<td nowrap><small>#" . $id . " " . $tgl . "</small><br><b class='text-danger'>" . $jenis . "</b> <small>" . $ket . "</small></td>";
            echo "<td nowrap class='text-right'><b>" . number_format($jumlah) . "</b><br><small class='text-" . URL::ST_MUTASI[$st][1] . "'>" . URL::ST_MUTASI[$st][0] . "</small></td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='2' class='text-center text-muted'>Belum ada pengeluaran</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal" id="modalPengeluaran" tabindex="-1" aria-labelledby="modalPengeluaranLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPengeluaranLabel">Pengeluaran Kas Besar</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form action="<?= URL::BASE_URL; ?>Kas_Besar/insert_pengeluaran" method="POST" id="formPengeluaran">
          <div class="card-body">
            <div class="form-group">
              <input type="text" name='kas' class="form-control text-center text-bold saldoKas" readonly>
            </div>
            <div class="form-group" id="jenisKeluar">
              <label>Jenis Pengeluaran</label>
              <select name="f1a" class="form-control form-control-sm jenisKeluar" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php 
                // Pisahkan berdasarkan is_expense
                $biaya = [];
                $nonBiaya = [];
                foreach ($data['pengeluaran_jenis'] as $ip) {
                  $is_expense = isset($ip['is_expense']) ? $ip['is_expense'] : 1;
                  if ($is_expense == 1) {
                    $biaya[] = $ip;
                  } else {
                    $nonBiaya[] = $ip;
                  }
                }
                ?>
                <?php if (count($biaya) > 0) { ?>
                <optgroup label="Biaya">
                  <?php foreach ($biaya as $ip) { ?>
                    <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                  <?php } ?>
                </optgroup>
                <?php } ?>
                <?php if (count($nonBiaya) > 0) { ?>
                <optgroup label="Non-Biaya">
                  <?php foreach ($nonBiaya as $ip) { ?>
                    <option value="<?= $ip['id_item_pengeluaran'] ?><explode><?= $ip['item_pengeluaran'] ?>"><?= $ip['item_pengeluaran'] ?></option>
                  <?php } ?>
                </optgroup>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label>Jumlah Rp</label>
              <input type="number" name="f2" min="1000" class="form-control jumlahTarik text-center" placeholder="" required>
            </div>
            <div class="form-group">
              <label>Keterangan</label>
              <input type="text" name="f1" class="form-control" placeholder="">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-sm rounded-0 w-100 bg-gradient btn-danger">Buat Pengeluaran</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::ASSETS_URL ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    var saldoKas = <?= $kas ?>;
    $('input.saldoKas').val(formatter.format(saldoKas));

    // Inisialisasi Selectize untuk jenis pengeluaran
    $('select.jenisKeluar').selectize({
      sortField: 'text',
      placeholder: 'Pilih Jenis Pengeluaran'
    });
  });

  $("#formPengeluaran").on("submit", function(e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(res) {
        if (res == 0) {
          location.reload(true);
        } else {
          alert(res);
        }
      },
    });
  });

  $("input.jumlahTarik").on("keyup change", function() {
    if ($(this).val() > 0) {
      saldoKas = <?= $kas ?>;
      var potong = $(this).val();
      var sisaKas = parseInt(saldoKas) - parseInt(potong);

      $('input.saldoKas').val(formatter.format(sisaKas));
      if (sisaKas < 0) {
        $('input.saldoKas').addClass('text-danger');
      } else {
        $('input.saldoKas').removeClass('text-danger');
      }
    } else {
      $('input.saldoKas').val(formatter.format(saldoKas));
      $('input.saldoKas').removeClass('text-danger');
    }
  });

  var formatter = new Intl.NumberFormat('en-ID', {
    style: 'currency',
    currency: 'IDR',
  });
</script>

