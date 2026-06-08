<?php

/**
 * Salin file ini ke Env.php dan sesuaikan credential production.
 * Env.php tidak di-commit ke git (lihat api/.gitignore).
 */
class Env
{
    const MODE = 'pro'; // 'dev' | 'pro'
    const DB_HOST = 'localhost';

    /**
     * Hanya override index yang perlu diubah.
     * Index yang tidak ada di sini tetap pakai default dari DBC.php
     *
     * Index 5 = mdl_investasi (app Investasi PWA)
     */
    const DB_CREDENTIALS = [
        'pro' => [
            0 => ['db' => 'mdl_main', 'user' => 'mdl_main', 'pass' => 'ISI_PASSWORD'],
            1 => ['db' => 'mdl_laundry', 'user' => 'mdl_laundry', 'pass' => 'ISI_PASSWORD'],
            2 => ['db' => 'mdl_resto', 'user' => 'mdl_resto', 'pass' => 'ISI_PASSWORD'],
            3 => ['db' => 'mdl_water', 'user' => 'mdl_water', 'pass' => 'ISI_PASSWORD'],
            4 => ['db' => 'mdl_salon', 'user' => 'mdl_salon', 'pass' => 'ISI_PASSWORD'],
            5 => ['db' => 'mdl_investasi', 'user' => 'mdl_investasi', 'pass' => 'ISI_PASSWORD_INVESTASI'],
        ],
        'dev' => [
            5 => ['db' => 'mdl_investasi', 'user' => 'root', 'pass' => ''],
        ],
    ];
}
