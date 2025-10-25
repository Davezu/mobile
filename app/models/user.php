<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * User Model
 * Handles user-related database operations
 */
class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Register a new user
     */
    public function register($data)
    {
        // Hash the password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $query = "INSERT INTO users (username, email, password, full_name, phone, address, role) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['role'] ?? 'user'
        ];

        try {
            $this->db->query($query, $params);
            $userId = $this->db->lastInsertId();

            // Return user data without password
            return $this->findById($userId);
        } catch (PDOException $e) {
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    /**
     * Find user by ID (without sensitive fields)
     */
    public function findById($id)
    {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at, updated_at 
                  FROM users WHERE id = ?";
        return $this->db->fetch($query, [$id]);
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        $query = "SELECT * FROM users WHERE email = ?";
        return $this->db->fetch($query, [$email]);
    }

    /**
     * Find user by username
     */
    public function findByUsername($username)
    {
        // Note: Password is included for verification, but should be unset before returning to client
        $query = "SELECT id, username, email, password, full_name, phone, address, role, is_active, created_at, updated_at 
                  FROM users WHERE username = ?";
        return $this->db->fetch($query, [$username]);
    }

    /**
     * Verify user password
     */
    public function verifyPassword($password, $hashedPassword)
    {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Update user profile
     */
    public function updateProfile($id, $data)
    {
        $fields = [];
        $params = [];

        // Build dynamic update query
        $allowedFields = ['full_name', 'phone', 'address'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $params[] = $id;
        $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

        try {
            $this->db->query($query, $params);
            return $this->findById($id);
        } catch (PDOException $e) {
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getAll($limit = 100, $offset = 0)
    {
        $query = "SELECT id, username, email, full_name, phone, role, is_active, created_at 
                  FROM users 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($query, [$limit, $offset]);
    }

    /**
     * Check if email exists
     */
    public function emailExists($email)
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = $this->db->fetch($query, [$email]);
        return $result['count'] > 0;
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username)
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $result = $this->db->fetch($query, [$username]);
        return $result['count'] > 0;
    }

    /**
     * Update user's remember token
     */
    public function updateToken($userId, $token)
    {
        $query = "UPDATE users SET remember_token = ? WHERE id = ?";
        try {
            $this->db->query($query, [$token, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Token update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user by ID (admin access)
     */
    public function getById($id)
    {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at 
                  FROM users WHERE id = ?";
        return $this->db->fetch($query, [$id]);
    }

    /**
     * Update user status (admin only)
     */
    public function updateStatus($id, $isActive)
    {
        $query = "UPDATE users SET is_active = ? WHERE id = ?";
        try {
            $this->db->query($query, [$isActive ? 1 : 0, $id]);
            return true;
        } catch (PDOException $e) {
            error_log("Status update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user role (admin only)
     */
    public function updateRole($id, $role)
    {
        $query = "UPDATE users SET role = ? WHERE id = ?";
        try {
            $this->db->query($query, [$role, $id]);
            return true;
        } catch (PDOException $e) {
            error_log("Role update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete user (admin only)
     */
    public function delete($id)
    {
        $query = "DELETE FROM users WHERE id = ?";
        try {
            $this->db->query($query, [$id]);
            return true;
        } catch (PDOException $e) {
            error_log("User deletion failed: " . $e->getMessage());
            return false;
        }
    }
}
