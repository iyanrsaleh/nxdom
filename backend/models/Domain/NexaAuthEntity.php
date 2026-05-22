<?php
namespace App\Models\Domain;

// For now, we'll create a simplified version without complex dependencies
// use App\System\Domain\NexaEntity;
// use App\System\Domain\ValueObjects\NexaEmail;
// use App\System\Domain\ValueObjects\NexaPassword;
// use App\System\Domain\ValueObjects\NexaUserId;

class NexaAuthEntity
{
    private int $userId;
    private string $name;
    private string $email;
    private string $password;
    private string $status;
    private ?string $avatar;
    private string $gender; // Gender for avatar selection
    private \DateTime $loginTime;
    private array $domainEvents = [];
    private bool $authenticationPassed = false; // Flag to track if authentication succeeded

    public function __construct(array $data)
    {
        $this->userId = $data['id'] ?? 0;
        $this->name = $data['nama'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->status = $data['status'] ?? 'user';
        $this->avatar = $data['avatar'] ?? null;
        $this->gender = $data['gender'] ?? '';
        $this->loginTime = new \DateTime();
        
        // Add domain event (simplified)
        $this->addDomainEvent('UserAuthenticated');
    }

    public function authenticate(string $plainPassword): bool
    {
        $isValid = false;
        
        // Support both hashed and plain text passwords
        if (str_starts_with($this->password, '$2y$')) {
            // Password is hashed, use password_verify
            $isValid = password_verify($plainPassword, $this->password);
        } else {
            // Password is plain text, direct comparison
            $isValid = $plainPassword === $this->password;
        }
        
        // Set authentication flag if password is correct
        if ($isValid) {
            $this->authenticationPassed = true;
        }
        
        return $isValid;
    }

    public function isActive(): bool
    {
        // If authentication already passed, consider user as active
        if ($this->authenticationPassed) {
            return true;
        }
        
        // Check various possible status values - INCLUDING 'admin'
        $activeStatuses = ['active', 'user', 'admin', 'superuser', 'moderator', '1', 1, 'enabled', 'on'];
        $isActive = in_array($this->status, $activeStatuses, false) || 
                   in_array(strtolower($this->status), ['active', 'user', 'admin', 'superuser', 'moderator', 'enabled', 'on'], true);
        
        return $isActive;
    }

    public function canLogin(): bool
    {
        // If authentication already passed, allow login (bypass additional checks)
        if ($this->authenticationPassed) {
            return true;
        }
        
        return $this->isActive() && filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }

    public function getSessionData(): array
    {
        $slugName = $this->generateSlugName();
        
        return [
            "user_id" => $this->userId,
            "user" => $slugName,
            "user_name" => $slugName,
            "avatar" => $this->getAvatarUrl(),
            "user_real_name" => $this->name,
            "email" => $this->email,
            "status" => $this->status,
            "login_time" => $this->loginTime->format('Y-m-d H:i:s')
        ];
    }

    private function generateSlugName(): string
    {
        // Ensure we have a name to work with
        if (empty($this->name)) {
            return 'user' . $this->userId;
        }
        
        // Take full name, not just first word
        $text = $this->name;
        
        // Convert to lowercase FIRST to ensure everything is lowercase
        $text = strtolower($text);
        
        // Remove accents and special characters, convert to basic latin
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Replace any non-alphanumeric characters with dashes
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove multiple consecutive dashes
        $text = preg_replace('/-+/', '-', $text);
        
        // Trim dashes from start and end
        $text = trim($text, '-');
        
        // Ensure we don't have an empty result
        if (empty($text)) {
            return 'user' . $this->userId;
        }
        
        // Limit length to reasonable size
        if (strlen($text) > 50) {
            $text = substr($text, 0, 50);
            $text = rtrim($text, '-');
        }
        
        // Final check: ensure it's all lowercase
        return strtolower($text);
    }

    private function getAvatarUrl(): string
    {
        // Jika sudah ada avatar yang di-set dari repository, gunakan itu
        if (!empty($this->avatar) && $this->avatar !== 'null') {
            return $this->avatar;
        }
        
        // Gunakan gender-based avatar system yang konsisten dengan repository
        return $this->getGenderBasedAvatar($this->avatar ?? '', $this->gender);
    }

    /**
     * Gender-based avatar selection - konsisten dengan NexaAuthRepository
     */
    private function getGenderBasedAvatar(string $avatar, string $gender): string
    {
        // Jika sudah ada avatar, gunakan yang ada
        if (!empty($avatar) && $avatar !== 'null') {
            return $avatar;
        }
        
        // Normalize gender untuk handling typo dan variasi
        $genderNormalized = $this->normalizeGender($gender);
        
        // Male variants (termasuk typo umum)
        if (in_array($genderNormalized, [
            'male', 'm', 'laki-laki', 'pria', '1', 'l',
            // Typo umum
            'mele', 'mel', 'male!', 'mae', 'mal',
            // Variasi lain
            'cowok', 'cowo', 'boy', 'man', 'gentleman'
        ])) {
            return 'images/pria.png';
        }
        
        // Female variants (termasuk typo umum)
        if (in_array($genderNormalized, [
            'female', 'f', 'perempuan', 'wanita', '2', 'p',
            // Typo umum
            'femal', 'fem', 'female!', 'fmale', 'femle',
            // Variasi lain
            'cewek', 'cewe', 'girl', 'woman', 'lady'
        ])) {
            return 'images/wanita.png';
        }
        
        // Default fallback
        return 'images/wanita.png';
    }

    /**
     * Normalize gender untuk consistent handling
     */
    private function normalizeGender(string $gender): string
    {
        return strtolower(trim($gender));
    }

    // Getters
    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getGender(): string
    {
        return $this->gender;
    }
    
    // Domain Events
    public function addDomainEvent(string $eventName): void
    {
        $this->domainEvents[] = $eventName;
    }
    
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    /**
     * Debug method untuk troubleshooting validation
     */
    public function getValidationStatus(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'gender' => $this->gender,
            'authentication_passed' => $this->authenticationPassed,
            'is_active_check' => [
                'bypassed' => $this->authenticationPassed,
                'status_valid' => in_array($this->status, ['active', 'user', 'admin', 'superuser', 'moderator', '1', 1, 'enabled', 'on'], false),
                'final_result' => $this->isActive()
            ],
            'can_login_check' => [
                'bypassed' => $this->authenticationPassed,
                'email_valid' => filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false,
                'final_result' => $this->canLogin()
            ],
            'slug_generated' => $this->generateSlugName(),
            'avatar_info' => [
                'original_avatar' => $this->avatar,
                'gender' => $this->gender,
                'normalized_gender' => $this->normalizeGender($this->gender),
                'final_avatar_url' => $this->getAvatarUrl(),
                'is_gender_based' => empty($this->avatar) || $this->avatar === 'null'
            ]
        ];
    }

