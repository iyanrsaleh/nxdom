<?php
declare(strict_types=1);
namespace App\System\Helpers;

use App\System\Storage\NexaIndexDB;

class NexaAuth {
    private $secretKey = 'tatiye2025';
    private $tokenExpiration = 3600; // Default 1 hour
    private $token = null;
    private $indexDB = null;
    private $tokenStore = null;
    private $sessionStore = null;

    public function __construct($dbPath = null) {
        // Set default path to system/Storage/tabel directory if not specified
        if ($dbPath === null) {
            $projectRoot = dirname(dirname(__DIR__)); // Go up from system/Helpers to dev/
            $dbPath = $projectRoot . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'tabel';
        }
        
        // Initialize NexaIndexDB for token storage
        $this->indexDB = new NexaIndexDB('nexa_auth_tokens', 1, $dbPath);
        
        // Create object stores for tokens and sessions
        $this->tokenStore = $this->indexDB->createObjectStore('tokens', [
            'keyPath' => 'token_id',
            'autoIncrement' => false
        ]);
        
        $this->sessionStore = $this->indexDB->createObjectStore('sessions', [
            'keyPath' => 'session_id',
            'autoIncrement' => false
        ]);
        
        // Create indexes for efficient querying
        $this->tokenStore->createIndex('user_id_idx', 'user_id');
        $this->tokenStore->createIndex('expires_idx', 'expires_at');
        $this->sessionStore->createIndex('user_id_idx', 'user_id');
        
        // Clean expired tokens on initialization
        $this->cleanExpiredTokens();
    }

    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        // Handle case where Apache strips out the Authorization header
        if (!$headers && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        
        // If no authorization header found, check for token in GET or POST
        if (!$headers) {
            if (isset($_GET['token'])) {
                return $_GET['token'];
            }
            if (isset($_POST['token'])) {
                return $_POST['token'];
            }
        }

        return $headers ? str_replace('Bearer ', '', $headers) : null;
    }

    private function parseExpiration($expiration) {
        if (empty($expiration)) {
            return $this->tokenExpiration; // Default 1 hour
        }

        $value = intval(substr($expiration, 0, -1));
        $unit = strtolower(substr($expiration, -1));

        switch ($unit) {
            case 'h': // Hours
                return $value * 3600;
            case 'd': // Days
                return $value * 86400;
            case 'm': // Months (approximate)
                return $value * 2592000;
            case 'y': // Years (approximate)
                return $value * 31536000;
            default:
                return $this->tokenExpiration; // Default 1 hour if invalid format
        }
    }

