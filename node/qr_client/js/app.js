const WS_URL = 'wss://qrs.nalju.com';
let ws = null;
let currentQrString = null;

// Cookie functions
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
    hideConnectionOverlay();

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

function showConnectionOverlay(message) {
    const overlay = document.getElementById('connection-overlay');
    const textEl = document.getElementById('connection-text');
    textEl.textContent = message || 'Menghubungkan ke server…';
    overlay.classList.add('show');
}

function hideConnectionOverlay() {
    document.getElementById('connection-overlay').classList.remove('show');
}

function connectWebSocket(kasirId) {
    if (!navigator.onLine) {
        showConnectionOverlay('Tidak ada koneksi internet');
        setTimeout(() => connectWebSocket(kasirId), 3000);
        return;
    }

    showConnectionOverlay('Menghubungkan ke server…');

    ws = new WebSocket(`${WS_URL}?kasir_id=${kasirId}`);

    ws.onopen = function () {
        document.getElementById('status-box').className = 'status-box connected';
        hideConnectionOverlay();

        if (ws.pingInterval) clearInterval(ws.pingInterval);
        ws.pingInterval = setInterval(() => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    };

    ws.onclose = function (e) {
        document.getElementById('status-box').className = 'status-box disconnected';

        if (ws.pingInterval) clearInterval(ws.pingInterval);

        if (e.code >= 4001 && e.code <= 4003) {
            clearCredentials();
            showLoginForm(e.reason || 'Koneksi ditolak');
            return;
        }

        showConnectionOverlay('Menghubungkan ke server…');
        setTimeout(() => connectWebSocket(kasirId), 3000);
    };

    ws.onerror = function () {
        document.getElementById('status-box').className = 'status-box disconnected';
        showConnectionOverlay('Menghubungkan ke server…');
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

let qrCodeInstance = null;
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
        qrCodeInstance = new QRCode(qrContainer, {
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
        if (document.getElementById('qr-display').style.display !== 'none') {
            showConnectionOverlay('Tidak ada koneksi internet');
        }
    });

    window.addEventListener('online', function () {
        const kasirId = getCookie('kasir_id');
        if (kasirId && (!ws || ws.readyState !== WebSocket.OPEN)) {
            connectWebSocket(kasirId);
        }
    });
});
