<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/telnet.php';
require_once __DIR__ . '/../app/security_helpers.php';

$page_title = 'Admin Tools - ONU Config';
$_active    = 'admin_tools';

$db = db();

function h($value){
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function decrypt_olt_secret(?string $ciphertext): ?string {
  if($ciphertext === null || $ciphertext === '') return null;
  $plain = decrypt_password($ciphertext, ENCRYPTION_KEY);
  if($plain === false) return null;
  return $plain;
}

function normalize_description_value(?string $value): ?string {
  if($value === null) return null;
  $trim = trim($value);
  return $trim === '' ? null : $trim;
}

function admin_normalize_port_label(?string $label): string {
  if(!$label) return '—';
  $label = trim($label);
  if($label === '') return '—';
  if(preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/i', $label, $m)){
    $slot = str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return strtoupper($m[1])." 0/{$slot}";
  }
  if(preg_match('/0\/(\d{1,2})/i', $label, $m)){
    $slot = str_pad($m[1], 2, '0', STR_PAD_LEFT);
    return "PON 0/{$slot}";
  }
  return strtoupper($label);
}

function admin_extract_slot(?string $label): ?int {
  if(!$label) return null;
  $label = strtoupper($label);
  if(preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/i', $label, $m)){
    return (int)$m[2];
  }
  if(preg_match('/PON\s*0\/(\d{1,2})/i', $label, $m)){
    return (int)$m[1];
  }
  if(preg_match('/0\/(\d{1,2})/i', $label, $m)){
    return (int)$m[1];
  }
  if(preg_match('/PON\s*(\d{1,2})/i', $label, $m)){
    return (int)$m[1];
  }
  return null;
}

function admin_port_sort_key(array $row): string {
  $port = $row['normalized_port'] ?? ($row['port'] ?? '');
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $port, $m)){
    $family = strtoupper($m[1]) === 'GPON' ? 'B' : 'A';
    return $family . sprintf('%02d', (int)$m[2]);
  }
  if(preg_match('/PON\s*0\/(\d+)/i', $port, $m)){
    return 'C' . sprintf('%02d', (int)$m[1]);
  }
  return 'Z' . strtoupper($port);
}

function sanitize_onu_description(?string $value): array {
  $normalized = normalize_description_value($value);
  if($normalized === null){
    return ['', false];
  }
  $original = $normalized;
  $normalized = preg_replace('/\s+/', '-', $normalized);
  $normalized = preg_replace('/[^A-Za-z0-9_.\\-+\\/]/', '', $normalized);
  if(function_exists('mb_substr')){
    $normalized = mb_substr($normalized, 0, 60, 'UTF-8');
  } else {
    $normalized = substr($normalized, 0, 60);
  }
  return [$normalized, $normalized !== $original];
}

function parse_port_label_for_cli(?string $label): ?array {
  if(!$label) return null;
  $label = strtoupper(trim($label));
  if($label === '') return null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/', $label, $m)){
    $family = strtolower($m[1]) === 'gpon' ? 'gpon' : 'epon';
    $slot = (int)$m[2];
    return [$family, '0/'.$slot];
  }
  if(preg_match('/0\/(\d{1,2})/', $label, $m)){
    return ['epon', '0/'.(int)$m[1]];
  }
  return null;
}

function parse_onu_number_from_label(?string $label): ?int {
  if(!$label) return null;
  if(preg_match('/(\d{1,3})/', $label, $m)){
    return (int)$m[1];
  }
  return null;
}

function admin_onu_numeric(?string $onu): int {
  if($onu && preg_match('/(\d+)/', $onu, $m)){
    return (int)$m[1];
  }
  return PHP_INT_MAX;
}

function admin_normalize_status(?string $status): ?string {
  $s = strtolower(trim((string)$status));
  if($s === '') return null;
  return match($s){
    'online','up','active','reachable','yes','true','1'   => 'online',
    'offline','down','inactive','failed','no','false','0' => 'offline',
    'unknown' => 'unknown',
    default => null,
  };
}

function admin_resolve_status(array $row): ?string {
  $normalized = admin_normalize_status($row['status'] ?? null);
  if($normalized) return $normalized;
  $reason = strtolower(trim((string)($row['last_dereg_reason'] ?? '')));
  if($reason !== '' && $reason !== 'n/a' && $reason !== '--' && $reason !== '—') return 'offline';
  $deregTime = trim((string)($row['last_dereg_time'] ?? ''));
  if($deregTime !== '' && $deregTime !== '0000-00-00 00:00:00') return 'offline';
  $rx = $row['rx_power_dbm'] ?? null;
  if($rx !== null && $rx !== '' && is_numeric($rx)) return 'online';
  return null;
}

