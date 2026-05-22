<?php
namespace app\models\Domain;

use App\System\Domain\NexaEntity;
use App\System\Domain\NexaEmail;
use App\System\Domain\NexaPassword;
use App\System\Domain\NexaUserId;
use App\System\Events\NexaUserCreated;
use App\System\Events\NexaUserUpdated;
use App\System\Events\NexaUserDeleted;
use InvalidArgumentException;

/**
 * NexaUserEntity - User Domain Entity
 * Rich domain model with business logic and validation
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
class NexaUserEntity extends NexaEntity
{
    private string $name = '';
    private ?NexaEmail $email = null;
    private ?NexaPassword $password = null;
    private string $status = self::STATUS_ACTIVE;
    private ?string $avatar = null;
    private ?string $phone = null;
    
    // Valid status values
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_DELETED = 'deleted';
    
    /**
     * Constructor
     */
    public function __construct(
        int $id = null,
        string $name = '',
        NexaEmail $email = null,
        NexaPassword $password = null,
        string $status = self::STATUS_ACTIVE
    ) {
        parent::__construct($id);
        
        if ($name) {
            $this->setName($name);
        }
        
        if ($email) {
            $this->email = $email;
        }
        
        if ($password) {
            $this->password = $password;
        }
        
        $this->setStatus($status);
    }
    
    /**
     * Create new user (factory method)
     */
    public static function create(string $name, string $email, string $password): self
    {
        $emailVO = new NexaEmail($email);
        $passwordVO = new NexaPassword($password);
        
        $user = new self(null, $name, $emailVO, $passwordVO);
        
        // Add domain event
        $user->addDomainEvent(new NexaUserCreated(
            $user->getId() ?? 0,
            $emailVO->getValue(),
            ['name' => $name, 'status' => $user->status]
        ));
        
        return $user;
    }
    
    /**
     * Create from array data (for repository usage)
     */
    public static function fromArray(array $data): static
    {
        $user = new self(
            $data['id'] ?? null,
            $data['name'] ?? '',
            isset($data['email']) ? new NexaEmail($data['email']) : null,
            isset($data['password']) ? new NexaPassword($data['password'], true) : null,
            $data['status'] ?? self::STATUS_ACTIVE
        );
        
        if (isset($data['avatar'])) {
            $user->avatar = $data['avatar'];
        }
        
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        
        // Set timestamps from data
        if (isset($data['created_at'])) {
            $user->setCreatedAt(new \DateTime($data['created_at']));
        }
        
        if (isset($data['updated_at'])) {
            $user->setUpdatedAt(new \DateTime($data['updated_at']));
        }
        
        return $user;
    }
    
    /**
     * Get user name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Set user name
     */
    public function setName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        
        if (strlen($name) < 2) {
            throw new InvalidArgumentException('Name must be at least 2 characters long');
        }
        
        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Name cannot exceed 100 characters');
        }
        
        $oldName = $this->name ?? '';
        $this->name = trim($name);
        
        if ($oldName !== $this->name && !$this->isNew()) {
            $this->markAsUpdated();
            $this->addDomainEvent(new NexaUserUpdated(
                $this->getId(),
                ['name' => $this->name],
                ['name' => $oldName],
                ['name' => $this->name]
            ));
        }
    }
    
    /**
     * Get email
     */
    public function getEmail(): ?NexaEmail
    {
        return $this->email;
    }
    
    /**
     * Change email
     */
    public function changeEmail(string $newEmail): void
    {
        $oldEmail = $this->email?->getValue() ?? '';
        $this->email = new NexaEmail($newEmail);
        
        if ($oldEmail !== $this->email->getValue() && !$this->isNew()) {
            $this->markAsUpdated();
            $this->addDomainEvent(new NexaUserUpdated(
                $this->getId(),
                ['email' => $this->email->getValue()],
                ['email' => $oldEmail],
                ['email' => $this->email->getValue()]
            ));
        }
    }
    
    /**
     * Change password
     */
    public function changePassword(string $newPassword): void
    {
        $this->password = new NexaPassword($newPassword);
        
        if (!$this->isNew()) {
            $this->markAsUpdated();
            $this->addDomainEvent(new NexaUserUpdated(
                $this->getId(),
                ['password' => '[CHANGED]'],
                ['password' => '[HIDDEN]'],
                ['password' => '[HIDDEN]']
            ));
        }
    }
    
    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return $this->password?->verify($password) ?? false;
    }
    
    /**
     * Get status
     */
    public function getStatus(): string
    {
        return $this->status;
    }
    
    /**
     * Set status
     */
    private function setStatus(string $status): void
    {
        $validStatuses = [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_DELETED
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }
        
        $this->status = $status;
    }
    
    /**
     * Activate user
     */
    public function activate(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            $oldStatus = $this->status;
            $this->status = self::STATUS_ACTIVE;
            $this->markAsUpdated();
            
            if (!$this->isNew()) {
                $this->addDomainEvent(new NexaUserUpdated(
                    $this->getId(),
                    ['status' => $this->status],
                    ['status' => $oldStatus],
                    ['status' => $this->status]
                ));
            }
        }
    }
    
    /**
     * Deactivate user
     */
    public function deactivate(): void
    {
        if ($this->status !== self::STATUS_INACTIVE) {
            $oldStatus = $this->status;
            $this->status = self::STATUS_INACTIVE;
            $this->markAsUpdated();
            
            if (!$this->isNew()) {
                $this->addDomainEvent(new NexaUserUpdated(
                    $this->getId(),
                    ['status' => $this->status],
                    ['status' => $oldStatus],
                    ['status' => $this->status]
                ));
            }
        }
    }
    
    /**
     * Suspend user
     */
    public function suspend(): void
    {
        if ($this->status !== self::STATUS_SUSPENDED) {
            $oldStatus = $this->status;
            $this->status = self::STATUS_SUSPENDED;
            $this->markAsUpdated();
            
            if (!$this->isNew()) {
                $this->addDomainEvent(new NexaUserUpdated(
                    $this->getId(),
                    ['status' => $this->status],
                    ['status' => $oldStatus],
                    ['status' => $this->status]
                ));
            }
        }
    }
    
    /**
     * Mark user as deleted
     */
    public function markAsDeleted(): void
    {
        if ($this->status !== self::STATUS_DELETED) {
            $userData = $this->toArray();
            $this->status = self::STATUS_DELETED;
            $this->markAsUpdated();
            
            if (!$this->isNew()) {
                $this->addDomainEvent(new NexaUserDeleted(
                    $this->getId(),
                    $userData
                ));
            }
        }
    }
    
    /**
     * Business logic: Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Business logic: Check if user can login
     */
    public function canLogin(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE]);
    }
    
    /**
     * Business logic: Check if user can be modified
     */
    public function canBeModified(): bool
    {
        return $this->status !== self::STATUS_DELETED;
    }
    
    /**
     * Set avatar
     */
    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
        if (!$this->isNew()) {
            $this->markAsUpdated();
        }
    }
    
    /**
     * Get avatar
     */
    public function getAvatar(): ?string
    {
        return $this->avatar;
    }
    
    /**
     * Set phone
     */
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
        if (!$this->isNew()) {
            $this->markAsUpdated();
        }
    }
    
    /**
     * Get phone
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    
    /**
     * Convert to array (for persistence)
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        
        return array_merge($data, [
            'name' => $this->name,
            'email' => $this->email?->getValue(),
            'password' => $this->password?->getValue(),
            'status' => $this->status,
            'avatar' => $this->avatar,
            'phone' => $this->phone
        ]);
    }
} 