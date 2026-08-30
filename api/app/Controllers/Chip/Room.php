<?php

namespace App\Controllers\Chip;

/**
 * Room — login user, saldo, daftar pemain, transfer chip, mutasi.
 * URL: /Chip/Room/{method}
 */
class Room extends ChipBaseController
{
    /**
     * POST /Chip/Room/login — login cukup username (sesuai aplikasi asli).
     * Body: { user: string }
     */
    public function login()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $user = strtolower(trim((string) ($body['user'] ?? '')));
        if ($user === '') {
            $this->error('Username wajib diisi');
        }

        if (!$this->userExists($user)) {
            $this->error('Invalid Username', 401);
        }

        $this->setUser($user);
        $this->success([
            'user' => $user,
            'saldo' => $this->saldo($user),
        ], 'Login berhasil');
    }

    /**
     * POST /Chip/Room/logout
     */
    public function logout()
    {
        $this->handleCors();
        $this->clearUser();
        $this->success(null, 'Logout berhasil');
    }

    /**
     * GET /Chip/Room/me — user login + saldo sendiri.
     */
    public function me()
    {
        $this->handleCors();
        $this->requireUserAuth();
        $user = $this->currentUser();
        $this->success([
            'user' => $user,
            'saldo' => $this->saldo($user),
            'low' => $this->saldo($user) <= self::LOW_SALDO,
        ]);
    }

    /**
     * GET /Chip/Room/players — semua pemain lain + saldonya.
     */
    public function players()
    {
        $this->handleCors();
        $this->requireUserAuth();
        $me = $this->currentUser();

        $rows = $this->db(self::DB_CHIP)->query(
            'SELECT user FROM `user` WHERE user <> ? ORDER BY user ASC',
            [$me]
        )->result_array();

        $players = [];
        foreach ($rows as $r) {
            $u = (string) ($r['user'] ?? '');
            if ($u === '') {
                continue;
            }
            $players[] = [
                'user' => $u,
                'saldo' => $this->saldo($u),
            ];
        }

        $this->success(['players' => $players]);
    }

    /**
     * POST /Chip/Room/transfer — kirim chip ke user lain.
     * Body: { t: string, c: int }
     */
    public function transfer()
    {
        $this->handleCors();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        $this->requireUserAuth();

        $body = $this->getBody();
        $from = $this->currentUser();
        $to = strtolower(trim((string) ($body['t'] ?? '')));
        $chip = (int) ($body['c'] ?? 0);

        if ($to === '') {
            $this->error('Penerima wajib diisi');
        }
        if ($to === $from) {
            $this->error('Tidak bisa transfer ke diri sendiri');
        }
        if (!$this->userExists($to)) {
            $this->error('Penerima tidak terdaftar');
        }
        if ($chip <= 0) {
            $this->error('Jumlah chip harus lebih dari 0');
        }
        if ($this->saldo($from) < $chip) {
            $this->error('Saldo tidak cukup');
        }

        $insertId = $this->db(self::DB_CHIP)->insert('mutasi', [
            'f' => $from,
            't' => $to,
            'chip' => $chip,
        ]);
        if (!$insertId) {
            $this->error('Gagal menyimpan transfer', 500);
        }

        $this->success([
            'from' => $from,
            'to' => $to,
            'chip' => $chip,
            'saldo' => $this->saldo($from),
        ], 'Transfer berhasil');
    }

    /**
     * GET /Chip/Room/mutasi — riwayat transfer milik user login (f atau t).
     */
    public function mutasi()
    {
        $this->handleCors();
        $this->requireUserAuth();
        $me = $this->currentUser();

        $rows = $this->db(self::DB_CHIP)->query(
            'SELECT id, f, t, chip, insertTime FROM mutasi
             WHERE f = ? OR t = ?
             ORDER BY id DESC LIMIT 100',
            [$me, $me]
        )->result_array();

        $this->success(['items' => $rows]);
    }
}
