<?php
// Seed two cron_runs rows for auto_bkash_apply and bkash_rtn
declare(strict_types=1);
require_once __DIR__ . '/../app/db.php';

function insert_run($job_key, $title, $output = '', $error = null) {
    $db = db();
    $st = $db->prepare("INSERT INTO cron_runs (job_key, title, status, started_at, finished_at, duration_ms, output, error, triggered_by) VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)");
    $status = $error ? 'failed' : 'success';
    $duration = 0;
    $triggered_by = null;
    $st->execute([$job_key, $title, $status, $duration, $output, $error, $triggered_by]);
}

insert_run('auto_bkash_apply', 'অটো বিকাশ অ্যাপ্লাই (auto_bkash_apply) — প্রাথমিক এন্ট্রি', "ক্রন ফাইল: cron/auto_bkash_apply.php চালু থাকবে।");
insert_run('bkash_rtn', 'bKash RTN প্রসেসর (bkash_rtn_process) — প্রাথমিক এন্ট্রি', "ক্রন ফাইল: cron/bkash_rtn_process.php চালু থাকবে।");

echo "Inserted two cron_runs rows.\n";
