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
                'shortcut' => '/loc-pn',
                'title' => 'Lokasi MDL PN',
                'message' => "QRIS https://ml.nalju.com/I/q\nBRI 327901031534535\nBCA 8455103793\nBTNs 7132077419\nSEABANK 901799867052\nSHOPEE/GOPAY/DANA 081268098300\nan. LUHUR GUNAWAN"
            ],
            
        ];
    }
}
