/**
 * Local print server helper (PC printer_server / Android Print Bridge)
 * Tries localhost then 127.0.0.1. Always re-probes before print if not known-good.
 */
(function (window) {
  "use strict";

  var BASES = ["http://localhost:3000", "http://127.0.0.1:3000"];
  var PROBE_MS = 2000;
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
      return "Print Bridge tidak aktif di localhost:3000. Pastikan app Print Bridge Start Server, lalu refresh halaman.";
    }
    return "Print server tidak aktif di localhost:3000. Jalankan printer_server di PC.";
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      probe(false);
    });
  } else {
    probe(false);
  }
})(window);
