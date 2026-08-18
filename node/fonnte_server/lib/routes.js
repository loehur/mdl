const express = require('express');
const multer = require('multer');
const { targetToJid } = require('./phone');
const {
  sendViaBaileys,
  logoutBaileys,
  getConnectionState,
  getLastQr,
  getDeviceNumber,
} = require('./baileys');

const TOKEN = String(process.env.FONNTE_TOKEN || '').trim();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 10 * 1024 * 1024 } });

function readToken(req) {
  const auth = String(req.headers.authorization || '').trim();
  if (auth) return auth;
  return String(req.body?.token || req.query?.token || '').trim();
}

function requireToken(req, res, next) {
  if (!TOKEN) {
    console.warn('[auth] FONNTE_TOKEN not set — API terbuka (dev only)');
    return next();
  }
  const provided = readToken(req);
  if (provided !== TOKEN) {
    return res.status(401).json({ status: false, reason: 'unauthorized' });
  }
  return next();
}

function normalizeBody(req) {
  return { ...(req.body || {}), ...(req.query || {}) };
}

function mountFonnteRoutes(app) {
  /**
   * Fonnte-compatible: POST /send
   * Body form/json: target, message, url, filename, inboxid, delay, typing, countryCode
   */
  app.post('/send', requireToken, upload.single('file'), async (req, res) => {
    try {
      const body = normalizeBody(req);
      const target = String(body.target || '').trim();
      const message = String(body.message ?? body.text ?? '');
      let url = String(body.url || '').trim();

      if (!url && req.file) {
        const base = String(process.env.MEDIA_PUBLIC_BASE_URL || '').replace(/\/$/, '');
        const ext = (req.file.originalname || 'file.bin').split('.').pop() || 'bin';
        const { saveMediaBuffer } = require('./media');
        const saved = saveMediaBuffer(req.file.buffer, { extension: ext, mimetype: req.file.mimetype });
        url = saved.url;
        if (!base) {
          url = saved.url;
        }
      }

      const jid = targetToJid(target);
      const result = await sendViaBaileys({
        jid,
        message,
        url,
        filename: body.filename,
        inboxid: body.inboxid,
      });

      if (!result.status) {
        return res.status(502).json(result);
      }
      return res.json(result);
    } catch (err) {
      console.error('[POST /send]', err);
      return res.status(500).json({ status: false, reason: err.message || 'internal error' });
    }
  });

  /**
   * Fonnte-compatible: POST /device
   */
  app.post('/device', requireToken, (_req, res) => {
    const conn = getConnectionState();
    const device = getDeviceNumber();
    return res.json({
      status: conn.connected,
      connected: conn.connected,
      device,
      name: device,
      package: 'self-hosted-baileys',
      quota: 'unlimited',
      messages: conn.connected ? 'online' : 'offline',
      state: conn.state,
    });
  });

  app.get('/health', (_req, res) => {
    const conn = getConnectionState();
    res.json({
      ok: true,
      service: 'fonnte_server',
      whatsapp: conn,
      device: getDeviceNumber(),
      webhook: Boolean(process.env.WEBHOOK_URL),
    });
  });

  app.get('/qr', requireToken, (_req, res) => {
    const qr = getLastQr();
    if (!qr) {
      const conn = getConnectionState();
      if (conn.connected) {
        return res.json({ ok: true, connected: true, device: getDeviceNumber() });
      }
      return res.status(404).json({ ok: false, message: 'QR belum tersedia — tunggu beberapa detik atau reset sesi' });
    }
    return res.json({ ok: true, qr });
  });

  app.post('/logout', requireToken, async (_req, res) => {
    try {
      const result = await logoutBaileys();
      return res.json({ ok: true, ...result });
    } catch (err) {
      console.error('[POST /logout]', err);
      return res.status(500).json({ ok: false, reason: err.message || 'logout failed' });
    }
  });
}

module.exports = { mountFonnteRoutes, requireToken };
