<?php

namespace App\Controllers\Investasi;

use App\Core\Controller as BaseController;

abstract class InvestasiController extends BaseController
{
    protected $db_index = 5;
    protected $session_key = 'investasi_user_session';

    public function __construct()
    {
        $this->handleCors();
    }

    protected function verifyAuth()
    {
        if (empty($_SESSION[$this->session_key]['logged_in'])) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function currentUser()
    {
        return $_SESSION[$this->session_key]['user'] ?? null;
    }

    protected function sanitizeAmount($value)
    {
        if (!is_numeric($value)) {
            $this->error('Jumlah tidak valid', 400);
        }

        $amount = round((float) $value, 2);
        if ($amount <= 0) {
            $this->error('Jumlah harus lebih dari 0', 400);
        }

        return $amount;
    }

    protected function sanitizeDate($value, $field = 'record_date')
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->error("Format {$field} tidak valid (gunakan YYYY-MM-DD)", 400);
        }

        return $value;
    }
}
