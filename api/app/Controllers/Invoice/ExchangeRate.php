<?php

namespace App\Controllers\Invoice;

use App\Helpers\InvoiceExchangeRate;

/**
 * Preview kurs USD→IDR (dari DB hari ini atau fetch freecurrencyapi).
 * GET /Invoice/ExchangeRate/usdIdr
 */
class ExchangeRate extends InvoiceController
{
    public function usdIdr()
    {
        $this->verifyAuth();

        try {
            $info = InvoiceExchangeRate::getUsdToIdrRate($this->db($this->db_index));
            $this->success($info, 'Kurs USD ke IDR');
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 502);
        }
    }
}
