<?php

namespace App\Controllers\Chip;

use App\Core\Controller as BaseController;

/**
 * Base controller aplikasi Chip.
 * Auth via session (login username tanpa password, sesuai aplikasi asli).
 * DB chip di index 9.
 */
abstract class ChipBaseController extends BaseController
{
    protected const DB_CHIP = 9;
    protected const SESSION_KEY = 'chip_user';
    protected const SESSION_ADMIN = 'chip_admin_auth';
    protected const ADMIN_PASSWORD = '123654';
    protected const LOW_SALDO = 300;

    public function __construct()
    {
        $this->handleCors();
    }

    protected function requireUserAuth()
    {
        if (!$this->currentUser()) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function requireAdminAuth()
    {
        if (empty($_SESSION[self::SESSION_ADMIN])) {
            $this->error('Unauthorized', 401);
        }
    }

    protected function currentUser(): string
    {
        return trim((string) ($_SESSION[self::SESSION_KEY] ?? ''));
    }

    protected function setUser(string $user): void
    {
        $_SESSION[self::SESSION_KEY] = strtolower(trim($user));
    }

    protected function clearUser(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Hitung saldo user = chip awal di tabel user dikurangi total transfer keluar (f) + masuk (t).
     */
    protected function saldo(string $user): int
    {
        $db = $this->db(self::DB_CHIP);
        $user = strtolower(trim($user));
        if ($user === '') {
            return 0;
        }

        $row = $db->query('SELECT chip FROM `user` WHERE user = ? LIMIT 1', [$user])->row_array();
        if (!$row) {
            return 0;
        }
        $awal = (int) ($row['chip'] ?? 0);

        $out = $db->query('SELECT COALESCE(SUM(chip), 0) AS total FROM mutasi WHERE f = ?', [$user])->row_array();
        $in = $db->query('SELECT COALESCE(SUM(chip), 0) AS total FROM mutasi WHERE t = ?', [$user])->row_array();

        $totalOut = (int) ($out['total'] ?? 0);
        $totalIn = (int) ($in['total'] ?? 0);

        return $awal - $totalOut + $totalIn;
    }

    /**
     * User terdaftar? (case-insensitive)
     */
    protected function userExists(string $user): bool
    {
        if (trim($user) === '') {
            return false;
        }
        $row = $this->db(self::DB_CHIP)->query(
            'SELECT 1 FROM `user` WHERE user = ? LIMIT 1',
            [strtolower(trim($user))]
        )->row_array();

        return !empty($row);
    }
}
