const path = require('path');
const fs = require('fs');
const pino = require('pino');
const qrcode = require('qrcode-terminal');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  downloadMediaMessage,
  getContentType,
  jidNormalizedUser,
  fetchLatestBaileysVersion,
  Browsers,
} = require('@whiskeysockets/baileys');

const { nextInboxId } = require('./inbox');
const { saveMediaBuffer, extFromMime } = require('./media');
const { postWebhook } = require('./webhook');
const { jidToSender, deviceDisplayNumber, isGroupJid, isBroadcastJid, isLidJid, looksLikeMobileDigits } = require('./phone');
const { setLidPhone, getPhoneJidForLid } = require('./lid-map');

const AUTH_DIR = path.join(__dirname, '..', 'auth');
/**
 * WhatsApp kadang timeout saat sync awal `fetchProps`. Selama socket kemudian
 * berhasil open, ini tidak memengaruhi pengiriman atau webhook dan hanya
 * memenuhi error log. Error Baileys lain tetap diteruskan ke log.
 */
function isTransientInitQueryTimeout(args) {
  const detail = args.find((arg) => arg && typeof arg === 'object') || {};
  const message = args.find((arg) => typeof arg === 'string') || detail.msg || '';
  const errorMessage = detail.err?.message || '';

  return String(message).includes("unexpected error in 'init queries'")
    && errorMessage === 'Timed Out';
}

const logger = pino({
  // Default 'error': warn Baileys (decrypt MessageCounterError, Invalid mex,
  // stream error) itu noise rutin non-fatal — error asli tetap tampil.
  level: process.env.LOG_LEVEL || 'error',
  hooks: {
    logMethod(args, method) {
      if (isTransientInitQueryTimeout(args)) {
        return;
      }
      method.apply(this, args);
    },
  },
});

/** Log info hanya muncul saat DEBUG_FONNTE=1 — biar log tidak ramai (cukup error/warn). */
function debugLog(...args) {
  if (process.env.DEBUG_FONNTE === '1') {
    console.log(...args);
  }
}

/** @type {import('@whiskeysockets/baileys').WASocket | null} */
let sock = null;
let connectionState = 'close';
let lastQr = null;
let sockGeneration = 0;
/** @type {Map<string, { resolve: Function, reject: Function, timer: NodeJS.Timeout }>} */
const pendingOutStatus = new Map();

const SEEN_INBOUND_FILE = path.join(AUTH_DIR, 'seen_inbound_keys.json');
const UPSERT_DEBUG_FILE = path.join(__dirname, '..', 'data', 'upsert_debug.log');
const SEEN_INBOUND_MAX = 10000;
/** @type {Set<string>} */
const seenInboundKeys = new Set();
let seenInboundLoaded = false;
let seenInboundDirty = false;

function loadSeenInboundKeys() {
  if (seenInboundLoaded) return;
  seenInboundLoaded = true;
  try {
    if (fs.existsSync(SEEN_INBOUND_FILE)) {
      const raw = JSON.parse(fs.readFileSync(SEEN_INBOUND_FILE, 'utf8'));
      if (Array.isArray(raw)) {
        for (const k of raw) {
          if (typeof k === 'string' && k) seenInboundKeys.add(k);
        }
      }
    }
  } catch (_) {
    // ignore corrupt cache
  }
}

function persistSeenInboundKeys() {
  if (!seenInboundDirty) return;
  seenInboundDirty = false;
  try {
    ensureAuthDir();
    const arr = Array.from(seenInboundKeys).slice(-SEEN_INBOUND_MAX);
    fs.writeFileSync(SEEN_INBOUND_FILE, JSON.stringify(arr));
  } catch (err) {
    console.error('[baileys] persist seen inbound keys failed:', err.message || err);
  }
}

/** Dedup key: remoteJid + WA message id (stable across Baileys upsert retries). */
function inboundDedupKey(msg) {
  const id = msg?.key?.id;
  const jid = msg?.key?.remoteJid;
  if (!id || !jid) return null;
  return `${jid}|${id}`;
}

