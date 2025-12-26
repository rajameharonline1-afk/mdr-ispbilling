<?php
// /app/bkash_tokenized.php
// Purpose: bKash Tokenized Checkout (PGW) helper: token grant, create, execute
// Notes: Reads credentials from settings table first (settings_store.php), then constants/env.
// Language: Bengali responses are handled at API/page level.

declare(strict_types=1);

require_once __DIR__ . '/settings_store.php';

if (!function_exists('bkash_tz_cfg')) {
  function bkash_tz_cfg(string $key, $default = null) {
    if (function_exists('settings_get')) {
      $sv = settings_get($key, null);
      if ($sv !== null && $sv !== '') return $sv;
    }
    if (defined($key)) return constant($key);
    $env = getenv($key);
    if ($env !== false && $env !== '') return $env;
    if (isset($GLOBALS['CONFIG'][$key])) return $GLOBALS['CONFIG'][$key];
    return $default;
  }
}

if (!function_exists('bkash_tz_http')) {
  function bkash_tz_http(string $url, array $headers = [], ?array $json = null, int $timeout = 20): array {
    $ch = curl_init($url);
    $hdrs = $headers;
    if (!array_filter($hdrs, fn($h) => stripos($h, 'content-type:') === 0)) {
      $hdrs[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => $timeout,
      CURLOPT_HTTPHEADER     => $hdrs,
      CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($json !== null) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new RuntimeException("bKash HTTP error: $err");
    $data = json_decode((string)$body, true);
    if (!is_array($data)) $data = ['raw' => $body];
    return ['code' => $code, 'data' => $data];
  }
}

if (!function_exists('bkash_tz_base')) {
  function bkash_tz_base(): string {
    $base = trim((string)bkash_tz_cfg('BKASH_BASE_URL', ''));
    return rtrim($base, '/');
  }
}

if (!function_exists('bkash_tz_cache_path')) {
  function bkash_tz_cache_path(): string {
    return dirname(__DIR__) . '/storage/bkash_tokenized_token.json';
  }
}

if (!function_exists('bkash_tz_url_candidates')) {
  function bkash_tz_url_candidates(string $suffix): array {
    $base = bkash_tz_base();
    if ($base === '') return [];

    $suffix = '/' . ltrim($suffix, '/');

    // Prefer tokenized endpoints. Fall back to older "/checkout/..." if needed.
    // Also support when user mistakenly includes "/tokenized/checkout" at the end of base.
    $candidates = [];
    $candidates[] = $base . '/tokenized/checkout' . $suffix;
    $candidates[] = $base . '/checkout' . $suffix;
    $candidates[] = $base . $suffix;

    // de-dupe
    return array_values(array_unique($candidates));
  }
}

if (!function_exists('bkash_tz_require_config')) {
  function bkash_tz_require_config(): void {
    $need = ['BKASH_BASE_URL','BKASH_USERNAME','BKASH_PASSWORD','BKASH_APP_KEY','BKASH_APP_SECRET'];
    foreach ($need as $k) {
      $v = (string)bkash_tz_cfg($k, '');
      if ($v === '') throw new RuntimeException("bKash কনফিগার করা নেই: $k");
    }
  }
}

if (!function_exists('bkash_tz_get_token')) {
  function bkash_tz_get_token(bool $forceFresh = false): array {
    bkash_tz_require_config();

    $cacheFile = bkash_tz_cache_path();
    if (!$forceFresh && is_file($cacheFile)) {
      $raw = file_get_contents($cacheFile);
      $cached = json_decode((string)$raw, true);
      if (is_array($cached)) {
        $token = (string)($cached['id_token'] ?? '');
        $expAt = (int)($cached['expires_at'] ?? 0);
        if ($token !== '' && $expAt > (time() + 60)) {
          return $cached;
        }
      }
    }

    $user   = (string)bkash_tz_cfg('BKASH_USERNAME', '');
    $pass   = (string)bkash_tz_cfg('BKASH_PASSWORD', '');
    $appKey = (string)bkash_tz_cfg('BKASH_APP_KEY', '');
    $secret = (string)bkash_tz_cfg('BKASH_APP_SECRET', '');

    $headers = [
      'username: ' . $user,
      'password: ' . $pass,
    ];
    $payload = [
      'app_key'    => $appKey,
      'app_secret' => $secret,
    ];

    $last = null;
    $bestErr = '';
    $bestUrl = '';
    foreach (bkash_tz_url_candidates('/token/grant') as $url) {
      $last = bkash_tz_http($url, $headers, $payload);
      $d = $last['data'];
      $token = (string)($d['id_token'] ?? $d['token'] ?? '');
      if ($token !== '') {
        $expiresIn = (int)($d['expires_in'] ?? 3600);
        $cache = [
          'id_token'    => $token,
          'token_type'  => (string)($d['token_type'] ?? 'Bearer'),
          'expires_in'  => $expiresIn,
          'expires_at'  => time() + max(60, $expiresIn),
          'raw'         => $d,
          'used_url'    => $url,
        ];
        @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $cache;
      }
      // Capture the most meaningful error message for reporting.
      $msg = (string)($d['statusMessage'] ?? $d['message'] ?? $d['errorMessage'] ?? '');
      if ($msg !== '' && $bestErr === '') {
        $bestErr = $msg;
        $bestUrl = $url;
      }
    }

    if ($bestErr !== '') {
      throw new RuntimeException('bKash টোকেন পাওয়া যায়নি। ' . $bestErr . ($bestUrl ? " (URL: $bestUrl)" : ''));
    }
    $msg = $last ? json_encode($last['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    throw new RuntimeException('bKash টোকেন পাওয়া যায়নি। ' . ($msg ? "Response: $msg" : ''));
  }
}

if (!function_exists('bkash_tz_create_payment')) {
  function bkash_tz_create_payment(array $req): array {
    bkash_tz_require_config();
    $token = bkash_tz_get_token();

    $appKey = (string)bkash_tz_cfg('BKASH_APP_KEY', '');
    $headers = [
      'Authorization: Bearer ' . $token['id_token'],
      'X-APP-Key: ' . $appKey,
      'Accept: application/json',
    ];

    $last = null;
    foreach (bkash_tz_url_candidates('/create') as $url) {
      $last = bkash_tz_http($url, $headers, $req);
      $d = $last['data'];
      if (!empty($d['bkashURL']) && !empty($d['paymentID'])) {
        $d['_used_url'] = $url;
        return $d;
      }
      // If token expired, try once with fresh token
      if (($d['message'] ?? '') === 'Unauthenticated.' || ($d['errorCode'] ?? '') === '401') {
        $token = bkash_tz_get_token(true);
        $headers[0] = 'Authorization: Bearer ' . $token['id_token'];
      }
    }

    $msg = $last ? json_encode($last['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    throw new RuntimeException('bKash create payment ব্যর্থ। ' . ($msg ? "Response: $msg" : ''));
  }
}

if (!function_exists('bkash_tz_execute_payment')) {
  function bkash_tz_execute_payment(string $paymentID): array {
    bkash_tz_require_config();
    $token = bkash_tz_get_token();
    $appKey = (string)bkash_tz_cfg('BKASH_APP_KEY', '');
    $headers = [
      'Authorization: Bearer ' . $token['id_token'],
      'X-APP-Key: ' . $appKey,
      'Accept: application/json',
    ];
    $payload = ['paymentID' => $paymentID];

    $last = null;
    foreach (bkash_tz_url_candidates('/execute') as $url) {
      $last = bkash_tz_http($url, $headers, $payload);
      $d = $last['data'];
      if (!empty($d['trxID']) || !empty($d['transactionStatus'])) {
        $d['_used_url'] = $url;
        return $d;
      }
      if (($d['message'] ?? '') === 'Unauthenticated.' || ($d['errorCode'] ?? '') === '401') {
        $token = bkash_tz_get_token(true);
        $headers[0] = 'Authorization: Bearer ' . $token['id_token'];
      }
    }

    $msg = $last ? json_encode($last['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    throw new RuntimeException('bKash execute payment ব্যর্থ। ' . ($msg ? "Response: $msg" : ''));
  }
}
