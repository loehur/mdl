<?php

namespace App\Controllers\Jaggu_School;

/**
 * Ceklist mapel (anak).
 * GET  /Jaggu_School/Checklist/today
 * POST /Jaggu_School/Checklist/toggle  { schedule_item_id, for_date, checked: bool }
 *
 * Window tampilan (bergantian jam 08:00):
 * - Sebelum 08:00: hari ini
 * - Mulai 08:00: besok
 */
class Checklist extends JagguController
{
    public function today()
    {
        $child = $this->requireChild();
        $childId = (int) $child['id'];

        try {
            $today = date('Y-m-d');
            $showToday = $this->showToday();
            $showTomorrow = $this->showTomorrow();

            $payload = [
                'now' => date('Y-m-d H:i:s'),
                'show_today' => $showToday,
                'show_tomorrow' => $showTomorrow,
                'switch_hour' => self::SWITCH_HOUR,
                'tomorrow_reveal_hour' => self::SWITCH_HOUR,
                'today' => null,
                'tomorrow' => null,
                'notices' => [],
            ];

            if ($showToday) {
                $payload['today'] = $this->enrichDay($today, $childId, true);
            }

            $tomorrowDate = $this->nextSchoolDate($today);
            if ($tomorrowDate && $showTomorrow) {
                $payload['tomorrow'] = $this->enrichDay($tomorrowDate, $childId, true);
            }

            $notices = [];

            if ($showToday && $payload['today']) {
                $todayDay = $payload['today'];
                if ($todayDay['total'] === 0) {
                    $notices[] = [
                        'type' => 'info',
                        'text' => 'Hari ini tidak ada mapel di jadwal (libur atau belum diisi orang tua).',
                    ];
                } elseif ($todayDay['pending'] > 0) {
                    $notices[] = [
                        'type' => 'warn',
                        'text' => "Masih ada {$todayDay['pending']} mapel hari ini yang belum diceklist.",
                    ];
                } else {
                    $notices[] = [
                        'type' => 'ok',
                        'text' => 'Semua mapel hari ini sudah diceklist. Hebat!',
                    ];
                }

                $notices[] = [
                    'type' => 'info',
                    'text' => 'Persiapan besok menggantikan list ini jam ' . self::SWITCH_HOUR . '.00.',
                ];
            }

            if ($payload['tomorrow']) {
                $tm = $payload['tomorrow'];
                if ($tm['total'] > 0 && $tm['pending'] > 0) {
                    $notices[] = [
                        'type' => 'prep',
                        'text' => "Siapkan mapel {$tm['day_name']}: masih {$tm['pending']} belum diceklist.",
                    ];
                } elseif ($tm['total'] > 0 && $tm['complete']) {
                    $notices[] = [
                        'type' => 'ok',
                        'text' => "Mapel {$tm['day_name']} sudah siap semua.",
                    ];
                }
            }

            $payload['notices'] = $notices;
            $this->success($payload, 'OK');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat ceklist: ' . $e->getMessage(), 500);
        }
    }

    public function toggle()
    {
        $child = $this->requireChild();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $itemId = (int) ($body['schedule_item_id'] ?? 0);
            $forDate = trim((string) ($body['for_date'] ?? ''));
            $checked = !empty($body['checked']);

            if ($itemId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $forDate)) {
                $this->error('Data tidak valid', 400);
            }

            if (!$this->canChecklistDate($forDate)) {
                $this->error('Ceklist hanya boleh dari H-1 sampai hari H', 400);
            }

            $dow = $this->isoDayOfWeek($forDate);
            if ($dow < 1 || $dow > 6) {
                $this->error('Tanggal ini hari libur (Minggu)', 400);
            }

            $today = date('Y-m-d');

            if ($forDate === $today && !$this->showToday()) {
                $this->error('List hari ini sudah diganti sejak jam ' . self::SWITCH_HOUR . '.00', 400);
            }

            if ($forDate > $today && !$this->showTomorrow()) {
                $this->error('Mapel besok baru bisa diceklist mulai jam ' . self::SWITCH_HOUR . '.00', 400);
            }

            $item = $this->db($this->db_index)->query(
                "SELECT id, day_of_week, subject_name FROM schedule_items WHERE id = ? LIMIT 1",
                [$itemId]
            )->row_array();

            if (!$item) {
                $this->error('Mapel tidak ditemukan', 404);
            }

            if ((int) $item['day_of_week'] !== $dow) {
                $this->error('Mapel tidak sesuai hari tanggal tersebut', 400);
            }

            $childId = (int) $child['id'];
            $db = $this->db($this->db_index);

            if ($checked) {
                $existing = $db->query(
                    "SELECT id FROM checklist_entries
                     WHERE child_user_id = ? AND schedule_item_id = ? AND for_date = ?
                     LIMIT 1",
                    [$childId, $itemId, $forDate]
                )->row_array();

                if (!$existing) {
                    $db->insert('checklist_entries', [
                        'child_user_id' => $childId,
                        'schedule_item_id' => $itemId,
                        'for_date' => $forDate,
                        'checked_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                $db->query(
                    "DELETE FROM checklist_entries
                     WHERE child_user_id = ? AND schedule_item_id = ? AND for_date = ?",
                    [$childId, $itemId, $forDate]
                );
            }

            $this->success([
                'schedule_item_id' => $itemId,
                'for_date' => $forDate,
                'checked' => $checked,
                'day' => $this->enrichDay($forDate, $childId, true),
            ], $checked ? 'Mapel ditandai siap' : 'Ceklist dibatalkan');
        } catch (\Throwable $e) {
            $this->error('Gagal menyimpan ceklist: ' . $e->getMessage(), 500);
        }
    }
}
