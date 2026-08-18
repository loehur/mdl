const COUNTRY_CODE = String(process.env.COUNTRY_CODE || '62').replace(/\D/g, '') || '62';

function digitsOnly(value) {
  return String(value || '').replace(/\D/g, '');
}

function isGroupJid(value) {
  return /@g\.us$/i.test(String(value || '').trim());
}

/**
 * Fonnte target → Baileys JID.
 * @param {string} target 62812… atau 0812… atau …@g.us
 */
function targetToJid(target) {
  const raw = String(target || '').trim();
  if (!raw) return null;
  if (isGroupJid(raw)) return raw;
  if (raw.includes('@')) return raw;

  let digits = digitsOnly(raw);
  if (digits.length < 8) return null;

  if (digits.startsWith('0')) {
    digits = COUNTRY_CODE + digits.slice(1);
  } else if (digits.length <= 11 && !digits.startsWith(COUNTRY_CODE)) {
    digits = COUNTRY_CODE + digits;
  }

  return `${digits}@s.whatsapp.net`;
}

/**
 * Baileys JID / participant → nomor Fonnte webhook (628…).
 */
function jidToSender(jid) {
  const raw = String(jid || '');
  if (!raw) return '';
  if (isGroupJid(raw)) return raw;
  const user = raw.split('@')[0].split(':')[0];
  const digits = digitsOnly(user);
  if (!digits) return '';
  if (digits.startsWith('0')) return COUNTRY_CODE + digits.slice(1);
  return digits;
}

/**
 * Nomor device untuk field webhook `device` (0…).
 */
function deviceDisplayNumber(jidOrPhone) {
  const sender = jidToSender(jidOrPhone);
  if (!sender) return process.env.DEVICE_NUMBER || '';
  if (sender.startsWith(COUNTRY_CODE)) {
    return '0' + sender.slice(COUNTRY_CODE.length);
  }
  return sender;
}

module.exports = {
  COUNTRY_CODE,
  digitsOnly,
  isGroupJid,
  targetToJid,
  jidToSender,
  deviceDisplayNumber,
};