/** Returns true if this inbound was already processed. */
function markInboundSeen(msg) {
  loadSeenInboundKeys();
  const key = inboundDedupKey(msg);
  if (!key) return false;
  if (seenInboundKeys.has(key)) return true;
  seenInboundKeys.add(key);
  if (seenInboundKeys.size > SEEN_INBOUND_MAX) {
    const trim = seenInboundKeys.size - SEEN_INBOUND_MAX;
    const iter = seenInboundKeys.values();
    for (let i = 0; i < trim; i++) {
      const next = iter.next();
      if (next.done) break;
      seenInboundKeys.delete(next.value);
    }
  }
  seenInboundDirty = true;
  persistSeenInboundKeys();
  return false;
}

function debugUpsert(line) {
  if (process.env.DEBUG_UPSERT !== '1') return;
  try {
    const dir = path.dirname(UPSERT_DEBUG_FILE);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(UPSERT_DEBUG_FILE, `${new Date().toISOString()} ${line}\n`);
  } catch (_) {
    // ignore
  }
}

async function processDeviceOutbound(msg, type) {
  loadSeenInboundKeys();
  const dedupKey = inboundDedupKey(msg);
  if (dedupKey && seenInboundKeys.has(dedupKey)) {
    debugLog('[outbound-device] skip duplicate wamid=', msg.key?.id);
    return;
  }
  if (isTrackedOutgoing(msg.key?.id)) {
    debugLog('[outbound-device] skip tracked-api wamid=', msg.key?.id);
    return;
  }
  const outPayload = await buildInboundPayload(msg, { fromMe: true });
  if (!outPayload) {
    debugLog(
      '[outbound-device] skip null payload type=',
      type,
      'wamid=',
      msg.key?.id,
      'remoteJid=',
      msg.key?.remoteJid || '-',
      'hasMessage=',
      Boolean(msg.message)
    );
    return;
  }
  debugLog(
    '[outbound-device]',
    outPayload.sender,
    outPayload.sender_pn || '(no pn)',
    outPayload.type,
    outPayload.message?.slice(0, 60) || '(media)',
    'wamid=',
    outPayload.wa_message_id || '-'
  );
  const hookResult = await postWebhook(outPayload);
  if (!hookResult?.ok) {
    console.error('[outbound-device] webhook failed:', hookResult?.error || hookResult);
    return;
  }
  markInboundSeen(msg);
  if (outPayload.wa_message_id) {
    trackOutgoingMessageId(outPayload.wa_message_id, outPayload.wa_message_id);
  }
}

function ensureAuthDir() {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }
}

function authDirIsEmpty() {
  try {
    if (!fs.existsSync(AUTH_DIR)) return true;
    return fs.readdirSync(AUTH_DIR).length === 0;
  } catch (_) {
    return true;
  }
}

function getConnectionState() {
  return {
    connected: connectionState === 'open',
    state: connectionState,
    has_qr: Boolean(lastQr),
  };
}

function getLastQr() {
  return lastQr;
}

function getDeviceJid() {
  return sock?.user?.id ? jidNormalizedUser(sock.user.id) : null;
}

function getDeviceNumber() {
  const jid = getDeviceJid();
  if (jid) return deviceDisplayNumber(jid);
  if (connectionState === 'open') {
    const env = String(process.env.DEVICE_NUMBER || '').trim();
    if (env) return env;
  }
  return '';
}

async function sendStatusWebhook(fields) {
  const device = getDeviceNumber();
  await postWebhook({
    device,
    ...fields,
  });
}

/**
 * Resolve JID pengirim — LID → nomor HP bila memungkinkan.
 */
