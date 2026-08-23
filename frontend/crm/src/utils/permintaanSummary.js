/**
 * Normalisasi tampilan ringkasan permintaan (selaras PermintaanSummaryHelper PHP).
 * Berguna untuk data lama di DB yang belum dirapikan.
 */
export function formatPermintaanSummary(raw) {
  if (raw == null) return "";
  let text = String(raw).trim();
  if (!text) return "";

  text = text.replace(/^[io]-\s+/iu, "");
  text = text.replace(/\s+/g, " ").trim();
  text = text.replace(/^(baik|oke|ok|siap)[,.]?\s+/iu, "");
  text = text.replace(/^[,;.–—:\s-]+/u, "");
  text = text.replace(/\s*\/\s*/g, "; ");
  text = text.replace(/\s*;\s*/g, "; ");
  text = text.replace(/;\s*;/g, "; ");
  text = text.trim().replace(/[;]+$/g, "").trim();

  if (!text) return "";

  text = text.replace(/^tanya\b/iu, "Menanyakan");
  text = text.replace(/^minta\b/iu, "Meminta");
  text = text.replace(/^dulukan\b/iu, "Meminta dulukan");
  text = text.replace(/^ambil\b/iu, "Meminta ambil");
  text = text.replace(/;\s*tanya\b/giu, "; menanyakan");
  text = text.replace(/;\s*minta\b/giu, "; meminta");
  text = text.replace(/;\s*dulukan\b/giu, "; meminta dulukan");
  text = text.replace(/;\s*(\p{L})/gu, (_, c) => "; " + c.toUpperCase());

  if (/^\p{Ll}/u.test(text)) {
    text = text.charAt(0).toUpperCase() + text.slice(1);
  }

  if (!/[.!?]$/.test(text)) {
    text += ".";
  }

  return text;
}

export function normalizePermintaanItems(items) {
  if (!Array.isArray(items)) return [];
  return items.map((item) => {
    if (!item || typeof item !== "object") return item;
    const summary = formatPermintaanSummary(item.summary);
    return {
      ...item,
      summary: summary || item.summary || "",
    };
  });
}
