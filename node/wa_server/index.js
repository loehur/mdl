/**
 * WhatsApp Multi-Session Service
 *
 * Service untuk menjalankan multiple WhatsApp sessions secara bersamaan
 * menggunakan Baileys library
 *
 * @author MDL Team
 * @version 2.0.0
 */

const fs = require("fs-extra");
const pino = require("pino");
const express = require("express");
const fileUpload = require("express-fileupload");
const cors = require("cors");
const bodyParser = require("body-parser");
const qrcode = require("qrcode");
const { Boom } = require("@hapi/boom");

const {
  default: makeWASocket,
  DisconnectReason,
  fetchLatestBaileysVersion,
  isJidBroadcast,
  useMultiFileAuthState,
} = require("@whiskeysockets/baileys");

const webhook = require("./config.js");

// ============================================================================
// CONFIGURATION
// ============================================================================

const PORT = process.env.PORT || 8033;
const AUTH_DIR_BASE = "auth";
const path = require("path");

// ============================================================================
// APPLICATION SETUP
// ============================================================================

const app = express();

// CORS Configuration - Whitelist Origins
const allowedOrigins = [
  'http://localhost',
  'https://localhost',
  'http://127.0.0.1',
  'https://127.0.0.1',
  'http://ml.nalju.com',
  'https://ml.nalju.com',
];

const corsOptions = {
  origin: function (origin, callback) {
    // Allow requests with no origin (like mobile apps or curl requests)
    if (!origin) return callback(null, true);

    // Check if origin is in whitelist (also check with different ports for localhost)
    const isAllowed = allowedOrigins.some(allowed => {
      if (origin === allowed) return true;
      // Allow localhost with any port
      if (origin.match(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/)) return true;
      return false;
    });

    if (isAllowed) {
      callback(null, true);
    } else {
      console.warn(`[CORS] Blocked origin: ${origin}`);
      callback(new Error('Not allowed by CORS'));
    }
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
};

app.use(
  fileUpload({
    createParentPath: true,
  })
);
app.use(cors(corsOptions));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

const server = require("http").createServer(app);
const sessions = new Map();

ensureAuthBase();
migrateAuthFolders();
cleanEmptyAuthDirs();
setInterval(cleanEmptyAuthDirs, 60 * 60 * 1000);
restoreSessionsFromAuth();

/**
 * @typedef {Object} SessionData
 * @property {string} sessionId - Unique identifier untuk session
 * @property {object} sock - WhatsApp socket connection
 * @property {string|null} qr - QR code string
 * @property {boolean} qr_status - Status apakah QR code ready
 * @property {boolean} logged_in - Status login
 * @property {string} authDir - Directory untuk auth credentials
 * @property {object} store - In-memory store untuk messages
 */

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Generate auth directory path untuk session tertentu
 * @param {string} sessionId - Session identifier
 * @returns {string} Path ke auth directory
 */
function getAuthDir(sessionId) {
  return path.join(AUTH_DIR_BASE, sessionId);
}

/**
 * Clear auth credentials dari filesystem
 * @param {string} authDir - Path ke auth directory
 */
function clearAuth(authDir) {
  try {
    if (fs.existsSync(authDir)) {
      fs.removeSync(authDir);
    }
  } catch (error) {
    console.error(`[clearAuth] Error clearing ${authDir}:`, error.message);
  }
}

function ensureAuthBase() {
  try {
    fs.ensureDirSync(AUTH_DIR_BASE);
  } catch { }
}

function migrateAuthFolders() {
  try {
    const entries = fs.readdirSync(".");
    for (const name of entries) {
      const src = name;
      if (fs.existsSync(src) && fs.lstatSync(src).isDirectory()) {
        if (name.startsWith("auth_") || name.startsWith("auth_mdl_")) {
          let sessionId = name.replace(/^auth_mdl_/, "").replace(/^auth_/, "");
          const dest = path.join(AUTH_DIR_BASE, sessionId);
          if (!fs.existsSync(dest)) {
            try {
              fs.moveSync(src, dest, { overwrite: false });
            } catch { }
          }
        }
      }
    }
  } catch { }
}

function cleanEmptyAuthDirs() {
  try {
    fs.ensureDirSync(AUTH_DIR_BASE);
    const entries = fs.readdirSync(AUTH_DIR_BASE);
    for (const name of entries) {
      const dir = path.join(AUTH_DIR_BASE, name);
      try {
        if (fs.lstatSync(dir).isDirectory()) {
          const items = fs.readdirSync(dir);
          if (!items || items.length === 0) {
            fs.removeSync(dir);
          }
        }
      } catch { }
    }
  } catch { }
}

/**
 * Get session data by ID
 * @param {string} sessionId - Session identifier
 * @returns {SessionData|undefined} Session data atau undefined jika tidak ada
 */
function getSession(sessionId) {
  return sessions.get(sessionId);
}

function restoreSessionsFromAuth() {
  try {
    fs.ensureDirSync(AUTH_DIR_BASE);
    const entries = fs.readdirSync(AUTH_DIR_BASE);
    console.log(`[Restore] Found ${entries.length} potential sessions in ${AUTH_DIR_BASE}`);
    for (const name of entries) {
      const dir = path.join(AUTH_DIR_BASE, name);
      try {
        if (fs.lstatSync(dir).isDirectory()) {
          const creds = path.join(dir, "creds.json");
          if (fs.existsSync(creds) && !sessions.has(name)) {
            console.log(`[Restore] Restoring session: ${name}`);
            connectToWhatsApp(name).catch((err) => console.error(`[Restore] Failed to connect ${name}:`, err));
          } else {
            console.log(`[Restore] Skipping ${name}: creds exists=${fs.existsSync(creds)}, in_memory=${sessions.has(name)}`);
          }
        }
      } catch (e) { console.error(`[Restore] Error processing ${name}:`, e); }
    }
  } catch (e) { console.error("[Restore] Fatal error:", e); }
}

/**
 * Delete session dan cleanup resources
 * @param {string} sessionId - Session identifier
 * @returns {boolean} True jika berhasil delete, false jika session tidak ditemukan
 */
function deleteSession(sessionId) {
  const session = sessions.get(sessionId);
  const authDir = getAuthDir(sessionId);
  try {
    if (session?.sock) {
      session.sock.end();
    }
    if (session) {
      sessions.delete(sessionId);
    }
    clearAuth(authDir);
    console.log(`[${sessionId}] Session deleted successfully`);
    return true;
  } catch (error) {
    console.error(`[${sessionId}] Error deleting session:`, error.message);
    return false;
  }
}

/**
 * Send data ke webhook
 * @param {object} data - Data yang akan dikirim
 * @param {string} sessionId - Session identifier
 */
async function sendToWebhook(data, sessionId) {
  try {
    const response = await fetch(webhook, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...data,
        sessionId,
      }),
    });

    // Log response status
    console.log(`[${sessionId}] Webhook response status: ${response.status}`);

    // Get response body
    const responseBody = await response.text();
    console.log(`[${sessionId}] Webhook response body:`, responseBody);

    if (!response.ok) {
      console.error(`[${sessionId}] ❌ Webhook error: ${response.status}`);
    } else {
      console.log(`[${sessionId}] ✓ Webhook sent successfully`);
    }
  } catch (error) {
    console.error(`[${sessionId}] ❌ Failed to send webhook:`, error.message);
  }
}

