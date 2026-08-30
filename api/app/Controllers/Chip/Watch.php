<?php

namespace App\Controllers\Chip;

/**
 * Watch — leaderboard pemain, total chip, jumlah saldo rendah, riwayat mutasi.
 * URL: /Chip/Watch/{method}
 */
class Watch extends ChipBaseController
{
    /**
     * GET /Chip/Watch/board — ranking pemain + total + low count.
     */
    public function board()
    {
        $this->handleCors();
        $this->requireUserAuth();

        $rows = $this->db(self::DB_CHIP)->query('SELECT user FROM `user` ORDER BY user ASC')->result_array();

        $players = [];
        $total = 0;
        $lowCount = 0;
        foreach ($rows as $r) {
            $u = (string) ($r['user'] ?? '');
            if ($u === '') {
                continue;
            }
            $chip = $this->saldo($u);
            $players[] = ['user' => $u, 'chip' => $chip];
            $total += $chip;
            if ($chip <= self::LOW_SALDO) {
                $lowCount++;
            }
        }

        usort($players, static function (array $a, array $b): int {
            return $b['chip'] <=> $a['chip'];
        });

        $this->success([
            'players' => $players,
            'total' => $total,
            'low_count' => $lowCount,
            'player_count' => count($players),
        ]);
    }

    /**
     * GET /Chip/Watch/history — 60 mutasi terakhir.
     */
    public function history()
    {
        $this->handleCors();
        $this->requireUserAuth();

        $rows = $this->db(self::DB_CHIP)->query(
            'SELECT id, f, t, chip, insertTime FROM mutasi ORDER BY id DESC LIMIT 60'
        )->result_array();

        $this->success(['items' => $rows]);
    }
}
