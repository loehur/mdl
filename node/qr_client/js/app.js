const WS_URL = 'wss://qrs.nalju.com';
let ws = null;
let currentQrString = null; // Track currently displayed QR string

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
    // We store expiry timestamp in a separate cookie
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
    const pin = getCookie('pin');
    const expiry = getCookieExpiry('kasir_id');

    if (kasirId && pin && expiry) {
        const oneDayMs = 24 * 60 * 60 * 1000;
        const timeLeft = expiry - Date.now();

        // If less than 1 day left, refresh to 2 days
        if (timeLeft < oneDayMs) {
            setCookieWithExpiry('kasir_id', kasirId, 2);
            setCookieWithExpiry('pin', pin, 2);
        }
        return { kasirId, pin };
    }
    return null;
}

// Save Kasir ID and PIN, then connect
function saveKasirId() {
    const kasirInput = document.getElementById('kasir-id-input');
    const pinInput = document.getElementById('pin-input');
    const kasirId = kasirInput.value.trim().toUpperCase();
    const pin = pinInput.value.trim();

    if (!kasirId) {
        kasirInput.focus();
        return;
    }

    if (!pin) {
        pinInput.focus();
        return;
    }

    setCookieWithExpiry('kasir_id', kasirId, 2);
    setCookieWithExpiry('pin', pin, 2);

    // Debug: verify cookies were saved
    console.log('Cookies after save:', document.cookie);
    console.log('kasir_id cookie:', getCookie('kasir_id'));
    console.log('pin cookie:', getCookie('pin'));

    showQrDisplay(kasirId);
    connectWebSocket(kasirId, pin);
}

// Show QR display
function showQrDisplay(kasirId) {
    document.getElementById('form-kasir').style.display = 'none';
    document.getElementById('qr-display').style.display = 'block';
    document.getElementById('kasir-id-label').textContent = 'ID: ' + kasirId;
}

