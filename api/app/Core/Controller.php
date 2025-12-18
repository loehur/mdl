<?php

namespace App\Core;

class Controller
{
    /**
     * Get Database Instance
     */
    public function db($db = 0)
    {
        return DB::getInstance($db);
    }

    // ============ RESPONSE HELPERS ============

    /**
     * Send JSON Response
     */
    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $this->setCorsHeaders();
        
        // Prevent caching
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($data);
        exit;
    }

    /**
     * Success Response
     */
    protected function success($data = null, $message = 'Success')
    {
        $this->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Error Response
     */
    protected function error($message = 'Error', $status = 400, $data = null)
    {
        $this->json([
            'status' => false,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Get POST JSON Body
     */
    protected function getBody()
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }

    /**
     * Get Request Method
     */
    protected function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if POST request
     */
    protected function isPost()
    {
        return $this->method() === 'POST';
    }

    /**
     * Check if GET request
     */
    protected function isGet()
    {
        return $this->method() === 'GET';
    }

    /**
     * Handle CORS preflight
     */
    protected function handleCors()
    {
        if ($this->method() === 'OPTIONS') {
            $this->setCorsHeaders();
            http_response_code(200);
            exit;
        }
    }

    /**
     * Set CORS Headers Consistently
     */
    protected function setCorsHeaders()
    {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
    }

    /**
     * Get Query Parameter
     */
    protected function query($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Validate Required Fields
     */
    protected function validate($data, $required = [])
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->error('Missing required fields: ' . implode(', ', $missing), 400);
        }
        
        return true;
    }
}
