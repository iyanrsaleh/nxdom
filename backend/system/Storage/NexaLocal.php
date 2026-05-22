<?php
namespace App\System\Storage;

/**
 * NexaLocal - PHP Local Storage Class
 * Implementasi konsep localStorage JavaScript dalam PHP
 * Menggunakan file-based storage untuk menyimpan data secara lokal
 */
class NexaLocal
{
    private $storageDir;
    private $storageFile;
    private $data;
    
    /**
     * Constructor
     * @param string $identifier - Identifier unik untuk storage (default: 'default')
     * @param string $storageDir - Directory untuk menyimpan file storage
     */
    public function __construct($identifier = 'default', $storageDir = null)
    {
        // Set default storage directory
        if ($storageDir === null) {
            $storageDir = __DIR__ . '/data';
        }
        
        $this->storageDir = $storageDir;
        $this->storageFile = $this->storageDir . '/' . $identifier . '_localstorage.json';
        
        // Buat directory jika belum ada
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        
        // Load data yang sudah ada
        $this->loadData();
    }
    
    /**
     * Set item - Menyimpan data dengan key tertentu
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setItem($key, $value)
    {
        try {
            $this->data[$key] = $value;
            return $this->saveData();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get item - Mengambil data berdasarkan key
     * @param string $key
     * @param mixed $default - Nilai default jika key tidak ditemukan
     * @return mixed
     */
    public function getItem($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
    
    /**
     * Remove item - Menghapus data berdasarkan key
     * @param string $key
     * @return bool
     */
    public function removeItem($key)
    {
        try {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                return $this->saveData();
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Clear - Menghapus semua data
     * @return bool
     */
    public function clear()
    {
        try {
            $this->data = [];
            return $this->saveData();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Key - Mendapatkan key berdasarkan index
     * @param int $index
     * @return string|null
     */
    public function key($index)
    {
        $keys = array_keys($this->data);
        return isset($keys[$index]) ? $keys[$index] : null;
    }
    
    /**
     * Length - Mendapatkan jumlah item yang tersimpan
     * @return int
     */
    public function length()
    {
        return count($this->data);
    }
    
    /**
     * Get all data - Mendapatkan semua data
     * @return array
     */
    public function getAllData()
    {
        return $this->data;
    }
    
    /**
     * Has key - Mengecek apakah key ada
     * @param string $key
     * @return bool
     */
    public function hasKey($key)
    {
        return isset($this->data[$key]);
    }
    
    /**
     * Get all keys - Mendapatkan semua key
     * @return array
     */
    public function getAllKeys()
    {
        return array_keys($this->data);
    }
    
    /**
     * Set multiple items - Menyimpan multiple data sekaligus
     * @param array $items
     * @return bool
     */
    public function setMultiple($items)
    {
        try {
            foreach ($items as $key => $value) {
                $this->data[$key] = $value;
            }
            return $this->saveData();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get multiple items - Mengambil multiple data sekaligus
     * @param array $keys
     * @return array
     */
    public function getMultiple($keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }
        return $result;
    }
    
    /**
     * Remove multiple items - Menghapus multiple data sekaligus
     * @param array $keys
     * @return bool
     */
    public function removeMultiple($keys)
    {
        try {
            foreach ($keys as $key) {
                if (isset($this->data[$key])) {
                    unset($this->data[$key]);
                }
            }
            return $this->saveData();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Load data dari file
     * @return void
     */
    private function loadData()
    {
        if (file_exists($this->storageFile)) {
            $jsonData = file_get_contents($this->storageFile);
            $this->data = json_decode($jsonData, true) ?: [];
        } else {
            $this->data = [];
        }
    }
    
    /**
     * Save data ke file
     * @return bool
     */
    private function saveData()
    {
        try {
            $jsonData = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return file_put_contents($this->storageFile, $jsonData) !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get storage file path
     * @return string
     */
    public function getStorageFilePath()
    {
        return $this->storageFile;
    }
    
    /**
     * Get storage size in bytes
     * @return int
     */
    public function getStorageSize()
    {
        return file_exists($this->storageFile) ? filesize($this->storageFile) : 0;
    }
    
    /**
     * Export data to array
     * @return array
     */
    public function export()
    {
        return [
            'data' => $this->data,
            'length' => $this->length(),
            'file_path' => $this->storageFile,
            'file_size' => $this->getStorageSize(),
            'last_modified' => file_exists($this->storageFile) ? filemtime($this->storageFile) : null
        ];
    }
    
    /**
     * Import data from array
     * @param array $data
     * @return bool
     */
    public function import($data)
    {
        try {
            if (is_array($data)) {
                $this->data = $data;
                return $this->saveData();
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Magic method untuk mendukung syntax seperti property
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getItem($key);
    }
    
    /**
     * Magic method untuk mendukung syntax seperti property
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->setItem($key, $value);
    }
    
    /**
     * Magic method untuk mendukung isset()
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->hasKey($key);
    }
    
    /**
     * Magic method untuk mendukung unset()
     * @param string $key
     */
    public function __unset($key)
    {
        $this->removeItem($key);
    }
}
