<?php
// /app/location_options.php
// Helper to persist reusable dropdown options for client Area/Sub Zone/Box fields.

declare(strict_types=1);

if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Ensure backing table exists.
 */
function location_option_ensure_table(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) return;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `client_location_options` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` ENUM('area','sub_zone','box') NOT NULL,
            `label` VARCHAR(120) NOT NULL,
            `details` TEXT NULL,
            `parent_area` VARCHAR(120) NULL,
            `parent_sub_zone` VARCHAR(120) NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try {
        $pdo->exec("ALTER TABLE `client_location_options` ADD COLUMN `details` TEXT NULL AFTER `label`");
    } catch (Throwable $e) {
        // column already exists
    }
    try {
        $pdo->exec("ALTER TABLE `client_location_options` ADD COLUMN `parent_area` VARCHAR(120) NULL AFTER `details`");
    } catch (Throwable $e) {
        // already exists
    }
    try {
        $pdo->exec("ALTER TABLE `client_location_options` ADD COLUMN `parent_sub_zone` VARCHAR(120) NULL AFTER `parent_area`");
    } catch (Throwable $e) {
        // already exists
    }
    try {
        $pdo->exec("ALTER TABLE `client_location_options` DROP INDEX `uniq_type_label`");
    } catch (Throwable $e) {
        // ignore if not exists
    }
    $ensured = true;
}

/** @return array<string> */
function location_option_valid_types(): array {
    return ['area','sub_zone','box'];
}

function location_option_is_valid(string $type): bool {
    return in_array($type, location_option_valid_types(), true);
}

/**
 * Fetch saved labels for a type.
 * @return array<int,string>
 */
function location_option_list(PDO $pdo, string $type): array {
    if (!location_option_is_valid($type)) return [];
    location_option_ensure_table($pdo);
    $st = $pdo->prepare("SELECT label FROM client_location_options WHERE type=? ORDER BY label ASC");
    $st->execute([$type]);
    return array_map(fn($row) => (string)$row['label'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

/**
 * Return full rows for UI listing.
 * @return array<int,array{id:int,type:string,label:string,details:?string,created_at:?string}>
 */
function location_option_list_full(PDO $pdo, string $type): array {
    if (!location_option_is_valid($type)) return [];
    location_option_ensure_table($pdo);
    $st = $pdo->prepare("SELECT id,type,label,details,parent_area,parent_sub_zone,created_at FROM client_location_options WHERE type=? ORDER BY label ASC, id ASC");
    $st->execute([$type]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function location_option_find(PDO $pdo, int $id): ?array {
    if ($id <= 0) return null;
    location_option_ensure_table($pdo);
    $st = $pdo->prepare("SELECT id,type,label,details,parent_area,parent_sub_zone,created_at FROM client_location_options WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function location_option_sanitize_parent(?string $value): ?string {
    if ($value === null) return null;
    $value = trim((string)$value);
    if ($value === '') return null;
    return mb_substr($value, 0, 120);
}

/**
 * Insert (or ensure) an option record.
 * Returns ['ok'=>bool,'value'=>string,'error'=>?string]
 */
function location_option_store(PDO $pdo, string $type, string $label, string $details='', ?string $parent_area=null, ?string $parent_sub_zone=null): array {
    $label = trim($label);
    if ($label === '' || !location_option_is_valid($type)) {
        return ['ok'=>false,'error'=>'Invalid type or empty label.'];
    }
    location_option_ensure_table($pdo);

    // Normalize spacing and cap length
    $label         = mb_substr($label, 0, 120);
    $details       = trim($details);
    $parent_area     = location_option_sanitize_parent($parent_area);
    $parent_sub_zone = location_option_sanitize_parent($parent_sub_zone);

    if ($type === 'area') {
        $parent_area = null;
        $parent_sub_zone = null;
    } elseif ($type === 'sub_zone') {
        $parent_sub_zone = null;
    }

    $sql = "INSERT INTO client_location_options (type,label,details,parent_area,parent_sub_zone) VALUES (:type,:label,:details,:parent_area,:parent_sub_zone)";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':type'=>$type,
        ':label'=>$label,
        ':details'=>$details !== '' ? $details : null,
        ':parent_area'=>$parent_area,
        ':parent_sub_zone'=>$parent_sub_zone
    ]);

    return [
        'ok'=>true,
        'value'=>$label,
        'id'=>(int)$pdo->lastInsertId(),
        'details'=>$details,
        'parent_area'=>$parent_area,
        'parent_sub_zone'=>$parent_sub_zone
    ];
}

/**
 * Update existing option by ID.
 */
function location_option_update(PDO $pdo, int $id, string $label, string $details='', ?string $parent_area=null, ?string $parent_sub_zone=null): array {
    $label = trim($label);
    if ($id <= 0 || $label === '') {
        return ['ok'=>false,'error'=>'Invalid ID or empty label.'];
    }
    location_option_ensure_table($pdo);
    $row = location_option_find($pdo, $id);
    if (!$row) {
        return ['ok'=>false,'error'=>'Option not found.'];
    }
    $label         = mb_substr($label, 0, 120);
    $details       = trim($details);
    $parent_area     = location_option_sanitize_parent($parent_area);
    $parent_sub_zone = location_option_sanitize_parent($parent_sub_zone);

    if ($row['type'] === 'area') {
        $parent_area = null;
        $parent_sub_zone = null;
    } elseif ($row['type'] === 'sub_zone') {
        $parent_sub_zone = null;
    }

    $st = $pdo->prepare("UPDATE client_location_options SET label=:label, details=:details, parent_area=:parent_area, parent_sub_zone=:parent_sub_zone WHERE id=:id");
    $st->execute([
        ':label'=>$label,
        ':details'=>$details !== '' ? $details : null,
        ':parent_area'=>$parent_area,
        ':parent_sub_zone'=>$parent_sub_zone,
        ':id'=>$id
    ]);
    return [
        'ok'=>true,
        'id'=>$id,
        'type'=>$row['type'],
        'value'=>$label,
        'details'=>$details,
        'parent_area'=>$parent_area,
        'parent_sub_zone'=>$parent_sub_zone
    ];
}

function location_option_delete(PDO $pdo, int $id): array {
    if ($id <= 0) {
        return ['ok'=>false,'error'=>'Invalid ID.'];
    }
    location_option_ensure_table($pdo);
    $row = location_option_find($pdo, $id);
    if (!$row) {
        return ['ok'=>false,'error'=>'Option not found.'];
    }
    $type = $row['type'];
    $label = $row['label'];
    if ($type === 'area') {
        $stSub = $pdo->prepare("SELECT COUNT(*) FROM client_location_options WHERE type='sub_zone' AND parent_area=?");
        $stSub->execute([$label]);
        if ((int)$stSub->fetchColumn() > 0) {
            return ['ok'=>false,'error'=>'Delete sub zones under this area first.'];
        }
        $stBox = $pdo->prepare("SELECT COUNT(*) FROM client_location_options WHERE type='box' AND parent_area=?");
        $stBox->execute([$label]);
        if ((int)$stBox->fetchColumn() > 0) {
            return ['ok'=>false,'error'=>'Delete boxes under this area first.'];
        }
    } elseif ($type === 'sub_zone') {
        $stBox = $pdo->prepare("SELECT COUNT(*) FROM client_location_options WHERE type='box' AND parent_sub_zone=?");
        $stBox->execute([$label]);
        if ((int)$stBox->fetchColumn() > 0) {
            return ['ok'=>false,'error'=>'Delete boxes under this sub zone first.'];
        }
    }
    $st = $pdo->prepare("DELETE FROM client_location_options WHERE id=?");
    $st->execute([$id]);
    return ['ok'=>true,'type'=>$type];
}
