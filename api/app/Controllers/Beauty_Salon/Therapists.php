<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Therapists extends Controller
{
    private $db_index = 5;

    public function index()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                // Return empty if no session/salon (security)
                $this->json(['success' => true, 'data' => []]);
                return;
            }

            $data = $this->db($this->db_index)
                 ->query("SELECT * FROM therapists WHERE salon_id = ? ORDER BY nama ASC", [$salon_id])
                 ->result_array();

            $this->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->error('Gagal mengambil data: ' . $e->getMessage(), 500);
        }
    }

    public function create()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['nama', 'no_hp']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) {
                $this->error('Sesi habis, silakan login ulang', 401);
            }

            $data = [
                'salon_id' => $salon_id,
                'nama' => $body['nama'],
                'no_hp' => $body['no_hp'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('therapists', $data);

            if ($id) {
                $data['id'] = $id;
                $this->json([
                    'success' => true,
                    'message' => 'Terapis berhasil ditambahkan',
                    'data' => $data
                ]);
            } else {
                $this->error('Gagal menyimpan data', 500);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function update($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['nama', 'no_hp']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }

            // Verify ownership
            $exists = $this->db($this->db_index)->get_where('therapists', ['id' => $id, 'salon_id' => $salon_id], 1)->row_array();
            if (!$exists) {
                $this->error('Terapis tidak ditemukan', 404);
            }

            $data = [
                'nama' => $body['nama'],
                'no_hp' => $body['no_hp'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db($this->db_index)->update('therapists', $data, ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Data terapis berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }

            // Verify ownership
            $exists = $this->db($this->db_index)->get_where('therapists', ['id' => $id, 'salon_id' => $salon_id], 1)->row_array();
            if (!$exists) {
                $this->error('Terapis tidak ditemukan', 404);
            }

            $this->db($this->db_index)->delete('therapists', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Terapis berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