function resolveSenderJid(msg) {
  const remoteJid = msg.key?.remoteJid || '';
  if (!remoteJid || isBroadcastJid(remoteJid)) return null;

  const isGroup = isGroupJid(remoteJid);
  const participant = msg.key?.participant || msg.participant || remoteJid;
  let senderJid = isGroup ? participant : remoteJid;

  if (isBroadcastJid(senderJid)) return null;

  // Baileys 6.7.19+ — sender_pn dari server WA (sama seperti Fonnte cloud)
  const senderPn = msg.key?.senderPn || msg.key?.sender_pn
    || msg.key?.participantPn || msg.key?.participant_pn;
  const senderLidKey = msg.key?.senderLid || msg.key?.sender_lid
    || msg.key?.participantLid || msg.key?.participant_lid;

  if (senderPn) {
    const pn = String(senderPn);
    const lidRef = senderLidKey
      || (isLidJid(remoteJid) ? remoteJid : '')
      || (isLidJid(senderJid) ? senderJid : '');
    if (lidRef && isLidJid(lidRef)) setLidPhone(lidRef, pn);
    if (isLidJid(senderJid) || isLidJid(remoteJid) || !looksLikeMobileDigits(jidToSender(senderJid))) {
      senderJid = pn;
    }
  } else if (isLidJid(senderJid) || (!isGroup && isLidJid(remoteJid))) {
    const lidRef = senderLidKey
      || (isLidJid(remoteJid) ? remoteJid : senderJid);
    const mapped = getPhoneJidForLid(lidRef);
    if (mapped) senderJid = mapped;
  }

  return senderJid;
}

/**
 * Chat peer (lawan bicara) — untuk pesan fromMe (kirim dari HP).
 */
function resolvePeerJid(msg) {
  const remoteJid = msg.key?.remoteJid || '';
  if (!remoteJid || isBroadcastJid(remoteJid) || isGroupJid(remoteJid)) return null;

  const alt = msg.key?.remoteJidAlt || msg.key?.remote_jid_alt
    || msg.key?.participantPn || msg.key?.participant_pn
    || msg.key?.senderPn || msg.key?.sender_pn;
  if (alt && !isLidJid(alt) && !isBroadcastJid(alt)) {
    if (isLidJid(remoteJid)) setLidPhone(remoteJid, alt);
    return alt;
  }
  if (isLidJid(remoteJid)) {
    const mapped = getPhoneJidForLid(remoteJid);
    if (mapped) return mapped;
  }
  return remoteJid;
}

/**
 * Parse pesan masuk → payload webhook Fonnte.
 */
