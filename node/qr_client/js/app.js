const WS_URL = 'wss://qrs.nalju.com';
let ws = null;
let currentQrString = null;
let reconnectTimer = null;

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
    document.getElementById('kasir-id-label').textContent = 'ID Cabang: ' + kasirId;
}

function showLoginForm(errorMessage) {
    document.getElementById('form-kasir').style.display = 'block';
    document.getElementById('qr-display').style.display = 'none';
    hideStatusLabel();

    const input = document.getElementById('kasir-id-input');
    input.value = '';

    if (errorMessage) {
        const h2 = document.querySelector('.form-kasir h2');
        const originalText = h2.textContent;
        h2.textContent = errorMessage;
        h2.style.color = '#e74c3c';
        setTimeout(() => {
            h2.textContent = originalText;
            h2.style.color = '';
        }, 3000);
    }
}

function clearCredentials() {
    document.cookie = 'kasir_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'kasir_id_expiry=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

function showStatusLabel(message) {
    const label = document.getElementById('status-label');
    label.textContent = message || 'Menghubungkan…';
    label.classList.add('show');
}

function hideStatusLabel() {
    const label = document.getElementById('status-label');
    label.textContent = '';
    label.classList.remove('show');
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
            ws.close();
        }
        ws = null;
    }
}

function logout() {
    closeWebSocket();
    clearCredentials();
    hideQR();
    document.getElementById('payment-success-overlay').classList.remove('show');
    showLoginForm();
}

function connectWebSocket(kasirId) {
    clearReconnectTimer();
    closeWebSocket();

    if (!navigator.onLine) {
        setConnected(false);
        showStatusLabel('Tidak ada internet');
        reconnectTimer = setTimeout(() => connectWebSocket(kasirId), 3000);
        return;
    }

    setConnected(false);
    showStatusLabel('Menghubungkan…');

    ws = new WebSocket(`${WS_URL}?kasir_id=${kasirId}`);

    ws.onopen = function () {
        setConnected(true);

        ws.pingInterval = setInterval(() => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    };

    ws.onclose = function (e) {
        if (ws && ws.pingInterval) clearInterval(ws.pingInterval);

        if (e.code >= 4001 && e.code <= 4003) {
            clearCredentials();
            showLoginForm(e.reason || 'Koneksi ditolak');
            return;
        }

        if (!getCookie('kasir_id')) return;

        setConnected(false);
        showStatusLabel('Menghubungkan…');
        reconnectTimer = setTimeout(() => connectWebSocket(kasirId), 3000);
    };

    ws.onerror = function () {
        setConnected(false);
        showStatusLabel('Menghubungkan…');
    };

    ws.onmessage = function (event) {
        const data = JSON.parse(event.data);

        if (data.type === 'qr_code') {
            displayQR(data.qr_string, data.text);
        }

        if (data.type === 'payment_success') {
            if (currentQrString && data.qr_string === currentQrString) {
                showPaymentSuccess(data.status);
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
        qrTextEl.innerHTML = text.replace(/<br\s*\/?>/gi, '<br>');
    } else {
        qrTextEl.innerHTML = '';
    }

    if (qrHideTimeout) clearTimeout(qrHideTimeout);
    qrHideTimeout = setTimeout(() => hideQR(), 180000);
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

        setTimeout(() => overlay.classList.remove('show'), 60000);
        currentQrString = null;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const credentials = checkAndRefreshCookie();

    if (credentials) {
        showQrDisplay(credentials.kasirId);
        connectWebSocket(credentials.kasirId);
    }

    document.getElementById('kasir-id-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') saveKasirId();
    });

    window.addEventListener('offline', function () {
        if (document.getElementById('qr-display').style.display === 'none') return;
        setConnected(false);
        showStatusLabel('Tidak ada internet');
    });

    window.addEventListener('online', function () {
        const kasirId = getCookie('kasir_id');
        if (kasirId && document.getElementById('qr-display').style.display !== 'none') {
            connectWebSocket(kasirId);
        }
    });
});