function admin_format_onu_identifier(array $row): string {
  $port = $row['normalized_port'] ?? ($row['port'] ?? '');
  $onuNum = admin_onu_numeric($row['onu'] ?? '');
  if($onuNum === PHP_INT_MAX){
    return $port !== '' ? $port : '—';
  }
  $family = 'PON';
  $slot = null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', (string)$port, $m)){
    $family = strtoupper($m[1]);
    $slot = (int)$m[2];
  } elseif(preg_match('/PON\s*0\/(\d+)/i', (string)$port, $m)){
    $family = 'PON';
    $slot = (int)$m[1];
  }
  if($slot !== null){
    return sprintf('%s0/%d:%d', $family, $slot, $onuNum);
  }
  return trim($port . ($port && $row['onu'] ? ' ' : '') . ($row['onu'] ?? ''));
}

function push_onu_description_to_device(PDO $db, array $entry, ?string $description): array {
  $oltId = (int)($entry['olt_id'] ?? 0);
  if($oltId <= 0){
    return ['ok'=>false,'message'=>'OLT তথ্য পাওয়া যায়নি।'];
  }
  $st = $db->prepare("SELECT id,name,host,telnet_port,ssh_port,username,password,enable_password FROM olts WHERE id = ? LIMIT 1");
  $st->execute([$oltId]);
  $olt = $st->fetch(PDO::FETCH_ASSOC);
  if(!$olt){
    return ['ok'=>false,'message'=>'OLT ক্রেডেনশিয়াল অনুপস্থিত।'];
  }
  $username = trim((string)($olt['username'] ?? ''));
  $password = decrypt_olt_secret($olt['password'] ?? '');
  if($username === '' || $password === null){
    return ['ok'=>false,'message'=>'OLT ইউজার বা পাসওয়ার্ড পাওয়া যায়নি।'];
  }
  $enablePass = decrypt_olt_secret($olt['enable_password'] ?? '') ?: $password;
  $parsedPort = parse_port_label_for_cli($entry['port'] ?? '');
  if(!$parsedPort){
    return ['ok'=>false,'message'=>'পোর্ট ফরম্যাট শনাক্ত করা যায়নি।'];
  }
  [$familyCli, $iface] = $parsedPort;
  $onuNum = parse_onu_number_from_label($entry['onu'] ?? '');
  if($onuNum === null){
    return ['ok'=>false,'message'=>'ONU নম্বর পাওয়া যায়নি।'];
  }
  [$desc,] = sanitize_onu_description($description);
  $port = (int)($olt['telnet_port'] ?? 0);
  if($port < 1){
    $port = (int)($olt['ssh_port'] ?? 23);
  }
  if($port < 1) $port = 23;
  $commands = [
    'configure terminal',
    "interface {$familyCli} {$iface}",
    $desc === '' ? "onu {$onuNum} description default" : "onu {$onuNum} description {$desc}",
    'exit',
    'exit',
  ];
  $result = telnet_run_commands(
    $olt['host'],
    $port,
    $username,
    $password,
    $commands,
    null,
    true,
    $enablePass,
    false,
    20
  );
  if(!$result['ok']){
    return ['ok'=>false,'message'=>$result['error'] ?? 'টেলনেট কমান্ড ব্যর্থ হয়েছে।'];
  }
  return ['ok'=>true,'message'=>'ONU বিবরণ ডিভাইসে পাঠানো হয়েছে।'];
}

$filterOlt = (int)($_GET['olt_id'] ?? 0);
$search    = trim((string)($_GET['q'] ?? ''));
$perPage   = 1000;

$statusOptions = [
  '' => 'Auto (from OLT)',
  'online' => 'Online',
  'offline' => 'Offline',
  'unknown' => 'Unknown',
];

