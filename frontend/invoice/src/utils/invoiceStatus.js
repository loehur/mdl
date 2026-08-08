export function isInvoiceOverdue(inv, today = new Date()) {
  if (!inv || inv.status === "cancelled") return false;
  if (inv.payment_status === "paid") return false;
  if (!inv.due_date) return false;

  const due = String(inv.due_date).slice(0, 10);
  const y = today.getFullYear();
  const m = String(today.getMonth() + 1).padStart(2, "0");
  const d = String(today.getDate()).padStart(2, "0");
  const todayStr = `${y}-${m}-${d}`;

  return due < todayStr;
}

export function invoiceStatusLabel(inv) {
  if (inv?.status === "cancelled") return "Dibatalkan";
  if (inv?.payment_status === "paid") return "Lunas";
  if (isInvoiceOverdue(inv)) return "Telat Bayar";
  if (inv?.payment_status === "pending") return "Menunggu";
  return "Belum Bayar";
}

export function invoiceStatusChipClass(inv) {
  if (inv?.status === "cancelled") return "chip-cancelled";
  if (inv?.payment_status === "paid") return "chip-in";
  return "chip-out";
}
