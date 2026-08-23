<?php

/**
 * @deprecated Pakai api/app/Helpers/Laundry/DeliveryTarifGrantCore.php
 */
$core = dirname(__DIR__, 2) . '/api/app/Helpers/Laundry/DeliveryTarifGrantCore.php';
if (!is_file($core)) {
    throw new RuntimeException('DeliveryTarifGrantCore tidak ditemukan: ' . $core);
}
require_once $core;
