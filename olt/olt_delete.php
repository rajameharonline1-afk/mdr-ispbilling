<?php
// /olt/olt_delete.php
// (বাংলা) OLT ডিলিট করার জন্য নিরাপত্তা পরীক্ষাসহ চূড়ান্ত সংস্করণ
session_start();
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['toast_message'] = 'Invalid OLT ID provided.';
    $_SESSION['toast_type'] = 'danger';
    header('Location: index.php');
    exit;
}

try {
    $pdo = db();
    
    // **গুরুত্বপূর্ণ নিরাপত্তা পরীক্ষা: এই OLT-এর অধীনে কোনো ক্লায়েন্ট আছে কিনা?**
    // আমরা ধরে নিচ্ছি 'clients' টেবিলে 'olt_id' নামে একটি কলাম আছে।
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE olt_id = ?");
    $check_stmt->execute([$id]);
    $client_count = (int)$check_stmt->fetchColumn();

    if ($client_count > 0) {
        // যদি ক্লায়েন্ট সংযুক্ত থাকে, তাহলে ডিলিট ব্লক করুন এবং বার্তা দিন
        $_SESSION['toast_message'] = "Cannot delete this OLT because <strong>{$client_count} client(s)</strong> are currently assigned to it. Please reassign them first.";
        $_SESSION['toast_type'] = 'danger';
        header('Location: index.php');
        exit;
    }

    // যদি কোনো ক্লায়েন্ট সংযুক্ত না থাকে, তাহলে OLT ডিলিট করুন
    $delete_stmt = $pdo->prepare("DELETE FROM olts WHERE id = ?");
    $success = $delete_stmt->execute([$id]);

    if ($success) {
        $_SESSION['toast_message'] = 'OLT has been deleted successfully!';
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast_message'] = 'Failed to delete the OLT.';
        $_SESSION['toast_type'] = 'danger';
    }

} catch (PDOException $e) {
    $_SESSION['toast_message'] = 'Database error: ' . $e->getMessage();
    $_SESSION['toast_type'] = 'danger';
}

header('Location: index.php');
exit;
?>
