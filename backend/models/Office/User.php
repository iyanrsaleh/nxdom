<?php
namespace App\Models\Office;
use App\System\NexaModel;

/**
 * Model Control - Mengelola manajemen controller dan akses pengguna
 * 
 * Model ini mengelola controller, hak akses pengguna, dan kontrol akses
 * untuk aplikasi. Extends NexaModel untuk operasi database.
 * 
 * @package App\Models\Role
 * @extends NexaModel
 */
class User extends NexaModel
{
    /**
     * Nama tabel database untuk controllers
     * @var string
     */
    protected $table = 'controllers';
    
    /**
     * Nama tabel database untuk users
     * @var string
     */
    protected $user = 'user';
    
    /**
     * Field yang dapat diisi untuk mass assignment
     * @var array
     */
    protected $fillable = ['name', 'email', 'password'];
    
    /**
     * Penampung instance controller
     * @var mixed|null
     */
    private $controller = null;
    
    /**
     * Set instance controller
     * 
     * @param mixed $controller Instance controller yang akan di-set
     * @return self Mengembalikan instance saat ini untuk method chaining
     */
    public function setController($controller): self 
    {
        $this->controller = $controller;
        return $this;
    }

   


    public function access(string $search='', int $limit=5, int $page = 1, string $url=null): array 
    {
        $query = $this->Storage($this->user) 
            ->select(['id', 'nama', 'gender', 'avatar',
                "COALESCE(status, 'User') AS status",
                'email',
                'package'])
            ->where('id', '!=', '1');
            
        // Add search condition only if search term is provided
        if (!empty($search)) {
            $query->where('nama', 'LIKE', "%{$search}%");
        }
        
        $result = $query->orderBy("id", "DESC")
            ->paginate($page, $limit);
           
        // Process avatar berdasarkan gender di aplikasi level
        if (!empty($result['data']) && is_array($result)) {
            foreach ($result['data'] as &$user) {
                $user['badge'] = $this->getBadge($user['status'] ?? '');
                // Handle avatar URL properly
                if (!empty($user['avatar']) && $user['avatar'] !== 'null') {
                    $user['avatar'] = $url . $user['avatar'];
                } else {
                    // Use gender-based avatar if no avatar
                    $user['avatar'] = $this->getGenderBasedAvatar($user['avatar'] ?? '', $user['gender'] ?? '');
                }
            }
        }
           
        return $result ?? [];
    }

















   private function getBadge(string $status): string
    {
        if (strtolower($status) == 'admin') {
            // code...
          return 'nx-badge nx-soft-red';
        } elseif (strtolower($status) == 'moderator') {
            // code...
          return 'nx-badge nx-soft-yellow';
    
        } else {
          return 'nx-badge nx-soft-blue';
        }
    }



