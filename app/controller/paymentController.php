<?php

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../models/order.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';

/**
 * Payment Controller
 * Handles Stripe payment processing
 */
class PaymentController
{
    private $orderModel;
    private $userModel;

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
        // Try to get user from token (Bearer token from Flutter)
        try {
            require_once __DIR__ . '/authController.php';
            $tokenUser = verifyToken();
            
            if ($tokenUser) {
                return $tokenUser;
            }
        } catch (Exception $e) {
            error_log('Token verification failed: ' . $e->getMessage());
        }
        
        // Fallback to session-based auth
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            Response::unauthorized('Please login to continue');
            return null;
        }

        $user = $this->userModel->getById($_SESSION['user_id']);
        
        if (!$user) {
            Response::unauthorized('User not found');
            return null;
        }

        return $user;
    }

    /**
     * Test endpoint to check if payment controller is working
     * GET /api/payment/test
     */
    public function test()
    {
        Response::success('Payment controller is working!', [
            'stripe_sdk_exists' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
            'php_version' => phpversion(),
        ]);
    }

    /**
     * Test authentication endpoint
     * GET /api/payment/auth-test
     */
    public function authTest()
    {
        $user = $this->getCurrentUser();
        if ($user) {
            Response::success('Authentication working!', [
                'user_id' => $user['id'],
                'username' => $user['username'],
            ]);
        } else {
            Response::unauthorized('Not authenticated');
        }
    }

    /**
     * Create payment intent for Stripe
     * POST /api/payment/create-intent
     */
    public function createPaymentIntent()
    {
        try {
            error_log("Payment intent creation started");
            
            // Get user from token or session
            $user = null;
        
        // Try to get user from token (Bearer token from Flutter)
        $headers = getallheaders();
        if (!$headers) {
            // Fallback for servers that don't support getallheaders()
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace('_', '-', substr($key, 5));
                    $headers[$headerName] = $value;
                }
            }
        }
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
            }
        }
        
        // Fallback to session
        if (!$user) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (isset($_SESSION['user_id'])) {
                $user = $this->userModel->getById($_SESSION['user_id']);
            }
        }
        
        if (!$user) {
            Response::unauthorized('Please login to continue');
            return;
        }

        // Load Stripe
        if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            Response::error('Stripe SDK not found. Path: ' . __DIR__ . '/../../vendor/autoload.php');
            return;
        }
        
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
        } catch (Exception $e) {
            Response::error('Failed to load Stripe SDK: ' . $e->getMessage());
            return;
        }
        
        // Get Stripe secret key from config
        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51SM3z3D1gObyTdYy1BTQ0MDw260bR8chv0PRAq78iRL87Vhm2zgz9tk5wDqKFi3J62AFaL81dpG8a0MTxqnZ6lYc00LZsR4D3p';
        
        \Stripe\Stripe::setApiKey($stripeSecret);

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('amount')
            ->numeric('amount')
            ->required('items')
            ->required('shipping_address');
        
        // Check amount is greater than 0
        if (isset($input['amount']) && $input['amount'] <= 0) {
            Response::error('Amount must be greater than 0');
            return;
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

         try {
             // Create payment intent for test mode
             $amountInCents = (int)($input['amount'] * 100); // Convert to cents
             
             $paymentIntent = \Stripe\PaymentIntent::create([
                 'amount' => $amountInCents,
                 'currency' => 'usd',
                 'automatic_payment_methods' => [
                     'enabled' => true,
                 ],
                 'metadata' => [
                     'user_id' => $user['id'],
                     'items' => json_encode($input['items']),
                     'shipping_address' => $input['shipping_address'],
                     'test_mode' => 'true',
                 ],
             ]);

            Response::success('Payment intent created', [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]);
        } catch (Exception $e) {
            error_log("Payment intent error: " . $e->getMessage());
            error_log("Payment intent trace: " . $e->getTraceAsString());
            Response::serverError($e->getMessage());
        }
        } catch (Exception $e) {
            error_log("Payment intent outer error: " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Confirm payment and create order
     * POST /api/payment/confirm
     */
    public function confirmPayment()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::unauthorized('Please login to continue');
            return;
        }

        // Load Stripe
        if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            Response::error('Stripe SDK not found. Path: ' . __DIR__ . '/../../vendor/autoload.php');
            return;
        }
        
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
        } catch (Exception $e) {
            Response::error('Failed to load Stripe SDK: ' . $e->getMessage());
            return;
        }
        
        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51SM3z3D1gObyTdYy1BTQ0MDw260bR8chv0PRAq78iRL87Vhm2zgz9tk5wDqKFi3J62AFaL81dpG8a0MTxqnZ6lYc00LZsR4D3p';
        \Stripe\Stripe::setApiKey($stripeSecret);

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('payment_intent_id')
            ->required('items')
            ->required('shipping_address');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

         try {
             // Verify payment intent with Stripe
             $paymentIntent = \Stripe\PaymentIntent::retrieve($input['payment_intent_id']);

             // Check if payment was successful
             if ($paymentIntent->status !== 'succeeded') {
                 if ($paymentIntent->status === 'canceled') {
                     Response::error('Payment was canceled');
                     return;
                 } elseif ($paymentIntent->status === 'payment_failed') {
                     Response::error('Payment failed');
                     return;
                 } else {
                     Response::error('Payment not completed. Status: ' . $paymentIntent->status);
                     return;
                 }
             }

            // Create order in database
            $order = $this->orderModel->create(
                $user['id'],
                $input['items'],
                $input['shipping_address']
            );

            // Update order with payment info
            // You may want to add a payment_intent_id column to orders table
            // $this->orderModel->updatePaymentInfo($order['id'], $paymentIntent->id);

            Response::success('Order placed successfully', $order, 201);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

     /**
      * Create sandbox payment using Stripe test mode
      * POST /api/payment/create-sandbox-payment
      */
     public function createSandboxPayment()
     {
         $user = $this->getCurrentUser();
         if (!$user) {
             Response::unauthorized('Please login to continue');
             return;
         }

         // Load Stripe
         if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
             Response::error('Stripe SDK not found');
             return;
         }
         
         try {
             require_once __DIR__ . '/../../vendor/autoload.php';
         } catch (Exception $e) {
             Response::error('Failed to load Stripe SDK: ' . $e->getMessage());
             return;
         }
         
         $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_51SM3z3D1gObyTdYy1BTQ0MDw260bR8chv0PRAq78iRL87Vhm2zgz9tk5wDqKFi3J62AFaL81dpG8a0MTxqnZ6lYc00LZsR4D3p';
         \Stripe\Stripe::setApiKey($stripeSecret);

         // Get JSON input
         $input = json_decode(file_get_contents('php://input'), true);

         if (!$input) {
             Response::error('Invalid JSON data');
             return;
         }

         // Validate input
         $validator = new Validator($input);
         $validator
             ->required('amount')
             ->numeric('amount')
             ->required('items')
             ->required('shipping_address');
         
         // Check amount is greater than 0
         if (isset($input['amount']) && $input['amount'] <= 0) {
             Response::error('Amount must be greater than 0');
             return;
         }

         if ($validator->fails()) {
             Response::validationError($validator->errors());
             return;
         }

         try {
             // Create payment intent for sandbox testing
             $amountInCents = (int)($input['amount'] * 100);
             
             // For sandbox mode, create PaymentIntent without immediate confirmation
             $paymentIntent = \Stripe\PaymentIntent::create([
                 'amount' => $amountInCents,
                 'currency' => 'usd',
                 'automatic_payment_methods' => [
                     'enabled' => true,
                     'allow_redirects' => 'never', // Prevent redirect-based payment methods
                 ],
                 'metadata' => [
                     'user_id' => $user['id'],
                     'items' => json_encode($input['items']),
                     'shipping_address' => $input['shipping_address'],
                     'sandbox_mode' => 'true',
                 ],
             ]);

             // For sandbox mode, simulate successful payment
             // In a real implementation, you would handle the payment confirmation differently
             
             // Create order in database (simulating successful payment)
             $order = $this->orderModel->create(
                 $user['id'],
                 $input['items'],
                 $input['shipping_address']
             );

             Response::success('Sandbox payment successful - Order created', [
                 'order' => $order,
                 'payment_intent_id' => $paymentIntent->id,
                 'sandbox_mode' => true,
                 'note' => 'This is a sandbox payment - no real money was charged'
             ], 201);
         } catch (Exception $e) {
             error_log("Sandbox payment error: " . $e->getMessage());
             Response::serverError($e->getMessage());
         }
     }
}
