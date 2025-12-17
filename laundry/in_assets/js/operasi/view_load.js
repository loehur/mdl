// View Load JavaScript - Laundry Management System
// Separated from view_load.php for better maintainability

(function () {
  "use strict";

  // Unbind global events to prevent duplication before rebinding happens naturally or if we skip
  // We MUST unbind ALL global document handlers defined in this file
  $(document).off("click", ".tokopayOrder");
  $(document).off("click", "[data-print-ref]");
  $(document).off("click", "[data-print-id]");
  $(document).off("click", "[data-print-qr]");
  $(document).off("click", "#btnPrintQR");
  $(document).off("click", "#btnCekStatusQR");

  // Cleanup orphaned modals that were moved to body in previous executions
  // This prevents Duplicate ID errors and Bootstrap confusion which causes recursive errors
  $("body > #modalAlert").remove();
  $("body > #modalQR").remove();

  if (window.viewLoadJsLoaded) {
    // Already loaded, and we just unbound everything. 
    // We will re-bind below as the script execution continues.
  }
  window.viewLoadJsLoaded = true;

  // Global variables
  window.noref = "";
  window.json_rekap = [];
  window.totalBill = 0;
  window.idNya = 0;
  window.diBayar = 0;
  window.idtargetOperasi = 0;
  window.totalNotif = "";
  var klikNotif = 0;
  var userClick = "";
  var click = 0;

  // Fungsi untuk menampilkan alert modal profesional
  window.showAlert = function (message, type) {
    type = type || "info"; // info, success, warning, error
    var iconClass = "fa-info-circle text-primary";
    var title = "Informasi";

    if (type === "success") {
      iconClass = "fa-check-circle text-success";
      title = "Berhasil";
    } else if (type === "warning") {
      iconClass = "fa-exclamation-triangle text-warning";
      title = "Peringatan";
    } else if (type === "error") {
      iconClass = "fa-times-circle text-danger";
      title = "Error";
    }

    try {
      var modalEl = document.getElementById("modalAlert");

      // If we found the one in the body (orphaned) but we have a new one in the DOM, prefer the new one?
      // Actually, we cleaned up body > #modalAlert at the top. 
      // So document.getElementById should find the one inside the new HTML.

      // Cek apakah modal element ada
      if (!modalEl) {
        alert(message);
        return;
      }

      // Pastikan Bootstrap 5 tersedia
      if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
        alert(message);
        return;
      }

      // Pindahkan modal ke body untuk menghindari masalah z-index/overflow
      if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
      }

      // Set content modal
      $("#modalAlertIcon").attr("class", "fas " + iconClass);
      $("#modalAlertTitle").text(title);
      $("#modalAlertMessage").css("white-space", "pre-wrap").text(message);

      // Tutup modal yang mungkin sedang terbuka (prevent backdrop sticking)
      var existingModal = bootstrap.Modal.getInstance(modalEl);
      if (existingModal) {
        existingModal.show();
      } else {
        var newModal = new bootstrap.Modal(modalEl);
        newModal.show();
      }

    } catch (e) {
      alert(message);
    }
  };

  // Copy to clipboard helper
  window.copyToClipboard = function (text, btn) {
    navigator.clipboard.writeText(text).then(function () {
      var originalHtml = $(btn).html();
      $(btn).html('<i class="fas fa-check"></i>');
      $(btn).removeClass('btn-outline-secondary btn-outline-danger').addClass('btn-success');
      setTimeout(function () {
        $(btn).html(originalHtml);
        $(btn).removeClass('btn-success').addClass(originalHtml.includes('danger') ? 'btn-outline-danger' : 'btn-outline-secondary');
      }, 1500);
    }).catch(function () {
      alert('Gagal menyalin. Silakan copy manual.');
    });
  };

  // Inisialisasi konfigurasi dari window.ViewLoadConfig (akan diset dari PHP)
  var config = window.ViewLoadConfig || {};
  var BASE_URL = config.baseUrl || "";
  var modeView = config.modeView || "0";
  var id_pelanggan = config.idPelanggan || "";
  var marginTop = config.marginTop || 0;
  var feedLines = config.feedLines || 0;

  $(document).ready(function () {
    clearTuntas();
    $("tr#nTunaiBill").hide();
    $("#noteBill").prop("required", false);
    $("select.tize").selectize();
    window.totalBill = $("span#totalBill").attr("data-total");
    if (config.loadRekap) {
      window.json_rekap = [config.loadRekap];
    }
    try {
      var sumRekap = 0;
      var lr = config.loadRekap || {};
      for (var k in lr) {
        if (!Object.prototype.hasOwnProperty.call(lr, k)) continue;
        var v = parseInt(lr[k] || 0);
        if (!isNaN(v)) sumRekap += v;
      }
      if (sumRekap <= 0) {
        $("#btnModalLoadRekap").addClass("d-none");
      }
    } catch (e) { }

    // Event delegation untuk tombol print content
    $(document).on("click", "[data-print-ref]", function (e) {
      e.preventDefault();
      var btn = e.currentTarget;
      var id = $(btn).attr("data-print-ref");
      var idPelanggan = $(btn).attr("data-print-pelanggan");
      if (id) {
        window.PrintContentRef(id, idPelanggan, btn);
      }
    });

    // Event delegation untuk tombol print dengan ID
    $(document).on("click", "[data-print-id]", function (e) {
      e.preventDefault();
      var btn = e.currentTarget;
      var id = $(btn).attr("data-print-id");
      if (id) {
        window.Print(id, btn);
      }
    });

    // Event delegation untuk tombol print QR
    $(document).on("click", "[data-print-qr]", function (e) {
      e.preventDefault();
      var btn = e.currentTarget;
      var data = $(btn).attr("data-print-qr");
      var text = $(btn).attr("data-print-text") || "";
      if (data) {
        window.PrintQR(data, text, btn);
      }
    });

    // Store current QR data for printing and status check
    window.currentQRData = {
      qrString: "",
      total: 0,
      nama: "",
      ref_id: ""
    };

    window.showQR = function (text, total, nama, isDev, devRes, ref_id) {
      var modalEl = document.getElementById("modalQR");
      if (!modalEl) return;

      // Store QR data
      var fmtTotal = new Intl.NumberFormat('id-ID').format(total);
      var customerName = $("select[name=pelanggan] option:selected").text().split("|")[0].trim() || nama;
      window.currentQRData = {
        qrString: text,
        total: fmtTotal,
        nama: customerName,
        ref_id: ref_id
      };

      // Clear previous QR
      document.getElementById("qrcode").innerHTML = "";

      // Generate QR
      try {
        new QRCode(document.getElementById("qrcode"), {
          text: text,
          width: 200,
          height: 200
        });
      } catch (e) {
        document.getElementById("qrcode").innerText = "Error loading QR Lib";
      }

      // Set Text
      var fmtTotal = new Intl.NumberFormat('id-ID').format(total);
      $("#qrTotal").text("Rp " + fmtTotal);

      // Try to find customer name from page if passed 'nama' is just generic
      var customerName = $("select[name=pelanggan] option:selected").text().split("|")[0].trim() || nama;
      $("#qrNama").text(customerName);

      // Dev Mode Handling
      if (isDev) {
        $("#devModeLabel").removeClass("d-none");
        var apiResText = typeof devRes === 'object' ? JSON.stringify(devRes, null, 2) : devRes;
        $("#devApiRes").text(apiResText);
      } else {
        $("#devModeLabel").addClass("d-none");
      }

      // Send QR data to QR Client Server (only for real QR, not dev mode)
      // This is optional - if server is unavailable, we silently ignore
      var kodeCabang = config.kodeCabang || "";
      if (!isDev && kodeCabang && text) {
        fetch("https://qrs.nalju.com/send-qr", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            kasir_id: kodeCabang,
            qr_string: text,
            text: customerName + "<br>Rp" + fmtTotal
          })
        })
          .then(function (res) {
            if (res.ok) {
              console.log("QR Display: sent");
            }
          })
          .catch(function () {
            // Silently ignore - QR display server is optional
          });
      }

      // Show Modal
      try {
        // Move modal to body to avoid z-index/overflow issues (same fix as modalAlert)
        if (modalEl.parentNode !== document.body) {
          document.body.appendChild(modalEl);
        }

        if (window.bootstrap && bootstrap.Modal) {
          var mFn = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          mFn.show();

          // Force backdrop z-index after a short delay
          setTimeout(function () {
            $(".modal-backdrop").css("z-index", "10049");
          }, 50);
        }
      } catch (e) { }
    }

    // Print QR button handler
    $(document).off("click", "#btnPrintQR").on("click", "#btnPrintQR", function (e) {
      e.preventDefault();
      var btn = this;
      var data = window.currentQRData;
      if (data && data.qrString) {
        var printText = "Rp" + data.total + "\n" + data.nama;

        // Disable button while printing
        $(btn).addClass('disabled').prop('disabled', true);
        $(btn).html('<i class="fas fa-spinner fa-spin"></i> Printing...');

        // POST to print server
        fetch("http://localhost:3000/printqr", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            qr_string: data.qrString,
            text: printText,
            margin_top: marginTop,
            feed_lines: feedLines
          })
        })
          .then(function (res) {
            console.log("Print server response:", res.status);
            return res.text().catch(function () { return ""; });
          })
          .then(function (body) {
            console.log("Print server body:", body);
          })
          .catch(function (err) {
            console.error("Print server error:", err);
            showAlert("Gagal mengirim ke printer: " + err.message, "error");
          })
          .finally(function () {
            $(btn).removeClass('disabled').prop('disabled', false);
            $(btn).html('<i class="fas fa-print"></i> Print');
          });
      } else {
        showAlert("Tidak ada QR code untuk dicetak", "warning");
      }
    });

    // Cek Status QR button handler
    $(document).off("click", "#btnCekStatusQR").on("click", "#btnCekStatusQR", function (e) {
      e.preventDefault();
      var btn = this;
      var data = window.currentQRData;

      if (!data || !data.ref_id) {
        showAlert("Data Transaksi tidak ditemukan di modal ini.", "error");
        return;
      }

      var ref = data.ref_id;
      var originalHtml = $(btn).html();

      $.ajax({
        url: BASE_URL + "Operasi/payment_gateway_check_status/" + ref,
        type: "GET",
        beforeSend: function () {
          $(btn).addClass('disabled').prop('disabled', true);
          $(btn).html('<i class="fas fa-spinner fa-spin"></i> Checking...');
        },
        success: function (response) {
          var res = response;
          if (typeof response === 'string') {
            try {
              res = JSON.parse(response);
            } catch (e) { }
          }

          if (res.status === 'PAID') {
            // Update UI
            $("#qrcode").html('<div class="text-success text-center"><i class="fas fa-check-circle fa-5x"></i><h3 class="mt-2">LUNAS/PAID</h3></div>');
            $(btn).removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-check"></i> PAID');

            // Reload after 2 seconds
            setTimeout(function () {
              var modalEl = document.getElementById("modalQR");
              if (modalEl && window.bootstrap && bootstrap.Modal) {
                var mFn = bootstrap.Modal.getInstance(modalEl);
                if (mFn) mFn.hide();
              }

              if (typeof load_data_operasi === 'function' && id_pelanggan) {
                load_data_operasi(id_pelanggan);
              } else if (typeof loadDiv === 'function') {
                loadDiv();
              } else {
                location.reload();
              }
            }, 3000);

          } else {
            showAlert("Status: " + (res.status || "Unknown") + "\nSilahkan cek ulang beberapa saat lagi.", "info");
            $(btn).html(originalHtml);
            $(btn).removeClass('disabled').prop('disabled', false);
          }
        },
        error: function (xhr, status, error) {
          showAlert("Gagal mengecek status: " + error, "error");
          $(btn).html(originalHtml);
          $(btn).removeClass('disabled').prop('disabled', false);
        }
      });
    });

    // Event delegation untuk tombol tokopay order dengan validasi QRIS
    $(document).off("click", ".tokopayOrder").on("click", ".tokopayOrder", function (e) {
      e.preventDefault();
      var btn = e.currentTarget;
      var ref = $(btn).attr("data-ref");
      var total = $(btn).attr("data-total");
      var note = $(btn).attr("data-note");

      // Validasi: hanya proses jika note = "QRIS"
      if (note && note.toUpperCase() === "QRIS") {
        var url = BASE_URL + "Operasi/payment_gateway_order/" + ref + "?nominal=" + total + "&metode=" + encodeURIComponent(note);

        // Save original button text
        var originalBtnHtml = $(btn).html();

        $.ajax({
          url: url,
          type: "GET",
          beforeSend: function () {
            $(btn).addClass('disabled').prop('disabled', true);
            $(btn).html('<i class="fas fa-spinner fa-spin"></i>');
          },
          success: function (response) {
            // Try to parse JSON if it's a string
            var res = response;
            if (typeof response === 'string') {
              try {
                res = JSON.parse(response);
              } catch (e) {
                // Response is not JSON, treat as plain text/error code
                res = { raw: response };
              }
            }

            // Handle response
            if (res.status === 'paid') {
              if (typeof load_data_operasi === 'function' && id_pelanggan) {
                load_data_operasi(id_pelanggan);
              } else if (typeof loadDiv === 'function') {
                loadDiv();
              } else {
                location.reload();
              }
              return;
            }

            var qrString = res.qr_string;

            if (qrString) {
              // Scenario 1: Real QR String
              showQR(qrString, total, "Customer", false, null, ref);
            } else {
              // Scenario 2: Fallback (Random QR) - Always show if real QR missing
              var randomQR = Array(241).join((Math.random().toString(36) + '00000000000000000').slice(2, 18)).slice(0, 240);
              showQR(randomQR, total, "Customer", true, res, ref);
            }
          },
          error: function (xhr, status, error) {
            // Fallback on error
            var randomQR = Array(241).join((Math.random().toString(36) + '00000000000000000').slice(2, 18)).slice(0, 240);
            showQR(randomQR, total, "Customer", true, { error: error, status: status }, ref);
          },
          complete: function () {
            $(btn).removeClass('disabled').prop('disabled', false);
            $(btn).html(originalBtnHtml);
          }
        });

      } else {
        // Non-QRIS Guide - Display detailed payment guide in modal
        var guides = config.nonTunaiGuide || {};
        var guideData = guides[note];

        if (guideData) {
          var totalFmt = new Intl.NumberFormat('id-ID').format(total);

          var html = '<div class="text-center">';

          if (guideData && typeof guideData === 'object') {
            html += '<h5 class="text-primary fw-bold mb-3">' + (guideData.label || note) + '</h5>';
            html += '<div class="bg-light rounded p-3 mb-3">';
            html += '<p class="mb-1 text-muted small">Nomor Rekening:</p>';
            html += '<div class="d-flex align-items-center justify-content-center gap-2">';
            html += '<h4 class="fw-bold mb-0" style="letter-spacing: 2px;">' + (guideData.number || '-') + '</h4>';
            html += '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard(\'' + (guideData.number || '') + '\', this)"><i class="fas fa-copy"></i></button>';
            html += '</div>';
            html += '<p class="mb-0 text-muted mt-2">a.n. <strong>' + (guideData.name || '-') + '</strong></p>';
            html += '</div>';
          } else {
            html += '<p class="mb-3">' + (guideData || 'Silakan lakukan pembayaran ke rekening terkait.') + '</p>';
          }

          html += '<div class="d-flex align-items-center justify-content-center gap-2 my-3">';
          html += '<h3 class="fw-bold text-danger mb-0">Rp' + totalFmt + '</h3>';
          html += '<button type="button" class="btn btn-sm btn-outline-danger" onclick="copyToClipboard(\'' + total + '\', this)"><i class="fas fa-copy"></i></button>';
          html += '</div>';
          html += '<div class="alert alert-warning small mb-0"><i class="fas fa-exclamation-triangle"></i> Pastikan nominal transfer sama dan tepat hingga digit terakhir</div>';
          html += '</div>';

          $('#modalAlertMessage').html(html);
          $('#modalAlertTitle').text('Panduan Pembayaran');
          $('#modalAlertIcon').attr('class', 'fas fa-info-circle text-primary');

          var modalEl = document.getElementById('modalAlert');
          if (modalEl) {
            if (modalEl.parentNode !== document.body) {
              document.body.appendChild(modalEl);
            }
            var alertModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            alertModal.show();
          }
        } else {
          showAlert("Fitur ini hanya tersedia untuk pembayaran QRIS", "warning");
        }
      }
    });
  });

  $(".hoverBill").hover(
    function () {
      $(this).addClass("bg-light");
    },
    function () {
      $(this).removeClass("bg-light");
    }
  );

  $("span.nonTunaiMetod").click(function () {
    $("input[name=noteBayar]").val($(this).html());
    $("input[name=noteBill]").val($(this).html());
  });

  function clearTuntas() {
    if (config.arrTuntas && config.arrTuntas.length > 0) {
      $.ajax({
        url: BASE_URL + "Antrian/clearTuntas",
        data: {
          data: config.arrTuntasSerial,
        },
        type: "POST",
        success: function (response) {
          loadDiv();
        },
      });
    }
  }

  $("form.ajax").on("submit", function (e) {
    e.preventDefault();
    $.ajax({
      url: $(this).attr("action"),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res == 0) {
          try {
            var offcanvasEl = document.getElementById("offcanvasPayment");
            if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
              var instance = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
              instance.hide();
            }
          } catch (e) { }

          try {
            var mEl = document.querySelector(".modal.show");
            if (mEl && window.bootstrap && bootstrap.Modal) {
              var instance = bootstrap.Modal.getInstance(mEl) || new bootstrap.Modal(mEl);
              instance.hide();
            }
          } catch (e) { }

          // Robust cleanup with delay to override Bootstrap race conditions
          setTimeout(function () {
            try {
              $(".modal-backdrop").remove();
              $(".offcanvas-backdrop").remove();
              $("body").removeClass("modal-open offcanvas-open").removeAttr("style").css({ overflow: "auto", "padding-right": "0" });
            } catch (e) { }
          }, 300); // 300ms delay matches bootstrap transition

          if (typeof hide_modal === "function") {
            hide_modal();
          }
          loadDiv();
        } else {
          showAlert(res, "error");
        }
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  });

  $("form.ajax_json").on("submit", function (e) {
    e.preventDefault();

    var karyawanBill = $("#karyawanBill").val();
    var metodeBill = $("#metodeBill").val();
    var noteBill = $("#noteBill").val();

    noteBill = (noteBill || "").replace(" ", "_SPACE_");

    $.ajax({
      url:
        BASE_URL +
        "Operasi/bayarMulti/" +
        karyawanBill +
        "/" +
        id_pelanggan +
        "/" +
        metodeBill +
        "/" +
        noteBill,
      data: {
        rekap: window.json_rekap,
        dibayar: $("input#bayarBill").val(),
      },
      type: $(this).attr("method"),
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
        $("#btnBayarBill").prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
      },
      success: function (res) {
        if (res == 0) {
          try {
            var offcanvasEl = document.getElementById("offcanvasPayment");
            if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
              var instance = bootstrap.Offcanvas.getInstance(offcanvasEl);
              if (instance) instance.hide();
            }
          } catch (e) { }

          try {
            // Robust cleanup with delay to override Bootstrap race conditions
            setTimeout(function () {
              $(".modal-backdrop").remove();
              $(".offcanvas-backdrop").remove();
              $("body").removeClass("modal-open offcanvas-open").removeAttr("style").css({ overflow: "auto", "padding-right": "0" });
            }, 300);
          } catch (e) { }

          if (typeof hide_modal === "function") {
            try {
              hide_modal();
            } catch (e) { }
          }
          loadDiv();
        } else {
          // Check for specific "lock" error or if we are in the offcanvasPayment
          var alertEl = $("#alertRecap");
          if (alertEl.length > 0 && $("#offcanvasPayment").hasClass("show")) {
            alertEl.removeClass("d-none").html(res);
            // Optional: Shake effect or focus
            alertEl.hide().fadeIn();
          } else {
            showAlert(res, "error");
          }
        }
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
        $("#btnBayarBill").prop("disabled", false).html("Bayar");
      },
    });
  });

  $("span.addOperasi").on("click", function (e) {
    e.preventDefault();
    $("div.letakRAK").hide();
    $("input#letakRAK").prop("required", false);

    window.idNya = $(this).attr("data-id");
    var valueNya = $(this).attr("data-value");
    var layanan = $(this).attr("data-layanan");
    $("input.idItem").val(window.idNya);
    $("input.valueItem").val(valueNya);
    $("b.operasi").html(layanan);
    window.idtargetOperasi = $(this).attr("id");

    var ref_ini = $(this).attr("data-ref");
    var totalNotif = $("span#textTotal" + ref_ini).html();
    $("input[name=inTotalNotif]").val(totalNotif);

    var textNya = $("span.selesai" + window.idNya).html();
    var hpNya = $("span.selesai" + window.idNya).attr("data-hp");
    $("input.textNotif").val(textNya);
    $("input.hpNotif").val(hpNya);
  });

  $("span.gantiOperasi").on("click", function (e) {
    e.preventDefault();
    window.idNya = $(this).attr("data-id");
    var awal = $(this).attr("data-awal");
    $("input#id_ganti").val(window.idNya);
    $("span#awalOP").html(awal);
  });

  $("span.endLayanan").on("click", function (e) {
    e.preventDefault();
    $("div.letakRAK").show();
    $("input#letakRAK").prop("required", true);
    $("form.operasi").attr("data-operasi", "operasiSelesai");
    window.idNya = $(this).attr("data-id");
    var valueNya = $(this).attr("data-value");
    var layanan = $(this).attr("data-layanan");
    window.noref = $(this).attr("data-ref");
    $("input.idItem").val(window.idNya);
    $("input.valueItem").val(valueNya);
    $("b.operasi").html(layanan);
    window.idtargetOperasi = $(this).attr("id");

    var textNya = $("span.selesai" + window.idNya).html();
    var hpNya = $("span.selesai" + window.idNya).attr("data-hp");
    $("input.textNotif").val(textNya);
    $("input.hpNotif").val(hpNya);

    var ref_ini = $(this).attr("data-ref");
    var totalNotif = $("span#textTotal" + ref_ini).html();
    $("input[name=inTotalNotif]").val(totalNotif);
  });

  $(".tambahCas").click(function () {
    window.noref = $(this).attr("data-ref");
    window.idNya = $(this).attr("data-tr");
    $("#" + window.idNya).val(window.noref);
  });

  // --- Logika Modal Hapus Order ---

  // Fungsi untuk membuka modal
  window.bukaModalHapus = function (ref) {
    var modal = $('#modalHapusOrderInline');
    if (modal.length > 0) {
      // Pindahkan ke body agar z-index benar (jika belum)
      if (modal.parent()[0] !== document.body) {
        modal.appendTo('body');
      }

      $('#hapusRefText').text('#' + ref);
      $('#inputAlasanHapus').val('').css('borderColor', '#ccc');
      $('#btnHapusKonfirm').data('ref', ref);

      modal.show();

      setTimeout(function () {
        $('#inputAlasanHapus').focus();
      }, 100);
    } else {
      console.error("Modal #modalHapusOrderInline tidak ditemukan!");
    }
  };

  // Event handler tombol hapus
  $(document).on("click", "a.hapusRef", function (e) {
    e.preventDefault();
    var ref = $(this).attr("data-ref");
    bukaModalHapus(ref);
  });

  // Event handler tutup modal
  $(document).on('click', '.tutupModalHapusBtn', function () {
    $('#modalHapusOrderInline').hide();
  });

  // Event handler konfirmasi hapus
  $(document).on('click', '#btnHapusKonfirm', function () {
    var ref = $(this).data('ref');
    var note = $('#inputAlasanHapus').val().trim();

    if (note.length === 0) {
      $('#inputAlasanHapus').css('borderColor', '#dc3545').focus();
      return;
    }

    var btn = $(this);
    var oldHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
      url: BASE_URL + "Antrian/hapusRef",
      data: {
        ref: ref,
        note: note,
      },
      type: "POST",
      success: function (response) {
        $('#modalHapusOrderInline').hide();
        loadDiv();
      },
      error: function () {
        alert("Gagal menghapus via network");
      },
      complete: function () {
        btn.prop('disabled', false).html(oldHtml);
      }
    });
  });
  // --- Akhir Logika Modal Hapus Order ---

  $("a.ambil").on("click", function (e) {
    e.preventDefault();
    window.idNya = $(this).attr("data-id");
    $("input.idItem").val(window.idNya);
  });

  $("a.sendNotif").on("click", function (e) {
    klikNotif += 1;
    if (klikNotif > 1) {
      return;
    }
    $(this).fadeOut("slow");
    e.preventDefault();
    var urutRef = $(this).attr("data-urutRef");
    var id_pelanggan_notif = $(this).attr("data-idPelanggan");
    var id_harga = $(this).attr("data-id_harga");
    var hpNya = $(this).attr("data-hp");
    var refNya = $(this).attr("data-ref");
    var timeNya = $(this).attr("data-time");
    var textNya = $("span#" + urutRef).html();
    var countMember = $("span#member" + urutRef).html();

    // Fallback: jika refNya kosong atau undefined, gunakan urutRef
    if (!refNya || refNya == '' || refNya == '0' || refNya == 'undefined') {
      refNya = urutRef;
    }

    $.ajax({
      url: BASE_URL + "Antrian/sendNotif/" + countMember + "/1",
      data: {
        hp: hpNya,
        text: textNya,
        id_harga: id_harga,
        ref: refNya,
        time: timeNya,
        idPelanggan: id_pelanggan_notif,
      },
      type: "POST",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res == 0) {
          loadDiv();
        } else {
          showAlert(res, "error");
        }
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  });

  $("a.sendNotifMember").on("click", function (e) {
    klikNotif += 1;
    if (klikNotif > 1) {
      return;
    }
    $(this).fadeOut("slow");
    e.preventDefault();
    var refNya = $(this).attr("data-ref");
    $.ajax({
      url: BASE_URL + "Member/sendNotifDeposit/" + refNya,
      data: {},
      type: "POST",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function () {
        loadDiv();
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  });

  $("a.bayarPasMulti").on("click", function (e) {
    $("input#bayarBill").val(window.totalBill);
    bayarBill();
  });

  $("select.metodeBayarBill").on("keyup change", function () {
    if ($(this).val() == 2) {
      $("tr#nTunaiBill").show();
      $("#noteBill").prop("required", true);
    } else {
      $("tr#nTunaiBill").hide();
      $("#noteBill").prop("required", false);
    }
  });

  $("select.userChange").change(function () {
    userClick = $("select.userChange option:selected").text();
  });

  $("span.editRak").on("click", function () {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var ref_ini = $(this).attr("data-ref");
    var totalNotif = $("span#textTotal" + ref_ini).html();

    var id_value = $(this).attr("data-id");
    var value = $(this).attr("data-value");
    var value_before = value;
    var span = $(this);
    var valHtml = $(this).html();
    span.html(
      "<input type='text' maxLength='2' id='value_' style='text-align:center;width:30px' value='" +
      value.toUpperCase() +
      "'>"
    );

    $("#value_").focus();

    $("#value_").focusout(function () {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: BASE_URL + "Antrian/updateRak/",
          data: {
            id: id_value,
            value: value_after,
            totalNotif: totalNotif,
          },
          type: "POST",
          beforeSend: function () {
            $(".loaderDiv").fadeIn("fast");
          },
          success: function () {
            span.html(value_after.toUpperCase());
            span.attr("data-value", value_after.toUpperCase());
            click = 0;
          },
          complete: function () {
            $(".loaderDiv").fadeOut("slow");
          },
        });
      }
    });
  });

  $("span.editPack").on("click", function () {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var id_value = $(this).attr("data-id");
    var value = $(this).attr("data-value");
    var value_before = value;
    var span = $(this);
    var valHtml = $(this).html();
    span.html(
      "<input type='number' min='0' id='value_' style='text-align:center;width:45px' value='" +
      value +
      "'>"
    );

    $("#value_").focus();
    $("#value_").focusout(function () {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: BASE_URL + "Antrian/updateRak/1",
          data: {
            id: id_value,
            value: value_after,
          },
          type: "POST",
          beforeSend: function () {
            $(".loaderDiv").fadeIn("fast");
          },
          success: function () {
            loadDiv();
          },
          complete: function () {
            $(".loaderDiv").fadeOut("slow");
          },
        });
      }
    });
  });

  $("span.editHanger").on("click", function () {
    click = click + 1;
    if (click != 1) {
      return;
    }

    var id_value = $(this).attr("data-id");
    var value = $(this).attr("data-value");
    var value_before = value;
    var span = $(this);
    var valHtml = $(this).html();
    span.html(
      "<input type='number' min='0' id='value_' style='text-align:center;width:45px' value='" +
      value +
      "'>"
    );

    $("#value_").focus();
    $("#value_").focusout(function () {
      var value_after = $(this).val();
      if (value_after === value_before) {
        span.html(valHtml);
        click = 0;
      } else {
        $.ajax({
          url: BASE_URL + "Antrian/updateRak/2",
          data: {
            id: id_value,
            value: value_after,
          },
          type: "POST",
          beforeSend: function () {
            $(".loaderDiv").fadeIn("fast");
          },
          success: function () {
            loadDiv();
          },
          complete: function () {
            $(".loaderDiv").fadeOut("slow");
          },
        });
      }
    });
  });

  window.PrintContentRef = function (id, idPelanggan, btn) {
    var countMember = $("span#member" + id).html();
    if (countMember > 0) {
      $.ajax({
        url: BASE_URL + "Member/textSaldo",
        data: {
          id: idPelanggan,
        },
        type: "POST",
        success: function (result) {
          $("td.textMember" + id).html(result);
          if (window.requestAnimationFrame) {
            requestAnimationFrame(function () {
              requestAnimationFrame(function () {
                Print(id, btn);
              });
            });
          } else {
            setTimeout(function () {
              Print(id, btn);
            }, 0);
          }
        },
      });
    } else {
      Print(id, btn);
    }
  };

  $("input#bayarBill").on("keyup change", function () {
    bayarBill();
  });

  function bayarBill() {
    var dibayar = parseInt($("input#bayarBill").val());
    var kembalian = parseInt(dibayar) - parseInt(window.totalBill);
    if (kembalian > 0) {
      $("input#kembalianBill").val(kembalian);
    } else {
      $("input#kembalianBill").val(0);
    }
  }

  window.totalBill = $("span#totalBill").attr("data-total");

  $("input.cek").change(function () {
    var jumlah = $(this).attr("data-jumlah");
    let refRekap = $(this).attr("data-ref");

    if ($(this).is(":checked")) {
      window.totalBill = parseInt(window.totalBill) + parseInt(jumlah);
      window.json_rekap[0][refRekap] = jumlah;
    } else {
      delete window.json_rekap[0][refRekap];
      window.totalBill = parseInt(window.totalBill) - parseInt(jumlah);
    }

    $("span#totalBill")
      .html(window.totalBill.toLocaleString("en-US"))
      .attr("data-total", window.totalBill);
    bayarBill();
  });

  window.Print = function (id, btn) {
    // Jika btn tidak diberikan, fallback cari tombol print yang sesuai id (legacy)
    // btn harus selalu tombol yang diklik (event.currentTarget)
    function __startBtnLoading(b) {
      try {
        if (!b) return;
        if (b.dataset.loading === "1") return;
        b.dataset.loading = "1";
        // Cari icon print di dalam tombol
        var icon = b.querySelector("i.fas.fa-print");
        if (icon) {
          b.dataset.prevIconClass = icon.className;
          icon.className = "fas fa-spinner fa-spin";
        }
        b.classList.add("disabled");
        b.style.pointerEvents = "none";
      } catch (e) { }
    }

    function __endBtnLoading(b) {
      try {
        if (!b) return;
        b.classList.remove("disabled");
        b.style.pointerEvents = "";
        // Kembalikan icon print jika sebelumnya diubah
        var icon = b.querySelector("i.fas.fa-spinner");
        if (icon && b.dataset.prevIconClass) {
          icon.className = b.dataset.prevIconClass;
          b.dataset.prevIconClass = "";
        }
        b.dataset.loading = "";
      } catch (e) { }
    }

    // btn harus selalu tombol yang diklik, tidak perlu fallback ke semua tombol

    if (window.__printLockUntil && Date.now() < window.__printLockUntil) {
      return;
    }
    window.__printLockUntil = Date.now() + 3000;

    __startBtnLoading(btn);

    setTimeout(function () {
      __endBtnLoading(btn);
      window.__printLockUntil = 0;
    }, 3000);

    var el = document.getElementById("print" + id);
    var pmode = "server";
    var rows = el.querySelectorAll("tr");
    var lines = [];

    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var tds = tr.querySelectorAll("td");
      if (tr.id && tr.id.toLowerCase() === "dashrow") {
        // handled after width is determined
      }
      if (tds.length === 0) {
        continue;
      }
      var width = parseInt(localStorage.getItem("escpos_width") || "32");
      if (!width || isNaN(width)) {
        width = 32;
      }

      if (tr.id && tr.id.toLowerCase() === "dashrow") {
        var dash = makeDash(width);
        if (pmode === "server") {
          lines.push("[[TR]][[TD]]" + dash + "[[/TD]][[/TR]]");
        } else {
          lines.push(dash);
        }
        continue;
      }

      var escLine = function (left, right, width) {
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

      var makeDash = function (w) {
        return Array(w + 1).join("-");
      };

      var cellToLines = function (td) {
        var html = td.innerHTML || "";
        var s = html;
        if (pmode !== "server") {
          s = s.replace(/<br\s*\/?>/gi, "\n");
        }
        s = s.replace(/&nbsp;/gi, " ");
        s = s.replace(/\u00a0/g, " ");
        s = s.replace(/<b>/gi, "[[B]]").replace(/<\/b>/gi, "[[/B]]");
        s = s.replace(/<h1>/gi, "[[H1]]").replace(/<\/h1>/gi, "[[/H1]]");
        s = s.replace(/<del>/gi, "[[DEL]]").replace(/<\/del>/gi, "[[/DEL]]");
        if (pmode === "server") {
          s = s.replace(/<(?!br\b)[^>]+>/gi, "");
        } else {
          s = s.replace(/<[^>]+>/g, "");
        }
        s = s.replace(/\r\n/g, "\n");
        var arr = s.split("\n");
        var out = [];
        for (var a = 0; a < arr.length; a++) {
          var raw = arr[a];
          var t = raw.replace(/[ \t]+/g, " ").trim();
          if (t.length > 0) {
            out.push(t);
          } else if (pmode === "server") {
            out.push("");
          }
        }
        return out;
      };

      var getAlign = function (td) {
        try {
          var ta =
            td.style && td.style.textAlign
              ? td.style.textAlign.toLowerCase()
              : "";
          if (!ta && window.getComputedStyle) {
            ta = window.getComputedStyle(td).textAlign.toLowerCase();
          }
          return ta || "left";
        } catch (e) {
          return "left";
        }
      };

      var sanitizeServerTd = function (td) {
        try {
          var s = td.innerHTML || "";
          s = s.replace(/<b>/gi, "[[B]]").replace(/<\/b>/gi, "[[/B]]");
          s = s.replace(/<h1>/gi, "[[H1]]").replace(/<\/h1>/gi, "[[/H1]]");
          s = s.replace(/&nbsp;/gi, " ");
          s = s.replace(/\u00a0/g, " ");
          s = s.replace(/<(?!br\b)[^>]+>/gi, "");
          s = s.replace(/[\r\n]+/g, " ");
          s = s.replace(/[ \t]+/g, " ").trim();
          return s;
        } catch (e) {
          return "";
        }
      };

      if (tds.length === 1 || tds[0].getAttribute("colspan") === "2") {
        if (pmode === "server") {
          var v = sanitizeServerTd(tds[0]);
          v = "[[TD]]" + v + "[[/TD]]";
          lines.push("[[TR]]" + v + "[[/TR]]");
        } else {
          var a0 = getAlign(tds[0]);
          var arr1 = cellToLines(tds[0]);
          for (var x = 0; x < arr1.length; x++) {
            var v2 = arr1[x];
            if (a0 === "center") v2 = "[[C]]" + v2 + "[[/C]]";
            else if (a0 === "right") v2 = "[[R]]" + v2 + "[[/R]]";
            else v2 = "[[L]]" + v2 + "[[/L]]";
            lines.push(v2);
          }
        }
      } else if (tds.length >= 2) {
        if (pmode === "server") {
          var left0 = sanitizeServerTd(tds[0]);
          var right0 = sanitizeServerTd(tds[1]);
          var row0 = escLine(left0, right0, width);
          lines.push("[[TR]]" + row0 + "[[/TR]]");
        } else {
          var arrL = cellToLines(tds[0]);
          var arrR = cellToLines(tds[1]);
          var aL = getAlign(tds[0]);
          var aR = getAlign(tds[1]);
          var max = Math.max(arrL.length, arrR.length);
          for (var y = 0; y < max; y++) {
            var left = arrL[y] || "";
            var right = arrR[y] || "";
            if (aL === "center") left = "[[C]]" + left + "[[/C]]";
            else if (aL === "right") left = "[[R]]" + left + "[[/R]]";
            else left = "[[L]]" + left + "[[/L]]";
            if (aR === "center") right = "[[C]]" + right + "[[/C]]";
            else if (aR === "right") right = "[[R]]" + right + "[[/R]]";
            else right = "[[L]]" + right + "[[/L]]";
            lines.push(escLine(left, right, width));
          }
        }
      }
    }

    var encoder = new TextEncoder();
    var chunks = [];
    chunks.push(new Uint8Array([27, 64]));
    var esc_font = (localStorage.getItem("escpos_font") || "A").toUpperCase();
    var esc_cp = parseInt(localStorage.getItem("escpos_codepage") || "16");
    var esc_line = parseInt(localStorage.getItem("escpos_line") || "36");
    var esc_size = (
      localStorage.getItem("escpos_size") || "normal"
    ).toLowerCase();
    var sizeVal = 0;
    if (esc_size === "doublew") sizeVal = 1;
    if (esc_size === "doubleh") sizeVal = 16;
    if (esc_size === "doublehw") sizeVal = 17;
    chunks.push(new Uint8Array([27, 77, esc_font === "A" ? 0 : 1]));
    chunks.push(new Uint8Array([27, 116, isNaN(esc_cp) ? 16 : esc_cp]));
    chunks.push(new Uint8Array([27, 51, isNaN(esc_line) ? 24 : esc_line]));
    chunks.push(new Uint8Array([29, 33, sizeVal]));

    var addLine = function (s, align) {
      s = s || "";
      var center = false;
      if (s.indexOf("[[C]]") === 0) {
        center = true;
        s = s.substring(5);
      }
      s = s.replace(/\[\[(?:\/)?(?:B|DEL|H1|C|R|L|TD)\]\]/g, "");
      chunks.push(new Uint8Array([27, 97, center ? 1 : align]));
      chunks.push(encoder.encode(s));
      chunks.push(encoder.encode("\n"));
    };

    for (var j = 0; j < lines.length; j++) {
      if (j < 2) {
        addLine(lines[j], 1);
      } else {
        addLine(lines[j], 0);
      }
    }

    chunks.push(encoder.encode("\n\n\n"));
    var doCut = (localStorage.getItem("escpos_cut") || "0") === "1";
    if (doCut) {
      chunks.push(new Uint8Array([29, 86, 0]));
    }

    var totalLen = 0;
    for (var k = 0; k < chunks.length; k++) totalLen += chunks[k].length;
    var all = new Uint8Array(totalLen);
    var offset = 0;
    for (var m = 0; m < chunks.length; m++) {
      all.set(chunks[m], offset);
      offset += chunks[m].length;
    }

    function fallbackHtml() {
      var divContents = el.innerHTML;
      var a = window.open("");
      a.document.write("<title>Print Page</title>");
      a.document.write('<body style="margin-left: ' + print_ms + 'mm">');
      a.document.write(divContents);
      var window_width = $(window).width();
      a.print();
      if (window_width > 600) {
        a.close();
      } else {
        setTimeout(function () {
          a.close();
        }, 60000);
      }
      loadDiv();
    }

    function tryBluetooth() {
      if (!navigator.bluetooth) {
        return;
      }

      function doWrite(characteristic, data) {
        var size = 20;
        var idx = 0;
        var p = Promise.resolve();
        while (idx < data.length) {
          var chunk = data.slice(idx, Math.min(idx + size, data.length));
          p = p.then(
            function (c) {
              return characteristic.writeValue(c);
            }.bind(null, chunk)
          );
          idx += size;
        }
        return p;
      }

      navigator.bluetooth
        .requestDevice({
          acceptAllDevices: true,
          optionalServices: [
            "0000ffe0-0000-1000-8000-00805f9b34fb",
            "0000ff00-0000-1000-8000-00805f9b34fb",
          ],
        })
        .then(function (device) {
          return device.gatt.connect();
        })
        .then(function (server) {
          return server
            .getPrimaryService("0000ffe0-0000-1000-8000-00805f9b34fb")
            .catch(function () {
              return server.getPrimaryService(
                "0000ff00-0000-1000-8000-00805f9b34fb"
              );
            });
        })
        .then(function (service) {
          return service
            .getCharacteristic("0000ffe1-0000-1000-8000-00805f9b34fb")
            .catch(function () {
              return service.getCharacteristic(
                "0000ff01-0000-1000-8000-00805f9b34fb"
              );
            });
        })
        .then(function (characteristic) {
          return doWrite(characteristic, all);
        })
        .then(function () {
          loadDiv();
        })
        .catch(function (err) { });
    }

    function escposGetSavedBaud() {
      var b = parseInt(localStorage.getItem("escpos_baud") || "9600");
      if (!b || isNaN(b)) b = 9600;
      return b;
    }

    function escposGetSavedPort() {
      return navigator.serial.getPorts().then(function (ports) {
        if (!ports || ports.length === 0) {
          return null;
        }
        var vid = parseInt(localStorage.getItem("escpos_vendor") || "0");
        var pid = parseInt(localStorage.getItem("escpos_product") || "0");
        if (vid && pid) {
          for (var i = 0; i < ports.length; i++) {
            var info = ports[i].getInfo ? ports[i].getInfo() : {};
            if (info && info.usbVendorId === vid && info.usbProductId === pid) {
              return ports[i];
            }
          }
        }
        return ports[0];
      });
    }

    function escposSavePort(port, baud) {
      try {
        var info = port.getInfo ? port.getInfo() : {};
        if (info && info.usbVendorId)
          localStorage.setItem("escpos_vendor", String(info.usbVendorId));
        if (info && info.usbProductId)
          localStorage.setItem("escpos_product", String(info.usbProductId));
        localStorage.setItem("escpos_baud", String(baud));
      } catch (e) { }
    }

    function trySerial() {
      if (!navigator.serial) {
        tryBluetooth();
        return;
      }
      if (!window.__escpos) {
        window.__escpos = {
          port: null,
          writer: null,
          open: false,
          baud: 9600,
        };
      }

      var openWithSettings = function (rate) {
        return window.__escpos.port
          .open({
            baudRate: rate,
            dataBits: 8,
            stopBits: 1,
            parity: "none",
            flowControl: "none",
          })
          .then(function () {
            if (window.__escpos.port.setSignals) {
              return window.__escpos.port.setSignals({
                dataTerminalReady: true,
                requestToSend: true,
              });
            }
          });
      };

      escposGetSavedPort()
        .then(function (saved) {
          if (saved) {
            window.__escpos.port = saved;
            var b = escposGetSavedBaud();
            return openWithSettings(b).catch(function () {
              return openWithSettings(9600);
            });
          }
          var vid = parseInt(localStorage.getItem("escpos_vendor") || "0");
          var pid = parseInt(localStorage.getItem("escpos_product") || "0");
          var opts = {};
          if (vid && pid) {
            opts = {
              filters: [
                {
                  usbVendorId: vid,
                  usbProductId: pid,
                },
              ],
            };
          }
          return navigator.serial.requestPort(opts).then(function (p) {
            window.__escpos.port = p;
            return openWithSettings(9600).catch(function () {
              return openWithSettings(115200);
            });
          });
        })
        .then(function () {
          var size = 256,
            idx = 0,
            p = Promise.resolve();
          var writer = window.__escpos.port.writable.getWriter();
          window.__escpos.open = true;
          try {
            escposSavePort(window.__escpos.port, escposGetSavedBaud());
          } catch (e) { }
          while (idx < all.length) {
            var chunk = all.slice(idx, Math.min(idx + size, all.length));
            p = p.then(
              function (c) {
                return writer.write(c);
              }.bind(null, chunk)
            );
            idx += size;
          }
          return p.then(function () {
            writer.releaseLock();
            loadDiv();
          });
        })
        .catch(function () {
          tryBluetooth();
        });
    }

    if (pmode === "bluetooth") {
      tryBluetooth();
    } else if (pmode === "esc/pos" || pmode === "escpos" || pmode === "esc") {
      trySerial();
    } else if (pmode === "server") {
      try {
        if (pmode === "server") {
          lines = lines.filter(function (s) {
            var x = String(s || "");
            if (x.indexOf("[[TR]]") === -1) return true;
            var inner = x.replace(/\[\[(?:\/)?(?:TR|TD)\]\]/g, "");
            return inner.trim().length > 0;
          });
        }
        var plain =
          lines
            .map(function (s) {
              s = String(s || "");
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
            })
            .join(pmode === "server" ? "" : "\n") +
          (pmode === "server" ? "" : "\n");
        fetch("http://localhost:3000/print", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            text: plain,
            margin_top: marginTop,
            feed_lines: feedLines
          }),
        })
          .then(function (res) {
            console.log("Server print status:", res.status);
            return res.text().catch(function () {
              return "";
            });
          })
          .then(function (body) {
            console.log("Server print body:", body);
            loadDiv();
          })
          .catch(function (err) {
            console.log("Server print error:", err);
          });
      } catch (e) { }
    } else {
      tryBluetooth();
    }
  };

  window.cekQris = function (ref_id, jumlah) {
    $.ajax({
      url: BASE_URL + "Kas/cek_qris/" + ref_id + "/" + jumlah,
      data: {},
      type: "POST",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res == 0) {
          loadDiv();
        }
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  };

  function loadDiv() {
    if (modeView != 2) {
      var pelanggan = $("select[name=pelanggan]").val();
      $("div#load").load(BASE_URL + "Operasi/loadData/" + pelanggan + "/0");
    }
    if (modeView == 2) {
      var pelanggan = $("select[name=pelanggan]").val();
      var tahun = $("select[name=tahun]").val();
      $("div#load").load(
        BASE_URL + "Operasi/loadData/" + pelanggan + "/" + tahun
      );
    }
  }

  window.PrintQR = function (data, text, btn) {
    var t = String(data || "");
    var label = String(text || "");

    function __startBtnLoading(b) {
      try {
        if (!b) {
          return;
        }
        if (b.dataset.loading === "1") return;
        b.dataset.loading = "1";
        b.dataset.prevHtml = b.innerHTML;
        b.classList.add("disabled");
        b.style.pointerEvents = "none";
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      } catch (e) { }
    }

    function __endBtnLoading(b) {
      try {
        if (!b) return;
        b.classList.remove("disabled");
        b.style.pointerEvents = "";
        if (b.dataset.prevHtml) {
          b.innerHTML = b.dataset.prevHtml;
          b.dataset.prevHtml = "";
        }
        b.dataset.loading = "";
      } catch (e) { }
    }

    if (window.__printLockUntil && Date.now() < window.__printLockUntil) {
      return;
    }
    window.__printLockUntil = Date.now() + 3000;

    __startBtnLoading(btn);

    setTimeout(function () {
      __endBtnLoading(btn);
      window.__printLockUntil = 0;
    }, 3000);

    var encoder = new TextEncoder();
    var chunks = [];
    chunks.push(new Uint8Array([27, 64]));
    chunks.push(new Uint8Array([27, 97, 1]));
    chunks.push(new Uint8Array([29, 40, 107, 4, 0, 49, 65, 49, 0]));
    chunks.push(new Uint8Array([29, 40, 107, 3, 0, 49, 67, 5]));
    chunks.push(new Uint8Array([29, 40, 107, 3, 0, 49, 69, 48]));
    var db = encoder.encode(t);
    var len = db.length + 3;
    var pL = len & 255;
    var pH = (len >> 8) & 255;
    chunks.push(new Uint8Array([29, 40, 107, pL, pH, 49, 80, 48]));
    chunks.push(db);
    chunks.push(new Uint8Array([29, 40, 107, 3, 0, 49, 81, 48]));
    chunks.push(encoder.encode("\n"));
    if (label.length > 0) {
      chunks.push(new Uint8Array([27, 97, 1]));
      chunks.push(encoder.encode(label));
      chunks.push(encoder.encode("\n"));
    }
    chunks.push(encoder.encode("\n"));
    var qrFeed = parseInt(localStorage.getItem("escpos_qr_feed") || "6");
    chunks.push(new Uint8Array([27, 100, isNaN(qrFeed) ? 6 : qrFeed]));
    chunks.push(new Uint8Array([27, 97, 0]));
    var doCutQr = (localStorage.getItem("escpos_cut") || "0") === "1";
    if (doCutQr) {
      chunks.push(new Uint8Array([29, 86, 0]));
    }

    var total = 0;
    for (var i = 0; i < chunks.length; i++) total += chunks[i].length;
    var all = new Uint8Array(total);
    var off = 0;
    for (var j = 0; j < chunks.length; j++) {
      all.set(chunks[j], off);
      off += chunks[j].length;
    }

    var pmode = "server";

    function tryBluetooth() {
      if (!navigator.bluetooth) {
        return;
      }

      function w(ch, d) {
        var s = 20,
          idx = 0,
          p = Promise.resolve();
        while (idx < d.length) {
          var c = d.slice(idx, Math.min(idx + s, d.length));
          p = p.then(
            function (x) {
              return ch.writeValue(x);
            }.bind(null, c)
          );
          idx += s;
        }
        return p;
      }

      navigator.bluetooth
        .requestDevice({
          acceptAllDevices: true,
          optionalServices: [
            "0000ffe0-0000-1000-8000-00805f9b34fb",
            "0000ff00-0000-1000-8000-00805f9b34fb",
          ],
        })
        .then(function (dev) {
          return dev.gatt.connect();
        })
        .then(function (srv) {
          return srv
            .getPrimaryService("0000ffe0-0000-1000-8000-00805f9b34fb")
            .catch(function () {
              return srv.getPrimaryService(
                "0000ff00-0000-1000-8000-00805f9b34fb"
              );
            });
        })
        .then(function (svc) {
          return svc
            .getCharacteristic("0000ffe1-0000-1000-8000-00805f9b34fb")
            .catch(function () {
              return svc.getCharacteristic(
                "0000ff01-0000-1000-8000-00805f9b34fb"
              );
            });
        })
        .then(function (ch) {
          return w(ch, all);
        });
    }

    function escposGetSavedBaud() {
      var b = parseInt(localStorage.getItem("escpos_baud") || "9600");
      if (!b || isNaN(b)) b = 9600;
      return b;
    }

    function escposGetSavedPort() {
      return navigator.serial.getPorts().then(function (ports) {
        if (!ports || ports.length === 0) {
          return null;
        }
        var vid = parseInt(localStorage.getItem("escpos_vendor") || "0");
        var pid = parseInt(localStorage.getItem("escpos_product") || "0");
        if (vid && pid) {
          for (var i = 0; i < ports.length; i++) {
            var info = ports[i].getInfo ? ports[i].getInfo() : {};
            if (info && info.usbVendorId === vid && info.usbProductId === pid) {
              return ports[i];
            }
          }
        }
        return ports[0];
      });
    }

    function escposSavePort(port, baud) {
      try {
        var info = port.getInfo ? port.getInfo() : {};
        if (info && info.usbVendorId)
          localStorage.setItem("escpos_vendor", String(info.usbVendorId));
        if (info && info.usbProductId)
          localStorage.setItem("escpos_product", String(info.usbProductId));
        localStorage.setItem("escpos_baud", String(baud));
      } catch (e) { }
    }

    function trySerial() {
      if (!navigator.serial) {
        tryBluetooth();
        return;
      }
      if (!window.__escpos) {
        window.__escpos = {
          port: null,
          open: false,
          baud: escposGetSavedBaud(),
        };
      }
      var port = window.__escpos.port;

      var openWith = function (rate) {
        return port
          .open({
            baudRate: rate,
            dataBits: 8,
            stopBits: 1,
            parity: "none",
            flowControl: "none",
          })
          .then(function () {
            if (port.setSignals)
              return port.setSignals({
                dataTerminalReady: true,
                requestToSend: true,
              });
          });
      };

      var writeAll = function () {
        var writer = port.writable.getWriter();
        var size = 256,
          idx = 0,
          p = Promise.resolve();
        while (idx < all.length) {
          var chunk = all.slice(idx, Math.min(idx + size, all.length));
          p = p.then(
            function (c) {
              return writer.write(c);
            }.bind(null, chunk)
          );
          idx += size;
        }
        return p.then(function () {
          writer.releaseLock();
        });
      };

      var startSerial = function () {
        writeAll()
          .then(function () {
            window.__escpos.open = true;
          })
          .catch(function () {
            tryBluetooth();
          });
      };

      if (port && window.__escpos.open) {
        startSerial();
        return;
      }
      if (port && !window.__escpos.open) {
        openWith(window.__escpos.baud)
          .catch(function () {
            return openWith(9600);
          })
          .then(function () {
            startSerial();
          });
        return;
      }

      escposGetSavedPort()
        .then(function (saved) {
          if (saved) {
            port = saved;
            window.__escpos.port = port;
            return openWith(window.__escpos.baud).catch(function () {
              return openWith(9600);
            });
          }
          return navigator.serial.requestPort().then(function (p) {
            port = p;
            window.__escpos.port = port;
            return openWith(9600).catch(function () {
              return openWith(115200);
            });
          });
        })
        .then(function () {
          try {
            escposSavePort(port, window.__escpos.baud);
          } catch (e) { }
          startSerial();
        })
        .catch(function () {
          tryBluetooth();
        });
    }

    if (pmode === "bluetooth") {
      tryBluetooth();
    } else if (pmode === "esc/pos" || pmode === "escpos" || pmode === "esc") {
      trySerial();
    } else if (pmode === "server") {
      try {
        fetch("http://localhost:3000/printqr", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            qr_string: t,
            text: label,
            margin_top: marginTop,
            feed_lines: feedLines
          }),
        })
          .then(function (res) {
            console.log("Server printqr status:", res.status);
            return res.text().catch(function () {
              return "";
            });
          })
          .then(function (body) {
            console.log("Server printqr body:", body);
          })
          .catch(function (err) {
            console.log("Server printqr error:", err);
          });
      } catch (e) { }
    } else {
      tryBluetooth();
    }
  };
  // Cancel Payment Handler
  var cancelPaymentRef = '';
  $(document).on('click', '.cancelPayment', function (e) {
    e.preventDefault();
    var ref = $(this).data('ref');
    var note = $(this).data('note');
    var total = $(this).data('total');

    cancelPaymentRef = ref;
    $('#cancelPaymentInfo').html('<strong>' + note + '</strong> - Rp' + total);

    // Move modal to body if not already there
    var modal = $('#modalCancelPayment');
    if (modal.parent()[0] !== document.body) {
      modal.appendTo('body');
    }

    // Close any open modals first
    $('.modal.show').each(function () {
      $(this).removeClass('show').css('display', 'none');
    });
    $('.modal-backdrop').remove();

    // Show our modal with proper styling
    modal.css({
      'display': 'block',
      'z-index': '10055',
      'position': 'fixed',
      'top': '0',
      'left': '0',
      'width': '100%',
      'height': '100%'
    }).addClass('show');

    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show" style="z-index: 10050;"></div>');
  });

  $(document).on('click', '#btnConfirmCancel', function () {
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
      url: config.baseUrl + 'Operasi/cancel_payment/' + cancelPaymentRef,
      type: 'GET',
      dataType: 'JSON',
      success: function (response) {
        btn.prop('disabled', false).html(originalHtml);
        // Hide modal manually
        $('#modalCancelPayment').removeClass('show').css('display', 'none');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();

        if (response.status === 'success') {
          loadDiv();
        }
      },
      error: function (xhr, status, error) {
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });

  // Handle modal close buttons
  $(document).on('click', '#modalCancelPayment [data-bs-dismiss="modal"]', function () {
    $('#modalCancelPayment').removeClass('show').css('display', 'none');
    $('body').removeClass('modal-open');
    $('.modal-backdrop').remove();
  });
})();
