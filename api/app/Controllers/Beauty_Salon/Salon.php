<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class Salon extends Controller
{
    private $db_index = 5; // Using the salon database index

    /**
     * GET - Get salon info by salon_id from session
     */
    public function index()
    {
        try {
            // Get salon_id from session
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan. Silakan login ulang.', 401);
            }

            // Get salon data
            $salon = $this->db($this->db_index)
                ->get_where('salon', ['salon_id' => $salon_id], 1)
                ->row_array();

            if (!$salon) {
                // Return empty data if salon not created yet
                $this->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Salon belum dikonfigurasi'
                ]);
            } else {
                $this->json([
                    'success' => true,
                    'data' => $salon
                ]);
            }
        } catch (\Exception $e) {
            error_log("Salon index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create or update salon
     */
    public function save()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['nama_salon', 'alamat_salon']);

            // Get data from session
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;

            if (!$salon_id || !$user_id) {
                $this->error('Session tidak valid. Silakan login ulang.', 401);
            }

            $nama_salon = $body['nama_salon'];
            $alamat_salon = $body['alamat_salon'];

            // Check if salon exists
            $exists = $this->db($this->db_index)
                ->get_where('salon', ['salon_id' => $salon_id], 1)
                ->row_array();

            if ($exists) {
                // Update
                $this->db($this->db_index)->update('salon', [
                    'nama_salon' => $nama_salon,
                    'alamat_salon' => $alamat_salon,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['salon_id' => $salon_id]);

                $this->json([
                    'success' => true,
                    'message' => 'Data salon berhasil diperbarui'
                ]);
            } else {
                // Insert
                $this->db($this->db_index)->insert('salon', [
                    'salon_id' => $salon_id,
                    'owner_id' => $user_id,
                    'nama_salon' => $nama_salon,
                    'alamat_salon' => $alamat_salon,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $this->json([
                    'success' => true,
                    'message' => 'Data salon berhasil disimpan'
                ]);
            }
        } catch (\Exception $e) {
            error_log("Salon save error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
