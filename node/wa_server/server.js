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

// ============================================
// Security Configuration
// ============================================
// Only these IDs are allowed to connect
// Load from ENV, default to empty array if not set
const ALLOWED_CLIENT_IDS = process.env.ALLOWED_CLIENT_IDS
    ? process.env.ALLOWED_CLIENT_IDS.split(',').map(id => id.trim())
    : ['agent1', 'agent2', 'admin', 'cs1', 'cs2']; // Fallback default

// Always allow 1000-1010 (Admin Range with full access)
for (let i = 1000; i <= 1010; i++) {
    const adminId = i.toString();
    if (!ALLOWED_CLIENT_IDS.includes(adminId)) {
        ALLOWED_CLIENT_IDS.push(adminId);
    }
}

const SOCKET_PASSWORD = process.env.SOCKET_PASSWORD;

// Log allowed IDs for debugging
console.log('='.repeat(50));
console.log('WebSocket Server - Allowed Client IDs:');
console.log('Admin Range:', '1000-1010');
console.log('Regular Agents:', ALLOWED_CLIENT_IDS.filter(id => parseInt(id) < 1000 || parseInt(id) > 1010));
console.log('='.repeat(50));

// Store connected clients: Map<id, Set<WebSocket>> to support multiple connections per ID
// Using Set to allow easy addition/removal
const clients = new Map();
const MAX_CONNECTIONS_PER_ID = 1;

wss.on('connection', (ws, req) => {
    // Extract ID and Password from query params (e.g. ?id=123&password=pass)
    const urlParams = new URLSearchParams(req.url.split('?')[1]);
    const id = urlParams.get('id');
    const password = urlParams.get('password');

    if (!id) {
        console.log('Connection rejected: missing id');
        ws.close(1008, 'id required');
        return;
    }

    // Check Password if set in ENV
    if (SOCKET_PASSWORD && password !== SOCKET_PASSWORD) {
        console.log(`Connection rejected for ID ${id}: Invalid Password`);
        ws.close(1008, 'Invalid Password');
        return;
    }

    // Check if ID is allowed
    if (!ALLOWED_CLIENT_IDS.includes(id)) {
        console.log(`Connection rejected: ID "${id}" is not in the allowed list`);
        ws.close(1008, 'Unauthorized ID');
        return;
    }

    console.log(`Client connected with ID: ${id}`);

    // Manage Connections (Max 2)
    if (!clients.has(id)) {
        clients.set(id, new Set());
    }

    const userSockets = clients.get(id);

    // If limit reached, reject NEW connection
    if (userSockets.size >= MAX_CONNECTIONS_PER_ID) {
        console.log(`Connection rejected for ID ${id}: Max connections limit (${MAX_CONNECTIONS_PER_ID}) reached.`);
        ws.close(1008, 'Connection Limit Reached');
        return;
    }

    userSockets.add(ws);

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
        console.log(`Client disconnected: ${id}`);
        if (clients.has(id)) {
            const userSockets = clients.get(id);
            userSockets.delete(ws);
            if (userSockets.size === 0) {
                clients.delete(id);
            }
        }
    });

    ws.on('error', (err) => {
        console.error(`Client ${id} error:`, err);
        if (clients.has(id)) {
            const userSockets = clients.get(id);
            userSockets.delete(ws);
            if (userSockets.size === 0) {
                clients.delete(id);
            }
        }
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

function sendToTarget(targetId, data, excludeId = null) {
    let sent = false;

    // 1. Send to the specific target_id (all connected sockets for this ID)
    // But NOT if target is the excluded sender
    if (clients.has(targetId) && targetId !== excludeId) {
        const userSockets = clients.get(targetId);
        userSockets.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify(data));
                sent = true;
            }
        });
    }

    // 2. Also send to Monitor IDs (1000-1010 range untuk admin access)
    // Avoid double sending if targetId is the monitor itself
    // AND avoid sending to the excludeId (sender)
    const monitorIds = [];

    // Add range 1000-1010 as admin/monitor IDs
    for (let i = 1000; i <= 1010; i++) {
        monitorIds.push(i.toString());
    }

    monitorIds.forEach(monitorId => {
        // Don't send to: 1) the target itself, 2) the sender (excludeId)
        if (targetId !== monitorId && monitorId !== excludeId && clients.has(monitorId)) {
            const monitorSockets = clients.get(monitorId);
            monitorSockets.forEach(monitor => {
                if (monitor.readyState === WebSocket.OPEN) {
                    monitor.send(JSON.stringify(data));
                }
            });
        }
    });

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

    // BROADCAST TO ALL if target_id = '0'
    if (targetId === '0') {
        console.log('[BROADCAST] Sending to ALL connected clients');

        // Extract sender_id to exclude from broadcast
        const senderId = data.sender_id ? data.sender_id.toString() : null;
        let broadcastCount = 0;

        clients.forEach((userSockets, userId) => {
            // Skip if this is the sender
            if (senderId && userId === senderId) {
                console.log(`[BROADCAST] Skipping sender: ${senderId}`);
                return;
            }

            userSockets.forEach(client => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(data));
                    broadcastCount++;
                }
            });
        });

        console.log(`[BROADCAST] Sent to ${broadcastCount} client(s), excluded sender: ${senderId || 'none'}`);
        return res.json({
            success: true,
            message: `Broadcast to ${broadcastCount} client(s)`,
            broadcast: true
        });
    }

    // Normal flow: Send to specific target
    // Exclude sender_id to prevent duplicate messages
    const senderId = data.sender_id ? data.sender_id.toString() : null;
    const sent = sendToTarget(targetId, {
        type: 'wa_masuk',
        data: data,
        timestamp: new Date().toISOString()
    }, senderId);

    if (sent) {
        res.json({ success: true, message: 'Message sent to client' });
    } else {
        // Option: return generic 200 even if not connected, or 404
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

    // Exclude sender_id to prevent duplicate messages
    const senderId = data.sender_id ? data.sender_id.toString() : null;
    const sent = sendToTarget(targetId, {
        type: type,
        data: data,
        timestamp: new Date().toISOString()
    }, senderId);

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
