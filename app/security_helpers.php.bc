<?php
// /app/security_helpers.php
// ✅ Secure, Compatible & Stable Encryption/Decryption System
// Compatible with olt_add.php, olt_edit.php, onu_monitor.php

// ------------------------------------------------------------------
// CONFIGURATION
// ------------------------------------------------------------------

// Encryption Algorithm
define('ENCRYPTION_CIPHER', 'AES-256-CBC');

// ✅ Master Encryption Key (64 hex chars = 32 bytes = 256-bit)
// 👉 প্রোডাকশনে যাওয়ার আগে এটা নিজের কী দিয়ে বদলে দাও:
//    php -r "echo bin2hex(random_bytes(32));"
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', '3a882f550300684b48e265ae290b05ca'); 
    // উদাহরণ: 3a882f550300684b48e265ae290b05ca... (মোট 64 হেক্স ক্যারেক্টার)
}

// ------------------------------------------------------------------
// ENCRYPTION FUNCTION
// ------------------------------------------------------------------

/**
 * Encrypts plain text using AES-256-CBC.
 *
 * @param string $plaintext
 * @param string $key
 * @return string|false Base64 encoded string (IV + encrypted data) or false
 */
function encrypt_password(string $plaintext, string $key)
{
    // খালি স্ট্রিং encrypt করার দরকার নেই
    if ($plaintext === '') return false;

    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    if ($iv === false) return false;

    $encrypted = openssl_encrypt(
        $plaintext,
        ENCRYPTION_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    if ($encrypted === false) return false;

    // DB-safe storage: base64( IV || CIPHERTEXT )
    return base64_encode($iv . $encrypted);
}

// ------------------------------------------------------------------
// DECRYPTION FUNCTION
// ------------------------------------------------------------------

/**
 * Decrypts Base64 encoded encrypted string.
 *
 * @param string $ciphertext_b64
 * @param string $key
 * @return string|false Decrypted text or false on failure
 */
function decrypt_password(string $ciphertext_b64, string $key)
{
    if ($ciphertext_b64 === '') return false;

    // Decode Base64 safely
    $data = base64_decode($ciphertext_b64, true);
    if ($data === false) {
        return false; // invalid base64
    }

    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    if (strlen($data) <= $iv_length) {
        return false; // corrupted data
    }

    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    $decrypted = openssl_decrypt(
        $ciphertext,
        ENCRYPTION_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($decrypted === false) {
        return false;
    }

    // mbstring নাও থাকতে পারে; থাকলে UTF-8 ভ্যালিডেশন করবে, নাহলে স্কিপ করবে
    if (function_exists('mb_check_encoding') && !mb_check_encoding($decrypted, 'UTF-8')) {
        // UTF-8 না হলেও আমরা 그대로 রিটার্ন করতে পারি—পাসওয়ার্ডে যেকোনো বাইট থাকতে পারে।
        // চাইলে এখানে false রিটার্ন করতে পারো; আমি permissive রাখলাম।
    }

    // 🔸 trim() না দিয়ে আসল ভ্যালু 그대로 রিটার্ন করা হলো
    return $decrypted;
}

// ------------------------------------------------------------------
// EXTRA: KEY GENERATOR
// ------------------------------------------------------------------

/**
 * Generates a 64-hex-character strong encryption key (32 bytes / 256-bit).
 * Use this to generate a new key (run once and paste into ENCRYPTION_KEY).
 *
 * @return string
 */
function generate_encryption_key(): string
{
    return bin2hex(random_bytes(32)); // 64 hex chars = 32 bytes = 256-bit
}

// ------------------------------------------------------------------
// TEST HELPER (optional - disable on production)
// ------------------------------------------------------------------
if (isset($_GET['test_enc']) && ($_ENV['APP_ENV'] ?? '') === 'local') {
    $sample = 'admin';
    $enc = encrypt_password($sample, ENCRYPTION_KEY);
    $dec = decrypt_password($enc, ENCRYPTION_KEY);

    echo "<pre>";
    echo "🔐 Encryption Key: " . ENCRYPTION_KEY . "\n";
    echo "Plaintext: $sample\n";
    echo "Encrypted: $enc\n";
    echo "Decrypted: $dec\n";
    echo "</pre>";
}
