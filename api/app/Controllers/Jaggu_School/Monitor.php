<?php

namespace App\Controllers\Jaggu_School;

/**
 * Pantauan orang tua.
 * GET /Jaggu_School/Monitor/index
 *
 * Window tampilan (bergantian jam 07:00):
 * - Sebelum 07:00: hari ini
 * - Mulai 07:00: besok
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
            $showToday = $this->showToday();
            $showTomorrow = $this->showTomorrow();

            $payload = [
                'now' => date('Y-m-d H:i:s'),
                'show_today' => $showToday,
                'show_tomorrow' => $showTomorrow,
                'switch_hour' => self::SWITCH_HOUR,
                'tomorrow_reveal_hour' => self::SWITCH_HOUR,
                'child' => [
                    'id' => $childId,
                    'name' => $child['name'],
                    'email' => $child['email'],
                ],
                'today' => null,
                'tomorrow' => null,
                'summary' => [],
            ];

            if ($showToday) {
                $payload['today'] = $this->enrichDay($today, $childId, false);
            }

            $tomorrowDate = $this->nextSchoolDate($today);
            if ($tomorrowDate && $showTomorrow) {
                $payload['tomorrow'] = $this->enrichDay($tomorrowDate, $childId, false);
            }

            $summary = [];

            if ($showToday && $payload['today']) {
                $td = $payload['today'];
                if ($td['total'] === 0) {
                    $summary[] = [
                        'type' => 'info',
                        'text' => 'Tidak ada mapel hari ini.',
                    ];
                } elseif ($td['complete']) {
                    $summary[] = [
                        'type' => 'ok',
                        'text' => 'Mapel hari ini telah Jaggu persiapkan.',
                    ];
                } else {
                    $summary[] = [
                        'type' => 'warn',
                        'text' => "Jaggu belum selesai: {$td['pending']}/{$td['total']} mapel hari ini.",
                    ];
                }

                $summary[] = [
                    'type' => 'info',
                    'text' => 'Persiapan besok menggantikan list ini jam ' . self::SWITCH_HOUR . '.00.',
                ];
            }

            if ($payload['tomorrow']) {
                $tm = $payload['tomorrow'];
                if ($tm['total'] > 0 && !$tm['complete']) {
                    $summary[] = [
                        'type' => 'prep',
                        'text' => "Persiapan Besok ({$tm['day_name']}): {$tm['done']}/{$tm['total']} sudah diceklist.",
                    ];
                } elseif ($tm['total'] > 0) {
                    $summary[] = [
                        'type' => 'ok',
                        'text' => "Persiapan Besok ({$tm['day_name']}) telah Jaggu persiapkan.",
                    ];
                }
            }

            $payload['summary'] = $summary;
            $this->success($payload, 'OK');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat pantauan: ' . $e->getMessage(), 500);
        }
    }
}
