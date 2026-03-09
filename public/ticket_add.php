<?php
// /public/ticket_add.php
// Support ticket creation + list (admin/staff)

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/audit.php';

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$_active = 'tickets';
$page_title = 'Support & Ticketing';

$ticketCols = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$clientCols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$userCols = [];
try {
  $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $userCols = [];
}

$has = function(array $cols, string $col): bool {
  return in_array($col, $cols, true);
};

$pick_col = function(array $cols, array $cands): string {
  foreach ($cands as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return '';
};

$hasCreated = $has($ticketCols, 'created_at');
$hasUpdated = $has($ticketCols, 'updated_at');
$assignCol = $pick_col($ticketCols, ['assign_to', 'assigned_to', 'assigned_user', 'assigned_user_id', 'assign_to_id']);
$solvedCol = $pick_col($ticketCols, ['solved_time', 'solved_at', 'closed_at', 'resolved_at']);
$userIdCol = $pick_col($userCols, ['id', 'user_id']);
$userNameCol = $pick_col($userCols, ['full_name', 'name', 'username']);

function normalize_client_ref(string $ref): string {
  $ref = trim($ref);
  if ($ref === '') return '';
  if (preg_match('/\((\d+)\)/', $ref, $m)) return $m[1];
  // if the whole value is numeric, use it as id
  if (preg_match('/^\d+$/', $ref)) return $ref;
  return $ref;
}

function find_client(PDO $pdo, string $ref): ?array {
  $raw = trim($ref);
  if ($raw === '') return null;

  // Handle values like "CODE (123)" — prefer id first, then code part
  $idCandidate = null;
  $codeCandidate = null;
  if (preg_match('/^(.+?)\s*\((\d+)\)/', $raw, $m)) {
    $codeCandidate = trim($m[1]);
    $idCandidate = $m[2];
  }

  $ref = normalize_client_ref($raw);
  if ($ref === '') return null;

  // try id candidate first
  if ($idCandidate && ctype_digit($idCandidate)) {
    $st = $pdo->prepare("SELECT * FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$idCandidate]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
  }

  // try by normalized id
  if (ctype_digit($ref)) {
    $st = $pdo->prepare("SELECT * FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$ref]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
  }

  // exact matches on common identifiers
  $cands = array_values(array_filter([$ref, $codeCandidate], fn($v)=>$v!==null && $v!==''));
  foreach ($cands as $cand) {
    $st = $pdo->prepare("SELECT * FROM clients WHERE client_code=? OR pppoe_id=? OR name=? LIMIT 1");
    $st->execute([$cand, $cand, $cand]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
  }

  // last fallback: partial LIKE on code/pppoe/name
  $like = "%$ref%";
  $st = $pdo->prepare("SELECT * FROM clients WHERE client_code LIKE ? OR pppoe_id LIKE ? OR name LIKE ? ORDER BY id DESC LIMIT 1");
  $st->execute([$like, $like, $like]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$errors = [];
$notice = '';
$is_edit = false;
$ticket = null;

$edit_id = max(0, (int)($_GET['id'] ?? 0));
if ($edit_id > 0) {
  $st = $pdo->prepare("SELECT * FROM tickets WHERE id=? LIMIT 1");
  $st->execute([$edit_id]);
  $ticket = $st->fetch(PDO::FETCH_ASSOC);
  if ($ticket) {
    $is_edit = true;
  } else {
    $errors[] = 'Ticket not found.';
  }
}

function format_duration(?string $start, ?string $end = null): string {
  if (!$start) return '';
  try {
    $startAt = new DateTime($start);
    $endAt = $end ? new DateTime($end) : new DateTime();
    $diff = $startAt->diff($endAt);
    return sprintf('%dd:%dh:%dm:%ds', (int)$diff->days, (int)$diff->h, (int)$diff->i, (int)$diff->s);
  } catch (Throwable $e) {
    return '';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify()) {
    $errors[] = 'Invalid CSRF token. Please try again.';
  }

  if (isset($_POST['solve_ticket_id'])) {
    $solveTicketId = (int)($_POST['solve_ticket_id'] ?? 0);
    $solveOnline = trim((string)($_POST['solve_online'] ?? ''));
    if (!$errors) {
      if ($solveTicketId <= 0) {
        $errors[] = 'Solve operation not available.';
      } else {
        $ticketSnapshot = null;
        try {
          $stTmp = $pdo->prepare("SELECT t.id, t.client_id, t.subject, t.status, t.problem_priority, t.problem_category, c.client_code, c.pppoe_id FROM tickets t LEFT JOIN clients c ON c.id = t.client_id WHERE t.id=? LIMIT 1");
          $stTmp->execute([$solveTicketId]);
          $ticketSnapshot = $stTmp->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
          $ticketSnapshot = null;
        }

        if ($solveOnline === '0') {
          $_SESSION['flash_error'] = 'Client is offline. Cannot mark as solved.';
          header("Location: /public/ticket_add.php");
          exit;
        }
        if ($solveOnline !== '1' && $has($clientCols, 'is_online')) {
          $st = $pdo->prepare("SELECT client_id FROM tickets WHERE id=?");
          $st->execute([$solveTicketId]);
          $cid = (int)$st->fetchColumn();
          if ($cid > 0) {
            $st = $pdo->prepare("SELECT is_online FROM clients WHERE id=?");
            $st->execute([$cid]);
            $isOnline = (int)$st->fetchColumn();
            if ($isOnline !== 1) {
              $_SESSION['flash_error'] = 'Client is offline. Cannot mark as solved.';
              header("Location: /public/ticket_add.php");
              exit;
            }
          }
        }
        $sets = ["status='closed'"];
        if ($solvedCol !== '') {
          $sets[] = "`{$solvedCol}`=NOW()";
        }
        if ($hasUpdated) {
          $sets[] = "`updated_at`=NOW()";
        }
        $sql = "UPDATE tickets SET ".implode(', ', $sets)." WHERE id=?";
        $st = $pdo->prepare($sql);
        $st->execute([$solveTicketId]);
        try {
          audit('ticket_solve', 'ticket', $solveTicketId, [
            'prev_status' => $ticketSnapshot['status'] ?? null,
            'new_status' => 'closed',
            'priority' => $ticketSnapshot['problem_priority'] ?? null,
            'category' => $ticketSnapshot['problem_category'] ?? null,
            'subject' => $ticketSnapshot['subject'] ?? null,
            'client_id' => $ticketSnapshot['client_id'] ?? null,
            'client_code' => $ticketSnapshot['client_code'] ?? null,
            'pppoe_id' => $ticketSnapshot['pppoe_id'] ?? null,
          ]);
        } catch (Throwable $e) {
          try {
            $pdo->prepare("INSERT INTO audit_logs (entity, entity_id, action, old_json, new_json, user_id, created_at) VALUES (?,?,?,?,?,?,NOW())")
              ->execute([
                'ticket',
                $solveTicketId,
                'ticket_solve',
                json_encode([
                  'status' => $ticketSnapshot['status'] ?? null,
                  'client_id' => $ticketSnapshot['client_id'] ?? null,
                  'client_code' => $ticketSnapshot['client_code'] ?? null,
                  'pppoe_id' => $ticketSnapshot['pppoe_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode([
                  'status' => 'closed',
                  'client_id' => $ticketSnapshot['client_id'] ?? null,
                  'client_code' => $ticketSnapshot['client_code'] ?? null,
                  'pppoe_id' => $ticketSnapshot['pppoe_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $_SESSION['user']['id'] ?? null,
              ]);
          } catch (Throwable $_) { /* ignore audit failures */ }
        }
        $_SESSION['flash_success'] = 'Ticket solved successfully.';
        header("Location: /public/ticket_add.php");
        exit;
      }
    }
  }

  if (isset($_POST['assign_ticket_id'])) {
    $assignTicketId = (int)($_POST['assign_ticket_id'] ?? 0);
    $assignUserId = (int)($_POST['assign_user_id'] ?? 0);

    if (!$errors) {
      if ($assignTicketId <= 0 || $assignCol === '') {
        $errors[] = 'Assign operation not available.';
      } elseif ($assignUserId <= 0) {
        $errors[] = 'Please select an assignee.';
      } else {
        $sets = ["`{$assignCol}`=?"];
        $vals = [$assignUserId];
        if ($hasUpdated) {
          $sets[] = "`updated_at`=NOW()";
        }
        $sets[] = "status=CASE WHEN status='open' THEN 'in_progress' ELSE status END";
        $sql = "UPDATE tickets SET ".implode(', ', $sets)." WHERE id=?";
        $vals[] = $assignTicketId;
        $st = $pdo->prepare($sql);
        $st->execute($vals);
        $_SESSION['flash_success'] = 'Ticket assigned successfully. Will notify via twist notification.';
        header("Location: /public/ticket_add.php");
        exit;
      }
    }
  }

  $clientRef = trim((string)($_POST['client_ref'] ?? ''));
  $subject = trim((string)($_POST['subject'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));
  $problemCategory = trim((string)($_POST['problem_category'] ?? ''));
  $problemPriority = trim((string)($_POST['problem_priority'] ?? ''));
  $complainedNo = trim((string)($_POST['complained_number'] ?? ''));
  $description = trim((string)($_POST['description'] ?? ''));
  $status = trim((string)($_POST['status'] ?? 'open'));
  $sendSms = isset($_POST['send_sms']) ? 1 : 0;

  if ($subject === '' || mb_strlen($subject) < 3) {
    $errors[] = 'Subject is required (min 3 characters).';
  }
  if ($message === '' || mb_strlen($message) < 5) {
    $errors[] = 'Message is required (min 5 characters).';
  }

  $client = null;
  if ($is_edit && $clientRef === '' && !empty($ticket['client_id'])) {
    $client = find_client($pdo, (string)$ticket['client_id']);
  } else {
    $client = find_client($pdo, $clientRef);
  }
  if (!$client) {
    $errors[] = 'Valid client ID / code is required.';
  }

  $allowedStatus = ['open', 'in_progress', 'closed'];
  if (!in_array($status, $allowedStatus, true)) {
    $status = 'open';
  }

  if (!$errors) {
    $now = date('Y-m-d H:i:s');
    $clientId = (int)($client['id'] ?? 0);
    $customerName = (string)($client['name'] ?? '');
    $mobileExisting = (string)($client['mobile'] ?? '');
    $clientAddress = (string)($client['address'] ?? '');
    $zone = (string)($client['area'] ?? '');
    $billingStatus = (string)($client['status'] ?? '');
    $monthlyBill = (string)($client['monthly_bill'] ?? '');
    $paymentStatus = (string)($client['payment_status'] ?? '');
    $ipAddress = (string)($client['ip_address'] ?? '');
    $macCallerId = (string)($client['caller_mac'] ?? '');
    $clientMac = (string)($client['router_mac'] ?? ($client['ap_mac'] ?? ''));
    $oltPort = (string)($client['olt_port'] ?? '');
    $lastLogout = (string)($client['last_logout_at'] ?? '');
    $connectivity = isset($client['is_online']) ? ((int)$client['is_online'] === 1 ? 'online' : 'offline') : '';

    if ($is_edit) {
      $sets = [];
      $vals = [];
      $add = function(string $col, $val) use (&$sets, &$vals, $ticketCols) {
        if (in_array($col, $ticketCols, true)) {
          $sets[] = "`$col`=?";
          $vals[] = $val;
        }
      };

      $add('client_id', $clientId);
      $add('subject', $subject);
      $add('message', $message);
      $add('status', $status);
      $add('customer_name', $customerName);
      $add('mobile_existing', $mobileExisting);
      $add('client_address', $clientAddress);
      $add('zone', $zone);
      $add('billing_status', $billingStatus);
      $add('monthly_bill', $monthlyBill);
      $add('payment_status', $paymentStatus);
      $add('ip_address', $ipAddress);
      $add('mac_caller_id', $macCallerId);
      $add('client_mac_address', $clientMac);
      $add('olt_port', $oltPort);
      $add('last_logout_time', $lastLogout);
      $add('connectivity_status', $connectivity);
      $add('problem_category', $problemCategory);
      $add('problem_priority', $problemPriority);
      $add('complained_number', $complainedNo);
      $add('description', $description !== '' ? $description : $message);
      $add('send_sms', $sendSms);
      if ($solvedCol !== '' && $status === 'closed') {
        $currentSolved = $ticket[$solvedCol] ?? '';
        if ($currentSolved === '' || $currentSolved === null) {
          $add($solvedCol, $now);
        }
      }
      if ($hasUpdated) $add('updated_at', $now);

      if ($sets) {
        $vals[] = $edit_id;
        $sql = "UPDATE tickets SET ".implode(', ', $sets)." WHERE id=?";
        $st = $pdo->prepare($sql);
        $st->execute($vals);
      }
      $_SESSION['flash_success'] = 'Ticket updated successfully.';
      header("Location: /public/ticket_add.php?id={$edit_id}");
      exit;
    } else {
      $fields = [];
      $placeholders = [];
      $vals = [];
      $add = function(string $col, $val) use (&$fields, &$placeholders, &$vals, $ticketCols) {
        if (in_array($col, $ticketCols, true)) {
          $fields[] = "`$col`";
          $placeholders[] = "?";
          $vals[] = $val;
        }
      };

      $add('client_id', $clientId);
      $add('subject', $subject);
      $add('message', $message);
      $add('status', $status);
      $add('customer_name', $customerName);
      $add('mobile_existing', $mobileExisting);
      $add('client_address', $clientAddress);
      $add('zone', $zone);
      $add('billing_status', $billingStatus);
      $add('monthly_bill', $monthlyBill);
      $add('payment_status', $paymentStatus);
      $add('ip_address', $ipAddress);
      $add('mac_caller_id', $macCallerId);
      $add('client_mac_address', $clientMac);
      $add('olt_port', $oltPort);
      $add('last_logout_time', $lastLogout);
      $add('connectivity_status', $connectivity);
      $add('problem_category', $problemCategory);
      $add('problem_priority', $problemPriority);
      $add('complained_number', $complainedNo);
      $add('description', $description !== '' ? $description : $message);
      $add('send_sms', $sendSms);
      if ($solvedCol !== '' && $status === 'closed') $add($solvedCol, $now);
      if ($hasCreated) $add('created_at', $now);
      if ($hasUpdated) $add('updated_at', $now);

      $sql = "INSERT INTO tickets (".implode(', ', $fields).") VALUES (".implode(', ', $placeholders).")";
      $st = $pdo->prepare($sql);
      $st->execute($vals);
      $ticketId = (int)$pdo->lastInsertId();
      try {
        audit('ticket_create', 'ticket', $ticketId, [
          'client_id' => $clientId,
          'client_code' => $client['client_code'] ?? null,
          'pppoe_id' => $client['pppoe_id'] ?? null,
          'subject' => $subject,
          'status' => $status,
          'priority' => $problemPriority,
          'category' => $problemCategory,
          'created_at' => $now,
        ]);
      } catch (Throwable $e) {
        try {
          $pdo->prepare("INSERT INTO audit_logs (entity, entity_id, action, new_json, user_id, created_at) VALUES (?,?,?,?,?,NOW())")
            ->execute([
              'ticket',
              $ticketId ?: null,
              'ticket_create',
              json_encode([
                'client_id' => $clientId,
                'client_code' => $client['client_code'] ?? null,
                'pppoe_id' => $client['pppoe_id'] ?? null,
                'subject' => $subject,
                'status' => $status,
                'priority' => $problemPriority,
                'category' => $problemCategory,
                'created_at' => $now,
              ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
              $_SESSION['user']['id'] ?? null,
            ]);
        } catch (Throwable $_) { /* ignore audit failures */ }
      }
      $_SESSION['flash_success'] = 'Ticket created successfully.';
      header("Location: /public/ticket_add.php?created=1");
      exit;
    }
  }
}

function distinct_values(PDO $pdo, string $sql): array {
  try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return array_values(array_filter(array_map('trim', $rows), fn($v) => $v !== ''));
  } catch (Throwable $e) {
    return [];
  }
}

$categoryOptions = $has($ticketCols, 'problem_category')
  ? distinct_values($pdo, "SELECT DISTINCT problem_category FROM tickets ORDER BY problem_category ASC")
  : [];
$priorityOptions = $has($ticketCols, 'problem_priority')
  ? distinct_values($pdo, "SELECT DISTINCT problem_priority FROM tickets ORDER BY problem_priority ASC")
  : [];
$zoneOptions = [];
if ($has($ticketCols, 'zone')) {
  $zoneOptions = distinct_values($pdo, "SELECT DISTINCT zone FROM tickets ORDER BY zone ASC");
} elseif ($has($clientCols, 'area')) {
  $zoneOptions = distinct_values($pdo, "SELECT DISTINCT area FROM clients ORDER BY area ASC");
}

$filters = [
  'category' => trim((string)($_GET['category'] ?? '')),
  'zone' => trim((string)($_GET['zone'] ?? '')),
  'status' => trim((string)($_GET['status'] ?? '')),
  'priority' => trim((string)($_GET['priority'] ?? '')),
  'from' => trim((string)($_GET['from'] ?? '')),
  'to' => trim((string)($_GET['to'] ?? '')),
  'complained' => trim((string)($_GET['complained'] ?? '')),
  'search' => trim((string)($_GET['search'] ?? '')),
];

$where = [];
$params = [];
$zoneCol = $has($ticketCols, 'zone') ? 't.zone' : ($has($clientCols, 'area') ? 'c.area' : '');

if ($filters['category'] !== '' && $has($ticketCols, 'problem_category')) {
  $where[] = 't.problem_category = :category';
  $params[':category'] = $filters['category'];
}
if ($filters['zone'] !== '' && $zoneCol !== '') {
  $where[] = $zoneCol.' = :zone';
  $params[':zone'] = $filters['zone'];
}
if ($filters['status'] !== '') {
  $where[] = 't.status = :status';
  $params[':status'] = $filters['status'];
}
if ($filters['priority'] !== '' && $has($ticketCols, 'problem_priority')) {
  $where[] = 't.problem_priority = :priority';
  $params[':priority'] = $filters['priority'];
}
if ($filters['complained'] !== '' && $has($ticketCols, 'complained_number')) {
  $where[] = 't.complained_number LIKE :complained';
  $params[':complained'] = '%'.$filters['complained'].'%';
}
if ($hasCreated && ($filters['from'] !== '' || $filters['to'] !== '')) {
  if ($filters['from'] !== '') {
    $where[] = 'DATE(t.created_at) >= :from';
    $params[':from'] = $filters['from'];
  }
  if ($filters['to'] !== '') {
    $where[] = 'DATE(t.created_at) <= :to';
    $params[':to'] = $filters['to'];
  }
}
if ($filters['search'] !== '') {
  $searchParts = [];
  $params[':search'] = '%'.$filters['search'].'%';
  $searchParts[] = "CAST(t.id AS CHAR) LIKE :search";
  if ($has($ticketCols, 'subject')) $searchParts[] = "t.subject LIKE :search";
  if ($has($ticketCols, 'customer_name')) $searchParts[] = "t.customer_name LIKE :search";
  if ($has($ticketCols, 'complained_number')) $searchParts[] = "t.complained_number LIKE :search";
  if ($has($clientCols, 'client_code')) $searchParts[] = "c.client_code LIKE :search";
  if ($has($clientCols, 'pppoe_id')) $searchParts[] = "c.pppoe_id LIKE :search";
  if ($searchParts) $where[] = '('.implode(' OR ', $searchParts).')';
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$statusBase = $hasCreated ? "created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')" : "1";
$countTotal = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE $statusBase")->fetchColumn();
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open' AND $statusBase")->fetchColumn();
$countProcessing = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in_progress' AND $statusBase")->fetchColumn();
$countSolved = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='closed' AND $statusBase")->fetchColumn();

$joinAssignee = '';
$selectAssignee = $assignCol !== '' ? "t.`{$assignCol}` AS assigned_user_id" : "'' AS assigned_user_id";
$selectAssigneeName = "'' AS assigned_to_name";
if ($assignCol !== '' && $userIdCol !== '' && $userNameCol !== '') {
  $joinAssignee = "LEFT JOIN users u ON u.`{$userIdCol}` = t.`{$assignCol}`";
  $selectAssigneeName = "u.`{$userNameCol}` AS assigned_to_name";
}
$selectSolved = $solvedCol !== '' ? "t.`{$solvedCol}` AS solved_time" : "'' AS solved_time";
$selectConn = $has($ticketCols, 'connectivity_status') ? "t.connectivity_status AS t_connectivity_status" : "'' AS t_connectivity_status";
$selectUptime = $has($ticketCols, 'uptime') ? "t.uptime AS t_uptime" : "'' AS t_uptime";
$selectLastLogout = $has($ticketCols, 'last_logout_time') ? "t.last_logout_time AS t_last_logout_time" : "'' AS t_last_logout_time";
$selectMacCaller = $has($ticketCols, 'mac_caller_id') ? "t.mac_caller_id AS t_mac_caller_id" : "'' AS t_mac_caller_id";
$selectTicketIp = $has($ticketCols, 'ip_address') ? "t.ip_address AS t_ip_address" : "'' AS t_ip_address";
$selectClientMac = $has($clientCols, 'caller_mac') ? "c.caller_mac AS client_caller_mac" : "'' AS client_caller_mac";
$selectClientLastLogout = $has($clientCols, 'last_logout_at') ? "c.last_logout_at AS client_last_logout" : "'' AS client_last_logout";
$selectClientIp = $has($clientCols, 'ip_address') ? "c.ip_address AS client_ip" : "'' AS client_ip";
$selectClientOnline = $has($clientCols, 'is_online') ? "c.is_online AS client_online" : "NULL AS client_online";

$sql = "
  SELECT
    t.id,
    t.client_id,
    t.subject,
    t.status,
    t.created_at,
    t.updated_at,
    t.problem_category,
    t.problem_priority,
    t.complained_number,
    t.zone,
    t.customer_name,
    t.mobile_existing,
    {$selectTicketIp},
    t.description,
    {$selectConn},
    {$selectUptime},
    {$selectLastLogout},
    {$selectMacCaller},
    {$selectClientMac},
    {$selectClientLastLogout},
    {$selectClientIp},
    {$selectClientOnline},
    {$selectAssignee},
    {$selectAssigneeName},
    {$selectSolved},
    ".($has($clientCols, 'client_code') ? "c.client_code" : "''")." AS client_code,
    ".($has($clientCols, 'pppoe_id') ? "c.pppoe_id" : "''")." AS pppoe_id,
    ".($has($clientCols, 'name') ? "c.name" : "t.customer_name")." AS client_name,
    ".($has($clientCols, 'mobile') ? "c.mobile" : "t.mobile_existing")." AS client_mobile,
    ".($has($clientCols, 'area') ? "c.area" : "t.zone")." AS area,
    ".($has($clientCols, 'sub_zone') ? "c.sub_zone" : "''")." AS sub_zone,
    ".($has($clientCols, 'box') ? "c.box" : "''")." AS box
  FROM tickets t
  LEFT JOIN clients c ON c.id = t.client_id
  {$joinAssignee}
  $whereSql
  ORDER BY t.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($params);
$tickets = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$clientOptions = [];
try {
  $clientOptions = $pdo->query("SELECT id, client_code, pppoe_id, name FROM clients WHERE COALESCE(is_deleted,0)=0 ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $clientOptions = [];
}

$users = [];
if ($userIdCol !== '' && $userNameCol !== '') {
  try {
    $users = $pdo->query("SELECT {$userIdCol} AS id, {$userNameCol} AS name FROM users WHERE COALESCE(status,1)=1 ORDER BY {$userNameCol} ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $users = [];
  }
}

require_once __DIR__ . '/../partials/partials_header.php';
?>

<style>
  .ticket-kpi{
    border-radius:14px;
    color:#fff;
    padding:14px 18px;
    display:flex;
    align-items:center;
    gap:12px;
    min-height:90px;
    box-shadow:0 10px 18px rgba(0,0,0,0.1);
  }
  .ticket-kpi .icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(255,255,255,.2);
    font-size:22px;
  }
  .ticket-kpi .num{ font-size:26px; font-weight:700; line-height:1; }
  .ticket-kpi .lbl{ font-size:13px; opacity:.9; }
  .ticket-kpi.total{ background:linear-gradient(45deg,#00bcd4,#00acc1); }
  .ticket-kpi.pending{ background:linear-gradient(45deg,#ff5252,#ff6f61); }
  .ticket-kpi.processing{ background:linear-gradient(45deg,#ff9800,#fb8c00); }
  .ticket-kpi.solved{ background:linear-gradient(45deg,#4caf50,#43a047); }
  .ticket-filter-card{ border-radius:14px; }
  .ticket-table thead th{ background:#223a4e; color:#fff; }

  /* Keep the ticket modals clickable above any dark overlay */
  .modal-backdrop{
    z-index: 2080 !important;
    background-color: rgba(15, 23, 42, 0.18);
  }
  .modal{
    z-index: 2090 !important;
  }

  /* Solve modal */
  #solveModal .modal-content{
    border-radius: 14px;
    border: 0;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.2);
  }
  #solveModal .modal-header{
    border-bottom: 0;
    padding-bottom: 0.25rem;
  }
  #solveModal .modal-title{
    font-weight: 600;
  }
  #solveModal .form-label{
    font-size: 11px;
    letter-spacing: .04em;
    color: #64748b;
  }
  #solveModal .form-control{
    height: 38px;
    border-radius: 8px;
  }
  #solveModal #solveOnlineBtn{
    border-radius: 999px;
    font-weight: 600;
    box-shadow: 0 10px 18px rgba(34, 197, 94, 0.25);
  }
  #solveModal .modal-footer{
    border-top: 0;
    justify-content: space-between;
    padding-top: 0.5rem;
  }
  #solveModal .modal-footer .btn{
    border-radius: 999px;
    padding: 0.45rem 1.6rem;
  }
  #solveModal .modal-footer .btn-danger{
    box-shadow: 0 10px 18px rgba(239, 68, 68, 0.25);
  }
  #solveModal .modal-footer .btn-dark{
    box-shadow: 0 10px 18px rgba(15, 23, 42, 0.25);
  }
</style>

<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="mb-0"><i class="bi bi-question-circle me-1"></i> Support & Ticketing</h4>
      <div class="text-muted small">Daily Support Ticket</div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-life-preserver me-1"></i> Support & Ticketing
      <span class="mx-1">&rsaquo;</span> Client Support
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
      <!-- <span class="badge bg-dark">Accepted (Client's)</span>
      <span class="badge bg-light text-dark border">Pending (Client's)</span>
      <span class="badge bg-light text-dark border">MAC Reseller's</span>
      <span class="badge bg-light text-dark border">Bandwidth POP's</span> -->
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ticketModal">
      <i class="bi bi-plus-circle"></i> Open New Ticket
    </button>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <script>
      window.addEventListener('load', () => {
        if (typeof globalToast === 'function') {
          globalToast(<?= json_encode($_SESSION['flash_success'], JSON_UNESCAPED_UNICODE) ?>, 'success');
        }
      });
    </script>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <script>
      window.addEventListener('load', () => {
        if (typeof globalToast === 'function') {
          globalToast(<?= json_encode($_SESSION['flash_error'], JSON_UNESCAPED_UNICODE) ?>, 'danger');
        }
      });
    </script>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger py-2">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-lg-3 col-md-6">
      <div class="ticket-kpi total">
        <div class="icon"><i class="bi bi-check2-circle"></i></div>
        <div><div class="num"><?= (int)$countTotal ?></div><div class="lbl">Total Tickets</div></div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="ticket-kpi pending">
        <div class="icon"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="num"><?= (int)$countPending ?></div><div class="lbl">Pending Tickets</div></div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="ticket-kpi processing">
        <div class="icon"><i class="bi bi-gear"></i></div>
        <div><div class="num"><?= (int)$countProcessing ?></div><div class="lbl">Processing Tickets</div></div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="ticket-kpi solved">
        <div class="icon"><i class="bi bi-patch-check"></i></div>
        <div><div class="num"><?= (int)$countSolved ?></div><div class="lbl">Solved Tickets</div></div>
      </div>
    </div>
  </div>

  <?php if ($is_edit): ?>
    <div class="card ticket-filter-card shadow-sm mb-4">
      <div class="card-header bg-light fw-semibold">Edit Ticket #<?= (int)$ticket['id'] ?></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_input_html() ?>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Client</label>
              <input type="text" class="form-control" name="client_ref" list="clientList" value="<?= h((string)($ticket['client_id'] ?? '')) ?>" placeholder="Client ID / Code">
            </div>
            <div class="col-md-4">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" name="subject" value="<?= h((string)($ticket['subject'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['open'=>'Open','in_progress'=>'In Progress','closed'=>'Closed'] as $k=>$v): ?>
                  <option value="<?= h($k) ?>" <?= ($ticket['status'] ?? '')===$k?'selected':'' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Problem Category</label>
              <input type="text" class="form-control" name="problem_category" value="<?= h((string)($ticket['problem_category'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Problem Priority</label>
              <input type="text" class="form-control" name="problem_priority" value="<?= h((string)($ticket['problem_priority'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Complained No</label>
              <input type="text" class="form-control" name="complained_number" value="<?= h((string)($ticket['complained_number'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4"><?= h((string)($ticket['message'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"><?= h((string)($ticket['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-4">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="send_sms" id="sendSmsEdit" <?= ((int)($ticket['send_sms'] ?? 0)===1)?'checked':'' ?>>
                <label class="form-check-label" for="sendSmsEdit">Send SMS to Client</label>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary">Update Ticket</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="card ticket-filter-card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Support Category</label>
          <select class="form-select" name="category">
            <option value="">Select</option>
            <?php foreach ($categoryOptions as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $filters['category']===$opt?'selected':'' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Zone</label>
          <select class="form-select" name="zone">
            <option value="">Select</option>
            <?php foreach ($zoneOptions as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $filters['zone']===$opt?'selected':'' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">Select</option>
            <option value="open" <?= $filters['status']==='open'?'selected':'' ?>>Open</option>
            <option value="in_progress" <?= $filters['status']==='in_progress'?'selected':'' ?>>In Progress</option>
            <option value="closed" <?= $filters['status']==='closed'?'selected':'' ?>>Closed</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Priority</label>
          <select class="form-select" name="priority">
            <option value="">Select</option>
            <?php foreach ($priorityOptions as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $filters['priority']===$opt?'selected':'' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">From Date</label>
          <input type="date" class="form-control" name="from" value="<?= h($filters['from']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To Date</label>
          <input type="date" class="form-control" name="to" value="<?= h($filters['to']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Complained No</label>
          <input type="text" class="form-control" name="complained" value="<?= h($filters['complained']) ?>" placeholder="Complained No">
        </div>
        <div class="col-md-3">
          <label class="form-label">Search</label>
          <input type="text" class="form-control" name="search" value="<?= h($filters['search']) ?>" placeholder="Ticket, Client Code...">
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
          <a href="/public/ticket_add.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle ticket-table">
          <thead>
            <tr>
              <th>TicketNo.</th>
              <th>ClientCode</th>
              <th>ID/IP</th>
              <th>CustomerName</th>
              <th>Mobile(Existing)</th>
              <th>ComplainNo.</th>
              <th>Zone</th>
              <th>Subzone</th>
              <th>Box</th>
              <th>Problem</th>
              <th>Priority</th>
              <th>Complain Time</th>
              <th>CreatedBy</th>
              <th>Status</th>
              <th>Assign To</th>
              <th>Solved Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$tickets): ?>
              <tr><td colspan="16" class="text-center text-muted">No data available in table</td></tr>
            <?php else: foreach ($tickets as $t): ?>
              <?php
                $assignTo = trim((string)($t['assigned_to_name'] ?? ''));
                if ($assignTo === '') $assignTo = trim((string)($t['assigned_user_id'] ?? ''));
                if ($assignTo === '') $assignTo = '-';
                $solvedAt = trim((string)($t['solved_time'] ?? ''));
                $solvedDisplay = $solvedAt;
                if ($solvedDisplay === '' && (($t['status'] ?? '') === 'closed')) {
                  $solvedDisplay = (string)($t['updated_at'] ?? '');
                }
                $duration = format_duration($t['created_at'] ?? '', $solvedAt !== '' ? $solvedAt : null);
                if ($solvedDisplay === '') $solvedDisplay = '-';
                $fallbackIp = trim((string)($t['t_ip_address'] ?? ''));
                if ($fallbackIp === '') $fallbackIp = trim((string)($t['client_ip'] ?? ''));
                $connVal = trim((string)($t['t_connectivity_status'] ?? ''));
                if ($connVal === '' && $t['client_online'] !== null) {
                  $connVal = ((int)$t['client_online'] === 1) ? 'Online' : 'Offline';
                }
                $uptimeVal = trim((string)($t['t_uptime'] ?? ''));
                $logoutVal = trim((string)($t['t_last_logout_time'] ?? ''));
                if ($logoutVal === '') $logoutVal = trim((string)($t['client_last_logout'] ?? ''));
                $macVal = trim((string)($t['t_mac_caller_id'] ?? ''));
                if ($macVal === '') $macVal = trim((string)($t['client_caller_mac'] ?? ''));
              ?>
              <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= h($t['client_code'] ?? '') ?></td>
                <td><?= h($t['pppoe_id'] ?: ($fallbackIp ?: '')) ?></td>
                <td><?= h($t['client_name'] ?: ($t['customer_name'] ?? '')) ?></td>
                <td><?= h($t['client_mobile'] ?: ($t['mobile_existing'] ?? '')) ?></td>
                <td><?= h($t['complained_number'] ?? '') ?></td>
                <td><?= h($t['area'] ?: ($t['zone'] ?? '')) ?></td>
                <td><?= h($t['sub_zone'] ?? '') ?></td>
                <td><?= h($t['box'] ?? '') ?></td>
                <td><?= h($t['problem_category'] ?: ($t['subject'] ?? '')) ?></td>
                <td><?= h($t['problem_priority'] ?? '') ?></td>
                <td><?= h($t['created_at'] ?? '') ?></td>
                <td><?= h($_SESSION['user']['username'] ?? 'admin') ?></td>
                <td>
                  <?php
                    $status = strtolower((string)($t['status'] ?? ''));
                    $statusLabel = $status === 'in_progress' ? 'Processing' : ($status === 'closed' ? 'Solved' : 'Pending');
                    $statusClass = $status === 'closed' ? 'bg-success' : ($status === 'in_progress' ? 'bg-warning text-dark' : 'bg-danger');
                  ?>
                  <?php if ($status === 'in_progress'): ?>
                    <button type="button" class="badge <?= h($statusClass) ?> border-0 btn-solve"
                      data-bs-toggle="modal" data-bs-target="#solveModal"
                      data-ticket-id="<?= (int)$t['id'] ?>"
                      data-client-id="<?= (int)($t['client_id'] ?? 0) ?>"
                      data-conn="<?= h($connVal) ?>"
                      data-uptime="<?= h($uptimeVal) ?>"
                      data-lastlogout="<?= h($logoutVal) ?>"
                      data-mac="<?= h($macVal) ?>"
                      data-ip="<?= h($fallbackIp) ?>">
                      <?= h($statusLabel) ?>
                    </button>
                  <?php else: ?>
                    <span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex flex-column gap-1">
                    <span><?= h($assignTo) ?></span>
                    <?php if ($assignCol !== '' && $users): ?>
                      <button type="button" class="btn btn-sm btn-primary btn-assign"
                        data-bs-toggle="modal" data-bs-target="#assignModal"
                        data-ticket-id="<?= (int)$t['id'] ?>" data-assigned="<?= h((string)($t['assigned_user_id'] ?? '')) ?>">
                        <?= $assignTo === '-' ? 'Assign' : 'Change' ?>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div><?= h($solvedDisplay) ?></div>
                  <?php if ($duration !== ''): ?>
                    <div class="small text-muted">Duration <?= h($duration) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Solve Modal -->
<div class="modal fade" id="solveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Press Yes if solved</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" id="solveForm">
        <div class="modal-body">
          <?= csrf_input_html() ?>
          <input type="hidden" name="solve_ticket_id" id="solveTicketId" value="">
          <input type="hidden" name="solve_online" id="solveOnlineState" value="">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label text-uppercase small">Connectivity Status</label>
              <input type="text" class="form-control" id="solveConn" readonly>
            </div>
            <div class="col-md-5 d-flex align-items-end">
              <button type="button" class="btn btn-success w-100" id="solveOnlineBtn">Online</button>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small">Uptime</label>
              <input type="text" class="form-control" id="solveUptime" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small">Last Logout Time</label>
              <input type="text" class="form-control" id="solveLastLogout" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small">Mac Address/Caller ID</label>
              <input type="text" class="form-control" id="solveMac" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label text-uppercase small">IP Address</label>
              <input type="text" class="form-control" id="solveIp" readonly>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark" id="solveSubmitBtn">Yes, Solved</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Solver</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <?= csrf_input_html() ?>
          <input type="hidden" name="assign_ticket_id" id="assignTicketId" value="">
          <div class="mb-3">
            <label class="form-label">Assign To</label>
            <select name="assign_user_id" id="assignUserId" class="form-select" required>
              <option value="">Select</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= h($u['name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
          <button type="submit" class="btn btn-success">Yes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Move all modals to <body> so bootstrap's backdrop sits underneath correctly.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal').forEach(modal => {
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
  });
});
</script>

<script>
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-assign');
  if (!btn) return;
  const idInput = document.getElementById('assignTicketId');
  const userSelect = document.getElementById('assignUserId');
  if (idInput) idInput.value = btn.dataset.ticketId || '';
  if (userSelect) userSelect.value = btn.dataset.assigned || '';
});

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-solve');
  if (!btn) return;
  const idInput = document.getElementById('solveTicketId');
  if (idInput) idInput.value = btn.dataset.ticketId || '';
  const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
  const onlineBtn = document.getElementById('solveOnlineBtn');
  const solveSubmit = document.getElementById('solveSubmitBtn');
  const solveOnlineState = document.getElementById('solveOnlineState');
  const setSolveAllowed = (allowed) => {
    if (!solveSubmit) return;
    solveSubmit.disabled = !allowed;
    solveSubmit.classList.toggle('disabled', !allowed);
    if (solveOnlineState) solveOnlineState.value = allowed ? '1' : '0';
  };
  const setOnlineState = (isOnline, label) => {
    if (!onlineBtn) return;
    const text = label || (isOnline ? 'Online' : 'Offline');
    onlineBtn.textContent = text;
    onlineBtn.classList.remove('btn-success', 'btn-danger', 'btn-secondary');
    onlineBtn.classList.add(isOnline ? 'btn-success' : 'btn-danger');
    setSolveAllowed(isOnline);
  };

  const initConn = btn.dataset.conn || '';
  setVal('solveConn', initConn);
  setVal('solveUptime', btn.dataset.uptime || '');
  setVal('solveLastLogout', btn.dataset.lastlogout || '');
  setVal('solveMac', btn.dataset.mac || '');
  setVal('solveIp', btn.dataset.ip || '');

  if (initConn.toLowerCase() === 'online') setOnlineState(true, 'Online');
  else if (initConn.toLowerCase() === 'offline') setOnlineState(false, 'Offline');
  else setOnlineState(true, 'Online');

  const clientId = btn.dataset.clientId || '';
  if (!clientId) return;

  fetch(`/api/client_live_status.php?id=${encodeURIComponent(clientId)}`, { cache: 'no-store' })
    .then(res => res.ok ? res.json() : null)
    .then(data => {
      if (!data) return;
      if (typeof data.online === 'boolean') {
        setOnlineState(data.online, data.online ? 'Online' : 'Offline');
        setVal('solveConn', data.online ? 'Online' : 'Offline');
      }
      if (data.uptime) setVal('solveUptime', data.uptime);
      if (data.last_seen) setVal('solveLastLogout', data.last_seen);
      if (data.ip) setVal('solveIp', data.ip);
      const mac = data.caller_id || data.arp_mac;
      if (mac) setVal('solveMac', mac);
    })
    .catch(() => {});
});

document.addEventListener('submit', (e) => {
  const form = e.target.closest('#solveForm');
  if (!form) return;
  const solveSubmit = document.getElementById('solveSubmitBtn');
  if (solveSubmit && solveSubmit.disabled) {
    e.preventDefault();
    if (typeof globalToast === 'function') {
      globalToast('Client is offline. Cannot mark as solved.', 'warning');
    }
  }
});
</script>

<!-- New Ticket Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Support Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <?= csrf_input_html() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Client ID</label>
              <input type="text" class="form-control" name="client_ref" list="clientList" placeholder="e.g., R3545001" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" name="subject" placeholder="Short issue summary" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Problem Category</label>
              <input type="text" class="form-control" name="problem_category" placeholder="Connectivity / Billing / Other">
            </div>
            <div class="col-md-6">
              <label class="form-label">Problem Priority</label>
              <select name="problem_priority" class="form-select">
                <option value="">Select</option>
                <option>Low</option>
                <option>Normal</option>
                <option>High</option>
                <option>Urgent</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Complained Number</label>
              <input type="text" class="form-control" name="complained_number" placeholder="Complain No / Phone">
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4" placeholder="Write the issue details..." required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3" placeholder="Additional details (optional)"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="send_sms" id="sendSms">
                <label class="form-check-label" for="sendSms">Send SMS to Client</label>
              </div>
            </div>
          </div>
          <datalist id="clientList">
            <?php foreach ($clientOptions as $c): ?>
              <?php
                $optVal = trim((string)($c['client_code'] ?? ''));
                if ($optVal === '' && !empty($c['pppoe_id'])) $optVal = (string)$c['pppoe_id'];
                if ($optVal === '') continue;
              ?>
              <option value="<?= h($optVal) ?>"><?= h($optVal) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
