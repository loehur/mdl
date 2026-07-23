const WS_URL = 'wss://qrs.nalju.com';
let ws = null;
let currentQrString = null;
let reconnectTimer = null;
let currentKasirId = null;
let replaceRetryCount = 0;
const MAX_REPLACE_RETRIES = 3;

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    document.cookie = `${name}=${value}; expires=${expires}; path=/`;
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

function getCookieExpiry(name) {
    const expiry = getCookie(name + '_expiry');
    return expiry ? parseInt(expiry) : null;
}

function setCookieWithExpiry(name, value, days) {
    const expiryTime = Date.now() + days * 24 * 60 * 60 * 1000;
    setCookie(name, value, days);
    setCookie(name + '_expiry', expiryTime, days);
}

function checkAndRefreshCookie() {
    const kasirId = getCookie('kasir_id');
    const expiry = getCookieExpiry('kasir_id');

    if (kasirId && expiry) {
        const oneDayMs = 24 * 60 * 60 * 1000;
        const timeLeft = expiry - Date.now();

        if (timeLeft < oneDayMs) {
            setCookieWithExpiry('kasir_id', kasirId, 2);
        }
        return { kasirId };
    }
    return null;
}

function saveKasirId() {
    const kasirInput = document.getElementById('kasir-id-input');
    const kasirId = kasirInput.value.trim();

    if (!kasirId) {
        kasirInput.focus();
        return;
    }

    setCookieWithExpiry('kasir_id', kasirId, 2);
    showQrDisplay(kasirId);
    connectWebSocket(kasirId);
}

function showQrDisplay(kasirId) {
    document.getElementById('form-kasir').style.display = 'none';
    document.getElementById('qr-display').style.display = 'block';
    document.getElementById('kasir-id-label').textContent = '#' + kasirId;
}

function showLoginForm(errorMessage) {
    document.getElementById('form-kasir').style.display = 'block';
    document.getElementById('qr-display').style.display = 'none';
    hideStatusLabel();

    const input = document.getElementById('kasir-id-input');
    input.value = '';

    const err = document.getElementById('form-error');
    if (errorMessage) {
        err.textContent = String(errorMessage).toUpperCase();
        setTimeout(function () {
            err.textContent = '';
        }, 3000);
    } else {
        err.textContent = '';
    }
}

function clearCredentials() {
    document.cookie = 'kasir_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'kasir_id_expiry=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

function showStatusLabel(message) {
    const label = document.getElementById('status-label');
    label.textContent = (message || 'MENGHUBUNGKAN...').toUpperCase();
}

function hideStatusLabel() {
    document.getElementById('status-label').textContent = '';
}

function setConnected(connected) {
    const box = document.getElementById('status-box');
    box.className = connected ? 'status-box connected' : 'status-box disconnected';
    if (connected) {
        hideStatusLabel();
    }
}

function clearReconnectTimer() {
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }
}

function closeWebSocket() {
    clearReconnectTimer();
    if (ws) {
        if (ws.pingInterval) clearInterval(ws.pingInterval);
        ws.onclose = null;
        ws.onerror = null;
        ws.onmessage = null;
        ws.onopen = null;
        if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
            try {
                ws.close();
            } catch (e) {
                // ignore
            }
        }
        ws = null;
    }
    // Sinkron ke window.ws agar Android onResume bisa cek readyState
    window.ws = null;
}

function logout() {
    replaceRetryCount = 0;
    closeWebSocket();
    clearCredentials();
    hideQR();
    document.getElementById('payment-success-overlay').classList.remove('show');
    showLoginForm();
}

function reloadPage() {
    window.location.reload();
}

