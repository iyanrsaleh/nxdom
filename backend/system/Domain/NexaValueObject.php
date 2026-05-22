<?php
namespace App\System\Domain;

use InvalidArgumentException;

/**
 * NexaValueObject - Base Value Object
 * Represents immutable values with no identity
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
abstract class NexaValueObject
{
    /**
     * Value objects should be immutable
     * All properties should be readonly or protected
     */
    
    /**
     * Validate the value object
     * Should be implemented by child classes
     */
    abstract protected function validate(): void;
    
    /**
     * Get the string representation of the value
     */
    abstract public function toString(): string;
    
    /**
     * Get the actual value
     */
    abstract public function getValue();
    
    /**
     * Equals comparison
     */
    public function equals(NexaValueObject $other): bool
    {
        return get_class($this) === get_class($other) && 
               $this->getValue() === $other->getValue();
    }
    
    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return ['value' => $this->getValue()];
    }
    
    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

/**
 * NexaEmail - Email Value Object
 */
class NexaEmail extends NexaValueObject
{
    private string $email;
    
    public function __construct(string $email)
    {
        $this->email = trim(strtolower($email));
        $this->validate();
    }
    
    protected function validate(): void
    {
        if (empty($this->email)) {
            throw new InvalidArgumentException('Email cannot be empty');
        }
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format: {$this->email}");
        }
        
        if (strlen($this->email) > 254) {
            throw new InvalidArgumentException('Email is too long');
        }
    }
    
    public function getValue(): string
    {
        return $this->email;
    }
    
    public function toString(): string
    {
        return $this->email;
    }
    
    public function getDomain(): string
    {
        return substr($this->email, strpos($this->email, '@') + 1);
    }
    
    public function getLocalPart(): string
    {
        return substr($this->email, 0, strpos($this->email, '@'));
    }
}

/**
 * NexaPassword - Password Value Object
 */
class NexaPassword extends NexaValueObject
{
    private string $hashedPassword;
    
    public function __construct(string $password, bool $isHashed = false)
    {
        if ($isHashed) {
            $this->hashedPassword = $password;
        } else {
            $this->validatePlainPassword($password);
            $this->hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }
    }
    
    private function validatePlainPassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one uppercase letter');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one lowercase letter');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one number');
        }
    }
    
    protected function validate(): void
    {
        // Hash validation is done during construction
    }
    
    public function getValue(): string
    {
        return $this->hashedPassword;
    }
    
    public function toString(): string
    {
        return '[HIDDEN]';
    }
    
    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedPassword);
    }
    
    public function needsRehash(): bool
    {
        return password_needs_rehash($this->hashedPassword, PASSWORD_DEFAULT);
    }
}

/**
 * NexaUserId - User ID Value Object
 */
class NexaUserId extends NexaValueObject
{
    private int $id;
    
    public function __construct(int $id)
    {
        $this->id = $id;
        $this->validate();
    }
    
    protected function validate(): void
    {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer');
        }
    }
    
    public function getValue(): int
    {
        return $this->id;
    }
    
    public function toString(): string
    {
        return (string) $this->id;
    }
    
    public static function generate(): self
    {
        // In a real implementation, this might generate a UUID or use a sequence
        return new self(random_int(1, 999999));
    }
} 