<?php

/**
 * Admin Dashboard Controller
 * Handles dashboard statistics and overview
 * 
 * Endpoints:
 * - GET /Admin/Dashboard/index  - Get dashboard overview
 * - GET /Admin/Dashboard/stats  - Get statistics
 */
class Dashboard extends Controller
{
    private $dbIndex = 2000;

    public function index()
    {
        $this->handleCors();
        
        $this->success([
            'message' => 'Admin Dashboard API',
            'endpoints' => [
                'stats' => '/Admin/Dashboard/stats',
            ]
        ]);
    }

    /**
     * Get dashboard statistics
     * GET /Admin/Dashboard/stats
     */
    public function stats()
    {
        $this->handleCors();

        // Example stats - customize based on your needs
        $stats = [
            'total_users' => $this->db($this->dbIndex)
                ->query("SELECT COUNT(*) as count FROM admin_users")
                ->row()->count ?? 0,
            'today' => date('Y-m-d'),
            'server_time' => $GLOBALS['now']
        ];

        $this->success($stats);
    }
}