// Show login form (after error)
function showLoginForm(errorMessage) {
    document.getElementById('form-kasir').style.display = 'block';
    document.getElementById('qr-display').style.display = 'none';
    // Show error message temporarily
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

// Clear stored credentials
function clearCredentials() {
    // Delete cookies by setting expiry to past
    document.cookie = 'kasir_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'kasir_id_expiry=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'pin=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'pin_expiry=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

// WebSocket connection
function connectWebSocket(kasirId, pin) {
    // Get PIN from cookie if not provided
    if (!pin) {
        pin = getCookie('pin');
    }

    console.log('Connecting to:', `${WS_URL}?kasir_id=${kasirId}&pin=***`);
    ws = new WebSocket(`${WS_URL}?kasir_id=${kasirId}&pin=${pin}`);

    ws.onopen = function () {
        console.log('WebSocket connected!');
        document.getElementById('status-box').className = 'status-box connected';

        // Keep-alive: Send ping every 30 seconds
        if (ws.pingInterval) clearInterval(ws.pingInterval);
        ws.pingInterval = setInterval(() => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    };

    ws.onclose = function (e) {
        console.log('WebSocket closed:', e.code, e.reason);
        document.getElementById('status-box').className = 'status-box disconnected';

        if (ws.pingInterval) clearInterval(ws.pingInterval);

        // Don't reconnect for authentication/authorization errors
        // 4001: kasir_id is required
        // 4002: Invalid PIN
        // 4003: kasir_id is not allowed
        if (e.code >= 4001 && e.code <= 4003) {
            console.log('Connection rejected permanently:', e.reason);
            // Clear cookies so user can re-enter credentials
            clearCredentials();
            // Show form again
            showLoginForm(e.reason || 'Koneksi ditolak');
            return;
        }

        // Reconnect after 3 seconds for other errors
        setTimeout(() => connectWebSocket(kasirId, pin), 3000);
    };

    ws.onerror = function (e) {
        console.error('WebSocket error:', e);
        document.getElementById('status-box').className = 'status-box disconnected';
    };

    ws.onmessage = function (event) {
        console.log('WebSocket message received:', event.data);
        const data = JSON.parse(event.data);
        console.log('Parsed data:', data);

        if (data.type === 'qr_code') {
            console.log('QR Code data received, qr_string:', data.qr_string);
            // Wake screen if running in Android WebView
            if (typeof Android !== 'undefined' && typeof Android.wakeScreen === 'function') {
                Android.wakeScreen();
            }
            displayQR(data.qr_string, data.text);
        }

        if (data.type === 'payment_success') {
            // Only show success if qr_string matches currently displayed QR
            if (currentQrString && data.qr_string === currentQrString) {
                // Wake screen if running in Android WebView
                if (typeof Android !== 'undefined' && typeof Android.wakeScreen === 'function') {
                    Android.wakeScreen();
                }
                showPaymentSuccess(data.status);
            }
        }
    };
}

// Display QR Code
let qrCodeInstance = null;
let qrHideTimeout = null;

function displayQR(qrString, text) {
    console.log('displayQR called with:', { qrString, text });

    const qrContainer = document.getElementById('qr-container');
    const overlay = document.getElementById('payment-success-overlay');

    // Hide payment success overlay if showing
    overlay.classList.remove('show');

    // Validate qrString
    if (!qrString || qrString.trim() === '') {
        console.error('QR string is empty or undefined!');
        return;
    }

    // Store current QR string for payment validation
    currentQrString = qrString;

    // Show container
    qrContainer.style.display = 'block';

    // Clear previous QR code
    qrContainer.innerHTML = '';

    // Create new QR code using qrcodejs library
    try {
        qrCodeInstance = new QRCode(qrContainer, {
            text: qrString,
            width: 280,
            height: 280,
            colorDark: '#1a1a2e',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        console.log('QRCode generated successfully for:', qrString);
    } catch (error) {
        console.error('QRCode generation error:', error);
    }

    // Convert <br> to actual line breaks and set text
    const qrTextEl = document.getElementById('qr-text');
    if (text) {
        // Replace <br> and <br/> and <br /> with actual line breaks
        qrTextEl.innerHTML = text.replace(/<br\s*\/?>/gi, '<br>');
    } else {
        qrTextEl.innerHTML = '';
    }

    // Clear previous timeout if exists
    if (qrHideTimeout) {
        clearTimeout(qrHideTimeout);
    }

    // Auto-hide QR after 1 minute (180000 ms)
    qrHideTimeout = setTimeout(() => {
        hideQR();
    }, 180000);
}

// Hide QR Code
function hideQR() {
    const qrContainer = document.getElementById('qr-container');
    const qrTextEl = document.getElementById('qr-text');

    qrContainer.style.display = 'none';
    qrContainer.innerHTML = '';
    qrTextEl.innerHTML = '';
    currentQrString = null;

    console.log('QR hidden after timeout');
}

// Show Payment Success Overlay
function showPaymentSuccess(status) {
    const overlay = document.getElementById('payment-success-overlay');
    const qrContainer = document.getElementById('qr-container');
    const qrText = document.getElementById('qr-text');

    if (status) {
        // Hide QR code
        qrContainer.style.display = 'none';
        qrContainer.innerHTML = '';
        qrText.textContent = '';

        // Show success overlay
        overlay.classList.add('show');

        // Auto hide after 5 seconds
        setTimeout(() => {
            overlay.classList.remove('show');
        }, 5000);

        // Clear current QR string after success
        currentQrString = null;
    }
}

// Init
document.addEventListener('DOMContentLoaded', function () {
    console.log('Page loaded, checking cookies...');
    console.log('All cookies:', document.cookie);

    const credentials = checkAndRefreshCookie();
    console.log('Credentials found:', credentials);

    if (credentials) {
        console.log('Auto-connecting with saved credentials...');
        showQrDisplay(credentials.kasirId);
        connectWebSocket(credentials.kasirId, credentials.pin);
    } else {
        console.log('No saved credentials, showing login form');
    }

    // Enter key to submit
    document.getElementById('kasir-id-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') saveKasirId();
    });
});