// ============================================================================
// WHATSAPP CONNECTION
// ============================================================================

/**
 * Connect ke WhatsApp untuk session tertentu
 * @param {string} sessionId - Session identifier
 * @returns {Promise<SessionData>} Session data yang telah dibuat
 */
async function connectToWhatsApp(sessionId) {
  // Cleanup existing session if any to prevent conflict loops
  const existingSession = sessions.get(sessionId);
  if (existingSession) {
    console.log(`[${sessionId}] Closing existing session instance before reconnecting...`);
    try {
      if (existingSession.sock) existingSession.sock.end();
    } catch { }
    sessions.delete(sessionId);
  }

  const authDir = getAuthDir(sessionId);
  const { state, saveCreds } = await useMultiFileAuthState(authDir);
  const { version } = await fetchLatestBaileysVersion();

  const sock = makeWASocket({
    auth: state,
    logger: pino({ level: "silent" }),
    version,
    shouldIgnoreJid: (jid) => isJidBroadcast(jid),
  });
  sock.multi = true;

  // Initialize session object
  const sessionData = {
    sessionId,
    sock,
    qr: null,
    qr_status: false,
    logged_in: false,
    authDir,
  };

  sessions.set(sessionId, sessionData);

  // Handle connection updates
  sock.ev.on("connection.update", async (update) => {
    const { connection, lastDisconnect } = update;
    const currentSession = sessions.get(sessionId);

    if (connection === "close") {
      if (currentSession) {
        currentSession.logged_in = false;
      }

      const shouldReconnect =
        lastDisconnect?.error instanceof Boom
          ? lastDisconnect.error.output.statusCode !==
          DisconnectReason.loggedOut
          : false;

      const reason = new Boom(lastDisconnect?.error)?.output?.statusCode;

      switch (reason) {
        case DisconnectReason.badSession:
          console.log(`[${sessionId}] Bad Session File, Reconnecting`);
          await connectToWhatsApp(sessionId);
          break;

        case DisconnectReason.connectionClosed:
          console.log(`[${sessionId}] Connection closed, Reconnecting...`);
          await connectToWhatsApp(sessionId);
          break;

        case DisconnectReason.connectionLost:
          console.log(`[${sessionId}] Connection Lost, Reconnecting...`);
          await connectToWhatsApp(sessionId);
          break;

        case DisconnectReason.connectionReplaced:
          console.log(`[${sessionId}] Connection Replaced. Terminating this session to avoid conflict.`);
          // SYSTEM DO NOT RECONNECT HERE. 
          // This implies another instance (or a zombie instance of this same code) has taken over.
          if (currentSession) {
            sessions.delete(sessionId);
          }
          sock.end();
          break;

        case DisconnectReason.loggedOut:
          console.log(`[${sessionId}] Device Logged Out`);
          // keep session in memory but mark as logged out; do not reconnect automatically
          if (currentSession) {
            currentSession.logged_in = false;
            currentSession.qr = null;
            currentSession.qr_status = false;
          }
          break;

        case DisconnectReason.restartRequired:
          console.log(`[${sessionId}] Restart Required, Restarting...`);
          await connectToWhatsApp(sessionId);
          break;

        case DisconnectReason.timedOut:
          console.log(`[${sessionId}] Connection Timed Out, Reconnecting...`);
          await connectToWhatsApp(sessionId);
          break;

        default:
          if (shouldReconnect) {
            console.log(`[${sessionId}] Unknown error, Reconnecting...`);
            await connectToWhatsApp(sessionId);
          } else {
            console.log(`[${sessionId}] Connection closed: ${reason}`);
            sock.end();
          }
      }
    } else if (connection === "open") {
      if (currentSession) {
        currentSession.logged_in = true;
      }
      console.log(`[${sessionId}] ✓ Connection Ready!`);
    }

    // Handle QR Code
    if (update.qr) {
      if (currentSession) {
        currentSession.qr = update.qr;
        currentSession.qr_status = true;
        console.log(`[${sessionId}] QR Code received, please scan`);
      }
    } else if (!update.qr && currentSession) {
      currentSession.qr_status = false;
    }
  });

  // Handle events
  sock.ev.process(async (events) => {
    // Credentials update
    if (events["creds.update"]) {
      await saveCreds();
    }

    // Messages update
    if (events["messages.update"]) {
      const updates = events["messages.update"];

      for (const update of updates) {
        console.log(`[${sessionId}] Message update:`, JSON.stringify(update));
        console.log(`[${sessionId}] Webhook check - fromMe: ${update.key?.fromMe}, hasUpdate: ${!!update.update}`);

        // Send to webhook if fromMe=true (like index_old.js)
        if (update.key?.fromMe === true && update.update) {
          console.log(`[${sessionId}] ✓ Sending to webhook...`);
          // Send raw update to webhook (like index_old.js)
          try {
            const response = await fetch(webhook, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify(update), // Send raw update without modification
            });

            // Log response status
            console.log(`[${sessionId}] Webhook response status: ${response.status}`);

            // Get response body
            const responseBody = await response.text();
            console.log(`[${sessionId}] Webhook response body:`, responseBody);

            if (!response.ok) {
              console.error(`[${sessionId}] ❌ Webhook error: ${response.status}`);
            } else {
              console.log(`[${sessionId}] ✓ Webhook sent successfully`);
            }
          } catch (error) {
            console.error(`[${sessionId}] ❌ Failed to send webhook:`, error.message);
          }
        }
      }
    }
  });

  return sessionData;
}

