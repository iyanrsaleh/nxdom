<?php
namespace app\models\Repositories;

use App\System\Repositories\NexaBaseRepository;
use App\System\Contracts\NexaRepositoryInterface;
use App\System\Events\NexaEventDispatcher;
use app\models\Domain\NexaUserEntity;
use App\System\Domain\NexaEmail;

/**
 * NexaUserRepository - User Repository Implementation
 * Handles User data persistence and retrieval
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
class NexaUserRepository extends NexaBaseRepository
{
    protected array $fillable = [
        'name', 'email', 'password', 'status', 'avatar', 'phone'
    ];
    
    protected array $guarded = [
        'id', 'created_at', 'updated_at'
    ];
    
    /**
     * Set the table name
     */
    protected function setTable(): void
    {
        $this->table = 'users';
    }
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?NexaUserEntity
    {
        try {
            $data = $this->findBy(['email' => $email]);
            return $data ? NexaUserEntity::fromArray($data) : null;
        } catch (\Exception $e) {
            $this->logError("Error finding user by email: {$email}", $e);
            return null;
        }
    }
    
    /**
     * Find active users
     */
    public function findActiveUsers(): array
    {
        try {
            $data = $this->findAll(['status' => NexaUserEntity::STATUS_ACTIVE]);
            return array_map(fn($userData) => NexaUserEntity::fromArray($userData), $data);
        } catch (\Exception $e) {
            $this->logError("Error finding active users", $e);
            return [];
        }
    }
    
    /**
     * Save user entity (create or update)
     */
    public function saveEntity(NexaUserEntity $user): bool
    {
        try {
            if ($user->isNew()) {
                // Create new user
                $data = $user->toArray();
                unset($data['id']); // Remove null ID
                
                $id = $this->create($data);
                if ($id) {
                    // Set the ID on the entity using reflection since setId is protected
                    $reflection = new \ReflectionClass($user);
                    $idProperty = $reflection->getProperty('id');
                    $idProperty->setAccessible(true);
                    $idProperty->setValue($user, $id);
                    
                    // Dispatch domain events
                    $this->dispatchDomainEvents($user);
                    
                    return true;
                }
                
                return false;
            } else {
                // Update existing user
                $data = $user->toArray();
                unset($data['id'], $data['created_at']); // Don't update these fields
                
                $result = $this->update($user->getId(), $data);
                
                if ($result) {
                    // Dispatch domain events
                    $this->dispatchDomainEvents($user);
                }
                
                return $result;
            }
        } catch (\Exception $e) {
            $this->logError("Error saving user entity", $e);
            return false;
        }
    }
    
    /**
     * Delete user entity
     */
    public function deleteEntity(NexaUserEntity $user): bool
    {
        try {
            if ($user->isNew()) {
                return false; // Cannot delete unsaved entity
            }
            
            // Mark as deleted in domain
            $user->markAsDeleted();
            
            // Soft delete - just update status
            $result = $this->update($user->getId(), ['status' => NexaUserEntity::STATUS_DELETED]);
            
            if ($result) {
                // Dispatch domain events
                $this->dispatchDomainEvents($user);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logError("Error deleting user entity", $e);
            return false;
        }
    }
    
    /**
     * Hard delete user (permanently remove from database)
     */
    public function hardDeleteEntity(NexaUserEntity $user): bool
    {
        try {
            if ($user->isNew()) {
                return false;
            }
            
            $result = $this->delete($user->getId());
            
            if ($result) {
                // Dispatch domain events
                $this->dispatchDomainEvents($user);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logError("Error hard deleting user", $e);
            return false;
        }
    }
    
    /**
     * Find user by ID and return as entity
     */
    public function findEntity(int $id): ?NexaUserEntity
    {
        try {
            $data = $this->find($id);
            return $data ? NexaUserEntity::fromArray($data) : null;
        } catch (\Exception $e) {
            $this->logError("Error finding user entity by ID: {$id}", $e);
            return null;
        }
    }
    
    /**
     * Get all users as entities
     */
    public function getAllEntities(array $criteria = []): array
    {
        try {
            $data = $this->findAll($criteria);
            return array_map(fn($userData) => NexaUserEntity::fromArray($userData), $data);
        } catch (\Exception $e) {
            $this->logError("Error getting all user entities", $e);
            return [];
        }
    }
    
    /**
     * Get paginated users as entities
     */
    public function getPaginatedEntities(int $page = 1, int $perPage = 10, array $criteria = []): array
    {
        try {
            $result = $this->paginate($page, $perPage, $criteria);
            
            if (isset($result['data']) && is_array($result['data'])) {
                $result['data'] = array_map(
                    fn($userData) => NexaUserEntity::fromArray($userData), 
                    $result['data']
                );
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logError("Error getting paginated user entities", $e);
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Check if email exists (for uniqueness validation)
     */
    public function emailExists(string $email, int $excludeId = null): bool
    {
        try {
            $criteria = ['email' => $email];
            
            if ($excludeId) {
                // Use raw query for NOT IN condition
                $query = $this->model->table($this->table)
                    ->where('email', $email);
                
                if ($excludeId) {
                    $query->where('id', '!=', $excludeId);
                }
                
                return $query->exists();
            }
            
            return $this->count($criteria) > 0;
        } catch (\Exception $e) {
            $this->logError("Error checking email existence: {$email}", $e);
            return false;
        }
    }
    
    /**
     * Dispatch domain events from entity
     */
    private function dispatchDomainEvents(NexaUserEntity $user): void
    {
        try {
            $events = $user->getDomainEvents();
            $dispatcher = NexaEventDispatcher::getInstance();
            
            foreach ($events as $event) {
                $dispatcher->dispatch($event);
            }
            
            // Clear events after dispatching
            $user->clearDomainEvents();
        } catch (\Exception $e) {
            $this->logError("Error dispatching domain events", $e);
        }
    }
    
    /**
     * Transaction wrapper for complex operations
     */
    public function withTransaction(callable $callback)
    {
        try {
            return $this->model->transaction($callback);
        } catch (\Exception $e) {
            $this->logError("Transaction error", $e);
            throw $e;
        }
    }
} 