<?php
namespace App\System\Events\Listeners;

use App\System\Events\NexaUserCreated;
use App\System\Events\NexaUserUpdated;
use App\System\Events\NexaUserDeleted;

/**
 * NexaUserEventListeners - Listener Event untuk Domain Event User
 */
class NexaUserEventListeners
{
    /**
     * Tangani Event User Dibuat
     */
    public static function onUserCreated(NexaUserCreated $event): void
    {
        error_log("Event User Dibuat: " . $event->toJson());
        
        // Kirim email selamat datang (simulasi)
        self::sendWelcomeEmail($event->getEmail());
        
        // Buat profil user (simulasi)
        self::createUserProfile($event->getUserId());
    }
    
    /**
     * Tangani Event User Diupdate
     */
    public static function onUserUpdated(NexaUserUpdated $event): void
    {
        error_log("Event User Diupdate: " . $event->toJson());
        
        // Tangani perubahan status
        if (isset($event->getChanges()['status'])) {
            self::handleStatusChange($event->getUserId(), $event->getChanges()['status']);
        }
    }
    
    /**
     * Tangani Event User Dihapus
     */
    public static function onUserDeleted(NexaUserDeleted $event): void
    {
        error_log("Event User Dihapus: " . $event->toJson());
        
        // Bersihkan data user
        self::cleanupUserData($event->getUserId());
    }
    
    private static function sendWelcomeEmail(string $email): void
    {
        error_log("Mengirim email selamat datang ke: {$email}");
    }
    
    private static function createUserProfile(int $userId): void
    {
        error_log("Membuat profil untuk user: {$userId}");
    }
    
    private static function handleStatusChange(int $userId, string $status): void
    {
        error_log("User {$userId} status berubah menjadi: {$status}");
    }
    
    private static function cleanupUserData(int $userId): void
    {
        error_log("Membersihkan data untuk user: {$userId}");
    }
} 