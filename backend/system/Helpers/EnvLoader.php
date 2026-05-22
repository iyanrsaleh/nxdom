<?php
namespace App\System\Helpers;

class EnvLoader {
    private static $loaded = false;
    private static $envVars = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($envFile = null) {
        if (self::$loaded && empty($envFile)) {
            return;
        }
        
        $envFile = $envFile ?: dirname(dirname(__DIR__)) . '/.env';
        
        // If .env file doesn't exist, use default configuration
        if (!file_exists($envFile)) {
            self::loadDefaults();
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
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
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                $_ENV[$key] = $value;
                self::$envVars[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Load default configuration if .env file doesn't exist
     */
    private static function loadDefaults() {
        $defaults = [
            'DB_HOST' => 'localhost',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_DATABASE' => 'nexa_db',
            'DB_PORT' => '3306',
            'DB_CHARSET' => 'utf8mb4',
            'APP_NAME' => 'NexaFramework',
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost',
            'WEBSOCKET_HOST' => '127.0.0.1',
            'WEBSOCKET_PORT' => '8080',
            'SECRET_KEY' => 'nexaui2025_secret_key',
            'CACHE_ENABLED' => 'true',
            'CACHE_TTL' => '3600',
            'LOG_LEVEL' => 'info',
            'LOG_FILE' => 'system/log/app.log'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                self::$envVars[$key] = $value;
            }
        }
        
        self::$loaded = true;
        echo "INFO: Using default configuration (create .env file for custom settings)\n";
    }
    
    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * Set environment variable
     */
    public static function set($key, $value) {
        $_ENV[$key] = $value;
        self::$envVars[$key] = $value;
    }
    
    /**
     * Check if variable exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset($_ENV[$key]);
    }
    
    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$envVars;
    }
    
    /**
     * Create .env file with current configuration
     */
    public static function createEnvFile($filePath = null) {
        $filePath = $filePath ?: dirname(dirname(__DIR__)) . '/.env';
        
        $content = "# Database Configuration\n";
        $content .= "DB_HOST=" . self::get('DB_HOST', 'localhost') . "\n";
        $content .= "DB_USERNAME=" . self::get('DB_USERNAME', 'root') . "\n";
        $content .= "DB_PASSWORD=" . self::get('DB_PASSWORD', '') . "\n";
        $content .= "DB_DATABASE=" . self::get('DB_DATABASE', 'nexa_db') . "\n";
        $content .= "DB_PORT=" . self::get('DB_PORT', '3306') . "\n";
        $content .= "DB_CHARSET=" . self::get('DB_CHARSET', 'utf8mb4') . "\n\n";
        
        $content .= "# Application Configuration\n";
        $content .= "APP_NAME=" . self::get('APP_NAME', 'NexaFramework') . "\n";
        $content .= "APP_ENV=" . self::get('APP_ENV', 'development') . "\n";
        $content .= "APP_DEBUG=" . self::get('APP_DEBUG', 'true') . "\n";
        $content .= "APP_URL=" . self::get('APP_URL', 'http://localhost') . "\n\n";
        
        $content .= "# WebSocket Configuration\n";
        $content .= "WEBSOCKET_HOST=" . self::get('WEBSOCKET_HOST', '127.0.0.1') . "\n";
        $content .= "WEBSOCKET_PORT=" . self::get('WEBSOCKET_PORT', '8080') . "\n\n";
        
        $content .= "# Security\n";
        $content .= "SECRET_KEY=" . self::get('SECRET_KEY', 'nexaui2025_secret_key') . "\n\n";
        
        $content .= "# Cache Configuration\n";
        $content .= "CACHE_ENABLED=" . self::get('CACHE_ENABLED', 'true') . "\n";
        $content .= "CACHE_TTL=" . self::get('CACHE_TTL', '3600') . "\n\n";
        
        $content .= "# Logging\n";
        $content .= "LOG_LEVEL=" . self::get('LOG_LEVEL', 'info') . "\n";
        $content .= "LOG_FILE=" . self::get('LOG_FILE', 'system/log/app.log') . "\n";
        
        return file_put_contents($filePath, $content);
    }
    
    /**
     * Validate database configuration
     */
    public static function validateDatabaseConfig() {
        $required = ['DB_HOST', 'DB_USERNAME', 'DB_DATABASE'];
        $missing = [];
        
        foreach ($required as $var) {
            if (empty(self::get($var))) {
                $missing[] = $var;
            }
        }
        
        if (!empty($missing)) {
            throw new \Exception("Missing required database configuration: " . implode(', ', $missing));
        }
        
        return true;
    }
    
    /**
     * Display current configuration
     */
    public static function displayConfig() {
        if (!self::$loaded) {
            self::load();
        }
        
        echo "=== Environment Configuration ===\n";
        echo "Database:\n";
        echo "  Host: " . self::get('DB_HOST') . "\n";
        echo "  Username: " . self::get('DB_USERNAME') . "\n";
        echo "  Database: " . self::get('DB_DATABASE') . "\n";
        echo "  Port: " . self::get('DB_PORT') . "\n";
        echo "  Charset: " . self::get('DB_CHARSET') . "\n";
        echo "\nApplication:\n";
        echo "  Name: " . self::get('APP_NAME') . "\n";
        echo "  Environment: " . self::get('APP_ENV') . "\n";
        echo "  Debug: " . self::get('APP_DEBUG') . "\n";
        echo "\nWebSocket:\n";
        echo "  Host: " . self::get('WEBSOCKET_HOST') . "\n";
        echo "  Port: " . self::get('WEBSOCKET_PORT') . "\n";
        echo "================================\n\n";
    }
} 