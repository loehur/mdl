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

// ============================================
// OneSignal Configuration
// ============================================
const ONESIGNAL_APP_ID = process.env.ONESIGNAL_APP_ID || '';
const ONESIGNAL_REST_API_KEY = process.env.ONESIGNAL_REST_API_KEY || '';

// ============================================
// Notification Counter per Chat (for WhatsApp-style grouping)
// ============================================
// Tracks messages per groupKey for notification display
// Format: Map<groupKey, { count: number, messages: string[], lastSent: timestamp }>
const notificationCounter = new Map();

// Auto-cleanup: Reset counter after 5 minutes of inactivity
const COUNTER_TTL_MS = 5 * 60 * 1000; // 5 minutes

// Max messages to store in history (to prevent memory issues)
const MAX_MESSAGE_HISTORY = 10;

/**
 * Add message to history and get notification info
 * @param {string} groupKey - The group key (e.g., chat_6281234567890)
 * @param {string} message - The new message to add
 * @returns {{ count: number, displayMessage: string }} - Count and formatted display message
 */
function addMessageToHistory(groupKey, message) {
    const now = Date.now();
    const existing = notificationCounter.get(groupKey);

    // Truncate long messages for notification display (40 chars max)
    const shortMessage = message.length > 40 ? message.substring(0, 37) + '...' : message;

    if (existing && (now - existing.lastSent) < COUNTER_TTL_MS) {
        // Still within TTL, add to history
        existing.count++;
        existing.messages.push(shortMessage);

        // Keep only last N messages
        if (existing.messages.length > MAX_MESSAGE_HISTORY) {
            existing.messages = existing.messages.slice(-MAX_MESSAGE_HISTORY);
        }

        existing.lastSent = now;
        notificationCounter.set(groupKey, existing);

        // Format: "pesan 1\npesan 2\npesan 3"
        const displayMessage = existing.messages.join('\n');
        return { count: existing.count, displayMessage };
    } else {
        // Expired or new, start fresh
        notificationCounter.set(groupKey, {
            count: 1,
            messages: [shortMessage],
            lastSent: now
        });
        return { count: 1, displayMessage: shortMessage };
    }
}

/**
 * Reset notification count for a group (when user opens chat)
 * @param {string} groupKey - The group key
 */
function resetNotifCount(groupKey) {
    notificationCounter.delete(groupKey);
}

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
    const { title, message, phone, caseType, data, userIds, autoReplied } = options;

    if (!ONESIGNAL_APP_ID || !ONESIGNAL_REST_API_KEY) {
        console.log('[OneSignal] Skipped: Missing APP_ID or REST_API_KEY');
        return { success: false, error: 'OneSignal not configured' };
    }

    // Skip notification if system already auto-replied to the customer
    if (autoReplied === true) {
        console.log('[OneSignal] Skipped: Auto-reply was sent - no push notification needed');
        return { success: false, error: 'Auto-replied - no notification' };
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

    // Sanitize phone for key generation (remove non-digits) to ensure consistency
    const cleanPhone = phone ? String(phone).replace(/\D/g, '') : '';
    const groupKey = cleanPhone ? `chat_${cleanPhone}` : undefined;

    // ============================================
    // Track messages and build display content
    // ============================================
    const { count: notifCount, displayMessage } = addMessageToHistory(groupKey, message);

    // ============================================
    // SINGLE NOTIFICATION PER CUSTOMER (WhatsApp-like)
    // ============================================
    // - Uses collapse_id so new messages REPLACE old notification
    // - Shows all message history separated by newlines
    // - Clean, no spam, like WhatsApp

    // Just show message list directly (no count header)
    const notifContent = displayMessage;

    const payload = {
        app_id: ONESIGNAL_APP_ID,
        include_external_user_ids: filteredUserIds.map(id => id.toUpperCase()),
        headings: { en: title },
        contents: { en: notifContent },

        // collapse_id: Same customer = REPLACE old notification (not stack)
        // This creates WhatsApp-like behavior: 1 notification per customer
        collapse_id: groupKey,

        // android_group for visual grouping if multiple customers
        android_group: 'mdl_chat',
        android_group_message: { en: '$[notif_count] chat baru' },

        // iOS thread
        thread_id: groupKey,
        ios_badgeType: 'Increase',
        ios_badgeCount: 1,

        android_channel_id: process.env.ONESIGNAL_ANDROID_CHANNEL_ID || undefined,

        data: {
            type: 'wa_masuk',
            phone: phone,
            clean_phone: cleanPhone, // Add clean phone for matching
            phone_clean: cleanPhone, // Alternative key name
            case: caseType,
            notif_count: notifCount,
            group_id: groupKey, // Identifier for cancellation/grouping
            chat_group: groupKey, // Alternative key name
            collapse_id: groupKey, // Also include collapse_id in data
            ...data
        }
    };

    // Remove undefined values
    Object.keys(payload).forEach(key => payload[key] === undefined && delete payload[key]);

    console.log(`[OneSignal] Group ${groupKey}: notif_count = ${notifCount}`);

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
            console.log(`[OneSignal] ✅ Sent to ${filteredUserIds.length} user(s), collapse_id: ${groupKey}, count: ${notifCount}`);
            return {
                success: true,
                id: result.id,
                notifCount: notifCount,
                recipients: filteredUserIds.length
            };
        } else {
            console.log('[OneSignal] ❌ Error:', result.errors || result);
            return { success: false, error: result.errors || 'Unknown error' };
        }
    } catch (err) {
        console.error('[OneSignal] ❌ Request failed:', err.message);
        return { success: false, error: err.message };
    }
}

