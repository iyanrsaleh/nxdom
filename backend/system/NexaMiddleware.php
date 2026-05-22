<?php
declare(strict_types=1);

namespace App\System;

use Exception;

/**
 * NexaMiddleware - Base Middleware untuk sistem MVC
 * 
 * Features:
 * - Authentication middleware
 * - CORS middleware
 * - Rate limiting
 * - Request validation
 */
abstract class NexaMiddleware
{
    /**
     * Handle middleware logic
     * Return false to block request, true to continue
     */
    abstract public function handle(): bool;
    
    /**
     * Get current user from session
     */
    protected function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }
    
    /**
     * Check if user has specific role
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        return isset($user['role']) && $user['role'] === $role;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    protected function hasAnyRole(array $roles): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        return isset($user['role']) && in_array($user['role'], $roles);
    }
    
    /**
     * Redirect to login page
     */
    protected function redirectToLogin(): void
    {
        header('Location: /login');
        exit;
    }
    
    /**
     * Return 403 Forbidden
     */
    protected function forbidden(): void
    {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    /**
     * Return 401 Unauthorized
     */
    protected function unauthorized(): void
    {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

/**
 * Authentication Middleware
 */
class NexaAuthMiddleware extends NexaMiddleware
{
    public function handle(): bool
    {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
            return false;
        }
        
        return true;
    }
}

/**
 * Admin Middleware
 */
class NexaAdminMiddleware extends NexaMiddleware
{
    public function handle(): bool
    {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
            return false;
        }
        
        if (!$this->hasRole('admin')) {
            $this->forbidden();
            return false;
        }
        
        return true;
    }
}

/**
 * CORS Middleware
 */
class NexaCorsMiddleware extends NexaMiddleware
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With']
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }
    
    public function handle(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $this->allowedOrigins) || in_array($origin, $this->allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Allow-Credentials: true');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        return true;
    }
}

/**
 * Rate Limiting Middleware
 */
class NexaRateLimitMiddleware extends NexaMiddleware
{
    private int $maxRequests;
    private int $timeWindow;
    private string $storageFile;
    
    public function __construct(int $maxRequests = 100, int $timeWindow = 3600)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->storageFile = sys_get_temp_dir() . '/nexa_rate_limit.json';
    }
    
    public function handle(): bool
    {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $currentTime = time();
        
        // Load existing data
        $data = [];
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true) ?: [];
        }
        
        // Clean old entries
        foreach ($data as $ip => $requests) {
            $data[$ip] = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < $this->timeWindow;
            });
        }
        
        // Check current IP
        if (!isset($data[$clientIP])) {
            $data[$clientIP] = [];
        }
        
        if (count($data[$clientIP]) >= $this->maxRequests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too Many Requests']);
            return false;
        }
        
        // Add current request
        $data[$clientIP][] = $currentTime;
        
        // Save data
        file_put_contents($this->storageFile, json_encode($data));
        
        return true;
    }
}

/**
 * Request Validation Middleware
 */
class NexaValidationMiddleware extends NexaMiddleware
{
    private array $rules;
    
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }
    
    public function handle(): bool
    {
        $errors = [];
        
        foreach ($this->rules as $field => $rule) {
            $value = $_REQUEST[$field] ?? null;
            
            if ($this->isRequired($rule) && empty($value)) {
                $errors[$field] = "Field $field is required";
                continue;
            }
            
            if (!empty($value)) {
                if ($this->hasMinLength($rule) && strlen($value) < $this->getMinLength($rule)) {
                    $errors[$field] = "Field $field must be at least " . $this->getMinLength($rule) . " characters";
                }
                
                if ($this->hasMaxLength($rule) && strlen($value) > $this->getMaxLength($rule)) {
                    $errors[$field] = "Field $field must not exceed " . $this->getMaxLength($rule) . " characters";
                }
                
                if ($this->isEmail($rule) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "Field $field must be a valid email";
                }
                
                if ($this->isNumeric($rule) && !is_numeric($value)) {
                    $errors[$field] = "Field $field must be numeric";
                }
            }
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            return false;
        }
        
        return true;
    }
    
    private function isRequired(string $rule): bool
    {
        return strpos($rule, 'required') !== false;
    }
    
    private function hasMinLength(string $rule): bool
    {
        return strpos($rule, 'min:') !== false;
    }
    
    private function getMinLength(string $rule): int
    {
        preg_match('/min:(\d+)/', $rule, $matches);
        return (int) ($matches[1] ?? 0);
    }
    
    private function hasMaxLength(string $rule): bool
    {
        return strpos($rule, 'max:') !== false;
    }
    
    private function getMaxLength(string $rule): int
    {
        preg_match('/max:(\d+)/', $rule, $matches);
        return (int) ($matches[1] ?? 0);
    }
    
    private function isEmail(string $rule): bool
    {
        return strpos($rule, 'email') !== false;
    }
    
    private function isNumeric(string $rule): bool
    {
        return strpos($rule, 'numeric') !== false;
    }
} 