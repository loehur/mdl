<?php

class Pwa extends Controller
{
    public function manifest()
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        $base = URL::BASE_URL;

        echo json_encode([
            'id' => $base,
            'name' => 'MDL Laundry',
            'short_name' => 'MDL',
            'description' => 'Sistem manajemen laundry MDL',
            'start_url' => $base . 'Login',
            'scope' => $base,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#ffc107',
            'orientation' => 'portrait',
            'lang' => 'id',
            'icons' => [
                [
                    'src' => URL::IN_ASSETS . 'icon/j-icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => URL::IN_ASSETS . 'icon/j-icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function sw()
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . URL::BASE_URL);
        header('Cache-Control: no-cache');
        readfile(__DIR__ . '/../../sw.js');
        exit;
    }
}
