<?php
// /tools/test_bkash_webhook.php
// CLI helper to fire a sample bKash webhook JSON into /api/bkash_webhook.php
// Usage: php tools/test_bkash_webhook.php [endpoint] [token] [client_code]

declare(strict_types=1);

$endpoint = $argv[1] ?? 'http://localhost/api/bkash_webhook.php';
$token    = $argv[2] ?? 'CHANGE_ME';
$client   = $argv[3] ?? 'CLIENT123';
$hmac     = getenv('BKASH_WEBHOOK_HMAC') ?: '';

$payload = [
  'trxID'                 => 'T' . date('YmdHis'),
  'amount'                => 500.00,
  'payerReference'        => $client,
  'merchantInvoiceNumber' => 'INV-' . date('His'),
  'transactionStatus'     => 'COMPLETED',
];

$ch = curl_init();
$headers = ['Content-Type: application/json', 'X-WebHook-Token: ' . $token];
if ($hmac !== '') {
  $sig = base64_encode(hash_hmac('sha256', json_encode($payload), $hmac, true));
  $headers[] = 'X-Bkash-Signature: ' . $sig;
}

curl_setopt_array($ch, [
  CURLOPT_URL            => $endpoint,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => $headers,
  CURLOPT_POSTFIELDS     => json_encode($payload),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HEADER         => true,
  CURLOPT_TIMEOUT        => 15,
]);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$info  = curl_getinfo($ch);
curl_close($ch);

echo "POST $endpoint\n";
echo "Status: " . ($info['http_code'] ?? 'NA') . "\n";
if ($errno) {
  echo "cURL error: $errno\n";
}
echo "Response:\n";
echo $resp . "\n";
