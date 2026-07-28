const WebSocket = require('ws');
const express = require('express');
const http = require('http');
const https = require('https');


const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ============================================
// CORS Middleware
// ============================================
const allowedOrigins = [
    'https://ml.nalju.com',
    'https://qrs.nalju.com',
    'http://localhost',
    'http://localhost:3000'
];

app.use((req, res, next) => {
    const origin = req.headers.origin;

    // Check if the origin is in the allowed list
    if (allowedOrigins.includes(origin)) {
        res.setHeader('Access-Control-Allow-Origin', origin);
    }

    // Allow credentials
    res.setHeader('Access-Control-Allow-Credentials', 'true');

    // Allowed headers
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

    // Allowed methods
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

    // Handle preflight requests
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    next();
});

// ============================================
// Configuration — allowed kasir = id_cabang from DB (via API)
// ============================================
// CRM/Roles.crew is sourced from mdl_laundry.cabang.id_cabang
const API_URL = process.env.API_URL || 'https://api.nalju.com/CRM/Roles';
let ALLOWED_KASIR_IDS = [];

function fetchAllowedKasirIds() {
    return new Promise((resolve) => {
        console.log(`Fetching cabang IDs from ${API_URL}...`);
        const client = API_URL.startsWith('https') ? https : http;

        const req = client.get(API_URL, { timeout: 10000 }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    const crew = (json.data && json.data.crew) ? json.data.crew : [];
                    if (Array.isArray(crew) && crew.length > 0) {
                        ALLOWED_KASIR_IDS = crew.map((id) => String(id).trim()).filter(Boolean);
                        console.log(`✅ Allowed kasir (id_cabang): ${ALLOWED_KASIR_IDS.join(', ')}`);
                    } else {
                        console.error('❌ API returned empty crew/cabang list — keeping previous list');
                    }
                } catch (e) {
                    console.error('❌ Error parsing cabang API response:', e.message);
                }
                resolve();
            });
        });

        req.on('error', (err) => {
            console.error('❌ Failed to fetch cabang IDs:', err.message);
            resolve();
        });

        req.on('timeout', () => {
            req.destroy();
            console.error('❌ Cabang API timeout');
            resolve();
        });
    });
}

function isKasirAllowed(kasirId) {
    const id = String(kasirId || '').trim();
    if (!id) return false;
    return ALLOWED_KASIR_IDS.some((allowed) => String(allowed) === id);
}

// ============================================

// Create HTTP server
const server = http.createServer(app);

// Create WebSocket server
const wss = new WebSocket.Server({ server });

// Store connected clients by kasir_id → WebSocket[]
const clients = new Map();
const MAX_CONNECTIONS_PER_KASIR = 3;

function getSocketList(kasirId) {
    return clients.get(kasirId) || [];
}

function getOpenSockets(kasirId) {
    return getSocketList(kasirId).filter((ws) => ws.readyState === WebSocket.OPEN);
}

function removeSocket(kasirId, ws) {
    const list = getSocketList(kasirId);
    if (!list.length) return;
    const next = list.filter((s) => s !== ws);
    if (next.length === 0) {
        clients.delete(kasirId);
    } else {
        clients.set(kasirId, next);
    }
}

function evictOldest(kasirId) {
    const list = getSocketList(kasirId);
    if (!list.length) return;
    const oldest = list.shift();
    clients.set(kasirId, list);
    if (!oldest) return;
    console.log(`Kasir ${kasirId}: evicting oldest connection (max ${MAX_CONNECTIONS_PER_KASIR})`);
    try {
        oldest.close(4005, 'Replaced by a new connection');
    } catch (e) {
        // ignore
    }
    try {
        oldest.terminate();
    } catch (e) {
        // ignore
    }
}

function broadcast(kasirId, message) {
    const open = getOpenSockets(kasirId);
    open.forEach((ws) => {
        try {
            ws.send(message);
        } catch (e) {
            console.error(`Broadcast error Kasir ${kasirId}:`, e.message);
        }
    });
    return open.length;
}

