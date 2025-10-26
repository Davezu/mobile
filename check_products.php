<?php
require_once 'config/config.php';

try {
    $db = getDB();
    $conn = $db->getConnection();
    
    $stmt = $conn->query('SELECT id, name, image_url FROM products LIMIT 5');
    
    echo "Current products in database:\n";
    echo "================================\n";
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Image URL: " . ($row['image_url'] ?: 'NULL') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
