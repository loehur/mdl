<?php

/**
 * Admin Tools — WhatsApp gateway (fonnte_server / Baileys)
 * Menu: Tools → WhatsApp
 */
class WaGateway extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'WhatsApp Gateway'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/whatsapp', []);
    }

    public function status()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::status();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    public function qr()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::qr();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    public function logout()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::logout();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
