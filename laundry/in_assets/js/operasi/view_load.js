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
  $(document).off("click", ".editDurasi");
  $(document).off("change", "#ubahDurasiSelect");
  $(document).off("click", "#btnSimpanDurasi");
  $(document).off("click", ".editMember");
  $(document).off("click", "#btnSimpanMember");
  $(document).off("click", ".editLayanan");
  $(document).off("change", "#ubahLayananSelect");
  $(document).off("click", "#btnSimpanLayanan");
  $(document).off("click", ".hapusItemNota");
  $(document).off("click", "[data-close-hapus-item]");
  $(document).off("click", "#btnKonfirmasiHapusItem");
  $(document).off("click", "a.hapusRef");
  $(document).off("click", ".tutupModalHapusBtn");
  $(document).off("click", "#btnHapusKonfirm");

  // Cleanup orphaned modals that were moved to body in previous executions
  // This prevents Duplicate ID errors and Bootstrap confusion which causes recursive errors
  $("body > #modalAlert").remove();
  $("body > #modalQR").remove();
  $("body > #modalHapusItemNota").remove();
  $("body > #modalHapusOrderInline").remove();

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

  // Local print server helpers — see print_server.js (loaded before this file)
  // Fallbacks if print_server.js missing
  if (typeof window.printServerFetch !== "function") {
    window.printServerFetch = function (path, bodyObj, timeoutMs) {
      timeoutMs = timeoutMs || 3000;
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);
      return fetch("http://localhost:3000" + path, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(bodyObj || {}),
        signal: controller.signal,
      }).finally(function () {
        clearTimeout(timer);
      });
    };
  }
  if (typeof window.printServerErrorMessage !== "function") {
    window.printServerErrorMessage = function () {
      return "Print server / Print Bridge tidak aktif di localhost:3000.";
    };
  }

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
  var nama_pelanggan = config.namaPelanggan || "";
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
    var operasiQRPollInterval = null;

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

      // Polling status dari DB setiap 3 detik (hanya untuk QRIS, bukan dev mode)
      function stopOperasiQRPoll() {
        if (operasiQRPollInterval) {
          clearInterval(operasiQRPollInterval);
          operasiQRPollInterval = null;
        }
      }
      function doPoll() {
        $.getJSON(BASE_URL + "Operasi/payment_gateway_status_poll/" + ref_id).done(function (res) {
          if (res.status === "PAID") {
            stopOperasiQRPoll();
            $("#qrcode").html('<div class="text-success text-center"><i class="fas fa-check-circle fa-5x"></i><h3 class="mt-2">LUNAS/PAID</h3></div>');
            $("#btnCekStatusQR").removeClass("btn-warning").addClass("btn-success").html('<i class="fas fa-check"></i> PAID');
            setTimeout(function () {
              var modalEl2 = document.getElementById("modalQR");
              if (modalEl2 && window.bootstrap && bootstrap.Modal) {
                var mFn = bootstrap.Modal.getInstance(modalEl2);
                if (mFn) mFn.hide();
              }
              if (typeof load_data_operasi === "function" && id_pelanggan) {
                load_data_operasi(id_pelanggan);
              } else if (typeof loadDiv === "function") {
                loadDiv();
              } else {
                location.reload();
              }
            }, 2000);
          }
        });
      }
      if (operasiQRPollInterval) stopOperasiQRPoll();
      modalEl.addEventListener("shown.bs.modal", function () {
        if (!isDev && ref_id) {
          stopOperasiQRPoll();
          operasiQRPollInterval = setInterval(doPoll, 3000);
          doPoll();
        }
      }, { once: true });
      modalEl.addEventListener("hidden.bs.modal", stopOperasiQRPoll, { once: true });

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

        // POST to print server / Android Print Bridge
        window.printServerFetch("/printqr", {
          qr_string: data.qrString,
          text: printText,
          margin_top: marginTop,
          feed_lines: feedLines
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
            showAlert(window.printServerErrorMessage(err), "error");
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
            var msg = (res.msg || "Silahkan cek ulang beberapa saat lagi.");
            if (res.status === 'PENDING' && msg.indexOf('lihat kode QR') !== -1) {
              msg = "Silakan buka kode QR terlebih dahulu (klik tombol QRIS di riwayat), lalu scan dan bayar. Setelah itu baru cek status.";
            }
            showAlert("Status: " + (res.status || "Unknown") + "\n" + msg, "info");
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
            $(btn).html('<i class="fas fa-spinner fa-spin"></i> Memuat QR...');
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
              // Scenario 2: QRIS Maintenance - Show friendly message
              var errorMsg = "🔧 QRIS Sedang Dalam Perbaikan\n\n";
              errorMsg += "Mohon maaf, layanan QRIS sementara tidak tersedia.\n";
              errorMsg += "Silakan gunakan metode pembayaran lain atau coba beberapa saat lagi.\n\n";
              errorMsg += "Detail Teknis:\n";
              errorMsg += JSON.stringify(res, null, 2);
              
              showAlert(errorMsg, "warning");
            }
          },
          error: function (xhr, status, error) {
            // Show friendly error message
            var errorMsg = "🔧 QRIS Sedang Dalam Perbaikan\n\n";
            errorMsg += "Mohon maaf, sistem pembayaran QRIS sementara tidak dapat diakses.\n";
            errorMsg += "Silakan coba lagi dalam beberapa saat atau gunakan metode pembayaran lain.\n\n";
            errorMsg += "Detail Teknis:\n";
            errorMsg += "Error: " + error + "\n";
            errorMsg += "Status: " + status + "\n";
            if (xhr.responseText) {
              errorMsg += "\nResponse: " + xhr.responseText;
            }
            
            showAlert(errorMsg, "warning");
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

  // Unbind to prevent duplicate handlers when view_load re-injected
  $(document).off("submit", "form.ajax");
  $(document).on("submit", "form.ajax", function (e) {
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
    var idPenanggungBayar = parseInt($("#idPenanggungBayar").val() || "0", 10);

    if (idPenanggungBayar > 0 && metodeBill != "3") {
      showAlert("Tanggung bayar hanya dapat menggunakan metode Saldo Tunai.", "error");
      return;
    }

    noteBill = (noteBill || "").replace(" ", "_SPACE_");

    var postData = {
      rekap: window.json_rekap,
      dibayar: parseRupiahInput($("input#bayarBill").val()),
    };
    if (idPenanggungBayar > 0) {
      postData.id_penanggung_bayar = idPenanggungBayar;
    }

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
      data: postData,
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

  // --- Hapus satu item dari nota ---
  function tutupModalHapusItem() {
    $('#modalHapusItemNota').removeClass('is-open').attr('aria-hidden', 'true');
  }

  function bukaModalHapusItem(id, ref, itemName) {
    var $modal = $('#modalHapusItemNota');
    if ($modal.length === 0) {
      console.error('Modal #modalHapusItemNota tidak ditemukan!');
      return;
    }

    // Pindahkan ke body agar tidak tertutup overflow/stacking context di #load
    if ($modal.parent()[0] !== document.body) {
      $modal.appendTo('body');
    }

    $('#hapusItemNama').text(itemName || ('ID ' + id));
    $('#hapusItemRef').text('#' + ref);
    $('#hapusItemNote').val('').css('border-color', '');
    $('#btnKonfirmasiHapusItem').attr('data-id', id);
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    setTimeout(function () { $('#hapusItemNote').focus(); }, 100);
  }

  $(document).on('click', '.hapusItemNota', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $button = $(this);
    bukaModalHapusItem(
      $button.attr('data-id'),
      $button.attr('data-ref'),
      $button.attr('data-item')
    );
  });

  $(document).on('click', '[data-close-hapus-item]', function (e) {
    e.preventDefault();
    tutupModalHapusItem();
  });

  $(document).on('click', '#btnKonfirmasiHapusItem', function () {
    var $button = $(this);
    var id = $button.attr('data-id');
    var note = $('#hapusItemNote').val().trim();
    if (!id) {
      alert('Item tidak ditemukan. Muat ulang halaman lalu coba lagi.');
      return;
    }
    if (!note) {
      $('#hapusItemNote').css('border-color', '#dc2626').focus();
      return;
    }

    var original = $button.html();
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    $.ajax({
      url: BASE_URL + 'Operasi/hapusItem',
      method: 'POST',
      dataType: 'json',
      data: { id: id, note: note },
      success: function (response) {
        if (response && response.status === 'success') {
          tutupModalHapusItem();
          loadDiv();
          return;
        }
        alert((response && response.message) || 'Item tidak dapat dihapus.');
      },
      error: function (xhr) {
        var msg = 'Gagal menghapus item. Periksa koneksi lalu coba lagi.';
        try {
          var parsed = JSON.parse(xhr.responseText);
          if (parsed && parsed.message) {
            msg = parsed.message;
          }
        } catch (err) { }
        alert(msg);
      },
      complete: function () {
        $button.prop('disabled', false).html(original);
      }
    });
  });
  // --- Akhir hapus satu item dari nota ---

  $("a.ambil").on("click", function (e) {
    e.preventDefault();
    window.idNya = $(this).attr("data-id");
    $("input.idItem").val(window.idNya);
  });

  // Unbind to prevent duplicate handlers
  $(document).off("click", "a.sendNotif");

  $(document).on("click", "a.sendNotif", function (e) {
    e.preventDefault();

    var $btn = $(this);

    // Prevent multiple clicks on the same button
    if ($btn.hasClass('sending')) {
      return;
    }

    $btn.addClass('sending').fadeOut("slow");

    var urutRef = $btn.attr("data-urutRef");
    var hpNya = $btn.attr("data-hp");
    var refNya = $btn.attr("data-ref");
    var timeNya = $btn.attr("data-time");

    // Fallback: jika refNya kosong atau undefined, gunakan urutRef
    if (!refNya || refNya == '' || refNya == '0' || refNya == 'undefined') {
      refNya = urutRef;
    }

    $.ajax({
      url: BASE_URL + "Antrian/sendNotif/0/1",
      data: {
        hp: hpNya,
        ref: refNya,
        time: timeNya,
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
          $btn.removeClass('sending').fadeIn("fast");
        }
      },
      error: function () {
        $btn.removeClass('sending').fadeIn("fast");
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
    $("input#bayarBill").val(formatRupiahInput(window.totalBill));
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
    if ($(this).val() != "3") {
      resetTanggungBayar();
    }
  });

  var tbListData = [];
  var tbSelected = null;
  var tbMetodeSaldoAdded = false;
  var tbMetodeSaldoOrigText = null;

  function resetTanggungBayar() {
    $("#idPenanggungBayar").val("");
    $("#rowTanggungBayarInfo").addClass("d-none");
    $("#rowTanggungBayar").removeClass("d-none");
    tbSelected = null;
    $("#tbKonfirmasi").addClass("d-none");
    if (tbMetodeSaldoOrigText !== null) {
      $("#metodeBill option[value='3']").text(tbMetodeSaldoOrigText);
      tbMetodeSaldoOrigText = null;
    }
    if (tbMetodeSaldoAdded) {
      $("#metodeBill option[value='3']").remove();
      tbMetodeSaldoAdded = false;
      var $metode = $("#metodeBill");
      if ($metode.find("option").length > 0) {
        $metode.val($metode.find("option:first").val()).trigger("change");
      }
    }
  }

  function ensureMetodeSaldoTanggungBayar(labelSaldo) {
    var $sel = $("#metodeBill");
    var $opt = $sel.find('option[value="3"]');
    if ($opt.length === 0) {
      $sel.append(
        $("<option>", {
          value: "3",
          text: "Saldo Tunai (Tanggung Bayar) [ " + labelSaldo + " ]",
        })
      );
      tbMetodeSaldoAdded = true;
    } else {
      if (tbMetodeSaldoOrigText === null) {
        tbMetodeSaldoOrigText = $opt.text();
      }
      $opt.text("Saldo Tunai (Tanggung Bayar) [ " + labelSaldo + " ]");
    }
    $sel.val("3").trigger("change");
  }

  function renderListPenanggungBayar(filter) {
    var q = (filter || "").toUpperCase().trim();
    var html = "";
    var count = 0;
    for (var i = 0; i < tbListData.length; i++) {
      var row = tbListData[i];
      var nama = String(row.nama_pelanggan || "").toUpperCase();
      var hp = String(row.nomor_pelanggan || "");
      if (q && nama.indexOf(q) === -1 && hp.indexOf(q) === -1) {
        continue;
      }
      count++;
      html +=
        "<button type='button' class='btn btn-outline-secondary btn-sm w-100 text-start mb-1 tb-pilih-penanggung' " +
        "data-id='" + row.id_pelanggan + "' " +
        "data-nama='" + nama.replace(/'/g, "") + "' " +
        "data-saldo='" + row.saldo + "'>" +
        "<strong>" + nama + "</strong><br>" +
        "<span class='small text-muted'>" + hp + "</span>" +
        "<span class='float-end text-success fw-bold'>Rp " + Number(row.saldo).toLocaleString("id-ID") + "</span>" +
        "</button>";
    }
    if (count === 0) {
      html = "<div class='text-center text-muted py-3 small'>Tidak ada pemilik saldo tunai ditemukan.</div>";
    }
    $("#listPenanggungBayar").html(html);
  }

  function loadListPenanggungBayar() {
    $("#listPenanggungBayar").html("<div class='text-center text-muted py-3'><i class='fas fa-spinner fa-spin'></i> Memuat...</div>");
    $.getJSON(BASE_URL + "Operasi/listPenanggungBayar/" + id_pelanggan)
      .done(function (res) {
        if (res && res.ok && Array.isArray(res.data)) {
          tbListData = res.data;
          renderListPenanggungBayar($("#searchPenanggungBayar").val());
        } else {
          tbListData = [];
          $("#listPenanggungBayar").html("<div class='text-center text-danger py-3 small'>Gagal memuat data.</div>");
        }
      })
      .fail(function () {
        tbListData = [];
        $("#listPenanggungBayar").html("<div class='text-center text-danger py-3 small'>Gagal memuat data.</div>");
      });
  }

  $("#btnTanggungBayar").on("click", function () {
    tbSelected = null;
    $("#tbKonfirmasi").addClass("d-none");
    $("#searchPenanggungBayar").val("");
    loadListPenanggungBayar();
    var modalEl = document.getElementById("modalTanggungBayar");
    if (modalEl) {
      if (modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
      }
      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    }
  });

  $("#searchPenanggungBayar").on("input", function () {
    renderListPenanggungBayar($(this).val());
  });

  $(document).on("click", ".tb-pilih-penanggung", function () {
    tbSelected = {
      id: parseInt($(this).attr("data-id"), 10),
      nama: $(this).attr("data-nama"),
      saldo: parseInt($(this).attr("data-saldo"), 10) || 0,
    };
    $("#tbKonfirmasiOrder").text(nama_pelanggan ? nama_pelanggan.toUpperCase() : "pelanggan ini");
    $("#tbKonfirmasiNama").text(tbSelected.nama);
    $("#tbKonfirmasiSaldo").text(Number(tbSelected.saldo).toLocaleString("id-ID"));
    $("#tbKonfirmasi").removeClass("d-none");
  });

  $("#btnKonfirmasiTanggungBayar").on("click", function () {
    if (!tbSelected || !tbSelected.id) {
      return;
    }
    $("#idPenanggungBayar").val(tbSelected.id);
    $("#tbNamaPenanggung").text(tbSelected.nama);
    $("#tbSaldoPenanggung").text(Number(tbSelected.saldo).toLocaleString("id-ID"));
    $("#rowTanggungBayarInfo").removeClass("d-none");
    $("#rowTanggungBayar").addClass("d-none");
    ensureMetodeSaldoTanggungBayar(Number(tbSelected.saldo).toLocaleString("id-ID"));
    var modalEl = document.getElementById("modalTanggungBayar");
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      var inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
  });

  $("#btnBatalTanggungBayar").on("click", function () {
    resetTanggungBayar();
  });

  var offcanvasPaymentEl = document.getElementById("offcanvasPayment");
  if (offcanvasPaymentEl) {
    offcanvasPaymentEl.addEventListener("hidden.bs.offcanvas", function () {
      resetTanggungBayar();
      $("#alertRecap").addClass("d-none").html("");
    });
  }

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

  var ubahDurasiState = { id: 0, options: [], dibayar: 0 };

  function formatRp(n) {
    return parseInt(n || 0, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function renderUbahDurasiOption(opt) {
    var label = opt.durasi + " (" + opt.hari + "h " + opt.jam + "j) - Rp" + formatRp(opt.harga);
    if (!opt.can_select) {
      label += " [min. total Rp" + formatRp(ubahDurasiState.dibayar) + "]";
    }
    return label;
  }

  function updateUbahDurasiPreview() {
    var val = $("#ubahDurasiSelect").val();
    var opt = null;
    for (var i = 0; i < ubahDurasiState.options.length; i++) {
      if (String(ubahDurasiState.options[i].id_harga) === String(val)) {
        opt = ubahDurasiState.options[i];
        break;
      }
    }

    $("#ubahDurasiAlert").addClass("d-none").text("");

    if (!opt) {
      $("#btnSimpanDurasi").prop("disabled", true);
      return;
    }

    $("#ubahDurasiItemHarga").text("Rp" + formatRp(opt.item_total));
    $("#ubahDurasiRefTotal").text("Rp" + formatRp(opt.ref_total));

    if (opt.selected) {
      $("#btnSimpanDurasi").prop("disabled", true);
      return;
    }

    if (!opt.can_select) {
      $("#ubahDurasiAlert")
        .removeClass("d-none")
        .text(
          "Total order setelah ubah durasi (Rp" +
            formatRp(opt.ref_total) +
            ") kurang dari pembayaran Cek/Berhasil (Rp" +
            formatRp(ubahDurasiState.dibayar) +
            ")."
        );
      $("#btnSimpanDurasi").prop("disabled", true);
      return;
    }

    $("#btnSimpanDurasi").prop("disabled", false);
  }

  function loadUbahDurasiOptions(idPenjualan) {
    ubahDurasiState = { id: idPenjualan, options: [], dibayar: 0 };
    $("#ubahDurasiLoading").removeClass("d-none");
    $("#ubahDurasiContent").addClass("d-none");
    $("#btnSimpanDurasi").prop("disabled", true);

    $.ajax({
      url: BASE_URL + "Operasi/durasi_options",
      data: { id: idPenjualan },
      type: "POST",
      dataType: "json",
      success: function (res) {
        $("#ubahDurasiLoading").addClass("d-none");
        if (!res || res.status !== "success") {
          showAlert((res && res.message) || "Gagal memuat pilihan durasi", "error");
          try {
            var modalEl = document.getElementById("modalUbahDurasi");
            if (modalEl && bootstrap.Modal) {
              bootstrap.Modal.getInstance(modalEl).hide();
            }
          } catch (e) {}
          return;
        }

        ubahDurasiState.id = res.id_penjualan || idPenjualan;
        ubahDurasiState.options = res.options || [];
        ubahDurasiState.dibayar = res.dibayar || 0;

        $("#ubahDurasiItem").text("#" + res.id_penjualan + " " + (res.kategori || ""));
        $("#ubahDurasiInfo").text(
          "Durasi sekarang: " +
            (res.current_durasi || "-") +
            " | Total order: Rp" +
            formatRp(res.current_ref_total)
        );

        var $sel = $("#ubahDurasiSelect").empty();
        ubahDurasiState.options.forEach(function (opt) {
          $sel.append(
            $("<option></option>")
              .val(opt.id_harga)
              .prop("disabled", !opt.can_select && !opt.selected)
              .text(renderUbahDurasiOption(opt))
          );
        });

        ubahDurasiState.options.forEach(function (opt) {
          if (opt.selected) {
            $sel.val(opt.id_harga);
          }
        });

        if (res.dibayar > 0) {
          $("#ubahDurasiBayarInfo").removeClass("d-none");
          $("#ubahDurasiDibayar").text("Rp" + formatRp(res.dibayar));
        } else {
          $("#ubahDurasiBayarInfo").addClass("d-none");
        }

        $("#ubahDurasiContent").removeClass("d-none");
        updateUbahDurasiPreview();
      },
      error: function () {
        $("#ubahDurasiLoading").addClass("d-none");
        showAlert("Gagal memuat pilihan durasi", "error");
      },
    });
  }

  $(document).on("click", ".editDurasi", function (e) {
    e.preventDefault();
    var idPenjualan = $(this).attr("data-id");
    if (!idPenjualan) {
      return;
    }
    loadUbahDurasiOptions(idPenjualan);
  });

  $(document).on("change", "#ubahDurasiSelect", function () {
    updateUbahDurasiPreview();
  });

  $(document).on("click", "#btnSimpanDurasi", function () {
    var idHarga = $("#ubahDurasiSelect").val();
    if (!ubahDurasiState.id || !idHarga) {
      return;
    }

    $("#btnSimpanDurasi").prop("disabled", true);
    $.ajax({
      url: BASE_URL + "Operasi/ubah_durasi",
      data: {
        id: ubahDurasiState.id,
        id_harga: idHarga,
      },
      type: "POST",
      dataType: "json",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res && res.status === "success") {
          try {
            var modalEl = document.getElementById("modalUbahDurasi");
            if (modalEl && bootstrap.Modal) {
              var instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
              instance.hide();
            }
          } catch (e) {}
          loadDiv();
        } else {
          showAlert((res && res.message) || "Gagal mengubah durasi", "error");
          updateUbahDurasiPreview();
        }
      },
      error: function () {
        showAlert("Gagal mengubah durasi", "error");
        updateUbahDurasiPreview();
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  });

  var ubahLayananState = { id: 0, options: [], dibayar: 0 };

  function renderUbahLayananOption(opt) {
    var label = opt.layanan + " - Rp" + formatRp(opt.harga);
    if (!opt.can_select) {
      label += " [min. total Rp" + formatRp(ubahLayananState.dibayar) + "]";
    }
    return label;
  }

  function updateUbahLayananPreview() {
    var val = $("#ubahLayananSelect").val();
    var opt = null;
    for (var i = 0; i < ubahLayananState.options.length; i++) {
      if (String(ubahLayananState.options[i].id_harga) === String(val)) {
        opt = ubahLayananState.options[i];
        break;
      }
    }

    $("#ubahLayananAlert").addClass("d-none").text("");

    if (!opt) {
      $("#btnSimpanLayanan").prop("disabled", true);
      return;
    }

    $("#ubahLayananItemHarga").text("Rp" + formatRp(opt.item_total));
    $("#ubahLayananRefTotal").text("Rp" + formatRp(opt.ref_total));

    if (opt.selected) {
      $("#btnSimpanLayanan").prop("disabled", true);
      return;
    }

    if (!opt.can_select) {
      $("#ubahLayananAlert")
        .removeClass("d-none")
        .text(
          "Total order setelah ubah layanan (Rp" +
            formatRp(opt.ref_total) +
            ") kurang dari pembayaran Cek/Berhasil (Rp" +
            formatRp(ubahLayananState.dibayar) +
            ")."
        );
      $("#btnSimpanLayanan").prop("disabled", true);
      return;
    }

    $("#btnSimpanLayanan").prop("disabled", false);
  }

  function loadUbahLayananOptions(idPenjualan) {
    ubahLayananState = { id: idPenjualan, options: [], dibayar: 0 };
    $("#ubahLayananLoading").removeClass("d-none");
    $("#ubahLayananContent").addClass("d-none");
    $("#btnSimpanLayanan").prop("disabled", true);

    $.ajax({
      url: BASE_URL + "Operasi/layanan_options",
      data: { id: idPenjualan },
      type: "POST",
      dataType: "json",
      success: function (res) {
        $("#ubahLayananLoading").addClass("d-none");
        if (!res || res.status !== "success") {
          showAlert((res && res.message) || "Gagal memuat pilihan layanan", "error");
          try {
            var modalEl = document.getElementById("modalUbahLayanan");
            if (modalEl && bootstrap.Modal) {
              bootstrap.Modal.getInstance(modalEl).hide();
            }
          } catch (e) {}
          return;
        }

        ubahLayananState.id = res.id_penjualan || idPenjualan;
        ubahLayananState.options = res.options || [];
        ubahLayananState.dibayar = res.dibayar || 0;

        $("#ubahLayananItem").text("#" + res.id_penjualan + " " + (res.kategori || ""));
        $("#ubahLayananInfo").text(
          "Layanan sekarang: " +
            (res.current_layanan || "-") +
            " | " +
            (res.current_durasi || "") +
            " | Total order: Rp" +
            formatRp(res.current_ref_total)
        );

        var $sel = $("#ubahLayananSelect").empty();
        ubahLayananState.options.forEach(function (opt) {
          $sel.append(
            $("<option></option>")
              .val(opt.id_harga)
              .prop("disabled", !opt.can_select && !opt.selected)
              .text(renderUbahLayananOption(opt))
          );
        });

        ubahLayananState.options.forEach(function (opt) {
          if (opt.selected) {
            $sel.val(opt.id_harga);
          }
        });

        if (res.dibayar > 0) {
          $("#ubahLayananBayarInfo").removeClass("d-none");
          $("#ubahLayananDibayar").text("Rp" + formatRp(res.dibayar));
        } else {
          $("#ubahLayananBayarInfo").addClass("d-none");
        }

        $("#ubahLayananContent").removeClass("d-none");
        updateUbahLayananPreview();
      },
      error: function () {
        $("#ubahLayananLoading").addClass("d-none");
        showAlert("Gagal memuat pilihan layanan", "error");
      },
    });
  }

  $(document).on("click", ".editLayanan", function (e) {
    e.preventDefault();
    var idPenjualan = $(this).attr("data-id");
    if (!idPenjualan) {
      return;
    }
    loadUbahLayananOptions(idPenjualan);
  });

  $(document).on("change", "#ubahLayananSelect", function () {
    updateUbahLayananPreview();
  });

  $(document).on("click", "#btnSimpanLayanan", function () {
    var idHarga = $("#ubahLayananSelect").val();
    if (!ubahLayananState.id || !idHarga) {
      return;
    }

    $("#btnSimpanLayanan").prop("disabled", true);
    $.ajax({
      url: BASE_URL + "Operasi/ubah_layanan",
      data: {
        id: ubahLayananState.id,
        id_harga: idHarga,
      },
      type: "POST",
      dataType: "json",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res && res.status === "success") {
          try {
            var modalEl = document.getElementById("modalUbahLayanan");
            if (modalEl && bootstrap.Modal) {
              var instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
              instance.hide();
            }
          } catch (e) {}
          loadDiv();
        } else {
          showAlert((res && res.message) || "Gagal mengubah layanan", "error");
          updateUbahLayananPreview();
        }
      },
      error: function () {
        showAlert("Gagal mengubah layanan", "error");
        updateUbahLayananPreview();
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
    });
  });

  var ubahMemberState = { id: 0 };

  function loadUbahMemberOptions(idPenjualan) {
    ubahMemberState = { id: idPenjualan };
    $("#ubahMemberLoading").removeClass("d-none");
    $("#ubahMemberContent").addClass("d-none");
    $("#ubahMemberAlert").addClass("d-none").text("");
    $("#btnSimpanMember").prop("disabled", true);

    $.ajax({
      url: BASE_URL + "Operasi/member_options",
      data: { id: idPenjualan },
      type: "POST",
      dataType: "json",
      success: function (res) {
        $("#ubahMemberLoading").addClass("d-none");
        if (!res || res.status !== "success") {
          showAlert((res && res.message) || "Gagal memuat data member", "error");
          try {
            var modalEl = document.getElementById("modalUbahMember");
            if (modalEl && bootstrap.Modal) {
              bootstrap.Modal.getInstance(modalEl).hide();
            }
          } catch (e) {}
          return;
        }

        $("#ubahMemberInfo").text(
          "#" + res.id_penjualan + " " + (res.kategori || "") + " - " + (res.durasi || "")
        );
        $("#ubahMemberPaket").text("M" + res.id_harga);
        $("#ubahMemberQty").text((res.qty_fmt || res.qty) + (res.unit || ""));
        $("#ubahMemberSaldo").text((res.saldo_fmt || res.saldo) + (res.unit || ""));
        $("#ubahMemberRefTotal").text(
          "Rp" + formatRp(res.current_ref_total) + " → Rp" + formatRp(res.new_ref_total)
        );

        if (res.dibayar > 0) {
          $("#ubahMemberBayarInfo").removeClass("d-none");
          $("#ubahMemberDibayar").text("Rp" + formatRp(res.dibayar));
        } else {
          $("#ubahMemberBayarInfo").addClass("d-none");
        }

        ubahMemberState.id = res.id_penjualan || idPenjualan;

        if (!res.can_convert && res.message) {
          $("#ubahMemberAlert").removeClass("d-none").text(res.message);
          $("#btnSimpanMember").prop("disabled", true);
        } else {
          $("#btnSimpanMember").prop("disabled", false);
        }

        $("#ubahMemberContent").removeClass("d-none");
      },
      error: function () {
        $("#ubahMemberLoading").addClass("d-none");
        showAlert("Gagal memuat data member", "error");
      },
    });
  }

  $(document).on("click", ".editMember", function (e) {
    e.preventDefault();
    var idPenjualan = $(this).attr("data-id");
    if (!idPenjualan) {
      return;
    }
    loadUbahMemberOptions(idPenjualan);
  });

  $(document).on("click", "#btnSimpanMember", function () {
    if (!ubahMemberState.id) {
      return;
    }

    $("#btnSimpanMember").prop("disabled", true);
    $.ajax({
      url: BASE_URL + "Operasi/ubah_member",
      data: { id: ubahMemberState.id },
      type: "POST",
      dataType: "json",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res && res.status === "success") {
          try {
            var modalEl = document.getElementById("modalUbahMember");
            if (modalEl && bootstrap.Modal) {
              var instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
              instance.hide();
            }
          } catch (e) {}
          loadDiv();
        } else {
          showAlert((res && res.message) || "Gagal mengubah ke member", "error");
          $("#btnSimpanMember").prop("disabled", false);
        }
      },
      error: function () {
        showAlert("Gagal mengubah ke member", "error");
        $("#btnSimpanMember").prop("disabled", false);
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
      },
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

  // Helper: format nominal dengan pemisah ribuan (titik) - e.g. 10000 -> "10.000"
  function formatRupiahInput(val) {
    var num = String(val || "").replace(/\D/g, "");
    if (num === "") return "";
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }
  // Helper: parse nominal dari format tampilan - e.g. "10.000" -> 10000
  function parseRupiahInput(val) {
    return parseInt(String(val || "").replace(/\D/g, "") || 0, 10);
  }

  $("input#bayarBill").on("input", function () {
    var $el = $(this);
    var digitsOnly = $el.val().replace(/\D/g, "");
    var formatted = formatRupiahInput(digitsOnly);
    if ($el.val() !== formatted) {
      $el.val(formatted);
      this.setSelectionRange(formatted.length, formatted.length);
    }
    bayarBill();
  });

  $("input#bayarBill").on("keyup change", function () {
    bayarBill();
  });

  function bayarBill() {
    var dibayar = parseRupiahInput($("input#bayarBill").val());
    var kembalian = dibayar - parseInt(window.totalBill || 0, 10);
    if (kembalian > 0) {
      $("input#kembalianBill").val(formatRupiahInput(kembalian));
    } else {
      $("input#kembalianBill").val(formatRupiahInput(0));
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
        window.printServerFetch("/print", {
          text: plain,
          margin_top: marginTop,
          feed_lines: feedLines
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
            if (typeof showAlert === "function") {
              showAlert(window.printServerErrorMessage(err), "error");
            }
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
    if (config.saldoTunaiView) {
      var pelSaldo = $("select[name=p]").val();
      if (pelSaldo) {
        $("div#riwayat").load(BASE_URL + "SaldoTunai/tampilkan/" + pelSaldo);
        $("div#saldoRekap").load(BASE_URL + "SaldoTunai/tampil_rekap/0/" + pelSaldo);
      }
      return;
    }
    if (modeView != 2) {
      var pelanggan = $("select[name=pelanggan]").attr("data-id");
      $("div#load").load(BASE_URL + "Operasi/loadData/" + pelanggan + "/0");
    }
    if (modeView == 2) {
      var pelanggan = $("select[name=pelanggan]").attr("data-id");
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
        window.printServerFetch("/printqr", {
          qr_string: t,
          text: label,
          margin_top: marginTop,
          feed_lines: feedLines
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
            if (typeof showAlert === "function") {
              showAlert(window.printServerErrorMessage(err), "error");
            }
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
