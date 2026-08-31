<?php

namespace App\Helpers\WaDesk;

/**
 * Download media from Kirimin/provider URL and store locally for WaDesk chat.
 */
class WaMediaHelper
{
    public static function downloadAndSave(?string $remoteUrl, ?string $messageId = null, ?string $mimeHint = null, ?string $authHeader = null): ?string
    {
        if ($remoteUrl === null || trim($remoteUrl) === '') {
            return null;
        }

        $remoteUrl = trim($remoteUrl);
        if (!preg_match('#^https?://#i', $remoteUrl)) {
            return null;
        }

        $mediaData = self::fetchUrl($remoteUrl, $authHeader);
        if ($mediaData === null || $mediaData === '') {
            if (class_exists('\Log')) {
                \Log::write('WaDesk media download failed url=' . $remoteUrl, 'wadesk', 'Media');
            }

            return null;
        }

        $mime = $mimeHint ?: self::guessMime($remoteUrl, $mediaData);
        $ext = self::mime2ext($mime, $remoteUrl);

        $relativePath = '/uploads/wadesk/' . date('Y/m');
        $baseDir = __DIR__ . '/../../../uploads/wadesk/' . date('Y/m');
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $baseName = $messageId !== null && $messageId !== ''
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $messageId)
            : '';
        if ($baseName === null || $baseName === '') {
            $baseName = bin2hex(random_bytes(8));
        }

        $filename = $baseName . '.' . $ext;
        $savePath = $baseDir . '/' . $filename;

        $saved = false;
        if ($mime !== null && str_starts_with($mime, 'image/') && $mime !== 'image/gif') {
            $saved = self::saveCompressedImage($mediaData, $baseDir, $baseName, $savePath, $filename);
        }

        if (!$saved) {
            file_put_contents($savePath, $mediaData);
            $filename = basename($savePath);
        }

        return self::baseUrl() . $relativePath . '/' . $filename;
    }

    private static function fetchUrl(string $url, ?string $authHeader = null): ?string
    {
        $headers = [];
        if ($authHeader !== null && trim($authHeader) !== '') {
            $headers[] = trim($authHeader);
        }

        if ($headers === []) {
            $data = @file_get_contents($url);
            if ($data !== false && $data !== '') {
                return $data;
            }
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'MdL-WaDesk/1.0',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        if ($headers !== []) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $data === '' || $code >= 400) {
            return null;
        }

        return $data;
    }

    private static function saveCompressedImage(
        string $mediaData,
        string $baseDir,
        string $baseName,
        string $savePath,
        string &$filename
    ): bool {
        try {
            $im = @imagecreatefromstring($mediaData);
            if (!$im) {
                return false;
            }

            $width = imagesx($im);
            $height = imagesy($im);
            $maxDim = 1280;

            if ($width > $maxDim || $height > $maxDim) {
                $ratio = $width / $height;
                if ($ratio > 1) {
                    $newWidth = $maxDim;
                    $newHeight = (int) round($maxDim / $ratio);
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int) round($maxDim * $ratio);
                }

                $newIm = imagecreatetruecolor($newWidth, $newHeight);
                $white = imagecolorallocate($newIm, 255, 255, 255);
                imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $white);
                imagecopyresampled($newIm, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($im);
                $im = $newIm;
            }

            $filename = $baseName . '.jpg';
            $savePath = $baseDir . '/' . $filename;
            imagejpeg($im, $savePath, 75);
            imagedestroy($im);

            return is_file($savePath);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('WaDesk image compress failed: ' . $e->getMessage(), 'wadesk', 'Media');
            }

            return false;
        }
    }

    private static function guessMime(string $url, string $data): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_buffer($finfo, $data);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $map = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'mp4' => 'video/mp4',
                'ogg' => 'audio/ogg',
                'pdf' => 'application/pdf',
            ];
            if (isset($map[$ext])) {
                return $map[$ext];
            }
        }

        return null;
    }

    private static function mime2ext(?string $mime, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'application/pdf' => 'pdf',
        ];

        if ($mime !== null && isset($map[$mime])) {
            return $map[$mime];
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== '') {
                return preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
            }
        }

        return 'bin';
    }

    private static function baseUrl(): string
    {
        if (class_exists('\App\Config\Env') && defined('\App\Config\Env::BASE_URL')) {
            return rtrim((string) \App\Config\Env::BASE_URL, '/');
        }
        if (defined('\Env::BASE_URL')) {
            return rtrim((string) \Env::BASE_URL, '/');
        }

        return 'https://api.nalju.com';
    }
}
