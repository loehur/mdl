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
                'shortcut' => '/rekening',
                'title' => 'Rekening Pembayaran',
                'message' => "QRIS https://ml.nalju.com/I/q\nBRI 327901031534535\nBCA 8455103793\nBTNs 7132077419\nSEABANK 901799867052\nSHOPEE/GOPAY/DANA 081268098300\nan. LUHUR GUNAWAN"
            ],
            [
                'id' => 2,
                'shortcut' => '/pn-location',
                'title' => 'Lokasi MDL PN',
                'message' => "https://maps.app.goo.gl/n5zFfqwiPQGwuj9r9"
            ],
            [
                'id' => 3,
                'shortcut' => '/rm-location',
                'title' => 'Lokasi MDL RM',
                'message' => "https://maps.app.goo.gl/sCWFztm1Z5nXDmKE7"
            ],
            [
                'id' => 4,
                'shortcut' => '/ks-location',
                'title' => 'Lokasi MDL KS',
                'message' => "https://maps.app.goo.gl/sqPVTc3msJh8xhdJ8"
            ],
            [
                'id' => 5,
                'shortcut' => '/rw-location',
                'title' => 'Lokasi MDL RW',
                'message' => "https://maps.app.goo.gl/XXW64AENKVgxHNKh9"
            ],
            [
                'id' => 6,
                'shortcut' => '/tg-location',
                'title' => 'Lokasi MDL TG',
                'message' => "https://maps.app.goo.gl/hzsG5n9NWVjE2s5K6"
            ],
            [
                'id' => 7,
                'shortcut' => '/sb-location',
                'title' => 'Lokasi MDL SB',
                'message' => "https://maps.app.goo.gl/HMc7m4p6PUrKfvPA9"
            ],
            [
                'id' => 8,
                'shortcut' => '/tb-location',
                'title' => 'Lokasi MDL TB',
                'message' => "https://maps.app.goo.gl/gd4GziHokAjtTD9K9"
            ],
            [
                'id' => 9,
                'shortcut' => '/mw-location',
                'title' => 'Lokasi MDL MW',
                'message' => "https://maps.app.goo.gl/jCfoVBwKRKkAUMHg6"
            ],            
        ];
    }
}