    public function generateToken($payload) {
        // Extract expiration from payload if exists
        $expiration = isset($payload['expired']) ? $payload['expired'] : '1d';
        $expirationSeconds = $this->parseExpiration($expiration);
        $expiresAt = time() + $expirationSeconds;
        
        // Generate unique token ID (shorter format)
        $tokenId = $this->generateShortTokenId();
        
        // Create MINIMAL payload for token (only essential data)
        $minimalPayload = [
            't' => $tokenId,                     // token_id for IndexDB lookup
            'e' => $expiresAt                    // expiration (for quick validation)
        ];
        
        // Convert to base64
        $data = base64_encode(json_encode($minimalPayload));

        // Generate SHORT signature (only 6 chars)
        $signature = hash_hmac('sha256', $data, $this->secretKey, true);
        $sig = substr(base64_encode($signature), 0, 6); // Only 6 chars
        
        // Combine data and signature - MUCH SHORTER TOKEN
        $token = $data . '.' . $sig;
        
        // Store FULL token data in IndexDB
        $tokenData = [
            'token_id' => $tokenId,
            'user_id' => $payload['user_id'],
            'username' => $payload['username'],
            'role' => $payload['role'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => time(),
            'last_used' => time(),
            'is_active' => true,
            'metadata' => isset($payload['metadata']) ? $payload['metadata'] : []
        ];
        
        $this->tokenStore->put($tokenData, $tokenId);
        
        // Create session record
        $sessionData = [
            'session_id' => $this->generateSessionId(),
            'token_id' => $tokenId,
            'user_id' => $payload['user_id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => time(),
            'last_activity' => time()
        ];
        
        $this->sessionStore->put($sessionData);
        
        return $token;
    }

    public function validateToken() {
        $this->token = $this->getAuthorizationHeader();
        if (!$this->token) {
            return false;
        }

        $parts = explode('.', $this->token);
        if (count($parts) !== 2) {
            return false;
        }

        list($data, $sig) = $parts;

        // Verify signature (now 6 chars)
        $expectedSig = substr(base64_encode(hash_hmac('sha256', $data, $this->secretKey, true)), 0, 6);
        if ($sig !== $expectedSig) {
            return false;
        }

        // Decode minimal payload
        $payload = json_decode(base64_decode($data), true);
        if (!$payload) {
            return false;
        }

        // Check basic expiration
        if (isset($payload['e']) && $payload['e'] < time()) {
            return false;
        }

        // Validate against IndexDB storage
        $tokenId = $payload['t'] ?? null;
        if (!$tokenId) {
            return false;
        }

        $tokenData = $this->tokenStore->get($tokenId);
        if (!$tokenData) {
            return false;
        }

        // Check if token is active and not expired
        if (!$tokenData['is_active'] || $tokenData['expires_at'] < time()) {
            return false;
        }

        // Verify token hash matches
        $currentTokenHash = hash('sha256', $this->token);
        if ($tokenData['token_hash'] !== $currentTokenHash) {
            return false;
        }

        // Update last used timestamp
        $tokenData['last_used'] = time();
        $this->tokenStore->put($tokenData, $tokenId);

        return [
            'user_id' => $tokenData['user_id'],
            'username' => $tokenData['username'],
            'role' => $tokenData['role'],
            'exp' => $tokenData['expires_at'],
            'token_id' => $tokenId,
            'metadata' => $tokenData['metadata']
        ];
    }

    public function revokeToken($tokenId = null) {
        if (!$tokenId && $this->token) {
            // Try to get token ID from current token
            $parts = explode('.', $this->token);
            if (count($parts) === 2) {
                $payload = json_decode(base64_decode($parts[0]), true);
                $tokenId = $payload['t'] ?? null;
            }
        }

        if (!$tokenId) {
            return false;
        }

        $tokenData = $this->tokenStore->get($tokenId);
        if ($tokenData) {
            $tokenData['is_active'] = false;
            $tokenData['revoked_at'] = time();
            $this->tokenStore->put($tokenData, $tokenId);
            return true;
        }

        return false;
    }

    public function revokeAllUserTokens($userId) {
        $userTokens = $this->tokenStore->getByIndex('user_id_idx', $userId);
        $revokedCount = 0;

        foreach ($userTokens as $tokenData) {
            if ($tokenData['is_active']) {
                $tokenData['is_active'] = false;
                $tokenData['revoked_at'] = time();
                $this->tokenStore->put($tokenData, $tokenData['token_id']);
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    public function getUserActiveSessions($userId) {
        $sessions = $this->sessionStore->getByIndex('user_id_idx', $userId);
        $activeSessions = [];

        foreach ($sessions as $session) {
            $tokenData = $this->tokenStore->get($session['token_id']);
            if ($tokenData && $tokenData['is_active'] && $tokenData['expires_at'] > time()) {
                $activeSessions[] = [
                    'session_id' => $session['session_id'],
                    'token_id' => $session['token_id'],
                    'ip_address' => $session['ip_address'],
                    'user_agent' => $session['user_agent'],
                    'created_at' => $session['created_at'],
                    'last_activity' => $session['last_activity'],
                    'expires_at' => $tokenData['expires_at']
                ];
            }
        }

        return $activeSessions;
    }

    public function cleanExpiredTokens() {
        $allTokens = $this->tokenStore->getAll();
        $cleanedCount = 0;

        foreach ($allTokens as $tokenData) {
            if ($tokenData['expires_at'] < time()) {
                $this->tokenStore->delete($tokenData['token_id']);
                $cleanedCount++;
            }
        }

        return $cleanedCount;
    }

    private function generateTokenId() {
        return 'tk_' . bin2hex(random_bytes(16)) . '_' . time();
    }

    private function generateShortTokenId() {
        return 'tk_' . bin2hex(random_bytes(8)); // Much shorter: tk_1a2b3c4d5e6f7890
    }

    private function generateSessionId() {
        return 'ss_' . bin2hex(random_bytes(16)) . '_' . time();
    }

    public function getToken() {
        return $this->token;
    }

    public function setSecretKey($key) {
        $this->secretKey = $key;
        return $this;
    }

    public function setTokenExpiration($seconds) {
        $this->tokenExpiration = $seconds;
        return $this;
    }

    public function getTokenStore() {
        return $this->tokenStore;
    }

    public function getSessionStore() {
        return $this->sessionStore;
    }

    public function getStats() {
        $totalTokens = $this->tokenStore->count();
        $totalSessions = $this->sessionStore->count();
        
        $allTokens = $this->tokenStore->getAll();
        $activeTokens = 0;
        $expiredTokens = 0;
        
        foreach ($allTokens as $token) {
            if ($token['is_active'] && $token['expires_at'] > time()) {
                $activeTokens++;
            } else {
                $expiredTokens++;
            }
        }

        return [
            'total_tokens' => $totalTokens,
            'active_tokens' => $activeTokens,
            'expired_tokens' => $expiredTokens,
            'total_sessions' => $totalSessions
        ];
    }
}
