<?php
declare(strict_types=1);

namespace App\System\Helpers;

use App\System\Helpers\NexaSession;
use App\System\Helpers\NexaValidation;
use App\System\Storage\NexaFile;

/**
 * NexaAjax - AJAX Request Handler
 * Handles AJAX requests, file uploads, and JSON responses
 * 
 * Features:
 * - AJAX request validation
 * - JSON response formatting
 * - File upload via AJAX
 * - CSRF protection
 * - Error handling
 * - Progress tracking
 * 
 * Usage Examples:
 * 
 * // Basic AJAX response
 * NexaAjax::success('Data saved successfully', ['id' => 123]);
 * NexaAjax::error('Validation failed', ['field' => 'email']);
 * 
 * // File upload via AJAX
 * $ajax = new NexaAjax();
 * $result = $ajax->handleFileUpload($_FILES['file'], $config);
 * 
 * // Check if request is AJAX
 * if (NexaAjax::isAjax()) {
 *     // Handle AJAX request
 * }
 */
class NexaAjax
{
    private array $response = [];
    private int $httpCode = 200;
    private array $headers = [];

    public function __construct()
    {
        // Set default headers for AJAX responses
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $this->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
    }

    /**
     * Check if current request is AJAX
     * 
     * @return bool
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request method is specific type
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @return bool
     */
    public static function isMethod(string $method): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($method);
    }

    /**
     * Get request input data (works with JSON and form data)
     * 
     * @param string|null $key Specific key to get
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function input(?string $key = null, mixed $default = null): mixed
    {
        $input = [];
        
        // Handle JSON input
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $input = json_decode($json, true) ?? [];
        } else {
            // Handle form data
            $input = array_merge($_GET, $_POST);
        }
        
        if ($key !== null) {
            return $input[$key] ?? $default;
        }
        
        return $input;
    }

    /**
     * Send success response
     * 
     * @param string $message Success message
     * @param mixed $data Additional data
     * @param int $code HTTP status code
     * @return void
     */
    public static function success(string $message = 'Success', mixed $data = null, int $code = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => time(),
            'request_id' => uniqid()
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::sendResponse($response, $code);
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param mixed $errors Error details
     * @param int $code HTTP status code
     * @return void
     */
    public static function error(string $message = 'Error', mixed $errors = null, int $code = 400): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => time(),
            'request_id' => uniqid()
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        self::sendResponse($response, $code);
    }

    /**
     * Send validation error response
     * 
     * @param array $validationErrors Validation errors
     * @param string $message Main error message
     * @return void
     */
    public static function validationError(array $validationErrors, string $message = 'Validation failed'): void
    {
        self::error($message, $validationErrors, 422);
    }

    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, null, 401);
    }

    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, null, 403);
    }

    /**
     * Send not found response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, null, 404);
    }

    /**
     * Send response with custom data
     * 
     * @param array $data Response data
     * @param int $code HTTP status code
     * @return void
     */
    public static function sendResponse(array $data, int $code = 200): void
    {
        // Set HTTP response code
        http_response_code($code);
        
        // Set headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        // Send response
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle file upload via AJAX
     * 
     * @param array $fileData $_FILES data
     * @param array $config Upload configuration
     * @return array Upload result
     */
    public function handleFileUpload(array $fileData, array $config = []): array
    {
        try {
            $nexaFile = new NexaFile();
            
            // Default configuration
            $defaultConfig = [
                'maxSize' => '10MB',
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
                'thumbnail' => [], // No thumbnails by default
                'thumbnailCropMode' => 'crop'
            ];
            
            $config = array_merge($defaultConfig, $config);
            
            // Upload file
            $result = $nexaFile->autoUpload($fileData, $config);
            
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle multiple file uploads via AJAX
     * 
     * @param array $filesData Multiple $_FILES data
     * @param array $config Upload configuration
     * @return array Upload results
     */
    public function handleMultipleFileUpload(array $filesData, array $config = []): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($filesData as $index => $fileData) {
            $result = $this->handleFileUpload($fileData, $config);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
            
            $results[] = $result;
        }
        
        return [
            'success' => $errorCount === 0,
            'message' => "Uploaded {$successCount} files successfully" . 
                        ($errorCount > 0 ? ", {$errorCount} failed" : ""),
            'data' => [
                'results' => $results,
                'summary' => [
                    'total' => count($filesData),
                    'success' => $successCount,
                    'failed' => $errorCount
                ]
            ]
        ];
    }

    /**
     * Validate CSRF token for AJAX requests
     * 
     * @param string $token CSRF token from request
     * @return bool
     */
    public static function validateCsrfToken(string $token): bool
    {
        try {
            // Ensure session is started
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $session = NexaSession::getInstance();
            return $session->validateCsrfToken($token);
        } catch (\Exception $e) {
            error_log("CSRF validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get CSRF token for AJAX requests
     * 
     * @return string
     */
    public static function getCsrfToken(): string
    {
        try {
            // Ensure session is started
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $session = NexaSession::getInstance();
            return $session->getCsrfToken();
        } catch (\Exception $e) {
            error_log("CSRF token generation error: " . $e->getMessage());
            // Return a fallback token
            return hash('sha256', session_id() . time());
        }
    }

    /**
     * Set response header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return void
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Set HTTP response code
     * 
     * @param int $code HTTP status code
     * @return void
     */
    public function setHttpCode(int $code): void
    {
        $this->httpCode = $code;
    }

    /**
     * Add data to response
     * 
     * @param string $key Data key
     * @param mixed $value Data value
     * @return void
     */
    public function addData(string $key, mixed $value): void
    {
        $this->response[$key] = $value;
    }

    /**
     * Send the prepared response
     * 
     * @return void
     */
    public function send(): void
    {
        // Set HTTP response code
        http_response_code($this->httpCode);
        
        // Set headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Send response
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle AJAX request with automatic routing
     * 
     * @param array $routes Array of routes [action => callback]
     * @return void
     */
    public static function handleRequest(array $routes): void
    {
        if (!self::isAjax()) {
            self::error('AJAX request required', null, 400);
            return;
        }
        
        $action = self::input('action');
        
        if (!$action) {
            self::error('Action parameter required', null, 400);
            return;
        }
        
        if (!isset($routes[$action])) {
            self::error('Invalid action', null, 404);
            return;
        }
        
        $callback = $routes[$action];
        
        if (is_callable($callback)) {
            try {
                $result = call_user_func($callback);
                
                if (is_array($result)) {
                    self::sendResponse($result);
                } else {
                    self::success('Action completed', $result);
                }
            } catch (\Exception $e) {
                self::error('Action failed: ' . $e->getMessage());
            }
        } else {
            self::error('Invalid callback for action', null, 500);
        }
    }

    /**
     * Create progress response for long-running operations
     * 
     * @param int $current Current progress
     * @param int $total Total items
     * @param string $message Progress message
     * @param mixed $data Additional data
     * @return void
     */
    public static function progress(int $current, int $total, string $message = '', mixed $data = null): void
    {
        $percentage = $total > 0 ? round(($current / $total) * 100, 2) : 0;
        
        $response = [
            'success' => true,
            'progress' => [
                'current' => $current,
                'total' => $total,
                'percentage' => $percentage,
                'message' => $message
            ],
            'timestamp' => time()
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::sendResponse($response);
    }
}
