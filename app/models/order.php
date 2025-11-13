<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * Order Model
 * Handles order-related database operations
 */
class Order
{
    private $conn;
    private $ordersTable = 'orders';
    private $orderItemsTable = 'order_items';

    public function __construct()
    {
        require_once __DIR__ . '/../../config/config.php';
        $this->conn = getConnection();
    }

    /**
     * Create new order
     */
    public function create($userId, $items, $shippingAddress, $checkoutSessionId = null)
    {
        try {
            $this->conn->beginTransaction();

            // Calculate total
            $total = 0;
            foreach ($items as $item) {
                $total += $item['price'] * $item['quantity'];
            }

            // Check if checkout_session_id column exists
            $hasCheckoutSessionColumn = false;
            try {
                $checkQuery = "SHOW COLUMNS FROM {$this->ordersTable} LIKE 'checkout_session_id'";
                $checkStmt = $this->conn->query($checkQuery);
                $hasCheckoutSessionColumn = $checkStmt->rowCount() > 0;
            } catch (Exception $e) {
                // Column doesn't exist, continue without it
                error_log("Order Model: checkout_session_id column not found, creating order without it");
            }

            // Create order - include checkout_session_id only if column exists
            if ($hasCheckoutSessionColumn) {
                $query = "INSERT INTO {$this->ordersTable} 
                          (user_id, total_amount, shipping_address, status, checkout_session_id) 
                          VALUES (:user_id, :total_amount, :shipping_address, 'pending', :checkout_session_id)";
            } else {
                $query = "INSERT INTO {$this->ordersTable} 
                          (user_id, total_amount, shipping_address, status) 
                          VALUES (:user_id, :total_amount, :shipping_address, 'pending')";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':total_amount', $total);
            $stmt->bindParam(':shipping_address', $shippingAddress);
            if ($hasCheckoutSessionColumn) {
                $stmt->bindParam(':checkout_session_id', $checkoutSessionId);
            }
            $stmt->execute();

            $orderId = $this->conn->lastInsertId();

            // Create order items
            $query = "INSERT INTO {$this->orderItemsTable} 
                      (order_id, product_id, quantity, price, subtotal) 
                      VALUES (:order_id, :product_id, :quantity, :price, :subtotal)";
            
            $stmt = $this->conn->prepare($query);

            foreach ($items as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->execute();

                // Update product stock
                $updateQuery = "UPDATE products SET stock = stock - :quantity WHERE id = :product_id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':quantity', $item['quantity']);
                $updateStmt->bindParam(':product_id', $item['product_id']);
                $updateStmt->execute();
            }

            $this->conn->commit();

            return $this->getById($orderId);
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Get order by ID with items
     */
    public function getById($id)
    {
        $query = "SELECT * FROM {$this->ordersTable} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $order['items'] = $this->getOrderItems($id);
        }
        
        return $order;
    }

    /**
     * Get user's orders
     */
    public function getUserOrders($userId)
    {
        $query = "SELECT * FROM {$this->ordersTable} 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }
        
        return $orders;
    }

    /**
     * Get all orders (admin)
     */
    public function getAll()
    {
        $query = "SELECT o.*, u.username, u.email 
                  FROM {$this->ordersTable} o
                  LEFT JOIN users u ON o.user_id = u.id
                  ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }
        
        return $orders;
    }

    /**
     * Get order items
     */
    private function getOrderItems($orderId)
    {
        $query = "SELECT oi.*, p.name as product_name 
                  FROM {$this->orderItemsTable} oi
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update order status
     */
    public function updateStatus($id, $status)
    {
        $query = "UPDATE {$this->ordersTable} 
                  SET status = :status 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            return $this->getById($id);
        }
        
        return false;
    }

    /**
     * Check if order belongs to user
     */
    public function belongsToUser($orderId, $userId)
    {
        $query = "SELECT COUNT(*) FROM {$this->ordersTable} 
                  WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get order by checkout session ID
     */
    public function getByCheckoutSessionId($checkoutSessionId)
    {
        $query = "SELECT * FROM {$this->ordersTable} WHERE checkout_session_id = :checkout_session_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':checkout_session_id', $checkoutSessionId);
        $stmt->execute();
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }
        
        return $order;
    }
}

