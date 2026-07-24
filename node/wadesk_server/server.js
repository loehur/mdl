/**
 * WaDesk realtime server — WebSocket + HTTP /incoming push from PHP
 * Port default 3010 (CRM wa_server uses 3003)
 */
const WebSocket = require('ws');
const express = require('express');
const http = require('http');
const cors = require('cors');
const path = require('path');
const dotenv = require('dotenv');

dotenv.config({ path: path.resolve(__dirname, '.env') });

const app = express();
app.use(express.json({ limit: '1mb' }));
app.use(cors({ origin: true, credentials: true }));

const PORT = Number(process.env.PORT || 3010);

/** @type {Map<string, Set<import('ws').WebSocket>>} */
const clients = new Map();

function clientKey(tenantId, userId) {
  return `t${tenantId}:u${userId}`;
}

function teamRoom(tenantId, teamId) {
  return `t${tenantId}:team${teamId}`;
}

function adminRoom(tenantId) {
  return `t${tenantId}:admin`;
}

/** @type {Map<string, Set<import('ws').WebSocket>>} */
const rooms = new Map();

function joinRoom(room, ws) {
  if (!rooms.has(room)) rooms.set(room, new Set());
  rooms.get(room).add(ws);
}

function leaveAllRooms(ws) {
  for (const set of rooms.values()) {
    set.delete(ws);
  }
}

function broadcastRoom(room, payload) {
  const set = rooms.get(room);
  if (!set) return 0;
  const data = JSON.stringify(payload);
  let n = 0;
  for (const ws of set) {
    if (ws.readyState === WebSocket.OPEN) {
      ws.send(data);
      n++;
    }
  }
  return n;
}

function fanoutEvent(payload) {
  const tenantId = payload.tenant_id;
  const teamId = payload.team_id;
  if (!tenantId) return;

  const msg = { ...payload, ts: Date.now() };
  let sent = broadcastRoom(adminRoom(tenantId), msg);
  if (teamId != null) {
    sent += broadcastRoom(teamRoom(tenantId, teamId), msg);
  }
  console.log(`[push] type=${payload.type} tenant=${tenantId} team=${teamId} sent=${sent}`);
}

app.get('/', (_req, res) => {
  res.json({ ok: true, service: 'wadesk_server', clients: clients.size, rooms: rooms.size });
});

app.post('/incoming', (req, res) => {
  try {
    const body = req.body || {};
    fanoutEvent(body);
    res.json({ success: true });
  } catch (e) {
    console.error('[incoming]', e);
    res.status(500).json({ success: false, error: String(e.message || e) });
  }
});

const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

wss.on('connection', (ws, req) => {
  try {
    const url = new URL(req.url || '/', `http://${req.headers.host}`);
    const tenantId = url.searchParams.get('tenant_id');
    const userId = url.searchParams.get('user_id');
    const role = url.searchParams.get('role') || 'agent';
    const teamId = url.searchParams.get('team_id');

    if (!tenantId || !userId) {
      ws.close(4001, 'tenant_id and user_id required');
      return;
    }

    ws.meta = {
      tenant_id: Number(tenantId),
      user_id: Number(userId),
      role,
      team_id: teamId ? Number(teamId) : null,
    };

    const key = clientKey(ws.meta.tenant_id, ws.meta.user_id);
    if (!clients.has(key)) clients.set(key, new Set());
    clients.get(key).add(ws);

    if (role === 'admin') {
      joinRoom(adminRoom(ws.meta.tenant_id), ws);
    } else if (ws.meta.team_id) {
      joinRoom(teamRoom(ws.meta.tenant_id, ws.meta.team_id), ws);
    }

    ws.send(JSON.stringify({ type: 'connected', meta: ws.meta }));
    console.log(`[ws] connect ${key} role=${role} team=${ws.meta.team_id}`);

    ws.on('message', (raw) => {
      try {
        const msg = JSON.parse(String(raw));
        if (msg.type === 'ping') {
          ws.send(JSON.stringify({ type: 'pong' }));
        }
      } catch (_) {
        /* ignore */
      }
    });

    ws.on('close', () => {
      leaveAllRooms(ws);
      const set = clients.get(key);
      if (set) {
        set.delete(ws);
        if (set.size === 0) clients.delete(key);
      }
      console.log(`[ws] close ${key}`);
    });
  } catch (e) {
    console.error('[ws] connection error', e);
    ws.close();
  }
});

setInterval(() => {
  const ping = JSON.stringify({ type: 'ping' });
  for (const set of clients.values()) {
    for (const ws of set) {
      if (ws.readyState === WebSocket.OPEN) ws.send(ping);
    }
  }
}, 30000);

server.listen(PORT, () => {
  console.log(`[wadesk_server] listening on :${PORT}`);
});
