<?php

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
            ]
        ], 'API is running');
    }
}
