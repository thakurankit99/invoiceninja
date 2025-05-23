<?php
/**
 * Generate a secure APP_KEY for Invoice Ninja
 * Run with: php generate_app_key.php
 */

// Generate a random 32-byte key
$key = random_bytes(32);

// Encode it to base64
$base64Key = base64_encode($key);

echo "Your APP_KEY is: base64:{$base64Key}\n";
echo "Add this to your environment variables on Render.com\n"; 