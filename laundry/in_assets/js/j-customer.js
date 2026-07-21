(function () {
  var app = document.getElementById('jApp');
  if (!app) return;

  var base = app.getAttribute('data-base') || '/';
  var pelangganId = app.getAttribute('data-id');
  var content = document.getElementById('jContent');
  var reqToken = 0;

  function loadingHtml() {
    return '<div class="j-loading"><div class="j-spinner"></div><span>Memuat data...</span></div>';
  }

  function setActiveNav(page) {
    var navKey = page === 'paketDetail' ? 'paket' : page;
    document.querySelectorAll('.j-nav a').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-nav') === navKey);
    });
  }

  function pageUrl(page, extra) {
    if (page === 'home') return base + 'J/' + pelangganId;
    if (page === 'paketDetail') return base + 'J/paketDetail/' + pelangganId + '/' + extra;
    return base + 'J/' + page + '/' + pelangganId;
  }

  function loadUrl(page, extra) {
    var url = base + 'J/load/' + page + '/' + pelangganId;
    if (page === 'paketDetail' && extra) url += '/' + extra;
    return url;
  }

  function loadPage(page, extra, push) {
    page = page || 'home';
    extra = extra || '';
    var token = ++reqToken;

    content.innerHTML = loadingHtml();
    content.classList.remove('j-fade-in');
    setActiveNav(page);

    if (push) {
      history.pushState({ page: page, extra: extra }, '', pageUrl(page, extra));
    }

    fetch(loadUrl(page, extra), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Gagal memuat');
        return res.text();
      })
      .then(function (html) {
        if (token !== reqToken) return;
        document.querySelectorAll('body > #jInvoicePreview').forEach(function (el) {
          el.remove();
        });
        document.body.style.overflow = '';
        content.innerHTML = html;
        content.classList.add('j-fade-in');
        window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
      })
      .catch(function () {
        if (token !== reqToken) return;
        content.innerHTML =
          '<div class="j-empty"><b>Gagal memuat</b>Periksa koneksi, lalu coba lagi.<br><br>' +
          '<button type="button" class="j-btn j-btn-primary" id="jRetry">Coba lagi</button></div>';
        var btn = document.getElementById('jRetry');
        if (btn) {
          btn.addEventListener('click', function () {
            loadPage(page, extra, false);
          });
        }
      });
  }

  window.JApp = {
    loadPage: loadPage,
    reload: function () {
      var st = history.state || parsePath();
      loadPage(st.page || 'home', st.extra || '', false);
    }
  };

  function parsePath() {
    var path = window.location.pathname;
    var mDetail = path.match(/\/J\/paketDetail\/(\d+)\/(\d+)/i);
    if (mDetail) return { page: 'paketDetail', extra: mDetail[2] };
    var mPage = path.match(/\/J\/(tagihan|saldo|paket)\/(\d+)/i);
    if (mPage) return { page: mPage[1], extra: '' };
    return { page: 'home', extra: '' };
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (!a) return;

    var nav = a.getAttribute('data-nav');
    if (nav) {
      e.preventDefault();
      loadPage(nav, '', true);
      return;
    }

    var href = a.getAttribute('href') || '';
    if (href.indexOf(base + 'J/') !== 0 && href.indexOf('/J/') === -1) return;

    var mDetail = href.match(/J\/paketDetail\/(\d+)\/(\d+)/i);
    if (mDetail && String(mDetail[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('paketDetail', mDetail[2], true);
      return;
    }

    var mPage = href.match(/J\/(tagihan|saldo|paket)\/(\d+)/i);
    if (mPage && String(mPage[2]) === String(pelangganId)) {
      e.preventDefault();
      loadPage(mPage[1], '', true);
      return;
    }

    var mHome = href.match(/J\/(\d+)\/?$/i);
    if (mHome && String(mHome[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('home', '', true);
    }
  });

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.page) {
      loadPage(e.state.page, e.state.extra || '', false);
    } else {
      var parsed = parsePath();
      loadPage(parsed.page, parsed.extra, false);
    }
  });

  document.addEventListener('click', function (e) {
    var openPreview = e.target.closest('#jOpenPreview');
    if (openPreview) {
      var overlay = document.getElementById('jInvoicePreview');
      if (!overlay) return;
      e.preventDefault();
      if (overlay.parentElement !== document.body) {
        document.body.appendChild(overlay);
      }
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';
      return;
    }

    var closePreview = e.target.closest('#jClosePreview, .j-preview-close');
    if (closePreview || (e.target.id === 'jInvoicePreview')) {
      var ov = document.getElementById('jInvoicePreview');
      if (!ov || ov.hidden) return;
      if (closePreview || e.target === ov) {
        ov.hidden = true;
        document.body.style.overflow = '';
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var ov = document.getElementById('jInvoicePreview');
    if (!ov || ov.hidden) return;
    ov.hidden = true;
    document.body.style.overflow = '';
  });

  var initialPage = app.getAttribute('data-page') || 'home';
  var initialExtra = app.getAttribute('data-extra') || '';
  history.replaceState({ page: initialPage, extra: initialExtra }, '', pageUrl(initialPage, initialExtra));
  loadPage(initialPage, initialExtra, false);
})();
