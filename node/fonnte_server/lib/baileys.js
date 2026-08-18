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
} = require('@whiskeysockets/baileys');

const { nextInboxId } = require('./inbox');
const { saveMediaBuffer, extFromMime } = require('./media');
const { postWebhook } = require('./webhook');
const { jidToSender, deviceDisplayNumber, isGroupJid } = require('./phone');

const AUTH_DIR = path.join(__dirname, '..', 'auth');
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

/** @type {import('@whiskeysockets/baileys').WASocket | null} */
let sock = null;
let connectionState = 'close';
let lastQr = null;
/** @type {Map<string, { resolve: Function, reject: Function, timer: NodeJS.Timeout }>} */
const pendingOutStatus = new Map();

function ensureAuthDir() {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
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
  const env = String(process.env.DEVICE_NUMBER || '').trim();
  if (env) return env;
  const jid = getDeviceJid();
  return jid ? deviceDisplayNumber(jid) : '';
}

async function sendStatusWebhook(fields) {
  const device = getDeviceNumber();
  await postWebhook({
    device,
    ...fields,
  });
}

/**
 * Parse pesan masuk → payload webhook Fonnte.
 */
async function buildInboundPayload(msg) {
  if (!msg.message || msg.key.fromMe) return null;

  const type = getContentType(msg.message);
  if (!type || type === 'protocolMessage') return null;

  const inboxid = nextInboxId();
  const remoteJid = msg.key.remoteJid || '';
  const isGroup = isGroupJid(remoteJid);
  const participant = msg.key.participant || msg.participant || remoteJid;
  const senderJid = isGroup ? participant : remoteJid;
  const sender = jidToSender(senderJid);
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
  const senderLid = String(senderJid || '');

  return {
    choices: [],
    device,
    extension,
    filename,
    inboxid,
    isforwarded: Boolean(msg.message?.extendedTextMessage?.contextInfo?.isForwarded),
    isgroup: isGroup,
    location,
    memberlid: isGroup ? senderLid : '',
    message: messageText,
    mode: senderLid.includes('@lid') ? 'lid' : 'pn',
    name: pushName,
    pengirim: sender,
    pesan: messageText,
    pollname: '',
    pushname: pushName,
    quick: false,
    sender,
    senderlid: senderLid,
    text: 'non-button message',
    timestamp,
    type: waType,
    url,
    username: device,
    member: isGroup ? sender : '',
  };
}

async function handleIncomingMessages({ messages, type }) {
  if (type !== 'notify') return;
  for (const msg of messages) {
    try {
      const payload = await buildInboundPayload(msg);
      if (!payload) continue;
      console.log(
        '[inbound]',
        payload.sender,
        payload.type,
        payload.message?.slice(0, 60) || '(media)',
        'inboxid=',
        payload.inboxid
      );
      await postWebhook(payload);
    } catch (err) {
      console.error('[inbound] error:', err.message || err);
    }
  }
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

      let state = 'sent';
      if (status >= 3) state = 'read';
      else if (status >= 2) state = 'delivered';
      else if (status >= 1) state = 'sent';

      await sendStatusWebhook({
        id: fonnteId,
        status: state,
        state,
        stateid: waKeyId,
      });
    }
  }
}

/** True saat logout manual — cegah auto-reconnect sebelum start ulang. */
let preventReconnect = false;

async function logoutBaileys() {
  preventReconnect = true;
  connectionState = 'close';
  lastQr = null;

  if (sock) {
    try {
      await sock.logout();
    } catch (_) {
      /* ignore */
    }
    try {
      sock.end(undefined);
    } catch (_) {
      /* ignore */
    }
    sock = null;
  }

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
  setTimeout(() => startBaileys().catch(console.error), 1500);
  return { status: true, message: 'Session reset — scan QR baru' };
}

async function startBaileys() {
  ensureAuthDir();
  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

  sock = makeWASocket({
    auth: state,
    logger,
    printQRInTerminal: false,
    syncFullHistory: false,
    markOnlineOnConnect: false,
    generateHighQualityLinkPreview: false,
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;
    if (qr) {
      lastQr = qr;
      console.log('\n[fonnte_server] Scan QR WhatsApp:\n');
      qrcode.generate(qr, { small: true });
    }
    if (connection === 'connecting') {
      connectionState = 'connecting';
    }
    if (connection === 'open') {
      connectionState = 'open';
      lastQr = null;
      const num = getDeviceNumber();
      console.log('[fonnte_server] WhatsApp connected', num || sock?.user?.id || '');
      await sendStatusWebhook({ status: 'connect' });
    }
    if (connection === 'close') {
      connectionState = 'close';
      const code = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = !preventReconnect && code !== DisconnectReason.loggedOut;
      console.warn('[fonnte_server] connection closed', code, shouldReconnect ? 'reconnecting…' : 'logged out');
      await sendStatusWebhook({ status: 'disconnect' });
      if (shouldReconnect) {
        setTimeout(() => startBaileys().catch(console.error), 3000);
      } else {
        sock = null;
      }
    }
  });

  sock.ev.on('messages.upsert', handleIncomingMessages);
  sock.ev.on('messages.update', handleMessageUpdates);
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
    // quote reply via contextInfo when inboxid provided (best-effort)
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
      detail: sent,
      requestid: fonnteId,
    };
  } catch (err) {
    console.error('[send] failed:', err.message || err);
    return { status: false, reason: err.message || 'send failed' };
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
};
