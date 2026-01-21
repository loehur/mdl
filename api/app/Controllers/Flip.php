<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Flip as FlipModel;

/**
 * Flip Controller
 * Handles Flip payment gateway operations
 */
class Flip extends Controller
{
    /**
     * Get list of available banks from Flip
     * Endpoint: /Flip/banks
     * Method: GET
     */
    public function banks()
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $flip = new FlipModel();
            $result = $flip->getBanks();

            if ($result['status']) {
                $this->success($result['data'], 'Successfully retrieved bank list');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
