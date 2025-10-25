<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../models/product.php';
    require_once __DIR__ . '/authController.php';
    
    // Verify admin authentication
    $auth = verifyToken();
    if (!$auth || $auth['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $database = new Database();
    $db = $database->connect();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all products
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'image_url' => $row['image_url'],
                'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?><?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../models/product.php';
    require_once __DIR__ . '/authController.php';
    
    // Verify admin authentication
    $auth = verifyToken();
    if (!$auth || $auth['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $database = new Database();
    $db = $database->connect();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all products
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'image_url' => $row['image_url'],
                'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?><?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../models/product.php';
    require_once __DIR__ . '/authController.php';
    
    // Verify admin authentication
    $auth = verifyToken();
    if (!$auth || $auth['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $database = new Database();
    $db = $database->connect();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all products
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'image_url' => $row['image_url'],
                'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>