function connectWebSocket(kasirId, options) {
    options = options || {};
    const force = !!options.force;

    // Hindari double-connect yang mengisi slot sia-sia
    if (!force && ws && currentKasirId === kasirId &&
        (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
        return;
    }

    clearReconnectTimer();
    closeWebSocket();
    currentKasirId = kasirId;

    if (!navigator.onLine) {
        setConnected(false);
        showStatusLabel('TIDAK ADA INTERNET');
        reconnectTimer = setTimeout(function () {
            connectWebSocket(kasirId, { force: true });
        }, 3000);
        return;
    }

    setConnected(false);
    showStatusLabel('MENGHUBUNGKAN...');

    ws = new WebSocket(WS_URL + '?kasir_id=' + encodeURIComponent(kasirId));
    window.ws = ws;

    ws.onopen = function () {
        replaceRetryCount = 0;
        setConnected(true);

        ws.pingInterval = setInterval(function () {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    };

    ws.onclose = function (e) {
        if (ws && ws.pingInterval) clearInterval(ws.pingInterval);
        if (window.ws === ws) window.ws = null;

        // 4001-4003: auth rejected — kembali ke login
        if (e.code >= 4001 && e.code <= 4003) {
            replaceRetryCount = 0;
            clearCredentials();
            showLoginForm(e.reason || 'KONEKSI DITOLAK');
            return;
        }

        // 4005: slot penuh / digantikan — retry terbatas
        if (e.code === 4005) {
            setConnected(false);
            if (replaceRetryCount < MAX_REPLACE_RETRIES && getCookie('kasir_id')) {
                replaceRetryCount += 1;
                showStatusLabel('MENGHUBUNGKAN... (' + replaceRetryCount + '/' + MAX_REPLACE_RETRIES + ')');
                reconnectTimer = setTimeout(function () {
                    connectWebSocket(kasirId, { force: true });
                }, 500);
                return;
            }
            replaceRetryCount = 0;
            showStatusLabel('KONEKSI PENUH');
            return;
        }

        if (!getCookie('kasir_id')) return;

        setConnected(false);
        showStatusLabel('MENGHUBUNGKAN...');
        reconnectTimer = setTimeout(function () {
            connectWebSocket(kasirId, { force: true });
        }, 3000);
    };

    ws.onerror = function () {
        setConnected(false);
        showStatusLabel('MENGHUBUNGKAN...');
    };

    ws.onmessage = function (event) {
        const data = JSON.parse(event.data);

        if (data.type === 'qr_code') {
            displayQR(data.qr_string, data.text);
        }

        if (data.type === 'payment_success') {
            // Server sudah filter per kasir_id. Tampilkan sukses jika ada QR di layar.
            // Exact match preferred; fallback agar tidak gagal karena perbedaan whitespace/encoding.
            var incoming = (data.qr_string || '').trim();
            var current = (currentQrString || '').trim();
            var matched = !incoming || !current || incoming === current;
            if (data.status && current && matched) {
                showPaymentSuccess(true);
            }
        }
    };
}

let qrHideTimeout = null;

function displayQR(qrString, text) {
    const qrContainer = document.getElementById('qr-container');
    const overlay = document.getElementById('payment-success-overlay');

    overlay.classList.remove('show');

    if (!qrString || qrString.trim() === '') {
        return;
    }

    currentQrString = qrString;
    qrContainer.style.display = 'block';
    qrContainer.innerHTML = '';

    try {
        new QRCode(qrContainer, {
            text: qrString,
            width: 280,
            height: 280,
            colorDark: '#1a1a2e',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    } catch (error) {
        console.error('QRCode generation error:', error);
    }

    const qrTextEl = document.getElementById('qr-text');
    if (text) {
        const parts = String(text).split(/<br\s*\/?>/gi);
        qrTextEl.innerHTML = parts.map(function (part) {
            var clean = part.trim();
            if (!clean) return '';
            var isAmount = /^rp\s*[\d.,]+/i.test(clean);
            if (isAmount) {
                // Tampilkan "Rp" (bukan "RP") + angka
                clean = clean.replace(/^rp\s*/i, 'Rp');
                return '<span class="qr-amount">' + clean + '</span>';
            }
            return '<span class="qr-name">' + clean.toUpperCase() + '</span>';
        }).filter(Boolean).join('<br>');
    } else {
        qrTextEl.innerHTML = '';
    }

    if (qrHideTimeout) clearTimeout(qrHideTimeout);
    qrHideTimeout = setTimeout(function () {
        hideQR();
    }, 180000);
}

function hideQR() {
    const qrContainer = document.getElementById('qr-container');
    const qrTextEl = document.getElementById('qr-text');

    qrContainer.style.display = 'none';
    qrContainer.innerHTML = '';
    qrTextEl.innerHTML = '';
    currentQrString = null;
}

function showPaymentSuccess(status) {
    const overlay = document.getElementById('payment-success-overlay');
    const qrContainer = document.getElementById('qr-container');
    const qrText = document.getElementById('qr-text');

    if (status) {
        qrContainer.style.display = 'none';
        qrContainer.innerHTML = '';
        qrText.textContent = '';
        overlay.classList.add('show');

        setTimeout(function () {
            overlay.classList.remove('show');
        }, 60000);
        currentQrString = null;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const credentials = checkAndRefreshCookie();

    if (credentials) {
        showQrDisplay(credentials.kasirId);
        connectWebSocket(credentials.kasirId, { force: true });
    }

    document.getElementById('kasir-id-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') saveKasirId();
    });

    window.addEventListener('offline', function () {
        if (document.getElementById('qr-display').style.display === 'none') return;
        setConnected(false);
        showStatusLabel('TIDAK ADA INTERNET');
    });

    window.addEventListener('online', function () {
        const kasirId = getCookie('kasir_id');
        if (kasirId && document.getElementById('qr-display').style.display !== 'none') {
            connectWebSocket(kasirId);
        }
    });

    // Bridge untuk Android WebView (setelah semua fungsi tersedia)
    window.closeQrSocket = closeWebSocket;
    window.connectWebSocket = connectWebSocket;
});
