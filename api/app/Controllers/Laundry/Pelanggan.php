<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\CRM\WaSenderContext;
use App\Helpers\Laundry\PelangganStore;

/**
 * Data pelanggan laundry (tabel pelanggan di mdl_laundry) — dipakai CRM + Laundry (terpusat).
 * URL: /Laundry/Pelanggan/{get|setNomorAlternatif|cekHp|tambah|pilih|update|cekEdit|updateCell}
 */
class Pelanggan extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /** POST cek nomor HP (dipanggil Laundry). Body: hp, id_cabang. */
    public function cekHp()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $this->reply(PelangganStore::cekHp((string) ($body['hp'] ?? ''), $idCabang));
    }

    /** POST tambah pelanggan (dipanggil Laundry). Body: nama/f1, hp/f2, hp2/f3, cek_mirip, id_cabang. */
    public function tambah()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $this->reply(PelangganStore::tambah($body, $idCabang));
    }

    /** POST pilih/rename pelanggan (dipanggil Laundry). Body: id, nama, id_cabang. */
    public function pilih()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $this->reply(PelangganStore::pilih((int) ($body['id'] ?? 0), (string) ($body['nama'] ?? ''), $idCabang));
    }

    /** POST update seluruh field (dipanggil Laundry). Body: id, nama_pelanggan, nomor_pelanggan, nomor_pelanggan_2, disc, id_cabang, can_edit_disc. */
    public function update()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $canEditDisc = !empty($body['can_edit_disc']) || $body['can_edit_disc'] === '1';
        $this->reply(PelangganStore::update($body, $idCabang, $canEditDisc));
    }

    /** POST cek sebelum simpan edit (dipanggil Laundry). Body: id, nama_pelanggan, nomor_pelanggan, nomor_pelanggan_2, id_cabang. */
    public function cekEdit()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $this->reply(PelangganStore::cekEdit($body, $idCabang));
    }

    /** POST update satu kolom (dipanggil Laundry). Body: id, mode, value, id_cabang, can_edit_disc. */
    public function updateCell()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        if (!$this->verifyCronSecret()) {
            $this->fail('Akses ditolak', 403);
            return;
        }
        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $canEditDisc = !empty($body['can_edit_disc']) || $body['can_edit_disc'] === '1';
        $this->reply(PelangganStore::updateCell(
            (int) ($body['id'] ?? 0),
            (string) ($body['mode'] ?? ''),
            $body['value'] ?? '',
            $idCabang,
            $canEditDisc
        ));
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

        $idCabang = (int) ($body['id_cabang'] ?? 0);
        $nomor2 = preg_replace('/\D/', '', (string) ($body['nomor_alternatif'] ?? ''));

        $res = PelangganStore::setNomorAlternatif($id, $nomor2, $idCabang);
        if (empty($res['ok'])) {
            $this->fail($res['msg'] ?? 'Gagal menyimpan nomor alternatif', 400);
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

    /**
     * Verifikasi secret server-to-server (Laundry → API), pola Biteship/Fonnte.
     * - Env::CRON_SECRET kosong → izinkan (compat)
     * - secret tidak dikirim → izinkan (compat, log) selama API belum wajib
     * - secret dikirim tapi salah → tolak
     */
    private function verifyCronSecret(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }
        if ($expected === '') {
            $expected = (string) (getenv('CRON_SECRET') ?: '');
        }

        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        if ($expected === '') {
            return true;
        }
        if ($provided === '') {
            if (class_exists('\\Log', false)) {
                \Log::write('Pelanggan API: no secret from laundry (compat allow)', 'api', 'Pelanggan');
            }
            return true;
        }

        return hash_equals($expected, $provided);
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