async function buildInboundPayload(msg, opts = {}) {
  const fromMe = Boolean(opts.fromMe);
  if (!msg.message) return null;
  if (Boolean(msg.key.fromMe) !== fromMe) return null;

  const type = getContentType(msg.message);
  if (!type || type === 'protocolMessage') return null;

  const senderJid = fromMe ? resolvePeerJid(msg) : resolveSenderJid(msg);
  if (!senderJid) return null;

  const inboxid = nextInboxId();
  const remoteJid = msg.key.remoteJid || '';
  const isGroup = isGroupJid(remoteJid);
  let sender = jidToSender(senderJid);
  if (!sender && !isGroup) return null;

  const senderLidRaw = msg.key?.senderLid || msg.key?.sender_lid
    || msg.key?.participantLid || msg.key?.participant_lid
    || (isLidJid(remoteJid) ? remoteJid : '')
    || (isLidJid(msg.key?.participant || '') ? (msg.key?.participant || '') : '');
  const senderLid = senderLidRaw || (isLidJid(senderJid) ? senderJid : '');

  // fromMe ke kontak LID: enrich nomor HP dari map agar PHP tidak perlu baca file di luar open_basedir
  if (fromMe && !looksLikeMobileDigits(sender)) {
    const lidRef = isLidJid(remoteJid) ? remoteJid : (senderLid || '');
    if (lidRef) {
      const mappedPeer = getPhoneJidForLid(lidRef);
      if (mappedPeer) sender = jidToSender(mappedPeer);
    }
  }

  const resolvedPn = looksLikeMobileDigits(sender) ? sender : '';
  const pushName = msg.pushName || msg.verifiedBizName || '';
  const timestamp = Number(msg.messageTimestamp || Math.floor(Date.now() / 1000));

  let messageText = '';
  let url = '';
  let filename = '';
  let extension = '';
  let location = '';
  let waType = 'text';

  const inner = msg.message[type] || {};

  if (type === 'conversation') {
    messageText = String(msg.message.conversation || '');
  } else if (type === 'extendedTextMessage') {
    messageText = String(inner.text || '');
  } else if (type === 'imageMessage') {
    waType = 'image';
    messageText = String(inner.caption || '');
    try {
      const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      const saved = saveMediaBuffer(buffer, {
        inboxid,
        mimetype: inner.mimetype,
        extension: extFromMime(inner.mimetype),
      });
      url = saved.url;
      filename = saved.filename;
      extension = saved.extension;
    } catch (err) {
      console.error('[baileys] download image failed:', err.message || err);
    }
  } else if (type === 'videoMessage') {
    waType = 'video';
    messageText = String(inner.caption || '');
    try {
      const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      const saved = saveMediaBuffer(buffer, {
        inboxid,
        mimetype: inner.mimetype,
        extension: extFromMime(inner.mimetype) || 'mp4',
      });
      url = saved.url;
      filename = saved.filename;
      extension = saved.extension;
    } catch (err) {
      console.error('[baileys] download video failed:', err.message || err);
    }
  } else if (type === 'audioMessage' || type === 'pttMessage') {
    waType = 'audio';
    try {
      const audioMsg = type === 'pttMessage' ? msg.message.pttMessage : inner;
      const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      const saved = saveMediaBuffer(buffer, {
        inboxid,
        mimetype: audioMsg?.mimetype || 'audio/ogg',
        extension: extFromMime(audioMsg?.mimetype) || 'ogg',
      });
      url = saved.url;
      filename = saved.filename;
      extension = saved.extension;
    } catch (err) {
      console.error('[baileys] download audio failed:', err.message || err);
    }
  } else if (type === 'documentMessage') {
    waType = 'document';
    messageText = String(inner.caption || inner.title || '');
    try {
      const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      const ext = inner.fileName ? path.extname(inner.fileName).replace(/^\./, '') : extFromMime(inner.mimetype);
      const saved = saveMediaBuffer(buffer, {
        inboxid,
        mimetype: inner.mimetype,
        extension: ext || 'bin',
      });
      url = saved.url;
      filename = inner.fileName || saved.filename;
      extension = saved.extension;
    } catch (err) {
      console.error('[baileys] download document failed:', err.message || err);
    }
  } else if (type === 'stickerMessage') {
    waType = 'sticker';
    try {
      const buffer = await downloadMediaMessage(msg, 'buffer', {}, { logger, reuploadRequest: sock.updateMediaMessage });
      const saved = saveMediaBuffer(buffer, {
        inboxid,
        mimetype: inner.mimetype || 'image/webp',
        extension: 'webp',
      });
      url = saved.url;
      filename = saved.filename;
      extension = saved.extension;
    } catch (err) {
      console.error('[baileys] download sticker failed:', err.message || err);
    }
  } else if (type === 'locationMessage') {
    waType = 'location';
    const lat = inner.degreesLatitude;
    const lng = inner.degreesLongitude;
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      location = `${lat},${lng}`;
      messageText = inner.name ? String(inner.name) : `📍 ${location}`;
    }
  } else {
    messageText = String(inner.caption || inner.text || '');
    if (!messageText) {
      messageText = `[${type}]`;
    }
  }

  const device = getDeviceNumber();
  const senderLidStr = String(senderLid || '');

  const waMessageId = msg.key?.id ? String(msg.key.id) : '';

  return {
    choices: [],
    device,
    extension,
    filename,
    inboxid,
    wa_message_id: waMessageId || undefined,
    from_me: fromMe || undefined,
    direction: fromMe ? 'out' : undefined,
    isforwarded: Boolean(msg.message?.extendedTextMessage?.contextInfo?.isForwarded),
    isgroup: isGroup,
    location,
    memberlid: isGroup ? senderLidStr : '',
    message: messageText,
    mode: senderLidStr.includes('@lid') ? 'lid' : 'pn',
    name: pushName,
    pengirim: sender,
    pesan: messageText,
    pollname: '',
    pushname: pushName,
    quick: false,
    sender,
    sender_pn: resolvedPn || undefined,
    senderlid: senderLidStr,
    text: 'non-button message',
    timestamp,
    type: waType,
    url,
    username: device,
    member: isGroup ? sender : '',
  };
}

