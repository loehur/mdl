/**
 * Local print server helper (PC printer_server / Android Print Bridge)
 * Endpoint: http://localhost:3000
 */
(function (window) {
  "use strict";

  var BASE = "http://localhost:3000";
  var PROBE_MS = 800;
  var PRINT_MS = 3000;
  var CACHE_MS = 30000;

  var state = {
    ready: null, // null = unknown, true/false after probe
    checkedAt: 0,
    probing: null,
  };

  function isAndroid() {
    return /Android/i.test(navigator.userAgent || "");
  }

  function offlineMessage() {
    if (isAndroid()) {
      return "Print Bridge tidak aktif di localhost:3000. Buka app Print Bridge lalu Start Server.";
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
    var opts = options || {};
    opts.signal = controller.signal;
    return fetch(url, opts).finally(function () {
      clearTimeout(timer);
    });
  }

  function probe(force) {
    var now = Date.now();
    if (
      !force &&
      state.ready !== null &&
      now - state.checkedAt < CACHE_MS
    ) {
      return Promise.resolve(state.ready);
    }
    if (state.probing) {
      return state.probing;
    }

    state.probing = fetchWithTimeout(
      BASE + "/health",
      { method: "GET", cache: "no-store" },
      PROBE_MS
    )
      .then(function (res) {
        state.ready = !!(res && res.ok);
        state.checkedAt = Date.now();
        return state.ready;
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
   * Ensure server is up.
   * - cache true + fresh → OK
   * - cache false + fresh → fail instantly (0 lag)
   * - unknown / stale → probe (800ms)
   */
  function ensureReady() {
    var now = Date.now();
    var fresh = state.ready !== null && now - state.checkedAt < CACHE_MS;

    if (fresh && state.ready === true) {
      return Promise.resolve(true);
    }
    if (fresh && state.ready === false) {
      var offline = new Error("PRINT_SERVER_OFFLINE");
      offline.name = "PrintServerOffline";
      return Promise.reject(offline);
    }

    // null or stale → re-probe
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
    if (now - state.checkedAt >= CACHE_MS) return null;
    return state.ready;
  }

  function printFetch(path, bodyObj, timeoutMs) {
    timeoutMs = timeoutMs || PRINT_MS;
    return ensureReady().then(function () {
      return fetchWithTimeout(
        BASE + path,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(bodyObj || {}),
        },
        timeoutMs
      ).then(function (res) {
        // Mark offline if connection somehow fails status-wise after ensure
        if (!res.ok && res.status === 0) {
          state.ready = false;
          state.checkedAt = Date.now();
        }
        return res;
      });
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

  // Back-compat aliases used by view_load.js
  window.printServerFetch = function (path, bodyObj, timeoutMs) {
    return printFetch(path, bodyObj, timeoutMs);
  };
  window.printServerErrorMessage = errorMessage;
  window.printServerProbe = probe;
  window.printServerEnsureReady = ensureReady;
  window.isPrintServerReady = isPrintServerReady;

  // Background probe on load (non-blocking)
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      probe(false);
    });
  } else {
    probe(false);
  }
})(window);
