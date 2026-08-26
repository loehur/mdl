/**
 * Operasi — selesaikan delivery_request pelanggan (alur sama Delivery/selesai_request).
 */
(function () {
  if (window.OpDlvSelesai && window.OpDlvSelesai._inited) {
    return;
  }

  var karyawanSelectize = null;
  var batalKaryawanSelectize = null;
  var tarifSurcasLoading = false;
  var hooksBound = false;

  function cfg() {
    var root = document.getElementById('op-dlv-root');
    if (!root) {
      return {
        salesOptionsUrl: '',
        selesaiRequestUrl: '',
        batalRequestUrl: '',
        pendingRequestUrl: '',
        tarikLokasiUrl: '',
        tarifSurcasUrl: ''
      };
    }
    return {
      salesOptionsUrl: root.getAttribute('data-sales-options-url') || '',
      selesaiRequestUrl: root.getAttribute('data-selesai-request-url') || '',
      batalRequestUrl: root.getAttribute('data-batal-request-url') || '',
      pendingRequestUrl: root.getAttribute('data-pending-request-url') || '',
      tarikLokasiUrl: root.getAttribute('data-tarik-lokasi-url') || '',
      tarifSurcasUrl: root.getAttribute('data-tarif-surcas-url') || ''
    };
  }

  function toast(msg, type) {
    if (window.MdlToast) {
      if (type === 'error' || type === 'danger') MdlToast.error(msg);
      else if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
      else if (type === 'ok' || type === 'success') MdlToast.ok(msg);
      else MdlToast.info(msg);
      return;
    }
    alert(msg);
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function fmtTime(t) {
    if (!t) return '';
    var d = new Date(String(t).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(t);
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function openModal(id) {
    if (window.OpModal && typeof OpModal.open === 'function') {
      OpModal.open(id);
      return;
    }
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('op-modal-open');
  }

  function closeModal(id) {
    if (window.OpModal && typeof OpModal.close === 'function') {
      OpModal.close(id);
      return;
    }
    var modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.op-modal.is-open')) {
      document.body.classList.remove('op-modal-open');
    }
  }

  function reloadOperasi() {
    if (typeof loadDiv === 'function') {
      loadDiv();
      return;
    }
    if (typeof loadDataOnly === 'function' && window.ViewLoadConfig && window.ViewLoadConfig.idPelanggan) {
      loadDataOnly(window.ViewLoadConfig.idPelanggan);
    }
  }

  function ensureKaryawanSelectize() {
    if (!window.jQuery || !jQuery.fn.selectize) return;
    if (!karyawanSelectize) {
      var $selesai = jQuery('#dlvSelesaiKaryawan');
      if ($selesai.length) {
        if ($selesai[0].selectize) karyawanSelectize = $selesai[0].selectize;
        else karyawanSelectize = $selesai.selectize()[0].selectize;
      }
    }
    if (!batalKaryawanSelectize) {
      var $batal = jQuery('#dlvBatalKaryawan');
      if ($batal.length) {
        if ($batal[0].selectize) batalKaryawanSelectize = $batal[0].selectize;
        else batalKaryawanSelectize = $batal.selectize()[0].selectize;
      }
    }
  }

  function setJenisLocked(jenis) {
    var freeWrap = document.getElementById('dlvSelesaiJenisFreeWrap');
    var lockedWrap = document.getElementById('dlvSelesaiJenisLockedWrap');
    var lockedVal = document.getElementById('dlvSelesaiJenisLocked');
    var pill = document.getElementById('dlvSelesaiJenisLockedPill');
    var jenisEl = document.getElementById('dlvSelesaiJenis');
    var j = String(jenis || '').toLowerCase();
    var ok = j === 'antar' || j === 'jemput';
    if (ok) {
      if (jenisEl) {
        jenisEl.value = j;
        jenisEl.disabled = true;
        jenisEl.required = false;
      }
      if (lockedVal) lockedVal.value = j;
      if (pill) {
        pill.textContent = j === 'antar' ? 'Antar' : 'Jemput';
        pill.className = 'dlv-jenis-pill dlv-jenis-pill--' + j;
      }
      if (freeWrap) freeWrap.hidden = true;
      if (lockedWrap) lockedWrap.hidden = false;
      syncSelesaiKaryawanLabel();
      return;
    }
    if (jenisEl) {
      jenisEl.disabled = false;
      jenisEl.required = true;
    }
    if (lockedVal) lockedVal.value = '';
    if (freeWrap) freeWrap.hidden = false;
    if (lockedWrap) lockedWrap.hidden = true;
    syncSelesaiKaryawanLabel();
  }

  function getSelesaiJenis() {
    var locked = (document.getElementById('dlvSelesaiJenisLocked') || {}).value || '';
    if (locked === 'antar' || locked === 'jemput') return locked;
    return ((document.getElementById('dlvSelesaiJenis') || {}).value || '').toLowerCase();
  }

  function syncSelesaiKaryawanLabel() {
    var label = document.getElementById('dlvSelesaiKaryawanLabel');
    if (!label) return;
    var jenis = getSelesaiJenis();
    if (jenis === 'antar') {
      label.textContent = 'Petugas Antar';
    } else if (jenis === 'jemput') {
      label.textContent = 'Petugas Jemput';
    } else {
      label.textContent = 'Petugas';
    }
  }

  function resetSelesaiForm() {
    setJenisLocked('');
    var fields = ['dlvSelesaiPhone', 'dlvSelesaiPelanggan', 'dlvSelesaiRequestId', 'dlvSelesaiPrefill'];
    fields.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    var modeEl = document.getElementById('dlvSelesaiMode');
    if (modeEl) modeEl.value = 'request';
    var layananEl = document.getElementById('dlvSelesaiLayanan');
    if (layananEl) layananEl.value = 'sameday';
    var boundEl = document.getElementById('dlvSelesaiSurcasBound');
    if (boundEl) boundEl.value = '0';
    var box = document.getElementById('dlvSelesaiSales');
    if (box) box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
    ['dlvSurcasJemputRow', 'dlvSurcasAntarRow'].forEach(function (id) {
      var row = document.getElementById(id);
      if (row) row.hidden = true;
    });
    ['dlvSurcasJemputJumlah', 'dlvSurcasAntarJumlah'].forEach(function (id) {
      var input = document.getElementById(id);
      if (!input) return;
      input.value = '';
      input.required = false;
      input.readOnly = false;
      delete input.dataset.surcasLocked;
      delete input.dataset.userEdited;
      input.removeAttribute('data-tarif-fixed');
    });
    var batalBtn = document.getElementById('dlvSelesaiBatal');
    if (batalBtn) batalBtn.hidden = false;
    if (karyawanSelectize) karyawanSelectize.clear(true);
    else {
      var sel = document.getElementById('dlvSelesaiKaryawan');
      if (sel) sel.value = '';
    }
    syncSelesaiKaryawanLabel();
  }

  function isSelesaiSurcasBound() {
    return (document.getElementById('dlvSelesaiSurcasBound') || {}).value === '1';
  }

  function lockHideSurcasRow(row, input) {
    if (row) row.hidden = true;
    if (!input) return;
    input.required = false;
    input.readOnly = true;
    input.dataset.surcasLocked = '1';
  }

  function checkedSalesInputs() {
    return document.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
  }

  function syncSurcasAntarUi() {
    var row = document.getElementById('dlvSurcasAntarRow');
    var input = document.getElementById('dlvSurcasAntarJumlah');
    var jenis = getSelesaiJenis();
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    if (!row || !input) return;
    if (layanan === 'instant' || jenis !== 'antar') {
      row.hidden = true;
      input.required = false;
      return;
    }
    var checks = checkedSalesInputs();
    var allBound = checks.length > 0;
    for (var i = 0; i < checks.length; i++) {
      if (checks[i].getAttribute('data-surcas-antar') !== '1') {
        allBound = false;
        break;
      }
    }
    if (isSelesaiSurcasBound() || allBound) {
      lockHideSurcasRow(row, input);
      return;
    }
    row.hidden = false;
    input.readOnly = false;
    input.required = true;
    delete input.dataset.surcasLocked;
  }

  function syncSurcasJemputUi() {
    var row = document.getElementById('dlvSurcasJemputRow');
    var input = document.getElementById('dlvSurcasJemputJumlah');
    var hint = document.getElementById('dlvSurcasJemputHint');
    var jenis = getSelesaiJenis();
    var layanan = (document.getElementById('dlvSelesaiLayanan') || {}).value || 'sameday';
    if (!row || !input) return;
    if (jenis !== 'jemput' || layanan === 'instant') {
      row.hidden = true;
      input.required = false;
      syncSurcasAntarUi();
      return;
    }
    var fixed = parseInt(input.getAttribute('data-tarif-fixed') || '0', 10) || 0;
    var checks = checkedSalesInputs();
    var allBound = checks.length > 0;
    for (var i = 0; i < checks.length; i++) {
      if (checks[i].getAttribute('data-surcas-jemput') !== '1') {
        allBound = false;
        break;
      }
    }
    if (isSelesaiSurcasBound() || allBound) {
      lockHideSurcasRow(row, input);
      syncSurcasAntarUi();
      return;
    }
    row.hidden = false;
    input.readOnly = false;
    input.required = true;
    delete input.dataset.surcasLocked;
    if (!input.dataset.userEdited && fixed > 0) input.value = String(fixed);
    if (hint) {
      hint.innerHTML = fixed > 0
        ? '<i class="fas fa-info-circle me-1"></i>Default tarif request Rp' + Number(fixed).toLocaleString('id-ID') + '. Wajib diisi (0 = gratis).'
        : '<i class="fas fa-info-circle me-1"></i>Wajib diisi. Isi nominal, atau 0 untuk gratis.';
    }
    syncSurcasAntarUi();
  }

  function renderSalesOptions(orders, prefillIds) {
    var box = document.getElementById('dlvSelesaiSales');
    if (!box) return;
    if (!orders || !orders.length) {
      box.innerHTML = '<div class="dlv-sales-empty">Tidak ada item eligible</div>';
      syncSurcasJemputUi();
      return;
    }
    var prefillSet = {};
    (prefillIds || []).forEach(function (id) {
      if (id > 0) prefillSet[id] = true;
    });
    function isBelum(it) {
      return !!(it.belum_selesai === true || it.belum_selesai === 1 || it.belum_selesai === '1');
    }
    function renderItem(it, belum) {
      var status = Number(it.tuntas) === 1 ? 'Tuntas' : 'Proses';
      if (belum) {
        return '<label class="dlv-sales-item is-locked">' +
          '<input type="checkbox" disabled tabindex="-1">' +
          '<span class="dlv-sales-item__text">' + escapeHtml(it.kategori || '-') +
          ' · ' + escapeHtml(it.qty_show || '') +
          '<div class="dlv-sales-item__meta">#' + escapeHtml(String(it.id)) + ' · Belum selesai laundry</div></span></label>';
      }
      var checked = prefillSet[parseInt(it.id, 10) || 0] ? ' checked' : '';
      var hasJ = (it.surcas_jemput === true || it.surcas_jemput === 1 || it.surcas_jemput === '1') ? ' data-surcas-jemput="1"' : '';
      var hasA = (it.surcas_antar === true || it.surcas_antar === 1 || it.surcas_antar === '1') ? ' data-surcas-antar="1"' : '';
      return '<label class="dlv-sales-item">' +
        '<input type="checkbox" name="ids[]" value="' + escapeHtml(String(it.id)) + '"' + checked + hasJ + hasA + '>' +
        '<span class="dlv-sales-item__text">' + escapeHtml(it.kategori || '-') +
        (it.durasi ? ' · ' + escapeHtml(it.durasi) : '') +
        ' · ' + escapeHtml(it.qty_show || '') +
        '<div class="dlv-sales-item__meta">#' + escapeHtml(String(it.id)) + ' · ' + status + '</div></span></label>';
    }
    var html = orders.map(function (ord) {
      var items = (ord.items || []).filter(function (it) { return !isBelum(it); });
      if (!items.length) return '';
      return '<div class="dlv-sales-group" data-no-ref="' + escapeHtml(String(ord.no_ref || '-')) + '">' +
        '<div class="dlv-sales-group__head">#' + escapeHtml(String(ord.no_ref || '-')) +
        (ord.insertTime ? ' · ' + escapeHtml(fmtTime(ord.insertTime)) : '') + '</div>' +
        items.map(function (it) { return renderItem(it, false); }).join('') + '</div>';
    }).join('');
    box.innerHTML = html || '<div class="dlv-sales-empty">Tidak ada item siap</div>';
    box.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var jInput = document.getElementById('dlvSurcasJemputJumlah');
        if (jInput) delete jInput.dataset.userEdited;
        syncSurcasJemputUi();
        syncSurcasAntarUi();
      });
    });
    syncSurcasJemputUi();
    syncSurcasAntarUi();
  }

  function loadSalesOptions() {
    var c = cfg();
    var phone = (document.getElementById('dlvSelesaiPhone') || {}).value || '';
    var jenis = getSelesaiJenis();
    var box = document.getElementById('dlvSelesaiSales');
    var reqId = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var prefillRaw = (document.getElementById('dlvSelesaiPrefill') || {}).value || '';
    var prefillIds = prefillRaw
      ? prefillRaw.split(',').map(function (s) { return parseInt(s, 10); }).filter(function (n) { return n > 0; })
      : [];
    if (!box) return;
    if (!phone || !jenis) {
      box.innerHTML = '<div class="dlv-sales-empty">Pilih jenis terlebih dahulu</div>';
      return;
    }
    box.innerHTML = '<div class="dlv-sales-empty"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>';
    var url = c.salesOptionsUrl + encodeURIComponent(phone) + '?jenis=' + encodeURIComponent(jenis);
    if (reqId) url += '&id_request=' + encodeURIComponent(reqId);
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          box.innerHTML = '<div class="dlv-sales-empty">' + escapeHtml((res && res.message) || 'Gagal memuat item') + '</div>';
          return;
        }
        renderSalesOptions((res.data && res.data.orders) || [], prefillIds);
      })
      .catch(function () {
        box.innerHTML = '<div class="dlv-sales-empty">Gagal memuat item</div>';
      });
  }

  function openSelesaiRequest(btn) {
    var c = cfg();
    if (!c.selesaiRequestUrl) {
      toast('Endpoint selesai tidak tersedia', 'error');
      return;
    }
    ensureKaryawanSelectize();
    resetSelesaiForm();
    var idReq = btn.getAttribute('data-dlv-selesai-request') || '';
    var phone = btn.getAttribute('data-phone-tail') || '';
    var phoneShow = btn.getAttribute('data-phone-display') || phone;
    var jenis = (btn.getAttribute('data-jenis') || '').toLowerCase();
    var layanan = (btn.getAttribute('data-layanan') || 'sameday').toLowerCase();
    var prefill = btn.getAttribute('data-prefill') || '';
    var tarif = btn.getAttribute('data-tarif-surcas') || '';
    var nama = btn.getAttribute('data-nama') || 'Customer';
    var surcasBound = btn.getAttribute('data-surcas-bound') === '1';
    document.getElementById('dlvSelesaiMode').value = 'request';
    document.getElementById('dlvSelesaiSurcasBound').value = surcasBound ? '1' : '0';
    document.getElementById('dlvSelesaiRequestId').value = idReq;
    document.getElementById('dlvSelesaiPhone').value = phone;
    var idPel = parseInt(btn.getAttribute('data-id-pelanggan') || '0', 10) || 0;
    document.getElementById('dlvSelesaiPelanggan').value = idPel > 0 ? String(idPel) : '';
    document.getElementById('dlvSelesaiPrefill').value = prefill;
    document.getElementById('dlvSelesaiLayanan').value = layanan;
    setJenisLocked(jenis);
    var surcasJumlah = document.getElementById('dlvSurcasJemputJumlah');
    if (surcasJumlah) surcasJumlah.setAttribute('data-tarif-fixed', tarif || '0');
    var sub = document.getElementById('dlvSelesaiSub');
    if (sub) {
      var jenisLbl = jenis === 'antar' ? 'Antar' : (jenis === 'jemput' ? 'Jemput' : jenis);
      sub.textContent = nama + ' · ' + phoneShow + ' · ' + jenisLbl + ' · Request #' + idReq;
    }
    var batalBtn = document.getElementById('dlvSelesaiBatal');
    if (batalBtn) batalBtn.hidden = layanan === 'instant' || surcasBound;
    openModal('dlvSelesaiModal');
    syncSurcasJemputUi();
    syncSurcasAntarUi();
    syncSelesaiKaryawanLabel();
    loadSalesOptions();
  }

  function removeRequestRow(idRequest) {
    var opRoot = document.getElementById('op-dlv-root');
    if (!opRoot) return;
    var item = opRoot.querySelector('.dlv-item--request[data-id-request="' + idRequest + '"]');
    if (item) {
      var wrap = item.closest('.op-dlv-grid__item');
      if (wrap) wrap.remove();
      else item.remove();
    }
    if (!opRoot.querySelector('.dlv-item--request')) {
      opRoot.remove();
    }
  }

  function submitSelesai(e) {
    e.preventDefault();
    var c = cfg();
    var idRequest = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    var jenis = getSelesaiJenis();
    ensureKaryawanSelectize();
    var idKaryawan = karyawanSelectize ? karyawanSelectize.getValue() : ((document.getElementById('dlvSelesaiKaryawan') || {}).value || '');
    var checks = document.querySelectorAll('#dlvSelesaiSales input[name="ids[]"]:checked');
    if (!idRequest) { toast('Request tidak valid', 'error'); return; }
    if (!jenis) { toast('Jenis tidak valid', 'warn'); return; }
    if (!idKaryawan) {
      toast(jenis === 'antar' ? 'Pilih petugas antar' : (jenis === 'jemput' ? 'Pilih petugas jemput' : 'Pilih petugas'), 'warn');
      return;
    }
    if (!checks.length) { toast('Pilih minimal satu item', 'warn'); return; }
    if (jenis === 'jemput') {
      var jemputInput = document.getElementById('dlvSurcasJemputJumlah');
      if (!(jemputInput && jemputInput.dataset.surcasLocked === '1')) {
        var surcasRaw = String((jemputInput || {}).value || '').trim();
        var jumlahSc = parseInt(surcasRaw, 10);
        if (surcasRaw === '' || isNaN(jumlahSc) || jumlahSc < 0) {
          toast('Isi Surcas Penjemputan (isi 0 untuk gratis)', 'warn');
          return;
        }
      }
    }
    if (jenis === 'antar') {
      var antarInput = document.getElementById('dlvSurcasAntarJumlah');
      if (!(antarInput && antarInput.dataset.surcasLocked === '1')) {
        var antarRaw = String((antarInput || {}).value || '').trim();
        var jumlahAntar = parseInt(antarRaw, 10);
        if (antarRaw === '' || isNaN(jumlahAntar) || jumlahAntar < 0) {
          toast('Isi Surcas Pengantaran (isi 0 untuk gratis)', 'warn');
          return;
        }
      }
    }
    var fd = new FormData();
    fd.append('id_request', idRequest);
    fd.append('id_karyawan', idKaryawan);
    Array.prototype.forEach.call(checks, function (cb) { fd.append('ids[]', cb.value); });
    if (jenis === 'jemput') {
      fd.append('jumlah_surcas_jemput', String(parseInt((document.getElementById('dlvSurcasJemputJumlah') || {}).value || '0', 10) || 0));
    }
    if (jenis === 'antar') {
      fd.append('jumlah_surcas_antar', String(parseInt((document.getElementById('dlvSurcasAntarJumlah') || {}).value || '0', 10) || 0));
    }
    var submitBtn = document.getElementById('dlvSelesaiSubmit');
    if (submitBtn) submitBtn.disabled = true;
    fetch(c.selesaiRequestUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal menyelesaikan', 'error');
          return;
        }
        toast(res.message || 'Delivery selesai', 'success');
        closeModal('dlvSelesaiModal');
        removeRequestRow(idRequest);
        reloadOperasi();
      })
      .catch(function () { toast('Gagal menyelesaikan', 'error'); })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  }

  function openPendingRequest(btn) {
    var idReq = btn.getAttribute('data-dlv-pending-request') || '';
    var nama = btn.getAttribute('data-nama') || 'Customer';
    if (!idReq) { toast('Request tidak valid', 'error'); return; }
    document.getElementById('dlvPendingRequestId').value = idReq;
    var sub = document.getElementById('dlvPendingSub');
    if (sub) sub.textContent = nama + ' · Request #' + idReq;
    openModal('dlvPendingModal');
  }

  function confirmPendingRequest() {
    var c = cfg();
    var idReq = (document.getElementById('dlvPendingRequestId') || {}).value || '';
    if (!idReq || !c.pendingRequestUrl) { toast('Request tidak valid', 'error'); return; }
    var yesBtn = document.getElementById('dlvPendingYes');
    if (yesBtn) yesBtn.disabled = true;
    var fd = new FormData();
    fd.append('id_request', idReq);
    fetch(c.pendingRequestUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') throw new Error((res && res.message) || 'Gagal pending');
        closeModal('dlvPendingModal');
        toast(res.message || 'Request di-pending', 'ok');
        removeRequestRow(idReq);
        reloadOperasi();
      })
      .catch(function (err) { toast(err && err.message ? err.message : 'Gagal pending', 'error'); })
      .finally(function () { if (yesBtn) yesBtn.disabled = false; });
  }

  function batalDelivery() {
    ensureKaryawanSelectize();
    if (batalKaryawanSelectize) batalKaryawanSelectize.clear(true);
    document.getElementById('dlvBatalCatatan').value = '';
    document.getElementById('dlvConfirmMsg').textContent =
      'Request customer akan dibatalkan tanpa menyimpan riwayat jemput/antar.';
    openModal('dlvConfirmModal');
  }

  function confirmBatalDelivery() {
    var c = cfg();
    var idRequest = (document.getElementById('dlvSelesaiRequestId') || {}).value || '';
    ensureKaryawanSelectize();
    var idKaryawan = batalKaryawanSelectize ? batalKaryawanSelectize.getValue() : ((document.getElementById('dlvBatalKaryawan') || {}).value || '');
    var catatan = String((document.getElementById('dlvBatalCatatan') || {}).value || '').trim();
    if (!idRequest) { toast('Request tidak valid', 'error'); return; }
    if (!idKaryawan) { toast('Pilih karyawan yang membatalkan', 'warn'); return; }
    if (!catatan) { toast('Catatan wajib diisi', 'warn'); return; }
    var fd = new FormData();
    fd.append('id_request', idRequest);
    fd.append('id_karyawan', idKaryawan);
    fd.append('catatan', catatan);
    fetch(c.batalRequestUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal membatalkan', 'error');
          return;
        }
        toast(res.message || 'Delivery dibatalkan', 'success');
        closeModal('dlvConfirmModal');
        closeModal('dlvSelesaiModal');
        removeRequestRow(idRequest);
        reloadOperasi();
      })
      .catch(function () { toast('Gagal membatalkan', 'error'); });
  }

  function postTarikLokasi(idRequest, idLokasi, btn) {
    var c = cfg();
    if (!c.tarikLokasiUrl) { toast('Endpoint tarik lokasi tidak tersedia', 'error'); return; }
    if (btn) btn.disabled = true;
    var fd = new FormData();
    fd.append('id_request', String(idRequest));
    if (idLokasi > 0) fd.append('id_lokasi', String(idLokasi));
    fetch(c.tarikLokasiUrl, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') {
          toast((res && res.message) || 'Gagal tarik lokasi', 'error');
          return;
        }
        if (res.need_choose) {
          var list = document.getElementById('dlvLokasiPickList');
          var items = (res.data && res.data.items) || [];
          if (list) {
            list.innerHTML = items.map(function (it) {
              return '<li><button type="button" data-pick-lokasi="' + escapeHtml(String(it.id_lokasi || '')) + '">' +
                '<strong>' + escapeHtml(it.nama || 'Lokasi') + '</strong>' +
                (it.detail ? '<small>' + escapeHtml(it.detail) + '</small>' : '') + '</button></li>';
            }).join('');
          }
          document.getElementById('dlvLokasiPickRequestId').value = String(idRequest);
          document.getElementById('dlvLokasiPickSub').textContent = 'Request #' + idRequest;
          openModal('dlvLokasiPickModal');
          return;
        }
        toast(res.message || 'Lokasi diupdate', 'success');
        reloadOperasi();
      })
      .catch(function () { toast('Gagal tarik lokasi', 'error'); })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  function bindHooks() {
    if (hooksBound) return;
    hooksBound = true;

    document.addEventListener('click', function (e) {
      var opRoot = document.getElementById('op-dlv-root');
      if (!opRoot) return;

      var selesaiBtn = e.target.closest('[data-dlv-selesai-request]');
      if (selesaiBtn && opRoot.contains(selesaiBtn)) {
        e.preventDefault();
        openSelesaiRequest(selesaiBtn);
        return;
      }
      var pendingBtn = e.target.closest('[data-dlv-pending-request]');
      if (pendingBtn && opRoot.contains(pendingBtn)) {
        e.preventDefault();
        openPendingRequest(pendingBtn);
        return;
      }
      var tarikBtn = e.target.closest('[data-dlv-tarik-lokasi]');
      if (tarikBtn && opRoot.contains(tarikBtn)) {
        e.preventDefault();
        postTarikLokasi(parseInt(tarikBtn.getAttribute('data-dlv-tarik-lokasi') || '0', 10) || 0, 0, tarikBtn);
        return;
      }
      var pickBtn = e.target.closest('[data-pick-lokasi]');
      if (pickBtn && document.getElementById('dlvLokasiPickModal') && document.getElementById('dlvLokasiPickModal').classList.contains('is-open')) {
        e.preventDefault();
        var idReqPick = parseInt((document.getElementById('dlvLokasiPickRequestId') || {}).value || '0', 10) || 0;
        var idLok = parseInt(pickBtn.getAttribute('data-pick-lokasi') || '0', 10) || 0;
        if (idReqPick > 0 && idLok > 0) postTarikLokasi(idReqPick, idLok, pickBtn);
        return;
      }
      var tarifBtn = e.target.closest('.dlv-surcas-tarif-btn');
      if (tarifBtn && document.getElementById('dlvSelesaiModal') && document.getElementById('dlvSelesaiModal').classList.contains('is-open')) {
        e.preventDefault();
        var target = tarifBtn.getAttribute('data-surcas-target') || '';
        var input = target ? document.getElementById(target) : null;
        var c = cfg();
        if (!input || !c.tarifSurcasUrl || tarifSurcasLoading) return;
        var idPel = parseInt((document.getElementById('dlvSelesaiPelanggan') || {}).value || '0', 10) || 0;
        var idReq = parseInt((document.getElementById('dlvSelesaiRequestId') || {}).value || '0', 10) || 0;
        var phone = String((document.getElementById('dlvSelesaiPhone') || {}).value || '').trim();
        if (idPel <= 0 && !phone) { toast('Pelanggan tidak valid', 'warn'); return; }
        var q = [];
        if (idPel > 0) q.push('id_pelanggan=' + encodeURIComponent(String(idPel)));
        if (idReq > 0) q.push('id_request=' + encodeURIComponent(String(idReq)));
        if (phone) q.push('phone_tail=' + encodeURIComponent(phone));
        tarifSurcasLoading = true;
        tarifBtn.disabled = true;
        fetch(c.tarifSurcasUrl + '?' + q.join('&'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res || res.status !== 'success') {
              toast((res && res.message) || 'Lokasi pelanggan belum ada', 'warn');
              return;
            }
            input.value = String(parseInt((res.data && res.data.tarif) || 0, 10) || 0);
            input.dataset.userEdited = '1';
            syncSurcasJemputUi();
            syncSurcasAntarUi();
            toast('Surcas diisi Rp' + Number(input.value).toLocaleString('id-ID'), 'ok');
          })
          .catch(function () { toast('Gagal hitung tarif', 'error'); })
          .finally(function () {
            tarifSurcasLoading = false;
            tarifBtn.disabled = false;
          });
        return;
      }

      // Delegasi tombol modal — konten #load di-refresh via AJAX, elemen baru
      // tidak mewarisi addEventListener langsung, jadi binding harus di document.
      var batalBtn = e.target.closest('#dlvSelesaiBatal');
      if (batalBtn) {
        e.preventDefault();
        batalDelivery();
        return;
      }
      var confirmYesBtn = e.target.closest('#dlvConfirmYes');
      if (confirmYesBtn) {
        e.preventDefault();
        confirmBatalDelivery();
        return;
      }
      var pendingYesBtn = e.target.closest('#dlvPendingYes');
      if (pendingYesBtn) {
        e.preventDefault();
        confirmPendingRequest();
        return;
      }
    });

    document.addEventListener('submit', function (e) {
      var selesaiForm = e.target.closest('#dlvSelesaiForm');
      if (selesaiForm && document.getElementById('dlvSelesaiModal') && document.getElementById('dlvSelesaiModal').classList.contains('is-open')) {
        submitSelesai(e);
      }
    });
  }

  window.OpDlvSelesai = {
    _inited: true,
    bindHooks: bindHooks,
    ensureKaryawanSelectize: ensureKaryawanSelectize
  };

  bindHooks();
  if (window.jQuery) {
    jQuery(function () { ensureKaryawanSelectize(); });
  }
})();
