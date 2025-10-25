<?php

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../models/order.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';

/**
 * Order Controller
 * Handles order operations
 */
class OrderController
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
        // Try to get user from token first
        require_once __DIR__ . '/authController.php';
        $user = verifyToken();
        
        if ($user) {
            // Return full user data
            return $this->userModel->getById($user['id']);
        }
        
        // Fallback to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            Response::unauthorized('Please login to continue');
        }

        return $this->userModel->getById($_SESSION['user_id']);
    }

    /**
     * Create new order
     * POST /api/orders
     */
    public function create()
    {
        $user = $this->getCurrentUser();

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
        }

        // Validate items array first (can't use Validator for arrays)
        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            Response::error('Items must be a non-empty array');
        }

        // Validate other input
        $validator = new Validator($input);
        $validator
            ->required('shipping_address')
            ->minLength('shipping_address', 10);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Create order
        try {
            $order = $this->orderModel->create(
                $user['id'],
                $input['items'],
                $input['shipping_address']
            );

            Response::success('Order placed successfully', $order, 201);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Get user's orders
     * GET /api/orders
     */
    public function index()
    {
        $user = $this->getCurrentUser();

        try {
            $orders = $this->orderModel->getUserOrders($user['id']);
            Response::success('Orders retrieved successfully', $orders);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Get order by ID
     * GET /api/orders/{id}
     */
    public function show($id)
    {
        $user = $this->getCurrentUser();

        try {
            $order = $this->orderModel->getById($id);

            if (!$order) {
                Response::notFound('Order not found');
            }

            // Check if order belongs to user (unless admin)
            if ($user['role'] !== 'admin' && $order['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to view this order');
            }

            Response::success('Order retrieved successfully', $order);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Get all orders (Admin only)
     * GET /api/admin/orders
     */
    public function adminIndex()
    {
        $user = $this->getCurrentUser();

        if ($user['role'] !== 'admin') {
            Response::forbidden('Admin access required');
        }

        try {
            $orders = $this->orderModel->getAll();
            Response::success('Orders retrieved successfully', $orders);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Update order status (Admin only)
     * PUT /api/admin/orders/{id}
     */
    public function updateStatus($id)
    {
        $user = $this->getCurrentUser();

        if ($user['role'] !== 'admin') {
            Response::forbidden('Admin access required');
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('status')
            ->in('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled']);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check if order exists
        $order = $this->orderModel->getById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        // Update status
        try {
            $updated = $this->orderModel->updateStatus($id, $input['status']);

            if ($updated) {
                Response::success('Order status updated successfully', $updated);
            } else {
                Response::error('Failed to update order status');
            }
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }
}

