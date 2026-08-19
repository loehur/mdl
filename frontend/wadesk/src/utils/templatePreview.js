/** Shared template preview — mirrors PHP YCloud::buildFilledPreview / renderPreview. */

export function paramKey(p) {
  if (p.param_name) return String(p.param_name);
  const component = String(p.component || "body").toLowerCase();
  return `${component}_${p.param_index}`;
}

export function escapeRegExp(s) {
  return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function textParamsOnly(params) {
  return (params || []).filter((p) => {
    const c = String(p.component || "body").toLowerCase();
    return c === "body" || c === "header";
  });
}

/** True if body_preview already contains {{name}} or {{index}} for this param. */
export function placeholderInPreview(text, def) {
  const name = String(def.param_name || "").trim();
  if (name && new RegExp(`\\{\\{\\s*${escapeRegExp(name)}\\s*\\}\\}`).test(text)) {
    return true;
  }
  const idx = Number(def.param_index) || 0;
  if (idx > 0 && new RegExp(`\\{\\{\\s*${idx}\\s*\\}\\}`).test(text)) {
    return true;
  }
  return false;
}

export function renderPreview(preview, named = {}, indexed = {}) {
  let text = String(preview || "");
  if (!text) return "";

  for (const [name, value] of Object.entries(named)) {
    if (!name) continue;
    text = text.replace(
      new RegExp(`\\{\\{\\s*${escapeRegExp(name)}\\s*\\}\\}`, "g"),
      String(value)
    );
  }

  for (const [idx, value] of Object.entries(indexed)) {
    text = text.replace(
      new RegExp(`\\{\\{\\s*${Number(idx)}\\s*\\}\\}`, "g"),
      String(value)
    );
  }

  return text;
}

export function buildPreviewMapsFromValues(params, paramValues) {
  const named = {};
  const indexed = {};
  for (const p of params || []) {
    const key = paramKey(p);
    const val = paramValues[key] ?? "";
    const name = String(p.param_name || "").trim();
    const idx = Number(p.param_index) || 0;
    if (name) named[name] = val;
    if (idx > 0) indexed[idx] = val;
  }
  return { named, indexed };
}

/**
 * Fill body_preview with param values (named + positional {{1}}).
 * Prepends missing header placeholders only when neither name nor index appears in text.
 */
export function buildFilledPreview(preview, paramDefs, named = {}, indexed = {}) {
  let text = String(preview || "");
  const missing = [];

  for (const def of textParamsOnly(paramDefs)) {
    if (placeholderInPreview(text, def)) continue;

    let token = String(def.param_name || "").trim();
    if (!token) {
      const idx = Number(def.param_index) || 0;
      if (idx <= 0) continue;
      token = String(idx);
    }
    missing.push(`{{${token}}}`);
  }

  if (missing.length) {
    const synthetic = [...new Set(missing)].join(" ");
    text = text ? `${synthetic}\n\n${text}` : synthetic;
  }

  const filled = renderPreview(text, named, indexed);
  return filled || text;
}
