const path = require('path');
const fs = require('fs');

const MAP_FILE = path.join(__dirname, '..', 'data', 'lid_phone_map.json');
const MAP_MIRROR = String(process.env.LID_MAP_MIRROR_FILE || '').trim()
  || path.join(__dirname, '..', '..', '..', 'api', 'data', 'lid_phone_map.json');
/** @type {Map<string, string>} lid JID → phone JID (@s.whatsapp.net) */
const map = new Map();

function normalizeJid(value) {
  const raw = String(value || '').trim();
  if (!raw) return '';
  return raw.includes('@') ? raw : `${raw.replace(/\D/g, '')}@s.whatsapp.net`;
}

function loadFromFile(filePath) {
  if (!filePath || !fs.existsSync(filePath)) return;
  try {
    const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    if (!data || typeof data !== 'object') return;
    for (const [lid, phone] of Object.entries(data)) {
      const lidJid = normalizeJid(lid);
      const phoneJid = normalizeJid(phone);
      if (lidJid && phoneJid) map.set(lidJid, phoneJid);
    }
  } catch (err) {
    console.warn('[lid-map] load failed:', filePath, err.message || err);
  }
}

function loadLidMap() {
  loadFromFile(MAP_FILE);
  loadFromFile(MAP_MIRROR);
}

function writeMapFile(filePath) {
  if (!filePath) return;
  try {
    const dir = path.dirname(filePath);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    const obj = Object.fromEntries(map.entries());
    fs.writeFileSync(filePath, JSON.stringify(obj, null, 0));
  } catch (err) {
    console.warn('[lid-map] save failed:', filePath, err.message || err);
  }
}

function saveLidMap() {
  writeMapFile(MAP_FILE);
  if (MAP_MIRROR && path.resolve(MAP_MIRROR) !== path.resolve(MAP_FILE)) {
    writeMapFile(MAP_MIRROR);
  }
}

function setLidPhone(lidJid, phoneJid) {
  const lid = normalizeJid(lidJid);
  const phone = normalizeJid(phoneJid);
  if (!lid || !phone || !lid.includes('@lid')) return;
  if (map.get(lid) === phone) return;
  map.set(lid, phone);
  saveLidMap();
  if (process.env.DEBUG_FONNTE === '1') {
    console.log('[lid-map]', lid, '→', phone);
  }
}

function getPhoneJidForLid(lidJid) {
  const lid = String(lidJid || '').trim();
  if (!lid) return null;
  const key = lid.includes('@') ? lid : `${lid.replace(/\D/g, '')}@lid`;
  return map.get(key) || null;
}

loadLidMap();

module.exports = {
  setLidPhone,
  getPhoneJidForLid,
  loadLidMap,
};
