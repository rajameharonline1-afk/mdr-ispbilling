<?php
// ===============================
// ডাটাবেজ কানেকশন ফাইল (PDO)
// ===============================
require_once __DIR__ . '/config.php';

function db(bool $refresh=false) {
    static $pdo;
    if ($refresh) {
        $pdo = null;
    }
    if ($pdo instanceof PDO) {
        try {
            $pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $code = (string)$e->getCode();
            $msg  = strtolower($e->getMessage());
            $shouldReconnect = in_array($code, ['2006', '2013', 'HY000'], true)
                || str_contains($msg, 'server has gone away')
                || str_contains($msg, 'lost connection');
            if ($shouldReconnect) {
                $pdo = null;
            } else {
                throw $e;
            }
        }
    }
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
