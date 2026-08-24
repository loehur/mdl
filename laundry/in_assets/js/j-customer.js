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
    if (page === 'riwayat') navKey = 'tagihan';
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
    if (page === 'riwayat') {
      var rurl = base + 'J/riwayat/' + pelangganId;
      if (extra && /^\d{4}-\d{2}$/.test(String(extra))) rurl += '/' + extra;
      return rurl;
    }
    return base + 'J/' + page + '/' + pelangganId;
  }

  function loadUrl(page, extra) {
    var url = base + 'J/load/' + page + '/' + pelangganId;
    if ((page === 'paketDetail' || page === 'topup') && extra && String(extra) !== '0') {
      url += '/' + extra;
    }
    if (page === 'riwayat' && extra && /^\d{4}-\d{2}$/.test(String(extra))) {
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
    },
    /** JSON: list lokasi + tarif antar */
    lokasiList: function () {
      return fetch(base + 'J/lokasiList/' + pelangganId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json();
      });
    },
    /**
     * POST Request Antar — tambah surcas pengantaran ke 1 ref belum tuntas.
     * @param {number[]} ids id_penjualan
     * @param {number} idLokasi
     */
    requestAntar: function (ids, idLokasi) {
      var body = new URLSearchParams();
      body.set('id_lokasi', String(idLokasi || 0));
      (ids || []).forEach(function (id) {
        body.append('ids[]', String(id));
      });
      return fetch(base + 'J/requestAntar/' + pelangganId, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        credentials: 'same-origin',
        body: body.toString()
      }).then(function (res) {
        return res.json();
      });
    },
    /** HTML partial list lokasi + Tarif → inject ke container */
    loadLokasiAntar: function (container) {
      var el = typeof container === 'string' ? document.querySelector(container) : container;
      if (!el) {
        return Promise.reject(new Error('Container lokasi tidak ditemukan'));
      }
      el.innerHTML = loadingHtml();
      return fetch(base + 'J/load/lokasiAntar/' + pelangganId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (res) {
          if (!res.ok) throw new Error('Gagal memuat lokasi');
          return res.text();
        })
        .then(function (html) {
          el.innerHTML = html;
          return el;
        });
    },
    /** id_lokasi terpilih dari list partial */
    selectedLokasiId: function (root) {
      var scope = root || document;
      var checked = scope.querySelector('input[name="j_id_lokasi"]:checked');
      return checked ? parseInt(checked.value, 10) || 0 : 0;
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
    var mRiwayatYm = path.match(/\/J\/riwayat\/(\d+)\/(\d{4}-\d{2})/i);
    if (mRiwayatYm) return { page: 'riwayat', extra: mRiwayatYm[2] };
    var mRiwayat = path.match(/\/J\/riwayat\/(\d+)\/?$/i);
    if (mRiwayat) return { page: 'riwayat', extra: '' };
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

    var mRiwayatYm = href.match(/J\/riwayat\/(\d+)\/(\d{4}-\d{2})/i);
    if (mRiwayatYm && String(mRiwayatYm[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('riwayat', mRiwayatYm[2], true);
      return;
    }

    var mRiwayat = href.match(/J\/riwayat\/(\d+)\/?$/i);
    if (mRiwayat && String(mRiwayat[1]) === String(pelangganId)) {
      e.preventDefault();
      loadPage('riwayat', '', true);
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

    var openNotaPreview = e.target.closest('.j-nota-preview-btn');
    if (openNotaPreview) {
      e.preventDefault();
      var srcId = openNotaPreview.getAttribute('data-preview-src') || '';
      var src = srcId ? document.getElementById(srcId) : null;
      var overlayN = document.getElementById('jInvoicePreview');
      var slot = document.getElementById('jPreviewNotaSlot');
      if (!src || !overlayN || !slot) return;
      slot.innerHTML = src.innerHTML;
      if (overlayN.parentElement !== document.body) {
        document.body.appendChild(overlayN);
      }
      overlayN.hidden = false;
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

  /* ===== Detail Nota Timeline ===== */
  var jNotaDetailModal = null;
  function getJNotaDetailModal() {
    var el = document.getElementById('jModalNotaDetail');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
    if (!jNotaDetailModal) jNotaDetailModal = bootstrap.Modal.getOrCreateInstance(el);
    return jNotaDetailModal;
  }

  function jNdtEsc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function jNdtMoney(n) {
    n = parseInt(n || 0, 10);
    if (isNaN(n)) n = 0;
    return n.toLocaleString('id-ID');
  }

  function jNdtRowClass(type, done, inferred) {
    var cls = [done ? 'done' : 'pending'];
    if (inferred) cls.push('inferred');
    if (type === 'jemput' || type === 'antar' || type === 'ambil') cls.push(type);
    return cls.join(' ');
  }

  function renderJNotaDetail(data) {
    var html = '<div id="jNotaDetailCapture" data-ref="' + jNdtEsc(data.no_ref || '') + '" data-nama="' + jNdtEsc(data.pelanggan || '') + '">';
    var lunas = !!data.lunas;
    var statusPill = lunas
      ? '<span class="j-ndt-pill ok">LUNAS</span>'
      : '<span class="j-ndt-pill warn">SISA ' + jNdtEsc(jNdtMoney(data.sisa)) + '</span>';

    html += '<div class="j-ndt-summary">';
    html +=
      '<div class="j-ndt-box j-ndt-box--full">' +
      '<span class="j-ndt-label">Nota</span>' +
      '<div class="j-ndt-value">#' + jNdtEsc(data.no_ref) + ' ' + statusPill + '</div>' +
      '<span class="j-ndt-meta">' + jNdtEsc(data.pelanggan || '') + '</span>' +
      '</div>';
    html +=
      '<div class="j-ndt-box">' +
      '<span class="j-ndt-label">Dibuat</span>' +
      '<div class="j-ndt-value">' + jNdtEsc(data.created_at || '-') + '</div>' +
      '</div>';
    html +=
      '<div class="j-ndt-box">' +
      '<span class="j-ndt-label">Tagihan</span>' +
      '<div class="j-ndt-value">Rp' + jNdtEsc(jNdtMoney(data.total)) + '</div>' +
      '<span class="j-ndt-meta">Dibayar Rp' + jNdtEsc(jNdtMoney(data.dibayar)) + '</span>' +
      '</div></div>';

    html += '<div class="j-ndt-sec"><div class="j-ndt-sec__title">Pembayaran</div>';
    var pays = data.payments || [];
    if (!pays.length) {
      html += '<div class="j-ndt-empty">Belum ada pembayaran</div>';
    } else {
      pays.forEach(function (p) {
        var st = parseInt(p.status || 0, 10);
        var cancel = st === 4;
        var bits = [];
        if (p.method) bits.push(p.method);
        if (p.note) bits.push(p.note);
        html +=
          '<div class="j-ndt-pay"><div class="j-ndt-pay__top">' +
          '<span class="j-ndt-pill ' + (cancel ? '' : st === 3 ? 'ok' : 'info') + '">' +
          jNdtEsc(p.status_label || (cancel ? 'Batal' : 'Bayar')) +
          '</span><span class="j-ndt-pay__amt' + (cancel ? ' cancel' : '') + '">Rp' +
          jNdtEsc(jNdtMoney(p.amount)) +
          '</span></div><div class="j-ndt-meta">' +
          jNdtEsc(p.time || '-') +
          (bits.length ? ' · ' + jNdtEsc(bits.join(' · ')) : '') +
          '</div></div>';
      });
    }
    html += '</div>';

    var surcas = data.surcas || [];
    if (surcas.length) {
      html += '<div class="j-ndt-sec"><div class="j-ndt-sec__title">Surcas</div>';
      surcas.forEach(function (sc) {
        html +=
          '<div class="j-ndt-pay"><div class="j-ndt-pay__top"><div class="j-ndt-value" style="font-size:0.8rem">' +
          jNdtEsc(sc.nama || 'Surcas') +
          '</div><div class="j-ndt-pay__amt">Rp' +
          jNdtEsc(jNdtMoney(sc.jumlah)) +
          '</div></div><div class="j-ndt-meta">' +
          jNdtEsc(sc.time || '-') +
          '</div></div>';
      });
      html += '</div>';
    }

    html += '<div class="j-ndt-sec"><div class="j-ndt-sec__title">Item &amp; Timeline</div>';
    var items = data.items || [];
    if (!items.length) html += '<div class="j-ndt-empty">Tidak ada item</div>';
    items.forEach(function (it) {
      var totalShow =
        parseInt(it.member || 0, 10) === 1
          ? '<span class="j-ndt-pill ok">Member</span>'
          : 'Rp' + jNdtEsc(jNdtMoney(it.total));
      var subBits = [];
      if (it.durasi) subBits.push(it.durasi);
      if (it.qty_show) subBits.push(it.qty_show);
      if (it.letak) subBits.push('Rak ' + it.letak);
      html +=
        '<div class="j-ndt-item"><div class="j-ndt-item__head"><div>' +
        '<div class="j-ndt-item__title">#' + jNdtEsc(it.id) + ' ' + jNdtEsc(it.kategori || '') + '</div>' +
        '<span class="j-ndt-item__sub">' + jNdtEsc(subBits.join(' · ') || '-') +
        (it.note ? ' · ' + jNdtEsc(it.note) : '') +
        '</span></div><div class="j-ndt-pay__amt">' + totalShow + '</div></div>';

      var tl = it.timeline || [];
      if (!tl.length) {
        html += '<div class="j-ndt-empty" style="margin:8px;border:0;background:transparent">Belum ada aktivitas</div>';
      } else {
        html += '<ul class="j-ndt-tl">';
        tl.forEach(function (ev) {
          var done = !!ev.done;
          var inferred = !!ev.inferred;
          var meta = [];
          if (ev.time) meta.push(ev.time);
          else if (!done) meta.push('belum selesai');
          html +=
            '<li class="j-ndt-tl__row ' + jNdtRowClass(ev.type, done, inferred) + '">' +
            '<span class="j-ndt-tl__dot" aria-hidden="true"></span>' +
            '<span class="j-ndt-tl__content">' +
            '<span class="j-ndt-tl__label">' + jNdtEsc(ev.label || '') +
            (inferred ? ' <span class="j-ndt-chip">otomatis</span>' : '') +
            '</span><span class="j-ndt-tl__meta">' +
            jNdtEsc(meta.join(' · ') || '-') +
            '</span></span></li>';
        });
        html += '</ul>';
      }
      html += '</div>';
    });
    html += '</div></div>';
    return html;
  }

  function downloadJNotaDetail(btn) {
    var page = document.getElementById('jNotaDetailCapture');
    var body = document.getElementById('jNotaDetailBody');
    if (!page || !body) return;
    if (typeof html2canvas !== 'function') {
      alert('Fitur gambar belum siap. Muat ulang halaman.');
      return;
    }
    btn.disabled = true;
    var old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses';

    var prevOverflow = body.style.overflow;
    var prevMaxHeight = body.style.maxHeight;
    body.style.overflow = 'visible';
    body.style.maxHeight = 'none';

    html2canvas(page, {
      backgroundColor: '#ffffff',
      scale: Math.min(2, window.devicePixelRatio || 2),
      useCORS: true,
      logging: false
    })
      .then(function (canvas) {
        var ref = page.getAttribute('data-ref') || 'nota';
        var nama = page.getAttribute('data-nama') || '';
        var link = document.createElement('a');
        link.download = 'nota-' + slugName(ref) + (nama ? '-' + slugName(nama) : '') + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      })
      .catch(function (err) {
        alert((err && err.message) || 'Gagal membuat gambar detail nota.');
      })
      .finally(function () {
        body.style.overflow = prevOverflow;
        body.style.maxHeight = prevMaxHeight;
        btn.disabled = false;
        btn.innerHTML = old;
      });
  }

  document.addEventListener('click', function (e) {
    var openBtn = e.target.closest('.j-nota-detail-btn');
    if (openBtn) {
      e.preventDefault();
      var ref = openBtn.getAttribute('data-ref') || '';
      if (!ref) return;
      var title = document.getElementById('jNotaDetailTitle');
      var body = document.getElementById('jNotaDetailBody');
      var dl = document.getElementById('jBtnDownloadNotaDetail');
      if (title) title.textContent = 'Detail Nota #' + ref;
      if (dl) dl.disabled = true;
      if (body) {
        body.innerHTML = '<div class="j-ndt-loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail...</div>';
      }
      var modal = getJNotaDetailModal();
      if (modal) modal.show();

      fetch(base + 'J/nota_detail/' + pelangganId + '?ref=' + encodeURIComponent(ref), {
        headers: { Accept: 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res || !res.ok || !res.data) {
            if (body) {
              body.innerHTML = '<div class="j-ndt-error">' + jNdtEsc((res && res.message) || 'Gagal memuat detail') + '</div>';
            }
            if (dl) dl.disabled = true;
            return;
          }
          if (body) body.innerHTML = renderJNotaDetail(res.data);
          if (dl) dl.disabled = false;
        })
        .catch(function () {
          if (body) body.innerHTML = '<div class="j-ndt-error">Gagal memuat detail (network)</div>';
          if (dl) dl.disabled = true;
        });
      return;
    }

    var dlBtn = e.target.closest('#jBtnDownloadNotaDetail');
    if (dlBtn) {
      e.preventDefault();
      downloadJNotaDetail(dlBtn);
    }
  });

  /* ===== Kurir Sameday ===== */
  var kurirBusy = false;
  var kurirPendingJenis = '';
  var kurirPendingLayanan = 'sameday';
  var kurirSelectedLokasi = null;
  var kurirSelectedCourier = null;
  var kurirPendingIds = [];
  var kurirInstantPoll = null;
  var kurirSaldoTunai = 0;
  var pendingCancelKurirInstant = null;

  function getKurirSaldo() {
    try {
      var el = document.getElementById('jKurirConfig');
      if (el) {
        var cfg = JSON.parse(el.textContent || '{}');
        if (cfg && cfg.saldoTunai != null) return parseInt(cfg.saldoTunai, 10) || 0;
      }
    } catch (e) {}
    return kurirSaldoTunai || 0;
  }

  function syncKurirPayMetode() {
    var wrap = document.getElementById('jKurirCourierPayWrap');
    var sel = document.getElementById('jKurirCourierMetode');
    var hint = document.getElementById('jKurirCourierSaldoHint');
    var btnIco = document.querySelector('#jBtnSubmitKurirCourier i');
    var btnLbl = document.getElementById('jBtnSubmitKurirCourierLabel');
    var saldo = getKurirSaldo();
    var price = kurirSelectedCourier ? Number(kurirSelectedCourier.price || 0) : 0;
    if (!wrap || !sel) return;

    var prev = sel.value || '';
    var touched = sel.getAttribute('data-touched') === '1';
    sel.innerHTML = '';
    var canSaldo = saldo > 0 && price > 0 && saldo >= price;
    if (canSaldo) {
      var optS = document.createElement('option');
      optS.value = 'SALDO';
      optS.textContent = 'Saldo Deposit (Rp' + saldo.toLocaleString('id-ID') + ')';
      sel.appendChild(optS);
    }
    var optQ = document.createElement('option');
    optQ.value = 'QRIS';
    optQ.textContent = 'QRIS';
    sel.appendChild(optQ);

    if (canSaldo && !(touched && prev === 'QRIS')) sel.value = 'SALDO';
    else sel.value = 'QRIS';

    wrap.style.display = kurirSelectedCourier ? '' : 'none';
    if (hint) {
      if (saldo > 0 && price > 0 && saldo < price) {
        hint.textContent =
          'Saldo Deposit Rp' +
          saldo.toLocaleString('id-ID') +
          ' kurang dari ongkir. Gunakan QRIS.';
      } else if (canSaldo) {
        hint.textContent = 'Bisa bayar pakai Saldo Deposit atau QRIS.';
      } else {
        hint.textContent = '';
      }
    }
    var metode = sel.value;
    if (btnIco) btnIco.className = metode === 'SALDO' ? 'fas fa-wallet' : 'fas fa-qrcode';
    if (btnLbl) btnLbl.textContent = metode === 'SALDO' ? 'Bayar Saldo' : 'Bayar QRIS';
  }

  function layananLabel() {
    return kurirPendingLayanan === 'instant' ? 'Instant (Gojek/Grab)' : 'Sameday (Kurir Laundry)';
  }

  function setKurirKickers() {
    var label = layananLabel();
    ['jKurirLokasiKicker', 'jKurirAntarKicker', 'jKurirJemputKicker'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = label;
    });
    var antLbl = document.getElementById('jBtnSubmitKurirAntarLabel');
    if (antLbl) {
      antLbl.textContent = kurirPendingLayanan === 'instant' ? 'Lanjut pilih kurir' : 'Kirim permintaan';
    }
    var jemLbl = document.getElementById('jBtnConfirmKurirJemputLabel');
    if (jemLbl) {
      jemLbl.textContent = kurirPendingLayanan === 'instant' ? 'Lanjut pilih kurir' : 'Ya, jemput';
    }
    // Instant: catatan hanya di modal pilih kurir (hindari isi 2x).
    // Sameday: catatan di modal antar/jemput.
    var antCatatanWrap = document.getElementById('jKurirCatatanAntarWrap');
    if (antCatatanWrap) {
      antCatatanWrap.style.display = kurirPendingLayanan === 'instant' ? 'none' : '';
    }
  }

  function stopKurirInstantPoll() {
    if (kurirInstantPoll) {
      clearInterval(kurirInstantPoll);
      kurirInstantPoll = null;
    }
  }

  function openInstantQr(ref, total, btn) {
    var nama = '';
    try {
      var cfgEl = document.getElementById('jPayConfig');
      if (cfgEl) {
        var cfg = JSON.parse(cfgEl.textContent || '{}');
        nama = cfg.nama || '';
      }
    } catch (e) {}

    var restoreBtn = null;
    if (btn) {
      if (btn.disabled || btn.getAttribute('aria-busy') === 'true') return;
      if (!btn.dataset.origHtml) btn.dataset.origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Memuat…';
      restoreBtn = function () {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.innerHTML = btn.dataset.origHtml || '<i class="fas fa-qrcode"></i> Bayar QRIS';
      };
    }

    fetch(
      base +
        'I/payment_gateway_order/' +
        encodeURIComponent(ref) +
        '?nominal=' +
        encodeURIComponent(total) +
        '&metode=QRIS',
      { credentials: 'same-origin' }
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res && res.status === 'paid') {
          if (res.instant_activated === false) {
            if (restoreBtn) restoreBtn();
            toast(
              (res.msg && String(res.msg)) ||
                'Pembayaran berhasil, tapi order Instant gagal diproses. Coba klik Bayar QRIS lagi.',
              'warn'
            );
          } else {
            toast('Pembayaran berhasil', 'ok');
          }
          loadPage('kurir', '', false);
          return;
        }
        if (!res || !res.qr_string) {
          if (restoreBtn) restoreBtn();
          toast((res && res.msg) || 'QRIS sementara tidak tersedia', 'warn');
          loadPage('kurir', '', false);
          return;
        }
        if (restoreBtn) restoreBtn();
        var box = document.getElementById('jQrcode');
        if (box) {
          box.innerHTML = '';
          if (window.QRCode) {
            new QRCode(box, { text: res.qr_string, width: 180, height: 180 });
          } else {
            box.textContent = 'QR library gagal dimuat';
          }
        }
        var tot = document.getElementById('jQrTotal');
        if (tot) tot.textContent = 'Rp' + Number(total || 0).toLocaleString('id-ID');
        var nm = document.getElementById('jQrNama');
        if (nm) nm.textContent = nama || 'Ongkir Instant';
        var cek = document.getElementById('jBtnCekStatusQR');
        if (cek) cek.setAttribute('data-ref', ref);
        showModal('jModalQR');
        stopKurirInstantPoll();
        var pollTick = 0;
        kurirInstantPoll = setInterval(function () {
          pollTick += 1;
          var sync = pollTick % 3 === 0;
          var url =
            base +
            'I/payment_gateway_status_poll/' +
            encodeURIComponent(ref) +
            (sync ? '?sync=1' : '');
          fetch(url, {
            credentials: 'same-origin',
          })
            .then(function (r) {
              return r.json();
            })
            .then(function (st) {
              if (st && String(st.status).toUpperCase() === 'PAID') {
                stopKurirInstantPoll();
                hideModal('jModalQR');
                if (st.instant_activated === false) {
                  toast(
                    (st.msg && String(st.msg)) ||
                      'Pembayaran berhasil, tapi order Instant gagal diproses. Coba klik Bayar QRIS lagi.',
                    'warn'
                  );
                } else {
                  toast('Pembayaran berhasil. Order Instant diproses.', 'ok');
                }
                loadPage('kurir', '', false);
              }
            })
            .catch(function () {});
        }, 3000);
      })
      .catch(function () {
        if (restoreBtn) restoreBtn();
        toast('Gagal memuat QRIS', 'warn');
        loadPage('kurir', '', false);
      });
  }

  function fmtOngkir(n) {
    return 'Rp' + Number(n || 0).toLocaleString('id-ID');
  }

  function renderKurirCouriers(rates) {
    var box = document.getElementById('jKurirCourierBox');
    var btn = document.getElementById('jBtnSubmitKurirCourier');
    kurirSelectedCourier = null;
    if (btn) btn.disabled = true;
    if (!box) return;
    if (!rates || !rates.length) {
            box.innerHTML = '<div class="j-kurir-sales-empty">Tidak ada kurir Instant tersedia untuk rute ini.</div>';
      syncKurirPayMetode();
      return;
    }
    box.innerHTML = rates
      .map(function (r, idx) {
        var price = Number(r.price || 0);
        var name = r.courier_name || r.courier_company || 'Kurir';
        var desc = r.description || r.duration || '';
        return (
          '<label class="j-kurir-sales-item">' +
          '<input type="radio" name="kurir_courier" value="' +
          idx +
          '">' +
          '<span class="j-kurir-sales-item__text">' +
          '<strong>' +
          escapeHtmlKurir(name) +
          ' · ' +
          fmtOngkir(price) +
          '</strong>' +
          '<small>' +
          escapeHtmlKurir(String(r.courier_company || '')) +
          (r.courier_type ? ' / ' + escapeHtmlKurir(String(r.courier_type)) : '') +
          (desc ? ' · ' + escapeHtmlKurir(desc) : '') +
          '</small>' +
          '</span>' +
          '</label>'
        );
      })
      .join('');
    box._rates = rates;
    Array.prototype.forEach.call(box.querySelectorAll('input[name="kurir_courier"]'), function (inp) {
      inp.addEventListener('change', function () {
        var i = parseInt(inp.value, 10);
        kurirSelectedCourier = (box._rates && box._rates[i]) || null;
        if (btn) btn.disabled = !kurirSelectedCourier;
        syncKurirPayMetode();
      });
    });
    syncKurirPayMetode();
  }

  function openKurirCourierModal() {
    var kap = document.getElementById('jKurirCourierKapasitas');
    if (kap) {
      kap.textContent = 'Pastikan berat laundry yang ingin di-antar/jemput <10kg sesuai kapasitas kurir instant.';
    }
    var lokBox = document.getElementById('jKurirCourierLokasi');
    if (lokBox) lokBox.innerHTML = lokasiLabelHtml(kurirSelectedLokasi);
    var box = document.getElementById('jKurirCourierBox');
    if (box) box.innerHTML = '<div class="j-kurir-sales-empty"><i class="fas fa-spinner fa-spin"></i> Memuat kurir…</div>';
    var payWrap = document.getElementById('jKurirCourierPayWrap');
    if (payWrap) payWrap.style.display = 'none';
    var metodeSel = document.getElementById('jKurirCourierMetode');
    if (metodeSel) metodeSel.removeAttribute('data-touched');
    // Bawa catatan dari langkah sebelumnya jika sudah terisi (mis. antar sameday→instant edge)
    var courierCatatan = document.getElementById('jKurirCatatanCourier');
    if (courierCatatan && !String(courierCatatan.value || '').trim()) {
      var prev =
        getKurirCatatan(kurirPendingJenis === 'jemput' ? 'jemput' : 'antar') || '';
      if (prev) {
        courierCatatan.value = prev;
        courierCatatan.dispatchEvent(new Event('input'));
      }
    }
    showModal('jModalKurirCourier');
    var qs =
      'id_lokasi=' +
      encodeURIComponent(String(kurirSelectedLokasi.id_lokasi)) +
      '&jenis=' +
      encodeURIComponent(kurirPendingJenis);
    fetch(base + 'J/kurirInstantRates/' + pelangganId + '?' + qs, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal memuat kurir');
        }
        if (data.saldoTunai != null) kurirSaldoTunai = parseInt(data.saldoTunai, 10) || 0;
        renderKurirCouriers(data.rates || []);
      })
      .catch(function (err) {
        if (box) {
          box.innerHTML =
            '<div class="j-kurir-sales-empty">' +
            escapeHtmlKurir((err && err.message) || 'Gagal memuat kurir') +
            '</div>';
        }
      });
  }

  function getKurirCatatan(jenisOrMode) {
    var id = 'jKurirCatatanAntar';
    if (jenisOrMode === 'jemput') id = 'jKurirCatatanJemput';
    else if (jenisOrMode === 'courier' || jenisOrMode === 'instant') id = 'jKurirCatatanCourier';
    var el = document.getElementById(id);
    var val = String((el && el.value) || '').trim().replace(/\s+/g, ' ');
    if (val.length > 150) val = val.slice(0, 150);
    return val;
  }

  function bindKurirCatatanCounter(inputId, countId) {
    var input = document.getElementById(inputId);
    var count = document.getElementById(countId);
    if (!input || !count) return;
    var sync = function () {
      var n = String(input.value || '').length;
      count.textContent = String(n);
    };
    input.addEventListener('input', sync);
    sync();
  }
  bindKurirCatatanCounter('jKurirCatatanAntar', 'jKurirCatatanAntarCount');
  bindKurirCatatanCounter('jKurirCatatanJemput', 'jKurirCatatanJemputCount');
  bindKurirCatatanCounter('jKurirCatatanCourier', 'jKurirCatatanCourierCount');

  function submitKurirInstant(jenis, ids, btn) {
    if (kurirBusy) return;
    if (!kurirSelectedLokasi || !kurirSelectedLokasi.id_lokasi) {
      toast('Pilih lokasi dulu', 'warn');
      return;
    }
    if (!kurirSelectedCourier) {
      toast('Pilih kurir Instant dulu', 'warn');
      return;
    }
    var metodeEl = document.getElementById('jKurirCourierMetode');
    var metode = (metodeEl && metodeEl.value) || 'QRIS';
    if (metode !== 'SALDO') metode = 'QRIS';
    var ongkir = Number(kurirSelectedCourier.price || 0);
    if (metode === 'SALDO') {
      var saldo = getKurirSaldo();
      if (saldo < ongkir) {
        toast('Saldo Deposit tidak cukup untuk ongkir ini', 'warn');
        return;
      }
    }
    var catatan = getKurirCatatan('courier');
    kurirBusy = true;
    if (btn) btn.disabled = true;

    var body = new URLSearchParams();
    body.set('jenis', jenis);
    body.set('id_lokasi', String(kurirSelectedLokasi.id_lokasi));
    body.set('courier_company', String(kurirSelectedCourier.courier_company || ''));
    body.set('courier_type', String(kurirSelectedCourier.courier_type || ''));
    body.set('courier_name', String(kurirSelectedCourier.courier_name || ''));
    body.set('ongkir', String(ongkir));
    body.set('metode', metode);
    if (catatan) body.set('catatan', catatan);
    (ids || []).forEach(function (id) {
      body.append('ids[]', id);
    });

    fetch(base + 'J/kurirInstantSubmit/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      credentials: 'same-origin',
      body: body.toString(),
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal membuat permintaan Instant');
        }
        hideModal('jModalKurirCourier');
        hideModal('jModalKurirAntar');
        hideModal('jModalKurirJemput');
        hideModal('jModalKurirLokasi');
        toast(data.message || 'Berhasil', 'ok');
        var ref = data.ref_finance;
        var total = data.ongkir || ongkir;
        var paid = !!(data.paid || data.note === 'SALDO');
        kurirSelectedLokasi = null;
        kurirSelectedCourier = null;
        kurirPendingJenis = '';
        kurirPendingLayanan = 'sameday';
        kurirPendingIds = [];
        if (!paid && data.pay && ref) {
          openInstantQr(ref, total);
        } else {
          loadPage('kurir', '', false);
        }
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal mengirim permintaan', 'warn');
      })
      .finally(function () {
        kurirBusy = false;
        if (btn) btn.disabled = false;
      });
  }
  var kurirLokasiCache = [];
  var kurirDefaultMap = { latt: 0.507068, longt: 101.447779, nama_kota: 'PEKANBARU', source: 'fallback' };
  var kurirMap = null;
  var kurirMapIdleListener = null;
  var kurirMapSuppressIdle = false;
  var kurirMapsScriptPromise = null;
  var kurirSearchTimer = null;
  var kurirSearchSeq = 0;
  var kurirSelectingPlace = false;
  var kurirEditingLokasiId = 0;
  var KURIR_DEFAULT_ZOOM = 15;
  var KURIR_SELECT_ZOOM = 17;
  var KURIR_GPS_ICON_HTML =
    '<svg xmlns="http://www.w3.org/2000/svg" class="j-kurir-map-gps__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
    '<circle cx="12" cy="12" r="3" /><path d="M12 2v4M12 18v4M4 12H2M22 12h-2" />' +
    '</svg>';
  var KURIR_GPS_SPIN_HTML =
    '<svg xmlns="http://www.w3.org/2000/svg" class="j-kurir-map-gps__icon j-kurir-map-gps__icon--spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">' +
    '<circle class="j-kurir-map-gps__spin-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
    '<path class="j-kurir-map-gps__spin-head" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>' +
    '</svg>';

  function escapeHtmlKurir(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function hideModal(id) {
    var el = document.getElementById(id);
    if (!el || !window.bootstrap) return;
    var inst = bootstrap.Modal.getInstance(el);
    if (inst) inst.hide();
  }

  function showModal(id) {
    var el = document.getElementById(id);
    if (!el || !window.bootstrap) return;
    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  function lokasiLabelHtml(lok) {
    if (!lok) return '';
    return '<div class="j-kurir-lokasi-chosen__inner">' +
      '<i class="fas fa-map-marker-alt"></i>' +
      '<div><strong>' + escapeHtmlKurir(lok.nama || '-') + '</strong>' +
      '<small>' + escapeHtmlKurir(lok.detail || '') + '</small></div>' +
    '</div>';
  }

  function findLokasiById(id) {
    id = Number(id);
    for (var i = 0; i < kurirLokasiCache.length; i++) {
      if (Number(kurirLokasiCache[i].id_lokasi) === id) return kurirLokasiCache[i];
    }
    return null;
  }

  function renderKurirLokasiList(list, selectedId) {
    var box = document.getElementById('jKurirLokasiList');
    if (!box) return;
    kurirLokasiCache = list || [];
    if (!kurirLokasiCache.length) {
      box.innerHTML = '<div class="j-kurir-sales-empty">Belum ada lokasi. Tambahkan dulu.</div>';
      return;
    }

    var selectable = kurirLokasiCache.filter(function (lok) {
      return !(kurirPendingJenis === 'jemput' && lok.jemput_berjalan);
    });
    var prefer = selectedId || (kurirSelectedLokasi && kurirSelectedLokasi.id_lokasi) || null;
    if (prefer != null) {
      var preferOk = selectable.some(function (lok) {
        return Number(lok.id_lokasi) === Number(prefer);
      });
      if (!preferOk) prefer = null;
    }
    if (prefer == null && selectable.length) {
      prefer = selectable[0].id_lokasi;
    }

    box.innerHTML = kurirLokasiCache.map(function (lok) {
      var id = String(lok.id_lokasi);
      var blocked = kurirPendingJenis === 'jemput' && !!lok.jemput_berjalan;
      var checked = !blocked && prefer != null && String(prefer) === id ? ' checked' : '';
      var disabled = blocked ? ' disabled' : '';
      var cls = 'j-kurir-lokasi-item' + (blocked ? ' is-disabled' : '');
      var tarifHtml = '';
      // Tarif jarak hanya untuk Sameday — Instant pakai tarif Gojek/Grab
      if (kurirPendingLayanan !== 'instant' && lok.tarif != null && lok.tarif !== '') {
        var kmTxt = (lok.km != null && lok.km !== '')
          ? (Number(lok.km).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' km')
          : '';
        tarifHtml =
          '<span class="j-kurir-lokasi-item__tarif">' +
            '<span class="j-kurir-lokasi-item__tarif-label">Tarif</span>' +
            '<strong>' + (Number(lok.tarif) <= 0 ? 'Gratis' : ('Rp' + Number(lok.tarif).toLocaleString('id-ID'))) + '</strong>' +
            (kmTxt ? '<small>' + escapeHtmlKurir(kmTxt) + '</small>' : '') +
          '</span>';
      } else if (kurirPendingLayanan === 'instant') {
        tarifHtml =
          '<span class="j-kurir-lokasi-item__tarif">' +
            '<span class="j-kurir-lokasi-item__tarif-label">Ongkir</span>' +
            '<strong>Gojek/Grab</strong>' +
            '<small>lihat di langkah berikutnya</small>' +
          '</span>';
      }
      return (
        '<div class="' + cls + '">' +
          '<label class="j-kurir-lokasi-item__main">' +
            '<input type="radio" name="j_kurir_lokasi" value="' + escapeHtmlKurir(id) + '"' + checked + disabled + '>' +
            '<span class="j-kurir-lokasi-item__text">' +
              '<strong>' + escapeHtmlKurir(lok.nama || '-') +
                (blocked ? ' <span class="j-badge warn">Jemput berjalan</span>' : '') +
              '</strong>' +
              '<small>' + escapeHtmlKurir(lok.detail || '') +
                (blocked ? ' · Tidak bisa request jemput lagi ke lokasi ini sampai selesai' : '') +
              '</small>' +
            '</span>' +
          '</label>' +
          tarifHtml +
          '<div class="j-kurir-lokasi-item__acts">' +
            '<button type="button" class="j-kurir-lokasi-act j-kurir-lokasi-edit" data-id="' +
              escapeHtmlKurir(id) +
              '" title="Edit" aria-label="Edit lokasi">' +
              '<i class="fas fa-pen"></i>' +
            '</button>' +
            '<button type="button" class="j-kurir-lokasi-act j-kurir-lokasi-del" data-id="' +
              escapeHtmlKurir(id) +
              '" title="Hapus" aria-label="Hapus lokasi">' +
              '<i class="fas fa-trash"></i>' +
            '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    if (kurirPendingJenis === 'jemput' && !selectable.length) {
      box.insertAdjacentHTML(
        'beforeend',
        '<div class="j-kurir-sales-empty" style="margin-top:8px">Semua lokasi punya jemput berjalan. Tambah lokasi lain atau tunggu selesai.</div>'
      );
    }
  }

  function getSelectedLokasiFromList() {
    var checked = document.querySelector('#jKurirLokasiList input[name="j_kurir_lokasi"]:checked');
    if (!checked) return null;
    return findLokasiById(checked.value);
  }

  function showLokasiPickView() {
    var pick = document.getElementById('jKurirLokasiPick');
    var form = document.getElementById('jKurirLokasiForm');
    var footPick = document.getElementById('jKurirLokasiFootPick');
    var footForm = document.getElementById('jKurirLokasiFootForm');
    if (pick) pick.hidden = false;
    if (form) form.hidden = true;
    if (footPick) footPick.hidden = false;
    if (footForm) footForm.hidden = true;
    kurirEditingLokasiId = 0;
    var editId = document.getElementById('jLokasiEditId');
    if (editId) editId.value = '';
  }

  function showLokasiFormView(editLok) {
    var pick = document.getElementById('jKurirLokasiPick');
    var form = document.getElementById('jKurirLokasiForm');
    var footPick = document.getElementById('jKurirLokasiFootPick');
    var footForm = document.getElementById('jKurirLokasiFootForm');
    if (pick) pick.hidden = true;
    if (form) form.hidden = false;
    if (footPick) footPick.hidden = true;
    if (footForm) footForm.hidden = false;

    var isEdit = !!(editLok && editLok.id_lokasi);
    kurirEditingLokasiId = isEdit ? Number(editLok.id_lokasi) : 0;
    var editId = document.getElementById('jLokasiEditId');
    if (editId) editId.value = kurirEditingLokasiId ? String(kurirEditingLokasiId) : '';
    var saveLbl = document.getElementById('jBtnKurirLokasiSaveLabel');
    if (saveLbl) saveLbl.textContent = isEdit ? 'Simpan perubahan' : 'Simpan';

    var nama = document.getElementById('jLokasiNama');
    var detail = document.getElementById('jLokasiDetail');
    if (nama) nama.value = isEdit ? String(editLok.nama || '') : '';
    if (detail) detail.value = isEdit ? String(editLok.detail || '') : '';
    var searchInput = document.getElementById('jLokasiSearch');
    if (searchInput) searchInput.value = '';
    closeKurirSearchSuggestions();
    bindKurirMapSearch();

    var lat = isEdit ? parseFloat(editLok.latt) : NaN;
    var lng = isEdit ? parseFloat(editLok.longt) : NaN;
    // Tunggu layout form terlihat dulu — kalau map diinit saat hidden, tiles/pin bergeser
    setTimeout(function () {
      if (isEdit && !isNaN(lat) && !isNaN(lng) && !(lat === 0 && lng === 0)) {
        var loadingEl = document.getElementById('jKurirMapLoading');
        if (loadingEl) loadingEl.hidden = false;
        ensureKurirGoogleMap(lat, lng, KURIR_SELECT_ZOOM).then(function () {
          setMapHint('Geser peta agar pin berada di titik yang tepat.');
        });
      } else {
        initKurirMapThenLocate();
      }
    }, 50);
  }

  function setLokasiCoords(lat, lng) {
    var lattEl = document.getElementById('jLokasiLatt');
    var longtEl = document.getElementById('jLokasiLongt');
    var roundedLat = Math.round(Number(lat) * 1e7) / 1e7;
    var roundedLng = Math.round(Number(lng) * 1e7) / 1e7;
    if (lattEl) lattEl.value = String(roundedLat);
    if (longtEl) longtEl.value = String(roundedLng);
  }

  function readKurirMapCenter() {
    if (!kurirMap) return;
    var center = kurirMap.getCenter();
    if (!center) return;
    setLokasiCoords(center.lat(), center.lng());
  }

  function syncKurirMapView(lat, lng, zoom) {
    if (!kurirMap) return;
    zoom = typeof zoom === 'number' ? zoom : KURIR_SELECT_ZOOM;
    kurirMapSuppressIdle = true;
    if (window.google && google.maps && google.maps.event) {
      google.maps.event.trigger(kurirMap, 'resize');
    }
    kurirMap.panTo({ lat: lat, lng: lng });
    kurirMap.setZoom(zoom);
    setLokasiCoords(lat, lng);
    setTimeout(function () {
      if (!kurirMap) return;
      if (window.google && google.maps && google.maps.event) {
        google.maps.event.trigger(kurirMap, 'resize');
      }
      kurirMapSuppressIdle = false;
      readKurirMapCenter();
    }, 350);
  }

  function fetchKurirMapsConfig() {
    return fetch(base + 'J/kurirMapsConfig/' + pelangganId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json();
    });
  }

  function loadGoogleMapsApi(apiKey) {
    if (window.google && window.google.maps) {
      return Promise.resolve(window.google.maps);
    }
    if (kurirMapsScriptPromise) return kurirMapsScriptPromise;
    kurirMapsScriptPromise = new Promise(function (resolve, reject) {
      window.gm_authFailure = function () {
        reject(new Error('Google Maps menolak API key browser'));
      };
      window.__kurirGmReady = function () {
        delete window.__kurirGmReady;
        if (window.google && window.google.maps) {
          resolve(window.google.maps);
          return;
        }
        reject(new Error('Google Maps tidak tersedia'));
      };
      var script = document.createElement('script');
      script.src =
        'https://maps.googleapis.com/maps/api/js?key=' +
        encodeURIComponent(apiKey) +
        '&language=id&region=ID&callback=__kurirGmReady';
      script.async = true;
      script.defer = true;
      script.onerror = function () {
        reject(new Error('Gagal memuat Google Maps'));
      };
      document.head.appendChild(script);
    });
    return kurirMapsScriptPromise;
  }

  function ensureKurirGoogleMap(lat, lng, zoom) {
    zoom = typeof zoom === 'number' ? zoom : KURIR_SELECT_ZOOM;
    var loadingEl = document.getElementById('jKurirMapLoading');
    return fetchKurirMapsConfig()
      .then(function (cfg) {
        if (!cfg || (!cfg.ok && !cfg.status)) {
          throw new Error((cfg && cfg.message) || 'Gagal memuat konfigurasi Google Maps');
        }
        var apiKey = String(cfg.api_key || '').trim();
        if (!apiKey) throw new Error('Google Maps API key belum dikonfigurasi');
        return loadGoogleMapsApi(apiKey);
      })
      .then(function (maps) {
        if (loadingEl) loadingEl.hidden = true;
        var el = document.getElementById('jKurirMap');
        if (!el) return;
        if (!kurirMap) {
          kurirMap = new maps.Map(el, {
            center: { lat: lat, lng: lng },
            zoom: zoom,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            cameraControl: false,
            zoomControl: false
          });
          kurirMapIdleListener = kurirMap.addListener('idle', function () {
            if (kurirMapSuppressIdle) return;
            readKurirMapCenter();
          });
        }
        syncKurirMapView(lat, lng, zoom);
      })
      .catch(function (err) {
        if (loadingEl) loadingEl.hidden = true;
        toast((err && err.message) || 'Peta gagal dimuat', 'warn');
        setMapHint((err && err.message) || 'Peta gagal dimuat', true);
      });
  }

  function setMapHint(text, isWarn) {
    var hint = document.getElementById('jLokasiMapHint');
    if (!hint) return;
    hint.textContent = text || 'Geser peta agar pin berada di titik yang tepat.';
    hint.classList.toggle('is-warn', !!isWarn);
  }

  function getKurirMapBiasCoords() {
    if (!kurirMap) {
      var lattEl = document.getElementById('jLokasiLatt');
      var longtEl = document.getElementById('jLokasiLongt');
      return {
        lat: lattEl ? parseFloat(lattEl.value) : NaN,
        lng: longtEl ? parseFloat(longtEl.value) : NaN
      };
    }
    var center = kurirMap.getCenter();
    if (!center) return { lat: NaN, lng: NaN };
    return { lat: center.lat(), lng: center.lng() };
  }

  function closeKurirSearchSuggestions() {
    var list = document.getElementById('jLokasiSearchList');
    if (list) {
      list.hidden = true;
      list.innerHTML = '';
    }
  }

  function fetchKurirSearchSuggestions(query) {
    var q = String(query || '').trim();
    if (q.length < 2) {
      closeKurirSearchSuggestions();
      return;
    }
    var seq = ++kurirSearchSeq;
    var payload = { input: q };
    var bias = getKurirMapBiasCoords();
    if (!isNaN(bias.lat) && !isNaN(bias.lng)) {
      payload.lat = bias.lat;
      payload.lng = bias.lng;
    }
    fetch(base + 'J/kurirMapsAutocomplete/' + pelangganId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (seq !== kurirSearchSeq || kurirSelectingPlace) return;
        var list = document.getElementById('jLokasiSearchList');
        if (!list) return;
        var items = data && Array.isArray(data.items) ? data.items : [];
        if (!data || (!data.ok && !data.status)) {
          closeKurirSearchSuggestions();
          setMapHint((data && data.message) || 'Gagal memuat saran alamat.', true);
          return;
        }
        if (!items.length) {
          closeKurirSearchSuggestions();
          setMapHint('Tidak ada hasil untuk pencarian ini.', true);
          return;
        }
        list.innerHTML = items
          .map(function (item) {
            return (
              '<li><button type="button" data-place-id="' +
              escapeHtmlKurir(item.place_id || '') +
              '" data-label="' +
              escapeHtmlKurir(item.label || '') +
              '">' +
              escapeHtmlKurir(item.label || '') +
              '</button></li>'
            );
          })
          .join('');
        list.hidden = false;
        setMapHint('Geser peta agar pin berada di titik yang tepat.');
      })
      .catch(function () {
        if (seq !== kurirSearchSeq) return;
        closeKurirSearchSuggestions();
        setMapHint('Gagal memuat saran alamat.', true);
      });
  }

  function onKurirSearchInput() {
    if (kurirSearchTimer) clearTimeout(kurirSearchTimer);
    var input = document.getElementById('jLokasiSearch');
    var q = input ? String(input.value || '').trim() : '';
    if (q.length < 2) {
      closeKurirSearchSuggestions();
      return;
    }
    kurirSearchTimer = setTimeout(function () {
      fetchKurirSearchSuggestions(q);
    }, 280);
  }

  function selectKurirSearchSuggestion(placeId, label) {
    if (!placeId || kurirSelectingPlace) return;
    kurirSelectingPlace = true;
    closeKurirSearchSuggestions();
    var input = document.getElementById('jLokasiSearch');
    if (input) input.value = label || '';
    fetch(base + 'J/kurirMapsPlaceDetails/' + pelangganId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ place_id: placeId })
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (!data || (!data.ok && !data.status)) {
          setMapHint((data && data.message) || 'Gagal memuat detail lokasi.', true);
          return;
        }
        if (data.lat != null && data.lng != null) {
          if (kurirMap) {
            syncKurirMapView(Number(data.lat), Number(data.lng), KURIR_SELECT_ZOOM);
          } else {
            ensureKurirGoogleMap(Number(data.lat), Number(data.lng), KURIR_SELECT_ZOOM);
          }
          setMapHint('Geser peta agar pin berada di titik yang tepat.');
        } else {
          setMapHint('Koordinat lokasi tidak ditemukan.', true);
        }
      })
      .catch(function () {
        setMapHint('Gagal memuat detail lokasi.', true);
      })
      .finally(function () {
        kurirSelectingPlace = false;
      });
  }

  function bindKurirMapSearch() {
    var input = document.getElementById('jLokasiSearch');
    if (!input || input.dataset.bound) return;
    input.dataset.bound = '1';
    input.addEventListener('input', onKurirSearchInput);
    input.addEventListener('focus', onKurirSearchInput);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeKurirSearchSuggestions();
    });
  }

  function setKurirMapLocateLoading(on) {
    var wrap = document.getElementById('jKurirMapWrap');
    var overlay = document.getElementById('jKurirMapOverlay');
    if (wrap) wrap.classList.toggle('is-locating', !!on);
    if (overlay) overlay.hidden = !on;
    if (kurirMap) {
      kurirMap.setOptions({
        gestureHandling: on ? 'none' : 'auto',
        draggable: !on,
        scrollwheel: !on,
        disableDoubleClickZoom: !!on
      });
    }
  }

  function setKurirGpsBtnLoading(on) {
    var btn = document.getElementById('jBtnLokasiGps');
    setKurirMapLocateLoading(on);
    if (!btn) return;
    btn.disabled = !!on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    btn.innerHTML = on ? KURIR_GPS_SPIN_HTML : KURIR_GPS_ICON_HTML;
  }

  function initKurirMapThenLocate() {
    var btn = document.getElementById('jBtnLokasiGps');
    if (btn && btn.disabled) return;
    var loadingEl = document.getElementById('jKurirMapLoading');
    if (loadingEl) loadingEl.hidden = false;
    setKurirGpsBtnLoading(true);
    setMapHint('Mencari titik Anda…');

    var finishWithCoords = function (lat, lng, hint, isWarn) {
      ensureKurirGoogleMap(lat, lng, KURIR_SELECT_ZOOM).then(function () {
        setMapHint(hint, isWarn);
        setKurirGpsBtnLoading(false);
      });
    };

    if (!navigator.geolocation) {
      finishWithCoords(
        Number(kurirDefaultMap.latt) || 0.507068,
        Number(kurirDefaultMap.longt) || 101.447779,
        'GPS tidak tersedia · default ' + (kurirDefaultMap.nama_kota || 'kota cabang'),
        true
      );
      return;
    }
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        finishWithCoords(
          pos.coords.latitude,
          pos.coords.longitude,
          'Titik saat ini — geser peta jika perlu'
        );
      },
      function () {
        finishWithCoords(
          Number(kurirDefaultMap.latt) || 0.507068,
          Number(kurirDefaultMap.longt) || 101.447779,
          'Izin lokasi ditolak · default ' + (kurirDefaultMap.nama_kota || 'kota cabang'),
          true
        );
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  }

  function loadKurirLokasiList() {
    var box = document.getElementById('jKurirLokasiList');
    if (box) box.innerHTML = '<div class="j-kurir-sales-empty"><i class="fas fa-spinner fa-spin"></i> Memuat lokasi…</div>';
    return fetch(base + 'J/kurirLokasiList/' + pelangganId, {
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
          throw new Error((data && data.message) || 'Gagal memuat lokasi');
        }
        if (data.default_map) kurirDefaultMap = data.default_map;
        renderKurirLokasiList(data.lokasi || []);
        if (!(data.lokasi || []).length) {
          showLokasiFormView();
        } else {
          showLokasiPickView();
        }
      })
      .catch(function (err) {
        if (box) {
          box.innerHTML = '<div class="j-kurir-sales-empty">' +
            escapeHtmlKurir((err && err.message) || 'Gagal memuat lokasi') +
            '</div>';
        }
        showLokasiPickView();
      });
  }

  function openKurirFlow(jenis, layanan) {
    kurirPendingJenis = jenis;
    kurirPendingLayanan = layanan === 'instant' ? 'instant' : 'sameday';
    if (kurirPendingLayanan === 'instant') {
      var win = null;
      try {
        var cfgEl = document.getElementById('jKurirConfig');
        if (cfgEl) {
          var cfg = JSON.parse(cfgEl.textContent || '{}');
          win = cfg && cfg.instantWindow ? cfg.instantWindow : null;
        }
      } catch (e) {}
      if (win && win.ok === false) {
        toast(win.message || 'Kurir Instant di luar jam operasional', 'warn');
        return;
      }
    }
    kurirSelectedLokasi = null;
    kurirSelectedCourier = null;
    kurirPendingIds = [];
    ['jKurirCatatanAntar', 'jKurirCatatanJemput', 'jKurirCatatanCourier'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.value = '';
        el.dispatchEvent(new Event('input'));
      }
    });
    setKurirKickers();
    var title = document.getElementById('jKurirLokasiTitle');
    if (title) {
      title.textContent = jenis === 'antar' ? 'Lokasi antar' : 'Lokasi jemput';
    }
    showLokasiPickView();
    showModal('jModalKurirLokasi');
    loadKurirLokasiList();
  }

  function continueAfterLokasi() {
    if (!kurirSelectedLokasi || !kurirSelectedLokasi.id_lokasi) {
      toast('Pilih lokasi dulu', 'warn');
      return;
    }
    if (kurirPendingJenis === 'jemput' && kurirSelectedLokasi.jemput_berjalan) {
      toast('Lokasi ini masih ada jemput berjalan', 'warn');
      return;
    }
    hideModal('jModalKurirLokasi');
    if (kurirPendingJenis === 'antar') {
      var antBox = document.getElementById('jKurirAntarLokasi');
      if (antBox) antBox.innerHTML = lokasiLabelHtml(kurirSelectedLokasi);
      setKurirKickers();
      loadKurirSalesOptions();
      showModal('jModalKurirAntar');
      return;
    }
    if (kurirPendingJenis === 'jemput') {
      if (kurirPendingLayanan === 'instant') {
        openKurirCourierModal();
        return;
      }
      var jemBox = document.getElementById('jKurirJemputLokasi');
      if (jemBox) jemBox.innerHTML = lokasiLabelHtml(kurirSelectedLokasi);
      setKurirKickers();
      showModal('jModalKurirJemput');
    }
  }

  function renderKurirSales(orders) {
    var box = document.getElementById('jKurirSalesBox');
    if (!box) return;
    if (!orders || !orders.length) {
      box.innerHTML =
        '<div class="j-kurir-sales-empty">' +
        (kurirPendingLayanan === 'instant'
          ? 'Belum ada item selesai yang bisa diantar Instant.'
          : 'Tidak ada item yang bisa diantar saat ini.') +
        '</div>';
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
    var qs = 'layanan=' + encodeURIComponent(kurirPendingLayanan === 'instant' ? 'instant' : 'sameday');
    return fetch(base + 'J/kurirSalesOptions/' + pelangganId + '?' + qs, {
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
    if (!kurirSelectedLokasi || !kurirSelectedLokasi.id_lokasi) {
      toast('Pilih lokasi dulu', 'warn');
      return;
    }
    var catatan = getKurirCatatan(jenis);
    kurirBusy = true;
    if (btn) btn.disabled = true;

    var body = new URLSearchParams();
    body.set('jenis', jenis);
    body.set('id_lokasi', String(kurirSelectedLokasi.id_lokasi));
    if (catatan) body.set('catatan', catatan);
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
        hideModal('jModalKurirAntar');
        hideModal('jModalKurirJemput');
        hideModal('jModalKurirLokasi');
        kurirSelectedLokasi = null;
        kurirPendingJenis = '';
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

  function saveKurirLokasi(btn) {
    var nama = String((document.getElementById('jLokasiNama') || {}).value || '').trim();
    var detail = String((document.getElementById('jLokasiDetail') || {}).value || '').trim();
    var latt = parseFloat((document.getElementById('jLokasiLatt') || {}).value || '');
    var longt = parseFloat((document.getElementById('jLokasiLongt') || {}).value || '');
    var editId =
      kurirEditingLokasiId ||
      parseInt(String((document.getElementById('jLokasiEditId') || {}).value || '0'), 10) ||
      0;
    if (!nama) { toast('Isi nama lokasi', 'warn'); return; }
    if (!detail) { toast('Isi detail alamat', 'warn'); return; }
    if (isNaN(latt) || isNaN(longt)) { toast('Set titik di peta dulu', 'warn'); return; }

    if (btn) btn.disabled = true;
    var body = new URLSearchParams();
    body.set('nama', nama);
    body.set('detail', detail);
    body.set('latt', String(latt));
    body.set('longt', String(longt));
    var url = base + 'J/kurirLokasiAdd/' + pelangganId;
    if (editId > 0) {
      body.set('id_lokasi', String(editId));
      url = base + 'J/kurirLokasiUpdate/' + pelangganId;
    }

    fetch(url, {
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
          throw new Error((data && data.message) || 'Gagal menyimpan lokasi');
        }
        toast(data.message || 'Lokasi disimpan', 'ok');
        kurirSelectedLokasi = data.lokasi || null;
        renderKurirLokasiList(data.list || [], kurirSelectedLokasi && kurirSelectedLokasi.id_lokasi);
        showLokasiPickView();
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal menyimpan lokasi', 'warn');
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function deleteKurirLokasi(id, btn) {
    id = parseInt(id, 10) || 0;
    if (id <= 0) return;
    var lok = findLokasiById(id);
    var label = lok && lok.nama ? lok.nama : 'lokasi ini';
    if (!window.confirm('Hapus "' + label + '"?')) return;
    if (btn) btn.disabled = true;
    var body = new URLSearchParams();
    body.set('id_lokasi', String(id));
    fetch(base + 'J/kurirLokasiDelete/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      credentials: 'same-origin',
      body: body.toString(),
    })
      .then(function (res) {
        return res.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'Gagal menghapus lokasi');
        }
        toast(data.message || 'Lokasi dihapus', 'ok');
        if (kurirSelectedLokasi && Number(kurirSelectedLokasi.id_lokasi) === id) {
          kurirSelectedLokasi = null;
        }
        renderKurirLokasiList(data.list || [], null);
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal menghapus lokasi', 'warn');
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var riwayatToggle = e.target.closest('#jBtnKurirRiwayatToggle');
    if (riwayatToggle && content.contains(riwayatToggle)) {
      e.preventDefault();
      var list = document.getElementById('jKurirRiwayatList');
      if (!list) return;
      var open = list.hidden;
      list.hidden = !open;
      riwayatToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      riwayatToggle.textContent = open ? 'Sembunyikan' : 'Lihat';
      return;
    }
    var act = e.target.closest('.j-kurir-act');
    if (!act || !content.contains(act)) return;
    e.preventDefault();
    if (act.disabled) return;
    var jenis = (act.getAttribute('data-j-kurir-jenis') || '').toLowerCase();
    var layanan = (act.getAttribute('data-j-kurir-layanan') || 'sameday').toLowerCase();
    if (jenis !== 'antar' && jenis !== 'jemput') return;
    openKurirFlow(jenis, layanan);
  });

  document.addEventListener('click', function (e) {
    var paySaldoBtn = e.target.closest('.j-kurir-pay-saldo-instant');
    if (paySaldoBtn && content.contains(paySaldoBtn)) {
      e.preventDefault();
      var idReqSaldo = paySaldoBtn.getAttribute('data-id-request');
      var totalSaldo = paySaldoBtn.getAttribute('data-total') || '0';
      if (!idReqSaldo) return;
      if (
        !window.confirm(
          'Bayar ongkir Instant Rp' +
            Number(totalSaldo).toLocaleString('id-ID') +
            ' pakai Saldo Deposit?'
        )
      ) {
        return;
      }
      paySaldoBtn.disabled = true;
      var bodySaldo = new URLSearchParams();
      bodySaldo.set('id_request', String(idReqSaldo));
      fetch(base + 'J/kurirInstantPaySaldo/' + pelangganId, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin',
        body: bodySaldo.toString(),
      })
        .then(function (res) {
          return res.json().catch(function () {
            throw new Error('Respons tidak valid');
          });
        })
        .then(function (data) {
          if (!data || !data.ok) {
            throw new Error((data && data.message) || 'Gagal bayar Saldo');
          }
          toast(
            data.message || (data.paid ? 'Pembayaran QRIS sudah berhasil' : 'Pembayaran Saldo berhasil'),
            'ok'
          );
          loadPage('kurir', '', false);
        })
        .catch(function (err) {
          toast((err && err.message) || 'Gagal bayar Saldo', 'warn');
          paySaldoBtn.disabled = false;
        });
      return;
    }
    var payBtn = e.target.closest('.j-kurir-pay-instant');
    if (payBtn && content.contains(payBtn)) {
      e.preventDefault();
      openInstantQr(
        payBtn.getAttribute('data-ref'),
        payBtn.getAttribute('data-total'),
        payBtn
      );
      return;
    }
    var batalBtn = e.target.closest('.j-kurir-batal-instant');
    if (batalBtn && content.contains(batalBtn)) {
      e.preventDefault();
      var idReq = batalBtn.getAttribute('data-id-request');
      if (!idReq || batalBtn.disabled) return;
      pendingCancelKurirInstant = { idRequest: idReq, btn: batalBtn };
      var info = document.getElementById('jCancelKurirInstantInfo');
      if (info) {
        info.textContent = 'Order #' + idReq + ' yang belum dibayar akan dibatalkan.';
      }
      var modalEl = document.getElementById('jModalCancelKurirInstant');
      if (!modalEl || !window.bootstrap) {
        if (!window.confirm('Batalkan permintaan Instant yang belum dibayar?')) {
          pendingCancelKurirInstant = null;
          return;
        }
        submitCancelKurirInstant();
        return;
      }
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
  });

  function submitCancelKurirInstant() {
    if (!pendingCancelKurirInstant || !pendingCancelKurirInstant.idRequest) return;
    var idReq = pendingCancelKurirInstant.idRequest;
    var batalBtn = pendingCancelKurirInstant.btn;
    pendingCancelKurirInstant = null;
    if (batalBtn) batalBtn.disabled = true;
    var body = new URLSearchParams();
    body.set('id_request', String(idReq));
    fetch(base + 'J/kurirInstantBatal/' + pelangganId, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      credentials: 'same-origin',
      body: body.toString(),
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
        toast(
          data.message || (data.paid ? 'Pembayaran QRIS sudah berhasil, tidak dibatalkan' : 'Dibatalkan'),
          'ok'
        );
        loadPage('kurir', '', false);
      })
      .catch(function (err) {
        toast((err && err.message) || 'Gagal membatalkan', 'warn');
        if (batalBtn) batalBtn.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var confirmBtn = e.target.closest('#jBtnConfirmCancelKurirInstant');
    if (!confirmBtn) return;
    e.preventDefault();
    hideModal('jModalCancelKurirInstant');
    submitCancelKurirInstant();
  });

  var cancelKurirInstantModal = document.getElementById('jModalCancelKurirInstant');
  if (cancelKurirInstantModal) {
    cancelKurirInstantModal.addEventListener('hidden.bs.modal', function () {
      if (pendingCancelKurirInstant && pendingCancelKurirInstant.btn) {
        pendingCancelKurirInstant.btn.disabled = false;
      }
      pendingCancelKurirInstant = null;
    });
  }

  document.addEventListener('click', function (e) {
    var editBtn = e.target.closest('.j-kurir-lokasi-edit');
    if (editBtn) {
      e.preventDefault();
      e.stopPropagation();
      var editLok = findLokasiById(editBtn.getAttribute('data-id'));
      if (!editLok) {
        toast('Lokasi tidak ditemukan', 'warn');
        return;
      }
      showLokasiFormView(editLok);
      return;
    }
    var delBtn = e.target.closest('.j-kurir-lokasi-del');
    if (delBtn) {
      e.preventDefault();
      e.stopPropagation();
      deleteKurirLokasi(delBtn.getAttribute('data-id'), delBtn);
      return;
    }
    if (e.target.closest('#jBtnKurirLokasiAdd')) {
      e.preventDefault();
      showLokasiFormView(null);
      return;
    }
    if (e.target.closest('#jBtnKurirLokasiBack')) {
      e.preventDefault();
      showLokasiPickView();
      return;
    }
    if (e.target.closest('#jBtnKurirLokasiNext')) {
      e.preventDefault();
      kurirSelectedLokasi = getSelectedLokasiFromList();
      if (!kurirSelectedLokasi) {
        toast(
          kurirPendingJenis === 'jemput'
            ? 'Pilih lokasi yang belum ada jemput berjalan, atau tambah lokasi baru'
            : 'Pilih lokasi dulu, atau tambah lokasi baru',
          'warn'
        );
        return;
      }
      continueAfterLokasi();
      return;
    }
    var saveBtn = e.target.closest('#jBtnKurirLokasiSave');
    if (saveBtn) {
      e.preventDefault();
      saveKurirLokasi(saveBtn);
      return;
    }
    if (e.target.closest('#jBtnLokasiGps')) {
      e.preventDefault();
      initKurirMapThenLocate();
      return;
    }
    var searchBtn = e.target.closest('#jLokasiSearchList button[data-place-id]');
    if (searchBtn) {
      e.preventDefault();
      selectKurirSearchSuggestion(
        searchBtn.getAttribute('data-place-id'),
        searchBtn.getAttribute('data-label') || searchBtn.textContent || ''
      );
      return;
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
    if (kurirPendingLayanan === 'instant') {
      kurirPendingIds = ids;
      hideModal('jModalKurirAntar');
      openKurirCourierModal();
      return;
    }
    submitKurirSameday('antar', ids, btn);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#jBtnConfirmKurirJemput');
    if (!btn) return;
    e.preventDefault();
    if (kurirPendingLayanan === 'instant') {
      hideModal('jModalKurirJemput');
      openKurirCourierModal();
      return;
    }
    submitKurirSameday('jemput', [], btn);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#jBtnSubmitKurirCourier');
    if (!btn) return;
    e.preventDefault();
    var ids = kurirPendingJenis === 'antar' ? kurirPendingIds || [] : [];
    submitKurirInstant(kurirPendingJenis, ids, btn);
  });

  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'jKurirCourierMetode') {
      e.target.setAttribute('data-touched', '1');
      syncKurirPayMetode();
    }
  });

  var riwayatMonthShort = [
    '', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
  ];

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function closeRiwayatMonthPicker() {
    var root = document.getElementById('jRiwayatMonthPicker');
    var panel = document.getElementById('jRiwayatMonthPanel');
    var btn = document.getElementById('jRiwayatBulanBtn');
    if (!root || !panel || !btn) return;
    panel.hidden = true;
    root.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
  }

  function renderRiwayatMonthGrid(year) {
    var root = document.getElementById('jRiwayatMonthPicker');
    var grid = document.getElementById('jRiwayatMonthGrid');
    var yearLabel = document.getElementById('jRiwayatYearLabel');
    var prevBtn = document.getElementById('jRiwayatYearPrev');
    var nextBtn = document.getElementById('jRiwayatYearNext');
    if (!root || !grid) return;
    var minYm = root.getAttribute('data-min') || '2000-01';
    var maxYm = root.getAttribute('data-max') || '2099-12';
    var selectedYm = root.getAttribute('data-ym') || '';
    var minY = parseInt(minYm.slice(0, 4), 10);
    var maxY = parseInt(maxYm.slice(0, 4), 10);
    if (yearLabel) yearLabel.textContent = String(year);
    if (prevBtn) prevBtn.disabled = year <= minY;
    if (nextBtn) nextBtn.disabled = year >= maxY;
    var html = '';
    for (var m = 1; m <= 12; m++) {
      var ym = year + '-' + pad2(m);
      var disabled = ym < minYm || ym > maxYm;
      var selected = ym === selectedYm;
      html += '<button type="button" class="j-month-picker__month' + (selected ? ' is-selected' : '') + '"'
        + ' data-month="' + m + '"'
        + (disabled ? ' disabled' : '') + '>'
        + (riwayatMonthShort[m] || m)
        + '</button>';
    }
    grid.innerHTML = html;
    root.setAttribute('data-view-year', String(year));
  }

  function openRiwayatMonthPicker() {
    var root = document.getElementById('jRiwayatMonthPicker');
    var panel = document.getElementById('jRiwayatMonthPanel');
    var btn = document.getElementById('jRiwayatBulanBtn');
    if (!root || !panel || !btn) return;
    var ym = root.getAttribute('data-ym') || '';
    var year = parseInt((ym.split('-')[0] || ''), 10) || (new Date()).getFullYear();
    renderRiwayatMonthGrid(year);
    panel.hidden = false;
    root.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
  }

  document.addEventListener('click', function (e) {
    var root = document.getElementById('jRiwayatMonthPicker');
    if (!root) return;

    var trigger = e.target.closest('#jRiwayatBulanBtn');
    if (trigger && root.contains(trigger)) {
      e.preventDefault();
      if (root.classList.contains('is-open')) closeRiwayatMonthPicker();
      else openRiwayatMonthPicker();
      return;
    }

    var prev = e.target.closest('#jRiwayatYearPrev');
    if (prev && root.contains(prev)) {
      e.preventDefault();
      var yPrev = parseInt(root.getAttribute('data-view-year') || '0', 10) - 1;
      if (yPrev > 0) renderRiwayatMonthGrid(yPrev);
      return;
    }

    var next = e.target.closest('#jRiwayatYearNext');
    if (next && root.contains(next)) {
      e.preventDefault();
      var yNext = parseInt(root.getAttribute('data-view-year') || '0', 10) + 1;
      if (yNext > 0) renderRiwayatMonthGrid(yNext);
      return;
    }

    var monthBtn = e.target.closest('.j-month-picker__month');
    if (monthBtn && root.contains(monthBtn) && !monthBtn.disabled) {
      e.preventDefault();
      var month = parseInt(monthBtn.getAttribute('data-month') || '0', 10);
      var year = parseInt(root.getAttribute('data-view-year') || '0', 10);
      if (month >= 1 && month <= 12 && year > 0) {
        var ymPick = year + '-' + pad2(month);
        closeRiwayatMonthPicker();
        loadPage('riwayat', ymPick, true);
      }
      return;
    }

    if (root.classList.contains('is-open') && !root.contains(e.target)) {
      closeRiwayatMonthPicker();
    }
  });

  document.addEventListener('click', function (e) {
    var wrap = document.getElementById('jLokasiSearchWrap');
    if (wrap && !wrap.contains(e.target)) {
      closeKurirSearchSuggestions();
    }
  });

  var lokasiModal = document.getElementById('jModalKurirLokasi');
  if (lokasiModal) {
    lokasiModal.addEventListener('shown.bs.modal', function () {
      var form = document.getElementById('jKurirLokasiForm');
      if (!kurirMap || !form || form.hidden) return;
      var lat = parseFloat((document.getElementById('jLokasiLatt') || {}).value || '');
      var lng = parseFloat((document.getElementById('jLokasiLongt') || {}).value || '');
      if (isNaN(lat) || isNaN(lng)) return;
      if (window.google && google.maps && google.maps.event) {
        google.maps.event.trigger(kurirMap, 'resize');
      }
      syncKurirMapView(lat, lng, kurirMap.getZoom());
    });
  }

  var initialPage = app.getAttribute('data-page') || 'home';
  var initialExtra = app.getAttribute('data-extra') || '';
  history.replaceState({ page: initialPage, extra: initialExtra }, '', pageUrl(initialPage, initialExtra));
  loadPage(initialPage, initialExtra, false);
})();
