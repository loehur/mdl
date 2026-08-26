<?php

namespace App\Controllers\Payment;

use App\Core\Controller;
use App\Helpers\Payment\BankAccountGuide;

/**
 * GET /Payment/BankAccounts/index — rekening pembayaran (publik).
 * GET /Payment/BankAccounts/bca — panduan transfer BCA saja.
 */
class BankAccounts extends Controller
{
    public function __construct()
    {
        $this->handleCors();
        $this->setCorsHeaders();
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error('Method not allowed', 405);
        }

        $qrisUrl = trim((string) ($_GET['qris_url'] ?? ''));
        if ($qrisUrl === '' && class_exists('Env') && defined('Env::QRIS_PUBLIC_URL')) {
            $qrisUrl = (string) \Env::QRIS_PUBLIC_URL;
        }
        if ($qrisUrl === '') {
            $qrisUrl = 'https://ml.nalju.com/I/q';
        }

        $qrisImageUrl = trim((string) ($_GET['qris_image_url'] ?? ''));
        if ($qrisImageUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'ml.nalju.com'));
            $qrisImageUrl = $scheme . '://' . $host . '/mdl/laundry/in_assets/img/qris/qris_1.jpeg';
        }

        $payload = BankAccountGuide::publicPayload($qrisUrl, $qrisImageUrl);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function bca()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error('Method not allowed', 405);
        }

        $bca = BankAccountGuide::bcaAccount();
        if ($bca === null) {
            $this->error('Rekening BCA tidak tersedia', 503);
        }

        $this->success([
            'account' => $bca,
            'message' => BankAccountGuide::bcaTransferMessage(),
        ], 'Rekening BCA');
    }
}
