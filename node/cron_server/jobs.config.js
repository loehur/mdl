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
    id: 'wadesk-blast',
    description: 'Process WaDesk template blast queue',
    schedule: '2-59/7 * * * *', // setiap 7 menit, offset menit 02
    method: 'GET',
    url: '/Cron/WaDeskBlast/index?limit=20',
    enabled: true,
  },
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
    id: 'bca-kas-confirm',
    description: 'Konfirmasi kas BCA pending jika mutasi CR cocok (on-demand scrape)',
    schedule: '3-59/10 * * * *', // setiap 10 menit, offset menit 03
    method: 'GET',
    url: '/Cron/BcaKasConfirm/index',
    enabled: true,
  },
  {
    id: 'bca-qris-confirm',
    description: 'Sync transaksi QRIS merchant BCA + konfirmasi kas QRIS static pending',
    schedule: '4-59/14 * * * *', // setiap 14 menit, offset menit 04
    method: 'GET',
    url: '/Cron/BcaQrisConfirm/index',
    enabled: true,
  },
  {
    id: 'kas-unmatched-notify',
    description: 'Notify branch group for unmatched laundry kas BCA/QRIS',
    schedule: '5-59/11 * * * *', // setiap 11 menit, offset menit 05
    method: 'GET',
    url: '/Cron/KasUnmatchedNotify/index',
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
  {
    id: 'penetapan-gaji-laundry',
    description: 'Penetapan Gaji Laundry (bulan lalu)',
    schedule: '10 5 1 * *', // tanggal 1 tiap bulan jam 05:10
    method: 'GET',
    url: 'https://ml.nalju.com/Gaji/tetapkan',
    enabled: true,
  },
  {
    id: 'wa-queue',
    description: 'Resend / proses antrean WhatsApp (queue)',
    schedule: '0-59/5 * * * *', // setiap 5 menit, offset menit 00
    method: 'GET',
    url: '/Cron/ResendWAQueue',
    enabled: true,
  },
  {
    id: 'send-pending-notif',
    description: 'Kirim notifikasi pending laundry',
    schedule: '1-59/6 * * * *', // setiap 6 menit, offset menit 01
    method: 'GET',
    url: 'https://ml.nalju.com/Cron/send',
    enabled: true,
  },
  {
    id: 'pay-bill',
    description: 'Bayar / proses tagihan otomatis (PayBill)',
    schedule: '20 5 * * *', // setiap hari jam 05:20
    method: 'GET',
    url: '/Cron/PayBill',
    enabled: true,
  },
];
