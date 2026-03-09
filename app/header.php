<?php
require_once __DIR__ . '/require_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="/assets/css/global.css">
</head>
<body>

<div class="header-bar">
    <div class="header-left">
        📡 Admin Panel
    </div>
    <div class="header-right">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        (<?php echo htmlspecialchars($_SESSION['role']); ?>)
        <a href="/public/logout.php">Logout</a>
    </div>
</div>

<div class="content">
