<?php

/**
 * Bridge ke App\Helpers\CRM\FonnteService (api/) agar laundry bisa pakai service yang sama.
 * PHP 8.0+
 *
 * Usage:
 *   $this->helper('FonnteService');
 *   $fonnte = FonnteService::instance();
 *   $fonnte->sendToGroup($groupId, $message);
 *   FonnteService::driverGroupId();
 *   FonnteService::cabangGroupId($idCabang, $dbRowOrNull);
 */
class FonnteService
{
    /** @var \App\Helpers\CRM\FonnteService|null */
    private static $instance = null;

    /** @var bool */
    private static $booted = false;

    /** @var string|null */
    private static $bootError = null;

    /**
     * Load Env + App\Config\Fonnte + App\Helpers\CRM\FonnteService dari folder api.
     * @throws Exception
     */
    public static function boot(): void
    {
        if (self::$booted) {
            if (self::$bootError !== null) {
                throw new Exception(self::$bootError);
            }
            return;
        }
        self::$booted = true;

        $apiApp = self::resolveApiAppPath();
        if ($apiApp === '') {
            self::$bootError = 'Path api/app tidak ditemukan (cek struktur folder mdl/api)';
            throw new Exception(self::$bootError);
        }

        $envFile = $apiApp . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Env.php';
        $cfgFile = $apiApp . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Fonnte.php';
        $svcFile = $apiApp . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'CRM' . DIRECTORY_SEPARATOR . 'FonnteService.php';

        if (!is_file($cfgFile) || !is_file($svcFile)) {
            self::$bootError = 'File Fonnte config/service tidak lengkap di api/app';
            throw new Exception(self::$bootError);
        }

        if (is_file($envFile) && !class_exists('Env', false)) {
            require_once $envFile;
        }

        if (!class_exists('\\App\\Config\\Fonnte', false)) {
            require_once $cfgFile;
        }
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
            require_once $svcFile;
        }

        if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
            self::$bootError = 'Gagal memuat App\\Helpers\\CRM\\FonnteService';
            throw new Exception(self::$bootError);
        }
    }

    /**
     * @return \App\Helpers\CRM\FonnteService
     * @throws Exception
     */
    public static function instance()
    {
        self::boot();
        if (self::$instance === null) {
            self::$instance = new \App\Helpers\CRM\FonnteService();
        }
        return self::$instance;
    }

    public static function driverGroupId(): string
    {
        self::boot();
        return (string) \App\Config\Fonnte::getDriverGroupId();
    }

    public static function estimasiGroupId(): string
    {
        self::boot();
        return (string) \App\Config\Fonnte::getEstimasiGroupId();
    }

    /**
     * Group cabang dari row cabang.id_group_fonnte, fallback estimasi group.
     * @param array|null $cabangRow
     */
    public static function cabangGroupId($cabangRow = null): string
    {
        if (is_array($cabangRow)) {
            $fromCabang = trim((string) ($cabangRow['id_group_fonnte'] ?? ''));
            if ($fromCabang !== '' && preg_match('/@g\.us$/i', $fromCabang)) {
                return $fromCabang;
            }
        }
        return self::estimasiGroupId();
    }

    /**
     * @return array{success:bool,data:mixed,error:?string}
     */
    public static function sendToGroup(string $groupId, string $message, array $options = []): array
    {
        $svc = self::instance();
        $res = $svc->sendToGroup($groupId, $message, $options);
        return is_array($res) ? $res : ['success' => false, 'data' => null, 'error' => 'Invalid response'];
    }

    /**
     * @return array{success:bool,data:mixed,error:?string}
     */
    public static function sendMessage(string $phone, string $message, array $options = []): array
    {
        $svc = self::instance();
        $res = $svc->sendMessage($phone, $message, $options);
        return is_array($res) ? $res : ['success' => false, 'data' => null, 'error' => 'Invalid response'];
    }

    private static function resolveApiAppPath(): string
    {
        // Helper di laundry/app/Helper → ../../../api/app = mdl/api/app
        $candidates = [
            __DIR__ . '/../../../api/app',
            dirname(__DIR__, 3) . '/api/app',
            dirname(__DIR__, 2) . '/../api/app',
        ];
        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real !== false && is_dir($real) && is_file($real . '/Helpers/CRM/FonnteService.php')) {
                return $real;
            }
        }
        return '';
    }
}