// WebSocket connection handler
wss.on('connection', (ws, req) => {
    // Extract kasir_id from query parameter (= id_cabang)
    const urlParams = new URLSearchParams(req.url.split('?')[1]);
    const kasirId = String(urlParams.get('kasir_id') || '').trim();

    // Validate kasir_id is provided
    if (!kasirId) {
        console.log('Connection rejected: missing kasir_id');
        ws.close(4001, 'kasir_id is required');
        return;
    }

    // Validate kasir_id exists in cabang (synced from API)
    if (!isKasirAllowed(kasirId)) {
        console.log(`Connection rejected: kasir_id "${kasirId}" is not an allowed id_cabang`);
        ws.close(4003, 'kasir_id is not allowed');
        return;
    }

    // Add to pool; evict oldest while over max
    ws.kasirId = kasirId;
    const list = getSocketList(kasirId);
    list.push(ws);
    clients.set(kasirId, list);
    while (getSocketList(kasirId).length > MAX_CONNECTIONS_PER_KASIR) {
        evictOldest(kasirId);
    }

    const count = getSocketList(kasirId).length;
    console.log(`Kasir ${kasirId} connected (${count}/${MAX_CONNECTIONS_PER_KASIR})`);

    // Send welcome message
    ws.send(JSON.stringify({
        type: 'connected',
        message: `Welcome Kasir ${kasirId}`,
        kasir_id: kasirId
    }));

    // Handle incoming messages from client
    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            // Handle App-Level Ping
            if (data.type === 'ping') {
                ws.send(JSON.stringify({ type: 'pong' }));
                return;
            }

            console.log(`Message from Kasir ${kasirId}:`, data);
        } catch (e) {
            console.log(`Raw message from Kasir ${kasirId}:`, message.toString());
        }
    });

    // Handle client disconnect — remove only this socket from the pool
    ws.on('close', () => {
        console.log(`Kasir ${kasirId} disconnected`);
        removeSocket(kasirId, ws);
    });

    // Handle errors
    ws.on('error', (error) => {
        console.error(`Error for Kasir ${kasirId}:`, error.message);
        removeSocket(kasirId, ws);
    });

    // Heartbeat to keep connection alive
    ws.isAlive = true;
    ws.on('pong', () => {
        ws.isAlive = true;
    });
});

// Heartbeat interval to detect dead connections
const heartbeatInterval = setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) {
            return ws.terminate();
        }
        ws.isAlive = false;
        ws.ping();
    });
}, 15000);

wss.on('close', () => {
    clearInterval(heartbeatInterval);
});

// ============================================
// HTTP API Endpoints
// ============================================

// Handle favicon requests to prevent 404 errors
app.get('/favicon.ico', (req, res) => {
    res.status(204).end();
});

/**
 * Send QR string to specific kasir
 * POST /send-qr
 * Body: { kasir_id: string, qr_string: string, text: string (optional) }
 */
app.post('/send-qr', (req, res) => {
    const { kasir_id, qr_string, text } = req.body;

    if (!kasir_id) {
        return res.status(400).json({
            success: false,
            error: 'kasir_id is required'
        });
    }

    if (!qr_string) {
        return res.status(400).json({
            success: false,
            error: 'qr_string is required'
        });
    }

    const message = JSON.stringify({
        type: 'qr_code',
        qr_string: qr_string,
        text: text || '',
        timestamp: new Date().toISOString()
    });

    const sent = broadcast(kasir_id, message);

    if (sent === 0) {
        return res.status(404).json({
            success: false,
            error: `Kasir ${kasir_id} is not connected`
        });
    }

    console.log(`QR sent to Kasir ${kasir_id} (${sent} socket(s)): ${qr_string}${text ? ' | Text: ' + text : ''}`);

    return res.json({
        success: true,
        message: `QR string sent to Kasir ${kasir_id}`,
        kasir_id: kasir_id,
        qr_string: qr_string,
        text: text || '',
        sockets: sent
    });
});

