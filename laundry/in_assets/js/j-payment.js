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
    var saldo = parseInt(cfg.saldoTunai, 10) || 0;
    if (saldo > 0) {
      var optSaldo = document.createElement('option');
      optSaldo.value = 'SALDO';
      optSaldo.textContent = 'Saldo Deposit (' + fmt(saldo) + ')';
      sel.appendChild(optSaldo);
    }
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
    var total = 0;
    $all('.j-check-tagihan:checked').forEach(function (cb) {
      rekap[cb.getAttribute('data-ref')] = cb.value;
      total += parseInt(cb.value, 10) || 0;
      count++;
    });
    if (!count) {
      $('#jBayarStatus').textContent = 'Pilih minimal satu tagihan.';
      return;
    }
    var metode = $('#jMetodeBayar').value;
    var saldo = parseInt(cfg.saldoTunai, 10) || 0;
    if (metode === 'SALDO' && saldo <= 0) {
      $('#jBayarStatus').textContent = 'Saldo Deposit kosong.';
      return;
    }

    var btn = $('#jBtnSubmitBayar');
    var status = $('#jBayarStatus');
    if (metode === 'SALDO' && total > saldo) {
      status.textContent =
        'Saldo ' + fmt(saldo) + ' kurang dari total. Akan dipotong sesuai sisa saldo...';
    } else {
      status.textContent = 'Memproses pembayaran...';
    }
    btn.disabled = true;

    var body = new FormData();
    body.append('id_pelanggan', cfg.id_pelanggan);
    body.append('metode', metode);
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
          toast(
            metode === 'SALDO'
              ? 'Berhasil dibayar dengan Saldo Deposit'
              : 'Pembayaran dibuat. Lanjutkan Scan QR / transfer.',
            'ok'
          );
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
    $('#jQrNama').textContent = String(nama || '').toLocaleUpperCase('id-ID');
    $('#jBtnCekStatusQR').setAttribute('data-ref', refId);
    modalShow('jModalQR');
    stopPoll();
    var pollTick = 0;
    pollTimer = setInterval(function () {
      pollTick += 1;
      pollStatus(refId, true);
    }, 3000);
  }

  function pollStatus(refId, silent) {
    var cfg = getConfig();
    if (!cfg) return;
    var url =
      cfg.base +
      'I/payment_gateway_status_poll/' +
      encodeURIComponent(refId);
    fetch(url, {
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
          toast(res.msg || 'Menunggu Mutasi QRIS', 'warn');
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
        if (res && res.status === 'paid') {
          toast(res.msg || 'Pembayaran sudah berhasil', 'ok');
          reloadTagihan();
        } else if (res && res.status === 'success') {
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

  function openSaldoTopupModal() {
    var cfg = getConfig();
    if (!cfg) return;
    if (cfg.topupBlocked) {
      toast('Tidak bisa topup saat ini', 'warn');
      return;
    }
    var sel = $('#jSaldoTopupMetode');
    if (sel) {
      sel.innerHTML = '';
      (cfg.nonTunai || ['QRIS']).forEach(function (m) {
        var opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        sel.appendChild(opt);
      });
    }
    var room = parseInt(cfg.topupRoom, 10) || 0;
    var input = $('#jSaldoTopupJumlah');
    if (input) {
      input.value = '';
      input.max = String(room);
      input.min = '1000';
    }
    var hint = $('#jSaldoTopupHint');
    if (hint) {
      hint.textContent =
        'Maks. topup sekarang Rp' + Number(room).toLocaleString('id-ID') +
        ' (batas saldo Rp' + Number(cfg.maxSaldo || 5000000).toLocaleString('id-ID') + ')';
    }
    var st = $('#jSaldoTopupStatus');
    if (st) st.textContent = '';
    modalShow('jModalSaldoTopup');
  }

  function submitSaldoTopup() {
    var cfg = getConfig();
    if (!cfg) return;
    var input = $('#jSaldoTopupJumlah');
    var metodeEl = $('#jSaldoTopupMetode');
    var status = $('#jSaldoTopupStatus');
    var btn = $('#jBtnSubmitSaldoTopup');
    var jumlah = parseInt(input && input.value, 10) || 0;
    var metode = (metodeEl && metodeEl.value) || '';
    var room = parseInt(cfg.topupRoom, 10) || 0;

    if (!metode) {
      if (status) status.textContent = 'Pilih metode pembayaran.';
      return;
    }
    if (jumlah <= 0) {
      if (status) status.textContent = 'Isi nominal topup.';
      return;
    }
    if (String(metode).toUpperCase() === 'QRIS' && jumlah < 1000) {
      if (status) status.textContent = 'Minimal QRIS Rp1.000.';
      return;
    }
    if (String(metode).toUpperCase() !== 'QRIS' && jumlah < 10000) {
      if (status) status.textContent = 'Minimal transfer Rp10.000.';
      return;
    }
    if (jumlah > room) {
      if (status) status.textContent = 'Melebihi kapasitas saldo (sisa Rp' + Number(room).toLocaleString('id-ID') + ').';
      return;
    }

    if (status) status.textContent = 'Memproses...';
    if (btn) btn.disabled = true;

    var body = new URLSearchParams();
    body.set('jumlah', String(jumlah));
    body.set('metode', metode);

    fetch(cfg.base + 'J/saldoTopup/' + cfg.id_pelanggan, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      credentials: 'same-origin',
      body: body.toString(),
    })
      .then(function (r) {
        return r.json().catch(function () {
          throw new Error('Respons tidak valid');
        });
      })
      .then(function (data) {
        if (btn) btn.disabled = false;
        if (!data || !data.ok) {
          if (status) status.textContent = (data && data.message) || 'Gagal topup';
          return;
        }
        modalHide('jModalSaldoTopup');
        toast(data.message || 'Topup dibuat', 'ok');
        reloadTagihan();
      })
      .catch(function (err) {
        if (btn) btn.disabled = false;
        if (status) status.textContent = (err && err.message) || 'Gagal jaringan';
      });
  }

  document.addEventListener('click', function (e) {
    var bayar = e.target.closest('.j-open-bayar');
    if (bayar) {
      e.preventDefault();
      openBayarModal();
      return;
    }

    var saldoTopup = e.target.closest('.j-open-saldo-topup');
    if (saldoTopup) {
      e.preventDefault();
      openSaldoTopupModal();
      return;
    }

    var submitSaldo = e.target.closest('#jBtnSubmitSaldoTopup');
    if (submitSaldo) {
      e.preventDefault();
      submitSaldoTopup();
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
      pollStatus(cek.getAttribute('data-ref'), false, true);
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
