/**
 * Local print server helper (PC printer_server / Android Print Bridge)
 * Tries localhost then 127.0.0.1. Always re-probes before print if not known-good.
 */
(function (window) {
  "use strict";

  var BASES = ["http://localhost:3000", "http://127.0.0.1:3000"];
  // Health hanya diperiksa saat benar-benar mencetak. Local bridge yang mati
  // seharusnya tidak menambah request/timeout pada load halaman Operasi.
  var PROBE_MS = 900;
  var PRINT_MS = 8000;
  var CACHE_OK_MS = 30000;
  var CACHE_FAIL_MS = 3000; // short — allow quick retry after Start Server

  var state = {
    ready: null,
    base: BASES[0],
    checkedAt: 0,
    probing: null,
  };

  function isAndroid() {
    return /Android/i.test(navigator.userAgent || "");
  }

  function offlineMessage() {
    if (isAndroid()) {
      return "Print Bridge tidak aktif di localhost:3000.\nPastikan app Print Bridge sudah Start Server, lalu coba cetak lagi.";
    }
    return "Print server tidak aktif di localhost:3000.\nJalankan printer_server di PC kasir terlebih dahulu, lalu coba cetak lagi.";
  }

  function errorMessage(err) {
    var base = offlineMessage();
    if (err && err.name === "AbortError") {
      return "Timeout: " + base;
    }
    if (err && err.message === "PRINT_SERVER_OFFLINE") {
      return base;
    }
    return base;
  }

  /**
   * Themed alert modal (MDL UI) — replaces native alert for print errors.
   */
  function ensureAlertModal() {
    if (document.getElementById("mdlPrintAlert")) return;

    if (!document.getElementById("mdlPrintAlertStyle")) {
      var style = document.createElement("style");
      style.id = "mdlPrintAlertStyle";
      style.textContent = [
        "#mdlPrintAlert{position:fixed;inset:0;z-index:5300;display:none;align-items:center;justify-content:center;padding:16px;}",
        "#mdlPrintAlert.is-open{display:flex;}",
        "#mdlPrintAlert .mdl-pa__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);}",
        "#mdlPrintAlert .mdl-pa__panel{position:relative;z-index:1;width:min(400px,100%);background:#fff;border-radius:0;box-shadow:0 24px 48px rgba(15,23,42,.3);overflow:hidden;animation:mdlPaIn .18s ease-out;}",
        "@keyframes mdlPaIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}",
        "#mdlPrintAlert .mdl-pa__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;color:#fff;}",
        "#mdlPrintAlert .mdl-pa__head--red{background:linear-gradient(105deg,#b91c1c 0%,#dc2626 100%);}",
        "#mdlPrintAlert .mdl-pa__head--yellow{background:linear-gradient(105deg,#d97706 0%,#f59e0b 100%);color:#111;}",
        "#mdlPrintAlert .mdl-pa__head--blue{background:linear-gradient(105deg,#1d4ed8 0%,#2563eb 100%);}",
        "#mdlPrintAlert .mdl-pa__head h3{margin:0;font-size:16px;font-weight:900;letter-spacing:-.02em;font-family:'fontku','Segoe UI',sans-serif;}",
        "#mdlPrintAlert .mdl-pa__close{width:34px;height:34px;border:0;border-radius:0;background:rgba(255,255,255,.2);color:inherit;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;}",
        "#mdlPrintAlert .mdl-pa__body{padding:14px 16px;background:linear-gradient(180deg,#fef2f2,#fff);font-family:'fontku','Segoe UI',sans-serif;font-size:14px;font-weight:750;color:#0f172a;white-space:pre-wrap;}",
        "#mdlPrintAlert .mdl-pa__foot{display:flex;justify-content:flex-end;padding:12px 16px 16px;border-top:1px solid #e2e8f0;}",
        "#mdlPrintAlert .mdl-pa__btn{border:0;border-radius:0;padding:12px 14px;font-family:'fontku','Segoe UI',sans-serif;font-size:.95rem;font-weight:900;cursor:pointer;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;}",
      ].join("");
      document.head.appendChild(style);
    }

    var wrap = document.createElement("div");
    wrap.id = "mdlPrintAlert";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML =
      '<div class="mdl-pa__backdrop" data-mdl-pa-close></div>' +
      '<div class="mdl-pa__panel" role="dialog" aria-modal="true" aria-labelledby="mdlPrintAlertTitle">' +
      '  <div class="mdl-pa__head mdl-pa__head--red" id="mdlPrintAlertHead">' +
      '    <h3 id="mdlPrintAlertTitle"><i class="fas fa-times-circle" id="mdlPrintAlertIcon"></i> <span id="mdlPrintAlertTitleText">Error</span></h3>' +
      '    <button type="button" class="mdl-pa__close" data-mdl-pa-close aria-label="Tutup"><i class="fas fa-times"></i></button>' +
      "  </div>" +
      '  <div class="mdl-pa__body" id="mdlPrintAlertMessage"></div>' +
      '  <div class="mdl-pa__foot"><button type="button" class="mdl-pa__btn" data-mdl-pa-close>OK</button></div>' +
      "</div>";
    document.body.appendChild(wrap);

    wrap.addEventListener("click", function (e) {
      if (e.target && e.target.closest && e.target.closest("[data-mdl-pa-close]")) {
        closeAlert();
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && wrap.classList.contains("is-open")) {
        closeAlert();
      }
    });
  }

  function closeAlert() {
    var el = document.getElementById("mdlPrintAlert");
    if (!el) return;
    el.classList.remove("is-open");
    el.setAttribute("aria-hidden", "true");
  }

  function showAlert(message, type) {
    type = type || "warning";
    // Prefer Operasi OpModal if available on the same page
    if (typeof window.showAlert === "function" && window.showAlert !== showAlert) {
      try {
        window.showAlert(message, type);
        return;
      } catch (e) {}
    }
    if (typeof window.OpModal !== "undefined" && document.getElementById("modalAlert")) {
      try {
        var headClass = "op-modal__head op-modal__head--red";
        var iconClass = "fa-times-circle";
        var title = "Error";
        if (type === "warning" || type === "warn") {
          headClass = "op-modal__head op-modal__head--yellow";
          iconClass = "fa-exclamation-triangle";
          title = "Print Server";
        } else if (type === "info" || type === "success") {
          headClass = "op-modal__head op-modal__head--blue";
          iconClass = "fa-info-circle";
          title = "Informasi";
        }
        if (window.jQuery) {
          window.jQuery("#modalAlertHead").attr("class", headClass);
          window.jQuery("#modalAlertIcon").attr("class", "fas " + iconClass);
          window.jQuery("#modalAlertTitle").text(title);
          window.jQuery("#modalAlertMessage").css("white-space", "pre-wrap").text(message);
        }
        window.OpModal.open("modalAlert", { static: true });
        return;
      } catch (e2) {}
    }

    ensureAlertModal();
    var head = document.getElementById("mdlPrintAlertHead");
    var icon = document.getElementById("mdlPrintAlertIcon");
    var titleEl = document.getElementById("mdlPrintAlertTitleText");
    var msgEl = document.getElementById("mdlPrintAlertMessage");
    var wrap = document.getElementById("mdlPrintAlert");
    var headCls = "mdl-pa__head mdl-pa__head--red";
    var iconCls = "fas fa-times-circle";
    var title = "Error";
    if (type === "warning" || type === "warn") {
      headCls = "mdl-pa__head mdl-pa__head--yellow";
      iconCls = "fas fa-exclamation-triangle";
      title = "Print Server";
    } else if (type === "info" || type === "success") {
      headCls = "mdl-pa__head mdl-pa__head--blue";
      iconCls = "fas fa-info-circle";
      title = "Informasi";
    }
    if (head) head.className = headCls;
    if (icon) icon.className = iconCls;
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message || "";
    if (wrap) {
      wrap.classList.add("is-open");
      wrap.setAttribute("aria-hidden", "false");
    }
  }

  function fetchWithTimeout(url, options, timeoutMs) {
    var controller = new AbortController();
    var timer = setTimeout(function () {
      controller.abort();
    }, timeoutMs);
    var opts = Object.assign({}, options || {}, { signal: controller.signal });
    return fetch(url, opts).finally(function () {
      clearTimeout(timer);
    });
  }

  function probeOne(base) {
    return fetchWithTimeout(
      base + "/health",
      { method: "GET", cache: "no-store", mode: "cors" },
      PROBE_MS
    ).then(function (res) {
      if (res && res.ok) return base;
      throw new Error("health not ok");
    });
  }

  function probe(force) {
    var now = Date.now();
    var ttl = state.ready === true ? CACHE_OK_MS : CACHE_FAIL_MS;
    if (
      !force &&
      state.ready !== null &&
      now - state.checkedAt < ttl
    ) {
      return Promise.resolve(state.ready);
    }
    if (state.probing) {
      return state.probing;
    }

    // Prefer last known good base first
    var order = [state.base].concat(
      BASES.filter(function (b) {
        return b !== state.base;
      })
    );

    state.probing = order
      .reduce(function (p, base) {
        return p.catch(function () {
          return probeOne(base);
        });
      }, Promise.reject())
      .then(function (base) {
        state.base = base;
        state.ready = true;
        state.checkedAt = Date.now();
        return true;
      })
      .catch(function () {
        state.ready = false;
        state.checkedAt = Date.now();
        return false;
      })
      .finally(function () {
        state.probing = null;
      });

    return state.probing;
  }

  /**
   * Before print: if recently OK, skip; otherwise always re-probe
   * (including after a short fail cache).
   */
  function ensureReady() {
    var now = Date.now();
    if (state.ready === true && now - state.checkedAt < CACHE_OK_MS) {
      return Promise.resolve(true);
    }
    return probe(true).then(function (ok) {
      if (!ok) {
        var err = new Error("PRINT_SERVER_OFFLINE");
        err.name = "PrintServerOffline";
        throw err;
      }
      return true;
    });
  }

  function isPrintServerReady() {
    var now = Date.now();
    if (state.ready === null) return null;
    var ttl = state.ready === true ? CACHE_OK_MS : CACHE_FAIL_MS;
    if (now - state.checkedAt >= ttl) return null;
    return state.ready;
  }

  function printFetch(path, bodyObj, timeoutMs) {
    timeoutMs = timeoutMs || PRINT_MS;
    return ensureReady().then(function () {
      return fetchWithTimeout(
        state.base + path,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(bodyObj || {}),
          mode: "cors",
        },
        timeoutMs
      );
    }).catch(function (err) {
      if (
        err &&
        (err.name === "AbortError" ||
          err.name === "TypeError" ||
          err.name === "PrintServerOffline")
      ) {
        state.ready = false;
        state.checkedAt = Date.now();
      }
      throw err;
    });
  }

  function markOffline() {
    state.ready = false;
    state.checkedAt = Date.now();
  }

  function getCachedReady() {
    return state.ready;
  }

  window.PrintServer = {
    probe: probe,
    ensureReady: ensureReady,
    fetch: printFetch,
    errorMessage: errorMessage,
    offlineMessage: offlineMessage,
    isAndroid: isAndroid,
    markOffline: markOffline,
    getCachedReady: getCachedReady,
    isPrintServerReady: isPrintServerReady,
    showAlert: showAlert,
    PRINT_MS: PRINT_MS,
    PROBE_MS: PROBE_MS,
  };

  window.printServerFetch = function (path, bodyObj, timeoutMs) {
    return printFetch(path, bodyObj, timeoutMs);
  };
  window.printServerErrorMessage = errorMessage;
  window.printServerProbe = probe;
  window.printServerEnsureReady = ensureReady;
  window.isPrintServerReady = isPrintServerReady;
  window.showPrintServerAlert = showAlert;

})(window);
