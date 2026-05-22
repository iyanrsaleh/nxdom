<?php
namespace App\System\Events;

use DateTime;

/**
 * NexaDomainEvent - Event Domain Dasar
 * Merepresentasikan sesuatu yang terjadi di domain
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    Tim NexaUI
 */
abstract class NexaDomainEvent
{
    protected DateTime $occurredAt;
    protected string $eventId;
    protected string $eventType;
    
    public function __construct()
    {
        $this->occurredAt = new DateTime();
        $this->eventId = uniqid('event_', true);
        $this->eventType = static::class;
    }
    
    /**
     * Dapatkan kapan event terjadi
     */
    public function getOccurredAt(): DateTime
    {
        return $this->occurredAt;
    }
    
    /**
     * Dapatkan ID event
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }
    
    /**
     * Dapatkan tipe event
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }
    
    /**
     * Dapatkan nama event (nama kelas pendek)
     */
    public function getEventName(): string
    {
        $parts = explode('\\', $this->eventType);
        return end($parts);
    }
    
    /**
     * Dapatkan data event - harus diimplementasikan oleh kelas turunan
     */
    abstract public function getEventData(): array;
    
    /**
     * Konversi event ke array
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_name' => $this->getEventName(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
            'data' => $this->getEventData()
        ];
    }
    
    /**
     * Konversi event ke JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

/**
 * NexaUserCreated - Event User Dibuat
 */
class NexaUserCreated extends NexaDomainEvent
{
    private int $userId;
    private string $email;
    private array $userData;
    
    public function __construct(int $userId, string $email, array $userData = [])
    {
        parent::__construct();
        $this->userId = $userId;
        $this->email = $email;
        $this->userData = $userData;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getUserData(): array
    {
        return $this->userData;
    }
    
    public function getEventData(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'user_data' => $this->userData
        ];
    }
}

/**
 * NexaUserUpdated - Event User Diupdate
 */
class NexaUserUpdated extends NexaDomainEvent
{
    private int $userId;
    private array $changes;
    private array $oldData;
    private array $newData;
    
    public function __construct(int $userId, array $changes, array $oldData = [], array $newData = [])
    {
        parent::__construct();
        $this->userId = $userId;
        $this->changes = $changes;
        $this->oldData = $oldData;
        $this->newData = $newData;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getChanges(): array
    {
        return $this->changes;
    }
    
    public function getOldData(): array
    {
        return $this->oldData;
    }
    
    public function getNewData(): array
    {
        return $this->newData;
    }
    
    public function getEventData(): array
    {
        return [
            'user_id' => $this->userId,
            'changes' => $this->changes,
            'old_data' => $this->oldData,
            'new_data' => $this->newData
        ];
    }
}

/**
 * NexaUserDeleted - Event User Dihapus
 */
class NexaUserDeleted extends NexaDomainEvent
{
    private int $userId;
    private array $userData;
    
    public function __construct(int $userId, array $userData = [])
    {
        parent::__construct();
        $this->userId = $userId;
        $this->userData = $userData;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getUserData(): array
    {
        return $this->userData;
    }
    
    public function getEventData(): array
    {
        return [
            'user_id' => $this->userId,
            'user_data' => $this->userData
        ];
    }
} 