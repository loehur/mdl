<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\PaymentAllocator;

class Payment extends Controller
{
    public function allocate()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        $body = $this->getBody();
        $this->validate($body, ['id_pelanggan', 'nominal', 'type']);

        $result = (new PaymentAllocator())->allocate(
            (int) $body['id_pelanggan'],
            (int) $body['nominal'],
            (string) $body['type'],
            isset($body['receipt_date']) ? (string) $body['receipt_date'] : null,
            isset($body['message_id']) ? (string) $body['message_id'] : null
        );
        if (empty($result['ok'])) {
            $this->error($result['message'] ?? 'Gagal mengalokasikan pembayaran', 422, $result);
        }
        $this->success($result, $result['message']);
    }
}
