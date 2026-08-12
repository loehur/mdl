<?php

/**
 * Admin Tools — Intent Lab (cek klasifikasi intent chat WA)
 */
class IntentLab extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Intent Lab'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/intent_lab', []);
    }

    /**
     * POST JSON { text } → proxy ke API IntentCheck
     */
    public function check()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $raw = file_get_contents('php://input');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if ($data === []) {
            $data = $_POST;
        }

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            // fallback form-urlencoded / multipart
            $text = trim((string) ($_POST['text'] ?? ''));
        }
        if ($text === '') {
            echo json_encode(['ok' => 0, 'message' => 'Teks kosong'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $api = $this->helper('IntentCheckApi');
        $res = $api->check($text);
        if (!isset($res['ok'])) {
            $res['ok'] = false;
        }
        // Samakan flag ok numerik untuk UI lama bila perlu
        if ($res['ok'] === true) {
            $res['ok'] = 1;
        } elseif ($res['ok'] === false) {
            $res['ok'] = 0;
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
