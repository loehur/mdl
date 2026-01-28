<?php
/**
 * Script to generate RSA key pair for Doku SNAP API
 * Run this once to generate private and public keys
 */

// Configuration for key generation
$config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

// Generate private key
$privateKey = openssl_pkey_new($config);

if ($privateKey === false) {
    die("Failed to generate private key: " . openssl_error_string());
}

// Extract private key
openssl_pkey_export($privateKey, $privateKeyPem);

// Extract public key
$publicKeyDetails = openssl_pkey_get_details($privateKey);
$publicKeyPem = $publicKeyDetails['key'];

// Save keys to files
file_put_contents(__DIR__ . '/doku_private.key', $privateKeyPem);
file_put_contents(__DIR__ . '/doku_public.key', $publicKeyPem);

echo "=== RSA Key Pair Generated Successfully! ===\n\n";

echo "PRIVATE KEY (Save this securely, never share!):\n";
echo "File saved to: " . __DIR__ . "/doku_private.key\n";
echo str_repeat("=", 50) . "\n";
echo $privateKeyPem;
echo str_repeat("=", 50) . "\n\n";

echo "PUBLIC KEY (Upload this to Doku Dashboard):\n";
echo "File saved to: " . __DIR__ . "/doku_public.key\n";
echo str_repeat("=", 50) . "\n";
echo $publicKeyPem;
echo str_repeat("=", 50) . "\n\n";

echo "NEXT STEPS:\n";
echo "1. Copy the PUBLIC KEY above\n";
echo "2. Go to Doku Dashboard > API Keys > Edit Merchant Public Key\n";
echo "3. Paste the PUBLIC KEY and save\n";
echo "4. Copy the PRIVATE KEY to your Env.php or keep it in doku_private.key file\n";
echo "5. Delete this script (generate_rsa_keys.php) for security\n";
