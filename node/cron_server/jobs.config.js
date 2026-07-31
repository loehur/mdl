/**
 * Daftar job cron universal.
 * Tambah job baru di sini tanpa mengubah server.js.
 *
 * schedule: ekspresi cron (node-cron), zona waktu dari env TZ
 * url: absolute atau relatif terhadap API_BASE
 * method: GET | POST (default GET)
 * enabled: false untuk menonaktifkan sementara
 */
module.exports = [
  {
    id: 'invoice-recurring',
    description: 'Generate invoice dari recurring_bills yang jatuh tempo',
    schedule: '0 1 * * *', // setiap hari jam 01:00
    method: 'GET',
    url: '/Cron/InvoiceRecurring/index',
    enabled: true,
  },
  {
    id: 'invoice-due-reminder',
    description: 'Kirim WA template reminder invoice H-3 s/d H',
    schedule: '0 8 * * *', // setiap hari jam 08:00
    method: 'GET',
    url: '/Cron/InvoiceDueReminder/index',
    enabled: true,
  },
  {
    id: 'clean-kas-qris',
    description: 'Cleanup QRIS pending > 1 jam (hapus kosong / sync Tokopay)',
    schedule: '*/11 * * * *', // setiap 11 menit
    method: 'GET',
    url: '/Cron/CleanKas/index',
    enabled: true,
  },
  {
    id: 'rekap-snapshot-bulanan',
    description: 'Snapshot rekap laundry bulan lalu per cabang operasional (untuk fee jaga malam)',
    schedule: '15 3 1 * *', // tanggal 1 tiap bulan jam 03:15 (hindari 01:00, 08:00, dan */11)
    method: 'GET',
    url: '/Cron/RekapSnapshot/index',
    enabled: true,
  },
];
