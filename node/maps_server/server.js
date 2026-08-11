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
/** Cadangan redirect: cepat, jangan makan waktu parser. */
const REDIRECT_TIMEOUT_MS = Number(process.env.REDIRECT_TIMEOUT_MS || 5000);

const FETCH_HEADERS = {
  'User-Agent':
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
  Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
  'Accept-Language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
};

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
  console.warn(
    '[maps_server] 401 unauthorized',
    req.method,
    req.path,
    'has_header=',
    Boolean(bearer),
    'has_query_token=',
    Boolean(q)
  );
  return res.status(401).json({
    ok: false,
    error: 'unauthorized',
    message:
      'Token maps_server tidak cocok. Samakan MAPS_SERVER_TOKEN di laundry/api dengan node/maps_server/.env lalu restart.',
  });
}

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error('timeout')), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

function validCoords(lat, lng) {
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
  if (lat === 0 && lng === 0) return null;
  return { lat, lng };
}

/**
 * Ambil lat/lng dari teks URL / HTML Google Maps.
 * Prioritas: @lat,lng → q=lat,lng → !3d!4d → place/search path.
 */
function extractCoordsFromText(text) {
  if (typeof text !== 'string' || text === '') return null;

  let m = text.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
  if (m) {
    const c = validCoords(Number(m[1]), Number(m[2]));
    if (c) return c;
  }

  m = text.match(/[?&]q=(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/i);
  if (m) {
    const c = validCoords(Number(m[1]), Number(m[2]));
    if (c) return c;
  }

  m = text.match(/[?&]query=(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/i);
  if (m) {
    const c = validCoords(Number(m[1]), Number(m[2]));
    if (c) return c;
  }

  m = text.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
  if (m) {
    const c = validCoords(Number(m[1]), Number(m[2]));
    if (c) return c;
  }

  m = text.match(/maps\/(?:place|search)\/(-?\d+\.\d+)\s*,\s*\+?(-?\d+\.\d+)/i);
  if (m) {
    const c = validCoords(Number(m[1]), Number(m[2]));
    if (c) return c;
  }

  return null;
}

function coordsFromEnvelope(envelope) {
  const loc = envelope && envelope.location;
  if (!loc || loc.status !== 'present' || !loc.value) {
    return null;
  }
  const coords = validCoords(Number(loc.value.latitude), Number(loc.value.longitude));
  if (!coords) return null;
  return {
    ...coords,
    source: loc.value.source || 'parser',
    accuracy: loc.value.accuracy || null,
  };
}

/**
 * Ikuti redirect short-link lewat header Location (tanpa unduh HTML Maps penuh).
 * Banyak short link cukup sampai URL @lat,lng.
 */
async function resolveViaRedirect(inputUrl) {
  let current = inputUrl;
  let finalUrl = inputUrl;

  for (let hop = 0; hop < 10; hop++) {
    const res = await fetch(current, {
      method: 'GET',
      redirect: 'manual',
      headers: FETCH_HEADERS,
    });

    const locHeader = res.headers.get('location');
    if (
      locHeader &&
      [301, 302, 303, 307, 308].includes(res.status)
    ) {
      current = new URL(locHeader, current).href;
      finalUrl = current;
      const fromLoc = extractCoordsFromText(current);
      if (fromLoc) {
        return { coords: fromLoc, finalUrl: current, source: 'redirect_url' };
      }
      // Buang body redirect agar socket tidak menggantung
      try {
        await res.arrayBuffer();
      } catch (_) {
        /* ignore */
      }
      continue;
    }

    finalUrl = res.url || current;
    let coords = extractCoordsFromText(finalUrl);
    if (coords) {
      try {
        await res.arrayBuffer();
      } catch (_) {
        /* ignore */
      }
      return { coords, finalUrl, source: 'redirect_url' };
    }

    // Cadangan ringan: cuplikan HTML kecil saja (bukan full page)
    try {
      const html = await res.text();
      const slice = html.slice(0, 80000);
      coords = extractCoordsFromText(slice);
      if (coords) {
        return { coords, finalUrl, source: 'redirect_html' };
      }
      const meta = slice.match(
        /(?:content=["']\d+;\s*url=|rel=["']canonical["']\s+href=["'])([^"'>\s]+)/i
      );
      if (meta && meta[1]) {
        const metaUrl = meta[1].replace(/&amp;/g, '&');
        coords = extractCoordsFromText(metaUrl);
        if (coords) {
          return { coords, finalUrl: metaUrl, source: 'redirect_meta' };
        }
      }
    } catch (_) {
      /* ignore body read errors */
    }
    break;
  }

  return { coords: null, finalUrl, source: null };
}

async function resolveViaParser(inputUrl) {
  const envelope = await withTimeout(
    unfurlGoogleMapsUrl(inputUrl, {
      timeoutMs: RESOLVE_TIMEOUT_MS,
    }),
    RESOLVE_TIMEOUT_MS + 500
  );

  if (!envelope || envelope.status === 'error') {
    return {
      coords: null,
      finalUrl:
        (envelope && envelope.resolution && envelope.resolution.resolvedUrl) ||
        (envelope && envelope.input && envelope.input.normalized) ||
        null,
      error: envelope,
      intent: null,
    };
  }

  const coords = coordsFromEnvelope(envelope);
  return {
    coords,
    finalUrl:
      (envelope.resolution && envelope.resolution.resolvedUrl) ||
      (envelope.input && envelope.input.normalized) ||
      inputUrl,
    intent: envelope.intent || null,
    error: coords ? null : envelope,
  };
}

function okPayload(inputUrl, coords, extra) {
  return {
    ok: true,
    lat: coords.lat,
    lng: coords.lng,
    latt: coords.lat,
    long: coords.lng,
    source: extra.source || null,
    accuracy: extra.accuracy || null,
    input_url: inputUrl,
    final_url: extra.finalUrl || inputUrl,
    intent: extra.intent || null,
  };
}

async function handleResolve(req, res) {
  const inputUrl = extractUrlFromRequest(req);
  if (!inputUrl) {
    return res.status(400).json({
      ok: false,
      error: 'url_required',
      message: 'URL Google Maps tidak terdeteksi di request',
    });
  }

  // 1) URL input sudah mengandung koordinat
  const local = extractCoordsFromText(inputUrl);
  if (local) {
    return res.json(okPayload(inputUrl, local, { source: 'url_local', finalUrl: inputUrl }));
  }

  let finalUrl = null;
  let lastError = null;
  let lastIntent = null;

  // 2) Cara lama dulu (google-maps-link-parser) — yang biasanya sudah jalan
  try {
    const viaParser = await resolveViaParser(inputUrl);
    finalUrl = viaParser.finalUrl || finalUrl;
    lastIntent = viaParser.intent || null;
    if (viaParser.coords) {
      return res.json(
        okPayload(inputUrl, viaParser.coords, {
          source: viaParser.coords.source || 'parser',
          accuracy: viaParser.coords.accuracy || null,
          finalUrl: viaParser.finalUrl || inputUrl,
          intent: viaParser.intent || null,
        })
      );
    }
    if (viaParser.error && viaParser.error.error) {
      lastError =
        viaParser.error.error.message || viaParser.error.error.code || lastError;
    } else if (viaParser.error) {
      lastError = 'no_coords';
    }
  } catch (err) {
    lastError = err && err.message ? err.message : 'parser_failed';
    console.error('[maps_server] parser resolve error:', lastError);
  }

  // 3) Cadangan tambahan: follow redirect + baca @lat,lng
  try {
    const viaRedirect = await withTimeout(
      resolveViaRedirect(inputUrl),
      REDIRECT_TIMEOUT_MS
    );
    finalUrl = viaRedirect.finalUrl || finalUrl;
    if (viaRedirect.coords) {
      return res.json(
        okPayload(inputUrl, viaRedirect.coords, {
          source: viaRedirect.source,
          finalUrl: viaRedirect.finalUrl,
        })
      );
    }
  } catch (err) {
    const msg = err && err.message ? err.message : 'redirect_failed';
    if (!lastError) lastError = msg;
    console.error('[maps_server] redirect resolve error:', msg);
  }

  const code = lastError === 'timeout' ? 'timeout' : 'no_coords';
  return res.status(code === 'timeout' ? 504 : 422).json({
    ok: false,
    error: code,
    message: lastError || 'URL resolved but no lat/lng found',
    input_url: inputUrl,
    final_url: finalUrl,
    intent: lastIntent,
  });
}

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    status: 'running',
    service: 'maps_server',
    auth_required: Boolean(AUTH_TOKEN),
    timestamp: new Date().toISOString(),
  });
});

