<?php

namespace App\Controllers\CMS;

use App\Core\Controller;

/**
 * Quick Replies Controller
 * Manages quick reply templates for CMS chat
 */
class QuickReply extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * Get all quick replies
     * GET /cms/quick-replies
     */
    public function getAll()
    {
        try {
            // For now, return static data
            // You can move this to database later
            $quickReplies = $this->getQuickRepliesData();
            
            $this->success($quickReplies, 'OK');
            
        } catch (\Throwable $e) {
            $this->error('Failed to fetch quick replies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Static quick replies data
     * You can expand this or move to database
     */
    private function getQuickRepliesData()
    {
        return [
            [
                'id' => 1,
                'shortcut' => '/salam',
                'title' => 'Salam Pembuka',
                'message' => "Halo kak! 👋\nTerima kasih sudah menghubungi *MDL Laundry*.\nAda yang bisa kami bantu?"
            ],
            [
                'id' => 2,
                'shortcut' => '/tutup',
                'title' => 'Penutup',
                'message' => "Terima kasih sudah menggunakan layanan kami! 🙏\nJika ada pertanyaan lain, jangan ragu untuk menghubungi kami.\n\nSalam hangat,\n*MDL Laundry* ✨"
            ],
            [
                'id' => 3,
                'shortcut' => '/promo',
                'title' => 'Info Promo',
                'message' => "🎉 *PROMO BULAN INI* 🎉\n\n✅ Diskon 20% untuk pelanggan baru\n✅ Gratis antar jemput min. 5kg\n✅ Cuci Setrika mulai Rp 7.000/kg\n\nYuk segera order! 🧺"
            ],
            [
                'id' => 4,
                'shortcut' => '/jam',
                'title' => 'Jam Operasional',
                'message' => "🕐 *Jam Operasional MDL Laundry*\n\nSenin - Sabtu: 08.00 - 21.00\nMinggu: 09.00 - 18.00\n\nUntuk jemput/antar silakan hubungi sebelum jam 17.00 ya kak 🚚"
            ],
            [
                'id' => 5,
                'shortcut' => '/tunggu',
                'title' => 'Mohon Tunggu',
                'message' => "Mohon tunggu sebentar ya kak, kami sedang mengecek data Anda... ⏳"
            ],
            [
                'id' => 6,
                'shortcut' => '/jemput',
                'title' => 'Konfirmasi Jemput',
                'message' => "Baik kak, kami akan segera kirim kurir untuk menjemput cucian Anda. 🚚\n\nMohon siapkan cuciannya ya!\nEstimasi tiba: 30-60 menit."
            ],
            [
                'id' => 7,
                'shortcut' => '/antar',
                'title' => 'Konfirmasi Antar',
                'message' => "Cucian kakak sudah siap! ✅\n\nKami akan segera mengantarkan ke alamat Anda.\nEstimasi tiba: 30-60 menit. 🚚"
            ],
            [
                'id' => 8,
                'shortcut' => '/alamat',
                'title' => 'Minta Alamat',
                'message' => "Mohon kirimkan alamat lengkap untuk jemput/antar ya kak:\n\n📍 Nama Jalan & Nomor Rumah\n📍 RT/RW\n📍 Kelurahan/Kecamatan\n📍 Patokan terdekat"
            ]
        ];
    }
}