// ============================================================================
// API ENDPOINTS
// ============================================================================

/**
 * POST /create-session
 * Membuat session WhatsApp baru
 */
app.post("/create-session", async (req, res) => {
  try {
    const { sessionId } = req.body;

    // Validation
    if (
      !sessionId ||
      typeof sessionId !== "string" ||
      sessionId.trim() === ""
    ) {
      return res.status(400).json({
        status: false,
        message: "sessionId is required and must be a non-empty string",
      });
    }

    // Check if session already exists
    if (sessions.has(sessionId)) {
      return res.status(400).json({
        status: false,
        message: `Session "${sessionId}" already exists`,
      });
    }

    // Create new session
    await connectToWhatsApp(sessionId);

    res.status(200).json({
      status: true,
      message: `Session "${sessionId}" created successfully`,
      sessionId,
    });
  } catch (err) {
    console.error("[create-session] Error:", err);
    res.status(500).json({
      status: false,
      message: err.message || "Internal server error",
    });
  }
});

/**
 * POST /reset-session
 * Reset session: hapus kredensial dan restart koneksi dengan sessionId yang sama
 */
app.post("/reset-session", async (req, res) => {
  try {
    const { sessionId } = req.body;

    if (
      !sessionId ||
      typeof sessionId !== "string" ||
      sessionId.trim() === ""
    ) {
      return res.status(400).json({
        status: false,
        message: "sessionId is required and must be a non-empty string",
      });
    }

    try {
      if (sessions.has(sessionId)) {
        deleteSession(sessionId);
      } else {
        const dir = getAuthDir(sessionId);
        if (fs.existsSync(dir)) clearAuth(dir);
      }
    } catch { }

    await connectToWhatsApp(sessionId);

    res.status(200).json({
      status: true,
      message: `Session "${sessionId}" reset successfully`,
      sessionId,
    });
  } catch (err) {
    console.error("[reset-session] Error:", err);
    res.status(500).json({
      status: false,
      message: err.message || "Internal server error",
    });
  }
});

