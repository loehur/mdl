<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

/**
 * CRM Roles API
 * - admin: mdl_main.crm_users
 * - crew: mdl_laundry.cabang (id_cabang)
 */
class Roles extends Controller
{
    private $db_index = 0;      // mdl_main
    private $db_laundry = 1;    // mdl_laundry

    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET /CRM/Roles
     * Returns users grouped by role for WA Server
     */
    public function index()
    {
        try {
            $data = [
                'admin' => [],
                'crew' => []
            ];

            // admin from crm_users
            $users = $this->db($this->db_index)
                ->get('crm_users')
                ->result_array();

            foreach ($users as $user) {
                $role = strtolower($user['role'] ?? '');
                $username = strtoupper((string) $user['username']);

                if ($role === 'admin') {
                    $data['admin'][] = $username;
                }
            }

            // crew from cabang (id_cabang as username)
            $cabangs = $this->db($this->db_laundry)
                ->get('cabang')
                ->result_array();

            foreach ($cabangs as $cabang) {
                $id = (string) ($cabang['id_cabang'] ?? '');
                if ($id === '') {
                    continue;
                }
                $data['crew'][] = strtoupper($id);
            }

            $this->success($data, 'Role IDs retrieved successfully');

        } catch (\Exception $e) {
            \Log::write("CRM Roles error: " . $e->getMessage(), 'crm_error', 'Roles');
            
            $fallback = defined('\Env::CRM_USER_ROLES') ? \Env::CRM_USER_ROLES : [
                'admin' => [],
                'crew' => []
            ];
            // Pastikan tidak ada bucket driver di fallback
            unset($fallback['driver']);
            
            $this->success($fallback, 'Role IDs retrieved (fallback)');
        }
    }
}
