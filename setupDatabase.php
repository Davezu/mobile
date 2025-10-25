<?php

define("APP_ACCESS", true);

require_once __DIR__ . '/config/config.php';

echo "========================================\n";
echo "DATABASE SETUP SCRIPT\n";
echo "========================================\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "✓ Connected to database successfully!\n\n";
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/app/database/schema.sql';
    
    if (!file_exists($sqlFile)) {
        die("Error: schema.sql file not found at: $sqlFile\n");
    }
    
    echo "Reading SQL schema file...\n";
    $sql = file_get_contents($sqlFile);
    
    echo "Executing SQL statements...\n\n";
    
    // Remove comments and split SQL into statements
    $sql = preg_replace('/^--.*$/m', '', $sql); // Remove single-line comments
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && trim($stmt) !== '';
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $conn->exec($statement);
            
            // Extract table name or operation for display
            if (preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $matches)) {
                echo "✓ Created table: " . $matches[1] . "\n";
            } elseif (preg_match('/DROP TABLE\s+IF EXISTS\s+(\w+)/i', $statement, $matches)) {
                echo "✓ Dropped table (if exists): " . $matches[1] . "\n";
            } elseif (preg_match('/INSERT INTO\s+(\w+)/i', $statement, $matches)) {
                $rowCount = $conn->query("SELECT ROW_COUNT()")->fetchColumn();
                echo "✓ Inserted data into: " . $matches[1] . "\n";
            }
            
            $successCount++;
        } catch (PDOException $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
            $errorCount++;
        }
    }
    
    echo "\n========================================\n";
    echo "SETUP COMPLETED!\n";
    echo "========================================\n";
    echo "Successful operations: $successCount\n";
    echo "Failed operations: $errorCount\n\n";
    
    // Show summary
    echo "Database Summary:\n";
    echo "----------------\n";
    
    $tables = $db->fetchAll("SHOW TABLES");
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        $count = $db->fetch("SELECT COUNT(*) as count FROM `$tableName`");
        echo "- $tableName: {$count['count']} rows\n";
    }
    
    echo "\n========================================\n";
    echo "Default Admin Credentials:\n";
    echo "Email: admin@ecommerce.com\n";
    echo "Password: admin123\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>

