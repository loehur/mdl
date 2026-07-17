(function (window, document) {
  'use strict';

  var pollTimer = null;
  var cancelRef = null;

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function getConfig() {
    var el = $('#jPayConfig');
    if (!el) return null;
    try {
      return JSON.parse(el.textContent);
    } catch (e) {
      return null;
    }
  }

  function fmt(n) {
    return 'Rp' + Number(n || 0).toLocaleString('id-ID');
  }

  function toast(msg, type) {
    var t = $('#jToast');
    if (!t) return alert(msg);
    t.textContent = msg;
    t.className = 'j-toast show ' + (type || '');
    clearTimeout(t._timer);
    t._timer = setTimeout(function () {
      t.className = 'j-toast';
    }, 2800);
  }

  function modalShow(id) {
    var el = document.getElementById(id);
    if (!el || !window.bootstrap) return;
    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  function modalHide(id) {
    var el = document.getElementById(id);
    if (!el || !window.bootstrap) return;
    var inst = bootstrap.Modal.getInstance(el);
    if (inst) inst.hide();
  }

  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function reloadTagihan() {
    if (window.JApp && typeof window.JApp.reload === 'function') {
      window.JApp.reload();
    } else {
      location.reload();
    }
  }

  function fillMetode(cfg) {
    var sel = $('#jMetodeBayar');
    if (!sel) return;
    sel.innerHTML = '';
    (cfg.nonTunai || ['QRIS']).forEach(function (m) {
      var opt = document.createElement('option');
      opt.value = m;
      opt.textContent = m;
      sel.appendChild(opt);
    });
  }

  function renderUnpaidList(cfg) {
    var list = $('#jListTagihanBayar');
    if (!list) return;
    list.innerHTML = '';
    (cfg.unpaid || []).forEach(function (item) {
      var row = document.createElement('label');
      row.className = 'j-pay-item';
      row.innerHTML =
        '<input type="checkbox" class="j-check-tagihan" checked data-ref="' +
        item.ref +
        '" value="' +
        item.amount +
        '">' +
        '<span class="grow"><b>' +
        item.label +
        '</b></span>' +
        '<span>' +
        fmt(item.amount) +
        '</span>';
      list.appendChild(row);
    });
    calcTotal();
  }

  function calcTotal() {
    var total = 0;
    $all('.j-check-tagihan:checked').forEach(function (cb) {
      total += parseInt(cb.value, 10) || 0;
    });
    var el = $('#jTotalBayarModal');
    if (el) el.textContent = fmt(total);
  }

  function openBayarModal() {
    var cfg = getConfig();
    if (!cfg || !(cfg.unpaid || []).length) {
      toast('Tidak ada tagihan yang bisa dibayar', 'warn');
      return;
    }
    fillMetode(cfg);
    renderUnpaidList(cfg);
    var st = $('#jBayarStatus');
    if (st) st.textContent = '';
    modalShow('jModalBayar');
  }

  function submitBayar() {
    var cfg = getConfig();
    if (!cfg) return;
    var rekap = {};
    var count = 0;
    $all('.j-check-tagihan:checked').forEach(function (cb) {
      rekap[cb.getAttribute('data-ref')] = cb.value;
      count++;
    });
    if (!count) {
      $('#jBayarStatus').textContent = 'Pilih minimal satu tagihan.';
      return;
    }
    var btn = $('#jBtnSubmitBayar');
    var status = $('#jBayarStatus');
    status.textContent = 'Memproses pembayaran...';
    btn.disabled = true;

    var body = new FormData();
    body.append('id_pelanggan', cfg.id_pelanggan);
    body.append('metode', $('#jMetodeBayar').value);
    Object.keys(rekap).forEach(function (k) {
      body.append('rekap[' + k + ']', rekap[k]);
    });

    fetch(cfg.base + 'I/bayar', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.text();
      })
      .then(function (res) {
        btn.disabled = false;
        if (String(res).trim() === '0') {
          modalHide('jModalBayar');
          toast('Pembayaran dibuat. Lanjutkan Scan QR / transfer.', 'ok');
          reloadTagihan();
        } else {
          status.textContent = 'Error: ' + res;
        }
      })
      .catch(function () {
        btn.disabled = false;
        status.textContent = 'Terjadi kesalahan jaringan.';
      });
  }

  function showQR(qrString, total, nama, refId) {
    var box = $('#jQrcode');
    box.innerHTML = '';
    if (window.QRCode) {
      new QRCode(box, { text: qrString, width: 180, height: 180 });
    } else {
      box.textContent = 'QR library gagal dimuat';
    }
    $('#jQrTotal').textContent = fmt(total);
    $('#jQrNama').textContent = nama || '';
    $('#jBtnCekStatusQR').setAttribute('data-ref', refId);
    modalShow('jModalQR');
    stopPoll();
    pollTimer = setInterval(function () {
      pollStatus(refId, true);
    }, 3000);
  }

  function pollStatus(refId, silent) {
    var cfg = getConfig();
    if (!cfg) return;
    fetch(cfg.base + 'I/payment_gateway_status_poll/' + encodeURIComponent(refId), {
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res && String(res.status).toUpperCase() === 'PAID') {
          stopPoll();
          modalHide('jModalQR');
          modalHide('jModalStatus');
          toast('Pembayaran berhasil', 'ok');
          reloadTagihan();
        } else if (!silent) {
          toast(res.msg || 'Masih menunggu pembayaran', 'warn');
        }
      })
      .catch(function () {
        if (!silent) toast('Gagal cek status', 'warn');
      });
  }

  function handleTokopay(btn) {
    var cfg = getConfig();
    if (!cfg) return;
    var ref = btn.getAttribute('data-ref');
    var total = btn.getAttribute('data-total');
    var note = btn.getAttribute('data-note') || '';
    var isQRIS = note.toUpperCase() === 'QRIS';
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = isQRIS ? 'Memuat QR...' : 'Checking...';

    if (isQRIS) {
      fetch(
        cfg.base +
          'I/payment_gateway_order/' +
          encodeURIComponent(ref) +
          '?nominal=' +
          encodeURIComponent(total) +
          '&metode=' +
          encodeURIComponent(note),
        { credentials: 'same-origin' }
      )
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          btn.disabled = false;
          btn.innerHTML = original;
          if (res && res.status === 'paid') {
            toast('Sudah terbayar', 'ok');
            reloadTagihan();
            return;
          }
          if (res && res.qr_string) {
            showQR(res.qr_string, total, cfg.nama, ref);
            return;
          }
          toast((res && res.msg) || 'QRIS sementara tidak tersedia', 'warn');
        })
        .catch(function () {
          btn.disabled = false;
          btn.innerHTML = original;
          toast('Gagal memuat QRIS', 'warn');
        });
    } else {
      fetch(cfg.base + 'I/payment_gateway_check_status/' + encodeURIComponent(ref), {
        credentials: 'same-origin',
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (res) {
          btn.disabled = false;
          btn.innerHTML = original;
          if (res && String(res.status).toUpperCase() === 'PAID') {
            toast('Pembayaran berhasil', 'ok');
            reloadTagihan();
            return;
          }
          var guide = (cfg.nonTunaiGuide && cfg.nonTunaiGuide[note]) || null;
          var html = '<div class="j-status-card">';
          html += '<div class="j-badge warn" style="margin-bottom:10px">' + (res.status || 'PENDING') + '</div>';
          if (guide) {
            html +=
              '<p class="j-status-bank">' +
              guide.label +
              '</p>' +
              '<p class="j-status-rek">' +
              guide.number +
              '</p>' +
              '<p class="j-sheet-desc">a.n. <b>' +
              guide.name +
              '</b></p>' +
              '<div class="j-pay-total" style="margin-top:14px"><span>Transfer</span><strong>' +
              fmt(total) +
              '</strong></div>';
          } else {
            html += '<p class="j-sheet-desc">Silakan transfer sesuai metode ' + note + '.</p>';
          }
          html += '</div>';
          $('#jStatusModalBody').innerHTML = html;
          modalShow('jModalStatus');
        })
        .catch(function () {
          btn.disabled = false;
          btn.innerHTML = original;
          toast('Gagal cek status', 'warn');
        });
    }
  }

  function sendNota(btn) {
    var cfg = getConfig();
    if (!cfg) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    var body = new FormData();
    body.append('id_pelanggan', btn.getAttribute('data-id-pelanggan'));
    body.append('hp', btn.getAttribute('data-hp') || cfg.hp || '');
    body.append('ref', btn.getAttribute('data-ref'));
    body.append('time', btn.getAttribute('data-time'));

    fetch(cfg.base + 'I/send_nota/1', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.text();
      })
      .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = original;
        var trimmed = String(res).trim();
        if (trimmed === '0') {
          toast('Nota dikirim via WhatsApp', 'ok');
          reloadTagihan();
          return;
        }
        try {
          var json = JSON.parse(trimmed);
          if (json.status === 'exists') {
            toast(json.message || 'Nota sudah pernah dikirim', 'warn');
            reloadTagihan();
            return;
          }
        } catch (e) {}
        toast('Gagal kirim nota', 'warn');
      })
      .catch(function () {
        btn.disabled = false;
        btn.innerHTML = original;
        toast('Gagal kirim nota', 'warn');
      });
  }

  function confirmCancel() {
    var cfg = getConfig();
    if (!cfg || !cancelRef) return;
    var btn = $('#jBtnConfirmCancel');
    btn.disabled = true;
    fetch(cfg.base + 'I/cancel_payment/' + encodeURIComponent(cancelRef), {
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        btn.disabled = false;
        modalHide('jModalCancel');
        if (res && res.status === 'success') {
          toast('Pembayaran dibatalkan', 'ok');
          reloadTagihan();
        } else {
          toast((res && res.msg) || 'Gagal membatalkan', 'warn');
        }
      })
      .catch(function () {
        btn.disabled = false;
        toast('Gagal membatalkan', 'warn');
      });
  }

  document.addEventListener('click', function (e) {
    var bayar = e.target.closest('.j-open-bayar');
    if (bayar) {
      e.preventDefault();
      openBayarModal();
      return;
    }

    var submit = e.target.closest('#jBtnSubmitBayar');
    if (submit) {
      e.preventDefault();
      submitBayar();
      return;
    }

    var tokopay = e.target.closest('.j-tokopay');
    if (tokopay) {
      e.preventDefault();
      handleTokopay(tokopay);
      return;
    }

    var cancel = e.target.closest('.j-cancel-pay');
    if (cancel) {
      e.preventDefault();
      cancelRef = cancel.getAttribute('data-ref');
      $('#jCancelPaymentInfo').textContent =
        (cancel.getAttribute('data-note') || '') + ' · Rp' + cancel.getAttribute('data-total');
      modalShow('jModalCancel');
      return;
    }

    var confirm = e.target.closest('#jBtnConfirmCancel');
    if (confirm) {
      e.preventDefault();
      confirmCancel();
      return;
    }

    var cek = e.target.closest('#jBtnCekStatusQR');
    if (cek) {
      e.preventDefault();
      pollStatus(cek.getAttribute('data-ref'), false);
      return;
    }

    var nota = e.target.closest('.j-send-nota');
    if (nota) {
      e.preventDefault();
      sendNota(nota);
    }
  });

  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('j-check-tagihan')) {
      calcTotal();
    }
  });

  var qrModal = document.getElementById('jModalQR');
  if (qrModal) {
    qrModal.addEventListener('hidden.bs.modal', stopPoll);
  }
})(window, document);
