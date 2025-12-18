<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Base Controller
 * Controller default jika tidak ada route yang cocok
 */
class Base extends Controller
{
    public function index()
    {
        $this->handleCors();
        
        $this->success([
            'name' => 'MDL Backends API',
            'version' => '1.0.0',
            'endpoints' => [
                'admin' => '/Admin/{method}',
                'base' => '/Base atau /',
            ],
            'base_url' => 'http://localhost/mdl/api/'
        ], 'API is running');
    }
}