async function handleIncomingMessages({ messages, type }) {
  debugUpsert(`upsert type=${type} count=${messages.length} fromMe=${messages.filter((m) => m.key?.fromMe).length}`);
  for (const msg of messages) {
    try {
      if (msg.key?.fromMe) {
        // Pesan keluar dari HP (multi-device) sering type=append, bukan notify
        if (type !== 'notify' && type !== 'append') continue;
        await processDeviceOutbound(msg, type);
        continue;
      }
      if (type !== 'notify') continue;
      const remoteJid = msg.key?.remoteJid || '';
      if (isGroupJid(remoteJid)) {
        debugLog('[inbound] skip group wamid=', msg.key?.id);
        continue;
      }
      const payload = await buildInboundPayload(msg);
      if (!payload) continue;
      if (markInboundSeen(msg)) {
        debugLog('[inbound] skip duplicate wamid=', msg.key?.id);
        continue;
      }
      debugLog(
        '[inbound]',
        payload.sender,
        payload.type,
        payload.message?.slice(0, 60) || '(media)',
        'inboxid=',
        payload.inboxid,
        'wamid=',
        payload.wa_message_id || '-'
      );
      await postWebhook(payload);
    } catch (err) {
      console.error('[inbound] error:', err.message || err);
    }
  }
}

function isTrackedOutgoing(waKeyId) {
  if (!waKeyId) return false;
  const id = String(waKeyId);
  for (const entry of pendingOutStatus.values()) {
    if (entry.waKeyId === id) return true;
  }
  return false;
}

function trackOutgoingMessageId(fonnteId, waKeyId) {
  if (!fonnteId || !waKeyId) return;
  const timer = setTimeout(() => pendingOutStatus.delete(String(fonnteId)), 3600000);
  pendingOutStatus.set(String(fonnteId), { waKeyId: String(waKeyId), timer });
}

async function handleMessageUpdates(updates) {
  for (const update of updates) {
    const key = update.key;
    if (!key || !key.fromMe) continue;
    const waKeyId = key.id;
    if (!waKeyId) continue;

    for (const [fonnteId, entry] of pendingOutStatus.entries()) {
      if (entry.waKeyId !== waKeyId) continue;
      const status = update.update?.status;
      if (status === undefined || status === null) continue;

      // proto.WebMessageInfo.Status: PENDING=1, SERVER_ACK=2, DELIVERY_ACK=3, READ=4, PLAYED=5
      let state = 'sent';
      if (status >= 4) state = 'read';
      else if (status === 3) state = 'delivered';
      else if (status >= 2) state = 'sent';

      await sendStatusWebhook({
        id: fonnteId,
        status: state,
        state,
        stateid: waKeyId,
      });
      if (status >= 4) {
        clearTimeout(entry.timer);
        pendingOutStatus.delete(String(fonnteId));
      }
    }
  }
}

/** True saat logout/restart — cegah auto-reconnect socket lama. */
let preventReconnect = false;

/** Cegah double socket saat restart cepat. */
let isStarting = false;
let restartTimer = null;

function clearRestartTimer() {
  if (restartTimer) {
    clearTimeout(restartTimer);
    restartTimer = null;
  }
}

