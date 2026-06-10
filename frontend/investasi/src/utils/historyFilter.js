import { localDateISO } from "./format";

export const HISTORY_FILTER_OPTIONS = [
  { id: "today", label: "Hari ini" },
  { id: "yesterday", label: "Kemarin" },
  { id: "week", label: "Minggu ini" },
];

/** @returns {{ from: string, to: string } | null} */
export function historyFilterRange(filter) {
  const now = new Date();

  if (filter === "today") {
    const d = localDateISO(now);
    return { from: d, to: d };
  }

  if (filter === "yesterday") {
    const y = new Date(now);
    y.setDate(y.getDate() - 1);
    const d = localDateISO(y);
    return { from: d, to: d };
  }

  if (filter === "week") {
    const start = new Date(now);
    const dow = start.getDay();
    const diff = dow === 0 ? 6 : dow - 1;
    start.setDate(start.getDate() - diff);
    return { from: localDateISO(start), to: localDateISO(now) };
  }

  return null;
}

export function buildHistoryListQuery(filter, month) {
  const range = historyFilterRange(filter);
  if (range) {
    return `from=${range.from}&to=${range.to}`;
  }
  return `month=${month}`;
}

export function toggleHistoryFilter(current, next) {
  return current === next ? null : next;
}
