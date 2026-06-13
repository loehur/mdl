export function invoiceStatusLabel(inv) {
  if (inv?.status === "cancelled") return "Dibatalkan";
  if (inv?.payment_status === "paid") return "Lunas";
  if (inv?.payment_status === "pending") return "Menunggu";
  return "Belum Bayar";
}

export function invoiceStatusChipClass(inv) {
  if (inv?.status === "cancelled") return "chip-cancelled";
  if (inv?.payment_status === "paid") return "chip-in";
  return "chip-out";
}