$flashSuccess = '';
$flashError   = '';
$expandedEntryId = 0;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';
  if($action === 'update_onu'){
    $entryId = (int)($_POST['entry_id'] ?? 0);
    $expandedEntryId = $entryId;
    $descriptionRaw = trim((string)($_POST['description'] ?? ''));
    if($descriptionRaw !== ''){
      if(function_exists('mb_substr')){
        $descriptionRaw = mb_substr($descriptionRaw, 0, 255, 'UTF-8');
      } else {
        $descriptionRaw = substr($descriptionRaw, 0, 255);
      }
    }
    $statusInput = strtolower(trim((string)($_POST['status'] ?? '')));
    if($statusInput !== 'online' && $statusInput !== 'offline' && $statusInput !== 'unknown'){
      $statusInput = null;
    }
    if($entryId <= 0){
      $flashError = 'Invalid ONU entry selected.';
    } else {
      $check = $db->prepare("SELECT c.*, o.name AS olt_name FROM olt_mac_cache c LEFT JOIN olts o ON o.id = c.olt_id WHERE c.id = ? LIMIT 1");
      $check->execute([$entryId]);
      $row = $check->fetch(PDO::FETCH_ASSOC);
      if(!$row){
        $flashError = 'Selected ONU entry was not found.';
      } else {
        [$sanitizedDesc, $wasAdjusted] = sanitize_onu_description($descriptionRaw);
        if($descriptionRaw !== '' && $sanitizedDesc === ''){
          $flashError = 'Description-এ কেবল a-z, A-Z, 0-9, _, ., -, +, / ব্যবহার করা যাবে এবং স্পেস স্বয়ংক্রিয়ভাবে ড্যাশে রূপান্তরিত হবে।';
        } else {
          if($descriptionRaw !== '' && $wasAdjusted){
            $flashSuccess = "কিছু চিহ্ন সরানো হয়েছে। নতুন মান: {$sanitizedDesc}";
          }
          $descVal = $sanitizedDesc === '' ? null : $sanitizedDesc;
          $currentDesc = normalize_description_value($row['description'] ?? null);
          $newDescNorm = normalize_description_value($descVal);
          $descChanged = $currentDesc !== $newDescNorm;
          $upd = $db->prepare("UPDATE olt_mac_cache SET description = ?, status = ? WHERE id = ?");
          $upd->execute([$descVal, $statusInput, $entryId]);
          if($descChanged){
            $push = push_onu_description_to_device($db, $row, $newDescNorm ?? '');
            if($push['ok']){
              $flashSuccess = 'ONU বিবরণ সফলভাবে ডিভাইসে পাঠানো হয়েছে।';
            } else {
              $flashError = 'ডাটাবেজ আপডেট হয়েছে কিন্তু ডিভাইসে পাঠাতে ব্যর্থ: '.$push['message'];
              $flashSuccess = '';
            }
          } else {
            $flashSuccess = 'ONU details updated successfully.';
          }
        }
      }
    }
  }
}

