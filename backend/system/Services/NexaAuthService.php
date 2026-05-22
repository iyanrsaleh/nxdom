<?php
namespace App\System\Services;

use App\Models\Repositories\NexaAuthRepository;
use App\Models\Domain\NexaAuthEntity;

class NexaAuthService
{
    private NexaAuthRepository $authRepository;

    public function __construct()
    {
        $this->authRepository = new NexaAuthRepository();
    }

    /**
     * Authenticate user with email and password
     */
    public function authenticate(string $email, string $password): array
    {
        // 1. Validate input
        $this->validateAuthInput($email, $password);

        // 2. Find user by email
        $userData = $this->authRepository->findByEmail($email);
        
        if (!$userData) {
            $this->logFailedAttempt($email, 'User not found');
            throw new \Exception('Email tidak terdaftar');
        }

        // Optional: Log user data for debugging (uncomment if needed)
        // error_log("DEBUG: User data from database: " . json_encode($userData));

        // 3. Create domain entity
        $authEntity = $this->authRepository->createEntityFromData($userData);

        // 4. Check if user can login
        if (!$authEntity->canLogin()) {
            $this->logFailedAttempt($email, 'User cannot login - Status: ' . $authEntity->getStatus());
            
            // Provide more specific error message
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Format email tidak valid');
            } else {
                throw new \Exception('Akun tidak aktif. Status akun: ' . $authEntity->getStatus());
            }
        }

        // 5. Verify password
        if (!$authEntity->authenticate($password)) {
            $this->logFailedAttempt($email, 'Invalid password');
            throw new \Exception('Password tidak valid');
        }

        // 6. Update last login & log success
        $this->authRepository->updateLastLogin($authEntity->getUserId());
        $this->logSuccessfulAttempt($email);

        // 7. Dispatch domain events
        $this->dispatchDomainEvents($authEntity);

        // 8. Return session data
        return [
            'success' => true,
            'message' => 'Login berhasil!',
            'user' => $authEntity->getSessionData(),
            'redirect_url' => $this->generateRedirectUrl($authEntity)
        ];
    }

    /**
     * Check if email exists in system
     */
    public function emailExists(string $email): bool
    {
        return $this->authRepository->emailExists($email);
    }

    /**
     * Get user by email (for password reset, etc.)
     */
    public function getUserByEmail(string $email): ?NexaAuthEntity
    {
        $userData = $this->authRepository->findByEmail($email);
        
        if (!$userData) {
            return null;
        }

        return $this->authRepository->createEntityFromData($userData);
    }

    /**
     * Validate authentication input
     */
    private function validateAuthInput(string $email, string $password): void
    {
        if (empty($email)) {
            throw new \Exception('Email tidak boleh kosong');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Format email tidak valid');
        }

        if (empty($password)) {
            throw new \Exception('Password tidak boleh kosong');
        }

        if (strlen($password) < 6) {
            throw new \Exception('Password minimal 6 karakter');
        }
    }

    /**
     * Log failed authentication attempt
     */
    private function logFailedAttempt(string $email, string $reason): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->authRepository->logAuthAttempt($email, false, $ipAddress);
        
        error_log("Failed login attempt for {$email}: {$reason} from IP: {$ipAddress}");
    }

    /**
     * Log successful authentication attempt
     */
    private function logSuccessfulAttempt(string $email): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->authRepository->logAuthAttempt($email, true, $ipAddress);
        
        error_log("Successful login for {$email} from IP: {$ipAddress}");
    }

    /**
     * Dispatch domain events from entity
     */
    private function dispatchDomainEvents(NexaAuthEntity $authEntity): void
    {
        $events = $authEntity->releaseEvents();
        
        foreach ($events as $event) {
            // Fix: $event is a string, not an object
            error_log("Domain Event Dispatched: " . $event);
            
            // Optional: Use proper event dispatcher if available
            // if (class_exists('\App\System\Events\NexaEventDispatcher')) {
            //     $eventDispatcher = new \App\System\Events\NexaEventDispatcher();
            //     $eventDispatcher->dispatch($event);
            // }
        }
    }

    /**
     * Generate redirect URL after successful login
     */
    private function generateRedirectUrl(NexaAuthEntity $authEntity): string
    {
        $sessionData = $authEntity->getSessionData();
        return '/' . $sessionData['user_name'];
    }

    /**
     * Business method: Check user account status
     */
    public function checkAccountStatus(string $email): array
    {
        $userData = $this->authRepository->findByEmail($email);
        
        if (!$userData) {
            return [
                'exists' => false,
                'status' => 'not_found',
                'message' => 'Email tidak terdaftar'
            ];
        }

        $authEntity = $this->authRepository->createEntityFromData($userData);

        return [
            'exists' => true,
            'status' => $authEntity->getStatus(),
            'can_login' => $authEntity->canLogin(),
            'is_active' => $authEntity->isActive(),
            'message' => $authEntity->canLogin() ? 'Account active' : 'Account inactive'
        ];
    }
} 