const WebSocket = require('ws');
const express = require('express');
const http = require('http');
const https = require('https'); // Add https module support
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
    : ['http://localhost', 'https://ml.nalju.com', 'https://cms.nalju.com', 'http://localhost:8081'];

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
const SOCKET_PASSWORD = process.env.SOCKET_PASSWORD;

// ============================================
// OneSignal Configuration
// ============================================
const ONESIGNAL_APP_ID = process.env.ONESIGNAL_APP_ID || '';
const ONESIGNAL_REST_API_KEY = process.env.ONESIGNAL_REST_API_KEY || '';

/**
 * Send push notification via OneSignal
 * @param {object} options - Notification options
 * @param {string} options.title - Notification title
 * @param {string} options.message - Notification body
 * @param {string} options.phone - Customer phone (used as collapse_id)
 * @param {number} options.caseType - Case type (1=Payment, 2=Pickup, 3=Request, 4=FollowUp)
 * @param {object} options.data - Additional data payload
 * @param {string[]} options.userIds - Target user IDs (external_user_id in OneSignal)
 */
async function sendPushNotification(options) {
    const { title, message, phone, caseType, data, userIds } = options;

    if (!ONESIGNAL_APP_ID || !ONESIGNAL_REST_API_KEY) {
        console.log('[OneSignal] Skipped: Missing APP_ID or REST_API_KEY');
        return { success: false, error: 'OneSignal not configured' };
    }

    // Skip notification for case 0 (done/resolved conversations)
    if (caseType === 0) {
        console.log('[OneSignal] Skipped: Case 0 (done/resolved) - no notification needed');
        return { success: false, error: 'Case 0 - no notification' };
    }

    if (!userIds || userIds.length === 0) {
        console.log('[OneSignal] Skipped: No target user IDs');
        return { success: false, error: 'No target users' };
    }

    // Filter users by role if caseType is specified
    const filteredUserIds = userIds.filter(userId => {
        const role = getUserRole(userId);

        // Admin: receives all notifications
        if (role === 'admin') return true;

        // Driver: only receives case 2 (Pickup/Delivery)
        if (role === 'driver') {
            return caseType === 2;
        }

        // Crew: receives all (will be filtered by assignment at broadcast level)
        return true;
    });

    if (filteredUserIds.length === 0) {
        console.log('[OneSignal] Skipped: No eligible users after role filter');
        return { success: false, error: 'No eligible users' };
    }

    const payload = {
        app_id: ONESIGNAL_APP_ID,
        include_external_user_ids: filteredUserIds,
        headings: { en: title },
        contents: { en: message },
        collapse_id: phone ? `chat_${phone}` : undefined, // Group notifications by customer phone
        data: {
            type: 'wa_masuk',
            phone: phone,
            case: caseType,
            ...data
        },
        android_channel_id: process.env.ONESIGNAL_ANDROID_CHANNEL_ID || undefined,
        ios_badgeType: 'Increase',
        ios_badgeCount: 1
    };

    // Remove undefined values
    Object.keys(payload).forEach(key => payload[key] === undefined && delete payload[key]);

    try {
        const response = await fetch('https://onesignal.com/api/v1/notifications', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Basic ${ONESIGNAL_REST_API_KEY}`
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.id) {
            console.log(`[OneSignal] ✅ Sent to ${filteredUserIds.length} user(s), collapse_id: chat_${phone}`);
            return { success: true, id: result.id, recipients: filteredUserIds.length };
        } else {
            console.log('[OneSignal] ❌ Error:', result.errors || result);
            return { success: false, error: result.errors || 'Unknown error' };
        }
    } catch (err) {
        console.error('[OneSignal] ❌ Request failed:', err.message);
        return { success: false, error: err.message };
    }
}

// ============================================
// HTTP Server & WebSocket Setup
// ============================================
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// ============================================
// Security Configuration
// ============================================
// ============================================
// Variables to hold IDs (will be populated from API)
// ============================================
let CREW_IDS = [];
let ADMIN_IDS = [];
let DRIVER_IDS = [];

// API URL for fetching roles
// Adjust domain/path if hosted differently in production
// API URL for fetching roles
// Adjust domain/path if hosted differently in production
// FOR LOCAL DEV: Use localhost URL to fetch 'ADI' role from local PHP
const API_URL = process.env.API_URL || 'https://api.nalju.com/CMS/Roles';

async function fetchRoles() {
    return new Promise((resolve) => {
        console.log(`Fetching roles from ${API_URL}...`);

        // Select correct module
        const client = API_URL.startsWith('https') ? https : http;

        client.get(API_URL, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    if (json.status && json.data) {
                        CREW_IDS = json.data.crew || [];
                        ADMIN_IDS = json.data.admin || [];
                        DRIVER_IDS = json.data.driver || [];

                        // Ensure lists are array of strings
                        CREW_IDS = CREW_IDS.map(String);
                        ADMIN_IDS = ADMIN_IDS.map(String);
                        DRIVER_IDS = DRIVER_IDS.map(String);

                        console.log('✅ Roles updated from API');
                    } else {
                        console.error('❌ API returned invalid format:', json);
                        useDefaults();
                    }
                } catch (e) {
                    console.error('❌ Error parsing API response:', e.message);
                    useDefaults();
                }
                resolve();
            });

        }).on('error', (err) => {
            console.error('❌ Failed to fetch from API:', err.message);
            useDefaults();
            resolve();
        });
    });
}

function useDefaults() {
    console.log('⚠️ Using default fallback IDs');
    // Fallback defaults from env or hardcoded
    CREW_IDS = process.env.CREW_IDS ? process.env.CREW_IDS.split(',').map(id => id.trim()) : ['3', '4', '5', '6', '10', '11'];
    ADMIN_IDS = process.env.ADMIN_IDS ? process.env.ADMIN_IDS.split(',').map(id => id.trim()) : ['DEV', 'AYAH'];
    DRIVER_IDS = process.env.DRIVER_IDS ? process.env.DRIVER_IDS.split(',').map(id => id.trim()) : ['DRIVER1'];
}

function logRoles() {
    console.log('='.repeat(50));
    console.log('WebSocket Server - Active Client IDs:');
    console.log('Admin IDs:', ADMIN_IDS.join(', '));
    console.log('Driver IDs:', DRIVER_IDS.join(', '));
    console.log('Crew IDs:', CREW_IDS.join(', '));
    console.log('Total Allowed IDs:', CREW_IDS.length + ADMIN_IDS.length + DRIVER_IDS.length);
    console.log('='.repeat(50));
}

// ============================================
// Helper Functions для Role Detection
// ============================================
/**
 * Determine user role based on ID
 * @param {string} id - User ID
 * @returns {string} - 'admin', 'driver', or 'crew'
 */
function getUserRole(id) {
    if (ADMIN_IDS.includes(id)) {
        return 'admin';
    } else if (DRIVER_IDS.includes(id)) {
        return 'driver';
    } else if (CREW_IDS.includes(id)) {
        return 'crew';
    } else {
        // If not in explicit lists but connected (maybe allow all?)
        // For now default to crew or unauthorized if strict
        return 'crew';
    }
}

function isIdAllowed(id) {
    return ADMIN_IDS.includes(id) || DRIVER_IDS.includes(id) || CREW_IDS.includes(id);
}

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
    if (!isIdAllowed(id)) {
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

    console.log(`[DEBUG] ID ${id}: Current connections = ${userSockets.size}, Max = ${MAX_CONNECTIONS_PER_ID}`);

    // If limit reached, reject NEW connection
    if (userSockets.size >= MAX_CONNECTIONS_PER_ID) {
        console.log(`Connection rejected for ID ${id}: Max connections limit (${MAX_CONNECTIONS_PER_ID}) reached.`);
        ws.close(1008, 'Connection Limit Reached');
        return;
    }

    userSockets.add(ws);
    console.log(`[DEBUG] ID ${id}: Connection accepted, total connections now = ${userSockets.size}`);

    const role = getUserRole(id);
    ws.role = role; // Attach role to socket for filtering later
    console.log(`[DEBUG] ID ${id} assigned role: ${role}`);

    // Send welcome message
    ws.send(JSON.stringify({
        type: 'connection',
        status: 'urip',
        message: 'Connected to WA Server',
        id: id,
        role: role
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

    // 2. Also send to Admin IDs (for monitoring purposes)
    // Avoid double sending if targetId is the admin itself
    // AND avoid sending to the excludeId (sender)
    ADMIN_IDS.forEach(adminId => {
        // Don't send to: 1) the target itself, 2) the sender (excludeId)
        if (targetId !== adminId && adminId !== excludeId && clients.has(adminId)) {
            const adminSockets = clients.get(adminId);
            adminSockets.forEach(adminSocket => {
                if (adminSocket.readyState === WebSocket.OPEN) {
                    adminSocket.send(JSON.stringify(data));
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
app.post('/incoming', async (req, res) => {
    const data = req.body;
    const targetId = data.target_id;

    if (!targetId) {
        return res.status(400).json({ success: false, message: 'target_id is required' });
    }

    console.log(`WA Incoming for ${targetId}:`, data);

    // Extract common data for push notification
    const customerPhone = data.phone || data.wa_number || '';
    const customerName = data.name || data.contact_name || 'Customer';

    // Handle messageText - data.message can be an object from PHP webhook
    let messageText = '';
    if (typeof data.text === 'string') {
        messageText = data.text;
    } else if (typeof data.message === 'object' && data.message !== null) {
        // PHP sends message as object: { id, text, type, ... }
        messageText = data.message.text || data.message.caption || '';
    } else if (typeof data.message === 'string') {
        messageText = data.message;
    } else if (typeof data.lastMessage === 'string') {
        messageText = data.lastMessage;
    }
    // Ensure messageText is always a string
    messageText = String(messageText || '');

    const caseType = parseInt(data.priority || data.case || 0);

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
                    // --- ROLE BASED FILTERING ---
                    const role = client.role || getUserRole(userId);
                    let shouldSend = false;

                    // 1. Admin: Sees everything
                    if (role === 'admin') {
                        shouldSend = true;
                    }
                    // 2. Driver: Only Priority 2 (Delivery/Pickup)
                    else if (role === 'driver') {
                        if (data.priority == 2) { // Loose equality for string/int
                            shouldSend = true;
                        }
                    }
                    // 3. Crew: Only their assignments
                    else { // role === 'crew'
                        // Check if message is assigned to this user
                        // Handle both possible locations of assignment_user_id
                        const assignmentId = data.assignment_user_id || (data.data && data.data.assignment_user_id);

                        if (assignmentId && String(assignmentId) === String(userId)) {
                            shouldSend = true;
                        }
                    }

                    if (shouldSend) {
                        // Send data as-is (already in correct format from PHP)
                        client.send(JSON.stringify(data));
                        broadcastCount++;
                        console.log(`[BROADCAST] Sent to client: ${userId} (${role})`);
                    }
                }
            });
        });

        // Collect OFFLINE users for push notification
        // Only users who are NOT connected via WebSocket should get push
        const connectedUserIds = Array.from(clients.keys()); // Currently connected users
        const allUserIds = [...ADMIN_IDS, ...DRIVER_IDS, ...CREW_IDS];
        const offlineUserIds = allUserIds.filter(userId => {
            // Must not be connected AND must not be the sender
            return !connectedUserIds.includes(userId) && userId !== senderId;
        });

        console.log(`[PUSH] Connected users: ${connectedUserIds.join(', ') || 'none'}`);
        console.log(`[PUSH] Offline users for push: ${offlineUserIds.join(', ') || 'none'}`);

        // Send Push Notification only to OFFLINE users
        let pushResult = { success: false, error: 'No offline users' };
        if (offlineUserIds.length > 0) {
            pushResult = await sendPushNotification({
                title: customerName,
                message: messageText.substring(0, 100) || '📩 New message',
                phone: customerPhone,
                caseType: caseType,
                userIds: offlineUserIds,
                data: { phone: customerPhone }
            });
        }

        console.log(`[BROADCAST] Sent to ${broadcastCount} client(s), excluded sender: ${senderId || 'none'}`);
        console.log(`[BROADCAST] Push notification to ${offlineUserIds.length} offline user(s):`, pushResult.success ? '✅' : '⏭️ skipped');

        return res.json({
            success: true,
            message: `Broadcast to ${broadcastCount} client(s)`,
            broadcast: true,
            push: pushResult,
            offlineUsers: offlineUserIds.length
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

    // Send Push Notification only if target is OFFLINE (not connected)
    let pushResult = { success: false, error: 'Target is online' };
    const isTargetConnected = clients.has(targetId);

    if (!isTargetConnected) {
        pushResult = await sendPushNotification({
            title: customerName,
            message: messageText.substring(0, 100) || '📩 New message',
            phone: customerPhone,
            caseType: caseType,
            userIds: [targetId],
            data: { phone: customerPhone }
        });
        console.log(`[PUSH] Target ${targetId} is offline, push sent:`, pushResult.success ? '✅' : '❌');
    } else {
        console.log(`[PUSH] Target ${targetId} is online, skipping push notification`);
    }

    if (sent) {
        res.json({ success: true, message: 'Message sent to client', push: pushResult });
    } else {
        // Target not connected via WebSocket, but push was sent
        res.json({ success: true, message: 'Target offline, push notification sent', push: pushResult });
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

// Initialize and Start
fetchRoles().then(() => {
    logRoles();

    server.listen(PORT, () => {
        console.log(`WA Server running on port ${PORT}`);
        console.log(`WebSocket endpoint: ws://localhost:${PORT}?id=YOUR_CLIENT_ID`);
    });
});

// Refresh roles periodically (e.g., every 5 minutes) optional
setInterval(() => {
    fetchRoles().then(() => {
        // console.log('Roles refreshed'); // Silent update
    });
}, 5 * 60 * 1000);
