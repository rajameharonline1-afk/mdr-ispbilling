<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function col_exists(PDO $pdo, string $tbl, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function pick_col(PDO $pdo, string $tbl, array $cands): ?string {
    foreach ($cands as $c) if (col_exists($pdo, $tbl, $c)) return $c;
    return null;
}
function get_next_client_code(PDO $pdo): string {
    if (!col_exists($pdo, 'clients', 'client_code')) return '';
    $stmt_last = $pdo->query("SELECT client_code FROM clients WHERE client_code IS NOT NULL ORDER BY id DESC LIMIT 1");
    $last_client = $stmt_last->fetch(PDO::FETCH_ASSOC);
    if ($last_client && preg_match('/CL(\d+)/', (string)$last_client['client_code'], $matches)) {
        $next_number = intval($matches[1]) + 1;
    } else {
        $next_number = 1;
    }
    return 'CL' . str_pad((string)$next_number, 4, '0', STR_PAD_LEFT);
}

function client_code_from_pppoe(string $pppoe_id): string {
    $digits = preg_replace('/\D+/', '', $pppoe_id);
    if ($digits === '') return '';
    return substr($digits, -4);
}

$API = new RouterosAPI();
$API->debug = false;

// সক্রিয় MikroTik রাউটার আনা
$routerCols = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$hasType = in_array('type', $routerCols, true);
$hasStatus = in_array('status', $routerCols, true);
$where = [];
if ($hasType)   $where[] = "type='mikrotik'";
if ($hasStatus) $where[] = "status=1";
$sqlRouters = "SELECT id, name, ip, username, password, api_port FROM routers";
if ($where) $sqlRouters .= " WHERE ".implode(' AND ', $where);
$routers = $pdo->query($sqlRouters)->fetchAll(PDO::FETCH_ASSOC);

if (!$routers) {
    die("❌ No active MikroTik routers found in database.\n");
}

// (বাংলা) optional limit (CLI: --limit=200, GET: limit=200)
function cli_get_limit(): ?int {
    global $argv;
    if (PHP_SAPI !== 'cli' || empty($argv)) return null;
    foreach ($argv as $arg) {
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) return (int)$m[1];
    }
    return null;
}
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)(cli_get_limit() ?? 1000);
if ($limit < 0) $limit = 0;

function normalize_date(?string $v): ?string {
    if (!$v) return null;
    $v = trim($v);
    $ts = strtotime($v);
    if (!$ts) return null;
    return date('Y-m-d', $ts);
}

function parse_comment(string $comment): array {
    $out = [];
    $lines = preg_split('/\r\n|\r|\n|\s*\|\s*/', $comment);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^Client Code:\s*(.*)$/i', $line, $m)) $out['client_code'] = trim($m[1]);
        elseif (preg_match('/^Client Name:\s*(.*)$/i', $line, $m)) $out['name'] = trim($m[1]);
        elseif (preg_match('/^Contact Number:\s*(.*)$/i', $line, $m)) $out['mobile'] = trim($m[1]);
        elseif (preg_match('/^Zone Name:\s*(.*)$/i', $line, $m)) $out['area'] = trim($m[1]);
        elseif (preg_match('/^Present Address:\s*(.*)$/i', $line, $m)) $out['address'] = trim($m[1]);
        elseif (preg_match('/^Joining Date:\s*(.*)$/i', $line, $m)) $out['join_date'] = trim($m[1]);
        elseif (preg_match('/^Package Name:\s*(.*)$/i', $line, $m)) $out['package_name'] = trim($m[1]);
        elseif (preg_match('/^Monthly Bill:\s*(.*)$/i', $line, $m)) $out['monthly_bill'] = trim($m[1]);
        elseif (preg_match('/^Bill Expiry Date:\s*(.*)$/i', $line, $m)) $out['expiry_date'] = trim($m[1]);
    }
    return $out;
}

function build_comment(array $data): string {
    $lines = [];
    $code = trim((string)($data['client_code'] ?? ''));
    if ($code === '' && !empty($data['pppoe_id'])) {
        $digits = preg_replace('/\D+/', '', (string)$data['pppoe_id']);
        if ($digits !== '') $code = substr($digits, -4);
    }
    $map = [
        'client_code' => 'Client Code',
        'name' => 'Client Name',
        'mobile' => 'Contact Number',
        'area' => 'Zone Name',
        'address' => 'Present Address',
        'join_date' => 'Joining Date',
        'package_name' => 'Package Name',
        'monthly_bill' => 'Monthly Bill',
        'expiry_date' => 'Bill Expiry Date',
    ];
    foreach ($map as $k => $label) {
        $val = $k === 'client_code' ? $code : trim((string)($data[$k] ?? ''));
        $lines[] = $label.': '.$val;
    }
    return implode(' | ', $lines);
}
function normalize_client_status(string $raw): string {
    $val = strtolower(trim($raw));
    $allowed = ['active','inactive','expired','pending','left'];
    return in_array($val, $allowed, true) ? $val : 'pending';
}

