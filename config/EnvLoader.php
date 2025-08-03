<?php
/**
 * Environment Configuration Loader
 * Loads configuration from .env file
 */
class EnvLoader {
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * Load environment variables from .env file
     * @param string $path Path to .env file
     * @return bool
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return true;
        }
        
        if ($path === null) {
            // Try multiple locations for .env file
            $possiblePaths = [
                // For shared hosting (outside public_html)
                dirname(dirname(__DIR__)) . '/.env',
                // For local development
                __DIR__ . '/.env',
                // Fallback to current directory
                '.env'
            ];
            
            $path = null;
            foreach ($possiblePaths as $possiblePath) {
                if (file_exists($possiblePath)) {
                    $path = $possiblePath;
                    break;
                }
            }
        }
        
        if (!$path || !file_exists($path)) {
            // Try to load from example file
            $examplePath = __DIR__ . '/env.example';
            if (file_exists($examplePath)) {
                error_log("Warning: .env file not found. Please copy env.example to .env and configure your settings.");
                error_log("Expected locations: " . implode(', ', $possiblePaths));
            }
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                self::$variables[$key] = $value;
                
                // Set as environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
        
        self::$loaded = true;
        return true;
    }
    
    /**
     * Get environment variable
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$variables[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Get all environment variables
     * @return array
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$variables;
    }
    
    /**
     * Check if environment variable exists
     * @param string $key
     * @return bool
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$variables[$key]) || getenv($key) !== false;
    }
    
    /**
     * Get database configuration array
     * @return array
     */
    public static function getDatabaseConfig() {
        return [
            'host' => self::get('DB_HOST', '127.0.0.1'),
            'port' => self::get('DB_PORT', 3306), // Changed default to 3306 for shared hosting
            'database' => self::get('DB_NAME', 'water_quality_db'),
            'username' => self::get('DB_USERNAME', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4')
        ];
    }
} 