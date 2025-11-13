<?php

defined("APP_ACCESS") or define("APP_ACCESS", true);

// Simple .env file loader
function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            
            if (!array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Helper function to get environment variable
function env($key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }
    
    return $value;
}

// Load environment variables
loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

define("DB_HOST", env('DB_HOST', 'localhost'));
define("DB_NAME", env('DB_NAME', 'ecommerce_db'));
define("DB_USER", env('DB_USER', 'root'));
define("DB_PASS", env('DB_PASS', ''));
define("DB_CHARSET", env('DB_CHARSET', 'utf8mb4'));

define("APP_ENV", env('APP_ENV', 'development')); 
define("APP_DEBUG", env('APP_DEBUG', true));
define("APP_URL", env('APP_URL', 'http://localhost/e-commerce'));

// Firebase Configuration
define("FIREBASE_SERVICE_ACCOUNT_PATH", env('FIREBASE_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-service-account.json'));
define("FIREBASE_STORAGE_BUCKET", env('FIREBASE_STORAGE_BUCKET', ''));

// For API endpoints, never display errors (they break JSON responses)
error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

if (APP_ENV === "development") {
    // Log errors to file in development
    ini_set("error_log", __DIR__ . "/../logs/php_errors.log");
}

class Database
{
    private static $instance = null;
    private $connection;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        try {
            $dsn =
                "mysql:host=" .
                DB_HOST .
                ";dbname=" .
                DB_NAME .
                ";charset=" .
                DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Failed: " . $e->getMessage());
            // For API responses, throw exception instead of die()
            throw new Exception("Database connection failed");
        }
    }

    /**
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     *
     * @return PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Execute a prepared statement with parameters
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function query($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            // For API responses, throw exception instead of die()
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch all rows from a query
     *
     * @param string $query SQL query
     * @param array $params Parameters to bind
     * @return array
     */
    public function fetchAll($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch single row from a query
     *
     * @param string $query SQL query
     * @param array $params Parameters to bind
     * @return array|false
     */
    public function fetch($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }

    /**
     * Get last inserted ID
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    /**
     * Prevent cloning of instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get database instance
 *
 * @return Database
 */
function getDB()
{
    return Database::getInstance();
}

/**
 * Helper function to get PDO connection
 *
 * @return PDO
 */
function getConnection()
{
    return Database::getInstance()->getConnection();
}
