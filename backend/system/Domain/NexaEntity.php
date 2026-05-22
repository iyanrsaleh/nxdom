<?php
namespace App\System\Domain;

use App\System\Events\NexaDomainEvent;
use DateTime;

/**
 * NexaEntity - Base Domain Entity
 * Represents a domain entity with identity and behavior
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
abstract class NexaEntity
{
    protected int $id;
    protected DateTime $createdAt;
    protected DateTime $updatedAt;
    protected array $domainEvents = [];
    
    /**
     * Constructor
     */
    public function __construct(int $id = null)
    {
        if ($id) {
            $this->id = $id;
        }
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }
    
    /**
     * Get entity ID
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }
    
    /**
     * Set entity ID
     */
    protected function setId(int $id): void
    {
        $this->id = $id;
    }
    
    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
    
    /**
     * Get last update timestamp
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
    
    /**
     * Mark entity as updated
     */
    protected function markAsUpdated(): void
    {
        $this->updatedAt = new DateTime();
    }
    
    /**
     * Set created at timestamp
     */
    protected function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
    
    /**
     * Set updated at timestamp
     */
    protected function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
    
    /**
     * Add domain event
     */
    protected function addDomainEvent(NexaDomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
    
    /**
     * Get domain events
     */
    public function getDomainEvents(): array
    {
        return $this->domainEvents;
    }
    
    /**
     * Clear domain events
     */
    public function clearDomainEvents(): void
    {
        $this->domainEvents = [];
    }
    
    /**
     * Check if entity is new (not persisted)
     */
    public function isNew(): bool
    {
        return !isset($this->id);
    }
    
    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create entity from array data
     */
    public static function fromArray(array $data): static
    {
        $entity = new static($data['id'] ?? null);
        
        if (isset($data['created_at'])) {
            $entity->createdAt = new DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $entity->updatedAt = new DateTime($data['updated_at']);
        }
        
        return $entity;
    }
    
    /**
     * Equals comparison
     */
    public function equals(NexaEntity $other): bool
    {
        return $this->getId() === $other->getId() && 
               get_class($this) === get_class($other);
    }
} 