$olts = $db->query("SELECT id,name,host FROM olts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];
if($filterOlt > 0){
  $where[] = 'c.olt_id = ?';
  $params[] = $filterOlt;
}
if($search !== ''){
  $like = '%' . $search . '%';
  $where[] = '(c.mac LIKE ? OR c.port LIKE ? OR c.onu LIKE ? OR c.description LIKE ?)';
  $params = array_merge($params, [$like,$like,$like,$like]);
}
$sql = "SELECT c.*, o.name AS olt_name, o.host AS olt_host
        FROM olt_mac_cache c
        LEFT JOIN olts o ON o.id = c.olt_id";
if($where){
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY o.name ASC, c.port ASC, c.onu ASC LIMIT {$perPage}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$oltGroups = [];
$maxSlotByOlt = [];
if($rows){
  $dedupSeen = [];
  foreach($rows as $row){
    $oltId   = (int)($row['olt_id'] ?? 0);
    if($oltId <= 0) continue;
    $oltName = $row['olt_name'] ?? 'Unnamed OLT';
    $oltHost = $row['olt_host'] ?? '';
    $normalizedPort = admin_normalize_port_label($row['port'] ?? '');
    $slot = admin_extract_slot($normalizedPort);
    $onuLabel = trim((string)($row['onu'] ?? ''));
    if($slot === null || $slot <= 0 || $onuLabel === '' || $onuLabel === '—'){
      continue;
    }
    if(!isset($oltGroups[$oltId])){
      $oltGroups[$oltId] = [
        'name' => $oltName,
        'host' => $oltHost,
        'pons' => []
      ];
    }
    $onuNum = admin_onu_numeric($row['onu'] ?? '');
    if($onuNum === PHP_INT_MAX){
      continue;
    }
    $dedupKeySuffix = (string)$onuNum;
    $dedupKey = "{$normalizedPort}|{$dedupKeySuffix}";
    if(isset($dedupSeen[$oltId][$dedupKey])){
      continue;
    }
    $dedupSeen[$oltId][$dedupKey] = true;
    $row['normalized_port'] = $normalizedPort;
    $row['pon_slot'] = $slot;
    $maxSlotByOlt[$oltId] = max($maxSlotByOlt[$oltId] ?? 0, $slot);
    $row['onu_numeric'] = $onuNum;
    $labelKey = 'PON '.$slot;
    if(!isset($oltGroups[$oltId]['pons'][$labelKey])){
      $oltGroups[$oltId]['pons'][$labelKey] = [
        'label' => 'PON '.$slot,
        'slot'  => $slot,
        'rows'  => []
      ];
    }
    $oltGroups[$oltId]['pons'][$labelKey]['rows'][] = $row;
  }
  $fillAllPons = ($filterOlt > 0);
  foreach($oltGroups as $oltId => &$og){
    if($fillAllPons && isset($maxSlotByOlt[$oltId]) && $maxSlotByOlt[$oltId] > 0){
      for($s=1; $s<=$maxSlotByOlt[$oltId]; $s++){
        $key = 'PON '.$s;
        if(!isset($og['pons'][$key])){
          $og['pons'][$key] = ['label'=>$key, 'slot'=>$s, 'rows'=>[]];
        }
      }
    }
    foreach($og['pons'] as $key => &$pon){
      if(!$pon['rows'] && !$fillAllPons){
        unset($og['pons'][$key]);
        continue;
      }
      usort($pon['rows'], function($a,$b){
        $na = $a['onu_numeric'] ?? admin_onu_numeric($a['onu'] ?? '');
        $nb = $b['onu_numeric'] ?? admin_onu_numeric($b['onu'] ?? '');
        if($na === $nb){
          $ka = admin_port_sort_key($a);
          $kb = admin_port_sort_key($b);
          if($ka === $kb){
            $ta = strtotime($a['learned_at'] ?? '') ?: 0;
            $tb = strtotime($b['learned_at'] ?? '') ?: 0;
            return $tb <=> $ta;
          }
          return strcmp($ka, $kb);
        }
        return $na <=> $nb;
      });
      $counter = 1;
      foreach($pon['rows'] as &$entry){
        $entry['onu_serial'] = $counter++;
      }
      unset($entry);
    }
    unset($pon);
    if(!$fillAllPons){
      $og['pons'] = array_filter($og['pons'], fn($g) => !empty($g['rows']));
    }
    uasort($og['pons'], function($a,$b){
      $slotA = $a['slot'] ?? PHP_INT_MAX;
      $slotB = $b['slot'] ?? PHP_INT_MAX;
      if($slotA === $slotB){
        return strcmp($a['label'], $b['label']);
      }
      return $slotA <=> $slotB;
    });
  }
  unset($og);
  uasort($oltGroups, function($a,$b){
    return strcmp($a['name'], $b['name']);
  });
}

?>
<?php require_once __DIR__ . '/../partials/partials_header.php'; ?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div class="flex-grow-1">
      <h4 class="mb-0">Admin Tools</h4>
      <p class="text-muted mb-0">Manage ONU descriptions ও ফিল্ড সেটিং এখান থেকেই করুন।</p>
    </div>
    <a href="/public/olt_mac_table.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left-circle"></i> Back to OLT MAC Table
    </a>
  </div>

  <?php if($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?=$flashSuccess;?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?=$flashError;?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form class="card shadow-sm mb-4">
    <div class="card-body row g-3">
      <div class="col-md-5">
        <label class="text-muted small text-uppercase">Search</label>
        <input type="text" name="q" class="form-control" placeholder="MAC / Port / ONU / Description" value="<?=h($search);?>">
      </div>
      <div class="col-md-5">
        <label class="text-muted small text-uppercase">OLT</label>
        <select name="olt_id" class="form-select">
          <option value="0">All active OLTs</option>
          <?php foreach($olts as $olt): ?>
            <option value="<?=$olt['id'];?>" <?=$filterOlt===$olt['id']?'selected':'';?>>
              <?=h($olt['name'] ?: $olt['host']);?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-dark">Apply</button>
      </div>
    </div>
  </form>

  <?php if(!$oltGroups): ?>
    <div class="card shadow-sm">
      <div class="card-body text-center text-muted py-5">
        কোনো ONU ডেটা ফিল্টার মানদণ্ডে পাওয়া যায়নি।
      </div>
    </div>
  <?php else: $oltIdx=0; ?>
    <?php foreach($oltGroups as $oltId => $og): $accordionId='adminOlt-'.$oltIdx++; ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold"><?=h($og['name']);?></div>
            <div class="text-muted small"><?=h($og['host']);?></div>
          </div>
          <span class="text-muted small"><?=array_sum(array_map(fn($g) => count($g['rows']), $og['pons']));?> ONU</span>
        </div>
        <?php if($og['pons']): $idx=0; ?>
        <div class="accordion" id="<?=$accordionId;?>">
        <?php foreach($og['pons'] as $label => $group): $cid=$accordionId.'-'.$idx++; ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?=$cid;?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?=$cid;?>" aria-expanded="false" aria-controls="<?=$cid;?>">
                <span class="fw-semibold me-3"><?=h($group['label']);?></span>
                <span class="badge text-bg-primary ms-auto"><?=$group['rows'] ? count($group['rows']) : 0;?> ONU</span>
              </button>
            </h2>
            <div id="<?=$cid;?>" class="accordion-collapse collapse" aria-labelledby="heading-<?=$cid;?>" data-bs-parent="#<?=$accordionId;?>">
              <div class="accordion-body">
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0 table-app table-stack">
                    <thead>
                      <tr>
                        <th>ONU</th>
                        <th>MAC</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach($group['rows'] as $row): $collapseId='onuManage-'.$row['id']; $showCollapse = ($expandedEntryId === (int)$row['id']); ?>
                        <tr>
                          <td data-label="ONU"><?=h(admin_format_onu_identifier($row));?></td>
                          <td data-label="MAC"><code><?=h(strtoupper($row['mac'] ?? ''));?></code></td>
                          <td data-label="Description"><?=h($row['description'] ?? '—');?></td>
                          <td data-label="Status">
                            <?php
                              $statusResolved = admin_resolve_status($row);
                              $statusLabel = $statusResolved ? ucfirst($statusResolved) : 'Auto';
                              $statusClass = match($statusResolved){
                                'online' => 'success',
                                'offline' => 'danger',
                                default => 'secondary'
                              };
                            ?>
                            <span class="badge status-pill text-bg-<?=$statusClass;?>"><?=$statusLabel;?></span>
                          </td>
                          <td data-label="Actions" class="text-end">
                            <button class="btn btn-sm btn-outline-primary" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?=$collapseId;?>"
                                    aria-expanded="<?=$showCollapse?'true':'false';?>"
                                    aria-controls="<?=$collapseId;?>">
                              Configure
                            </button>
                          </td>
                        </tr>
                        <tr class="bg-light">
                          <td colspan="5" class="p-0 border-0">
                            <div class="collapse<?=$showCollapse?' show':'';?>" id="<?=$collapseId;?>">
                              <div class="p-3 border-top">
                                <form method="post" class="row g-3">
                                  <input type="hidden" name="action" value="update_onu">
                                  <input type="hidden" name="entry_id" value="<?=$row['id'];?>">
                                  <div class="col-md-8">
                                    <label class="form-label text-muted small text-uppercase">Description</label>
                                    <textarea name="description" rows="2" class="form-control" placeholder="e.g. Building-3, 3rd Floor"><?=h($row['description'] ?? '');?></textarea>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label text-muted small text-uppercase">Status Override</label>
                                    <select name="status" class="form-select">
                                      <?php foreach($statusOptions as $val=>$label): ?>
                                        <option value="<?=$val;?>" <?=(($row['status'] ?? '')===$val)?'selected':'';?>><?=$label;?></option>
                                      <?php endforeach; ?>
                                    </select>
                                    <div class="text-muted small mt-1">Auto = ডিভাইস থেকে পাওয়া স্ট্যাটাস।</div>
                                  </div>
                                  <div class="col-md-12 d-flex justify-content-between">
                                    <div class="text-muted small">Learned: <?=h($row['learned_at']);?></div>
                                    <div class="d-flex gap-2">
                                      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?=$collapseId;?>">Cancel</button>
                                      <button class="btn btn-primary btn-sm">Save Changes</button>
                                    </div>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="p-4 text-center text-muted">এই OLT তে কোনো ONU পাওয়া যায়নি।</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
