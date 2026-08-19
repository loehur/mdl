/**
 * CSV utilities for WaDesk Blast feature.
 *
 * - buildTemplateHeaders(params)   → string[]
 * - buildSampleCsvText(headers, params) → string (CSV text)
 * - downloadSampleCsv(templateName, params)
 * - parseCsv(text)                 → { headers: string[], rows: object[] }
 * - validateBlastRows(rows, params) → string[]  (error messages)
 * - normalizePhone(phone)          → string
 */

/**
 * Derive CSV column key from a param meta object returned by /WaDesk/Blast/csvHeaders.
 * Matches the PHP Blast::csvParamKey() logic.
 */
export function paramCsvKey(p) {
  return p.key; // already resolved by the API
}

/**
 * Build the list of CSV column headers for a given template.
 * @param {Array} params - from csvHeaders API: [{key, label, example, required, component}]
 * @returns {string[]}
 */
export function buildTemplateHeaders(params) {
  return ['phone', ...params.map((p) => p.key)];
}

/**
 * Build the CSV sample text (2 rows: header + example).
 * @param {Array} params
 * @returns {string}
 */
export function buildSampleCsvText(params) {
  const headers = buildTemplateHeaders(params);
  const exampleRow = [
    '62812xxxx',
    ...params.map((p) => p.example || p.label || ''),
  ];
  return [
    headers.join(','),
    exampleRow.map(csvEscape).join(','),
  ].join('\r\n') + '\r\n';
}

/**
 * Trigger a CSV file download in the browser.
 * @param {string} templateName
 * @param {Array}  params
 */
export function downloadSampleCsv(templateName, params) {
  const text = buildSampleCsvText(params);
  const blob = new Blob([text], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  const safeName = templateName.replace(/[^a-zA-Z0-9_-]/g, '_').substring(0, 60);
  a.href = url;
  a.download = `blast_${safeName}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

/**
 * Parse CSV text into { headers, rows }.
 * Handles quoted fields with commas and newlines.
 * @param {string} text
 * @returns {{ headers: string[], rows: Array<{[key:string]:string}> }}
 */
export function parseCsv(text) {
  const lines = splitCsvLines(text.replace(/\r\n/g, '\n').replace(/\r/g, '\n'));
  if (lines.length === 0) {
    return { headers: [], rows: [] };
  }

  const headers = parseCsvLine(lines[0]).map((h) => h.trim());
  const rows = [];

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim();
    if (line === '') continue;
    const values = parseCsvLine(line);
    const row = {};
    headers.forEach((h, idx) => {
      row[h] = values[idx] ?? '';
    });
    rows.push(row);
  }

  return { headers, rows };
}

/**
 * Validate parsed rows against template params.
 * @param {Array<{[key:string]:string}>} rows  - from parseCsv
 * @param {string[]} headers                   - from parseCsv
 * @param {Array}    params                    - from csvHeaders API
 * @returns {string[]} error messages (empty = valid)
 */
export function validateBlastRows(rows, headers, params) {
  const errors = [];

  // Check required headers
  if (!headers.includes('phone')) {
    errors.push('Kolom "phone" tidak ditemukan di CSV');
  }
  const expectedKeys = params.map((p) => p.key);
  const missingCols = expectedKeys.filter((k) => !headers.includes(k));
  if (missingCols.length > 0) {
    errors.push(`Kolom berikut tidak ditemukan di CSV: ${missingCols.join(', ')}`);
  }

  if (errors.length > 0) return errors; // header errors block row checks

  // Per-row validation (stop at first 10 errors)
  for (let i = 0; i < rows.length && errors.length < 10; i++) {
    const row = rows[i];
    const rowNum = i + 2; // 1-based, skip header

    const phone = normalizePhone(row['phone'] ?? '');
    if (phone.length < 9) {
      errors.push(`Baris ${rowNum}: phone tidak valid ("${row['phone']}")`);
    }

    for (const p of params) {
      const val = (row[p.key] ?? '').trim();
      if (p.required && val === '') {
        errors.push(`Baris ${rowNum}: kolom "${p.key}" (${p.label}) wajib diisi`);
        continue;
      }
      if (val === '') continue;
      const maxLen = Number(p.maxlength ?? 20) > 0 ? Number(p.maxlength ?? 20) : 20;
      if (val.length > maxLen) {
        errors.push(
          `Baris ${rowNum}: kolom "${p.key}" (${p.label}) maksimal ${maxLen} karakter (sekarang ${val.length})`
        );
      }
    }
  }

  return errors;
}

/**
 * Convert parseCsv rows into the API payload format: [{phone, params}].
 * @param {Array<{[key:string]:string}>} rows
 * @param {Array} params
 * @returns {Array<{phone:string, params:{[key:string]:string}}>}
 */
export function rowsToBlastPayload(rows, params) {
  const paramKeys = params.map((p) => p.key);
  return rows.map((row) => {
    const phone = normalizePhone(row['phone'] ?? '');
    const p = {};
    for (const key of paramKeys) {
      p[key] = row[key] ?? '';
    }
    return { phone, params: p };
  });
}

/**
 * Normalise phone number (strip non-digits, convert 08xx → 628xx).
 * Mirrors WaDeskController::normalizePhone.
 */
export function normalizePhone(phone) {
  let digits = phone.replace(/\D+/g, '');
  if (digits.startsWith('0')) {
    digits = '62' + digits.slice(1);
  }
  if (!digits.startsWith('62') && digits.length >= 9) {
    digits = '62' + digits.replace(/^0+/, '');
  }
  return digits;
}

// -----------------------------------------------------------------------
// Internal helpers
// -----------------------------------------------------------------------

function csvEscape(value) {
  const str = String(value ?? '');
  if (str.includes(',') || str.includes('"') || str.includes('\n')) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}

function parseCsvLine(line) {
  const result = [];
  let cur = '';
  let inQuotes = false;

  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (inQuotes) {
      if (ch === '"') {
        if (line[i + 1] === '"') {
          cur += '"';
          i++;
        } else {
          inQuotes = false;
        }
      } else {
        cur += ch;
      }
    } else if (ch === '"') {
      inQuotes = true;
    } else if (ch === ',') {
      result.push(cur);
      cur = '';
    } else {
      cur += ch;
    }
  }
  result.push(cur);
  return result;
}

function splitCsvLines(text) {
  // Split respecting quoted newlines
  const lines = [];
  let cur = '';
  let inQuotes = false;

  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (ch === '"') {
      if (inQuotes && text[i + 1] === '"') {
        cur += '""';
        i++;
      } else {
        inQuotes = !inQuotes;
        cur += ch;
      }
    } else if (ch === '\n' && !inQuotes) {
      lines.push(cur);
      cur = '';
    } else {
      cur += ch;
    }
  }
  if (cur !== '') lines.push(cur);
  return lines;
}
