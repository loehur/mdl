<?php

namespace App\Controllers\Chip;

/**
 * Admin — kelola user chip (create/list/delete/reset/reset_coin).
 * Login admin pakai password (sama seperti aplikasi asli: 123654).
 * URL: /Chip/Admin/{method}
 */
class Admin extends ChipBaseController
{
    /**
     * POST /Chip/Admin/login — body { password: string }.
     */
    public function login()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $body = $this->getBody();
        $password = (string) ($body['password'] ?? '');
        if ($password === self::ADMIN_PASSWORD) {
            $_SESSION[self::SESSION_ADMIN] = true;
            $this->success(null, 'Login admin berhasil');
        }
        $this->error('Password salah', 401);
    }

    /**
     * POST /Chip/Admin/logout
     */
    public function logout()
    {
        $this->handleCors();
        unset($_SESSION[self::SESSION_ADMIN]);
        $this->success(null, 'Logout admin berhasil');
    }

    /**
     * GET /Chip/Admin/check — status auth admin.
     */
    public function check()
    {
        $this->handleCors();
        $this->success([
            'authed' => !empty($_SESSION[self::SESSION_ADMIN]),
        ]);
    }

    /**
     * GET /Chip/Admin/list — daftar user.
     */
    public function list()
    {
        $this->handleCors();
        $this->requireAdminAuth();

        $rows = $this->db(self::DB_CHIP)->query('SELECT user, chip FROM `user` ORDER BY user ASC')->result_array();

        $users = [];
        foreach ($rows as $r) {
            $u = (string) ($r['user'] ?? '');
            if ($u === '') {
                continue;
            }
            $users[] = [
                'user' => $u,
                'chip_awal' => (int) ($r['chip'] ?? 0),
                'saldo' => $this->saldo($u),
            ];
        }

        $this->success(['users' => $users]);
    }

    /**
     * POST /Chip/Admin/create — body { user: string|string[], chip: int }.
     * Dukung multi-user dipisah koma seperti aplikasi asli.
     */
    public function create()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireAdminAuth();

        $body = $this->getBody();
        $raw = $body['user'] ?? '';
        $chip = (int) ($body['chip'] ?? 0);

        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $raw = (string) $raw;
        $names = array_values(array_filter(array_map(
            static fn($p) => strtolower(trim((string) $p)),
            explode(',', $raw)
        ), static fn($p) => strlen($p) > 1));

        if ($names === []) {
            $this->error('Tidak ada user valid');
        }
        if ($chip < 0) {
            $this->error('Chip awal tidak valid');
        }

        $db = $this->db(self::DB_CHIP);
        $created = [];
        foreach ($names as $name) {
            if ($this->userExists($name)) {
                continue;
            }
            $id = $db->insert('user', ['user' => $name, 'chip' => $chip]);
            $created[] = [
                'user' => $name,
                'chip' => $chip,
                'ok' => (bool) $id,
            ];
        }

        $this->success(['created' => $created], 'User dibuat');
    }

    /**
     * POST /Chip/Admin/delete — body { user: string }.
     */
    public function delete()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireAdminAuth();

        $body = $this->getBody();
        $user = strtolower(trim((string) ($body['user'] ?? '')));
        if ($user === '') {
            $this->error('User wajib diisi');
        }

        $db = $this->db(self::DB_CHIP);
        $ok = $db->delete('user', ['user' => $user]);
        if (!$ok) {
            $this->error('Gagal menghapus user', 500);
        }

        $this->success(null, 'User dihapus');
    }

    /**
     * POST /Chip/Admin/reset — hapus SEMUA user + mutasi.
     * Body: { confirm: 'yes' }
     */
    public function reset()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireAdminAuth();

        $body = $this->getBody();
        if (($body['confirm'] ?? '') !== 'yes') {
            $this->error('Konfirmasi wajib "yes"');
        }

        $db = $this->db(self::DB_CHIP);
        $db->query('DELETE FROM mutasi');
        $db->query('DELETE FROM `user`');

        $this->success(null, 'Semua user & mutasi direset');
    }

    /**
     * POST /Chip/Admin/resetCoin — hapus semua mutasi (saldo kembali ke chip awal).
     * Body: { confirm: 'yes' }
     */
    public function resetCoin()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireAdminAuth();

        $body = $this->getBody();
        if (($body['confirm'] ?? '') !== 'yes') {
            $this->error('Konfirmasi wajib "yes"');
        }

        $this->db(self::DB_CHIP)->query('DELETE FROM mutasi');

        $this->success(null, 'Semua mutasi direset');
    }
}
