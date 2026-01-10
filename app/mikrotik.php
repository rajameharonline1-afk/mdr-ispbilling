<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/routeros_api.class.php';

function getMikrotikConnection($router_id) {
    $stmt = db()->prepare("SELECT * FROM routers WHERE id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch();

    if (!$router) return false;

    $API = new RouterosAPI();
    $API->debug = false;
    $ip   = $router['ip_address'] ?? ($router['ip'] ?? ($router['host'] ?? ($router['address'] ?? '')));
    $user = $router['username'] ?? ($router['user'] ?? '');
    $pass = $router['password'] ?? ($router['pass'] ?? '');
    $port = isset($router['api_port']) && $router['api_port'] ? (int)$router['api_port'] : (int)($router['port'] ?? 8728);
    if ($ip && $user && $pass && $API->connect($ip, $user, $pass, $port)) {
        return $API;
    }
    return false;
}

function getPPPoEStats($router_id) {
    $API = getMikrotikConnection($router_id);
    if (!$API) return ['online' => 0, 'offline' => 0, 'disabled' => 0];

    // অনলাইন ইউজার
    $activeUsers = $API->comm("/ppp/active/print");
    $online_count = count($activeUsers);

    // সব ইউজারের লিস্ট (প্রোফাইলসহ)
    $secrets = $API->comm("/ppp/secret/print");
    $disabled_count = 0;
    foreach ($secrets as $user) {
        if (isset($user['disabled']) && $user['disabled'] === 'true') {
            $disabled_count++;
        }
    }

    $offline_count = count($secrets) - $online_count;

    $API->disconnect();
    return [
        'online' => $online_count,
        'offline' => $offline_count,
        'disabled' => $disabled_count
    ];
}

/**
 * Ensure a PPPoE secret exists/updated on MikroTik.
 * @param int $router_id MikroTik router id (routers.id)
 * @param string $pppoe_id PPPoE username
 * @param ?string $password If null and secret exists, skip password update; if null and secret missing, error.
 * @param ?string $profile PPP profile to apply (optional)
 * @param array $opts Extra options: ['comment' => '...']
 * @return array ['ok'=>bool,'action'=>'created|updated|noop','error'=>?string]
 */
function mikrotik_api_error($resp): ?string {
    if (!is_array($resp)) return null;
    if (!empty($resp['!trap'][0]['message'])) return (string)$resp['!trap'][0]['message'];
    if (!empty($resp['!fatal'][0]['message'])) return (string)$resp['!fatal'][0]['message'];
    if (!empty($resp['!trap'])) return 'MikroTik error';
    if (!empty($resp['!fatal'])) return 'MikroTik fatal error';
    return null;
}

function mikrotik_ensure_pppoe_secret(int $router_id, string $pppoe_id, ?string $password = null, ?string $profile = null, array $opts = []): array {
    $pppoe_id = trim($pppoe_id);
    if ($pppoe_id === '') return ['ok'=>false,'error'=>'PPPoE ID খালি'];

    $stmt = db()->prepare("SELECT * FROM routers WHERE id = ? LIMIT 1");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$router) return ['ok'=>false,'error'=>'Router পাওয়া যায়নি'];
    if (!empty($router['status']) && (int)$router['status'] === 0) return ['ok'=>false,'error'=>'Router নিষ্ক্রিয়'];

    $api = new RouterosAPI();
    $api->debug = false;
    $ip   = $router['ip'] ?? ($router['ip_address'] ?? ($router['host'] ?? ($router['address'] ?? '')));
    $user = $router['username'] ?? ($router['user'] ?? '');
    $pass = $router['password'] ?? ($router['pass'] ?? '');
    $port = isset($router['api_port']) && $router['api_port'] ? (int)$router['api_port'] : (int)($router['port'] ?? 8728);
    if ($ip === '' || $user === '' || $pass === '') {
        return ['ok'=>false,'error'=>'Router credential incomplete'];
    }
    if (!$api->connect($ip, $user, $pass, $port)) {
        return ['ok'=>false,'error'=>'MikroTik API সংযোগ ব্যর্থ'];
    }

    $existing = $api->comm('/ppp/secret/print', ['?name'=>$pppoe_id]);
    $exists = is_array($existing) && isset($existing[0]);

    if (!$exists && $password === null) {
        $api->disconnect();
        return ['ok'=>false,'error'=>'নতুন সিক্রেট তৈরি করতে পাসওয়ার্ড দরকার'];
    }

    try {
        if (!$exists) {
            $params = [
                'name'     => $pppoe_id,
                'service'  => 'pppoe',
                'password' => $password ?? '',
            ];
            if ($profile) $params['profile'] = $profile;
            if (!empty($opts['comment'])) $params['comment'] = $opts['comment'];
            $resp = $api->comm('/ppp/secret/add', $params);
            $err = mikrotik_api_error($resp);
            if ($err && $profile) {
                // (বাংলা) profile invalid হলে profile ছাড়া retry
                unset($params['profile']);
                $resp = $api->comm('/ppp/secret/add', $params);
                $err = mikrotik_api_error($resp);
            }
            if ($err) { $api->disconnect(); return ['ok'=>false,'error'=>$err]; }
            $api->disconnect();
            return ['ok'=>true,'action'=>'created'];
        }

        // Update existing
        $id = $existing[0]['.id'] ?? null;
        if ($id === null) { $api->disconnect(); return ['ok'=>false,'error'=>'Existing secret id পাওয়া যায়নি']; }

        $set = ['.id'=>$id];
        $needUpdate = false;
        if ($password !== null) { $set['password'] = $password; $needUpdate = true; }
        if ($profile)          { $set['profile']  = $profile;  $needUpdate = true; }
        if (!empty($opts['comment'])) { $set['comment'] = $opts['comment']; $needUpdate = true; }

        if ($needUpdate) {
            $resp = $api->comm('/ppp/secret/set', $set);
            $err = mikrotik_api_error($resp);
            if ($err && $profile) {
                // (বাংলা) profile invalid হলে profile ছাড়া retry
                unset($set['profile']);
                $resp = $api->comm('/ppp/secret/set', $set);
                $err = mikrotik_api_error($resp);
            }
            if ($err) { $api->disconnect(); return ['ok'=>false,'error'=>$err]; }
            $api->disconnect();
            return ['ok'=>true,'action'=>'updated'];
        } else {
            $api->disconnect();
            return ['ok'=>true,'action'=>'noop'];
        }
    } catch (Throwable $e) {
        $api->disconnect();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}