app.get('/favicon.ico', (_req, res) => res.status(204).end());

/** Log SEMUA hit /resolve sebelum auth — kalau 401 dulu, dulu tidak ada log sama sekali. */
function logResolveHit(req, _res, next) {
  const preview =
    (req.body && (req.body.url || req.body.gmaps || req.body.link || req.body.text)) ||
    (req.query && (req.query.url || req.query.gmaps || req.query.link)) ||
    '';
  console.log(
    '[maps_server]',
    req.method,
    '/resolve',
    'auth_required=',
    Boolean(AUTH_TOKEN),
    'url=',
    String(preview).slice(0, 160)
  );
  next();
}

app.post('/resolve', logResolveHit, requireToken, async (req, res) => {
  return handleResolve(req, res);
});
app.get('/resolve', logResolveHit, requireToken, async (req, res) => {
  return handleResolve(req, res);
});

app.listen(PORT, HOST, () => {
  console.log('========================================');
  console.log('  Maps Server (Google Maps → lat/lng)');
  console.log('========================================');
  console.log(`  HTTP: http://${HOST}:${PORT}`);
  console.log(`  Auth: ${AUTH_TOKEN ? 'X-Maps-Token required' : 'open (set MAPS_SERVER_TOKEN)'}`);
  console.log('  POST /resolve  { "url": "https://maps.app.goo.gl/..." }');
  console.log('  GET  /health');
  console.log('  Resolve: local → parser (lama) → redirect+@lat,lng (baru)');
  console.log('========================================');
});