    /**
     * Public method to test slug generation and avatar selection - for debugging
     */
    public function testSlugGeneration(): array
    {
        return [
            'slug_testing' => [
                'original_name' => $this->name,
                'generated_slug' => $this->generateSlugName(),
                'is_lowercase' => $this->generateSlugName() === strtolower($this->generateSlugName()),
                'slug_length' => strlen($this->generateSlugName()),
                'contains_only_valid_chars' => preg_match('/^[a-z0-9-]+$/', $this->generateSlugName()) === 1,
                'generation_steps' => $this->debugSlugGeneration()
            ],
            'avatar_testing' => [
                'original_gender' => $this->gender,
                'normalized_gender' => $this->normalizeGender($this->gender),
                'original_avatar' => $this->avatar,
                'final_avatar' => $this->getAvatarUrl(),
                'avatar_source' => $this->getAvatarSource(),
                'gender_variants_male' => ['male', 'm', 'laki-laki', 'pria', '1', 'l', 'mele', 'cowok'],
                'gender_variants_female' => ['female', 'f', 'perempuan', 'wanita', '2', 'p', 'femal', 'cewek']
            ]
        ];
    }

    /**
     * Get avatar source info for debugging
     */
    private function getAvatarSource(): string
    {
        if (!empty($this->avatar) && $this->avatar !== 'null') {
            return 'existing_avatar';
        }
        
        $genderNormalized = $this->normalizeGender($this->gender);
        
        if (in_array($genderNormalized, ['male', 'm', 'laki-laki', 'pria', '1', 'l', 'mele', 'mel', 'male!', 'mae', 'mal', 'cowok', 'cowo', 'boy', 'man', 'gentleman'])) {
            return 'gender_based_male';
        }
        
        if (in_array($genderNormalized, ['female', 'f', 'perempuan', 'wanita', '2', 'p', 'femal', 'fem', 'female!', 'fmale', 'femle', 'cewek', 'cewe', 'girl', 'woman', 'lady'])) {
            return 'gender_based_female';
        }
        
        return 'default_fallback';
    }

    /**
     * Debug slug generation step by step
     */
    private function debugSlugGeneration(): array
    {
        $steps = [];
        $text = $this->name;
        
        $steps['step1_original'] = $text;
        
        if (empty($text)) {
            $steps['step2_empty_fallback'] = 'user' . $this->userId;
            return $steps;
        }
        
        $steps['step2_lowercase'] = strtolower($text);
        $text = strtolower($text);
        
        $steps['step3_remove_accents'] = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        $steps['step4_replace_special'] = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        $steps['step5_remove_multiple_dashes'] = preg_replace('/-+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        
        $steps['step6_trim_dashes'] = trim($text, '-');
        $text = trim($text, '-');
        
        if (strlen($text) > 50) {
            $steps['step7_limit_length'] = substr($text, 0, 50);
            $text = substr($text, 0, 50);
            $text = rtrim($text, '-');
            $steps['step8_trim_after_limit'] = $text;
        }
        
        $steps['final_result'] = strtolower($text);
        
        return $steps;
    }
} 