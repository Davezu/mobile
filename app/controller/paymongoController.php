<?php

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../models/order.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';

/**
 * PayMongo Payment Controller
 * Handles GCash, Credit/Debit Card, Bank Transfer, QR Code, and Digital Wallet payments
 */
class PayMongoController
{
    private $orderModel;
    private $userModel;
    private $secretKey = 'sk_test_Srj3EUh6CF4rbSMJjzJviSRw';
    private $publicKey = 'pk_test_mJ8jdb5AdJmJp2h6GtAeMKWQ';
    private $apiUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->userModel = new User();
    }

    /**
     * Get current user from session/token
     */
    private function getCurrentUser()
    {
        // Try to get user from token
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!empty($authHeader)) {
            $token = str_replace('Bearer ', '', $authHeader);
            $token = trim($token);
            
            if (!empty($token)) {
                require_once __DIR__ . '/../../config/config.php';
                $db = getDB();
                $conn = $db->getConnection();
                
                $query = "SELECT id, username, email, role FROM users WHERE remember_token = ? AND is_active = 1";
                $stmt = $conn->prepare($query);
                $stmt->execute([$token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Store user ID in session for later use
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    return $user;
                }
            }
        }
        
        // Fallback to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            return $this->userModel->getById($_SESSION['user_id']);
        }
        
        return null;
    }

    /**
     * Make API request to PayMongo
     */
    private function makeRequest($endpoint, $method = 'POST', $data = null)
    {
        $url = $this->apiUrl . $endpoint;
        $auth = base64_encode($this->secretKey . ':');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new Exception($error['errors'][0]['detail'] ?? 'Payment gateway error');
        }

        return json_decode($response, true);
    }

    /**
     * Create Payment Intent for Credit/Debit Card
     * POST /api/paymongo/create-payment-intent
     */
    public function createPaymentIntent()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Please login to continue');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        $validator = new Validator($input);
        $validator
            ->required('amount')
            ->required('items')
            ->required('shipping_address');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        try {
            $amountInCentavos = (int)($input['amount'] * 100);

            $paymentIntentData = [
                'data' => [
                    'attributes' => [
                        'amount' => $amountInCentavos,
                        'payment_method_allowed' => ['card'],
                        'currency' => 'PHP',
                        'description' => 'Order Payment',
                        'statement_descriptor' => 'ShopHub Order'
                    ]
                ]
            ];

            $result = $this->makeRequest('/payment_intents', 'POST', $paymentIntentData);

            Response::success('Payment intent created', [
                'client_key' => $result['data']['attributes']['client_key'],
                'payment_intent_id' => $result['data']['id'],
                'status' => $result['data']['attributes']['status']
            ]);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Create Payment Method (for Card payments)
     * POST /api/paymongo/create-payment-method
     */
    public function createPaymentMethod()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Please login to continue');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        try {
            $paymentMethodData = [
                'data' => [
                    'attributes' => [
                        'type' => 'card',
                        'details' => [
                            'card_number' => $input['card_number'],
                            'exp_month' => $input['exp_month'],
                            'exp_year' => $input['exp_year'],
                            'cvc' => $input['cvc']
                        ],
                        'billing' => [
                            'name' => $input['billing_name'] ?? $user['username'],
                            'email' => $user['email'],
                            'phone' => $input['phone'] ?? ''
                        ]
                    ]
                ]
            ];

            $result = $this->makeRequest('/payment_methods', 'POST', $paymentMethodData);

            Response::success('Payment method created', [
                'payment_method_id' => $result['data']['id']
            ]);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Create Checkout Session for E-Wallets (GCash, GrabPay, etc)
     * POST /api/paymongo/create-source
     */
    public function createSource()
    {
        try {
            error_log("createSource called");
            
            $user = $this->getCurrentUser();
            error_log("User: " . json_encode($user));
            
            if (!$user) {
                Response::unauthorized('Please login to continue');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input: " . json_encode($input));
            
            if (!$input) {
                Response::error('Invalid JSON data');
                return;
            }
        } catch (Throwable $e) {
            error_log("Error in createSource: " . $e->getMessage());
            Response::serverError($e->getMessage());
            return;
        }

        $validator = new Validator($input);
        $validator
            ->required('amount')
            ->required('type') // gcash, grab_pay, paymaya, etc.
            ->required('items')
            ->required('shipping_address');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        try {
            $amountInCentavos = (int)($input['amount'] * 100);
            $type = $input['type']; // gcash, grab_pay, paymaya

            // Build line items from cart
            $lineItems = [];
            foreach ($input['items'] as $item) {
                $lineItems[] = [
                    'currency' => 'PHP',
                    'amount' => (int)($item['price'] * 100),
                    'name' => 'Product #' . $item['product_id'],
                    'quantity' => $item['quantity']
                ];
            }

            // Create simple success URL without session_id parameter
            // We'll use the most recent pending payment from the database
            $successUrl = 'http://localhost/e-commerce/public/api/paymongo/payment-success';
            
            $checkoutData = [
                'data' => [
                    'attributes' => [
                        'cancel_url' => 'http://localhost/e-commerce/public/api/paymongo/payment-failed',
                        'success_url' => $successUrl,
                        'line_items' => $lineItems,
                        'payment_method_types' => [$type],
                        'description' => 'Order Payment for ' . $user['username'],
                        'metadata' => [
                            'user_id' => (string)$user['id'],
                            'shipping_address' => $input['shipping_address']
                        ]
                    ]
                ]
            ];

            $result = $this->makeRequest('/checkout_sessions', 'POST', $checkoutData);
            
            // Get the actual checkout session ID
            $checkoutSessionId = $result['data']['id'];

            // Store checkout session info in database for later verification
            require_once __DIR__ . '/../../config/config.php';
            $db = getDB();
            $conn = $db->getConnection();
            
            // Store in a temporary payments table or use session with proper config
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Store checkout data with session ID
            $checkoutData = [
                'checkout_session_id' => $result['data']['id'],
                'user_id' => $user['id'],
                'items' => json_encode($input['items']),
                'shipping_address' => $input['shipping_address'],
                'amount' => $input['amount'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Store in database temporarily
            $query = "INSERT INTO pending_payments (checkout_session_id, user_id, items, shipping_address, amount, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), items = VALUES(items)";
            
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $checkoutData['checkout_session_id'],
                    $checkoutData['user_id'],
                    $checkoutData['items'],
                    $checkoutData['shipping_address'],
                    $checkoutData['amount'],
                    $checkoutData['created_at']
                ]);
            } catch (Exception $e) {
                // Fallback to session only
                $_SESSION['paymongo_checkout'] = $checkoutData;
            }

            // Replace placeholder with actual session ID in the checkout URL
            $actualCheckoutUrl = str_replace(
                '{CHECKOUT_SESSION_ID}',
                $checkoutSessionId,
                $result['data']['attributes']['checkout_url']
            );

            Response::success('Checkout session created', [
                'checkout_session_id' => $checkoutSessionId,
                'checkout_url' => $actualCheckoutUrl,
                'status' => $result['data']['attributes']['status']
            ]);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Attach Payment Method to Payment Intent
     * POST /api/paymongo/attach-payment
     */
    public function attachPayment()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Please login to continue');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        try {
            $attachData = [
                'data' => [
                    'attributes' => [
                        'payment_method' => $input['payment_method_id']
                    ]
                ]
            ];

            $result = $this->makeRequest(
                '/payment_intents/' . $input['payment_intent_id'] . '/attach',
                'POST',
                $attachData
            );

            // If successful, create order
            if ($result['data']['attributes']['status'] === 'succeeded') {
                $order = $this->orderModel->create(
                    $user['id'],
                    $input['items'],
                    $input['shipping_address']
                );

                Response::success('Payment successful', [
                    'order' => $order,
                    'payment_intent_id' => $result['data']['id']
                ], 201);
            } else {
                Response::success('Payment processing', [
                    'status' => $result['data']['attributes']['status'],
                    'payment_intent_id' => $result['data']['id']
                ]);
            }
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Payment Success Callback
     * GET /api/paymongo/payment-success
     */
    public function paymentSuccess()
    {
        try {
            // Log everything to debug
            error_log("=== Payment Success Called ===");
            error_log("GET params: " . json_encode($_GET));
            error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set'));
            error_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'not set'));
            
            // Check if this is an auto-test payment
            $isAutoTest = isset($_GET['auto_test']) && $_GET['auto_test'] == '1';
            
            // Get checkout session ID from URL parameter
            $checkoutSessionId = $_GET['session_id'] ?? null;
            
            error_log("Session ID from URL: " . ($checkoutSessionId ?? 'null'));
            error_log("Is auto test: " . ($isAutoTest ? 'yes' : 'no'));
            
            if (!$checkoutSessionId && !$isAutoTest) {
                // Try to get from session as fallback
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                if (isset($_SESSION['paymongo_checkout'])) {
                    $checkoutData = $_SESSION['paymongo_checkout'];
                    $checkoutSessionId = $checkoutData['checkout_session_id'];
                    error_log("Session ID from PHP session: " . $checkoutSessionId);
                }
            }
            
            // Retrieve checkout data from database
            require_once __DIR__ . '/../../config/config.php';
            $db = getDB();
            $conn = $db->getConnection();
            
            if ($checkoutSessionId) {
                // Try to find by session ID first
                $query = "SELECT * FROM pending_payments WHERE checkout_session_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$checkoutSessionId]);
                $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Payment data from session ID: " . ($paymentData ? 'found' : 'not found'));
            } else {
                $paymentData = false;
            }
            
            // If not found by session ID or no session ID, try to get most recent pending payment
            if (!$paymentData) {
                error_log("Trying to get most recent pending payment...");
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                $userId = $_SESSION['user_id'] ?? null;
                error_log("User ID from session: " . ($userId ?? 'null'));
                
                if ($userId) {
                    $query = "SELECT * FROM pending_payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$userId]);
                    $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    error_log("Payment data from user ID: " . ($paymentData ? 'found' : 'not found'));
                }
            }
            
            if (!$paymentData) {
                $this->showErrorPage('Payment data not found');
                return;
            }
            
            // Decode items
            $items = json_decode($paymentData['items'], true);
            
            // Create order
            $order = $this->orderModel->create(
                $paymentData['user_id'],
                $items,
                $paymentData['shipping_address']
            );

            // Delete pending payment record
            $deleteQuery = "DELETE FROM pending_payments WHERE checkout_session_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->execute([$checkoutSessionId]);
            
            // Clear session if exists
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['paymongo_checkout'])) {
                unset($_SESSION['paymongo_checkout']);
            }

            // Show success page that auto-closes and notifies Flutter
            $this->showSuccessPage($order);
        } catch (Exception $e) {
            $this->showErrorPage($e->getMessage());
        }
    }

    /**
     * Payment Failed Callback
     * GET /api/paymongo/payment-failed
     */
    public function paymentFailed()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear session
        if (isset($_SESSION['paymongo_checkout'])) {
            unset($_SESSION['paymongo_checkout']);
        }

        $this->showErrorPage('Payment was cancelled or failed');
    }

    /**
     * Test endpoint
     * GET /api/paymongo/test
     */
    public function test()
    {
        Response::success('PayMongo controller is working!', [
            'api_url' => $this->apiUrl,
            'public_key' => substr($this->publicKey, 0, 15) . '...',
        ]);
    }

    /**
     * Show success page that auto-closes
     */
    private function showSuccessPage($order)
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #00B14F 0%, #00953B 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #00B14F;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease-out 0.2s both;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        .checkmark {
            width: 40px;
            height: 40px;
            border: 4px solid white;
            border-top: none;
            border-right: none;
            transform: rotate(-45deg);
            margin-top: -5px;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .order-id {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: bold;
            color: #00B14F;
        }
        .close-msg {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <div class="checkmark"></div>
        </div>
        <h1>Payment Successful!</h1>
        <p>Your order has been placed successfully.</p>
        <div class="order-id">
            Order #' . $order['id'] . '
        </div>
        <p class="close-msg">This window will close automatically...</p>
    </div>
    <script>
        // Store order info
        const orderData = ' . json_encode($order) . ';
        
        // Try to communicate with Flutter (if opened via url_launcher)
        if (window.opener) {
            window.opener.postMessage({
                type: "payment_success",
                order: orderData
            }, "*");
        }
        
        // Auto-close immediately
        setTimeout(() => {
            window.close();
            
            // If window.close() does not work (some browsers block it)
            // redirect to Flutter cart/orders page
            if (!window.closed) {
                // Try to close again
                window.close();
                // If still cannot close, redirect
                setTimeout(() => {
                    if (!window.closed) {
                        window.location.href = "about:blank";
                    }
                }, 500);
            }
        }, 1500);
    </script>
</body>
</html>';
        exit;
    }

    /**
     * Show error page
     */
    private function showErrorPage($message)
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            color: white;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .close-msg {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">✕</div>
        <h1>Payment Failed</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <p class="close-msg">This window will close automatically...</p>
    </div>
    <script>
        // Notify opener window
        if (window.opener) {
            window.opener.postMessage({
                type: "payment_failed",
                message: "' . addslashes($message) . '"
            }, "*");
        }
        
        // Auto-close
        setTimeout(() => {
            window.close();
            
            if (!window.closed) {
                window.location.href = "http://localhost/e-commerce/flutter_app/index.html#/cart";
            }
        }, 2000);
    </script>
</body>
</html>';
        exit;
    }
}

