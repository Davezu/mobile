<?php
/**
 * Quick script to check if orders table is set up correctly
 * Run this from browser: http://localhost/e-commerce/app/database/check_orders_setup.php
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Orders Table Setup Check</h2>";

try {
    $conn = getConnection();
    
    // Check if orders table exists
    echo "<h3>1. Checking orders table structure:</h3>";
    $query = "SHOW COLUMNS FROM orders";
    $stmt = $conn->query($query);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if checkout_session_id exists
    echo "<h3>2. Checking for checkout_session_id column:</h3>";
    $hasCheckoutSession = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'checkout_session_id') {
            $hasCheckoutSession = true;
            break;
        }
    }
    
    if ($hasCheckoutSession) {
        echo "<p style='color: green;'>✓ checkout_session_id column exists</p>";
    } else {
        echo "<p style='color: red;'>✗ checkout_session_id column NOT found</p>";
        echo "<p>Run this SQL to add it:</p>";
        echo "<pre>ALTER TABLE `orders` 
ADD COLUMN `checkout_session_id` VARCHAR(255) NULL AFTER `payment_method`,
ADD INDEX `idx_checkout_session` (`checkout_session_id`);</pre>";
    }
    
    // Check pending_payments table
    echo "<h3>3. Checking pending_payments table:</h3>";
    try {
        $query = "SHOW COLUMNS FROM pending_payments";
        $stmt = $conn->query($query);
        $pendingColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✓ pending_payments table exists</p>";
        echo "<p>Columns: " . implode(', ', array_column($pendingColumns, 'Field')) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ pending_payments table NOT found</p>";
        echo "<p>Run the SQL from: app/database/create_pending_payments.sql</p>";
    }
    
    // Check recent orders
    echo "<h3>4. Recent orders (last 5):</h3>";
    $query = "SELECT id, user_id, total_amount, status, created_at, checkout_session_id 
              FROM orders 
              ORDER BY created_at DESC 
              LIMIT 5";
    $stmt = $conn->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "<p>No orders found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Total</th><th>Status</th><th>Created</th><th>Checkout Session</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($order['id']) . "</td>";
            echo "<td>" . htmlspecialchars($order['user_id']) . "</td>";
            echo "<td>₱" . htmlspecialchars($order['total_amount']) . "</td>";
            echo "<td>" . htmlspecialchars($order['status']) . "</td>";
            echo "<td>" . htmlspecialchars($order['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($order['checkout_session_id'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check pending payments
    echo "<h3>5. Pending payments (last 5):</h3>";
    try {
        $query = "SELECT checkout_session_id, user_id, amount, created_at, items 
                  FROM pending_payments 
                  ORDER BY created_at DESC 
                  LIMIT 5";
        $stmt = $conn->query($query);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pending)) {
            echo "<p>No pending payments found.</p>";
            echo "<p style='color: orange;'><strong>This might be why orders aren't being created!</strong></p>";
            echo "<p>When you make a payment, a record should be created here first, then the order is created when payment succeeds.</p>";
        } else {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Checkout Session ID</th><th>User ID</th><th>Amount</th><th>Items Count</th><th>Created</th></tr>";
            foreach ($pending as $p) {
                $items = json_decode($p['items'], true);
                $itemCount = is_array($items) ? count($items) : 0;
                echo "<tr>";
                echo "<td>" . htmlspecialchars($p['checkout_session_id']) . "</td>";
                echo "<td>" . htmlspecialchars($p['user_id']) . "</td>";
                echo "<td>₱" . htmlspecialchars($p['amount']) . "</td>";
                echo "<td>" . $itemCount . "</td>";
                echo "<td>" . htmlspecialchars($p['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p style='color: blue;'><strong>If you see pending payments here but no orders, the payment-success callback might not be finding them.</strong></p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Could not check pending_payments: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Make sure the pending_payments table exists. Run: app/database/create_pending_payments.sql</p>";
    }
    
    echo "<hr>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    if (!$hasCheckoutSession) {
        echo "<li>Run the migration SQL to add checkout_session_id column</li>";
    }
    echo "<li>Check PHP error logs: logs/php_errors.log</li>";
    echo "<li>Try making a payment and check if order is created</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

