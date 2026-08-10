require('dotenv').config();

const express = require('express');
const cors = require('cors');
const {
  isGoogleMapsUrl,
  unfurlGoogleMapsUrl,
} = require('google-maps-link-parser');

const app = express();
app.use(express.json({ limit: '32kb' }));
app.use(express.urlencoded({ extended: true }));
app.use(cors());

const PORT = Number(process.env.PORT || 3020);
const HOST = process.env.HOST || '0.0.0.0';
const AUTH_TOKEN = String(process.env.MAPS_SERVER_TOKEN || '').trim();
const RESOLVE_TIMEOUT_MS = Number(process.env.RESOLVE_TIMEOUT_MS || 10000);

function extractUrlCandidate(raw) {
  if (typeof raw !== 'string' || raw.trim() === '') return '';
  const text = raw.trim();
  const m = text.match(
    /https?:\/\/(?:maps\.app\.goo\.gl|goo\.gl\/maps|maps\.google\.[^\s]+|www\.google\.[^\s]*\/maps|google\.[^\s]*\/maps)[^\s<>"']*/i
  );
  if (m) {
    return m[0].replace(/[),.;]+$/, '');
  }
  if (/^https?:\/\//i.test(text) && isGoogleMapsUrl(text)) {
    return text;
  }
  return '';
}

function extractUrlFromRequest(req) {
  const body = req.body && typeof req.body === 'object' ? req.body : {};
  const q = req.query || {};
  const candidates = [body.url, body.gmaps, body.link, body.text, q.url, q.gmaps, q.link];
  for (const c of candidates) {
    const found = extractUrlCandidate(c);
    if (found) return found;
  }
  return '';
}

function requireToken(req, res, next) {
  if (!AUTH_TOKEN) {
    return next();
  }
  const header = String(req.headers['x-maps-token'] || req.headers['authorization'] || '');
  const bearer = header.toLowerCase().startsWith('bearer ')
    ? header.slice(7).trim()
    : header.trim();
  const q = String(req.query.token || '');
  if (bearer === AUTH_TOKEN || q === AUTH_TOKEN) {
    return next();
  }
  return res.status(401).json({ ok: false, error: 'unauthorized' });
}

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error('timeout')), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

function coordsFromEnvelope(envelope) {
  const loc = envelope && envelope.location;
  if (!loc || loc.status !== 'present' || !loc.value) {
    return null;
  }
  const lat = Number(loc.value.latitude);
  const lng = Number(loc.value.longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) {
    return null;
  }
  return {
    lat,
    lng,
    source: loc.value.source || null,
    accuracy: loc.value.accuracy || null,
  };
}

async function handleResolve(req, res) {
  const inputUrl = extractUrlFromRequest(req);
  if (!inputUrl) {
    return res.status(400).json({ ok: false, error: 'url_required' });
  }

  try {
    const envelope = await withTimeout(
      unfurlGoogleMapsUrl(inputUrl, {
        timeoutMs: RESOLVE_TIMEOUT_MS,
      }),
      RESOLVE_TIMEOUT_MS + 500
    );

    if (!envelope || envelope.status === 'error') {
      return res.status(422).json({
        ok: false,
        error: (envelope && envelope.error && envelope.error.code) || 'resolve_failed',
        message: (envelope && envelope.error && envelope.error.message) || null,
        input_url: inputUrl,
      });
    }

    const coords = coordsFromEnvelope(envelope);
    if (!coords) {
      return res.status(422).json({
        ok: false,
        error: 'no_coords',
        message: 'URL resolved but no lat/lng found',
        input_url: inputUrl,
        final_url: (envelope.resolution && envelope.resolution.resolvedUrl)
          || (envelope.input && envelope.input.normalized)
          || null,
        intent: envelope.intent || null,
      });
    }

    return res.json({
      ok: true,
      lat: coords.lat,
      lng: coords.lng,
      latt: coords.lat,
      long: coords.lng,
      source: coords.source,
      accuracy: coords.accuracy,
      input_url: inputUrl,
      final_url: (envelope.resolution && envelope.resolution.resolvedUrl)
        || (envelope.input && envelope.input.normalized)
        || inputUrl,
      intent: envelope.intent || null,
    });
  } catch (err) {
    const msg = err && err.message ? err.message : 'unknown';
    const code = msg === 'timeout' ? 'timeout' : 'exception';
    console.error('[maps_server] resolve error:', msg);
    return res.status(code === 'timeout' ? 504 : 500).json({
      ok: false,
      error: code,
      message: msg,
      input_url: inputUrl,
    });
  }
}

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    status: 'running',
    service: 'maps_server',
    timestamp: new Date().toISOString(),
  });
});

app.get('/favicon.ico', (_req, res) => res.status(204).end());

/**
 * POST /resolve  Body: { url | gmaps | text }
 * GET  /resolve?url=...
 * Header opsional: X-Maps-Token (wajib jika MAPS_SERVER_TOKEN di-set)
 */
app.post('/resolve', requireToken, handleResolve);
app.get('/resolve', requireToken, handleResolve);

app.listen(PORT, HOST, () => {
  console.log('========================================');
  console.log('  Maps Server (Google Maps → lat/lng)');
  console.log('========================================');
  console.log(`  HTTP: http://${HOST}:${PORT}`);
  console.log(`  Auth: ${AUTH_TOKEN ? 'X-Maps-Token required' : 'open (set MAPS_SERVER_TOKEN)'}`);
  console.log('  POST /resolve  { "url": "https://maps.app.goo.gl/..." }');
  console.log('  GET  /health');
  console.log('========================================');
});
