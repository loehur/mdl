<?php
/**
 * Generate RSA Key Pair for Doku SNAP API
 */

// Generate private key
$privateKey = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA
]);

// Export private key
openssl_pkey_export($privateKey, $privateKeyPEM);

// Get public key
$details = openssl_pkey_get_details($privateKey);
$publicKeyPEM = $details['key'];

// Save to files
file_put_contents('doku_private.key', $privateKeyPEM);
file_put_contents('doku_public.key', $publicKeyPEM);

// Display results
echo "========== DOKU RSA KEYS GENERATED ==========\n\n";
echo "PUBLIC KEY (Upload to Doku Dashboard):\n\n";
echo $publicKeyPEM;
echo "\n\n";
echo "PRIVATE KEY (Keep secret!):\n\n";
echo $privateKeyPEM;
echo "\n\n========================================\n";
echo "Keys saved to doku_private.key and doku_public.key\n";
