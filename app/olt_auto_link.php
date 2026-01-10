<?php
// /app/olt_auto_link.php
// Helpers to map clients with OLT/ONU data using olt_mac_cache records.

declare(strict_types=1);

if (!function_exists('normalize_mac_for_lookup')) {
    function normalize_mac_for_lookup(?string $mac): ?string {
        if(!$mac) return null;
        $hex = strtolower(preg_replace('/[^0-9a-f]/', '', (string)$mac));
        if(strlen($hex) !== 12) return null;
        return implode(':', str_split($hex, 2));
    }
}

if (!function_exists('auto_link_client_olt_from_cache')) {
    function auto_link_client_olt_from_cache(PDO $pdo, int $client_id, array $fields): array {
        $tokens = [];
        $addToken = static function($val) use (&$tokens) {
            $val = trim((string)$val);
            if($val !== '') $tokens[] = $val;
        };
        $addToken($fields['pppoe_id'] ?? '');
        $addToken($fields['client_code'] ?? '');
        $addToken($fields['name'] ?? '');
        $addToken($fields['mobile'] ?? '');

        if(!$tokens) return ['ok'=>false, 'reason'=>'no_tokens'];

        $fetchRow = static function(string $sql, array $params) use ($pdo): ?array {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        $match = null;
        try{
            foreach($tokens as $token){
                $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE LOWER(description)=? ORDER BY learned_at DESC LIMIT 1", [strtolower($token)]);
                if($match) break;
            }
            if(!$match){
                foreach($tokens as $token){
                    $mac = normalize_mac_for_lookup($token);
                    if(!$mac) continue;
                    $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE REPLACE(LOWER(mac),':','') = ? ORDER BY learned_at DESC LIMIT 1", [strtolower(str_replace(':','',$mac))]);
                    if($match) break;
                }
            }
        }catch(Throwable $e){
            return ['ok'=>false, 'reason'=>'lookup_failed'];
        }

        if(!$match) return ['ok'=>false, 'reason'=>'not_found'];

        $onuText = (string)($match['onu'] ?? '');
        $onuId = null;
        if(preg_match('/(\d+)/', $onuText, $m)){
            $onuId = (string)(int)$m[1];
        }
        $macAddr = normalize_mac_for_lookup($match['mac'] ?? '') ?? ($match['mac'] ?? null);
        $vendor = null;
        try{
            $st = $pdo->prepare("SELECT vendor FROM olts WHERE id=? LIMIT 1");
            $st->execute([(int)$match['olt_id']]);
            $vendor = $st->fetchColumn() ?: null;
        }catch(Throwable $e){}

        try{
            $upd = $pdo->prepare("UPDATE clients
                                  SET olt_id=:oid,
                                      olt_vendor=:vendor,
                                      olt_port=:port,
                                      olt_onu=:onu,
                                      caller_mac=:mac,
                                      last_linked_at=:linked
                                  WHERE id=:id");
            $upd->execute([
                ':oid' => $match['olt_id'] ?? null,
                ':vendor' => $vendor,
                ':port' => $match['port'] ?? null,
                ':onu' => $onuId,
                ':mac' => $macAddr,
                ':linked' => $match['learned_at'] ?? null,
                ':id' => $client_id,
            ]);
        }catch(Throwable $e){
            return ['ok'=>false, 'reason'=>'update_failed'];
        }

        $portLabel = (string)($match['port'] ?? '');
        if($portLabel !== '' && $onuId){
            try{
                if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $portLabel, $pm)){
                    $family = strtolower($pm[1]);
                    $slot = (int)$pm[2];
                    $iface = '0/'.$slot;
                    $rxVal = null;
                    if(isset($match['rx_power_dbm']) && $match['rx_power_dbm'] !== '' && is_numeric($match['rx_power_dbm'])){
                        $rxVal = (float)$match['rx_power_dbm'];
                    }
                    $status = strtolower((string)($match['status'] ?? 'online'));
                    $invSql = "INSERT INTO onu_inventory (olt_id,family,iface,onu_id,client_id,is_active,last_status,last_rx_dbm,last_updated)
                               VALUES (?,?,?,?,?,1,?,?,?)
                               ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), last_status=VALUES(last_status), last_rx_dbm=VALUES(last_rx_dbm), last_updated=VALUES(last_updated)";
                    $pdo->prepare($invSql)->execute([
                        (int)$match['olt_id'],
                        $family,
                        $iface,
                        (int)$onuId,
                        $client_id,
                        $status !== '' ? $status : 'online',
                        $rxVal,
                        $match['learned_at'] ?? null,
                    ]);
                    if($macAddr){
                        $invIdStmt = $pdo->prepare("SELECT id FROM onu_inventory WHERE olt_id=? AND family=? AND iface=? AND onu_id=? LIMIT 1");
                        $invIdStmt->execute([(int)$match['olt_id'], $family, $iface, (int)$onuId]);
                        $targetId = (int)($invIdStmt->fetchColumn() ?: 0);
                        if($targetId > 0){
                            $mapSql = "INSERT INTO onu_mac_map (target_id, mac, last_seen)
                                       VALUES (?, ?, ?)
                                       ON DUPLICATE KEY UPDATE mac=VALUES(mac), last_seen=VALUES(last_seen)";
                            $pdo->prepare($mapSql)->execute([$targetId, $macAddr, $match['learned_at'] ?? date('Y-m-d H:i:s')]);
                        }
                    }
                }
            }catch(Throwable $e){
                // ignore inventory sync failures
            }
        }

        return [
            'ok'=>true,
            'port'=>$portLabel,
            'onu'=>$onuId,
            'olt'=>$match['olt_id'] ?? null,
        ];
    }
}