function scheduleRestart(delayMs, reason) {
  if (preventReconnect || isStarting) return;
  clearRestartTimer();
  restartTimer = setTimeout(() => {
    restartTimer = null;
    if (connectionState === 'open' || lastQr) return;
    console.warn('[fonnte_server] restart Baileys:', reason);
    startBaileys().catch(console.error);
  }, delayMs);
}

async function destroySocket() {
  const old = sock;
  sock = null;
  if (!old) return;
  try {
    old.ev.removeAllListeners('connection.update');
    old.ev.removeAllListeners('creds.update');
    old.ev.removeAllListeners('messages.upsert');
    old.ev.removeAllListeners('messages.update');
  } catch (_) {
    /* ignore */
  }
  try {
    old.end(undefined);
  } catch (_) {
    /* ignore */
  }
}

async function logoutBaileys() {
  preventReconnect = true;
  clearRestartTimer();
  connectionState = 'close';
  lastQr = null;
  sockGeneration += 1;

  if (sock) {
    try {
      await sock.logout();
    } catch (_) {
      /* ignore */
    }
  }
  await destroySocket();

  try {
    if (fs.existsSync(AUTH_DIR)) {
      for (const name of fs.readdirSync(AUTH_DIR)) {
        fs.rmSync(path.join(AUTH_DIR, name), { recursive: true, force: true });
      }
    }
    ensureAuthDir();
  } catch (err) {
    console.error('[fonnte_server] logout cleanup failed:', err.message || err);
  }

  preventReconnect = false;
  await new Promise((r) => setTimeout(r, 800));
  await startBaileys();
  return { status: true, message: 'Session reset — scan QR baru' };
}

async function startBaileys() {
  if (isStarting) return;
  isStarting = true;
  clearRestartTimer();

  try {
    await destroySocket();
    sockGeneration += 1;
    const generation = sockGeneration;

    ensureAuthDir();
    const { version } = await fetchLatestBaileysVersion();
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

    connectionState = 'connecting';
    lastQr = null;

    const socket = makeWASocket({
      version,
      auth: state,
      logger,
      browser: Browsers.ubuntu('Chrome'),
      syncFullHistory: false,
      markOnlineOnConnect: false,
      generateHighQualityLinkPreview: false,
    });

    sock = socket;

    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', async (update) => {
      if (generation !== sockGeneration) return;

      const { connection, lastDisconnect, qr } = update;
      if (qr) {
        lastQr = qr;
        connectionState = 'connecting';
        clearRestartTimer();
        debugLog('[fonnte_server] QR ready — scan via Tools → WhatsApp');
        qrcode.generate(qr, { small: true });
      }
      if (connection === 'connecting') {
        connectionState = 'connecting';
      }
      if (connection === 'open') {
        connectionState = 'open';
        lastQr = null;
        clearRestartTimer();
        const num = getDeviceNumber();
        debugLog('[fonnte_server] WhatsApp connected', num || socket?.user?.id || '');
        await sendStatusWebhook({ status: 'connect' });
      }
      if (connection === 'close') {
        if (generation !== sockGeneration) return;

        connectionState = 'close';
        lastQr = null;
        const code = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = code === DisconnectReason.loggedOut;
        const needsQr = authDirIsEmpty();
        const shouldReconnect = !preventReconnect && !loggedOut;
        console.warn(
          '[fonnte_server] connection closed',
          code,
          shouldReconnect ? 'reconnecting…' : needsQr ? 'need QR' : 'logged out'
        );
        await sendStatusWebhook({ status: 'disconnect' });

        await destroySocket();

        if (shouldReconnect) {
          scheduleRestart(3000, 'disconnect');
        } else if (needsQr && !preventReconnect) {
          scheduleRestart(1500, 'logged out / empty auth');
        }
      }
    });

    socket.ev.on('messages.upsert', handleIncomingMessages);
    socket.ev.on('messages.update', handleMessageUpdates);

    socket.ev.on('chats.phoneNumberShare', ({ lid, jid }) => {
      if (lid && jid) setLidPhone(lid, jid);
    });

    if (authDirIsEmpty()) {
      scheduleRestart(25000, 'QR timeout watchdog');
    }
  } catch (err) {
    console.error('[fonnte_server] startBaileys failed:', err.message || err);
    connectionState = 'close';
    scheduleRestart(5000, 'start error');
  } finally {
    isStarting = false;
  }
}

