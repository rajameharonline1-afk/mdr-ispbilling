<?php
// app/olt_schema.php
// (বাংলা) olts টেবিলে Telnet/TCP স্ক্যানের জন্য দরকারি অতিরিক্ত কলামগুলো
// (mgmt_proto, telnet_port, prompt_regex) না থাকলে অটো-এড করে।

require_once __DIR__ . '/db.php';

if (!function_exists('ensure_olt_telnet_columns')) {
    function ensure_olt_telnet_columns(?PDO $pdo = null): void {
        static $done = false;
        if ($done) return;
        if ($pdo === null) {
            $pdo = db();
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM olts")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return;
        }

        $fragments = [];
        if (!in_array('mgmt_proto', $cols, true)) {
            $fragments[] = "ADD COLUMN `mgmt_proto` VARCHAR(16) DEFAULT 'telnet' AFTER `vendor`";
        }
        if (!in_array('telnet_port', $cols, true)) {
            $fragments[] = "ADD COLUMN `telnet_port` INT(11) DEFAULT NULL AFTER `ssh_port`";
        }
        if (!in_array('prompt_regex', $cols, true)) {
            // পুরনো ডাম্পে prompt_regex আছে, তাই মিসিং হলে তবেই যোগ করব
            $fragments[] = "ADD COLUMN `prompt_regex` VARCHAR(200) DEFAULT NULL AFTER `enable_password`";
        }
        // কিছু পুরনো ডাটাবেজে snmp_community নাও থাকতে পারে – SNMP কনফিগারেশনের জন্য এটা নিশ্চিত করি
        if (!in_array('snmp_community', $cols, true)) {
            $fragments[] = "ADD COLUMN `snmp_community` VARCHAR(64) DEFAULT 'public' AFTER `host`";
        }

        if ($fragments) {
            try {
                $sql = "ALTER TABLE olts\n  " . implode(",\n  ", $fragments) . ";";
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // ALTER ব্যর্থ হলেও ফাংশন সাইলেন্ট থাকবে; পরের কোড নিজে fallback করবে
            }
        }
        $done = true;
    }
}
