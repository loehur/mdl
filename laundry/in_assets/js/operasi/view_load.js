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
  $(document).off("click", ".editQty");
  $(document).off("input", "#ubahQtyInput");
  $(document).off("click", "#btnSimpanQty");
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
  $(document).off("click", ".btnNotaDetail");
  $(document).off("click", "#btnDownloadNotaDetail");

  // Cleanup orphaned modals that were moved to body in previous executions
  // This prevents Duplicate ID errors and Bootstrap confusion which causes recursive errors
  $("body > #modalAlert").remove();
  $("body > #modalQR").remove();
  $("body > #modalHapusItemNota").remove();
  $("body > #modalHapusOrderInline").remove();
  $("body > #modalNotaDetail").remove();


  // Custom Operasi modal helper (MDL theme — no Bootstrap Modal)
  window.OpModal = (function () {
    function el(id) {
      if (!id) return null;
      if (id.charAt(0) === "#") id = id.slice(1);
      return document.getElementById(id);
    }

    function syncLock() {
      var n = document.querySelectorAll(".op-modal.is-open").length;
      if (n === 0) {
        document.body.classList.remove("op-modal-open");
      } else {
        document.body.classList.add("op-modal-open");
      }
    }

    function open(id, opts) {
      opts = opts || {};
      var modal = el(id);
      if (!modal) return null;
      var isStatic = opts.static === true || modal.getAttribute("data-op-static") === "1";
      if (isStatic) {
        modal.classList.add("is-static");
        modal.setAttribute("data-op-static", "1");
      } else {
        modal.classList.remove("is-static");
      }
      if (!modal.classList.contains("is-open")) {
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        syncLock();
        try {
          modal.dispatchEvent(new CustomEvent("op-modal:open", { bubbles: true }));
        } catch (e) {}
      }
      return modal;
    }

    function close(id) {
      var modal = typeof id === "string" ? el(id) : id;
      if (!modal || !modal.classList.contains("is-open")) return;
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      syncLock();
      try {
        modal.dispatchEvent(new CustomEvent("op-modal:close", { bubbles: true }));
      } catch (e) {}
    }

    function closeAll() {
      document.querySelectorAll(".op-modal.is-open").forEach(function (m) {
        m.classList.remove("is-open");
        m.setAttribute("aria-hidden", "true");
        try {
          m.dispatchEvent(new CustomEvent("op-modal:close", { bubbles: true }));
        } catch (e) {}
      });
      syncLock();
    }

    if (!window.__opModalDelegatesBound) {
      window.__opModalDelegatesBound = true;
      document.addEventListener("click", function (e) {
        var closeBtn = e.target.closest("[data-op-close]");
        if (!closeBtn) return;
        var modal = closeBtn.closest(".op-modal");
        if (!modal) return;
        if (closeBtn.classList.contains("op-modal__backdrop") && modal.classList.contains("is-static")) {
          return;
        }
        e.preventDefault();
        if (window.OpModal) window.OpModal.close(modal);
      });
      document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        var openModals = document.querySelectorAll(".op-modal.is-open:not(.is-static)");
        if (!openModals.length) return;
        if (window.OpModal) window.OpModal.close(openModals[openModals.length - 1]);
      });
    }

    syncLock();
    return { open: open, close: close, closeAll: closeAll, syncLock: syncLock };
  })();


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
    var iconClass = "fa-info-circle";
    var title = "Informasi";
    var headClass = "op-modal__head op-modal__head--blue";

    if (type === "success") {
      iconClass = "fa-check-circle";
      title = "Berhasil";
      headClass = "op-modal__head op-modal__head--green";
    } else if (type === "warning") {
      iconClass = "fa-exclamation-triangle";
      title = "Peringatan";
      headClass = "op-modal__head op-modal__head--yellow";
    } else if (type === "error") {
      iconClass = "fa-times-circle";
      title = "Error";
      headClass = "op-modal__head op-modal__head--red";
    }

    try {
      var modalEl = document.getElementById("modalAlert");
      if (!modalEl || typeof window.OpModal === "undefined") {
        alert(message);
        return;
      }

      $("#modalAlertHead").attr("class", headClass);
      $("#modalAlertIcon").attr("class", "fas " + iconClass);
      $("#modalAlertTitle").text(title);
      $("#modalAlertMessage").css("white-space", "pre-wrap").text(message);
      window.OpModal.open("modalAlert", { static: true });
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
    $("#nTunaiBill").hide();
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

      // Store QR data — nama lengkap dari config halaman, bukan teks dropdown yang dipotong
      var fmtTotal = new Intl.NumberFormat('id-ID').format(total);
      var customerName = String(nama_pelanggan || config.namaPelanggan || nama || "")
        .trim()
        .replace(/\.{3}$/, "")
        .trim();
      if (!customerName) {
        var $optPel = $("select[name=pelanggan] option:selected");
        customerName = ($optPel.attr("data-nama") || "").trim()
          || $optPel.text().split("|")[0].trim().replace(/\.{3}$/, "").trim();
      }
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

      // Nama lengkap dari config halaman (dropdown sengaja dipotong untuk UI)
      var customerName = String(nama_pelanggan || config.namaPelanggan || nama || "")
        .trim()
        .replace(/\.{3}$/, "")
        .trim();
      if (!customerName) {
        var $optPel2 = $("select[name=pelanggan] option:selected");
        customerName = ($optPel2.attr("data-nama") || "").trim()
          || $optPel2.text().split("|")[0].trim().replace(/\.{3}$/, "").trim();
      }
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
      var pollTick = 0;
      function doPoll(syncGateway) {
        var url = BASE_URL + "Operasi/payment_gateway_status_poll/" + ref_id;
        if (syncGateway) url += "?sync=1";
        $.getJSON(url).done(function (res) {
          if (res.status === "PAID") {
            stopOperasiQRPoll();
            $("#qrcode").html('<div class="text-success text-center"><i class="fas fa-check-circle fa-5x"></i><h3 class="mt-2">LUNAS/PAID</h3></div>');
            $("#btnCekStatusQR").removeClass("btn-warning").addClass("btn-success").html('<i class="fas fa-check"></i> PAID');
            setTimeout(function () {
              if (window.OpModal) window.OpModal.close("modalQR");
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
      var onOpenQR = function () {
        if (!isDev && ref_id) {
          stopOperasiQRPoll();
          pollTick = 0;
          operasiQRPollInterval = setInterval(function () {
            pollTick += 1;
            doPoll(pollTick % 3 === 0);
          }, 3000);
          doPoll(true);
        }
        modalEl.removeEventListener("op-modal:open", onOpenQR);
      };
      var onCloseQR = function () {
        stopOperasiQRPoll();
        modalEl.removeEventListener("op-modal:close", onCloseQR);
      };
      modalEl.addEventListener("op-modal:open", onOpenQR);
      modalEl.addEventListener("op-modal:close", onCloseQR);
      if (window.OpModal) {
        window.OpModal.open("modalQR", { static: true });
      }
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
            var msg = window.printServerErrorMessage
              ? window.printServerErrorMessage(err)
              : "Print server tidak aktif. Jalankan printer server dulu.";
            if (window.PrintServer && typeof window.PrintServer.showAlert === "function") {
              window.PrintServer.showAlert(msg, "warning");
            } else if (typeof showAlert === "function") {
              showAlert(msg, "warning");
            }
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
              if (window.OpModal) window.OpModal.close("modalQR");

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

          $("#modalAlertHead").attr("class", "op-modal__head op-modal__head--blue");
          if (window.OpModal) {
            window.OpModal.open("modalAlert", { static: true });
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
    var $form = $(this);
    var action = String($form.attr("action") || "");
    var isOpSubmit = /Antrian\/(operasi|ambil)(\/|$|\?)/.test(action);
    var $btn = $form.find("button[type='submit']").first();
    var $modal = $form.find(".op-modal").first();
    var originalBtn = $btn.length ? $btn.html() : "";

    if (isOpSubmit && ($btn.data("loading") === 1 || $btn.prop("disabled"))) {
      return;
    }

    // Guard form Ubah Penyelesai
    if ($form.find("#modalGanti").length) {
      var valGanti = String($form.find("select[name='f1']").val() || "");
      var tuntasGanti = parseInt($form.data("tuntas") || "0", 10);
      var bulanOk = parseInt($form.data("bulanOk") || "0", 10) === 1;
      var keyGanti = String($form.find("input[name='access_key']").val() || "").trim();
      if (!/^\d{4}$/.test(keyGanti)) {
        showAlert("Access Key penyelesai sebelumnya wajib 4 digit.", "error");
        return;
      }
      if (valGanti === "0" || valGanti === "") {
        if (tuntasGanti === 1) {
          showAlert("Tidak dapat mengosongkan penyelesai: order sudah tuntas.", "error");
          return;
        }
      } else if (!bulanOk) {
        showAlert("Ubah penyelesai hanya untuk order bulan ini.", "error");
        return;
      }
    }

    function setOpSubmitLoading(on) {
      if (!isOpSubmit || !$btn.length) return;
      if (on) {
        $btn.data("loading", 1).addClass("is-loading").prop("disabled", true)
          .html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        $form.find("button[data-op-close]").prop("disabled", true);
        $modal.addClass("is-static");
      } else {
        $btn.data("loading", 0).removeClass("is-loading").prop("disabled", false).html(originalBtn);
        $form.find("button[data-op-close]").prop("disabled", false);
        $modal.removeClass("is-static");
      }
    }

    $.ajax({
      url: $form.attr("action"),
      data: $form.serialize(),
      type: $form.attr("method"),
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
        setOpSubmitLoading(true);
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

          if (window.OpModal) {
            window.OpModal.closeAll();
          }
          try {
            $(".offcanvas-backdrop").remove();
            $("body").removeClass("offcanvas-open").css({ overflow: "auto", "padding-right": "0" });
          } catch (e) { }
          loadDiv();
        } else {
          showAlert(res, "error");
        }
      },
      complete: function () {
        $(".loaderDiv").fadeOut("slow");
        setOpSubmitLoading(false);
      },
    });
  });

  $("form.ajax_json").on("submit", function (e) {
    e.preventDefault();

    // Cegah double-submit saat request masih jalan (bukan debounce timer)
    var $btn = $("#btnBayarBill");
    if ($btn.data("paying") === 1 || $btn.prop("disabled")) {
      return;
    }

    var karyawanBill = $("#karyawanBill").val();
    var metodeBill = $("#metodeBill").val();
    var noteBill = $("#noteBill").val();
    var idPenanggungBayar = parseInt($("#idPenanggungBayar").val() || "0", 10);

    if (idPenanggungBayar > 0 && metodeBill != "3") {
      showAlert("Tanggung bayar hanya dapat menggunakan metode Saldo Tunai.", "error");
      return;
    }

    // Tujuan non-tunai hanya relevan untuk metode Non Tunai (2)
    if (String(metodeBill) !== "2") {
      noteBill = "";
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
        $btn.data("paying", 1);
        $(".loaderDiv").fadeIn("fast");
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
      },
      success: function (res) {
        if (res == 0) {
          var reloadAfterPay = function () {
            if (window.OpModal) {
              window.OpModal.closeAll();
            }
            try {
              $(".offcanvas-backdrop").remove();
              $("body").removeClass("offcanvas-open").css({ overflow: "auto", "padding-right": "0" });
            } catch (e) { }
            loadDiv();
          };

          try {
            var offcanvasEl = document.getElementById("offcanvasPayment");
            if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
              var instance = bootstrap.Offcanvas.getInstance(offcanvasEl) || bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
              var done = false;
              var finish = function () {
                if (done) return;
                done = true;
                reloadAfterPay();
              };
              $(offcanvasEl).one("hidden.bs.offcanvas", finish);
              instance.hide();
              // Fallback: loadDiv bisa menghancurkan elemen sebelum event hidden terpanggil
              setTimeout(finish, 450);
              return;
            }
          } catch (e) { }

          reloadAfterPay();
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
        $btn.data("paying", 0);
        $btn.prop("disabled", false).html('<i class="fas fa-wallet"></i> Bayar');
      },
    });
  });

  $("span.addOperasi").on("click", function (e) {
    e.preventDefault();
    // Bukan layanan terakhir: tanpa rak & tanpa WA selesai
    $("div.letakRAK").hide();
    $("input#letakRAK").val("").prop("required", false);
    $("input#letakPack, input#letakHanger").prop("required", false);
    $("form.operasi").attr("data-operasi", "operasi");
    $("#opSelesaiHint").text("Catat karyawan penyelesai");

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
    if (!$("#modalGanti").length) {
      showAlert("Form ubah penyelesai tidak tersedia.", "error");
      return;
    }
    window.idNya = $(this).attr("data-id");
    var awal = $(this).attr("data-awal");
    var tuntas = parseInt($(this).attr("data-tuntas") || "0", 10);
    var bulanOrder = $(this).attr("data-bulan") || "";
    var now = new Date();
    var bulanSekarang =
      now.getFullYear() +
      "-" +
      String(now.getMonth() + 1).padStart(2, "0");
    var canGantiKaryawan = bulanOrder === bulanSekarang;

    $("input#id_ganti").val(window.idNya);
    $("span#awalOP").html(awal);
    $("span#gantiAccessKeyHint").text(awal || "penyelesai sebelumnya");
    $("input#gantiAccessKey").val("");

    var $formGanti = $("#modalGanti").closest("form");
    $formGanti.data("tuntas", tuntas);
    $formGanti.data("bulanOk", canGantiKaryawan ? 1 : 0);

    var selEl = document.querySelector("#modalGanti select[name='f1']");
    if (selEl && selEl.selectize) {
      var sz = selEl.selectize;
      var kosongVal = "0";
      var kosongText = "— Kosong (hapus penyelesai & notif selesai) —";

      // Backup opsi karyawan sekali (agar bisa dikembalikan antar klik)
      if (!window._gantiOperasiOptsBackup) {
        window._gantiOperasiOptsBackup = [];
        Object.keys(sz.options).forEach(function (val) {
          if (val === "" || val === kosongVal) {
            return;
          }
          window._gantiOperasiOptsBackup.push({
            value: val,
            text: sz.options[val].text,
          });
        });
      }

      // Reset opsi karyawan dari backup
      Object.keys(sz.options).forEach(function (val) {
        if (val !== "" && val !== kosongVal) {
          sz.removeOption(val);
        }
      });
      if (canGantiKaryawan) {
        window._gantiOperasiOptsBackup.forEach(function (opt) {
          sz.addOption(opt);
        });
      }

      // Opsi Kosong hanya untuk order belum tuntas
      if (tuntas === 1) {
        if (sz.options[kosongVal]) {
          sz.removeOption(kosongVal);
        }
      } else if (!sz.options[kosongVal]) {
        sz.addOption({ value: kosongVal, text: kosongText });
      }

      sz.clear();
    }
  });

  $("span.endLayanan").on("click", function (e) {
    e.preventDefault();
    // Layanan terakhir: wajib rak (+ pack/hanger), WA selesai dikirim setelah rak terisi
    $("div.letakRAK").show();
    $("input#letakRAK").prop("required", true);
    $("input#letakPack, input#letakHanger").prop("required", true);
    $("form.operasi").attr("data-operasi", "operasiSelesai");
    $("#opSelesaiHint").text("Catat karyawan & letak");
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
      $('#hapusRefText').text('#' + ref);
      $('#inputAlasanHapus').val('').css('borderColor', '#ccc');
      $('#btnHapusKonfirm').data('ref', ref);
      if (window.OpModal) {
        window.OpModal.open('modalHapusOrderInline', { static: true });
      }
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
    if (window.OpModal) window.OpModal.close('modalHapusOrderInline');
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
        if (window.OpModal) window.OpModal.close('modalHapusOrderInline');
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

  // --- Detail Nota Timeline ---
  function ndtEsc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function ndtMoney(n) {
    n = parseInt(n || 0, 10);
    if (isNaN(n)) n = 0;
    return n.toLocaleString("id-ID");
  }

  function ndtTypeClass(type, done, inferred) {
    var cls = [];
    if (done) cls.push("is-done");
    else cls.push("is-pending");
    if (inferred) cls.push("is-inferred");
    if (type === "jemput") cls.push("is-jemput");
    if (type === "antar") cls.push("is-antar");
    if (type === "ambil") cls.push("is-ambil");
    return cls.join(" ");
  }

  function renderNotaDetail(data) {
    var html = '<div id="notaDetailCapture" data-ref="' + ndtEsc(data.no_ref || "") + '" data-nama="' + ndtEsc(data.pelanggan || "") + '">';
    var lunas = !!data.lunas;
    var statusBadge = lunas
      ? '<span class="ndt-badge ndt-badge--ok">LUNAS</span>'
      : '<span class="ndt-badge ndt-badge--warn">SISA ' + ndtEsc(ndtMoney(data.sisa)) + "</span>";

    html += '<div class="ndt-summary">';
    html +=
      '<div class="ndt-summary__box ndt-summary__box--full">' +
      '<span class="ndt-summary__label">Nota</span>' +
      '<div class="ndt-summary__value">#' +
      ndtEsc(data.no_ref) +
      " " +
      statusBadge +
      "</div>" +
      '<span class="ndt-summary__meta">' +
      ndtEsc(data.pelanggan || "") +
      "</span>" +
      "</div>";

    html +=
      '<div class="ndt-summary__box">' +
      '<span class="ndt-summary__label">Dibuat</span>' +
      '<div class="ndt-summary__value">' +
      ndtEsc(data.created_at || "-") +
      "</div>" +
      '<span class="ndt-summary__meta">' +
      ndtEsc(data.created_by || "-") +
      "</span>" +
      "</div>";

    html +=
      '<div class="ndt-summary__box">' +
      '<span class="ndt-summary__label">Tagihan</span>' +
      '<div class="ndt-summary__value">' +
      ndtEsc(ndtMoney(data.total)) +
      "</div>" +
      '<span class="ndt-summary__meta">Dibayar ' +
      ndtEsc(ndtMoney(data.dibayar)) +
      "</span>" +
      "</div>";
    html += "</div>";

    html += '<div class="ndt-section"><div class="ndt-section__title">Pembayaran</div>';
    var pays = data.payments || [];
    if (!pays.length) {
      html += '<div class="ndt-empty">Belum ada pembayaran</div>';
    } else {
      pays.forEach(function (p) {
        var st = parseInt(p.status || 0, 10);
        var cancel = st === 4;
        var methodBits = [];
        if (p.method) methodBits.push(p.method);
        if (p.note) methodBits.push(p.note);
        var methodShow = methodBits.join(" · ") || "-";
        html +=
          '<div class="ndt-pay">' +
          '<div class="ndt-pay__top">' +
          "<div><span class=\"ndt-badge " +
          (cancel ? "" : st === 3 ? "ndt-badge--ok" : "ndt-badge--info") +
          '">' +
          ndtEsc(p.status_label || (cancel ? "Batal" : "Bayar")) +
          "</span></div>" +
          '<div class="ndt-pay__amount' +
          (cancel ? " is-cancel" : "") +
          '">' +
          ndtEsc(ndtMoney(p.amount)) +
          "</div>" +
          "</div>" +
          '<div class="ndt-pay__meta">' +
          ndtEsc(p.time || "-") +
          " · " +
          ndtEsc(p.user || "-") +
          " · " +
          ndtEsc(methodShow) +
          "</div>" +
          "</div>";
      });
    }
    html += "</div>";

    var surcas = data.surcas || [];
    if (surcas.length) {
      html += '<div class="ndt-section"><div class="ndt-section__title">Surcas</div>';
      surcas.forEach(function (sc) {
        html +=
          '<div class="ndt-pay">' +
          '<div class="ndt-pay__top"><div class="ndt-summary__value" style="font-size:0.84rem">' +
          ndtEsc(sc.nama || "Surcas") +
          '</div><div class="ndt-pay__amount">' +
          ndtEsc(ndtMoney(sc.jumlah)) +
          "</div></div>" +
          '<div class="ndt-pay__meta">' +
          ndtEsc(sc.time || "-") +
          " · " +
          ndtEsc(sc.user || "-") +
          "</div></div>";
      });
      html += "</div>";
    }

    html += '<div class="ndt-section"><div class="ndt-section__title">Item &amp; Timeline</div>';
    var items = data.items || [];
    if (!items.length) {
      html += '<div class="ndt-empty">Tidak ada item</div>';
    }
    items.forEach(function (it) {
      var totalShow =
        parseInt(it.member || 0, 10) === 1
          ? '<span class="ndt-badge ndt-badge--ok">Member</span>'
          : ndtEsc(ndtMoney(it.total));
      var subBits = [];
      if (it.durasi) subBits.push(it.durasi);
      if (it.qty_show) subBits.push(it.qty_show);
      if (it.letak) subBits.push("Rak " + it.letak);
      html +=
        '<div class="ndt-item">' +
        '<div class="ndt-item__head">' +
        "<div><div class=\"ndt-item__title\">#" +
        ndtEsc(it.id) +
        " " +
        ndtEsc(it.kategori || "") +
        '</div><span class="ndt-item__sub">' +
        ndtEsc(subBits.join(" · ") || "-") +
        (it.note ? " · " + ndtEsc(it.note) : "") +
        "</span></div>" +
        '<div class="ndt-item__total">' +
        totalShow +
        "</div></div>";

      var tl = it.timeline || [];
      if (!tl.length) {
        html += '<div class="ndt-empty" style="margin:8px;border:0;background:transparent">Belum ada aktivitas</div>';
      } else {
        html += '<ul class="ndt-tl">';
        tl.forEach(function (ev) {
          var inferred = !!ev.inferred;
          var done = !!ev.done;
          var meta = [];
          if (ev.time) meta.push(ev.time);
          if (ev.user) meta.push(ev.user);
          else if (done && inferred) meta.push("tanpa nama");
          else if (!done) meta.push("belum selesai");
          html +=
            '<li class="ndt-tl__row ' +
            ndtTypeClass(ev.type, done, inferred) +
            '">' +
            '<span class="ndt-tl__dot" aria-hidden="true"></span>' +
            '<span class="ndt-tl__content">' +
            '<span class="ndt-tl__label">' +
            ndtEsc(ev.label || "") +
            (inferred
              ? ' <span class="ndt-tl__chip ndt-tl__chip--inferred">isi otomatis</span>'
              : "") +
            '</span><span class="ndt-tl__meta">' +
            ndtEsc(meta.join(" · ") || "-") +
            "</span></span></li>";
        });
        html += "</ul>";
      }
      html += "</div>";
    });
    html += "</div></div>";

    return html;
  }

  function ndtSlug(name) {
    return String(name || "nota")
      .toLowerCase()
      .replace(/[^a-z0-9]+/gi, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 40) || "nota";
  }

  function downloadNotaDetailImage(btn) {
    var page = document.getElementById("notaDetailCapture");
    var body = document.getElementById("notaDetailBody");
    if (!page || !body) return;
    if (typeof html2canvas !== "function") {
      alert("Fitur gambar belum siap. Muat ulang halaman.");
      return;
    }

    var $btn = $(btn);
    var oldHtml = $btn.html();
    $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Proses');

    var prevOverflow = body.style.overflow;
    var prevMaxHeight = body.style.maxHeight;
    body.style.overflow = "visible";
    body.style.maxHeight = "none";

    html2canvas(page, {
      backgroundColor: "#ffffff",
      scale: Math.min(2, window.devicePixelRatio || 2),
      useCORS: true,
      logging: false,
    })
      .then(function (canvas) {
        var ref = page.getAttribute("data-ref") || "nota";
        var nama = page.getAttribute("data-nama") || "";
        var link = document.createElement("a");
        link.download = "nota-" + ndtSlug(ref) + (nama ? "-" + ndtSlug(nama) : "") + ".png";
        link.href = canvas.toDataURL("image/png");
        link.click();
      })
      .catch(function (err) {
        alert((err && err.message) || "Gagal membuat gambar detail nota.");
      })
      .finally(function () {
        body.style.overflow = prevOverflow;
        body.style.maxHeight = prevMaxHeight;
        $btn.prop("disabled", false).html(oldHtml);
      });
  }

  $(document).on("click", ".btnNotaDetail", function (e) {
    e.preventDefault();
    var ref = $(this).attr("data-ref") || "";
    if (!ref) return;

    $("#opNotaDetailTitle").text("Detail Nota #" + ref);
    $("#opNotaDetailSub").text("Timeline order");
    $("#btnDownloadNotaDetail").prop("disabled", true);
    $("#notaDetailBody").html(
      '<div class="ndt-loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail...</div>'
    );
    if (window.OpModal) window.OpModal.open("modalNotaDetail");
    else $("#modalNotaDetail").addClass("is-open").attr("aria-hidden", "false");

    $.ajax({
      url: BASE_URL + "Operasi/nota_detail",
      type: "GET",
      dataType: "json",
      data: { ref: ref },
      success: function (res) {
        if (!res || res.status !== "success" || !res.data) {
          $("#btnDownloadNotaDetail").prop("disabled", true);
          $("#notaDetailBody").html(
            '<div class="ndt-error">' +
              ndtEsc((res && res.message) || "Gagal memuat detail") +
              "</div>"
          );
          return;
        }
        var d = res.data;
        $("#opNotaDetailSub").text(
          (d.pelanggan || "Customer") +
            (d.lunas ? " · Lunas" : " · Sisa " + ndtMoney(d.sisa))
        );
        $("#notaDetailBody").html(renderNotaDetail(d));
        $("#btnDownloadNotaDetail").prop("disabled", false);
      },
      error: function () {
        $("#btnDownloadNotaDetail").prop("disabled", true);
        $("#notaDetailBody").html(
          '<div class="ndt-error">Gagal memuat detail (network)</div>'
        );
      },
    });
  });

  $(document).on("click", "#btnDownloadNotaDetail", function (e) {
    e.preventDefault();
    downloadNotaDetailImage(this);
  });

  // --- Hapus satu item dari nota ---
  function tutupModalHapusItem() {
    if (window.OpModal) window.OpModal.close('modalHapusItemNota');
    else $('#modalHapusItemNota').removeClass('is-open').attr('aria-hidden', 'true');
  }

  function bukaModalHapusItem(id, ref, itemName) {
    var $modal = $('#modalHapusItemNota');
    if ($modal.length === 0) {
      console.error('Modal #modalHapusItemNota tidak ditemukan!');
      return;
    }

    $('#hapusItemNama').text(itemName || ('ID ' + id));
    $('#hapusItemRef').text('#' + ref);
    $('#hapusItemNote').val('').css('border-color', '');
    $('#btnKonfirmasiHapusItem').attr('data-id', id);
    if (window.OpModal) window.OpModal.open('modalHapusItemNota', { static: true });
    else $modal.addClass('is-open').attr('aria-hidden', 'false');
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

  function tutupModalHapusSurcas() {
    if (window.OpModal) window.OpModal.close('modalHapusSurcasKurir');
    else $('#modalHapusSurcasKurir').removeClass('is-open').attr('aria-hidden', 'true');
  }

  function bukaModalHapusSurcas(id, ref, nama) {
    var $modal = $('#modalHapusSurcasKurir');
    if ($modal.length === 0) {
      console.error('Modal #modalHapusSurcasKurir tidak ditemukan!');
      return;
    }
    $('#hapusSurcasNama').text(nama || ('S' + id));
    $('#hapusSurcasRef').text('#' + ref);
    $('#hapusSurcasNote').val('').css('border-color', '');
    $('#btnKonfirmasiHapusSurcas').attr('data-id', id);
    if (window.OpModal) window.OpModal.open('modalHapusSurcasKurir', { static: true });
    else $modal.addClass('is-open').attr('aria-hidden', 'false');
    setTimeout(function () { $('#hapusSurcasNote').focus(); }, 100);
  }

  $(document).on('click', '.hapusSurcasKurir', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $button = $(this);
    bukaModalHapusSurcas(
      $button.attr('data-id'),
      $button.attr('data-ref'),
      $button.attr('data-nama')
    );
  });

  $(document).on('click', '[data-close-hapus-surcas]', function (e) {
    e.preventDefault();
    tutupModalHapusSurcas();
  });

  $(document).on('click', '#btnKonfirmasiHapusSurcas', function () {
    var $button = $(this);
    var id = $button.attr('data-id');
    var note = $('#hapusSurcasNote').val().trim();
    if (!id) {
      alert('Surcas tidak ditemukan. Muat ulang halaman lalu coba lagi.');
      return;
    }
    if (!note) {
      $('#hapusSurcasNote').css('border-color', '#dc2626').focus();
      return;
    }

    var original = $button.html();
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    $.ajax({
      url: BASE_URL + 'Operasi/hapusSurcasKurir',
      method: 'POST',
      dataType: 'json',
      data: { id_surcas: id, note: note },
      success: function (response) {
        if (response && response.status === 'success') {
          tutupModalHapusSurcas();
          loadDiv();
          return;
        }
        alert((response && response.message) || 'Surcas tidak dapat dihapus.');
      },
      error: function (xhr) {
        var msg = 'Gagal menghapus surcas. Periksa koneksi lalu coba lagi.';
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
  // --- Akhir hapus surcas Antar/Jemput ---

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

  function metodeBillIcon(id) {
    if (String(id) === "1") return "fa-money-bill-wave";
    if (String(id) === "2") return "fa-credit-card";
    if (String(id) === "3") return "fa-wallet";
    return "fa-coins";
  }

  function parseMetodeOptionText(text) {
    var raw = String(text || "").trim();
    var match = raw.match(/^(.*?)(\[\s*.*?\s*\])\s*$/);
    if (match) {
      return { name: match[1].trim(), extra: match[2].trim() };
    }
    return { name: raw, extra: "" };
  }

  function syncMetodeRadiosFromSelect() {
    var val = String($("#metodeBill").val() || "");
    var $grid = $("#metodeBillRadios");
    if (!$grid.length) return;
    $grid.find(".pay-opt").removeClass("is-selected");
    $grid.find('input[name="metodeBillRadio"]').prop("checked", false);
    var $input = $grid.find('input[name="metodeBillRadio"][value="' + val + '"]');
    if ($input.length) {
      $input.prop("checked", true);
      $input.closest(".pay-opt").addClass("is-selected");
    }
  }

  function refreshMetodeRadios() {
    var $grid = $("#metodeBillRadios");
    var $sel = $("#metodeBill");
    if (!$grid.length || !$sel.length) return;
    var current = String($sel.val() || "");
    var html = "";
    $sel.find("option").each(function () {
      var val = String($(this).val());
      var parsed = parseMetodeOptionText($(this).text());
      var selected = val === current;
      html +=
        '<label class="pay-opt' + (selected ? " is-selected" : "") + '" data-metode-id="' + val + '">' +
        '<input type="radio" name="metodeBillRadio" value="' + val + '"' + (selected ? " checked" : "") + ">" +
        '<span class="pay-opt__face">' +
        '<span class="pay-opt__icon"><i class="fas ' + metodeBillIcon(val) + '"></i></span>' +
        '<span class="pay-opt__name">' + $("<div>").text(parsed.name).html() + "</span>" +
        '<span class="pay-opt__extra">' + $("<div>").text(parsed.extra).html() + "</span>" +
        "</span></label>";
    });
    $grid.html(html);
  }

  function syncNoteRadiosFromSelect() {
    var val = String($("#noteBill").val() || "QRIS");
    var $grid = $("#noteBillRadios");
    if (!$grid.length) return;
    $grid.find(".pay-opt").removeClass("is-selected");
    $grid.find('input[name="noteBillRadio"]').prop("checked", false);
    var $input = $grid.find('input[name="noteBillRadio"][value="' + val + '"]');
    if (!$input.length) {
      $input = $grid.find('input[name="noteBillRadio"][value="QRIS"]');
      if ($input.length) {
        $("#noteBill").val("QRIS");
        val = "QRIS";
      }
    }
    if ($input.length) {
      $input.prop("checked", true);
      $input.closest(".pay-opt").addClass("is-selected");
    }
  }

  function setDefaultNoteQris() {
    var $note = $("#noteBill");
    if (!$note.length) return;
    if (!$note.find('option[value="QRIS"]').length) {
      var first = $note.find("option").first().val();
      if (first) $note.val(first);
    } else {
      $note.val("QRIS");
    }
    syncNoteRadiosFromSelect();
  }

  $(document).on("change", 'input[name="metodeBillRadio"]', function () {
    $("#metodeBill").val($(this).val()).trigger("change");
    syncMetodeRadiosFromSelect();
  });

  $(document).on("change", 'input[name="noteBillRadio"]', function () {
    $("#noteBill").val($(this).val());
    syncNoteRadiosFromSelect();
  });

  $("select.metodeBayarBill").on("keyup change", function () {
    if ($(this).val() == 2) {
      $("#nTunaiBill").show();
      $("#noteBill").prop("required", true);
      setDefaultNoteQris();
    } else {
      $("#nTunaiBill").hide();
      $("#noteBill").prop("required", false);
    }
    if ($(this).val() != "3") {
      resetTanggungBayar();
    }
    syncMetodeRadiosFromSelect();
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
    var needRefresh = false;
    if (tbMetodeSaldoOrigText !== null) {
      $("#metodeBill option[value='3']").text(tbMetodeSaldoOrigText);
      tbMetodeSaldoOrigText = null;
      needRefresh = true;
    }
    if (tbMetodeSaldoAdded) {
      $("#metodeBill option[value='3']").remove();
      tbMetodeSaldoAdded = false;
      needRefresh = true;
      refreshMetodeRadios();
      var $metode = $("#metodeBill");
      if ($metode.find("option").length > 0) {
        $metode.val($metode.find("option:first").val()).trigger("change");
      }
      return;
    }
    if (needRefresh) {
      refreshMetodeRadios();
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
    $sel.val("3");
    refreshMetodeRadios();
    $sel.trigger("change");
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
    if (window.OpModal) {
      window.OpModal.open("modalTanggungBayar", { static: true });
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
    if (window.OpModal) window.OpModal.close("modalTanggungBayar");
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
            if (window.OpModal) window.OpModal.close("modalUbahDurasi");
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
            if (window.OpModal) window.OpModal.close("modalUbahDurasi");
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

  var ubahQtyState = {
    id: 0,
    qty: 0,
    min_order: 0,
    harga: 0,
    diskon_qty: 0,
    diskon_partner: 0,
    dibayar: 0,
    current_ref_total: 0,
    current_item_total: 0,
    member: 0,
  };

  function calcUbahQtyPreviewTotals(qty) {
    if (ubahQtyState.member) {
      return {
        itemTotal: ubahQtyState.current_item_total || 0,
        refTotal: ubahQtyState.current_ref_total || 0,
      };
    }
    var minOrder = ubahQtyState.min_order || 0;
    var qtyReal = qty < minOrder ? minOrder : qty;
    var total = (ubahQtyState.harga || 0) * qtyReal;
    var dq = ubahQtyState.diskon_qty || 0;
    var dp = ubahQtyState.diskon_partner || 0;
    if (dq > 0) total -= total * (dq / 100);
    if (dp > 0) total -= total * (dp / 100);
    var itemTotal = Math.round(total);
    var delta = itemTotal - (ubahQtyState.current_item_total || 0);
    var refTotal = (ubahQtyState.current_ref_total || 0) + delta;
    return { itemTotal: itemTotal, refTotal: refTotal };
  }

  function updateUbahQtyPreview() {
    var raw = String($("#ubahQtyInput").val() || "").replace(",", ".");
    var qty = parseFloat(raw);
    $("#ubahQtyAlert").addClass("d-none").text("");

    if (!(qty > 0)) {
      $("#btnSimpanQty").prop("disabled", true);
      $("#ubahQtyItemHarga").text("-");
      $("#ubahQtyRefTotal").text("-");
      return;
    }

    var prev = calcUbahQtyPreviewTotals(qty);
    $("#ubahQtyItemHarga").text("Rp" + formatRp(prev.itemTotal));
    $("#ubahQtyRefTotal").text("Rp" + formatRp(prev.refTotal));

    if (ubahQtyState.dibayar > 0 && prev.refTotal < ubahQtyState.dibayar) {
      $("#ubahQtyAlert")
        .removeClass("d-none")
        .text(
          "Total order setelah perubahan kurang dari pembayaran Cek/Berhasil (Rp" +
            formatRp(ubahQtyState.dibayar) +
            ")"
        );
      $("#btnSimpanQty").prop("disabled", true);
      return;
    }

    var same = Math.abs(qty - (ubahQtyState.qty || 0)) < 0.001;
    $("#btnSimpanQty").prop("disabled", same);
  }

  function loadUbahQtyInfo(idPenjualan) {
    ubahQtyState = {
      id: idPenjualan,
      qty: 0,
      min_order: 0,
      harga: 0,
      diskon_qty: 0,
      diskon_partner: 0,
      dibayar: 0,
      current_ref_total: 0,
      current_item_total: 0,
      member: 0,
    };
    $("#ubahQtyLoading").removeClass("d-none");
    $("#ubahQtyContent").addClass("d-none");
    $("#ubahQtyAlert").addClass("d-none").text("");
    $("#btnSimpanQty").prop("disabled", true);

    $.ajax({
      url: BASE_URL + "Operasi/qty_info",
      data: { id: idPenjualan },
      type: "POST",
      dataType: "json",
      success: function (res) {
        $("#ubahQtyLoading").addClass("d-none");
        if (!res || res.status !== "success") {
          showAlert((res && res.message) || "Gagal memuat quantity", "error");
          try {
            if (window.OpModal) window.OpModal.close("modalUbahQty");
          } catch (e) {}
          return;
        }

        ubahQtyState.id = res.id_penjualan || idPenjualan;
        ubahQtyState.qty = parseFloat(res.qty) || 0;
        ubahQtyState.min_order = parseFloat(res.min_order) || 0;
        ubahQtyState.harga = parseFloat(res.harga) || 0;
        ubahQtyState.diskon_qty = parseFloat(res.diskon_qty) || 0;
        ubahQtyState.diskon_partner = parseFloat(res.diskon_partner) || 0;
        ubahQtyState.dibayar = res.dibayar || 0;
        ubahQtyState.current_ref_total = res.current_ref_total || 0;
        ubahQtyState.current_item_total = res.current_item_total || 0;
        ubahQtyState.member = res.member || 0;

        $("#ubahQtyItem").text("#" + res.id_penjualan + " " + (res.kategori || ""));
        $("#ubahQtyInfo").text("REF #" + (res.ref || "") + " — qty saat ini " + ubahQtyState.qty);
        $("#ubahQtySatuan").text(res.satuan || "");
        $("#ubahQtyInput").val(ubahQtyState.qty);

        if (ubahQtyState.min_order > 0) {
          $("#ubahQtyMinHint")
            .removeClass("d-none")
            .text("Min. order tagihan: " + ubahQtyState.min_order + (res.satuan ? " " + res.satuan : ""));
        } else {
          $("#ubahQtyMinHint").addClass("d-none").text("");
        }

        if (res.dibayar > 0) {
          $("#ubahQtyBayarInfo").removeClass("d-none");
          $("#ubahQtyDibayar").text("Rp" + formatRp(res.dibayar));
        } else {
          $("#ubahQtyBayarInfo").addClass("d-none");
        }

        $("#ubahQtyContent").removeClass("d-none");
        updateUbahQtyPreview();
        setTimeout(function () {
          $("#ubahQtyInput").trigger("focus").select();
        }, 50);
      },
      error: function () {
        $("#ubahQtyLoading").addClass("d-none");
        showAlert("Gagal memuat quantity", "error");
        try {
          if (window.OpModal) window.OpModal.close("modalUbahQty");
        } catch (e) {}
      },
    });
  }

  $(document).on("click", ".editQty", function (e) {
    e.preventDefault();
    var idPenjualan = $(this).attr("data-id");
    if (!idPenjualan) {
      return;
    }
    loadUbahQtyInfo(idPenjualan);
  });

  $(document).on("input", "#ubahQtyInput", function () {
    updateUbahQtyPreview();
  });

  $(document).on("click", "#btnSimpanQty", function () {
    var raw = String($("#ubahQtyInput").val() || "").replace(",", ".");
    var qty = parseFloat(raw);
    if (!ubahQtyState.id || !(qty > 0)) {
      return;
    }

    $("#btnSimpanQty").prop("disabled", true);
    $.ajax({
      url: BASE_URL + "Operasi/ubah_qty",
      data: {
        id: ubahQtyState.id,
        qty: qty,
      },
      type: "POST",
      dataType: "json",
      beforeSend: function () {
        $(".loaderDiv").fadeIn("fast");
      },
      success: function (res) {
        if (res && res.status === "success") {
          try {
            if (window.OpModal) window.OpModal.close("modalUbahQty");
          } catch (e) {}
          loadDiv();
        } else {
          showAlert((res && res.message) || "Gagal mengubah quantity", "error");
          updateUbahQtyPreview();
        }
      },
      error: function () {
        showAlert("Gagal mengubah quantity", "error");
        updateUbahQtyPreview();
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
            if (window.OpModal) window.OpModal.close("modalUbahLayanan");
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
            if (window.OpModal) window.OpModal.close("modalUbahLayanan");
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
            if (window.OpModal) window.OpModal.close("modalUbahMember");
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
            if (window.OpModal) window.OpModal.close("modalUbahMember");
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

    // Cetak hanya via Print Server / Print Bridge — tanpa fallback browser/bluetooth/serial
    lines = lines.filter(function (s) {
      var x = String(s || "");
      if (x.indexOf("[[TR]]") === -1) return true;
      var inner = x.replace(/\[\[(?:\/)?(?:TR|TD)\]\]/g, "");
      return inner.trim().length > 0;
    });
    var plain = lines
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
      .join("");

    var printFn =
      window.PrintServer && typeof window.PrintServer.fetch === "function"
        ? window.PrintServer.fetch.bind(window.PrintServer)
        : window.printServerFetch;
    var errMsg =
      window.PrintServer && typeof window.PrintServer.errorMessage === "function"
        ? window.PrintServer.errorMessage
        : window.printServerErrorMessage;
    var warnFn =
      window.PrintServer && typeof window.PrintServer.showAlert === "function"
        ? window.PrintServer.showAlert
        : typeof showAlert === "function"
          ? showAlert
          : null;

    if (typeof printFn !== "function") {
      if (warnFn) warnFn("Print server tidak tersedia. Jalankan printer server dulu.", "warning");
      return;
    }

    printFn("/print", {
      text: plain,
      margin_top: marginTop,
      feed_lines: feedLines,
    })
      .then(function (res) {
        return res.text().catch(function () {
          return "";
        });
      })
      .then(function () {
        if (window.MdlToast) MdlToast.ok("Nota dikirim ke printer");
        loadDiv();
      })
      .catch(function (err) {
        var msg =
          typeof errMsg === "function"
            ? errMsg(err)
            : "Print server tidak aktif. Jalankan printer server dulu.";
        if (warnFn) warnFn(msg, "warning");
      });
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

    // Cetak QR hanya via Print Server / Print Bridge
    var printFn =
      window.PrintServer && typeof window.PrintServer.fetch === "function"
        ? window.PrintServer.fetch.bind(window.PrintServer)
        : window.printServerFetch;
    var errMsg =
      window.PrintServer && typeof window.PrintServer.errorMessage === "function"
        ? window.PrintServer.errorMessage
        : window.printServerErrorMessage;
    var warnFn =
      window.PrintServer && typeof window.PrintServer.showAlert === "function"
        ? window.PrintServer.showAlert
        : typeof showAlert === "function"
          ? showAlert
          : null;

    if (typeof printFn !== "function") {
      if (warnFn) warnFn("Print server tidak tersedia. Jalankan printer server dulu.", "warning");
      return;
    }

    printFn("/printqr", {
      qr_string: t,
      text: label,
      margin_top: marginTop,
      feed_lines: feedLines,
    })
      .then(function (res) {
        return res.text().catch(function () {
          return "";
        });
      })
      .then(function () {
        if (window.MdlToast) MdlToast.ok("QR dikirim ke printer");
      })
      .catch(function (err) {
        var msg =
          typeof errMsg === "function"
            ? errMsg(err)
            : "Print server tidak aktif. Jalankan printer server dulu.";
        if (warnFn) warnFn(msg, "warning");
      });
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
    if (window.OpModal) {
      window.OpModal.open('modalCancelPayment', { static: true });
    }
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
        if (window.OpModal) window.OpModal.close('modalCancelPayment');
        if (response.status === 'success' || response.status === 'paid') {
          if (response.status === 'paid') {
            var warnFn = typeof showAlert === 'function' ? showAlert : null;
            if (warnFn) warnFn(response.msg || 'Pembayaran sudah berhasil di QRIS. Status diperbarui.', 'success');
            else if (typeof alert === 'function') alert(response.msg || 'Pembayaran sudah berhasil');
          }
          loadDiv();
        } else if (response.msg) {
          var warnFn = typeof showAlert === 'function' ? showAlert : null;
          if (warnFn) warnFn(response.msg, 'warning');
          else if (typeof alert === 'function') alert(response.msg);
        }
      },
      error: function (xhr, status, error) {
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
})();
