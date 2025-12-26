<?php
// ১. ডাটাবেস কানেকশন
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ২. JOIN Query চালানো
    // এখানে ধরে নিচ্ছি আপনার কাস্টমার টেবিলের নাম 'customers'
    // এবং সেখানে 'interface_id' নামে একটি কলাম আছে যা OLT-এর আইডির সাথে মিলে
    $sql = "SELECT 
                c.name, 
                c.phone, 
                c.address,
                c.interface_id, 
                s.rx_power, 
                s.signal_status, 
                s.last_updated
            FROM customers c
            LEFT JOIN onu_signals s ON c.interface_id = s.interface_id
            ORDER BY c.name ASC";
            
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Network Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .signal-bad { background-color: #ffcccc !important; color: #a00000; font-weight: bold; }
        .signal-good { color: #008000; font-weight: bold; }
        .offline { color: #999; font-style: italic; }
    </style>
</head>
<body class="bg-light p-4">

<div class="container bg-white p-4 shadow rounded">
    <h2 class="mb-4 text-primary">📊 Live ONU Signal Monitor</h2>
    
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Interface ID</th>
                <th>RX Power (dBm)</th>
                <th>Status</th>
                <th>Last Checked</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php 
                    // সিগন্যাল কালার লজিক
                    $row_class = '';
                    $power_display = '<span class="offline">No Signal</span>';
                    $status_display = '<span class="badge bg-secondary">Offline</span>';

                    if ($user['rx_power'] != null) {
                        // যদি সিগন্যাল -25 এর চেয়ে কম হয় (যেমন -27), তবে লাল দেখাবে
                        if ($user['rx_power'] < -25) {
                            $row_class = 'signal-bad';
                            $status_display = '<span class="badge bg-danger">Low Signal</span>';
                        } else {
                            $status_display = '<span class="badge bg-success">Good</span>';
                        }
                        $power_display = $user['rx_power'] . ' dBm';
                    }
                ?>
                
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><?php echo htmlspecialchars($user['interface_id']); ?></td>
                    <td><?php echo $power_display; ?></td>
                    <td><?php echo $status_display; ?></td>
                    <td>
                        <?php 
                        // সময় দেখাবে
                        echo $user['last_updated'] ? date('h:i:s A', strtotime($user['last_updated'])) : '-'; 
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>