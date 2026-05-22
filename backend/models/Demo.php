<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class Demo extends NexaModel
{
    protected $table = 'demo';

    private $controller = null;
    
    public function setController($controller): self {
        $this->controller = $controller;
        return $this;
    }

    public function send(array $data)
    {
        return $this->Storage($this->table)->insert($data);
    }
    public function sendUpdate(array $data, int $id): bool {
        // Hapus field id dari data untuk menghindari konflik
        unset($data['id']);
        
        return $this->Storage($this->table)->where('id', $id)->update($data);
    }
    
    /**
     * Ambil data berdasarkan ID
     * 
     * @param int $id ID record
     * @return array|null Data record atau null jika tidak ditemukan
     */
    public function getById(int $id): ?array {
        $result = $this->Storage($this->table)
            ->where('id', $id)
            ->first();
        
        return $result ?: null;
    }
    
    /**
     * Hapus data berdasarkan ID
     * 
     * @param int $id ID record yang akan dihapus
     * @return bool True jika berhasil, false jika gagal
     */
    public function deleteById(int $id): bool {
        return $this->Storage($this->table)
            ->where('id', $id)
            ->delete();
    }
    
    public function All(): array {
        $results = $this->Storage('demo') 
            ->get();
        
        return $results ?? [];
    }

} 