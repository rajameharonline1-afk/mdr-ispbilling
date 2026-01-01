<?php
/**
 * mac_lookup.php — JSON OUI DB ভিত্তিক MAC → Vendor
 */
function load_oui_db(): array {
    static $db = null;
    if ($db === null) {
        $path = __DIR__ . '/oui_vendors.json';
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $db = json_decode($json, true) ?: [];
        } else {
            $csvPath = __DIR__ . '/../assets/mac_vendors.csv';
            $db = [];
            if (file_exists($csvPath)) {
                if (($fh = fopen($csvPath, 'r')) !== false) {
                    while (($row = fgetcsv($fh)) !== false) {
                        if (count($row) < 2) continue;
                        $prefixRaw = strtoupper(preg_replace('/[^0-9A-F]/i', '', (string)$row[0]));
                        if (strlen($prefixRaw) < 6) continue;
                        $prefix = substr($prefixRaw, 0, 6);
                        $vendor = trim((string)$row[1]);
                        if ($vendor === '' || $vendor === 'vendor') continue;
                        if (!isset($db[$prefix])) {
                            $db[$prefix] = $vendor;
                        }
                    }
                    fclose($fh);
                }
            }
        }
    }
    return $db;
}
function mac_vendor_lookup(string $mac): string {
    $clean  = strtoupper(preg_replace('/[^0-9A-F]/', '', $mac));
    if (strlen($clean) < 6) return 'Unknown Vendor';
    $prefix = substr($clean, 0, 6);
    $db = load_oui_db();
    return $db[$prefix] ?? 'Unknown Vendor';
}
