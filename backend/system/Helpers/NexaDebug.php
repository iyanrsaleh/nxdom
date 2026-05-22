<?php
declare(strict_types=1);
namespace App\System\Helpers;
class NexaDebug {
    /**
     * Path file log error
     * @var string
     */
    private static $errorLogPath;
    
    /**
     * Path file log error JSON
     * @var string
     */
    private static $errorLogJsonPath;
    
    /**
     * Debug mode status
     * @var boolean
     */
    private static $debugMode;

    /**
     * Production mode status
     * @var boolean
     */
    private static $isInitialized = false;

    /**
     * Log format (text or json)
     * @var string
     */
    private static $logFormat = 'text';

    /**
     * Maximum log file size in bytes (10MB)
     * @var int
     */
    private static $maxLogSize = 10485760;

    /**
     * Maximum number of log files to keep
     * @var int
     */
    private static $maxLogFiles = 7;

    /**
     * Performance start time
     * @var array
     */
    private static $performanceTimers = [];

    /**
     * Request context data
     * @var array
     */
    private static $context = [];

    /**
     * Log levels
     * @var array
     */
    private static $logLevels = [
        'CRITICAL' => 1,
        'ERROR' => 2,
        'WARNING' => 3,
        'INFO' => 4,
        'DEBUG' => 5,
        'TRACE' => 6
    ];

    /**
     * Current log level
     * @var int
     */
    private static $currentLogLevel = 5; // DEBUG level

    /**
     * Include stack trace in logs
     * @var bool
     */
    private static $includeStackTrace = false;

    /**
     * Daftar tipe error yang didukung
     * @var array
     */
    private static $error_types = [
        E_ERROR => 'FATAL ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSING ERROR', 
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE'
    ];




    
    /**
     * Global log filter settings
     * @var array
     */


    /**
     * Original error_log function
     * @var callable
     */
    private static $originalErrorLog = null;

    /**
     * Inisialisasi path file log
     */
    public static function init() {
        if (self::$isInitialized) {
            return;
        }

        // Load environment variables first if not already loaded
        self::loadEnvironmentVariables();

        // Validate configuration first
        self::validateConfig();

        // Load settings from environment variables
        self::$debugMode = isset($_ENV['APP_DEBUG']) ? filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) : false;
        
        // Set log format
        self::$logFormat = $_ENV['LOG_FORMAT'] ?? 'text';
        
        // Set log level
        $configLogLevel = $_ENV['LOG_LEVEL'] ?? 'DEBUG';
        self::$currentLogLevel = self::$logLevels[$configLogLevel] ?? 5;

        // Set stack trace option
        self::$includeStackTrace = isset($_ENV['INCLUDE_STACK_TRACE']) ? filter_var($_ENV['INCLUDE_STACK_TRACE'], FILTER_VALIDATE_BOOLEAN) : false;

        // Set max log size and files
        self::$maxLogSize = isset($_ENV['MAX_LOG_SIZE']) ? (int)$_ENV['MAX_LOG_SIZE'] : 10485760;
        self::$maxLogFiles = isset($_ENV['MAX_LOG_FILES']) ? (int)$_ENV['MAX_LOG_FILES'] : 7;

        if (!self::$debugMode) {
            error_reporting(0);
            ini_set('display_errors', '0');
            ini_set('log_errors', '0');
            
            // Disable default PHP error log
            ini_set('error_log', '/dev/null');
            
            self::$isInitialized = true;
            return;
        }
        
        // Set path untuk error log
        $log_dir = self::getLogPath();
        
        // Buat direktori logs jika belum ada
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        // Set nama file berdasarkan tanggal
        $date = date('Y-m-d');
        self::$errorLogPath = $log_dir . "/error_{$date}.log";
        self::$errorLogJsonPath = $log_dir . "/error_{$date}.json";
        
        // Pastikan file bisa ditulis
        if (!file_exists(self::$errorLogPath)) {
            touch(self::$errorLogPath);
            chmod(self::$errorLogPath, 0666);
        }
        
