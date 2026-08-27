const PHONE_PREFIX_LENGTH = 3;
const PHONE_SUFFIX_LENGTH = 7;

/**
 * Displays a phone number without exposing its middle digits.
 * Example: 6281268098300 -> 628***8098300
 */
export function maskPhoneNumber(phone) {
  const digits = String(phone ?? "").replace(/\D/g, "");

  if (digits.length <= PHONE_PREFIX_LENGTH + PHONE_SUFFIX_LENGTH) {
    return digits || String(phone ?? "");
  }

  return `${digits.slice(0, PHONE_PREFIX_LENGTH)}***${digits.slice(-PHONE_SUFFIX_LENGTH)}`;
}

/**
 * A conversation uses its WhatsApp number as a fallback name. Only mask that
 * fallback; an actual contact name remains readable.
 */
export function displayConversationName(conversation) {
  const name = String(conversation?.name ?? "").trim();
  const phone = String(conversation?.wa_number ?? "").trim();
  const normalizeDigits = (value) => value.replace(/\D/g, "");

  if (name && normalizeDigits(name) !== normalizeDigits(phone)) {
    return name;
  }

  return maskPhoneNumber(phone || name);
}