foreach ($routers as $router) {
    echo "=============================\n";
    echo "📡 Connecting to Router: {$router['name']} ({$router['ip']})\n";

    $port = (int)($router['api_port'] ?? 8728) ?: 8728;
    if ($API->connect($router['ip'], $router['username'], $router['password'], $port)) {

        // Active list আনা (অনলাইন ইউজারদের জন্য)
        $active_users = $API->comm("/ppp/active/print");
        $online_ids = [];
        foreach ($active_users as $active) {
            if (!empty($active['name'])) {
                $online_ids[] = $active['name'];
            }
        }

        // PPP Secrets আনা
        $secrets = $API->comm("/ppp/secret/print", ['.proplist'=>'.id,name,password,profile,disabled,comment']);

        $processed = 0;
        foreach ($secrets as $secret) {
            $pppoe_id = $secret['name'] ?? '';
            $password = $secret['password'] ?? '';
            $profile  = $secret['profile'] ?? '';
            $disabled = $secret['disabled'] ?? 'false';
            $status   = normalize_client_status(($disabled === 'true') ? 'inactive' : 'active');
            $statusColExists = col_exists($pdo, 'clients', 'status');
            $comment  = (string)($secret['comment'] ?? '');
            $commentData = $comment !== '' ? parse_comment($comment) : [];

            if ($pppoe_id == '') continue;

            // প্যাকেজ ম্যাচ (router_id থাকলে সেটাও মিলাই)
            $package_id = null;
            if (!empty($commentData['package_name'])) {
                if (col_exists($pdo, 'packages', 'router_id')) {
                    $stmt_pkg = $pdo->prepare("SELECT id FROM packages WHERE name = ? AND router_id = ? LIMIT 1");
                    $stmt_pkg->execute([$commentData['package_name'], $router['id']]);
                } else {
                    $stmt_pkg = $pdo->prepare("SELECT id FROM packages WHERE name = ? LIMIT 1");
                    $stmt_pkg->execute([$commentData['package_name']]);
                }
                $pkg = $stmt_pkg->fetch(PDO::FETCH_ASSOC);
                $package_id = $pkg['id'] ?? null;
            }
            if ($package_id === null && $profile !== '') {
                if (col_exists($pdo, 'packages', 'router_id')) {
                    $stmt_pkg = $pdo->prepare("SELECT id FROM packages WHERE name = ? AND router_id = ? LIMIT 1");
                    $stmt_pkg->execute([$profile, $router['id']]);
                } else {
                    $stmt_pkg = $pdo->prepare("SELECT id FROM packages WHERE name = ? LIMIT 1");
                    $stmt_pkg->execute([$profile]);
                }
                $pkg = $stmt_pkg->fetch(PDO::FETCH_ASSOC);
                $package_id = $pkg['id'] ?? null;
            }
            if ($package_id === null && col_exists($pdo, 'packages', 'id')) {
                $package_id = (int)($pdo->query("SELECT id FROM packages ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
            }

            // অনলাইন স্ট্যাটাস চেক
            $is_online = in_array($pppoe_id, $online_ids) ? 1 : 0;

            // আগে আছে কিনা চেক
            $selCols = col_exists($pdo, 'clients', 'client_code') ? 'id, client_code' : 'id';
            $stmt_chk = $pdo->prepare("SELECT {$selCols} FROM clients WHERE pppoe_id = ? AND router_id = ?");
            $stmt_chk->execute([$pppoe_id, $router['id']]);
            $exists = $stmt_chk->fetch();

            if ($exists) {
                // আপডেট
                $sets = [];
                $vals = [];
                $passCol = col_exists($pdo, 'clients', 'password') ? 'password' : null;
                $pppPassCol = col_exists($pdo, 'clients', 'pppoe_pass') ? 'pppoe_pass' : null;
                if ($passCol) { $sets[] = "$passCol=?"; $vals[] = $password; }
                if ($pppPassCol) { $sets[] = "$pppPassCol=?"; $vals[] = $password; }
                if (col_exists($pdo, 'clients', 'package_id') && $package_id) { $sets[] = "package_id=?"; $vals[] = $package_id; }
                if ($statusColExists) { $sets[] = "status=?"; $vals[] = normalize_client_status($status); }
                if (col_exists($pdo, 'clients', 'is_online')) { $sets[] = "is_online=?"; $vals[] = $is_online; }
                if (!empty($commentData['name']) && col_exists($pdo, 'clients', 'name')) { $sets[] = "name=?"; $vals[] = $commentData['name']; }
                if (!empty($commentData['mobile'])) {
                    $mobCol = pick_col($pdo, 'clients', ['mobile','phone','cell','contact']);
                    if ($mobCol) { $sets[] = "$mobCol=?"; $vals[] = $commentData['mobile']; }
                }
                if (!empty($commentData['area']) && col_exists($pdo, 'clients', 'area')) { $sets[] = "area=?"; $vals[] = $commentData['area']; }
                if (!empty($commentData['address']) && col_exists($pdo, 'clients', 'address')) { $sets[] = "address=?"; $vals[] = $commentData['address']; }
                if (col_exists($pdo, 'clients', 'client_code') && empty($exists['client_code'])) {
                    $cc = $commentData['client_code'] ?? client_code_from_pppoe($pppoe_id);
                    if ($cc !== '') { $sets[] = "client_code=?"; $vals[] = $cc; }
                }
                if (!empty($commentData['join_date']) && col_exists($pdo, 'clients', 'join_date')) {
                    $jd = normalize_date($commentData['join_date']);
                    if ($jd) { $sets[] = "join_date=?"; $vals[] = $jd; }
                }
                if (!empty($commentData['monthly_bill'])) {
                    $mbCol = pick_col($pdo, 'clients', ['monthly_bill','monthly_bill_amount','bill_amount','monthly_bill_tk']);
                    if ($mbCol && is_numeric($commentData['monthly_bill'])) { $sets[] = "$mbCol=?"; $vals[] = (float)$commentData['monthly_bill']; }
                }
                if (!empty($commentData['expiry_date'])) {
                    $expCol = pick_col($pdo, 'clients', ['expiry_date','expire_date']);
                    if ($expCol) {
                        $ed = normalize_date($commentData['expiry_date']);
                        if ($ed) { $sets[] = "$expCol=?"; $vals[] = $ed; }
                    }
                }
                if ($sets) {
                    $vals[] = $exists['id'];
                    $update = $pdo->prepare("UPDATE clients SET ".implode(',', $sets)." WHERE id=?");
                    $update->execute($vals);
                }
                echo "🔄 Updated client: $pppoe_id (Online: $is_online)\n";
            } else {
                $new_client_code = '';
                if (col_exists($pdo, 'clients', 'client_code')) {
                    $new_client_code = $commentData['client_code'] ?? client_code_from_pppoe($pppoe_id);
                }

                // Default name = PPPoE ID
                $client_name = $pppoe_id;

                // ইনসার্ট
                $cols = [];
                $vals = [];
                if ($new_client_code !== '' && col_exists($pdo, 'clients', 'client_code')) { $cols[] = 'client_code'; $vals[] = $new_client_code; }
                if (col_exists($pdo, 'clients', 'router_id')) { $cols[] = 'router_id'; $vals[] = $router['id']; }
                if (col_exists($pdo, 'clients', 'name')) { $cols[] = 'name'; $vals[] = $client_name; }
                if (col_exists($pdo, 'clients', 'mobile')) { $cols[] = 'mobile'; $vals[] = null; }
                if (col_exists($pdo, 'clients', 'package_id') && $package_id) { $cols[] = 'package_id'; $vals[] = $package_id; }
                if (col_exists($pdo, 'clients', 'pppoe_id')) { $cols[] = 'pppoe_id'; $vals[] = $pppoe_id; }
                if (col_exists($pdo, 'clients', 'password')) { $cols[] = 'password'; $vals[] = $password; }
                if (col_exists($pdo, 'clients', 'pppoe_pass')) { $cols[] = 'pppoe_pass'; $vals[] = $password; }
                if ($statusColExists) { $cols[] = 'status'; $vals[] = normalize_client_status($status); }
                if (col_exists($pdo, 'clients', 'is_online')) { $cols[] = 'is_online'; $vals[] = $is_online; }
                if (!empty($commentData['name']) && col_exists($pdo, 'clients', 'name')) {
                    $pos = array_search('name', $cols, true);
                    if ($pos === false) {
                        $cols[] = 'name'; $vals[] = $commentData['name'];
                    } else {
                        $vals[$pos] = $commentData['name'];
                    }
                }
                if (!empty($commentData['mobile'])) {
                    $mobCol = pick_col($pdo, 'clients', ['mobile','phone','cell','contact']);
                    if ($mobCol) {
                        $pos = array_search($mobCol, $cols, true);
                        if ($pos === false) {
                            $cols[] = $mobCol; $vals[] = $commentData['mobile'];
                        } else {
                            $vals[$pos] = $commentData['mobile'];
                        }
                    }
                }
                if (!empty($commentData['area']) && col_exists($pdo, 'clients', 'area')) { $cols[] = 'area'; $vals[] = $commentData['area']; }
                if (!empty($commentData['address']) && col_exists($pdo, 'clients', 'address')) { $cols[] = 'address'; $vals[] = $commentData['address']; }
                if (col_exists($pdo, 'clients', 'join_date')) {
                    $jd = !empty($commentData['join_date']) ? normalize_date($commentData['join_date']) : date('Y-m-d');
                    if(!$jd) $jd = date('Y-m-d');
                    $cols[] = 'join_date'; $vals[] = $jd;
                }
                if (!empty($commentData['monthly_bill'])) {
                    $mbCol = pick_col($pdo, 'clients', ['monthly_bill','monthly_bill_amount','bill_amount','monthly_bill_tk']);
                    if ($mbCol && is_numeric($commentData['monthly_bill'])) { $cols[] = $mbCol; $vals[] = (float)$commentData['monthly_bill']; }
                }
                if (!empty($commentData['expiry_date'])) {
                    $expCol = pick_col($pdo, 'clients', ['expiry_date','expire_date']);
                    if ($expCol) {
                        $ed = normalize_date($commentData['expiry_date']);
                        if ($ed) { $cols[] = $expCol; $vals[] = $ed; }
                    }
                }
                // ensure required defaults exist
                if ($statusColExists && !in_array('status', $cols, true)) { $cols[]='status'; $vals[]= 'pending'; }
                if (col_exists($pdo, 'clients', 'monthly_bill') && !in_array('monthly_bill', $cols, true)) { $cols[]='monthly_bill'; $vals[]=(float)0; }
                if (col_exists($pdo, 'clients', 'is_online') && !in_array('is_online', $cols, true)) { $cols[]='is_online'; $vals[]=$is_online; }

                if ($cols) {
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $insert = $pdo->prepare("INSERT INTO clients (".implode(',', $cols).") VALUES ($ph)");
                    $insert->execute($vals);
                }

                echo "➕ Added new client: $pppoe_id (Online: $is_online)\n";
            }
            // MikroTik comment sync (set structured comment)
            $packageName = '';
            if ($package_id && col_exists($pdo, 'packages', 'name')) {
                $stpn = $pdo->prepare("SELECT name FROM packages WHERE id=? LIMIT 1");
                $stpn->execute([$package_id]);
                $packageName = (string)($stpn->fetchColumn() ?: '');
            }
            $codeForComment = $commentData['client_code']
                ?? ((is_array($exists) && isset($exists['client_code'])) ? $exists['client_code'] : ($new_client_code ?? client_code_from_pppoe($pppoe_id)));
            $commentPayload = [
                'client_code' => $codeForComment,
                'pppoe_id' => $pppoe_id,
                'name' => $commentData['name'] ?? $client_name ?? $pppoe_id,
                'mobile' => $commentData['mobile'] ?? '',
                'area' => $commentData['area'] ?? '',
                'address' => $commentData['address'] ?? '',
                'join_date' => $commentData['join_date'] ?? '',
                'package_name' => $commentData['package_name'] ?? $packageName,
                'monthly_bill' => $commentData['monthly_bill'] ?? '',
                'expiry_date' => $commentData['expiry_date'] ?? '',
            ];
            $wantComment = build_comment($commentPayload);
            if ($wantComment !== '' && isset($secret['.id'])) {
                $curComment = (string)($secret['comment'] ?? '');
                if ($curComment !== $wantComment) {
                    $API->comm('/ppp/secret/set', ['.id'=>$secret['.id'], 'comment'=>$wantComment]);
                }
            }

            $processed++;
            if ($limit > 0 && $processed >= $limit) {
                echo "⏭ Limit reached ({$limit}) for router {$router['name']}.\n";
                break;
            }
        }

        $API->disconnect();
        echo "✅ Sync complete for {$router['name']}!\n";
    } else {
        echo "❌ Failed to connect to {$router['name']} ({$router['ip']})\n";
    }
}
