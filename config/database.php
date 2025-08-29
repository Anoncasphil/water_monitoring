<?php
require_once __DIR__ . '/EnvLoader.php';

/**
 * Database Connection Class
 * Implements Singleton pattern for database connections
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Load environment configuration
        EnvLoader::load();
        
        // Get database configuration from environment
        $config = EnvLoader::getDatabaseConfig();
        
        try {
            // Enable error reporting
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );
            
            // Set charset to ensure proper encoding
            $this->connection->set_charset("utf8mb4");
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
        } catch (Exception $e) {
            // Log the error with timestamp
            error_log("[" . date('Y-m-d H:i:s') . "] Database connection error: " . $e->getMessage());
            throw new Exception("Database connection error. Please check the configuration.");
        }
    }
    
    /**
     * Get database instance (Singleton pattern)
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get database connection
     * @return mysqli
     */
    public function getConnection() {
        if (!$this->connection || $this->connection->ping() === false) {
            // If connection is lost, try to reconnect
            $this->__construct();
        }
        return $this->connection;
    }
    
    /**
     * Execute a query with error handling
     * @param string $query
     * @return mysqli_result|bool
     */
    public function query($query) {
        try {
            return $this->getConnection()->query($query);
        } catch (Exception $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] Query error: " . $e->getMessage());
            throw new Exception("Database query error occurred.");
        }
    }
    
    /**
     * Escape string to prevent SQL injection
     * @param string $string
     * @return string
     */
    public function escapeString($string) {
        return $this->getConnection()->real_escape_string($string);
    }
    
    // Prevent cloning of the instance
    private function __clone() {}
    
    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    // Close connection when object is destroyed
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}