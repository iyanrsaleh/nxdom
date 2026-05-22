<?php
namespace App\Models\Repositories;

use App\System\Repositories\NexaBaseRepository;
use App\Models\Domain\NexaAuthEntity;

class NexaAuthRepository extends NexaBaseRepository
{
    protected array $fillable = [
        'nama', 'email', 'password', 'status', 'avatar','gender'
    ];
    
    protected array $guarded = [
        'id', 'created_at', 'updated_at'
    ];
    
    public function __construct()
    {
        // Initialize with NexaModel instance
        $model = new \App\System\NexaModel();
        parent::__construct($model);
    }
    
    protected function setTable(): void
    {
        $this->table = 'user'; // atau 'users' sesuai dengan nama table di database
    }

    /**
     * Find user by email for authentication
     */
    public function findByEmail(string $email): ?array
    {
        try {
            $result = $this->model->table($this->table)
                ->where('email', $email)
                ->first();

              if (!empty($result[0]) && is_array($result[0])) {
                  foreach ($result as &$user) {
                      $user['avatar'] = $this->getGenderBasedAvatar($user['avatar'] ?? '', $user['gender'] ?? '');
                  }
              }
            
            if ($result) {
                error_log("User found by email: {$email}");
                return $result;
            }
            
            error_log("User not found by email: {$email}");
            return null;
            
        } catch (\Exception $e) {
            $this->logError("Error finding user by email: " . $e->getMessage(), $e);
            throw new \Exception("Database error occurred while finding user");
        }
    }

    /**
     * Get avatar berdasarkan gender - Application level processing
     * Enhanced dengan typo detection dan variasi gender yang lebih luas
     * 
     * @param string $avatar Current avatar path
     * @param string $gender User gender
     * @return string Avatar path
     */
    private function getGenderBasedAvatar(string $avatar, string $gender): string
    {
        // Jika sudah ada avatar, gunakan yang ada
        if (!empty($avatar) && $avatar !== 'null') {
            return $avatar;
        }
        
        // Normalize gender untuk handling typo dan variasi
        $genderNormalized = $gender;
        
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
     * Create domain entity from database data
     */
    public function createEntityFromData(array $data): NexaAuthEntity
    {
        return new NexaAuthEntity($data);
    }

    /**
     * Save authentication attempt log (using error_log for now)
     */
    public function logAuthAttempt(string $email, bool $success, string $ipAddress = null): void
    {
        try {
            // For now, log to error log. In production, you might want to use a dedicated table
            $status = $success ? 'SUCCESS' : 'FAILED';
            $logMessage = "AUTH ATTEMPT [{$status}] Email: {$email}, IP: {$ipAddress}, Time: " . date('Y-m-d H:i:s');
            
            error_log($logMessage);
            
            // Optional: Save to database if auth_logs table exists
            // $this->model->table('auth_logs')->insert([
            //     'email' => $email,
            //     'success' => $success ? 1 : 0,
            //     'ip_address' => $ipAddress,
            //     'attempted_at' => date('Y-m-d H:i:s')
            // ]);
            
        } catch (\Exception $e) {
            // Log error but don't throw - auth logging shouldn't break authentication
            error_log("Error logging auth attempt: " . $e->getMessage());
        }
    }

    /**
     * Check if user exists by email
     */
    public function emailExists(string $email): bool
    {
        try {
            return $this->model->table($this->table)
                ->where('email', $email)
                ->exists();
            
        } catch (\Exception $e) {
            $this->logError("Error checking email existence: " . $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Update user's last login time (disabled - column doesn't exist)
     */
    public function updateLastLogin(int $userId): void
    {
        try {
            // Skip updating last_login since column doesn't exist in current table structure
            error_log("INFO: Skipping last_login update - column doesn't exist in table");
            
            // Optional: If you want to add last_login column later, uncomment below:
            // $this->model->table($this->table)
            //     ->where('id', $userId)
            //     ->update(['last_login' => date('Y-m-d H:i:s')]);
            
        } catch (\Exception $e) {
            $this->logError("Error updating last login: " . $e->getMessage(), $e);
            // Don't throw - this shouldn't break authentication
        }
    }
} 