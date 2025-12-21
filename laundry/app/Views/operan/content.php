<?php
$idOperan = $data['idOperan'];
?>

<div class="card">
  <div class="card-body p-0 table-responsive-sm">
    <table class="table table-sm w-100 table-borderless">
      <tbody id="tabelAntrian">
        <?php
        $prevRef = '';
        $prevPoin = 0;

        $arrRef = array();
        $countRef = 0;

        $arrPoin = array();
        $jumlahRef = 0;

        foreach ($data['data_main'] as $a) {
          $ref = $a['no_ref'];

          if ($prevRef <> $a['no_ref']) {
            $countRef = 0;
            $countRef++;
            $arrRef[$ref] = $countRef;
          } else {
            $countRef++;
            $arrRef[$ref] = $countRef;
          }
          $prevRef = $ref;
        }

        $no = 0;
        $urutRef = 0;
        $arrCount = 0;
        $listPrint = "";
        $listNotif = "";
        $arrPoiny =  array();
        $arrGetPoin = array();
        $arrTotalPoin = array();

        $arrBayar = array();

        $enHapus = true;
        foreach ($data['data_main'] as $a) {
          $no++;
          $id = $a['id_penjualan'];
          $f10 = $a['id_penjualan_jenis'];
          $f3 = $a['id_item_group'];
          $f4 = $a['list_item'];
          $f5 = $a['list_layanan'];
          $f11 = $a['id_durasi'];
          $f6 = $a['qty'];
          $f7 = $a['harga'];
          $f8 = $a['note'];
          $f9 = $a['id_user'];
          $f1 = $a['insertTime'];
          $f12 = $a['hari'];
          $f13 = $a['jam'];
          $f14 = $a['diskon_qty'];
          $f15 = $a['diskon_partner'];
          $f16 = $a['min_order'];
          $f17 = $a['id_pelanggan'];
          $f18 = $a['id_user'];
          $noref = $a['no_ref'];
          $letak = $a['letak'];
          $pack = $a['pack'];
          $hanger = $a['hanger'];
          $id_ambil = $a['id_user_ambil'];
          $tgl_ambil = $a['tgl_ambil'];
          $id_cabang = $a['id_cabang'];
          $id_harga = $a['id_harga'];
          $member = $a['member'];
          $phpdate = strtotime($f1);
          $idCabangAsal = $a['id_cabang'];
          $f1 = date('d-m-Y H:i:s', $phpdate);

          if ($f12 <> 0) {
            $tgl_selesai = date('d-m-Y', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
          } else {
            $tgl_selesai = date('d-m-Y H:i', strtotime($f1 . ' +' . $f12 . ' days +' . $f13 . ' hours'));
          }

          $pelanggan = '';
          $no_pelanggan = '';
          foreach ($this->pelangganLaundry as $c) {
            if ($c['id_pelanggan'] == $f17) {
              $pelanggan = $c['nama_pelanggan'];
              $no_pelanggan = $c['nomor_pelanggan'];
            }
          }

          $karyawan = '';
          foreach ($this->userMerge as $c) {
            if ($c['id_user'] == $f18) {
              $karyawan = $c['nama_user'];
            }
          }

          $penjualan = "";
          $satuan = "";
          foreach ($this->dPenjualan as $l) {
            if ($l['id_penjualan_jenis'] == $f10) {
              $penjualan = $l['penjualan_jenis'];
              foreach ($this->dSatuan as $sa) {
                if ($sa['id_satuan'] == $l['id_satuan']) {
                  $satuan = $sa['nama_satuan'];
                }
              }
            }
          }

          $show_qty = "";
          $qty_real = 0;
          if ($f6 < $f16) {
            $qty_real = $f16;
            $show_qty = $f6 . $satuan . " (Min. " . $f16 . $satuan . ")";
          } else {
            $qty_real = $f6;
            $show_qty = $f6 . $satuan;
          }

          if ($no == 1) {
            $totalBayar = 0;
            $subTotal = 0;
            $enHapus = true;
            $urutRef++;
          }

          $kategori = "";
          foreach ($this->itemGroup as $b) {
            if ($b['id_item_group'] == $f3) {
              $kategori = $b['item_kategori'];
            }
          }

          $durasi = "";
          foreach ($this->dDurasi as $b) {
            if ($b['id_durasi'] == $f11) {
              $durasi = strtoupper($b['durasi']);
            }
          }

          $userAmbil = "";
          $endLayananDone = false;
          $list_layanan = "";
          $list_layanan_print = "";
          $arrList_layanan = unserialize($f5);
          $endLayanan = end($arrList_layanan);
          $doneLayanan = 0;
          $countLayanan = count($arrList_layanan);
          foreach ($arrList_layanan as $b) {
            $check = 0;
            foreach ($this->dLayanan as $c) {
              if ($c['id_layanan'] == $b) {
                foreach ($data['operasi'] as $o) {
                  if ($o['id_penjualan'] == $id && $o['jenis_operasi'] == $b) {
                    $user = "";
                    $check++;
                    foreach ($this->userMerge as $p) {
                      if ($p['id_user'] == $o['id_user_operasi']) {
                        $user = $p['nama_user'];
                      }
                      if ($p['id_user'] == $id_ambil) {
                        $userAmbil = $p['nama_user'];
                      }
                    }
                    $list_layanan = $list_layanan . '<small><b><i class="fas fa-check-circle text-success"></i> ' . $c['layanan'] . "</b> " . $user . " <span style='white-space: pre;'>(" . substr($o['insertTime'], 5, 11) . ")</span></small><br>";
                    $doneLayanan++;
                    if ($b == $endLayanan) {
                      $endLayananDone = true;
                    }
                    $enHapus = false;
                  }
                }
                if ($check == 0) {
                  $list_layanan = $list_layanan . "<span id='" . $id . $b . "' data-layanan='" . $c['layanan'] . "' data-cabang='" . $id_cabang . "' data-value='" . $c['id_layanan'] . "' data-id='" . $id . "' class='addOperasi mb-1 badge badge-info rounded btn'>" . $c['layanan'] . "</span><br>";
                }
                $list_layanan_print = $list_layanan_print . $c['layanan'] . " ";
              }
            }
          }

          if ($id_ambil > 0) {
            $list_layanan = $list_layanan . "<small><b><i class='fas fa-check-circle text-success'></i> Ambil</b> " . $userAmbil . " <span style='white-space: pre;'>(" . substr($tgl_ambil, 5, 11) . ")</span></small><br>";
          }

          $buttonAmbil = "";
          $list_layanan = $list_layanan . "<span class='operasiAmbil" . $id . "'></span>";

          $diskon_qty = $f14;
          $diskon_partner = $f15;

          $show_diskon_qty = "";
          if ($diskon_qty > 0) {
            $show_diskon_qty = $diskon_qty . "%";
          }
          $show_diskon_partner = "";
          if ($diskon_partner > 0) {
            $show_diskon_partner = $diskon_partner . "%";
          }
          $plus = "";
          if ($diskon_qty > 0 && $diskon_partner > 0) {
            $plus = " + ";
          }
          $show_diskon = $show_diskon_qty . $plus . $show_diskon_partner;

          $itemList = "";
          $itemListPrint = "";
          if (strlen($f4) > 0) {
            $arrItemList = unserialize($f4);
            $arrCount = count($arrItemList);
            if ($arrCount > 0) {
              foreach ($arrItemList as $key => $k) {
                foreach ($this->dItem as $b) {
                  if ($b['id_item'] == $key) {
                    $itemList = $itemList . "<span class='badge badge-light text-dark'>" . $b['item'] . "[" . $k . "]</span> ";
                    $itemListPrint = $itemListPrint . $b['item'] . "[" . $k . "]";
                  }
                }
              }
            }
          }

          $total = 0;
          if ($diskon_qty > 0) {
            $total = ($f7 * $qty_real) - (($f7 * $qty_real) * ($diskon_qty / 100));
          } else {
            $total = ($f7 * $qty_real);
          }

          $subTotal = $subTotal + $total;

          foreach ($arrRef as $key => $m) {
            if ($key == $noref) {
              $arrCount = $m;
            }
          }

          $show_total = "";
          $show_total_print = "";
          $show_total_notif = "";

          $tampilDiskon = "";
          if ($member == 0) {
            if (strlen($show_diskon) > 0) {
              $tampilDiskon = "(Disc. " . $show_diskon . ")";
              $show_total = "<del>Rp" . number_format($f7 * $qty_real) . "</del><br>Rp" . number_format($total);
              $show_total_print = "-" . $show_diskon . " <del>Rp" . number_format($f7 * $qty_real) . "</del> Rp" . number_format($total);
              $show_total_notif = "Rp" . number_format($f7 * $qty_real) . "-" . $show_diskon . " Rp" . number_format($total);
            } else {
              $tampilDiskon = "";
              $show_total = "Rp" . number_format($total);
              $show_total_print = "Rp" . number_format($total);
              $show_total_notif = "Rp" . number_format($total);
            }
          } else {
            $show_total = "<span class='badge badge-success'>Member</span>";
            $show_total_print = "MEMBER";
            $show_total_notif = "MEMBER";
          }

          $showNote = "";
          if (strlen($f8) > 0) {
            $showNote = $f8;
          }

          $classTRDurasi = "";
          if ($f11 <> 11) {
            $classTRDurasi = "class='table-warning'";
          }

          if ($endLayananDone == true) {
            $dataPack = "Pack: " . $pack . ", Hanger: " . $hanger;
          } else {
            $dataPack = "";
          }

          echo "<tr id='tr" . $id . "' " . $classTRDurasi . ">";
          echo "<td>"
            . $id . " | <span><b>" . strtoupper($pelanggan) . "</b> " . $buttonAmbil . "</span>
                  <br>" . $kategori . "<span class='badge badge-light'>" . $penjualan . "</span><br>" .
            $dataPack . "
                  </td>";
          echo "<td>" . $itemList . "</td>";
          echo "<td><b>" . $durasi . "</b><br><span style='white-space: pre;'>(" . $f12 . "h " . $f13 . "j)</span></td>";
          echo "<td class='text-right'>" . $show_total . "<br><b>" . $show_qty . " " . $tampilDiskon . "</b></td>";
          echo "<td class='text-info'><b>" . substr($f1, 0, 5) . "</b> " . substr($f1, 11, 5) . "<br><small>" . $f8 . "</small></td>";
          echo "<td class='text-right'></td>";
          echo "</tr>";
          echo "<tr>";
          echo "<td colspan='10'>" . $list_layanan . "</td>";
          echo "</tr>";
          echo "<tr>";
          echo "<td colspan='10' class='table-info'></td>";
          echo "</tr>";


          $showMutasi = "";
          $userKas = "";

          echo "<span class='d-none selesai" . $id . "' data-hp='" . $no_pelanggan . "'>" . strtoupper($pelanggan) . " _#" . $idCabangAsal . "-|STAFF|_ \n" . "#" . $id . " Selesai. |TOTAL| \n" . URL::HOST_URL . "/I/i/" . $f17 . "</span>";

          if ($arrCount == $no) {
            if ($totalBayar > 0) {
              $enHapus = false;
            }
            $sisaTagihan = intval($subTotal) - $totalBayar;
            $textPoin = "";
            if (isset($arrTotalPoin[$noref]) && $arrTotalPoin[$noref] > 0) {
              $textPoin = "Poin (+" . $arrTotalPoin[$noref] . ")";
            }
            $buttonHapus = "";
            if ($enHapus == true || $this->id_privilege == 100) {
              $buttonHapus = "<button data-ref='" . $noref . "' class='hapusRef badge-danger mb-1 rounded btn-outline-danger'><i class='fas fa-trash-alt'></i></button> ";
            }
        ?>
            <!-- NOTIF -->
        <?php
            $totalBayar = 0;
            $sisaTagihan = 0;
            $no = 0;
            $subTotal = 0;
            $listPrint = "";
            $listNotif = "";
            $enHapus = true;
          }
        }

        ?>
      </tbody>
    </table>
  </div>
</div>

<form class="jq" data-operasi='' data-modal='exampleModal' action="<?= URL::BASE_URL; ?>Operan/operasiOperan" method="POST">
  <div class="modal" id="exampleModal">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selesai <b class='operasi'></b>!</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="card-body">
            <div class="form-group">
              <label for="exampleInputEmail1">Karyawan</label>
              <select name="f1" class="form-control tize form-control-sm karyawan" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                  <?php foreach ($this->user as $a) { ?>
                    <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
                <?php if (count($this->userCabang) > 0) { ?>
                  <optgroup label="---- Cabang Lain ----">
                    <?php foreach ($this->userCabang as $a) { ?>
                      <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                    <?php } ?>
                  </optgroup>
                <?php } ?>
              </select>

              <input type="hidden" class="idItem" name="f2" required>
              <input type="hidden" class="valueItem" name="f3" required>
              <input type="hidden" class="idCabang" name="idCabang" required>

              <input type="hidden" class="textNotif" name="text" required>
              <input type="hidden" class="hpNotif" name="hp" required>
            </div>

            <div class="form-group letakRAK">
              <div class="row">
                <div class="col">
                  <label>Pack</label>
                  <input type="number" min="0" value="1" name="pack" style="text-transform: uppercase" class="form-control" required>
                </div>
                <div class="col">
                  <label>Hanger</label>
                  <input type="number" min="0" value="0" name="hanger" style="text-transform: uppercase" class="form-control" required>
                </div>
              </div>
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-primary">Selesai</button>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<script>
  // ========== JS ERROR LOGGER ==========
  // Menangkap dan mengirim error ke server untuk logging
  (function() {
    const LOG_ENDPOINT = '<?= URL::BASE_URL ?>Operan/jsLog';
    
    // Helper untuk kirim log ke server
    function sendLog(type, message, url, line, column, stack) {
      try {
        fetch(LOG_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: type,
            message: message,
            url: url || window.location.href,
            line: line || 'N/A',
            column: column || 'N/A',
            stack: stack || '',
            userAgent: navigator.userAgent
          })
        }).catch(() => {}); // Silent fail
      } catch(e) {}
    }

    // Tangkap window.onerror (syntax errors, runtime errors)
    window.onerror = function(message, source, lineno, colno, error) {
      sendLog('ERROR', message, source, lineno, colno, error?.stack || '');
      return false; // Tetap tampilkan di console
    };

    // Tangkap unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
      sendLog('PROMISE_ERROR', event.reason?.message || String(event.reason), 
              window.location.href, 'N/A', 'N/A', event.reason?.stack || '');
    });

    // Override console.error
    const originalConsoleError = console.error;
    console.error = function(...args) {
      const message = args.map(arg => {
        if (typeof arg === 'object') {
          try { return JSON.stringify(arg); } catch(e) { return String(arg); }
        }
        return String(arg);
      }).join(' ');
      
      sendLog('CONSOLE_ERROR', message, window.location.href, 'N/A', 'N/A', '');
      originalConsoleError.apply(console, args);
    };

    // Override console.warn (optional)
    const originalConsoleWarn = console.warn;
    console.warn = function(...args) {
      const message = args.map(arg => {
        if (typeof arg === 'object') {
          try { return JSON.stringify(arg); } catch(e) { return String(arg); }
        }
        return String(arg);
      }).join(' ');
      
      sendLog('CONSOLE_WARN', message, window.location.href, 'N/A', 'N/A', '');
      originalConsoleWarn.apply(console, args);
    };
  })();
  // ========== END JS ERROR LOGGER ==========

  $(document).ready(function() {
    selectList();
    $("input[name=idOperan]").focus();
  });

  $("span.addOperasi").click(function() {
    var layanan = $(this).attr('data-layanan');
    $('form').attr("data-operasi", 'operasi');
    var idNya = $(this).attr('data-id');
    var valueNya = $(this).attr('data-value');
    var id_cabang = $(this).attr('data-cabang');
    $("input.idItem").val(idNya);
    $("input.valueItem").val(valueNya);
    $("input.idCabang").val(id_cabang);
    $('b.operasi').html(layanan);
    idtargetOperasi = $(this).attr('id');

    var textNya = $('span.selesai' + idNya).html();
    var hpNya = $('span.selesai' + idNya).attr('data-hp');
    $("input.textNotif").val(textNya);
    $("input.hpNotif").val(hpNya);
    idRow = idNya;

    // Buka modal menggunakan Bootstrap 5 API
    var modalEl = document.getElementById('exampleModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  });

  $("form.jq").on("submit", function(e) {
    e.preventDefault();
    var target = $(this).attr('data-operasi');
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(response) {
        // Tutup modal menggunakan Bootstrap 5 API
        var modalEl = document.getElementById('exampleModal');
        var modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
          modalInstance.hide();
        }
        
        if (response == 0) {
          loadDiv();
        } else {
          alert(response);
        }
      },
    });
  });

  function selectList() {
    $('select.operasi').select2({
      dropdownParent: $("#exampleModal"),
    });
    $('select.operasi').val("").trigger("change");
  }

  $('.modal').on('hidden.bs.modal', function() {
    selectList();
  });

  function selectList() {
    $('#exampleModal').on('show.bs.modal', function(event) {
      $('select.karyawan').select2({
        dropdownParent: $("#exampleModal"),
      });
    })
  }
</script>