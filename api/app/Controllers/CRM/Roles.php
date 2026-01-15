<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

class Roles extends Controller
{
    public function index()
    {
        $this->handleCors();
        
        // Fetch from Env or use Defaults (Safety fallback)
        $data = defined('\Env::CRM_USER_ROLES') ? \Env::CRM_USER_ROLES : [
            'admin' => ['DEV', 'AYAH', 'IBU', 'TABLET'],
            'driver' => ['DRIVER1', 'DRIVER2'],
            'crew' => ['3', '4', '5', '6', '10', '11', '12', '13', '14']
        ];
        
        $this->success($data, 'Role IDs retrieved successfully');
    }
}