/**
 * Get list of connected kasir
 * GET /clients
 */
app.get('/clients', (req, res) => {
    const connectedClients = [];
    clients.forEach((list, kasirId) => {
        const openCount = list.filter((ws) => ws.readyState === WebSocket.OPEN).length;
        if (openCount > 0) {
            connectedClients.push({ kasir_id: kasirId, sockets: openCount });
        }
    });

    return res.json({
        success: true,
        count: connectedClients.length,
        clients: connectedClients
    });
});

/**
 * Check if specific kasir is connected
 * GET /client/:kasir_id
 */
app.get('/client/:kasir_id', (req, res) => {
    const kasirId = req.params.kasir_id;
    const openCount = getOpenSockets(kasirId).length;

    return res.json({
        success: true,
        kasir_id: kasirId,
        connected: openCount > 0,
        sockets: openCount
    });
});

/**
 * Send payment success notification to specific kasir
 * POST /payment-success
 * Body: { kasir_id: string, qr_string: string, status: boolean (default: true) }
 */
app.post('/payment-success', (req, res) => {
    const { kasir_id, qr_string, status = true } = req.body;

    if (!kasir_id) {
        return res.status(400).json({
            success: false,
            error: 'kasir_id is required'
        });
    }

    if (!qr_string) {
        return res.status(400).json({
            success: false,
            error: 'qr_string is required'
        });
    }

    const message = JSON.stringify({
        type: 'payment_success',
        qr_string: qr_string,
        status: status,
        timestamp: new Date().toISOString()
    });

    const sent = broadcast(kasir_id, message);

    if (sent === 0) {
        return res.status(404).json({
            success: false,
            error: `Kasir ${kasir_id} is not connected`
        });
    }

    console.log(`Payment success sent to Kasir ${kasir_id} (${sent} socket(s)): qr_string=${qr_string}, status=${status}`);

    return res.json({
        success: true,
        message: `Payment success notification sent to Kasir ${kasir_id}`,
        kasir_id: kasir_id,
        qr_string: qr_string,
        status: status,
        sockets: sent
    });
});

/**
 * Health check endpoint
 * GET /health
 */
app.get('/health', (req, res) => {
    return res.json({
        success: true,
        status: 'running',
        timestamp: new Date().toISOString()
    });
});

// ============================================
// Start Server
// ============================================

const PORT = process.env.PORT || 3001;
const HOST = process.env.HOST || '0.0.0.0'; // Bind to all interfaces for VPS access

fetchAllowedKasirIds().then(() => {
    server.listen(PORT, HOST, () => {
        console.log('========================================');
        console.log(`  QR WebSocket Server`);
        console.log('========================================');
        console.log(`  HTTP API : http://localhost:${PORT}`);
        console.log(`  WebSocket: ws://localhost:${PORT}`);
        console.log(`  Max sockets / kasir: ${MAX_CONNECTIONS_PER_KASIR}`);
        console.log(`  Allowed id_cabang: ${ALLOWED_KASIR_IDS.length} (from DB via API)`);
        console.log('========================================');
        console.log('');
        console.log('Endpoints:');
        console.log('  POST /send-qr         - Send QR to kasir');
        console.log('  POST /payment-success - Send payment success to kasir');
        console.log('  GET  /clients         - List connected kasir');
        console.log('  GET  /client/:id      - Check kasir connection');
        console.log('  GET  /health          - Health check');
        console.log('');
        console.log('WebSocket connection:');
        console.log(`  ws://localhost:${PORT}?kasir_id=ID_CABANG`);
        console.log('========================================');
    });
});

// Refresh cabang list every 5 minutes
setInterval(() => {
    fetchAllowedKasirIds();
}, 5 * 60 * 1000);