/**
 * Send SILENT push notification to trigger cancellation on device
 * @param {object} options 
 */
async function sendSilentCancelNotification(options) {
    const { phone, userIds } = options;

    if (!ONESIGNAL_APP_ID || !ONESIGNAL_REST_API_KEY) {
        return { success: false, error: 'OneSignal not configured' };
    }

    // Normalize phone: remove all non-digits for groupKey consistency
    const cleanPhone = phone ? String(phone).replace(/\D/g, '') : '';
    const groupKey = cleanPhone ? `chat_${cleanPhone}` : undefined;

    if (!groupKey) return { success: false, error: 'Invalid phone for group key' };

    // Keep original phone format for matching (could be +628... or 628...)
    const originalPhone = phone || '';

    console.log(`[Silent Cancel] Phone: ${originalPhone}, Clean: ${cleanPhone}, GroupKey: ${groupKey}`);

    const payload = {
        app_id: ONESIGNAL_APP_ID,
        include_external_user_ids: userIds.map(id => id.toUpperCase()),

        // Critical for Silent Push
        content_available: true, // iOS Background Fetch
        
        // Android: Need empty contents for data-only notification to work
        // OneSignal requires contents field even for silent push
        contents: { en: '' }, // Empty string = silent
        headings: { en: '' }, // Empty heading = silent
        
        // Android-specific silent push settings
        android_accent_color: 'FF6366F1', // Match your app color
        priority: 10,           // High Priority for immediate delivery
        
        // collapse_id: MUST match the original notification's collapse_id
        // This ensures Android replaces the existing notification
        collapse_id: groupKey,
        
        // Android group (should match original notification)
        android_group: 'mdl_chat',
        
        // Android channel (should match original notification)
        android_channel_id: process.env.ONESIGNAL_ANDROID_CHANNEL_ID || undefined,

        // Data payload - this is what triggers the cancellation logic
        // Include BOTH original phone and clean phone for maximum matching compatibility
        data: {
            type: 'cancel_chat',
            group_id: groupKey,
            phone: originalPhone,           // Original format: +628117686252
            clean_phone: cleanPhone,        // Clean format: 628117686252
            phone_clean: cleanPhone,        // Alternative key name
            // Also include groupKey variations for matching
            chat_group: groupKey,
            collapse_id: groupKey
        }
    };

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
            console.log(`[OneSignal] 🔇 Silent Cancel sent to ${userIds.length} user(s), group: ${groupKey}`);
            return { success: true, id: result.id };
        } else {
            console.log('[OneSignal] ❌ Silent Error:', result.errors || result);
            return { success: false, error: result.errors };
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
const API_URL = process.env.API_URL || 'https://api.nalju.com/CRM/Roles';

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
 * Case-insensitive check if array includes value
 * @param {string[]} arr - Array of strings
 * @param {string} value - Value to find
 * @returns {boolean}
 */
function includesIgnoreCase(arr, value) {
    if (!value) return false;
    const lowerValue = value.toLowerCase();
    return arr.some(item => item.toLowerCase() === lowerValue);
}

/**
 * Determine user role based on ID (case-insensitive)
 * @param {string} id - User ID
 * @returns {string} - 'admin', 'driver', or 'crew'
 */
function getUserRole(id) {
    if (includesIgnoreCase(ADMIN_IDS, id)) {
        return 'admin';
    } else if (includesIgnoreCase(DRIVER_IDS, id)) {
        return 'driver';
    } else if (includesIgnoreCase(CREW_IDS, id)) {
        return 'crew';
    } else {
        // If not in explicit lists but connected (maybe allow all?)
        // For now default to crew or unauthorized if strict
        return 'crew';
    }
}

function isIdAllowed(id) {
    return includesIgnoreCase(ADMIN_IDS, id) || includesIgnoreCase(DRIVER_IDS, id) || includesIgnoreCase(CREW_IDS, id);
}

// Store connected clients: Map<id, Set<WebSocket>> to support multiple connections per ID
// Using Set to allow easy addition/removal
const clients = new Map();
const MAX_CONNECTIONS_PER_ID = 1;

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

    // ⭐ SPECIAL HANDLER: Push notification to drivers for Case 2 (Pickup/Delivery)
    if (data.type === 'driver_pickup_added') {
        console.log('[DRIVER PUSH] Case 2 (Pickup/Delivery) added:', data);

        const customerName = (data.contact_name || 'Customer').toUpperCase();
        const customerPhone = data.phone || '';
        const message = data.message || `📦 Pickup/Delivery from ${customerName}`;

        // Find OFFLINE drivers only (case-insensitive compare)
        const connectedUserIds = Array.from(clients.keys());
        const connectedLower = connectedUserIds.map(id => id.toLowerCase());
        const offlineDrivers = DRIVER_IDS.filter(driverId => {
            return !connectedLower.includes(driverId.toLowerCase());
        });

        console.log(`[DRIVER PUSH] Connected: ${connectedUserIds.join(', ') || 'none'}`);
        console.log(`[DRIVER PUSH] Offline drivers: ${offlineDrivers.join(', ') || 'none'}`);

        let pushResult = { success: false, error: 'No offline drivers' };

        if (offlineDrivers.length > 0) {
            pushResult = await sendPushNotification({
                title: `📦 ${customerName}`,
                message: message,
                phone: customerPhone,
                caseType: 2,
                userIds: offlineDrivers,
                data: { phone: customerPhone, type: 'pickup' }
            });
            console.log(`[DRIVER PUSH] Sent to ${offlineDrivers.length} driver(s):`, pushResult.success ? '✅' : '❌');
        }

        // Also broadcast to connected drivers via WebSocket
        let wsCount = 0;
        DRIVER_IDS.forEach(driverId => {
            if (clients.has(driverId)) {
                const driverSockets = clients.get(driverId);
                driverSockets.forEach(socket => {
                    if (socket.readyState === WebSocket.OPEN) {
                        socket.send(JSON.stringify(data));
                        wsCount++;
                    }
                });
            }
        });

        console.log(`[DRIVER PUSH] WebSocket sent to ${wsCount} connected driver(s)`);

        return res.json({
            success: true,
            message: `Driver notification sent`,
            push: pushResult,
            websocket: wsCount
        });
    }


    console.log(`WA Incoming for ${targetId}:`, data);

    // Extract common data for push notification
    const customerPhone = data.phone || data.wa_number || '';
    const customerName = (data.name || data.contact_name || 'Customer').toUpperCase();

    // Handle messageText - data.message can be an object from PHP webhook
    let messageText = '';
    let messageType = '';

    if (typeof data.text === 'string') {
        messageText = data.text;
    } else if (typeof data.message === 'object' && data.message !== null) {
        // PHP sends message as object: { id, text, type, ... }
        messageText = data.message.text || data.message.caption || '';
        messageType = data.message.type || '';
    } else if (typeof data.message === 'string') {
        messageText = data.message;
    } else if (typeof data.lastMessage === 'string') {
        messageText = data.lastMessage;
    }

    // Ensure messageText is always a string and trimmed
    messageText = String(messageText || '').trim();

    // Flag to skip notification if no meaningful text content
    const hasTextContent = messageText.length > 0;

    // Flag to skip push notification for non-incoming message types
    // These are internal events/agent messages that should NOT trigger push notifications
    const skipPushTypes = [
        'agent_message_sent',    // Outgoing message from agent
        'status_update',         // Message status (sent/delivered/read)
        'case_updated',          // Case status change
        'case_resolved',         // Case resolved
        'conversation_read',     // Conversation marked as read
    ];
    const eventType = data.type || '';
    const shouldSkipPush = skipPushTypes.includes(eventType);

    if (shouldSkipPush) {
        console.log(`[PUSH] Info: Event type '${eventType}' excludes standard push (Silent push still possible)`);
    }

    const caseType = parseInt(data.case || 0);


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
                    // 2. Driver: Only Case 2 (Delivery/Pickup)
                    else if (role === 'driver') {
                        if (data.case == 2) { // Loose equality for string/int
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
        const connectedLower = connectedUserIds.map(id => id.toLowerCase()); // For case-insensitive compare
        const senderLower = senderId ? senderId.toLowerCase() : null;

        const allUserIds = [...ADMIN_IDS, ...DRIVER_IDS, ...CREW_IDS];
        const offlineUserIds = allUserIds.filter(userId => {
            const userIdLower = userId.toLowerCase();
            // Must not be connected AND must not be the sender (case-insensitive)
            const isOffline = !connectedLower.includes(userIdLower) && userIdLower !== senderLower;
            if (!isOffline) return false;

            // ROLE FILTER (use includesIgnoreCase for safety)
            if (includesIgnoreCase(DRIVER_IDS, userId)) {
                // Driver Filter: Accept if Case 2 OR Conversation has Active Case 2
                const activeCases = data.active_cases || [];
                const hasPickup = Array.isArray(activeCases) && (activeCases.includes(2) || activeCases.includes('2'));
                return (caseType === 2 || hasPickup);
            }
            // Admins & Crew get all
            return true;
        });

        console.log(`[PUSH] Connected users: ${connectedUserIds.join(', ') || 'none'}`);
        console.log(`[PUSH] Offline users for push: ${offlineUserIds.join(', ') || 'none'}`);

        // Send Push Notification only to OFFLINE users AND only if message has text content
        // AND only for incoming messages (not agent messages or system events)
        let pushResult = { success: false, error: 'No text content' };
        const autoReplied = data.auto_replied === true;

        // DEBUG: Log status of silent push triggers
        if (data.type === 'case_resolved' || data.type === 'mark_done' || data.all_closed) {
            console.log(`[PUSH-DEBUG] Silent Check: all_closed=${data.all_closed}, offlineUsers=${offlineUserIds.length}, phone=${customerPhone}`);
        }

        // Check for CANCEL / ALL CLOSED signal
        // Relax check to allow fuzzy boolean/string for all_closed
        if ((data.all_closed === true || data.all_closed === 'true') && offlineUserIds.length > 0) {
            console.log(`[PUSH] Triggering Silent Cancel for ${customerPhone}`);
            pushResult = await sendSilentCancelNotification({
                phone: customerPhone,
                userIds: offlineUserIds
            });
        }
        else if (offlineUserIds.length > 0 && hasTextContent && !shouldSkipPush) {
            pushResult = await sendPushNotification({
                title: customerName,
                message: messageText.substring(0, 100),
                phone: customerPhone,
                caseType: caseType,
                userIds: offlineUserIds,
                autoReplied: autoReplied,
                data: { phone: customerPhone }
            });
        } else if (shouldSkipPush) {
            pushResult = { success: false, error: 'Skipped: agent/system event' };
        } else if (!hasTextContent) {
            console.log('[PUSH] Skipped: Message has no text content (image/sticker/etc without caption)');
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

    // Send Push Notification
    // Logic: 
    // 1. Target: Filter if Driver (Case 2 only). Send if Offline.
    // 2. Admin: Always send if Offline (Monitoring).

    let pushRecipients = [];

    // 1. Target Logic
    const isTargetDriver = DRIVER_IDS.includes(targetId);
    let shouldNotifyTarget = true;

    // Driver Constraint
    if (isTargetDriver) {
        // Driver only gets if Current Case is 2 OR Active Case 2 exists
        const activeCases = data.active_cases || [];
        const hasPickup = Array.isArray(activeCases) && (activeCases.includes(2) || activeCases.includes('2'));

        if (caseType !== 2 && !hasPickup) {
            shouldNotifyTarget = false;
        }
    }

    if (shouldNotifyTarget && !clients.has(targetId)) {
        pushRecipients.push(targetId);
    }

    // 2. Admin Monitoring (Always Include Offline Admins)
    ADMIN_IDS.forEach(adminId => {
        if (!clients.has(adminId) && adminId !== senderId) {
            pushRecipients.push(adminId);
        }
    });

    // Deduplicate
    pushRecipients = [...new Set(pushRecipients)];

    let pushResult = { success: false, recipients: 0 };
    const autoRepliedTarget = data.auto_replied === true;

    // Only send push if message has text content AND is incoming (not agent/system event)
    if (pushRecipients.length > 0 && hasTextContent && !shouldSkipPush) {
        pushResult = await sendPushNotification({
            title: customerName,
            message: messageText.substring(0, 100),
            phone: customerPhone,
            caseType: caseType,
            userIds: pushRecipients,
            autoReplied: autoRepliedTarget,
            data: { phone: customerPhone }
        });
        console.log(`[PUSH] Sent to ${pushRecipients.length} offline user(s) (${pushRecipients.join(',')}) for target ${targetId} (Case ${caseType}):`, pushResult.success ? '✅' : '❌');
    } else if (shouldSkipPush) {
        console.log(`[PUSH] Skipped: Event type '${eventType}' does not trigger push notification`);
    } else if (!hasTextContent) {
        console.log('[PUSH] Skipped: Message has no text content');
    }


    if (sent) {
        res.json({ success: true, message: 'Message sent to client', push: pushResult });
    } else {
        res.json({ success: true, message: 'Target offline, push check complete', push: pushResult });
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