/**
 * Kirim pesan (Fonnte /send).
 * @returns {Promise<{status:boolean, id?:string, reason?:string, detail?:object}>}
 */
async function sendViaBaileys({ jid, message, url, filename, inboxid }) {
  if (!sock || connectionState !== 'open') {
    return { status: false, reason: 'WhatsApp not connected' };
  }
  if (!jid) {
    return { status: false, reason: 'Invalid target' };
  }

  const text = String(message || '').trim();
  const mediaUrl = String(url || '').trim();
  let content = {};

  if (mediaUrl) {
    const lower = mediaUrl.toLowerCase();
    if (/\.(jpg|jpeg|png|webp)(\?|$)/i.test(lower)) {
      content = { image: { url: mediaUrl }, caption: text || undefined };
    } else if (/\.(mp4|mov)(\?|$)/i.test(lower)) {
      content = { video: { url: mediaUrl }, caption: text || undefined };
    } else if (/\.(mp3|ogg|m4a|aac)(\?|$)/i.test(lower)) {
      content = {
        audio: { url: mediaUrl },
        mimetype: 'audio/mpeg',
        ptt: false,
      };
    } else {
      content = {
        document: { url: mediaUrl },
        fileName: filename || path.basename(mediaUrl.split('?')[0]) || 'file',
        caption: text || undefined,
      };
    }
  } else if (text) {
    content = { text };
  } else {
    return { status: false, reason: 'message or url required' };
  }

  if (inboxid && content.text === undefined && text) {
    content.contextInfo = { ...(content.contextInfo || {}), stanzaId: String(inboxid) };
  }

  try {
    const sent = await sock.sendMessage(jid, content);
    const fonnteId = String(Date.now()) + Math.floor(Math.random() * 1000);
    const waKeyId = sent?.key?.id;
    trackOutgoingMessageId(fonnteId, waKeyId);

    await sendStatusWebhook({
      id: fonnteId,
      status: 'sent',
      state: 'sent',
      stateid: waKeyId || fonnteId,
    });

    return {
      status: true,
      id: fonnteId,
      stateid: waKeyId || null,
      detail: sent,
      requestid: fonnteId,
    };
  } catch (err) {
    console.error('[send] failed:', err.message || err);
    return { status: false, reason: err.message || 'send failed' };
  }
}

/**
 * Daftar semua group yang diikuti perangkat (untuk endpoint /groups).
 * @returns {Promise<{status:boolean, groups?:Array, reason?:string}>}
 */
async function listGroupsBaileys() {
  if (!sock || connectionState !== 'open') {
    return { status: false, reason: 'WhatsApp not connected' };
  }
  try {
    const meta = await sock.groupFetchAllParticipating();
    const groups = Object.keys(meta || {}).map((jid) => {
      const g = meta[jid] || {};
      return {
        id: jid,
        name: g.subject || '',
        desc: g.desc || '',
        size: Array.isArray(g.participants) ? g.participants.length : 0,
        owner: g.owner || '',
        announce: Boolean(g.announce),
        restrict: Boolean(g.restrict),
      };
    }).sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
    return { status: true, groups };
  } catch (err) {
    console.error('[groups] failed:', err.message || err);
    return { status: false, reason: err.message || 'fetch groups failed' };
  }
}

module.exports = {
  startBaileys,
  sendViaBaileys,
  logoutBaileys,
  getConnectionState,
  getLastQr,
  getDeviceNumber,
  getDeviceJid,
  listGroupsBaileys,
};
