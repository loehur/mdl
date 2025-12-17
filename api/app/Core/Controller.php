<?php

class Controller
{
    /**
     * Get Database Instance
     */
    public function db($db = 0)
    {
        require_once "app/Core/DB.php";
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
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
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
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(200);
            exit;
        }
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
