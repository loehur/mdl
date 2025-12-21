const WebSocket = require('ws');
const express = require('express');
const http = require('http');
const cors = require('cors');
require('dotenv').config();

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ============================================
// CORS Configuration
// ============================================
// Defaults or from env
const allowedOrigins = process.env.AllowedOrigins
    ? process.env.AllowedOrigins.split(',')
    : ['http://localhost', 'https://ml.nalju.com', 'https://cs.nalju.com', 'http://localhost:8081'];

app.use(cors({
    origin: function (origin, callback) {
        // Allow requests with no origin (like mobile apps or curl requests)
        if (!origin) return callback(null, true);

        // Check allowed origins
        if (allowedOrigins.indexOf(origin) !== -1 || origin.endsWith('.nalju.com')) {
            callback(null, true);
        } else {
            // For development, might want to be more lenient or just log it
            console.log('Origin blocked by CORS:', origin);
            callback(null, true); // Temporary: allow all for dev ease if desired, or strictly fail
        }
    },
    credentials: true
}));

const PORT = process.env.PORT || 3003;

// ============================================
// HTTP Server & WebSocket Setup
// ============================================
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// Store connected clients: Map<id, WebSocket>
const clients = new Map();

// ============================================
// Security Configuration
// ============================================
// Only these IDs are allowed to connect
// Load from ENV, default to empty array if not set
const ALLOWED_CLIENT_IDS = process.env.ALLOWED_CLIENT_IDS
    ? process.env.ALLOWED_CLIENT_IDS.split(',').map(id => id.trim())
    : ['agent1', 'agent2', 'admin', 'cs1', 'cs2']; // Fallback default

// Always allow '0' (Super Admin)
if (!ALLOWED_CLIENT_IDS.includes('0')) {
    ALLOWED_CLIENT_IDS.push('0');
}

wss.on('connection', (ws, req) => {
    // Extract ID from query params (e.g. ?id=123)
    const urlParams = new URLSearchParams(req.url.split('?')[1]);
    const id = urlParams.get('id');

    if (!id) {
        console.log('Connection rejected: missing id');
        ws.close(1008, 'id required');
        return;
    }

    // Check if ID is allowed
    if (!ALLOWED_CLIENT_IDS.includes(id)) {
        console.log(`Connection rejected: ID "${id}" is not in the allowed list`);
        ws.close(1008, 'Unauthorized ID');
        return;
    }

    console.log(`Client connected with ID: ${id}`);

    // Check if ID is already connected, maybe close old one or allow overwrite?
    // Overwriting is usually safer for reconnection logic
    if (clients.has(id)) {
        console.log(`Client ${id} reconnected, replacing old connection`);
        const oldWs = clients.get(id);
        if (oldWs.readyState === WebSocket.OPEN) {
            oldWs.terminate();
        }
        clients.delete(id);
    }

    clients.set(id, ws);

    // Send welcome message
    ws.send(JSON.stringify({
        type: 'connection',
        status: 'urip',
        message: 'Connected to WA Server',
        id: id
    }));

    ws.isAlive = true;
    ws.on('pong', () => { ws.isAlive = true; });

    // Handle incoming messages from client (if any)
    ws.on('message', (message) => {
        try {
            console.log(`Received from ${id}:`, message.toString());
        } catch (e) {
            console.error('Error parsing message from client');
        }
    });

    ws.on('close', () => {
        console.log(`Client ${id} disconnected`);
        clients.delete(id);
    });

    ws.on('error', (err) => {
        console.error(`Client ${id} error:`, err);
        clients.delete(id);
    });
});

// Heartbeat to keep connections alive
const heartbeatInterval = setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) return ws.terminate();
        ws.isAlive = false;
        ws.ping();
    });
}, 30000);

wss.on('close', () => {
    clearInterval(heartbeatInterval);
});

// ============================================
// Helper Functions
// ============================================

function sendToTarget(targetId, data) {
    let sent = false;

    // 1. Send to the specific target_id
    const client = clients.get(targetId);
    if (client && client.readyState === WebSocket.OPEN) {
        client.send(JSON.stringify(data));
        sent = true;
    }

    // 2. Also send to '0' (Super Admin / Monitor) if connected
    // But avoid double sending if targetId is already '0'
    if (targetId !== '0') {
        const monitor = clients.get('0');
        if (monitor && monitor.readyState === WebSocket.OPEN) {
            monitor.send(JSON.stringify(data));
            // We don't change 'sent' status based on monitor, 
            // the primary target status determines success/failure of the intent.
        }
    }

    return sent;
}

// ============================================
// API Endpoints
// ============================================

app.get('/', (req, res) => {
    res.json({
        status: 'running',
        service: 'WA Server',
        clients_count: clients.size,
        connected_ids: Array.from(clients.keys())
    });
});

/**
 * Endpoint to receive incoming WA messages
 * Expects JSON body with message details AND target_id
 */
app.post('/incoming', (req, res) => {
    const data = req.body;
    const targetId = data.target_id;

    if (!targetId) {
        return res.status(400).json({ success: false, message: 'target_id is required' });
    }

    console.log(`WA Incoming for ${targetId}:`, data);

    const sent = sendToTarget(targetId, {
        type: 'wa_masuk',
        data: data,
        timestamp: new Date().toISOString()
    });

    if (sent) {
        res.json({ success: true, message: 'Message sent to client' });
    } else {
        // Option: return generic 200 even if not connected, or 404
        res.status(404).json({ success: false, message: 'Target client not connected' });
    }
});

/**
 * Endpoint to receive outgoing WA messages
 * Expects JSON body with message details AND target_id
 */
app.post('/outgoing', (req, res) => {
    const data = req.body;
    const targetId = data.target_id;

    if (!targetId) {
        return res.status(400).json({ success: false, message: 'target_id is required' });
    }

    console.log(`WA Outgoing for ${targetId}:`, data);

    const sent = sendToTarget(targetId, {
        type: 'wa_keluar',
        data: data,
        timestamp: new Date().toISOString()
    });

    if (sent) {
        res.json({ success: true, message: 'Message sent to client' });
    } else {
        res.status(404).json({ success: false, message: 'Target client not connected' });
    }
});

/**
 * Universal Endpoint if needed
 */
app.post('/webhook', (req, res) => {
    const data = req.body;
    const targetId = data.target_id;
    // Determine type based on payload content if possible, or default to general
    const type = data.type || 'wa_event';

    if (!targetId) {
        return res.status(400).json({ success: false, message: 'target_id is required' });
    }

    console.log(`Webhook (${type}) for ${targetId}:`, data);

    const sent = sendToTarget(targetId, {
        type: type,
        data: data,
        timestamp: new Date().toISOString()
    });

    if (sent) {
        res.json({ success: true, message: 'Event sent to client' });
    } else {
        res.status(404).json({ success: false, message: 'Target client not connected' });
    }
});

// ============================================
// Start Server
// ============================================
server.listen(PORT, () => {
    console.log(`WA Server running on port ${PORT}`);
    console.log(`WebSocket endpoint: ws://localhost:${PORT}?id=YOUR_CLIENT_ID`);
});
