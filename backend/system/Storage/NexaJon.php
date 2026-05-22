<?php
namespace App\System\Storage;

/**
 * NexaJon - JSON Storage Class
 * Class untuk menyimpan dan mengelola data JSON dalam format apapun
 * Menerima data statik dari method dataGlobal() tanpa mengubah strukturnya
 */
class NexaJon
{
    private $storageDir;
    private $data;
    
    /**
     * Constructor
     * @param string $storageDir - Directory untuk menyimpan file JSON (default: __DIR__)
     */
    public function __construct($storageDir = null)
    {
        // Set default storage directory
        if ($storageDir === null) {
            $storageDir = __DIR__;
        }
        
        $this->storageDir = $storageDir;
        
        // Buat directory jika belum ada
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        // Initialize data kosong - tidak memaksa struktur tertentu
        $this->data = null;
    }
    
    /**
     * Set type - Set tipe menu (opsional, hanya jika data sudah ada struktur)
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        if (is_array($this->data)) {
            $this->data['type'] = $type;
        }
        return $this;
    }
    
    /**
     * Set menu data - Set data menu (opsional, hanya jika data sudah ada struktur)
     * @param array $menuData
     * @return self
     */
    public function setMenuData($menuData)
    {
        if (is_array($this->data)) {
            $this->data['menuData'] = $menuData;
        }
        return $this;
    }
    
    /**
     * Add menu item - Menambahkan item menu (opsional, hanya jika data sudah ada struktur)
     * @param array $menuItem - Array dengan keys: class, label, handler, children
     * @return self
     */
    public function addMenuItem($menuItem)
    {
        if (is_array($this->data) && isset($this->data['menuData']) && is_array($this->data['menuData'])) {
            $this->data['menuData'][] = $menuItem;
        }
        return $this;
    }
    
    /**
     * Set brand config - Set konfigurasi brand (opsional, hanya jika data sudah ada struktur)
     * @param array $brandConfig - Array dengan keys: href, logo, alt, text
     * @return self
     */
    public function setBrandConfig($brandConfig)
    {
        if (is_array($this->data) && isset($this->data['brandConfig']) && is_array($this->data['brandConfig'])) {
            $this->data['brandConfig'] = array_merge($this->data['brandConfig'], $brandConfig);
        } elseif (is_array($this->data)) {
            $this->data['brandConfig'] = $brandConfig;
        }
        return $this;
    }
    
    /**
     * Set data - Set semua data sekaligus (menyimpan data apa adanya tanpa modifikasi)
     * @param mixed $data - Data dalam format apapun (array, object, dll)
     * @return self
     */
    public function setData($data)
    {
        // Simpan data apa adanya tanpa mengubah struktur
        $this->data = $data;
        return $this;
    }
    
    /**
     * Get data - Mendapatkan semua data
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * Get menu data - Mendapatkan data menu (jika ada)
     * @return mixed|null
     */
    public function getMenuData()
    {
        return (is_array($this->data) && isset($this->data['menuData'])) ? $this->data['menuData'] : null;
    }
    
    /**
     * Get brand config - Mendapatkan konfigurasi brand (jika ada)
     * @return mixed|null
     */
    public function getBrandConfig()
    {
        return (is_array($this->data) && isset($this->data['brandConfig'])) ? $this->data['brandConfig'] : null;
    }
    
    /**
     * Get type - Mendapatkan tipe menu (jika ada)
     * @return mixed|null
     */
    public function getType()
    {
        return (is_array($this->data) && isset($this->data['type'])) ? $this->data['type'] : null;
    }
    
    /**
     * Save - Menyimpan data ke file JSON (menyimpan data apa adanya tanpa modifikasi)
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return bool
     */
    public function save($filename = 'menu_config.json')
    {
        try {
            if ($this->data === null) {
                error_log("NexaJon save error: Data is null");
                return false;
            }
            
            $filePath = $this->storageDir . '/' . $filename;
            // Simpan data apa adanya tanpa mengubah struktur
            $jsonData = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return file_put_contents($filePath, $jsonData) !== false;
        } catch (\Exception $e) {
            error_log("NexaJon save error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load - Memuat data dari file JSON ke instance
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return bool
     */
    public function load($filename = 'menu_config.json')
    {
        try {
            $filePath = $this->storageDir . '/' . $filename;
            if (file_exists($filePath)) {
                $jsonData = file_get_contents($filePath);
                $loadedData = json_decode($jsonData, true);
                if ($loadedData !== null) {
                    $this->data = $loadedData;
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            error_log("NexaJon load error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get - Membaca dan mengembalikan data dari file JSON tanpa memuat ke instance
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return mixed|null - Mengembalikan data dari file atau null jika file tidak ada/gagal
     */
    public function get($filename = 'menu_config.json')
    {
        try {
            $filePath = $this->storageDir . '/' . $filename;
            if (!file_exists($filePath)) {
                return null;
            }
            
            $jsonData = file_get_contents($filePath);
            if ($jsonData === false) {
                return null;
            }
            
            // Decode JSON data
            $data = json_decode($jsonData, true);
            
            // Cek jika ada error JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("NexaJon get error: JSON decode failed - " . json_last_error_msg());
                return null;
            }
            
            // Kembalikan data (bisa null, array, object, dll sesuai isi file)
            return $data;
        } catch (\Exception $e) {
            error_log("NexaJon get error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete - Menghapus file JSON
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return bool
     */
    public function delete($filename = 'menu_config.json')
    {
        try {
            $filePath = $this->storageDir . '/' . $filename;
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
            return true;
        } catch (\Exception $e) {
            error_log("NexaJon delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exists - Mengecek apakah file JSON ada
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return bool
     */
    public function exists($filename = 'menu_config.json')
    {
        $filePath = $this->storageDir . '/' . $filename;
        return file_exists($filePath);
    }
    
    /**
     * Get file path - Mendapatkan path file JSON
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return string
     */
    public function getFilePath($filename = 'menu_config.json')
    {
        return $this->storageDir . '/' . $filename;
    }
    
    /**
     * Get file size - Mendapatkan ukuran file dalam bytes
     * @param string $filename - Nama file (default: 'menu_config.json')
     * @return int
     */
    public function getFileSize($filename = 'menu_config.json')
    {
        $filePath = $this->storageDir . '/' . $filename;
        return file_exists($filePath) ? filesize($filePath) : 0;
    }
    
    /**
     * Export - Export data sebagai array
     * @return array
     */
    public function export()
    {
        return [
            'data' => $this->data,
            'storage_dir' => $this->storageDir,
        ];
    }
    
    /**
     * To JSON - Konversi data ke JSON string (menyimpan data apa adanya tanpa modifikasi)
     * @param bool $prettyPrint - Format dengan indentasi (default: true)
     * @return string
     */
    public function toJson($prettyPrint = true)
    {
        if ($this->data === null) {
            return 'null';
        }
        
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($this->data, $flags);
    }
}

