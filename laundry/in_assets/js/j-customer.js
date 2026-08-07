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
    var navKey = (page === 'paketDetail' || page === 'topup') ? 'paket' : page;
    document.querySelectorAll('.j-nav a').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-nav') === navKey);
    });
  }

  function pageUrl(page, extra) {
    if (page === 'home') return base + 'J/' + pelangganId;
    if (page === 'paketDetail') return base + 'J/paketDetail/' + pelangganId + '/' + extra;
    if (page === 'topup') {
      var url = base + 'J/topup/' + pelangganId;
      if (extra && String(extra) !== '0') url += '/' + extra;
      return url;
    }
    return base + 'J/' + page + '/' + pelangganId;
  }

  function loadUrl(page, extra) {
    var url = base + 'J/load/' + page + '/' + pelangganId;
    if ((page === 'paketDetail' || page === 'topup') && extra && String(extra) !== '0') {
      url += '/' + extra;
    }
    return url;
  }

  function toast(msg, type) {
    var t = document.getElementById('jToast');
    if (!t) {
      alert(msg);
      return;
    }
    t.textContent = msg;
    t.className = 'j-toast show ' + (type || '');
    clearTimeout(t._timer);
    t._timer = setTimeout(function () {
      t.className = 'j-toast';
    }, 2800);
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
    var mTopupFilter = path.match(/\/J\/topup\/(\d+)\/(\d+)/i);
    if (mTopupFilter) return { page: 'topup', extra: mTopupFilter[2] };
    var mTopup = path.match(/\/J\/topup\/(\d+)\/?$/i);
    if (mTopup) return { page: 'topup', extra: '' };
    var mPage = path.match(/\/J\/(tagihan|saldo|paket|kurir)\/(\d+)/i);
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

    var mTopupFilter = href.match(/J\/topup\/(\d+)\/(\d+)/i);
    if (mTopupFilter && String(mTopupFilter[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('topup', mTopupFilter[2], true);
      return;
    }

    var mTopup = href.match(/J\/topup\/(\d+)\/?$/i);
    if (mTopup && String(mTopup[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('topup', '', true);
      return;
    }

    var mPage = href.match(/J\/(tagihan|saldo|paket|kurir)\/(\d+)/i);
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

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.j-topup-pick');
    if (!btn || !content.contains(btn)) return;
    e.preventDefault();

    var idPaket = btn.getAttribute('data-id-harga-paket');
    if (!idPaket) return;
    if (btn.disabled) return;

    var label = btn.getAttribute('data-label') || 'paket ini';
    pendingTopup = { idPaket: idPaket, btn: btn };

    var info = document.getElementById('jTopupConfirmInfo');
    if (info) info.textContent = label;

    var modalEl = document.getElementById('jModalTopup');
    if (!modalEl || !window.bootstrap) {
      if (!window.confirm('Topup ' + label + '?')) {
        pendingTopup = null;
        return;
      }
      submitTopup();
      return;
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });

  var pendingTopup = null;

  function submitTopup() {
    if (!pendingTopup) return;
    var idPaket = pendingTopup.idPaket;
    var btn = pendingTopup.btn;
    pendingTopup = null;
    if (!btn || !idPaket) return;

    btn.disabled = true;
    var body = new URLSearchParams();
    body.set('id_harga_paket', idPaket);

    fetch(base + 'J/topupSubmit/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          var err = new Error((data && data.message) || 'Gagal topup');
          err.go = data && data.go ? data.go : '';
          throw err;
        }
        toast(data.message || 'Berhasil', 'ok');
        var go = data.go === 'paket' ? 'paket' : 'tagihan';
        loadPage(go, '', true);
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal topup', 'warn');
        btn.disabled = false;
        if (err && err.go === 'tagihan') {
          loadPage('tagihan', '', true);
        }
      });
  }

  document.addEventListener('click', function (e) {
    var confirmBtn = e.target.closest('#jBtnConfirmTopup');
    if (!confirmBtn) return;
    e.preventDefault();
    var modalEl = document.getElementById('jModalTopup');
    if (modalEl && window.bootstrap) {
      var inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
    submitTopup();
  });

  var topupModal = document.getElementById('jModalTopup');
  if (topupModal) {
    topupModal.addEventListener('hidden.bs.modal', function () {
      if (pendingTopup && pendingTopup.btn) {
        pendingTopup.btn.disabled = false;
      }
      pendingTopup = null;
    });
  }

  var pendingCancelTopup = null;

  function submitCancelTopup() {
    if (!pendingCancelTopup) return;
    var idMember = pendingCancelTopup.idMember;
    var btn = pendingCancelTopup.btn;
    pendingCancelTopup = null;
    if (!idMember) return;

    if (btn) btn.disabled = true;
    var body = new URLSearchParams();
    body.set('id_member', idMember);

    fetch(base + 'J/topupCancel/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal membatalkan');
        }
        toast(data.message || 'Dibatalkan', 'ok');
        loadPage('tagihan', '', false);
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal membatalkan', 'warn');
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.j-topup-cancel');
    if (!btn || !content.contains(btn)) return;
    e.preventDefault();

    var idMember = btn.getAttribute('data-id-member');
    if (!idMember || btn.disabled) return;

    pendingCancelTopup = { idMember: idMember, btn: btn };
    var info = document.getElementById('jCancelTopupInfo');
    if (info) info.textContent = btn.getAttribute('data-label') || ('#' + idMember);

    var modalEl = document.getElementById('jModalCancelTopup');
    if (!modalEl || !window.bootstrap) {
      if (!window.confirm('Batalkan topup ini?')) {
        pendingCancelTopup = null;
        return;
      }
      submitCancelTopup();
      return;
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });

  document.addEventListener('click', function (e) {
    var confirmBtn = e.target.closest('#jBtnConfirmCancelTopup');
    if (!confirmBtn) return;
    e.preventDefault();
    var modalEl = document.getElementById('jModalCancelTopup');
    if (modalEl && window.bootstrap) {
      var inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
    submitCancelTopup();
  });

  var cancelTopupModal = document.getElementById('jModalCancelTopup');
  if (cancelTopupModal) {
    cancelTopupModal.addEventListener('hidden.bs.modal', function () {
      if (pendingCancelTopup && pendingCancelTopup.btn) {
        pendingCancelTopup.btn.disabled = false;
      }
      pendingCancelTopup = null;
    });
  }

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.page) {
      loadPage(e.state.page, e.state.extra || '', false);
    } else {
      var parsed = parsePath();
      loadPage(parsed.page, parsed.extra, false);
    }
  });

  function slugName(name) {
    return String(name || 'invoice')
      .toLowerCase()
      .replace(/[^a-z0-9]+/gi, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40) || 'invoice';
  }

  function capturePreviewCanvas() {
    var page = document.getElementById('jPreviewPage');
    if (!page) return Promise.reject(new Error('Preview tidak ditemukan'));
    if (typeof html2canvas !== 'function') {
      return Promise.reject(new Error('Fitur gambar belum siap. Muat ulang halaman.'));
    }

    var prevOverflow = page.style.overflow;
    var prevMaxHeight = page.style.maxHeight;
    page.style.overflow = 'visible';
    page.style.maxHeight = 'none';

    return html2canvas(page, {
      backgroundColor: '#ffffff',
      scale: Math.min(2, window.devicePixelRatio || 2),
      useCORS: true,
      logging: false
    }).finally(function () {
      page.style.overflow = prevOverflow;
      page.style.maxHeight = prevMaxHeight;
    });
  }

  function canvasToPngFile(canvas, filename) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (!blob) {
          reject(new Error('Gagal membuat file gambar'));
          return;
        }
        resolve(new File([blob], filename, { type: 'image/png' }));
      }, 'image/png');
    });
  }

  function downloadPreviewImage(btn) {
    var page = document.getElementById('jPreviewPage');
    if (!page) return;
    btn.disabled = true;

    capturePreviewCanvas()
      .then(function (canvas) {
        var nama = page.getAttribute('data-nama') || 'invoice';
        var link = document.createElement('a');
        link.download = 'invoice-' + slugName(nama) + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      })
      .catch(function (err) {
        alert((err && err.message) || 'Gagal membuat gambar invoice.');
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

  function sharePreviewImage(btn) {
    var page = document.getElementById('jPreviewPage');
    if (!page) return;

    if (!navigator.share) {
      alert('Perangkat ini belum mendukung bagikan langsung. Gunakan tombol download.');
      return;
    }

    btn.disabled = true;
    var nama = page.getAttribute('data-nama') || 'invoice';
    var filename = 'invoice-' + slugName(nama) + '.png';

    capturePreviewCanvas()
      .then(function (canvas) {
        return canvasToPngFile(canvas, filename);
      })
      .then(function (file) {
        var data = { files: [file] };
        if (navigator.canShare && !navigator.canShare(data)) {
          throw new Error('Perangkat tidak bisa membagikan gambar. Gunakan tombol download.');
        }
        return navigator.share(data);
      })
      .catch(function (err) {
        if (err && (err.name === 'AbortError' || err.name === 'NotAllowedError')) return;
        alert((err && err.message) || 'Gagal membagikan gambar.');
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

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

    var sharePreview = e.target.closest('#jSharePreview');
    if (sharePreview) {
      e.preventDefault();
      e.stopPropagation();
      sharePreviewImage(sharePreview);
      return;
    }

    var dlPreview = e.target.closest('#jDownloadPreview');
    if (dlPreview) {
      e.preventDefault();
      e.stopPropagation();
      downloadPreviewImage(dlPreview);
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

  /* ===== Kurir Sameday ===== */
  var kurirBusy = false;

  function escapeHtmlKurir(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderKurirSales(orders) {
    var box = document.getElementById('jKurirSalesBox');
    if (!box) return;
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="j-kurir-sales-empty">Tidak ada item yang bisa diantar saat ini.</div>';
      return;
    }
    box.innerHTML = orders.map(function (ord) {
      var items = (ord.items || []).map(function (it) {
        var status = Number(it.tuntas) === 1 ? 'Tuntas' : 'Proses';
        return '<label class="j-kurir-sales-item">' +
          '<input type="checkbox" name="kurir_ids[]" value="' + escapeHtmlKurir(String(it.id)) + '">' +
          '<span class="j-kurir-sales-item__text">' +
            '<strong>' + escapeHtmlKurir(it.kategori || '-') +
              (it.durasi ? ' · ' + escapeHtmlKurir(it.durasi) : '') +
            '</strong>' +
            '<small>' + escapeHtmlKurir(it.qty_show || '') +
              ' · #' + escapeHtmlKurir(String(it.id)) +
              ' · ' + status +
            '</small>' +
          '</span>' +
        '</label>';
      }).join('');
      return '<div class="j-kurir-sales-group">' +
        '<div class="j-kurir-sales-group__head">#' + escapeHtmlKurir(ord.no_ref || '-') + '</div>' +
        items +
      '</div>';
    }).join('');
  }

  function loadKurirSalesOptions() {
    var box = document.getElementById('jKurirSalesBox');
    if (box) box.innerHTML = '<div class="j-kurir-sales-empty"><i class="fas fa-spinner fa-spin"></i> Memuat item…</div>';
    return fetch(base + 'J/kurirSalesOptions/' + pelangganId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal memuat item');
        }
        renderKurirSales(data.orders || []);
      })
      .catch(function (err) {
        if (box) {
          box.innerHTML = '<div class="j-kurir-sales-empty">' +
            escapeHtmlKurir((err && err.message) || 'Gagal memuat item') +
            '</div>';
        }
      });
  }

  function submitKurirSameday(jenis, ids, btn) {
    if (kurirBusy) return;
    kurirBusy = true;
    if (btn) btn.disabled = true;

    var body = new URLSearchParams();
    body.set('jenis', jenis);
    (ids || []).forEach(function (id) {
      body.append('ids[]', id);
    });

    fetch(base + 'J/kurirSamedaySubmit/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal mengirim permintaan');
        }
        toast(data.message || 'Permintaan dikirim', 'ok');
        var antarModal = document.getElementById('jModalKurirAntar');
        var jemputModal = document.getElementById('jModalKurirJemput');
        if (antarModal && window.bootstrap) {
          var a = bootstrap.Modal.getInstance(antarModal);
          if (a) a.hide();
        }
        if (jemputModal && window.bootstrap) {
          var j = bootstrap.Modal.getInstance(jemputModal);
          if (j) j.hide();
        }
        loadPage('kurir', '', false);
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal mengirim permintaan', 'warn');
      })
      .finally(function () {
        kurirBusy = false;
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var act = e.target.closest('.j-kurir-act');
    if (!act || !content.contains(act)) return;
    e.preventDefault();
    if (act.disabled) return;

    var jenis = (act.getAttribute('data-j-kurir-jenis') || '').toLowerCase();
    if (jenis === 'antar') {
      loadKurirSalesOptions();
      var modalEl = document.getElementById('jModalKurirAntar');
      if (modalEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
      return;
    }
    if (jenis === 'jemput') {
      var jemputEl = document.getElementById('jModalKurirJemput');
      if (jemputEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(jemputEl).show();
      } else if (window.confirm('Jemput laundry sekarang?')) {
        submitKurirSameday('jemput', [], act);
      }
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#jBtnSubmitKurirAntar');
    if (!btn) return;
    e.preventDefault();
    var checks = document.querySelectorAll('#jKurirSalesBox input[name="kurir_ids[]"]:checked');
    if (!checks.length) {
      toast('Pilih minimal satu item', 'warn');
      return;
    }
    var ids = [];
    Array.prototype.forEach.call(checks, function (cb) {
      ids.push(cb.value);
    });
    submitKurirSameday('antar', ids, btn);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#jBtnConfirmKurirJemput');
    if (!btn) return;
    e.preventDefault();
    submitKurirSameday('jemput', [], btn);
  });

  var initialPage = app.getAttribute('data-page') || 'home';
  var initialExtra = app.getAttribute('data-extra') || '';
  history.replaceState({ page: initialPage, extra: initialExtra }, '', pageUrl(initialPage, initialExtra));
  loadPage(initialPage, initialExtra, false);
})();