        if (!file_exists(self::$errorLogJsonPath)) {
            touch(self::$errorLogJsonPath);
            chmod(self::$errorLogJsonPath, 0666);
            // Skip JSON initialization for performance
        }

        // Check and rotate log if needed
        self::rotateLogIfNeeded();
        
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('log_errors', '1');
        ini_set('error_log', self::$errorLogPath);

        // Initialize request context
        self::initializeContext();

        // Clean old log files (skip for performance)
        // self::cleanOldLogs();
        
        // Basic error interception (simplified)
        set_error_handler([self::class, 'handlePhpError']);

        self::$isInitialized = true;
    }

    /**
     * Load environment variables dari config.env
     */
    private static function loadEnvironmentVariables(): void {
        // Skip jika sudah dimuat
        if (isset($_ENV['APP_DEBUG'])) {
            return;
        }
        
        $configFile = dirname(__DIR__, 1) . '/config.env';
        
        if (file_exists($configFile)) {
            $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip comment lines (yang dimulai dengan #)
                if (empty($line) || strpos($line, '#') === 0) continue;
                
                // Skip baris yang tidak memiliki format key=value
                if (strpos($line, '=') === false) continue;
                
                // Parse key=value dan simpan ke $_ENV
                list($name, $value) = explode('=', $line, 2);
                $key = trim($name);
                
                // Handle inline comments - ambil hanya bagian sebelum # (jika ada)
                if (strpos($value, '#') !== false) {
                    $value = substr($value, 0, strpos($value, '#'));
                }
                
                // Clean value - hapus quotes dan trim whitespace
                $val = trim($value, " \t\n\r\0\x0B\"'");
                
                $_ENV[$key] = $val;
            }
        }
    }



    /**
     * Handle PHP errors dan masukkan ke JSON
     */
    public static function handlePhpError($severity, $message, $file, $line, $context = []) {
        // Jangan handle jika debug mode off
        if (!self::$debugMode) {
            return false;
        }
        
        // Create context for JSON logging
        $errorContext = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'severity_name' => self::$error_types[$severity] ?? 'UNKNOWN',
            'context' => $context
        ];
        
        // Log ke JSON menggunakan NexaDebug
        self::logError("PHP Error: {$message}", 'ERROR', $errorContext, false);
        
        // Return false agar PHP default error handler juga jalan
        return false;
    }

    /**
     * Handle shutdown errors
     */
    public static function handleShutdownErrors(): void {
        $error = error_get_last();
        if ($error && self::$debugMode) {
            $errorContext = [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];
            
            self::logError("Shutdown Error: {$error['message']}", 'CRITICAL', $errorContext, false);
        }
    }



    /**
     * Enhanced error_log function yang juga masuk ke JSON
     */
    public static function errorLog($message, $message_type = 3, $destination = null, $extra_headers = null): bool {
        // Jika debug mode off, gunakan error_log biasa
        if (!self::$debugMode) {
            return error_log($message, $message_type, $destination, $extra_headers);
        }
        
        // Log ke JSON dulu
        self::logError($message, 'INFO', ['source' => 'error_log_function'], false);
        
        // Kemudian log ke text file seperti biasa
        $destination = $destination ?? self::$errorLogPath;
        return error_log($message, $message_type, $destination, $extra_headers);
    }

    /**
     * Validate configuration
     */
    private static function validateConfig() {
        // Check if log directory is writable
        $logDir = self::getLogPath();
        if (!is_writable(dirname($logDir))) {
            throw new \Exception('Log directory is not writable: ' . dirname($logDir));
        }
        
        // Create log directory if needed
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    /**
     * Initialize request context
     */
    private static function initializeContext() {
        self::$context = [
            'request_id' => self::generateRequestId(),
            'timestamp' => microtime(true),
            'memory_start' => memory_get_usage(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI'
        ];
    }

    /**
     * Generate unique request ID
     */
    private static function generateRequestId() {
        return substr(md5(uniqid((string)rand(), true)), 0, 8);
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private static function rotateLogIfNeeded() {
        // Rotate text log
        if (file_exists(self::$errorLogPath) && filesize(self::$errorLogPath) > self::$maxLogSize) {
            $timestamp = date('Y-m-d_H-i-s');
            $rotatedFile = str_replace('.log', "_{$timestamp}.log", self::$errorLogPath);
            rename(self::$errorLogPath, $rotatedFile);
            
            // Create new log file
            touch(self::$errorLogPath);
            chmod(self::$errorLogPath, 0666);
        }
        
        // Skip JSON rotation for performance
        // if (file_exists(self::$errorLogJsonPath) && filesize(self::$errorLogJsonPath) > self::$maxLogSize) {
        //     $timestamp = date('Y-m-d_H-i-s');
        //     $rotatedJsonFile = str_replace('.json', "_{$timestamp}.json", self::$errorLogJsonPath);
        //     rename(self::$errorLogJsonPath, $rotatedJsonFile);
        //     
        //     // Create new JSON log file
        //     touch(self::$errorLogJsonPath);
        //     chmod(self::$errorLogJsonPath, 0666);
        // }
    }

    /**
     * Clean old log files
     */
    private static function cleanOldLogs() {
        $logDir = self::getLogPath();
        
        // Clean old text log files
        $textFiles = glob($logDir . '/error_*.log');
        if (count($textFiles) > self::$maxLogFiles) {
            // Sort by modification time (oldest first)
            usort($textFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove excess files
            $filesToRemove = array_slice($textFiles, 0, count($textFiles) - self::$maxLogFiles);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
        
        // Clean old JSON log files
        $jsonFiles = glob($logDir . '/error_*.json');
        if (count($jsonFiles) > self::$maxLogFiles) {
            // Sort by modification time (oldest first)
            usort($jsonFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove excess files
            $filesToRemove = array_slice($jsonFiles, 0, count($jsonFiles) - self::$maxLogFiles);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Mencatat pesan error ke file log
     * 
     * @param string $message Pesan error yang akan dicatat
     * @param mixed $error_type Tipe error (bisa berupa string atau konstanta PHP error)
     * @param array $context Additional context data
     * @param bool $includeTrace Include stack trace
     * @return void
     */
    public static function logError($message, $error_type = 'ERROR', $context = [], $includeTrace = false) {
        if (!self::$isInitialized) {
            self::init();
        }

        // Jika dalam mode production, tidak melakukan logging
        if (!self::$debugMode) {
            return;
        }

        // Check log level
        $level = is_string($error_type) ? $error_type : 'ERROR';
        if (!self::shouldLog($level)) {
            return;
        }

        // Simple pattern filtering only for critical patterns
        if (self::shouldSkipLogging($message)) {
            return;
        }

        // Konversi tipe error jika menggunakan konstanta PHP
        if (is_numeric($error_type) && isset(self::$error_types[$error_type])) {
            $error_type = self::$error_types[$error_type];
        }

        // Sanitize sensitive data
        $message = self::sanitizeMessage($message);
        
        // Only include trace if explicitly requested AND global setting allows it
        $shouldIncludeTrace = $includeTrace && self::$includeStackTrace;
        
        // Prepare common log data for both formats
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'unix_timestamp' => time(),
            'level' => $error_type,
            'message' => $message,
            'request_id' => self::$context['request_id'] ?? null,
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'context' => array_merge(self::$context, $context)
        ];

        if ($shouldIncludeTrace) {
            $logData['stack_trace'] = self::getStackTrace();
        }

        // Write to both text and JSON logs for maximum compatibility
        
        // 1. Always write to text log (primary format)
        $timestamp = date('Y-m-d H:i:s');
        $requestId = self::$context['request_id'] ?? 'N/A';
        $logMessage = "[{$timestamp}] [{$error_type}] [ID:{$requestId}] {$message}" . PHP_EOL;
        
        if (self::$debugMode && isset(self::$errorLogPath)) {
            error_log($logMessage, 3, self::$errorLogPath);
        }
        
        // 2. Also write to JSON log for enhanced features and UI
        if (self::$debugMode && isset(self::$errorLogJsonPath)) {
            self::writeJsonLog($logData);
        }
    }



    /**
     * Check if message should be logged based on current log level
     */
    private static function shouldLog($level) {
        $messageLevel = self::$logLevels[$level] ?? 5;
        return $messageLevel <= self::$currentLogLevel;
    }

    /**
     * Sanitize sensitive information from log messages
     */
    private static function sanitizeMessage($message) {
        $sensitivePatterns = [
            '/password["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i' => 'password=***HIDDEN***',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i' => 'api_key=***HIDDEN***',
            '/token["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i' => 'token=***HIDDEN***',
            '/secret["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i' => 'secret=***HIDDEN***'
        ];
        
        foreach ($sensitivePatterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $message);
            if ($result !== null) {
                $message = $result;
            }
        }
        
        return $message;
    }

    /**
     * Get formatted stack trace
     */
    private static function getStackTrace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        array_shift($trace); // Remove current function
        return $trace;
    }

    /**
     * Get stack trace as string
     */
    private static function getStackTraceString() {
        $trace = self::getStackTrace();
        $result = '';
        
        foreach ($trace as $i => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 'unknown';
            $function = $item['function'] ?? 'unknown';
            $class = isset($item['class']) ? $item['class'] . '::' : '';
            
            $result .= "#{$i} {$file}({$line}): {$class}{$function}()" . PHP_EOL;
        }
        
        return $result;
    }

    /**
     * Log with different levels
     */
    public static function critical($message, $context = [], $includeTrace = false) {
        self::logError($message, 'CRITICAL', $context, $includeTrace);
    }

    public static function error($message, $context = [], $includeTrace = false) {
        self::logError($message, 'ERROR', $context, $includeTrace);
    }

    public static function warning($message, $context = []) {
        self::logError($message, 'WARNING', $context);
    }

    public static function info($message, $context = []) {
        self::logError($message, 'INFO', $context);
    }

    /**
     * Log informasi debug
     * 
     * @param string $message
     * @return void
     */
    public static function logDebug($message) {
        if (!self::$isInitialized) {
            self::init();
        }

        // Tidak melakukan logging jika dalam mode production
        if (!self::$debugMode) {
            return;
        }

        self::logError($message, 'DEBUG');
    }

    public static function debug($message, $context = []) {
        self::logError($message, 'DEBUG', $context);
    }

    public static function trace($message, $context = []) {
        self::logError($message, 'TRACE', $context);
    }

    /**
     * Log informasi routing
     * 
     * @param string $message
     * @return void
     */
    public static function logRoute($message) {
        if (!self::$isInitialized) {
            self::init();
        }

        // Tidak melakukan logging jika dalam mode production
        if (!self::$debugMode) {
            return;
        }

        self::logError($message, 'INFO', ['type' => 'route']);
    }

    /**
     * Start performance timer
     */
    public static function startTimer($name) {
        self::$performanceTimers[$name] = microtime(true);
    }

    /**
     * End performance timer and log result
     */
    public static function endTimer($name, $message = '') {
        if (!isset(self::$performanceTimers[$name])) {
            return;
        }

        $duration = microtime(true) - self::$performanceTimers[$name];
        $message = $message ?: "Timer '{$name}' completed";
        
        self::info($message, [
            'timer' => $name,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage()
        ]);

        unset(self::$performanceTimers[$name]);
    }

    /**
     * Log database query
     */
    public static function logQuery($query, $executionTime, $parameters = []) {
        if (!self::$debugMode) {
            return;
        }

        self::debug('Database Query', [
            'query' => $query,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'parameters' => $parameters
        ]);
    }

    /**
     * Log HTTP request
     */
    public static function logRequest($method, $url, $headers = [], $body = '') {
        if (!self::$debugMode) {
            return;
        }

        self::info('HTTP Request', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body_size' => strlen($body)
        ]);
    }

    /**
     * Log HTTP response
     */
    public static function logResponse($statusCode, $headers = [], $body = '') {
        if (!self::$debugMode) {
            return;
        }

        self::info('HTTP Response', [
            'status_code' => $statusCode,
            'headers' => $headers,
            'body_size' => strlen($body)
        ]);
    }

    /**
     * Mengatur custom error handler
     */
    public static function setErrorHandler() {
        if (!self::$isInitialized) {
            self::init();
        }

        if (!self::$debugMode) {
            return;
        }

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError($severity, $message, $file, $line) {
        if (!self::$debugMode) {
            return false;
        }
        
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ];
        
        // Only include trace for fatal errors
        $includeTrace = in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);
        self::error("PHP Error: {$message}", $context, $includeTrace);
        
        // Only throw exception for fatal errors
        if ($includeTrace) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        
        // Return true to indicate the error was handled and prevent PHP's default error handler
        return true;
    }

    public static function handleException($exception) {
        if (!self::$debugMode) {
            return;
        }
        
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'exception_class' => get_class($exception)
        ];
        
        // Log exception without stack trace by default
        self::critical("Uncaught Exception: {$exception->getMessage()}", $context, false);
        
        // Display user-friendly error page
        self::displayErrorPage($exception);
    }

    /**
     * Display error page to user
     */
    private static function displayErrorPage($exception) {
        // Set HTTP status code
        http_response_code(500);
        
        $isDebugMode = isset($_ENV['APP_DEBUG']) ? (bool)$_ENV['APP_DEBUG'] : false;
        
        try {
            // Try to use the errorMessage helper
            if (class_exists('App\System\Helpers\errorMessage')) {
                $errorHelper = new \App\System\Helpers\errorMessage();
                
                if ($isDebugMode) {
                    // Developer mode - show detailed error
                    $templateData = [
                        'code' => 500,
                        'message' => '<b>Exception:</b> ' . htmlspecialchars($exception->getMessage()),
                        'controllers' => 'File: ' . $exception->getFile() . ' on line ' . $exception->getLine(),
                        'title' => 'NexaUI Framework Exception',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'url' => $_SERVER['REQUEST_URI'] ?? '/'
                    ];
                    //$errorHelper->renderErrorPage('112', [], $templateData);
                } else {
                    // Production mode - show generic error
                    //$errorHelper->renderErrorPage(500);
                }
                exit;
            }
        } catch (\Exception $e) {
            // Fallback if errorMessage helper fails
        }
        
        // Fallback error display
        self::displayFallbackErrorPage($exception, $isDebugMode);
        exit;
    }

    /**
     * Display fallback error page
     */
    private static function displayFallbackErrorPage($exception, $isDebugMode) {
        if ($isDebugMode) {
            // Developer mode - detailed error
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Exception Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-title { color: #d32f2f; font-size: 24px; margin-bottom: 20px; }
        .error-message { background: #ffebee; padding: 15px; border-left: 4px solid #d32f2f; margin: 15px 0; }
        .error-details { background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .error-file { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">🚨 Exception Error</h1>
        <div class="error-message">
            <strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '
        </div>
        <div class="error-details">
            <div class="error-file">
                <strong>File:</strong> ' . $exception->getFile() . '<br>
                <strong>Line:</strong> ' . $exception->getLine() . '<br>
                <strong>Type:</strong> ' . get_class($exception) . '
            </div>
        </div>
        <p><a href="javascript:history.back()">← Go Back</a></p>
    </div>
</body>
</html>';
        } else {
            // Production mode - generic error
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin: 40px; background: #f5f5f5; }
        .error-container { background: white; padding: 50px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .error-title { color: #d32f2f; font-size: 28px; margin-bottom: 20px; }
        .error-message { color: #666; font-size: 16px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">⚠️ Server Error</h1>
        <div class="error-message">
            Maaf, terjadi kesalahan pada server.<br>
            Silakan coba lagi nanti atau hubungi administrator.
        </div>
        <p><a href="/">← Kembali ke Beranda</a></p>
    </div>
</body>
</html>';
        }
    }

    /**
     * Enable or disable stack trace inclusion
     */
    public static function setIncludeStackTrace($include = true) {
        self::$includeStackTrace = $include;
    }

    /**
     * Get stack trace inclusion status
     */
    public static function isStackTraceEnabled() {
        return self::$includeStackTrace;
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return boolean
     */
    public static function isDebugMode() {
        if (!self::$isInitialized) {
            self::init();
        }
        return self::$debugMode;
    }

    /**
     * Get current context
     */
    public static function getContext() {
        return self::$context;
    }

    /**
     * Add context data
     */
    public static function addContext($key, $value) {
        self::$context[$key] = $value;
    }

    /**
     * Get log statistics
     */
    public static function getLogStats() {
        if (!self::$debugMode || !file_exists(self::$errorLogPath)) {
            return null;
        }

        return [
            'file_path' => self::$errorLogPath,
            'file_size' => filesize(self::$errorLogPath),
            'max_size' => self::$maxLogSize,
            'created' => date('Y-m-d H:i:s', filectime(self::$errorLogPath)),
            'modified' => date('Y-m-d H:i:s', filemtime(self::$errorLogPath))
        ];
    }

    /**
     * Configure smart logging settings (disabled for performance)
     */
    public static function configureSmartLogging($settings = []) {
        // Disabled for performance
        return;
    }

    /**
     * Enable or disable smart logging (disabled for performance)
     */
    public static function setSmartLogging($enabled = true) {
        // Disabled for performance
        return;
    }

    /**
     * Add ignore pattern for smart logging (disabled for performance)
     */
    public static function addIgnorePattern($pattern) {
        // Disabled for performance
        return;
    }

    /**
     * Get smart logging statistics (performance mode)
     */
    public static function getSmartLoggingStats() {
        return [
            'performance_mode' => true,
            'cache_enabled' => false,
            'smart_features_enabled' => false,
            'message' => 'Performance mode - all smart features disabled for speed'
        ];
    }

    /**
     * Clear smart logging cache (no-op since cache is disabled)
     */
    public static function clearSmartLoggingCache() {
        // Cache is disabled, nothing to clear
        return true;
    }

    private static function getLogPath() {
        // Normalize path separators untuk Windows
        return str_replace('/', DIRECTORY_SEPARATOR, dirname(__DIR__) . '/log');
    }

    /**
     * Ultra-fast skip check for performance (only critical patterns)
     */
    private static function shouldSkipLogging($message) {
        // Only check most critical patterns for performance
        static $criticalPatterns = [
            'NexaVisitors Debug',
            'Total visitors:',
            'Last 30min:',
            '%7B', '%7D'
        ];
        
        foreach ($criticalPatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }





    /**
     * Write JSON log entry (lightweight version)
     */
    private static function writeJsonLog($logData) {
        if (!self::$debugMode || !isset(self::$errorLogJsonPath)) {
            return;
        }

        try {
            // Append mode for better performance - just add new entry
            $jsonEntry = json_encode($logData, JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents(self::$errorLogJsonPath, $jsonEntry, FILE_APPEND | LOCK_EX);
            
        } catch (\Exception $e) {
            // Silently ignore JSON errors to maintain performance
            // error_log("[JSON LOG ERROR] " . $e->getMessage(), 3, self::$errorLogPath);
        }
    }

    /**
     * Get JSON log entries for UI
     * 
     * @param string|null $date Date in Y-m-d format, defaults to today
     * @param int $limit Maximum number of entries to return
     * @param string|null $level Filter by log level (INFO, DEBUG, WARNING, ERROR, CRITICAL)
     * @return array
     */
    public static function getJsonLogEntries($date = null, $limit = 100, $level = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $logFile = self::getLogPath() . "/error_{$date}.json";
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        try {
            $jsonLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logEntries = [];
            
            foreach ($jsonLines as $line) {
                $entry = json_decode($line, true);
                if ($entry && is_array($entry)) {
                    $logEntries[] = $entry;
                }
            }
            
            if (empty($logEntries)) {
                return [];
            }
            
            // Filter by level if specified
            if ($level) {
                $logEntries = array_filter($logEntries, function($entry) use ($level) {
                    return isset($entry['level']) && $entry['level'] === $level;
                });
            }
            
            // Sort by timestamp (newest first)
            usort($logEntries, function($a, $b) {
                $timeA = $a['unix_timestamp'] ?? 0;
                $timeB = $b['unix_timestamp'] ?? 0;
                return $timeB - $timeA;
            });
            
            // Apply limit
            return array_slice($logEntries, 0, $limit);
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get log statistics for UI dashboard
     * 
     * @param string|null $date Date in Y-m-d format, defaults to today
     * @return array
     */
    public static function getLogStatistics($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $logFile = self::getLogPath() . "/error_{$date}.json";
        
        if (!file_exists($logFile)) {
            return [
                'total' => 0,
                'by_level' => [],
                'last_entry' => null,
                'file_size' => 0
            ];
        }
        
        try {
            $jsonLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logEntries = [];
            
            foreach ($jsonLines as $line) {
                $entry = json_decode($line, true);
                if ($entry && is_array($entry)) {
                    $logEntries[] = $entry;
                }
            }
            
            if (empty($logEntries)) {
                return [
                    'total' => 0,
                    'by_level' => [],
                    'last_entry' => null,
                    'file_size' => filesize($logFile)
                ];
            }
            
            $stats = [
                'total' => count($logEntries),
                'by_level' => [],
                'last_entry' => null,
                'file_size' => filesize($logFile)
            ];
            
            // Count by level
            foreach ($logEntries as $entry) {
                $level = $entry['level'] ?? 'UNKNOWN';
                $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            }
            
            // Get last entry
            if (!empty($logEntries)) {
                $lastEntry = end($logEntries);
                $stats['last_entry'] = [
                    'timestamp' => $lastEntry['timestamp'] ?? null,
                    'level' => $lastEntry['level'] ?? null,
                    'message' => $lastEntry['message'] ?? null
                ];
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'by_level' => [],
                'last_entry' => null,
                'file_size' => file_exists($logFile) ? filesize($logFile) : 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available log dates
     * 
     * @return array Array of dates with log files
     */
    public static function getAvailableLogDates() {
        $logDir = self::getLogPath();
        $jsonFiles = glob($logDir . '/error_*.json');
        
        $dates = [];
        foreach ($jsonFiles as $file) {
            if (preg_match('/error_(\d{4}-\d{2}-\d{2})\.json$/', basename($file), $matches)) {
                $dates[] = $matches[1];
            }
        }
        
        // Sort dates (newest first)
        rsort($dates);
        
        return $dates;
    }

    /**
     * Search log entries
     * 
     * @param string $query Search query
     * @param string|null $date Date in Y-m-d format, defaults to today
     * @param int $limit Maximum number of results
     * @return array
     */
    public static function searchLogEntries($query, $date = null, $limit = 50) {
        $entries = self::getJsonLogEntries($date, 1000); // Get more entries for searching
        
        if (empty($query)) {
            return array_slice($entries, 0, $limit);
        }
        
        $results = [];
        $query = strtolower($query);
        
        foreach ($entries as $entry) {
            $searchIn = [
                $entry['message'] ?? '',
                $entry['level'] ?? '',
                json_encode($entry['context'] ?? [])
            ];
            
            $found = false;
            foreach ($searchIn as $text) {
                if (strpos(strtolower($text), $query) !== false) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $results[] = $entry;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }


}

// Global function overrides - harus berada di luar class
if (!function_exists('nexaErrorLog')) {
    /**
     * Global override untuk error_log() function
     * Menangkap semua error_log calls dan masukkan ke JSON juga
     */
    function nexaErrorLog($message, $message_type = 3, $destination = null, $extra_headers = null) {
        // Gunakan NexaDebug::errorLog untuk enhanced logging
        return \App\System\Helpers\NexaDebug::errorLog($message, $message_type, $destination, $extra_headers);
    }
}

// Override native error_log function jika memungkinkan
if (!function_exists('original_error_log')) {
    // Backup function name untuk original error_log
    if (function_exists('error_log')) {
        // Tidak bisa rename function, jadi kita akan intercept di level lain
    }
}

// Auto-initialize akan dilakukan saat class pertama kali digunakan
// \App\System\Helpers\NexaDebug::init();