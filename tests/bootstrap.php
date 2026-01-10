<?php
// PHPUnit bootstrap for ISP Billing
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Dhaka');

// Load helpers used by tests
require_once __DIR__ . '/../app/billing_helpers.php';
require_once __DIR__ . '/../app/helpers.php';
