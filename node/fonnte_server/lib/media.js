const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const MEDIA_DIR = path.join(__dirname, '..', 'media');

function ensureMediaDir() {
  if (!fs.existsSync(MEDIA_DIR)) {
    fs.mkdirSync(MEDIA_DIR, { recursive: true });
  }
}

function extFromMime(mimetype) {
  const map = {
    'image/jpeg': 'jpg',
    'image/jpg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'video/mp4': 'mp4',
    'audio/ogg': 'ogg',
    'audio/mpeg': 'mp3',
    'audio/mp4': 'm4a',
    'application/pdf': 'pdf',
  };
  return map[String(mimetype || '').toLowerCase()] || 'bin';
}

/**
 * Simpan buffer media; kembalikan URL publik + metadata.
 */
function saveMediaBuffer(buffer, opts = {}) {
  ensureMediaDir();
  const ext = (opts.extension || extFromMime(opts.mimetype) || 'bin').replace(/^\./, '');
  const prefix = opts.inboxid ? `in_${opts.inboxid}` : crypto.randomBytes(8).toString('hex');
  const filename = `${prefix}.${ext}`;
  const fullPath = path.join(MEDIA_DIR, filename);
  fs.writeFileSync(fullPath, buffer);

  const base = String(process.env.MEDIA_PUBLIC_BASE_URL || '').replace(/\/$/, '');
  const url = base ? `${base}/media/${filename}` : `/media/${filename}`;

  return { url, filename, extension: ext, path: fullPath };
}

module.exports = {
  MEDIA_DIR,
  ensureMediaDir,
  extFromMime,
  saveMediaBuffer,
};
