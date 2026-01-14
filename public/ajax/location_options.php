<?php
// /public/ajax/location_options.php
// CRUD endpoint for client_location_options (zone/sub-zone/box)

declare(strict_types=1);

require_once __DIR__ . '/../../app/require_login.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/acl.php';
require_once __DIR__ . '/../../app/location_options.php';
require_once __DIR__ . '/../../app/csrf_compat.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

function require_manage_perm(): void {
    $canManage = acl_can('settings.manage');
    $canClientAdd = acl_can('add.client');
    $canClientEdit = acl_can('edit.client') || acl_can('client.edit');
    if (!($canManage || $canClientAdd || $canClientEdit)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Permission denied.']);
        exit;
    }
}

if ($method === 'GET') {
    $rawType = (string)($_GET['type'] ?? '');
    $type = location_option_sanitize_type($rawType);
    $full = (int)($_GET['full'] ?? 0) === 1;
    if ($type === '') {
        $ref = strtolower((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if (strpos($ref, 'sub_zone') !== false || strpos($ref, 'subzone') !== false) {
            $type = 'sub_zone';
        } elseif (strpos($ref, 'box') !== false) {
            $type = 'box';
        } else {
            $type = 'area';
        }
    }
    $rows = $full ? location_option_list_full($pdo, $type) : location_option_list($pdo, $type);
    echo json_encode(['ok'=>true,'items'=>$rows]);
    exit;
}

require_manage_perm();

$inputRaw = file_get_contents('php://input') ?: '';
$input = json_decode($inputRaw, true);
if (!is_array($input)) {
    parse_str($inputRaw, $input);
    if (!is_array($input)) $input = [];
}

$token = $input['csrf_token'] ?? ($input['_csrf'] ?? ($input['csrf'] ?? null));
if (!$token) {
    $token = csrf_request_token();
}
$sessionToken = csrf_session_token();
if (!$sessionToken || !$token || !hash_equals((string)$sessionToken, (string)$token)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token.']);
    exit;
}

try {
    switch ($method) {
        case 'POST': {
            $type = location_option_sanitize_type((string)($input['type'] ?? ''));
            $label = trim((string)($input['label'] ?? ''));
            $details = trim((string)($input['details'] ?? ''));
            $parentArea = $input['parent_area'] ?? null;
            $parentSub  = $input['parent_sub_zone'] ?? null;
            if ($type === '') {
                if (!empty($parentSub)) {
                    $type = 'box';
                } elseif (!empty($parentArea)) {
                    $type = 'sub_zone';
                } else {
                    $type = 'area';
                }
            }
            if ($label === '') {
                throw new RuntimeException('Label is required.');
            }
            $result = location_option_store($pdo, $type, $label, $details, $parentArea, $type==='box' ? $parentSub : null);
            if (!$result['ok']) {
                throw new RuntimeException($result['error'] ?? 'Insert failed.');
            }
            echo json_encode(['ok'=>true,'data'=>$result]);
            break;
        }
        case 'PATCH': {
            $id = (int)($input['id'] ?? 0);
            $label = trim((string)($input['label'] ?? ''));
            $details = trim((string)($input['details'] ?? ''));
            $parentArea = $input['parent_area'] ?? null;
            $parentSub  = $input['parent_sub_zone'] ?? null;
            if ($id <= 0) {
                throw new RuntimeException('Invalid ID.');
            }
            $result = location_option_update($pdo, $id, $label, $details, $parentArea, $parentSub);
            if (!$result['ok']) {
                throw new RuntimeException($result['error'] ?? 'Update failed.');
            }
            echo json_encode(['ok'=>true,'data'=>$result]);
            break;
        }
        case 'DELETE': {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid ID.');
            }
            $result = location_option_delete($pdo, $id);
            if (!$result['ok']) {
                throw new RuntimeException($result['error'] ?? 'Delete failed.');
            }
            echo json_encode(['ok'=>true,'data'=>$result]);
            break;
        }
        default:
            http_response_code(405);
            echo json_encode(['ok'=>false,'error'=>'Method not allowed.']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
