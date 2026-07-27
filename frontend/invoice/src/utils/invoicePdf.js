import { jsPDF } from "jspdf";
import { formatDate, formatRupiah, formatUsd } from "./format";

const BRAND = "Nalju Digital Solutions (NDS)";

function money(value) {
  return `Rp ${formatRupiah(value)}`;
}

function moneyUsd(value) {
  return `$ ${formatUsd(value)}`;
}

function statusLabel(invoice) {
  if (invoice?.payment_status === "paid") return "LUNAS";
  if (invoice?.payment_status === "pending") return "MENUNGGU";
  if (invoice?.status === "cancelled") return "DIBATALKAN";
  return "BELUM BAYAR";
}

/**
 * Generate & download invoice PDF for customer print/save.
 * @param {object} invoice — payload dari PublicView/detail
 */
export function downloadInvoicePdf(invoice) {
  if (!invoice) return;

  const doc = new jsPDF({ unit: "mm", format: "a4" });
  const pageW = doc.internal.pageSize.getWidth();
  const margin = 18;
  const contentW = pageW - margin * 2;
  let y = 20;

  const brand = (invoice.issuer?.name || BRAND).trim() || BRAND;
  const invNo = invoice.invoice_number || "-";
  const title = (invoice.title || "").trim();

  // Header brand
  doc.setFont("helvetica", "bold");
  doc.setFontSize(16);
  doc.setTextColor(30, 30, 40);
  doc.text(brand, margin, y);
  y += 7;

  if (invoice.issuer?.phone || invoice.issuer?.address) {
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(100, 100, 110);
    if (invoice.issuer.phone) {
      doc.text(String(invoice.issuer.phone), margin, y);
      y += 4.5;
    }
    if (invoice.issuer.address) {
      const lines = doc.splitTextToSize(String(invoice.issuer.address), contentW * 0.55);
      doc.text(lines, margin, y);
      y += lines.length * 4.2;
    }
  }

  // Right-side invoice meta
  const metaX = margin + contentW * 0.58;
  let metaY = 20;
  doc.setFont("helvetica", "bold");
  doc.setFontSize(14);
  doc.setTextColor(30, 30, 40);
  doc.text("INVOICE", metaX, metaY);
  metaY += 6;
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9);
  doc.setTextColor(80, 80, 90);
  doc.text(invNo, metaX, metaY);
  metaY += 5;
  doc.text(`Status: ${statusLabel(invoice)}`, metaX, metaY);
  metaY += 5;
  doc.text(`Tanggal: ${formatDate(invoice.issue_date)}`, metaX, metaY);
  metaY += 5;
  doc.text(
    `Jatuh tempo: ${invoice.due_date ? formatDate(invoice.due_date) : "-"}`,
    metaX,
    metaY
  );

  y = Math.max(y, metaY) + 8;

  // Divider
  doc.setDrawColor(210, 210, 218);
  doc.setLineWidth(0.3);
  doc.line(margin, y, pageW - margin, y);
  y += 8;

  // Bill to
  doc.setFont("helvetica", "normal");
  doc.setFontSize(8);
  doc.setTextColor(120, 120, 130);
  doc.text("KEPADA", margin, y);
  y += 5;
  doc.setFont("helvetica", "bold");
  doc.setFontSize(11);
  doc.setTextColor(30, 30, 40);
  doc.text(invoice.customer_name || "-", margin, y);
  y += 5;

  if (invoice.customer_phone) {
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(90, 90, 100);
    doc.text(String(invoice.customer_phone), margin, y);
    y += 4.5;
  }
  if (invoice.customer_email) {
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(90, 90, 100);
    doc.text(String(invoice.customer_email), margin, y);
    y += 4.5;
  }

  if (title) {
    y += 3;
    doc.setFont("helvetica", "bold");
    doc.setFontSize(10);
    doc.setTextColor(30, 30, 40);
    const titleLines = doc.splitTextToSize(title, contentW);
    doc.text(titleLines, margin, y);
    y += titleLines.length * 5 + 2;
  }

  y += 4;

  // Table header
  const colDesc = margin;
  const colQty = margin + contentW * 0.58;
  const colPrice = margin + contentW * 0.72;
  const colAmt = pageW - margin;

  doc.setFillColor(245, 245, 248);
  doc.rect(margin, y - 4, contentW, 8, "F");
  doc.setFont("helvetica", "bold");
  doc.setFontSize(8);
  doc.setTextColor(90, 90, 100);
  doc.text("DESKRIPSI", colDesc, y);
  doc.text("QTY", colQty, y);
  doc.text("HARGA", colPrice, y);
  doc.text("JUMLAH", colAmt, y, { align: "right" });
  y += 8;

  const items = Array.isArray(invoice.items) ? invoice.items : [];
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9);
  doc.setTextColor(35, 35, 45);

  for (const item of items) {
    if (y > 270) {
      doc.addPage();
      y = 20;
    }

    const desc = String(item.description || "-");
    const descLines = doc.splitTextToSize(desc, contentW * 0.54);
    const rowH = Math.max(descLines.length * 4.2, 5);

    doc.text(descLines, colDesc, y);
    doc.text(String(item.quantity ?? 1), colQty, y);
    const priceLabel =
      item.unit_price_usd != null
        ? `${money(item.unit_price)} (${moneyUsd(item.unit_price_usd)})`
        : money(item.unit_price);
    doc.text(priceLabel, colPrice, y);
    const amtLabel =
      item.amount_usd != null
        ? `${money(item.amount ?? item.quantity * item.unit_price)}`
        : money(item.amount ?? item.quantity * item.unit_price);
    doc.text(amtLabel, colAmt, y, {
      align: "right",
    });

    y += rowH + 3;
    doc.setDrawColor(230, 230, 235);
    doc.setLineWidth(0.2);
    doc.line(margin, y - 2, pageW - margin, y - 2);
  }

  y += 4;

  // Totals
  const totalsX = margin + contentW * 0.55;
  const labelX = totalsX;
  const valueX = pageW - margin;

  const drawTotalRow = (label, value, bold = false) => {
    if (y > 275) {
      doc.addPage();
      y = 20;
    }
    doc.setFont("helvetica", bold ? "bold" : "normal");
    doc.setFontSize(bold ? 11 : 9);
    doc.setTextColor(bold ? 30 : 90, bold ? 30 : 90, bold ? 40 : 100);
    doc.text(label, labelX, y);
    doc.text(value, valueX, y, { align: "right" });
    y += bold ? 7 : 5.5;
  };

  drawTotalRow("Subtotal", money(invoice.subtotal));
  if (Number(invoice.tax_amount) > 0) {
    drawTotalRow(`Pajak (${invoice.tax_percent || 0}%)`, money(invoice.tax_amount));
  }
  if (invoice.total_usd != null && Number(invoice.total_usd) > 0) {
    drawTotalRow("Pedoman USD", moneyUsd(invoice.total_usd));
  }
  doc.setDrawColor(180, 180, 190);
  doc.setLineWidth(0.4);
  doc.line(labelX, y - 2, pageW - margin, y - 2);
  y += 2;
  drawTotalRow("TOTAL DIBAYAR", money(invoice.total), true);

  // Notes
  if (invoice.notes) {
    y += 6;
    if (y > 260) {
      doc.addPage();
      y = 20;
    }
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8);
    doc.setTextColor(120, 120, 130);
    doc.text("CATATAN", margin, y);
    y += 5;
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(60, 60, 70);
    const noteLines = doc.splitTextToSize(String(invoice.notes), contentW);
    doc.text(noteLines, margin, y);
    y += noteLines.length * 4.2 + 4;
  }

  // Footer
  const pageH = doc.internal.pageSize.getHeight();
  doc.setFont("helvetica", "normal");
  doc.setFontSize(8);
  doc.setTextColor(140, 140, 150);
  doc.text(`Diterbitkan oleh ${brand}`, margin, pageH - 12);
  doc.text("Terima kasih atas kepercayaan Anda.", margin, pageH - 8);

  const safeName = String(invNo).replace(/[^\w.-]+/g, "_");
  doc.save(`Invoice-${safeName}.pdf`);
}
