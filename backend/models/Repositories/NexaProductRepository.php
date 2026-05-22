<?php
namespace app\models\Repositories;

use App\System\Repositories\NexaBaseRepository;

/**
 * NexaProductRepository - Product Repository Implementation  
 * Handles Product data persistence and retrieval
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
class NexaProductRepository extends NexaBaseRepository
{
    /**
     * Fields yang boleh diisi mass assignment
     */
    protected array $fillable = [
        // TODO: Tambahkan field yang boleh diisi
        // 'name', 'description', 'status'
    ];
    
    /**
     * Fields yang dilindungi dari mass assignment
     */
    protected array $guarded = [
        'id', 'created_at', 'updated_at'
    ];
    
    /**
     * Set the table name
     */
    protected function setTable(): void
    {
        $this->table = 'products';
    }
    
    /**
     * Find active Products
     */
    public function findActive(): array
    {
        return $this->findAll(['status' => 'active']);
    }
    
    /**
     * Custom method example - sesuaikan dengan kebutuhan bisnis
     */
    public function findByProductName(string $name): ?array
    {
        return $this->findBy(['name' => $name]);
    }
    
    // TODO: Tambahkan custom methods sesuai kebutuhan bisnis
    // Contoh:
    // public function findByCategory(int $categoryId): array
    // public function findExpensive(float $minPrice): array
    // public function markAsDeleted(int $id): bool
}