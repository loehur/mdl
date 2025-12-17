<?php

namespace App\Core;

/**
 * Route Handler
 * Supports nested controllers: Admin/Users, Salon/Product, etc.
 * URL Pattern: /{App}/{Controller}/{method}/{params...}
 * Example: /Admin/Users/list -> Controllers/Admin/Users.php -> list()
 */
class Route extends Controller
{
    protected $method = 'index';
    protected $param = [];
    protected $controllerPath = '';
    protected $controllerName = 'Base';
    protected $controller = null;

    public function __construct()
    {
        // Handle CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(200);
            exit;
        }

        $url = $this->parseUrl();
        
        if (empty($url)) {
            $this->loadController('Base');
            return;
        }

        // Try nested controller first: Controllers/{App}/{Controller}.php
        // URL: /Admin/Users/list -> Controllers/Admin/Users.php
        if (count($url) >= 2) {
            $nestedPath = $url[0] . '/' . $url[1];
            if ($this->controllerExists($nestedPath)) {
                $this->loadController($nestedPath, array_slice($url, 2));
                return;
            }
        }

        // Try single controller: Controllers/{Controller}.php
        // URL: /Base/index -> Controllers/Base.php
        if ($this->controllerExists($url[0])) {
            $this->loadController($url[0], array_slice($url, 1));
            return;
        }

        // Fallback to Base controller
        $this->loadController('Base');
    }

    private function parseUrl()
    {
        if (!isset($_GET['url']) || empty($_GET['url'])) {
            return [];
        }
        
        $url = filter_var(trim($_GET['url'], '/'), FILTER_SANITIZE_URL);
        return explode('/', $url);
    }

    private function controllerExists($path)
    {
        return file_exists('app/Controllers/' . $path . '.php');
    }

    private function loadController($path, $remaining = [])
    {
        $filePath = 'app/Controllers/' . $path . '.php';
        
        if (!file_exists($filePath)) {
            $filePath = 'app/Controllers/Base.php';
            $path = 'Base';
        }

        require_once $filePath;
        
        // Get class name from path (last part)
        $parts = explode('/', $path);
        $className = end($parts);
        
        // Build fully qualified class name with namespace
        $fullClassName = 'App\\Controllers\\' . str_replace('/', '\\', $path);
        
        $this->controller = new $fullClassName();

        // Determine method and params from remaining URL parts
        if (!empty($remaining)) {
            $potentialMethod = str_replace('-', '_', $remaining[0]);
            if (method_exists($this->controller, $potentialMethod)) {
                $this->method = $potentialMethod;
                array_shift($remaining);
            }
        }

        $this->param = array_values($remaining);

        // Call the method
        call_user_func_array([$this->controller, $this->method], $this->param);
    }
}
