<?php

class Route extends Controller
{
    protected $method       = 'index';
    protected $param        = [];
    protected $controller   = 'Antrian';

    public function __construct()
    {
        if (isset($_GET['url'])) {
            $url = explode('/', filter_var(trim($_GET['url']), FILTER_SANITIZE_URL));
        } else {
            $url[0] = $this->controller;
        }

        if (file_exists('app/Controllers/' . $url[0] . '.php')) {
            $this->controller = $url[0];
        } else {
            header("Location: " . URL::BASE_URL . "E/e/404");
            exit();
        }

        require_once 'app/Controllers/' . $this->controller . '.php';
        $this->controller =  new $this->controller;

        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
            }
        }

        //BUANG URL CONTROLER DAN METHOD UNTUK MENGAMBIL PARAMETER
        unset($url[0]);
        unset($url[1]);
        $this->param = $url;

        //PANGGIL CLASS(yg sudah di panggil init/core beserta fungsi dan parameter)
        call_user_func_array([$this->controller, $this->method], $this->param);
    }
}
