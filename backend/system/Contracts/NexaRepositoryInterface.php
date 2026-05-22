<?php
namespace App\System\Contracts;

/**
 * NexaRepositoryInterface - Kontrak Repository Dasar
 * Mendefinisikan kontrak untuk semua repository di framework NexaUI
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    Tim NexaUI
 */
interface NexaRepositoryInterface
{
    /**
     * Cari record berdasarkan ID
     */
    public function find(int $id): ?array;
    
    /**
     * Cari record berdasarkan kriteria tertentu
     */
    public function findBy(array $criteria): ?array;
    
    /**
     * Cari semua record yang sesuai kriteria
     */
    public function findAll(array $criteria = []): array;
    
    /**
     * Buat record baru
     */
    public function create(array $data): int;
    
    /**
     * Update record yang sudah ada
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Hapus record
     */
    public function delete(int $id): bool;
    
    /**
     * Cek apakah record ada
     */
    public function exists(int $id): bool;
    
    /**
     * Hitung jumlah record
     */
    public function count(array $criteria = []): int;
    
    /**
     * Dapatkan hasil dengan paginasi
     */
    public function paginate(int $page = 1, int $perPage = 10, array $criteria = []): array;
    
    /**
     * Mulai transaksi
     */
    public function beginTransaction(): void;
    
    /**
     * Commit transaksi
     */
    public function commit(): void;
    
    /**
     * Rollback transaksi
     */
    public function rollback(): void;
} 