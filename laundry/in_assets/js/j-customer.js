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
  var kurirPendingJenis = '';
  var kurirSelectedLokasi = null;
  var kurirLokasiCache = [];
  var kurirDefaultMap = { latt: 0.507068, longt: 101.447779, nama_kota: 'PEKANBARU', source: 'fallback' };
  var kurirMap = null;
  var kurirMarker = null;

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
      return '<label class="' + cls + '">' +
        '<input type="radio" name="j_kurir_lokasi" value="' + escapeHtmlKurir(id) + '"' + checked + disabled + '>' +
        '<span class="j-kurir-lokasi-item__text">' +
          '<strong>' + escapeHtmlKurir(lok.nama || '-') +
            (blocked ? ' <span class="j-badge warn">Jemput berjalan</span>' : '') +
          '</strong>' +
          '<small>' + escapeHtmlKurir(lok.detail || '') +
            (blocked ? ' · Tidak bisa request jemput lagi ke lokasi ini sampai selesai' : '') +
          '</small>' +
        '</span>' +
      '</label>';
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
    var id = parseInt(checked.value, 10);
    for (var i = 0; i < kurirLokasiCache.length; i++) {
      if (Number(kurirLokasiCache[i].id_lokasi) === id) return kurirLokasiCache[i];
    }
    return null;
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
  }

  function showLokasiFormView() {
    var pick = document.getElementById('jKurirLokasiPick');
    var form = document.getElementById('jKurirLokasiForm');
    var footPick = document.getElementById('jKurirLokasiFootPick');
    var footForm = document.getElementById('jKurirLokasiFootForm');
    if (pick) pick.hidden = true;
    if (form) form.hidden = false;
    if (footPick) footPick.hidden = true;
    if (footForm) footForm.hidden = false;
    var nama = document.getElementById('jLokasiNama');
    var detail = document.getElementById('jLokasiDetail');
    if (nama) nama.value = '';
    if (detail) detail.value = '';
    // Tunggu layout form terlihat dulu — kalau map diinit saat hidden, tiles/pin bergeser
    setTimeout(function () {
      initKurirMapThenLocate();
    }, 50);
  }

  function setKurirMapMarker(lat, lng) {
    if (!kurirMap || typeof L === 'undefined') return;
    if (kurirMarker) {
      kurirMarker.setLatLng([lat, lng]);
    } else {
      kurirMarker = L.marker([lat, lng], { draggable: true }).addTo(kurirMap);
      kurirMarker.on('dragend', function () {
        var p = kurirMarker.getLatLng();
        document.getElementById('jLokasiLatt').value = String(p.lat);
        document.getElementById('jLokasiLongt').value = String(p.lng);
      });
    }
    document.getElementById('jLokasiLatt').value = String(lat);
    document.getElementById('jLokasiLongt').value = String(lng);
  }

  function syncKurirMapView(lat, lng, zoom) {
    if (!kurirMap) return;
    zoom = typeof zoom === 'number' ? zoom : 16;
    // invalidateSize dulu, baru setView — mencegah offset/geser
    kurirMap.invalidateSize(true);
    setKurirMapMarker(lat, lng);
    kurirMap.setView([lat, lng], zoom, { animate: false });
    // Pass kedua setelah reflow modal/bootstrap
    setTimeout(function () {
      if (!kurirMap) return;
      kurirMap.invalidateSize(true);
      kurirMap.setView([lat, lng], zoom, { animate: false });
      if (kurirMarker) kurirMarker.setLatLng([lat, lng]);
    }, 180);
  }

  function ensureKurirMap(lat, lng) {
    if (typeof L === 'undefined') {
      toast('Peta gagal dimuat', 'warn');
      return;
    }
    var el = document.getElementById('jKurirMap');
    if (!el) return;
    if (!kurirMap) {
      kurirMap = L.map(el, {
        center: [lat, lng],
        zoom: 15,
        zoomControl: true
      });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19
      }).addTo(kurirMap);
      kurirMap.on('click', function (ev) {
        syncKurirMapView(ev.latlng.lat, ev.latlng.lng, kurirMap.getZoom());
      });
    }
    syncKurirMapView(lat, lng, 16);
  }

  function setMapHint(text) {
    var hint = document.getElementById('jLokasiMapHint');
    if (hint) hint.textContent = text;
  }

  function useDefaultMapPoint(reason) {
    var lat = Number(kurirDefaultMap.latt) || 0.507068;
    var lng = Number(kurirDefaultMap.longt) || 101.447779;
    ensureKurirMap(lat, lng);
    var kota = kurirDefaultMap.nama_kota || 'kota cabang';
    setMapHint(reason || ('Default: ' + kota + ' — klik/geser pin'));
  }

  function initKurirMapThenLocate() {
    useDefaultMapPoint('Mencari titik Anda…');
    if (!navigator.geolocation) {
      useDefaultMapPoint('GPS tidak tersedia · default kota cabang');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        ensureKurirMap(pos.coords.latitude, pos.coords.longitude);
        setMapHint('Titik saat ini — klik/geser pin jika perlu');
      },
      function () {
        useDefaultMapPoint('Izin lokasi ditolak · default kota cabang');
      },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
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

  function openKurirFlow(jenis) {
    kurirPendingJenis = jenis;
    kurirSelectedLokasi = null;
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
      loadKurirSalesOptions();
      showModal('jModalKurirAntar');
      return;
    }
    if (kurirPendingJenis === 'jemput') {
      var jemBox = document.getElementById('jKurirJemputLokasi');
      if (jemBox) jemBox.innerHTML = lokasiLabelHtml(kurirSelectedLokasi);
      showModal('jModalKurirJemput');
    }
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
    if (!kurirSelectedLokasi || !kurirSelectedLokasi.id_lokasi) {
      toast('Pilih lokasi dulu', 'warn');
      return;
    }
    kurirBusy = true;
    if (btn) btn.disabled = true;

    var body = new URLSearchParams();
    body.set('jenis', jenis);
    body.set('id_lokasi', String(kurirSelectedLokasi.id_lokasi));
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
    if (!nama) { toast('Isi nama lokasi', 'warn'); return; }
    if (!detail) { toast('Isi detail alamat', 'warn'); return; }
    if (isNaN(latt) || isNaN(longt)) { toast('Set titik di peta dulu', 'warn'); return; }

    if (btn) btn.disabled = true;
    var body = new URLSearchParams();
    body.set('nama', nama);
    body.set('detail', detail);
    body.set('latt', String(latt));
    body.set('longt', String(longt));

    fetch(base + 'J/kurirLokasiAdd/' + pelangganId, {
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

  document.addEventListener('click', function (e) {
    var act = e.target.closest('.j-kurir-act');
    if (!act || !content.contains(act)) return;
    e.preventDefault();
    if (act.disabled) return;
    var jenis = (act.getAttribute('data-j-kurir-jenis') || '').toLowerCase();
    if (jenis !== 'antar' && jenis !== 'jemput') return;
    openKurirFlow(jenis);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('#jBtnKurirLokasiAdd')) {
      e.preventDefault();
      showLokasiFormView();
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

  var lokasiModal = document.getElementById('jModalKurirLokasi');
  if (lokasiModal) {
    lokasiModal.addEventListener('shown.bs.modal', function () {
      var form = document.getElementById('jKurirLokasiForm');
      if (!kurirMap || !form || form.hidden) return;
      var lat = parseFloat((document.getElementById('jLokasiLatt') || {}).value || '');
      var lng = parseFloat((document.getElementById('jLokasiLongt') || {}).value || '');
      if (isNaN(lat) || isNaN(lng)) return;
      syncKurirMapView(lat, lng, 16);
    });
  }

  var initialPage = app.getAttribute('data-page') || 'home';
  var initialExtra = app.getAttribute('data-extra') || '';
  history.replaceState({ page: initialPage, extra: initialExtra }, '', pageUrl(initialPage, initialExtra));
  loadPage(initialPage, initialExtra, false);
})();
