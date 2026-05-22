<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class User extends NexaModel
{
    protected $table = 'user';
    protected $fillable = ['name', 'email', 'password', 'avatar'];
    
    private $controller = null;
    
    public function setController($controller): self {
        $this->controller = $controller;
        return $this;
    }

    public function byId(int $id): array {
        $result=$this->Storage($this->table) 
            ->select([
                'id', 
                'nama', 
                'role',
                'email', 
                'password',
                'avatar',
                'status',
                'telepon',
                'nik',
                'alamat',
                'gender',
                'token',
                'expired'
            ])
            ->where('id', $id)
            ->first();

        if (!empty($result) && is_array($result)) {
            $result['avatar'] = $this->getGenderBasedAvatar($result['avatar'] ?? '', $result['gender'] ?? '');
        }
        return $result ?? [];
    }


  public function Team()
   {
       $result=$this->Storage('user') 
            ->select("id,id AS kode,nama,jabatan")
            ->orderBy("id", "ASC") 
            ->get();
    
        return $result ?? [];
   }

   
  public function TeamID($id)
   {
       $result=$this->Storage('user') 
            ->select("id,id AS kode,nama,jabatan")
            ->where('id', $id)
            ->first();
    
        return $result ?? [];
   }


    public function byUser(){
        $result=$this->Storage($this->table) 
            ->select([
                'id', 
                'nama', 
                'email', 
            ])
            ->get();
        if (!empty($result) && is_array($result)) {
            foreach ($result as $key => $user) {
               $user['avatar'] = $this->getGenderBasedAvatar($user['avatar'] ?? '', $user['gender'] ?? '');
            }
        }
        return $result ?? [];
    }


    public function byAvatar(int $id): array {
        $result=$this->Storage($this->table) 
            ->select([
                'id',
                'nama',
                'avatar',
                'gender',
            ])
            ->where('id', $id)
            ->first();

        if (!empty($result) && is_array($result)) {
            $result['avatar'] = $this->getGenderBasedAvatar($result['avatar'] ?? '', $result['gender'] ?? '');
        }
        return $result ?? [];
    }
    /**
     * Get avatar berdasarkan gender - Application level processing
     * Enhanced dengan typo detection dan variasi gender yang lebih luas
     * 
     * @param string $avatar Current avatar path
     * @param string $gender User gender
     * @return string Avatar path
     */
    private function getGenderBasedAvatar(string $avatar, string $gender): string
    {
        // Jika sudah ada avatar, gunakan yang ada
        if (!empty($avatar) && $avatar !== 'null') {
            return $avatar;
        }
        
        // Normalize gender untuk handling typo dan variasi
        $genderNormalized =$gender;
        
        // Male variants (termasuk typo umum)
        if (in_array($genderNormalized, [
            'male', 'm', 'laki-laki', 'pria', '1', 'l',
            // Typo umum
            'mele', 'mel', 'male!', 'mae', 'mal',
            // Variasi lain
            'cowok', 'cowo', 'boy', 'man', 'gentleman'
        ])) {
            return 'images/pria.png';
        }
        
        // Female variants (termasuk typo umum)
        if (in_array($genderNormalized, [
            'female', 'f', 'perempuan', 'wanita', '2', 'p',
            // Typo umum
            'femal', 'fem', 'female!', 'fmale', 'femle',
            // Variasi lain
            'cewek', 'cewe', 'girl', 'woman', 'lady'
        ])) {
            return 'images/wanita.png';
        }
        
        // Default fallback
        return 'images/wanita.png';
    }
    public function settings(array $data, int $id): array {
         $result = $this->Storage($this->table)->where('id', $id)->update($data);
         return ['success' => $result, 'affected_rows' => $result ? 1 : 0];
    }


    public function updatePhoto(array $fileData, int $id): array {
         // Update avatar and thumbnails in database
         $updateData = [];
         
         if (isset($fileData['path'])) {
             $updateData['avatar'] = 'avatar/' . $fileData['path'];
         }
         
         
         if (!empty($updateData)) {
             $result = $this->Storage($this->table)
                 ->where('id', $id)
                 ->update([
                     'avatar'=>'avatar/' . $fileData['path']
                 ]);
             return [
                 'success' => $result, 
                 'affected_rows' => $result ? 1 : 0,
                 'avatar_path' => 'avatar/' . $fileData['path']
             ];
         }
         
         return ['success' => false, 'error' => 'No photo data to update'];
    }

    public function updatePassword(int $id, string $hashedPassword): array {
         $result = $this->Storage($this->table)
             ->where('id', $id)
             ->update(['password' => $hashedPassword]);
         return ['success' => $result, 'affected_rows' => $result ? 1 : 0];
    }

    /**
     * Update nomor telepon - ONE TIME ONLY
     * Nomor telepon hanya bisa diupdate jika sebelumnya NULL/kosong
     * 
     * @param string $telepon Nomor telepon baru
     * @param int $id User ID
     * @return array Result array dengan success status
     */
    public function updateTelepon(string $telepon, int $id): array {
         // Get current phone number untuk security check
         $currentUser = $this->Storage($this->table)
             ->select(['telepon'])
             ->where('id', $id)
             ->first();
         
         // Security: Jika nomor sudah ada, jangan update
         if (!empty($currentUser['telepon'])) {
             return [
                 'success' => false, 
                 'error' => 'Phone number already exists and cannot be changed',
                 'affected_rows' => 0
             ];
         }
         
         // Update nomor telepon (hanya jika kosong)
         $result = $this->Storage($this->table)
             ->where('id', $id)
             ->update(['telepon' => $telepon]);
             
         return [
             'success' => $result, 
             'affected_rows' => $result ? 1 : 0,
             'message' => $result ? 'Phone number saved permanently' : 'Failed to save phone number',
             'telepon' => $telepon
         ];
    }

    /** Daftar user + pagination; $search dicocokkan ke nama, email, atau telepon (LIKE OR) */
    public function list(int $page = 1, string $search = '', int $perPage = 5): array
    {
        $search = trim($search);
        $q = $this->Storage($this->table)
            ->select(['id', 'nama', 'email', 'role', 'package', 'avatar', 'status', 'telepon'])
            ->orderBy('id', 'ASC');
        if ($search !== '') {
            $term = '%' . addcslashes($search, '%_\\') . '%';
            $q->where('nama', 'LIKE', $term);
            $q->orWhere('email', 'LIKE', $term);
            $q->orWhere('telepon', 'LIKE', $term);
        }
        $result = $q->paginate($page, $perPage);
        return $result ?? ['data' => [], 'total' => 0, 'last_page' => 1, 'current_page' => $page, 'per_page' => $perPage];
    }

    public function getActiveUsers(): array {
        return [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'active']
        ];
    }
    
    public function findByEmail(string $email): ?array {
        return [
            'id' => 1,
            'name' => 'John Doe',
            'email' => $email,
            'table' => $this->table
        ];
    }
} 