<?php 
$c = $data['cetak']; 
$hasCabangSelected = isset($c['cabang']) && !empty($c['cabang']);
?>
    <div class="row mx-0 mt-2">
      <div class="col">
        <div class="card p-2" style="max-width: 500px;">
          <form class="orderProses" action="<?= URL::BASE_URL ?>PackLabel/cetak" method="POST" style="display:none;">
            <input type="hidden" name="pelanggan" id="hiddenPelanggan" value="">
          </form>
            <div class="row">
              <div class="col">
                <label>Cabang</label>
                <select id="selectCabangPL" class="form-control form-control-sm">
                  <option value="">-- Pilih Cabang --</option>
                  <?php foreach ($this->listCabang as $dc) { ?>
                    <option <?= ($hasCabangSelected && $c['cabang'] == $dc['kode_cabang'] ? "selected" : "") ?> value="<?= $dc['kode_cabang'] ?>"><?= strtoupper($dc['kode_cabang']) . " - " . $dc['nama'] ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col">
                <label>Label Pelanggan</label>
                <select id="selectPelanggan" class="form-control form-control-sm" disabled>
                  <option value="" selected>-- Pilih Cabang Terlebih Dahulu --</option>
                </select>
                <div id="loadingPelanggan" class="d-none">
                  <small class="text-muted"><i class="fas fa-spinner fa-spin"></i> Memuat data pelanggan...</small>
                </div>
              </div>
            </div>
        </div>
      </div>
    </div>

<!-- Label Container - akan diupdate via AJAX -->
<div id="labelContainer" class="<?= isset($c['pelanggan']) ? '' : 'd-none' ?>">
  <div class="row mx-0">
    <div class="col-auto px-2">
      <div class="bg-white p-2">
        <div id="print">
          <div id="labelContent" class="w-100 text-center">
            <?php if (isset($c['pelanggan'])) { ?>
            <div class="py-1">
              <b><?= $this->dCabang['nama'] ?> - <span id="labelCabang"><?= $c['cabang'] ?></span></b><br>
              <span id="labelDate"><?= date('Y-m-d H:i:s') ?></span>
            </div>
            <div class="py-1">
              <h1 class="mb-0"><b><span id="labelPelanggan"><?= strtoupper($c['pelanggan']) ?></span></b></h1>
            </div>
            <?php } ?>
          </div>
        </div>
    </div>
    </div>
    <div class="col-auto">
      <button type="button" id="btnCetakLabel" onclick="Print()" class="btn btn-sm btn-success">
        <i class="fas fa-print"></i> Cetak Label
      </button>
    </div>
  </div>
</div>

<!-- SCRIPT -->
<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>

<script>
  var selectizePelanggan = null;
  var hasCabangSelected = <?= $hasCabangSelected ? 'true' : 'false' ?>;
  var hasPelangganSelected = <?= (isset($c['pelanggan']) && !empty($c['pelanggan'])) ? 'true' : 'false' ?>;
  var isInitialLoad = true; // Flag untuk mencegah auto-submit saat load pertama
  
  $(document).ready(function() {
    // Auto-load pelanggan jika cabang sudah terpilih
    if (hasCabangSelected) {
      var selectedCabang = $('#selectCabangPL').val();
      if (selectedCabang && selectedCabang.trim() !== '') {
        loadPelanggan(selectedCabang);
      }
    }
    
    // Set flag setelah initial load selesai (dengan delay)
    setTimeout(function() {
      isInitialLoad = false;
    }, 1000);
  });

  // Event saat cabang dipilih
  $('#selectCabangPL').on('change', function() {
    var kodeCabang = $(this).val();
    if (kodeCabang) {
      loadPelanggan(kodeCabang);
    } else {
      resetPelangganDropdown();
    }
  });

  function loadPelanggan(kodeCabang) {
    console.log('loadPelanggan called with:', kodeCabang);
    
    // Show loading
    $('#loadingPelanggan').removeClass('d-none');
    $('#selectPelanggan').prop('disabled', true);
    $('#btnCek').prop('disabled', true);
    
    // Destroy selectize jika sudah ada
    if (selectizePelanggan) {
      selectizePelanggan[0].selectize.destroy();
      selectizePelanggan = null;
    }

    $.ajax({
      url: '<?= URL::BASE_URL ?>PackLabel/getPelangganByCabang',
      type: 'POST',
      data: { kode_cabang: kodeCabang },
      dataType: 'json',
      success: function(response) {
        console.log('AJAX response:', response);
        $('#loadingPelanggan').addClass('d-none');
        
        if (response.success) {
          var options = '<option value="">-- Pilih Pelanggan --</option>';
          var selectedPelanggan = '<?= isset($c['pelanggan']) ? strtoupper($c['pelanggan']) : '' ?>';
          
          response.data.forEach(function(item) {
            var isSelected = (item.text === selectedPelanggan) ? 'selected' : '';
            options += '<option value="' + item.value + '" ' + isSelected + '>' + item.text + '</option>';
          });
          
          $('#selectPelanggan').html(options);
          $('#selectPelanggan').prop('disabled', false);
          
          // Apply selectize
          selectizePelanggan = $('#selectPelanggan').selectize({
            placeholder: '-- Pilih Pelanggan --'
          });

        } else {
          console.error('AJAX error response:', response.message);
          alert(response.message || 'Gagal memuat data pelanggan');
          resetPelangganDropdown();
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error:', status, error);
        console.error('Response:', xhr.responseText);
        $('#loadingPelanggan').addClass('d-none');
        alert('Terjadi kesalahan saat memuat data pelanggan');
        resetPelangganDropdown();
      }
    });
  }

  function resetPelangganDropdown() {
    // Destroy selectize jika sudah ada
    if (selectizePelanggan) {
      selectizePelanggan[0].selectize.destroy();
      selectizePelanggan = null;
    }
    
    $('#selectPelanggan').html('<option value="">-- Pilih Cabang Terlebih Dahulu --</option>');
    $('#selectPelanggan').prop('disabled', true);
  }

  // Update label saat pelanggan dipilih (tanpa reload halaman)
  $(document).on('change', '#selectPelanggan', function() {
    var selectedValue = $(this).val();
    if (selectedValue && !isInitialLoad) {
      // Parse value: NAMA_PELANGGAN_EXP_KODE_CABANG
      var parts = selectedValue.split('_EXP_');
      var namaPelanggan = parts[0];
      var kodeCabang = parts[1];
      
      // Update label content
      updateLabel(namaPelanggan, kodeCabang);
    }
  });
  
  function updateLabel(pelanggan, cabang) {
    // Format tanggal
    var now = new Date();
    var dateStr = now.getFullYear() + '-' + 
                  String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                  String(now.getDate()).padStart(2, '0') + ' ' +
                  String(now.getHours()).padStart(2, '0') + ':' +
                  String(now.getMinutes()).padStart(2, '0') + ':' +
                  String(now.getSeconds()).padStart(2, '0');
    
    // Generate label HTML (sama dengan format PHP)
    var labelHtml = `
      <tr>
        <td>
          <b><?= $this->dCabang['nama'] ?> - ${cabang}</b><br>
          ${dateStr}
        </td>
      </tr>
      <tr id="dashRow">
        <td></td>
      </tr>
      <tr>
        <td>
          <h1><b>${pelanggan}</b></h1>
        </td>
      </tr>
      <tr id="dashRow">
        <td></td>
      </tr>
      <tr>
        <td>
          <?= URL::PACK_ROWS ?>
        </td>
      </tr>
    `;
    
    // Update DOM
    $('#labelContent').html(labelHtml);
    $('#labelContainer').removeClass('d-none');
  }

  function Print() {
    var btn = document.getElementById('btnCetakLabel');
    
    // Start loading
    if (btn) {
      btn.classList.add('disabled');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencetak...';
    }

    var el = document.getElementById("print");
    var rows = el.querySelectorAll("tr");
    var lines = [];
    var pmode = "server";
    var width = parseInt(localStorage.getItem("escpos_width") || "32");
    if (!width || isNaN(width)) width = 32;

    var makeDash = function(w) {
      return Array(w + 1).join("-");
    };

    var sanitizeServerTd = function(td) {
      try {
        var s = td.innerHTML || "";
        // Convert br first to placeholder
        s = s.replace(/<br\s*\/?>/gi, "[[BR]]");
        // Convert h1, b, del to placeholders
        s = s.replace(/<h1[^>]*>/gi, "[[H1]]").replace(/<\/h1>/gi, "[[/H1]]");
        s = s.replace(/<b[^>]*>/gi, "[[B]]").replace(/<\/b>/gi, "[[/B]]");
        s = s.replace(/<del[^>]*>/gi, "[[DEL]]").replace(/<\/del>/gi, "[[/DEL]]");
        s = s.replace(/&nbsp;/gi, " ");
        s = s.replace(/\u00a0/g, " ");
        // Remove all other HTML tags
        s = s.replace(/<[^>]+>/gi, "");
        s = s.replace(/[\r\n]+/g, " ");
        s = s.replace(/[ \t]+/g, " ").trim();
        return s;
      } catch (e) {
        return "";
      }
    };

    var escLine = function(left, right, width) {
      var token = /\[\[(?:\/)?(?:B|DEL|H1|C|R|L|TD)\]\]/g;
      var rawL = (left || "").replace(/[ \t]+/g, " ").trim();
      var rawR = (right || "").replace(/[ \t]+/g, " ").trim();
      var plainL = rawL.replace(token, "");
      var plainR = rawR.replace(token, "");
      if (pmode === "server") {
        var out = "";
        if (plainL.length > 0) out += "[[TD]]" + rawL + "[[/TD]]";
        if (plainR.length > 0) out += "[[TD]]" + rawR + "[[/TD]]";
        return out;
      }
      var space = width - plainL.length - plainR.length;
      if (space < 1) space = 1;
      return rawL + Array(space + 1).join(" ") + rawR;
    };

    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var tds = tr.querySelectorAll("td");
      
      if (tr.id && tr.id.toLowerCase() === "dashrow") {
        var dash = makeDash(width);
        lines.push("[[TR]][[TD]]" + dash + "[[/TD]][[/TR]]");
        continue;
      }

      if (tds.length === 0) continue;

      if (tds.length === 1 || tds[0].getAttribute("colspan") === "2") {
        var v = sanitizeServerTd(tds[0]);
        v = "[[TD]]" + v + "[[/TD]]";
        lines.push("[[TR]]" + v + "[[/TR]]");
      } else if (tds.length >= 2) {
        var left0 = sanitizeServerTd(tds[0]);
        var right0 = sanitizeServerTd(tds[1]);
        var row0 = escLine(left0, right0, width);
        lines.push("[[TR]]" + row0 + "[[/TR]]");
      }
    }

    // Filter empty rows
    lines = lines.filter(function(s) {
      var x = String(s || "");
      if (x.indexOf("[[TR]]") === -1) return true;
      var inner = x.replace(/\[\[(?:\/)?(?:TR|TD)\]\]/g, "");
      return inner.trim().length > 0;
    });

    // Convert to HTML
    var plain = lines.map(function(s) {
      s = String(s || "");
      s = s.replace(/\[\[BR\]\]/g, "<br>");
      s = s.replace(/\[\[B\]\]/g, "<b>");
      s = s.replace(/\[\[\/B\]\]/g, "</b>");
      s = s.replace(/\[\[H1\]\]/g, "<h1>");
      s = s.replace(/\[\[\/H1\]\]/g, "</h1>");
      s = s.replace(/\[\[(?:\/)?C\]\]/g, "");
      s = s.replace(/\[\[(?:\/)?R\]\]/g, "");
      s = s.replace(/\[\[(?:\/)?L\]\]/g, "");
      s = s.replace(/\[\[TD\]\]/g, "<td>");
      s = s.replace(/\[\[\/TD\]\]/g, "</td>");
      s = s.replace(/\[\[TR\]\]/g, "<tr>");
      s = s.replace(/\[\[\/TR\]\]/g, "</tr>");
      s = s.replace(/\[\[(?:\/)?DEL\]\]/g, "");
      return s;
    }).join("");

    // Send to print server
    fetch("http://localhost:3000/print", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        text: plain,
        margin_top: 0,
        feed_lines: 3
      })
    })
    .then(function(res) {
      console.log("Print server status:", res.status);
      return res.text().catch(function() { return ""; });
    })
    .then(function(body) {
      console.log("Print server body:", body);
    })
    .catch(function(err) {
      console.error("Print server error:", err);
      // Fallback to browser print
      var divContents = el.innerHTML;
      var a = window.open('');
      a.document.write('<html>');
      a.document.write('<title>Print Page</title>');
      a.document.write('<body>');
      a.document.write(divContents);
      a.document.write('</body></html>');
      a.print();
      var window_width = $(window).width();
      if (window_width > 600) {
        a.close();
      } else {
        setTimeout(function() { a.close(); }, 60000);
      }
    })
    .finally(function() {
      // End loading
      if (btn) {
        btn.classList.remove('disabled');
        btn.innerHTML = 'Cetak Label';
      }
    });
  }
</script>