/**
 * GET /list-sessions
 * List semua active sessions
 */
app.get("/list-sessions", (_req, res) => {
  try {
    const sessionList = Array.from(sessions.values()).map((session) => ({
      sessionId: session.sessionId,
      logged_in: session.logged_in,
      qr_status: session.qr_status,
      user: session.sock?.user || null,
    }));

    res.status(200).json({
      status: true,
      sessions: sessionList,
      total: sessionList.length,
    });
  } catch (err) {
    console.error("[list-sessions] Error:", err);
    res.status(500).json({
      status: false,
      message: err.message || "Internal server error",
    });
  }
});

/**
 * POST /delete-session
 * Delete session tertentu
 */
app.post("/delete-session", (req, res) => {
  try {
    const { sessionId } = req.body;

    // Validation
    if (!sessionId) {
      return res.status(400).json({
        status: false,
        message: "sessionId is required",
      });
    }

    const deleted = deleteSession(sessionId);

    if (deleted) {
      res.status(200).json({
        status: true,
        message: `Session "${sessionId}" deleted successfully`,
      });
    } else {
      res.status(404).json({
        status: false,
        message: `Session "${sessionId}" not found`,
      });
    }
  } catch (err) {
    console.error("[delete-session] Error:", err);
    res.status(500).json({
      status: false,
      message: err.message || "Internal server error",
    });
  }
});

/**
 * POST /cek-status
 * Cek status session (logged in atau QR ready)
 */
app.post("/cek-status", async (req, res) => {
  try {
    const { sessionId } = req.body;

    // Validation
    if (!sessionId) {
      return res.status(400).json({
        status: false,
        message: "sessionId is required",
      });
    }

    let session = getSession(sessionId);

    if (!session) {
      const dir = getAuthDir(sessionId);
      if (fs.existsSync(path.join(dir, "creds.json"))) {
        try {
          await connectToWhatsApp(sessionId);
          await new Promise((r) => setTimeout(r, 300));
          session = getSession(sessionId);
        } catch { }
      }
      if (!session) {
        return res.status(404).json({
          status: false,
          message: `Session "${sessionId}" not found`,
        });
      }
    }

    // Jika sudah login
    if (session.logged_in) {
      return res.status(200).json({
        status: true,
        logged_in: true,
        user: session.sock?.user || null,
      });
    }

    // Jika QR ready
    if (session.qr_status && session.qr) {
      try {
        const qrDataUrl = await qrcode.toDataURL(session.qr);
        return res.status(200).json({
          status: true,
          logged_in: false,
          qr_ready: true,
          qr_string: qrDataUrl,
        });
      } catch (qrError) {
        console.error(`[${sessionId}] QR generation error:`, qrError);
        return res.status(500).json({
          status: false,
          message: "Failed to generate QR code",
        });
      }
    }

    // QR belum ready
    return res.status(200).json({
      status: true,
      logged_in: false,
      qr_ready: false,
    });
  } catch (err) {
    console.error("[cek-status] Error:", err);
    res.status(500).json({
      status: false,
      message: err.message || "Internal server error",
    });
  }
});

/**
 * POST /send-message
 * Kirim pesan WhatsApp
 */
