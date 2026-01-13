<?php
/**
 * Script to create subscription table for Beauty Salon
 * Run: php scripts/create_subscription_table.php
 */

$conn = new mysqli('localhost', 'root', '', 'mdl_salon');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to mdl_salon database\n";

// Create subscriptions table
$sql = "CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `salon_id` VARCHAR(20) NOT NULL,
    `plan` ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 60000.00,
    `status` ENUM('trial', 'active', 'expired', 'suspended', 'cancelled') DEFAULT 'trial',
    `trial_days` INT NOT NULL DEFAULT 14,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `last_payment_date` DATETIME NULL,
    `last_payment_amount` DECIMAL(12,2) NULL,
    `payment_ref` VARCHAR(100) NULL,
    `auto_renew` TINYINT(1) DEFAULT 1,
    `reminder_sent` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_salon` (`salon_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table 'subscriptions' created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Create subscription_payments table for payment history
$sql2 = "CREATE TABLE IF NOT EXISTS `subscription_payments` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `salon_id` VARCHAR(20) NOT NULL,
    `subscription_id` INT(11) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_method` VARCHAR(50) NULL,
    `payment_ref` VARCHAR(100) NULL,
    `payment_status` ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_salon` (`salon_id`),
    INDEX `idx_subscription` (`subscription_id`),
    INDEX `idx_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql2) === TRUE) {
    echo "Table 'subscription_payments' created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Add subscription columns to salon table if not exists
$checkColumn = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'mdl_salon' 
                AND TABLE_NAME = 'salon' 
                AND COLUMN_NAME = 'subscription_status'";
$result = $conn->query($checkColumn);

if ($result && $result->num_rows == 0) {
    $alterSql = "ALTER TABLE `salon` 
                 ADD COLUMN `subscription_status` ENUM('trial', 'active', 'expired', 'suspended') DEFAULT 'trial' AFTER `alamat_salon`,
                 ADD COLUMN `subscription_end_date` DATE NULL AFTER `subscription_status`";
    
    if ($conn->query($alterSql) === TRUE) {
        echo "Added subscription columns to 'salon' table\n";
    } else {
        echo "Error altering salon table: " . $conn->error . "\n";
    }
} else {
    echo "Subscription columns already exist in 'salon' table\n";
}

$conn->close();
echo "\nDone!\n";
