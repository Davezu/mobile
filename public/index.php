<?php

define("APP_ACCESS", true);

// Enable CORS for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration and database
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core/Response.php';

// Get request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and get path
$path = parse_url($requestUri, PHP_URL_PATH);

// Debug logging (only if APP_DEBUG is true)
if (APP_DEBUG) {
    error_log("Original URI: " . $requestUri);
    error_log("Parsed path: " . $path);
}

// Remove base directory from path (handle different scenarios)
$basePaths = [
    '/e-commerce/public/index.php',
    '/e-commerce/public',
    '/public/index.php',
    '/public',
    '/index.php'
];

foreach ($basePaths as $baseDir) {
    if (strpos($path, $baseDir) === 0) {
        $path = substr($path, strlen($baseDir));
        if (APP_DEBUG) {
            error_log("Matched base: $baseDir, New path: $path");
        }
        break;
    }
}

// Ensure path starts with /
if (empty($path) || $path[0] !== '/') {
    $path = '/' . $path;
}

// Also check PATH_INFO for servers that support it
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
    if (APP_DEBUG) {
        error_log("Using PATH_INFO: " . $path);
    }
}

if (APP_DEBUG) {
    error_log("Final path for routing: " . $path);
}

try {
    // Auth Routes
    if (preg_match('#^/api/auth/register$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/AuthController.php';
        $controller = new AuthController();
        $controller->register();
    } 
    elseif (preg_match('#^/api/auth/login$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/AuthController.php';
        $controller = new AuthController();
        $controller->login();
    }
    elseif (preg_match('#^/api/auth/logout$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/AuthController.php';
        $controller = new AuthController();
        $controller->logout();
    }
    elseif (preg_match('#^/api/auth/me$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/AuthController.php';
        $controller = new AuthController();
        $controller->me();
    }
    
    // Product Routes
    elseif (preg_match('#^/api/products$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->index();
    }
    elseif (preg_match('#^/api/products/search$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->search();
    }
    elseif (preg_match('#^/api/products/category/(.+)$#', $path, $matches) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->byCategory($matches[1]);
    }
    elseif (preg_match('#^/api/products/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->show($matches[1]);
    }
    
    // Admin Product Routes
    elseif (preg_match('#^/api/admin/products$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->create();
    }
    elseif (preg_match('#^/api/admin/products/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->update($matches[1]);
    }
    elseif (preg_match('#^/api/admin/products/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        require_once __DIR__ . '/../app/controller/productsController.php';
        $controller = new ProductsController();
        $controller->delete($matches[1]);
    }
    
    // Payment Routes
    elseif (preg_match('#^/api/payment/test$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/paymentController.php';
        $controller = new PaymentController();
        $controller->test();
    }
    elseif (preg_match('#^/api/payment/auth-test$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/paymentController.php';
        $controller = new PaymentController();
        $controller->authTest();
    }
    elseif (preg_match('#^/api/payment/create-intent$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/paymentController.php';
        $controller = new PaymentController();
        $controller->createPaymentIntent();
    }
    elseif (preg_match('#^/api/payment/confirm$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/paymentController.php';
        $controller = new PaymentController();
        $controller->confirmPayment();
    }
    elseif (preg_match('#^/api/payment/create-test-payment$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/paymentController.php';
        $controller = new PaymentController();
        $controller->createSandboxPayment();
    }
    
    // Order Routes
    elseif (preg_match('#^/api/orders$#', $path) && $requestMethod === 'POST') {
        require_once __DIR__ . '/../app/controller/orderController.php';
        $controller = new OrderController();
        $controller->create();
    }
    elseif (preg_match('#^/api/orders$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/orderController.php';
        $controller = new OrderController();
        $controller->index();
    }
    elseif (preg_match('#^/api/orders/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/orderController.php';
        $controller = new OrderController();
        $controller->show($matches[1]);
    }
    
    // Admin Order Routes
    elseif (preg_match('#^/api/admin/orders$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/orderController.php';
        $controller = new OrderController();
        $controller->adminIndex();
    }
    elseif (preg_match('#^/api/admin/orders/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../app/controller/orderController.php';
        $controller = new OrderController();
        $controller->updateStatus($matches[1]);
    }
    
    // Admin User Routes
    elseif (preg_match('#^/api/users$#', $path) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/userController.php';
        $controller = new UserController();
        $controller->getAllUsers();
    }
    elseif (preg_match('#^/api/users/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        require_once __DIR__ . '/../app/controller/userController.php';
        $controller = new UserController();
        $controller->getUserById($matches[1]);
    }
    elseif (preg_match('#^/api/users/(\d+)/status$#', $path, $matches) && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../app/controller/userController.php';
        $controller = new UserController();
        $controller->updateUserStatus($matches[1]);
    }
    elseif (preg_match('#^/api/users/(\d+)/role$#', $path, $matches) && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../app/controller/userController.php';
        $controller = new UserController();
        $controller->updateUserRole($matches[1]);
    }
    elseif (preg_match('#^/api/users/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        require_once __DIR__ . '/../app/controller/userController.php';
        $controller = new UserController();
        $controller->deleteUser($matches[1]);
    }
    
    // API Info
    elseif ($path === '/' || $path === '') {
        Response::success('E-Commerce API v1.0', [
            'endpoints' => [
                'Authentication' => [
                    'POST /api/auth/register' => 'Register a new user',
                    'POST /api/auth/login' => 'Login user',
                    'POST /api/auth/logout' => 'Logout user',
                    'GET /api/auth/me' => 'Get current user',
                ],
                'Products' => [
                    'GET /api/products' => 'Get all products',
                    'GET /api/products/{id}' => 'Get product by ID',
                    'GET /api/products/category/{category}' => 'Get products by category',
                    'GET /api/products/search?q={query}' => 'Search products',
                ],
                'Orders' => [
                    'POST /api/orders' => 'Create new order',
                    'GET /api/orders' => 'Get user orders',
                    'GET /api/orders/{id}' => 'Get order by ID',
                ],
                'Admin' => [
                    'POST /api/admin/products' => 'Create product',
                    'PUT /api/admin/products/{id}' => 'Update product',
                    'DELETE /api/admin/products/{id}' => 'Delete product',
                    'GET /api/admin/orders' => 'Get all orders',
                    'PUT /api/admin/orders/{id}' => 'Update order status',
                    'GET /api/users' => 'Get all users (admin)',
                    'GET /api/users/{id}' => 'Get user by ID (admin)',
                    'PUT /api/users/{id}/status' => 'Update user status (admin)',
                    'PUT /api/users/{id}/role' => 'Update user role (admin)',
                    'DELETE /api/users/{id}' => 'Delete user (admin)',
                ],
            ]
        ]);
    }
    else {
        Response::notFound('Endpoint not found');
    }
} catch (Exception $e) {
    if (APP_DEBUG) {
        Response::serverError($e->getMessage());
    } else {
        Response::serverError('An error occurred. Please try again later.');
    }
}

?>

