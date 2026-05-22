<?php
declare(strict_types=1);

namespace App\System\Helpers;

/**
 * Session Management Class
 * Handles session operations, CSRF protection, flash messages, and timeout management
 * 
 * Features added from NexaSession___.php:
 * - Static initialization with Config integration
 * - Activity-based timeout tracking with last_activity timestamp
 * - Automatic session timeout checking with redirect capability
 * - Remaining time calculation
 * - Static compatibility methods for legacy code
 * - Enhanced session cookie configuration
 * - Singleton pattern support
 * 
 * Usage Examples:
 * // Static initialization (recommended for global use)
 * Session::init();
 * Session::initialize(); // init + start combined
 * 
 * // Check timeout with redirect
 * Session::checkTimeout('/login'); 
 * Session::requireValidSession('/login'); // alias for checkTimeout
 * 
 * // Instance-based usage (for object-oriented code)
 * $session = Session::getInstance();
 * $session->start();
 * 
 * // Activity tracking
 * $remaining = Session::getRemainingTime();
 * $lastActivity = Session::getLastActivity();
 * $isActive = Session::isActive();
 */
class NexaSession
{
    private bool $started = false;
    private string $csrfTokenKey = '_csrf_token';
    private string $flashKey = '_flash_messages';
    private string $userKey = '_user_data';
    private static ?self $instance = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize session with configuration (static method for compatibility)
     * 
     * @return void
     */
    public static function init(): void
    {
        // Only initialize if session is not started yet
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        
        // Load configuration from environment variables
        $timeout = (int)($_ENV['SESSION_LIFETIME'] ?? 86400); 
        if ($timeout < 3600) { // If less than 1 hour, assume it's in minutes
            $timeout = $timeout * 60;
        }
        $sessionName = $_ENV['APP_NAME'] ?? 'NEXA_SESSION';
        $sessionName = strtoupper(str_replace(' ', '_', $sessionName)) . '_SESSION';
        $secure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $httponly = filter_var($_ENV['SESSION_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN);
        
        // Set session configuration (only if session not started)
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string)$timeout);
            ini_set('session.cookie_lifetime', (string)$timeout);
            ini_set('session.cookie_httponly', $httponly ? '1' : '0');
            ini_set('session.cookie_secure', $secure ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            
            // Set session cookie parameters
            session_set_cookie_params([
                'lifetime' => $timeout,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax'
            ]);
            
            // Set session name
            session_name($sessionName);
        }
    }

    /**
     * Check if session has timed out (static method for compatibility)
     * 
     * @return bool
     */
    public static function isExpired(): bool
    {
        $timeout = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
        if ($timeout < 3600) { // If less than 1 hour, assume it's in minutes
            $timeout = $timeout * 60;
        }
        
        if (isset($_SESSION['last_activity'])) {
            return (time() - $_SESSION['last_activity']) > $timeout;
        }
        
        return false;
    }

    /**
     * Update last activity time (static method for compatibility)
     * 
     * @return void
     */
    public static function updateActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check session timeout and handle expired sessions (static method for compatibility)
     * 
     * @param string $redirectUrl URL to redirect if session expired
     * @return bool True if session is valid, false if expired
     */
    public static function checkTimeout(string $redirectUrl = '/signin'): bool
    {
        // Quick check: jika session belum dimulai, tidak perlu check timeout
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        
        if (self::isExpired()) {
            self::destroyStatic();
            
            // Redirect to login page
            if (!headers_sent()) {
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            return false;
        }
        
        // Update activity on every check
        self::updateActivity();
        
        return true;
    }

    /**
     * Get session timeout in seconds (static method for compatibility)
     * 
     * @return int
     */
    public static function getTimeout(): int
    {
        $timeout = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
        if ($timeout < 3600) { // If less than 1 hour, assume it's in minutes
            $timeout = $timeout * 60;
        }
        return $timeout;
    }

    /**
     * Get remaining session time in seconds (static method for compatibility)
     * 
     * @return int
     */
    public static function getRemainingTime(): int
    {
        if (!isset($_SESSION['last_activity'])) {
            return self::getTimeout();
        }
        
        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = self::getTimeout() - $elapsed;
        
        return max(0, $remaining);
    }

    /**
     * Static destroy method for compatibility
     * 
     * @return void
     */
    public static function destroyStatic(): void
    {
        session_unset();
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }

    /**
     * Start session if not already started
     */
    public function start(): bool
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        // Configure session settings
        $this->configureSession();
        
        $this->started = session_start();
        
        if ($this->started) {
            $this->generateCsrfToken();
            $this->processFlashMessages();
            // Update activity when session starts
            self::updateActivity();
        }
        
        return $this->started;
    }