app.post("/send-message", async (req, res) => {
  try {
    const { sessionId, message, number } = req.body;

    // Validation
    if (!sessionId) {
      return res.status(500).json({
        status: false,
        response: "sessionId is required",
      });
    }

    if (!number || typeof number !== "string") {
      return res.status(500).json({
        status: false,
        response: "number is required and must be a string",
      });
    }

    if (!message || typeof message !== "string") {
      return res.status(500).json({
        status: false,
        response: "message is required and must be a string",
      });
    }

    console.log(`[send-message] Request: sessionId="${sessionId}", number="${number}"`);
    let session = getSession(sessionId);

    // Auto-recover session if it exists on disk but not in memory (e.g. after server restart)
    if (!session) {
      const dir = getAuthDir(sessionId);
      if (fs.existsSync(path.join(dir, "creds.json"))) {
        console.log(`[${sessionId}] Session found on disk, restoring...`);
        try {
          await connectToWhatsApp(sessionId);
          // Wait for connection to establish
          await new Promise((r) => setTimeout(r, 2000));
          session = getSession(sessionId);
        } catch (e) {
          console.error(`[${sessionId}] Restore failed:`, e.message);
        }
      }
    }

    if (!session) {
      console.error(`[send-message] ❌ TOKEN INVALID: Session "${sessionId}" not found`);
      console.log(`[send-message] Available sessions:`, Array.from(sessions.keys()));
      return res.status(500).json({
        status: false,
        response: `Session "${sessionId}" not found`,
      });
    }

    if (!session.logged_in) {
      console.error(`[send-message] ❌ TOKEN NOT LOGGED IN: Session "${sessionId}" exists but not logged in`);
      console.log(`[send-message] Session status:`, { logged_in: session.logged_in, qr_status: session.qr_status });
      // If we just restored, maybe it needs a bit more time or it's actually logged out
      return res.status(500).json({
        status: false,
        response: `Whatsapp disconnected`,
      });
    }

    console.log(`[send-message] ✓ Session "${sessionId}" validated, sending to ${number}...`);
    const cleanNumber = number.replace(/\D/g, "");
    const numberWA = `${cleanNumber}@s.whatsapp.net`;

    // Kirim pesan dengan timeout protection
    try {
      const sendPromise = session.sock.sendMessage(numberWA, { text: message });

      // Add timeout (30 seconds)
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Send timeout after 30s')), 30000)
      );

      const result = await Promise.race([sendPromise, timeoutPromise]);

      res.status(200).json({
        status: true,
        response: result,
      });
    } catch (sendErr) {
      console.error(`[${sessionId}] Send failed:`, sendErr.message);

      // Return error with status 500 (matching index_old.js)
      return res.status(500).json({
        status: false,
        response: sendErr.message
      });
    }
  } catch (err) {
    console.error("[send-message] Error:", err);
    res.status(500).json({
      status: false,
      response: err.message || "Failed to send message",
    });
  }
});

/**
 * GET /
 * Health check endpoint
 */
app.get("/", (_req, res) => {
  res.json({
    status: true,
    message: "WhatsApp Multi-Session Service is running",
    version: "2.0.0",
    active_sessions: sessions.size,
  });
});

// ============================================================================
// SERVER START
// ============================================================================

server.listen(PORT, () => {
  console.log("═══════════════════════════════════════════════════════");
  console.log("  WhatsApp Multi-Session Service");
  console.log("═══════════════════════════════════════════════════════");
  console.log(`  Port: ${PORT}`);
  console.log(`  Version: 2.0.0`);
  console.log("───────────────────────────────────────────────────────");
  console.log("  Available Endpoints:");
  console.log("  GET  /                - Health check");
  console.log("  POST /create-session  - Create new session");
  console.log("  GET  /list-sessions   - List all sessions");
  console.log("  POST /delete-session  - Delete a session");
  console.log("  POST /reset-session   - Reset session and restart");
  console.log("  POST /cek-status      - Check session status");
  console.log("  POST /send-message    - Send WhatsApp message");
  console.log("═══════════════════════════════════════════════════════");
});

// ============================================================================
// GRACEFUL SHUTDOWN
// ============================================================================

process.on("SIGINT", async () => {
  console.log("\nGracefully shutting down...");

  // Close all sessions without deleting auth folders
  for (const [sessionId, session] of sessions) {
    console.log(`Closing session: ${sessionId}`);
    try {
      session.sock?.end();
    } catch { }
    sessions.delete(sessionId);
  }

  // Close server
  server.close(() => {
    console.log("Server closed");
    process.exit(0);
  });
});

// ============================================================================
// UNHANDLED REJECTION HANDLER
// ============================================================================

/**
 * Handle unhandled promise rejections to prevent crashes
 * Specifically ignores "Bad MAC" errors from Baileys
 */
process.on('unhandledRejection', (err) => {
  if (err?.message?.includes('Bad MAC')) {
    console.warn('[WA] Bad MAC ignored');
    return;
  }
  console.error('[UnhandledRejection]', err);
});
