<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\CRM\WaSenderContext;

/**
 * Data pelanggan laundry (tabel pelanggan di mdl_laundry) — dipakai CRM.
 * URL: /Laundry/Pelanggan/{get|setNomorAlternatif}
 */
class Pelanggan extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /** GET info pelanggan: id, nama, nomor utama, nomor alternatif. */
    public function get()
    {
        $this->jsonHeader();
        $id = $this->resolveIdPelanggan();
        if ($id <= 0) {
            $this->fail('id_pelanggan / cust_id / wa_number wajib', 400);
            return;
        }

        $row = $this->db(1)->query(
            'SELECT id_pelanggan, id_cabang, nama_pelanggan, nomor_pelanggan, nomor_pelanggan_2
             FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
            [$id]
        )->row_array();

        if (!is_array($row) || empty($row['id_pelanggan'])) {
            $this->fail('Pelanggan tidak ditemukan', 404);
            return;
        }

        $this->reply([
            'ok' => true,
            'item' => [
                'id_pelanggan' => (int) $row['id_pelanggan'],
                'id_cabang' => (int) ($row['id_cabang'] ?? 0),
                'nama_pelanggan' => (string) ($row['nama_pelanggan'] ?? ''),
                'nomor_pelanggan' => (string) ($row['nomor_pelanggan'] ?? ''),
                'nomor_pelanggan_2' => (string) ($row['nomor_pelanggan_2'] ?? ''),
            ],
        ]);
    }

    /** POST set nomor alternatif (nomor_pelanggan_2). Khusus admin. Body: cust_id/id_pelanggan + nomor_alternatif (boleh kosong = hapus). */
    public function setNomorAlternatif()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();

        // Khusus admin — role lain hanya boleh baca.
        $userId = (string) ($body['user_id'] ?? $this->query('user_id') ?? '');
        if (!$this->isAdminUser($userId)) {
            $this->fail('Hanya admin yang dapat mengubah nomor alternatif', 403);
            return;
        }

        $id = $this->resolveIdPelanggan($body);
        if ($id <= 0) {
            $this->fail('id_pelanggan / cust_id / wa_number wajib', 400);
            return;
        }

        $nomor2 = preg_replace('/\D/', '', (string) ($body['nomor_alternatif'] ?? ''));

        $db = $this->db(1);
        $row = $db->query(
            'SELECT id_cabang, nomor_pelanggan, nomor_pelanggan_2 FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
            [$id]
        )->row_array();
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            $this->fail('Pelanggan tidak ditemukan', 404);
            return;
        }

        $nomorUtama = preg_replace('/\D/', '', (string) ($row['nomor_pelanggan'] ?? ''));

        if ($nomor2 !== '') {
            if (strlen($nomor2) < 8) {
                $this->fail('Nomor alternatif minimal 8 digit', 400);
                return;
            }
            if ($nomorUtama !== '' && $nomor2 === $nomorUtama) {
                $this->fail('Nomor alternatif tidak boleh sama dengan nomor utama', 400);
                return;
            }

            // Normalisasi ke nasional (852…) untuk cek duplikat pelanggan lain di cabang sama.
            $n = WaSenderContext::toNomorNasional($nomor2);
            if ($n !== null && strlen($n) >= 8) {
                $esc = $db->escape($n);
                $expr = WaSenderContext::sqlDigitsExpr('nomor_pelanggan');
                $expr2 = WaSenderContext::sqlDigitsExpr('nomor_pelanggan_2');
                $dup = $db->query(
                    'SELECT id_pelanggan FROM pelanggan
                     WHERE id_cabang = ' . (int) ($row['id_cabang'] ?? 0)
                        . ' AND id_pelanggan <> ' . $id
                        . " AND ({$expr} LIKE '%{$esc}' OR {$expr2} LIKE '%{$esc}')
                     LIMIT 1"
                )->row_array();
                if (!empty($dup['id_pelanggan'])) {
                    $this->fail('Nomor alternatif sudah digunakan pelanggan lain di cabang yang sama', 400);
                    return;
                }
            }
        }

        $up = $db->update('pelanggan', ['nomor_pelanggan_2' => $nomor2 !== '' ? $nomor2 : null], 'id_pelanggan = ' . $id);
        if (!empty($up['errno'])) {
            $this->fail('Gagal menyimpan nomor alternatif: ' . ($up['error'] ?? 'error'), 500);
            return;
        }

        $this->reply([
            'ok' => true,
            'item' => [
                'id_pelanggan' => $id,
                'nomor_pelanggan_2' => $nomor2 !== '' ? $nomor2 : '',
            ],
            'message' => $nomor2 !== '' ? 'Nomor alternatif disimpan' : 'Nomor alternatif dihapus',
        ]);
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->setCorsHeaders();
    }

    /**
     * @param array<string,mixed> $res
     */
    private function reply(array $res): void
    {
        $ok = !empty($res['ok']);
        $res['status'] = $ok;
        if (!$ok) {
            http_response_code(isset($res['message']) ? 400 : 500);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'ok' => false,
            'status' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve id_pelanggan dari request: prioritas id_pelanggan/cust_id, fallback wa_number
     * (cocokkan via WaSenderContext → pelanggan di mdl_laundry).
     *
     * @param array<string,mixed>|null $body
     */
    private function resolveIdPelanggan(?array $body = null): int
    {
        $src = $body ?? $this->mergedInput();
        $id = (int) (
            $src['id_pelanggan']
            ?? $src['cust_id']
            ?? $this->query('id_pelanggan')
            ?? $this->query('cust_id')
            ?? 0
        );
        if ($id > 0) {
            return $id;
        }

        $waNumber = (string) ($src['wa_number'] ?? $this->query('wa_number') ?? '');
        if ($waNumber === '') {
            return 0;
        }

        try {
            $ctx = WaSenderContext::resolve($waNumber);
            if (!empty($ctx['is_pelanggan'])) {
                $pid = (int) ($ctx['id_pelanggan'] ?? 0);
                if ($pid > 0) {
                    return $pid;
                }
                $ids = $ctx['ids_pelanggan'] ?? [];
                if (!empty($ids)) {
                    return (int) $ids[0];
                }
            }
        } catch (\Throwable $e) {
            // fallback 0
        }

        return 0;
    }

    /**
     * @param array<string,mixed>|null $body
     */
    private function idPelangganFromRequest(?array $body = null): int
    {
        $src = $body ?? $this->mergedInput();
        return (int) (
            $src['id_pelanggan']
            ?? $src['cust_id']
            ?? $this->query('id_pelanggan')
            ?? $this->query('cust_id')
            ?? 0
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function mergedInput(): array
    {
        $json = $this->getBody();
        if (!is_array($json)) {
            $json = [];
        }
        return array_merge($_GET, $_POST, $json);
    }

    /** Cek role admin CRM (session mdl_crm_session dulu, lalu tabel crm_users). */
    private function isAdminUser(string $userId = ''): bool
    {
        $sessionUser = $_SESSION['mdl_crm_session']['user'] ?? null;
        if (is_array($sessionUser)) {
            if (strtolower((string) ($sessionUser['role'] ?? '')) === 'admin') {
                return true;
            }
            if ($userId === '') {
                $userId = (string) ($sessionUser['username'] ?? '');
            }
        }

        if ($userId === '') {
            return false;
        }

        try {
            $row = $this->db(0)->query(
                'SELECT role FROM crm_users WHERE LOWER(username) = ? LIMIT 1',
                [strtolower($userId)]
            )->row_array();
            return is_array($row) && strtolower((string) ($row['role'] ?? '')) === 'admin';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