    /**
     * Configure session settings for security
     */
    private function configureSession(): void
    {
        // Use environment variables for session configuration
        $timeout = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
        if ($timeout < 3600) { // If less than 1 hour, assume it's in minutes
            $timeout = $timeout * 60;
        }

        $sessionName = $_ENV['APP_NAME'] ?? 'NEXAUI_SESSION';
        $sessionName = strtoupper(str_replace(' ', '_', $sessionName)) . '_SESSION';
        
        // ✅ VALIDATE session name - remove invalid characters
        $sessionName = preg_replace('/[^a-zA-Z0-9_]/', '', $sessionName);
        if (empty($sessionName)) {
            $sessionName = 'NEXAUI_SESSION';
        }
        
        $secure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $httponly = filter_var($_ENV['SESSION_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN);
        // Set secure session configuration
        ini_set('session.cookie_httponly', $httponly ? '1' : '0');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string)$timeout);
        ini_set('session.cookie_lifetime', (string)$timeout);
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.use_strict_mode', '1');
        // Set session name
        session_name($sessionName);
        
        // Set session cache settings
        session_cache_limiter('nocache');
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => $timeout,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Destroy session
     */
    public function destroy(): bool
    {
        if (!$this->started) {
            return true;
        }

        $_SESSION = [];
        
        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], 
                      $params['domain'], $params['secure'], $params['httponly']);
        }
        
        $result = session_destroy();
        $this->started = false;
        
        return $result;
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(): bool
    {
        if (!$this->started) {
            return false;
        }

        return session_regenerate_id(true);
    }

    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
        // Update activity when setting session data
        self::updateActivity();
    }

    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        // Update activity when accessing session data
        self::updateActivity();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set($this->csrfTokenKey, $token);
        return $token;
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken(): string
    {
        $token = $this->get($this->csrfTokenKey);
        
        if (!$token) {
            $token = $this->generateCsrfToken();
        }
        
        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        $sessionToken = $this->get($this->csrfTokenKey);
        return $sessionToken && hash_equals($sessionToken, $token);
    }

    /**
     * Set flash message
     */
    public function setFlash(string $type, mixed $message): void
    {
        $flash = $this->get($this->flashKey, []);
        
        if (!isset($flash[$type])) {
            $flash[$type] = [];
        }
        
        // If message is already an array, store it directly
        // Otherwise, add it to the array of messages
        if (is_array($message)) {
            $flash[$type] = $message;
        } else {
            $flash[$type][] = $message;
        }
        
        $this->set($this->flashKey, $flash);
    }

    /**
     * Get flash messages
     */
    public function getFlash(string $type = null): array
    {
        $flash = $this->get($this->flashKey, []);
        
        if ($type !== null) {
            return $flash[$type] ?? [];
        }
        
        return $flash;
    }

    /**
     * Get and clear flash messages
     */
    public function getFlashAndClear(string $type = null): array
    {
        $messages = $this->getFlash($type);
        
        if ($type !== null) {
            $flash = $this->get($this->flashKey, []);
            unset($flash[$type]);
            $this->set($this->flashKey, $flash);
        } else {
            $this->remove($this->flashKey);
        }
        
        return $messages;
    }

    /**
     * Process flash messages for template
     */
    private function processFlashMessages(): void
    {
        $flash = $this->getFlash();
        
        if (!empty($flash)) {
            // Convert flash messages to template format
            $templateFlash = [];
            
            foreach ($flash as $type => $messages) {
                $templateFlash["{$type}_messages"] = array_map(function($message) {
                    return ['message' => $message];
                }, $messages);
            }
            
            // Set template variables
            if (class_exists('App\System\Application')) {
                $app = \App\System\Application::getInstance();
                if ($app) {
                    $template = $app->getTemplate();
                    foreach ($templateFlash as $key => $value) {
                        $template->assign_block_vars($key, $value);
                    }
                    $template->assign_var('flash_messages', true);
                }
            }
            
            // Clear flash messages after processing
            $this->remove($this->flashKey);
        }
    }

    /**
     * Set user data
     */
    public function setUser(array $userData): void
    {
        $existingUser = $this->get($this->userKey);
        $this->set($this->userKey, $userData);
        
        // Only regenerate session ID for new logins, not updates
        if (!$existingUser) {
            $this->regenerate(); // Regenerate session ID for security on new login
        }
    }

    /**
     * Get page item data
     * @return array|null Array containing page information or null if not set
     */
    public function getPageItem(): ?array
    {
        $pageData = $this->get('page-link');
        
        // If it's a string (legacy format), convert to array
        if (is_string($pageData)) {
            return [
                'url' => $pageData,
                'page' => $this->extractPageNumber($pageData)
            ];
        }
        
        // If it's already an array, return as is
        if (is_array($pageData)) {
            return $pageData;
        }
        
        return null;
    }
    
    /**
     * Extract page number from URL string
     * @param string $url
     * @return int
     */
    private function extractPageNumber(string $url): int
    {
        // Extract number from URL like "?page/3"
        if (preg_match('/\?page\/(\d+)/', $url, $matches)) {
            return (int)$matches[1];
        }
        return 1;
    }

    /**
     * Get user data
     */
    public function getUser(): ?array
    {
        return $this->get($this->userKey);
    }

    /**
     * Get specific user field
     * 
     * @param string $field Field name to get
     * @param mixed $default Default value if field not found
     * @return mixed
     */
    public function getUserField(string $field, mixed $default = null): mixed
    {
        $user = $this->getUser();
        return $user[$field] ?? $default;
    }

    /**
     * Get user avatar URL
     * 
     * @return string
     */
    public function getUserAvatar(): string
    {
        // First try to get from user data
        $user = $this->getUser();
        if ($user && isset($user['avatar'])) {
            $avatar = $user['avatar'];
        } else {
            // Fallback to direct session key
            $avatar = $this->get('avatar', 'images/pria.png');
        }
        
        // Handle legacy case where avatar might be stored as array
        if (is_array($avatar)) {
            return $avatar['path'] ?? 'images/pria.png';
        }
        
        // Return avatar or default
        return $avatar ?: 'images/pria.png';
    }

    /**
     * Get user slug name
     * 
     * @return string
     */
    public function getUserSlug(): string
    {
        return $this->getUserField('user_name', 'user');
    }

    /**
     * Get user real name
     * 
     * @return string
     */
    public function getUserRealName(): string
    {
        return $this->getUserField('user_real_name', 'User');
    }

    /**
     * Get user email
     * 
     * @return string
     */
    public function getUserEmail(): string
    {
        return $this->getUserField('email', '');
    }

    /**
     * Get user status/role
     * 
     * @return string
     */
    public function getUserStatus(): string
    {
        return $this->getUserField('status', 'user');
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return $this->has($this->userKey);
    }

    /**
     * Check if user is logged in (static method for compatibility)
     * 
     * @return bool
     */
    public static function isLoggedInStatic(): bool
    {
        return isset($_SESSION['user_id']) || isset($_SESSION['_user_data']);
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $this->remove($this->userKey);
        $this->regenerate();
    }

    /**
     * Get user ID
     */
    public function getUserId(): ?int
    {
        $user = $this->getUser();
        return $user['user_id'] ?? null;
    }

    /**
     * Get user role
     */
    public function getUserRole(): ?string
    {
        $user = $this->getUser();
        return $user['role'] ?? null;
    }

    /**
     * Check if user has role
     */
    public function hasRole(string $role): bool
    {
        return $this->getUserRole() === $role;
    }

    /**
     * Set remember me token
     */
    public function setRememberToken(string $token, int $expiry = 86400): void
    {
        $cookieName = 'remember_token';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie($cookieName, $token, time() + $expiry, '/', '', $secure, true);
    }

    /**
     * Get remember me token
     */
    public function getRememberToken(): ?string
    {
        return $_COOKIE['remember_token'] ?? null;
    }

    /**
     * Clear remember me token
     */
    public function clearRememberToken(): void
    {
        $cookieName = 'remember_token';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie($cookieName, '', time() - 3600, '/', '', $secure, true);
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    /**
     * Check if session is started
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Set session timeout
     */
    public function setTimeout(int $seconds): void
    {
        // Update the activity timestamp instead of using separate timeout
        self::updateActivity();
        // Also update config if needed (this would require Config::set implementation)
        // Config::set('session.timeout', $seconds);
    }

    /**
     * Check if session has timed out
     */
    public function hasTimedOut(): bool
    {
        // Use the new activity-based timeout system
        return self::isExpired();
    }

    /**
     * Extend session timeout
     */
    public function extendTimeout(int $seconds): void
    {
        // Simply update activity to extend the session
        self::updateActivity();
    }

    /**
     * Ensure session is started
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
        
        // Auto-check timeout on every access
        if (self::isExpired()) {
            $this->destroy();
            throw new \RuntimeException('Session has expired');
        }
    }

    /**
     * Get current activity timestamp
     * 
     * @return int|null
     */
    public static function getLastActivity(): ?int
    {
        return $_SESSION['last_activity'] ?? null;
    }

    /**
     * Check if session is active (not expired)
     * 
     * @return bool
     */
    public static function isActive(): bool
    {
        return !self::isExpired();
    }

    /**
     * Get visitor session ID
     * Returns the current session ID which can be used to track visitors
     * 
     * @return string
     */
    public function getVisitorId(): string 
    {
        $user = $this->getUser();
        return (string)($user['user_id'] ?? $this->getId());
    }

    /**
     * Get visitor key - persistent identifier for tracking visitors
     * This key remains constant throughout the session, regardless of login status
     * Used for QR code authentication and visitor tracking
     * 
     * @return string
     */
    public function getVisitorKey(): string 
    {
        $this->ensureStarted();
        
        $cookieKey = '_visitor_key_' . session_name();
        
        // First check if visitor key exists in cookie (most persistent)
        $visitorKey = $_COOKIE[$cookieKey] ?? null;
        
        // Validate the cookie value (ensure it's a valid 32-char hex string)
        if ($visitorKey && !preg_match('/^[a-f0-9]{32}$/', $visitorKey)) {
            $visitorKey = null;
        }
        
        // If no valid cookie, check session
        if (!$visitorKey) {
            $visitorKey = $this->get('_visitor_key');
            
            // Validate session value too
            if ($visitorKey && !preg_match('/^[a-f0-9]{32}$/', $visitorKey)) {
                $visitorKey = null;
            }
        }
        
        // If still no valid key, generate a new one
        if (!$visitorKey) {
            // Generate a unique visitor key using more entropy
            $visitorKey = bin2hex(random_bytes(16)); // 32 character hex string
            
            // Store in cookie with longer expiration (30 days) for persistence
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $cookieExpiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            setcookie($cookieKey, $visitorKey, $cookieExpiry, '/', '', $secure, true);
            
            // Also store in session
            $this->set('_visitor_key', $visitorKey);
        } else {
            // If we got the key from cookie but not in session, sync it
            if (!$this->get('_visitor_key')) {
                $this->set('_visitor_key', $visitorKey);
            }
            
            // If we got the key from session but not in cookie, refresh cookie
            if (!isset($_COOKIE[$cookieKey])) {
                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                $cookieExpiry = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie($cookieKey, $visitorKey, $cookieExpiry, '/', '', $secure, true);
            }
        }
        
        return $visitorKey;
    }

    /**
     * Clear and regenerate visitor key
     * Used when QR code needs to be refreshed
     * 
     * @return string New visitor key
     */
    public function regenerateVisitorKey(): string 
    {
        // Remove from session
        $this->remove('_visitor_key');
        
        // Remove from cookie
        $cookieKey = '_visitor_key_' . session_name();
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie($cookieKey, '', time() - 3600, '/', '', $secure, true);
        
        // Generate new key
        return $this->getVisitorKey();
    }

    /**
     * Get session save path
     */
    public function getSavePath(): string
    {
        return session_save_path();
    }

    /**
     * Get session name
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * Set session save handler
     */
    public function setSaveHandler(object $handler): bool
    {
        return session_set_save_handler($handler, true);
    }

    /**
     * Initialize and start session with full configuration
     * Combines init() and start() for convenience
     * 
     * @return bool
     */
    public static function initialize(): bool
    {
        self::init();
        $instance = self::getInstance();
        return $instance->start();
    }

    /**
     * Quick session check and redirect if expired
     * Useful for protected pages
     * 
     * @param string $redirectUrl
     * @return bool
     */
    public static function requireValidSession(string $redirectUrl = '/signin'): bool
    {
        return self::checkTimeout($redirectUrl);
    }

    /**
     * Static method to get visitor key
     * Ensures session is initialized and returns a unique visitor key
     * 
     * @return string
     */
    public static function getUniqueVisitorKey(): string
    {
        $instance = self::getInstance();
        $instance->ensureStarted();
        return $instance->getVisitorKey();
    }
} 