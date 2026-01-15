<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

/**
 * CRM Roles API
 * Provides role lists for WA Server authentication
 * Fetches from crm_users table dynamically
 */
class Roles extends Controller
{
    private $db_index = 0;

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
            // Fetch all active users from crm_users table
            $users = $this->db($this->db_index)
                ->get('crm_users')
                ->result_array();

            $data = [
                'admin' => [],
                'driver' => [],
                'crew' => []
            ];

            // Group users by role
            // IMPORTANT: Convert to UPPERCASE for OneSignal External ID compatibility
            foreach ($users as $user) {
                $role = strtolower($user['role'] ?? 'crew');
                $username = strtoupper($user['username']); // UPPERCASE for OneSignal

                if ($role === 'admin') {
                    $data['admin'][] = $username;
                } elseif ($role === 'driver') {
                    $data['driver'][] = $username;
                } else {
                    // Default to crew for any other role
                    $data['crew'][] = $username;
                }
            }

            $this->success($data, 'Role IDs retrieved successfully');

        } catch (\Exception $e) {
            \Log::write("CRM Roles error: " . $e->getMessage(), 'crm_error', 'Roles');
            
            // Fallback to env/hardcoded if database fails
            $fallback = defined('\Env::CRM_USER_ROLES') ? \Env::CRM_USER_ROLES : [
                'admin' => ['DEV', 'AYAH'],
                'driver' => ['DRIVER1'],
                'crew' => []
            ];
            
            $this->success($fallback, 'Role IDs retrieved (fallback)');
        }
    }
}
