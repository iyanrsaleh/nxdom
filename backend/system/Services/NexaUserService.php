<?php
namespace App\System\Services;

use App\System\NexaModel;
use app\models\Repositories\NexaUserRepository;
use app\models\Domain\NexaUserEntity;
use App\System\Domain\NexaEmail;
use App\System\Domain\NexaPassword;
use Exception;

/**
 * NexaUserService - User Service Layer
 * Handles business logic and coordinates between repositories and domain
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
class NexaUserService
{
    private NexaUserRepository $userRepository;
    
    public function __construct(NexaUserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new NexaUserRepository(new NexaModel());
    }
    
    /**
     * Create a new user
     */
    public function createUser(string $name, string $email, string $password): array
    {
        try {
            // Check if email already exists
            if ($this->userRepository->emailExists($email)) {
                return [
                    'success' => false,
                    'message' => 'Email already exists',
                    'errors' => ['email' => 'This email is already registered']
                ];
            }
            
            // Create domain entity
            $user = NexaUserEntity::create($name, $email, $password);
            
            // Save through repository
            $result = $this->userRepository->saveEntity($user);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail()->getValue(),
                        'status' => $user->getStatus()
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create user',
                'errors' => ['general' => 'Database error occurred']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Update user information
     */
    public function updateUser(int $userId, array $data): array
    {
        try {
            // Find user
            $user = $this->userRepository->findEntity($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'errors' => ['user' => 'User does not exist']
                ];
            }
            
            // Check if user can be modified
            if (!$user->canBeModified()) {
                return [
                    'success' => false,
                    'message' => 'User cannot be modified',
                    'errors' => ['user' => 'User is in a state that prevents modification']
                ];
            }
            
            // Update name if provided
            if (isset($data['name'])) {
                $user->setName($data['name']);
            }
            
            // Update email if provided
            if (isset($data['email'])) {
                // Check email uniqueness
                if ($this->userRepository->emailExists($data['email'], $userId)) {
                    return [
                        'success' => false,
                        'message' => 'Email already exists',
                        'errors' => ['email' => 'This email is already registered']
                    ];
                }
                
                $user->changeEmail($data['email']);
            }
            
            // Update password if provided
            if (isset($data['password'])) {
                $user->changePassword($data['password']);
            }
            
            // Update avatar if provided
            if (isset($data['avatar'])) {
                $user->setAvatar($data['avatar']);
            }
            
            // Update phone if provided
            if (isset($data['phone'])) {
                $user->setPhone($data['phone']);
            }
            
            // Save changes
            $result = $this->userRepository->saveEntity($user);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data' => [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail()->getValue(),
                        'status' => $user->getStatus(),
                        'avatar' => $user->getAvatar(),
                        'phone' => $user->getPhone()
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update user',
                'errors' => ['general' => 'Database error occurred']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update user: ' . $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Authenticate user login
     */
    public function login(string $email, string $password): array
    {
        try {
            // Find user by email
            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'errors' => ['login' => 'Email or password is incorrect']
                ];
            }
            
            // Check if user can login
            if (!$user->canLogin()) {
                return [
                    'success' => false,
                    'message' => 'Account not active',
                    'errors' => ['login' => 'Your account is not active']
                ];
            }
            
            // Verify password
            if (!$user->verifyPassword($password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'errors' => ['login' => 'Email or password is incorrect']
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail()->getValue(),
                    'status' => $user->getStatus()
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(int $userId): array
    {
        try {
            $user = $this->userRepository->findEntity($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'User found',
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail()->getValue(),
                    'status' => $user->getStatus(),
                    'avatar' => $user->getAvatar(),
                    'phone' => $user->getPhone(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get user: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Get paginated users
     */
    public function getUsers(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        try {
            $result = $this->userRepository->getPaginatedEntities($page, $perPage, $filters);
            
            // Convert entities to arrays
            if (isset($result['data']) && is_array($result['data'])) {
                $result['data'] = array_map(function(NexaUserEntity $user) {
                    return [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail()->getValue(),
                        'status' => $user->getStatus(),
                        'avatar' => $user->getAvatar(),
                        'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                    ];
                }, $result['data']);
            }
            
            return [
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get users: ' . $e->getMessage(),
                'data' => [
                    'data' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 0
                ]
            ];
        }
    }
    
    /**
     * Activate user
     */
    public function activateUser(int $userId): array
    {
        return $this->changeUserStatus($userId, 'activate');
    }
    
    /**
     * Deactivate user
     */
    public function deactivateUser(int $userId): array
    {
        return $this->changeUserStatus($userId, 'deactivate');
    }
    
    /**
     * Suspend user
     */
    public function suspendUser(int $userId): array
    {
        return $this->changeUserStatus($userId, 'suspend');
    }
    
    /**
     * Delete user (soft delete)
     */
    public function deleteUser(int $userId): array
    {
        try {
            $user = $this->userRepository->findEntity($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            $result = $this->userRepository->deleteEntity($user);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete user'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Change user status
     */
    private function changeUserStatus(int $userId, string $action): array
    {
        try {
            $user = $this->userRepository->findEntity($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            switch ($action) {
                case 'activate':
                    $user->activate();
                    $message = 'User activated successfully';
                    break;
                case 'deactivate':
                    $user->deactivate();
                    $message = 'User deactivated successfully';
                    break;
                case 'suspend':
                    $user->suspend();
                    $message = 'User suspended successfully';
                    break;
                default:
                    return [
                        'success' => false,
                        'message' => 'Invalid action'
                    ];
            }
            
            $result = $this->userRepository->saveEntity($user);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'id' => $user->getId(),
                        'status' => $user->getStatus()
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to change user status'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to change user status: ' . $e->getMessage()
            ];
        }
    }
} 