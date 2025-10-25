<?php

function verifyToken() {
    try {
        // Get Authorization header
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
        
        if (empty($authHeader)) {
            error_log("No Authorization header found");
            return false;
        }
        
        // Extract token (remove 'Bearer ' prefix)
        $token = str_replace('Bearer ', '', $authHeader);
        $token = trim($token);
        
        if (empty($token)) {
            error_log("Token is empty after extraction");
            return false;
        }
        
        require_once __DIR__ . '/../../config/config.php';
        $db = getDB();
        $conn = $db->getConnection();
        
        // Query user by remember_token
        $query = "SELECT u.id, u.username, u.email, u.role, u.is_active
                  FROM users u 
                  WHERE u.remember_token = ? AND u.is_active = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("Token verified successfully for user: " . $user['username']);
            return [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
        }
        
        error_log("No user found for token");
        return false;
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
}

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';

/**
 * Auth Controller
 * Handles authentication operations
 */
class AuthController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Register a new user
     * POST /api/auth/register
     */
    public function register()
    {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('username')
            ->minLength('username', 3)
            ->maxLength('username', 50)
            ->pattern('username', '/^[a-zA-Z0-9_]+$/', 'Username can only contain letters, numbers, and underscores')
            ->unique('username', 'users', 'username', 'Username already taken')
            
            ->required('email')
            ->email('email')
            ->unique('email', 'users', 'email', 'Email already registered')
            
            ->required('password')
            ->minLength('password', 6, 'Password must be at least 6 characters');

        // Optional fields validation
        if (isset($input['full_name'])) {
            $validator->maxLength('full_name', 100);
        }

        if (isset($input['phone'])) {
            $validator->pattern('phone', '/^[0-9+\-\s()]+$/', 'Invalid phone number format');
        }

        // Check if validation failed
        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        // Register user
        try {
            $user = $this->userModel->register($input);

            // Start session and create token
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            $sessionId = session_id();
            
            // Store session ID as remember_token
            $this->userModel->updateToken($user['id'], $sessionId);

            Response::success('User registered successfully', [
                'user' => $user,
                'token' => $sessionId
            ], 201);
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Login user
     * POST /api/auth/login
     */
    public function login()
    {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
            return;
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('username', 'Username is required')
            ->required('password', 'Password is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        // Find user by username
        $user = $this->userModel->findByUsername($input['username']);

        if (!$user) {
            Response::error('Invalid credentials', null, 401);
            return;
        }

        // Verify password
        if (!$this->userModel->verifyPassword($input['password'], $user['password'])) {
            Response::error('Invalid credentials', null, 401);
            return;
        }

        // Create session and store session ID as token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        $sessionId = session_id();
        
        // Store session ID as remember_token for API authentication
        $this->userModel->updateToken($user['id'], $sessionId);

        // Remove password from response
        unset($user['password']);

        Response::success('Login successful', [
            'user' => $user,
            'token' => $sessionId
        ]);
    }

    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear the remember token from database
        if (isset($_SESSION['user_id'])) {
            $this->userModel->updateToken($_SESSION['user_id'], null);
        }

        session_destroy();
        Response::success('Logout successful');
    }

    /**
     * Get current authenticated user
     * GET /api/auth/me
     */
    public function me()
    {
        // Try to get user from token first
        $user = verifyToken();
        
        // If no token, try session
        if (!$user) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_SESSION['user_id'])) {
                Response::unauthorized('Not authenticated');
                return;
            }

            $user = $this->userModel->findById($_SESSION['user_id']);
        } else {
            // Get full user data from ID
            $user = $this->userModel->findById($user['id']);
        }

        if (!$user) {
            Response::notFound('User not found');
            return;
        }

        Response::success('User data retrieved', [
            'user' => $user
        ]);
    }
}