   private function getRole(string $status): string
    {
        if (strtolower($status) === 'select') {
          return 'box';
        } elseif (strtolower($status) == 'package') {
          return 'box';
        } else {
          return 'hidden';
        }
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
        $genderNormalized = $this->normalizeGender($gender);
        
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
    
    /**
     * Normalize gender value untuk handling typo dan case sensitivity
     * 
     * @param string $gender Raw gender value
     * @return string Normalized gender value
     */
    private function normalizeGender(string $gender): string
    {
        // Convert to lowercase dan trim
        $normalized = strtolower(trim($gender));
        
        // Remove common special characters
        $normalized = preg_replace('/[^a-z0-9\-]/', '', $normalized);
        
        // Handle common typos mapping
        $typoMap = [
            'mele' => 'male',
            'mel' => 'male', 
            'mae' => 'male',
            'mal' => 'male',
            'femal' => 'female',
            'fem' => 'female',
            'fmale' => 'female',
            'femle' => 'female',
            'waita' => 'wanita',
            'wnita' => 'wanita',
            'peria' => 'pria',
            'pira' => 'pria'
        ];
        
        // Apply typo correction
        if (isset($typoMap[$normalized])) {
            $normalized = $typoMap[$normalized];
        }
        
        return $normalized;
    }

    /**
     * Ambil data pengguna spesifik berdasarkan ID COALESCE(status, "User") AS status
     * 
     * @param int $id ID pengguna yang akan dicari
     * @return array Mengembalikan data pengguna dengan field yang dipilih
     */
    public function userById(int $id) 
    {
        $result= $this->Storage($this->user)
            ->select(['id', 'nama','email', 'avatar','status AS status_role','package','gender'])
            ->where('id', $id)
            ->orderBy("id", "DESC")
            ->get();

       if (!empty($result) && is_array($result)) {
            foreach ($result as &$user) {
                $user['badge'] = $this->getBadge($user['status_role'] ?? '');
                $user['photo'] = $this->getGenderBasedAvatar($user['avatar'] ?? '', $user['gender'] ?? '');
            }
        }

          return $result ?? [];

    }


    public function redAllUser() 
    {
            $result=$this->Storage($this->user)
                ->select(['id', 'nama','email', 'avatar AS photo','status AS status_role','package','gender',"jabatan"])
                
                ->orderBy("userid", "ASC") 
                ->get();

        if (!empty($result) && is_array($result)) {
            foreach ($result as &$user) {
                $user['badge'] = $this->getBadge($user['status_role'] ?? '');
                $user['photo'] = $this->getGenderBasedAvatar($user['photo'] ?? '', $user['gender'] ?? '');
            }
        }

          return $result ?? []; 
    }

    public function redUser() 
    {
            $result=$this->Storage($this->user)
                ->select(['id', 'nama','email', 'avatar AS photo','status AS status_role','package','gender',"jabatan"])
                ->where('role',1)
                ->orderBy("userid", "ASC") 
                ->get();

        if (!empty($result) && is_array($result)) {
            foreach ($result as &$user) {
                $user['badge'] = $this->getBadge($user['status_role'] ?? '');
                $user['photo'] = $this->getGenderBasedAvatar($user['photo'] ?? '', $user['gender'] ?? '');
            }
        }

          return $result ?? []; 
    }

    /**
     * Ambil keywords package berdasarkan ID controller
     * 
     * Mengambil keywords/label yang terkait dengan controller spesifik
     * untuk keperluan manajemen package.
     * 
     * @param int $id ID controller untuk mengambil data package
     * @return array Mengembalikan data package dengan keywords
     */

    public function packageById3(string $id='') 
    {
        if ($id==1) {
            return $this->Storage($this->table)
                ->select(['label AS keywords'])
                ->where('id', 150)
                ->orderBy("id", "DESC")
                ->get();
        } else {
            return $this->Storage($this->user)
                ->select(['package AS keywords'])
                ->where('id', $id)
                ->orderBy("id", "DESC")
                ->get();
        }
    }




    public function packageById(int $id) 
    {
        $result = $this->Storage($this->user)
            ->select(['package AS keywords'])
            ->where('id', $id)
            ->orderBy("id", "DESC")
            ->get();

        // If we have results, filter out "User" from the keywords
        if (!empty($result) && isset($result[0]['keywords'])) {
            // Split the keywords string into array
            $keywords = explode(',', $result[0]['keywords']);
            // Remove "User" and empty values
            $keywords = array_filter($keywords, function($keyword) {
                return trim($keyword) !== 'User' && !empty(trim($keyword));
            });
            // Join back to string
            $result[0]['keywords'] = implode(',', $keywords);
        }

        return $result;
    }

    /**
     * Tambah/Update data package untuk pengguna spesifik
     * 
     * @param array $postData Data package yang akan di-update
     * @param int $id ID pengguna untuk update package
     * @return mixed Mengembalikan hasil operasi update
     */
    public function packageAdd(array $postData, int $id) 
    {
        return $this->Storage($this->user)->where('id', $id)->update($postData);
    }
}