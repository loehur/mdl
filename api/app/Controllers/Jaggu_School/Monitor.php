<?php

namespace App\Controllers\Jaggu_School;

/**
 * Pantauan orang tua.
 * GET /Jaggu_School/Monitor/index
 */
class Monitor extends JagguController
{
    public function index()
    {
        $this->requireParent();

        try {
            $child = $this->getChildUser();
            if (!$child) {
                $this->error('Akun anak belum tersedia', 404);
            }

            $childId = (int) $child['id'];
            $today = date('Y-m-d');
            $showTomorrow = $this->showTomorrow();

            $payload = [
                'now' => date('Y-m-d H:i:s'),
                'show_tomorrow' => $showTomorrow,
                'tomorrow_reveal_hour' => self::TOMORROW_REVEAL_HOUR,
                'child' => [
                    'id' => $childId,
                    'name' => $child['name'],
                    'email' => $child['email'],
                ],
                'today' => $this->enrichDay($today, $childId, false),
                'tomorrow' => null,
                'summary' => [],
            ];

            $tomorrowDate = $this->nextSchoolDate($today);
            if ($tomorrowDate && $showTomorrow) {
                $payload['tomorrow'] = $this->enrichDay($tomorrowDate, $childId, false);
            }

            $summary = [];
            $td = $payload['today'];
            if ($td['total'] === 0) {
                $summary[] = [
                    'type' => 'info',
                    'text' => 'Tidak ada mapel hari ini.',
                ];
            } elseif ($td['complete']) {
                $summary[] = [
                    'type' => 'ok',
                    'text' => $child['name'] . ' sudah menyelesaikan semua mapel hari ini.',
                ];
            } else {
                $summary[] = [
                    'type' => 'warn',
                    'text' => $child['name'] . " belum selesai: {$td['pending']}/{$td['total']} mapel hari ini.",
                ];
            }

            if ($payload['tomorrow']) {
                $tm = $payload['tomorrow'];
                if ($tm['total'] > 0 && !$tm['complete']) {
                    $summary[] = [
                        'type' => 'prep',
                        'text' => "Persiapan {$tm['day_name']}: {$tm['done']}/{$tm['total']} sudah diceklist.",
                    ];
                } elseif ($tm['total'] > 0) {
                    $summary[] = [
                        'type' => 'ok',
                        'text' => "Persiapan {$tm['day_name']} sudah lengkap.",
                    ];
                }
            } elseif (!$showTomorrow) {
                $summary[] = [
                    'type' => 'info',
                    'text' => 'Pantauan mapel besok muncul mulai jam ' . self::TOMORROW_REVEAL_HOUR . ':00.',
                ];
            }

            $payload['summary'] = $summary;
            $this->success($payload, 'OK');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat pantauan: ' . $e->getMessage(), 500);
        }
    }
}
