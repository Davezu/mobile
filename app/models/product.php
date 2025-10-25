<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * Product Model
 * Handles product-related database operations
 */
class Product
{
    private $conn;
    private $table = 'products';

    public function __construct()
    {
        require_once __DIR__ . '/../../config/config.php';
        $this->conn = getConnection();
    }

    /**
     * Get all products
     */
    public function getAll()
    {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product by ID
     */
    public function getById($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get products by category
     */
    public function getByCategory($category)
    {
        $query = "SELECT * FROM {$this->table} WHERE category = :category ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category', $category);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search products
     */
    public function search($query)
    {
        $searchTerm = "%{$query}%";
        $sql = "SELECT * FROM {$this->table} 
                WHERE name LIKE :search 
                OR description LIKE :search 
                OR category LIKE :search 
                ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':search', $searchTerm);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new product
     */
    public function create($data)
    {
        $query = "INSERT INTO {$this->table} 
                  (name, description, price, stock, category, image_url) 
                  VALUES (:name, :description, :price, :stock, :category, :image_url)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':stock', $data['stock']);
        $stmt->bindParam(':category', $data['category']);
        $stmt->bindParam(':image_url', $data['image_url']);
        
        if ($stmt->execute()) {
            return $this->getById($this->conn->lastInsertId());
        }
        
        return false;
    }

    /**
     * Update product
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $fields[] = "price = :price";
            $params[':price'] = $data['price'];
        }
        if (isset($data['stock'])) {
            $fields[] = "stock = :stock";
            $params[':stock'] = $data['stock'];
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = $data['category'];
        }
        if (isset($data['image_url'])) {
            $fields[] = "image_url = :image_url";
            $params[':image_url'] = $data['image_url'];
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            return $this->getById($id);
        }
        
        return false;
    }

    /**
     * Delete product
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /**
     * Update stock
     */
    public function updateStock($id, $quantity)
    {
        $query = "UPDATE {$this->table} SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':quantity', $quantity);
        return $stmt->execute();
    }
}

