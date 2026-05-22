<?php
namespace App\System\Repositories;

use App\System\Contracts\NexaRepositoryInterface;
use App\System\NexaModel;
use Exception;

/**
 * NexaBaseRepository - Implementasi Repository Dasar
 * Menyediakan fungsionalitas repository umum menggunakan NexaModel
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    Tim NexaUI
 */
abstract class NexaBaseRepository implements NexaRepositoryInterface
{
    protected NexaModel $model;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = [];
    
    /**
     * Konstruktor
     */
    public function __construct(NexaModel $model)
    {
        $this->model = $model;
        $this->setTable();
    }
    
    /**
     * Tentukan nama tabel - harus diimplementasikan oleh kelas turunan
     */
    abstract protected function setTable(): void;
    
    /**
     * Cari record berdasarkan ID
     */
    public function find(int $id): ?array
    {
        try {
            $result = $this->model->table($this->table)
                ->where($this->primaryKey, $id)
                ->first();
                
            return $result ?: null;
        } catch (Exception $e) {
            $this->logError("Kesalahan mencari record berdasarkan ID: {$id}", $e);
            return null;
        }
    }
    
    /**
     * Cari record berdasarkan kriteria tertentu
     */
    public function findBy(array $criteria): ?array
    {
        try {
            $query = $this->model->table($this->table);
            
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->first() ?: null;
        } catch (Exception $e) {
            $this->logError("Kesalahan mencari record berdasarkan kriteria", $e);
            return null;
        }
    }
    
    /**
     * Cari semua record yang sesuai kriteria
     */
    public function findAll(array $criteria = []): array
    {
        try {
            $query = $this->model->table($this->table);
            
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->get() ?: [];
        } catch (Exception $e) {
            $this->logError("Kesalahan mencari semua record", $e);
            return [];
        }
    }
    
    /**
     * Buat record baru
     */
    public function create(array $data): int
    {
        try {
            // Filter data berdasarkan aturan fillable/guarded
            $filteredData = $this->filterData($data);
            
            $result = $this->model->table($this->table)->insert($filteredData);
            
            if ($result) {
                // Jalankan domain event
                $this->fireEvent('created', ['data' => $filteredData, 'id' => $result]);
                return $result;
            }
            
            throw new Exception("Gagal membuat record");
        } catch (Exception $e) {
            $this->logError("Kesalahan membuat record", $e);
            throw $e;
        }
    }
    
    /**
     * Update record yang sudah ada
     */
    public function update(int $id, array $data): bool
    {
        try {
            // Filter data berdasarkan aturan fillable/guarded
            $filteredData = $this->filterData($data);
            
            $result = $this->model->table($this->table)
                ->where($this->primaryKey, $id)
                ->update($filteredData);
            
            if ($result) {
                // Jalankan domain event
                $this->fireEvent('updated', ['id' => $id, 'data' => $filteredData]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logError("Kesalahan update record ID: {$id}", $e);
            return false;
        }
    }
    
    /**
     * Hapus record
     */
    public function delete(int $id): bool
    {
        try {
            $result = $this->model->table($this->table)
                ->where($this->primaryKey, $id)
                ->delete();
            
            if ($result) {
                // Jalankan domain event
                $this->fireEvent('deleted', ['id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logError("Kesalahan menghapus record ID: {$id}", $e);
            return false;
        }
    }
    
    /**
     * Cek apakah record ada
     */
    public function exists(int $id): bool
    {
        try {
            return $this->model->table($this->table)
                ->where($this->primaryKey, $id)
                ->exists();
        } catch (Exception $e) {
            $this->logError("Kesalahan mengecek record ada ID: {$id}", $e);
            return false;
        }
    }
    
    /**
     * Hitung jumlah record
     */
    public function count(array $criteria = []): int
    {
        try {
            $query = $this->model->table($this->table);
            
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->count();
        } catch (Exception $e) {
            $this->logError("Kesalahan menghitung record", $e);
            return 0;
        }
    }
    
    /**
     * Dapatkan hasil dengan paginasi
     */
    public function paginate(int $page = 1, int $perPage = 10, array $criteria = []): array
    {
        try {
            $query = $this->model->table($this->table);
            
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->paginate($page, $perPage);
        } catch (Exception $e) {
            $this->logError("Kesalahan paginasi record", $e);
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
     * Mulai transaksi
     */
    public function beginTransaction(): void
    {
        $this->model->transaction(function() {
            // Transaksi akan ditangani oleh callback
        });
    }
    
    /**
     * Commit transaksi
     */
    public function commit(): void
    {
        // Ditangani oleh metode transaksi NexaModel
    }
    
    /**
     * Rollback transaksi
     */
    public function rollback(): void
    {
        // Ditangani oleh metode transaksi NexaModel
    }
    
    /**
     * Filter data berdasarkan aturan fillable/guarded
     */
    protected function filterData(array $data): array
    {
        if (!empty($this->fillable)) {
            // Hanya izinkan field fillable
            return array_intersect_key($data, array_flip($this->fillable));
        }
        
        if (!empty($this->guarded)) {
            // Hapus field guarded
            return array_diff_key($data, array_flip($this->guarded));
        }
        
        return $data;
    }
    
    /**
     * Jalankan domain event
     */
    protected function fireEvent(string $event, array $data): void
    {
        // Akan diimplementasikan ketika menambahkan Event System
        // Untuk sementara, hanya log event
        error_log("Domain Event: {$this->table}.{$event} - " . json_encode($data));
    }
    
    /**
     * Log kesalahan
     */
    protected function logError(string $message, Exception $e): void
    {
        error_log("Repository Error [{$this->table}]: {$message} - " . $e->getMessage());
    }
} 