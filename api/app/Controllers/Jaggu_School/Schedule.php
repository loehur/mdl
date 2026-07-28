<?php

namespace App\Controllers\Jaggu_School;

/**
 * Jadwal mapel Senin–Sabtu (hanya orang tua).
 * GET  /Jaggu_School/Schedule/index
 * POST /Jaggu_School/Schedule/save  body: { days: { "1": ["Matematika", ...], ... } }
 */
class Schedule extends JagguController
{
    public function index()
    {
        $this->requireParent();

        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT id, day_of_week, subject_name, sort_order
                 FROM schedule_items
                 ORDER BY day_of_week ASC, sort_order ASC, id ASC"
            )->result_array();

            $days = [];
            foreach ($this->dayNames() as $dow => $name) {
                $days[(string) $dow] = [
                    'day_of_week' => $dow,
                    'day_name' => $name,
                    'subjects' => [],
                ];
            }

            foreach ($rows ?: [] as $r) {
                $dow = (string) (int) $r['day_of_week'];
                if (!isset($days[$dow])) {
                    continue;
                }
                $days[$dow]['subjects'][] = [
                    'id' => (int) $r['id'],
                    'subject_name' => $r['subject_name'],
                    'sort_order' => (int) $r['sort_order'],
                ];
            }

            $this->success([
                'days' => array_values($days),
            ], 'Jadwal dimuat');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat jadwal: ' . $e->getMessage(), 500);
        }
    }

    public function save()
    {
        $this->requireParent();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $inputDays = $body['days'] ?? null;
            if (!is_array($inputDays)) {
                $this->error('Format days tidak valid', 400);
            }

            $db = $this->db($this->db_index);
            $db->query('DELETE FROM schedule_items');

            $insertCount = 0;
            foreach ($this->dayNames() as $dow => $_name) {
                $key = (string) $dow;
                $list = $inputDays[$key] ?? $inputDays[$dow] ?? [];
                if (!is_array($list)) {
                    continue;
                }

                $order = 0;
                foreach ($list as $raw) {
                    $name = '';
                    if (is_string($raw)) {
                        $name = trim($raw);
                    } elseif (is_array($raw)) {
                        $name = trim((string) ($raw['subject_name'] ?? $raw['name'] ?? ''));
                    }
                    if ($name === '') {
                        continue;
                    }

                    $db->insert('schedule_items', [
                        'day_of_week' => $dow,
                        'subject_name' => mb_substr($name, 0, 150),
                        'sort_order' => $order++,
                    ]);
                    $insertCount++;
                }
            }

            $this->success([
                'saved' => $insertCount,
            ], 'Jadwal disimpan');
        } catch (\Throwable $e) {
            $this->error('Gagal menyimpan jadwal: ' . $e->getMessage(), 500);
        }
    }
}
