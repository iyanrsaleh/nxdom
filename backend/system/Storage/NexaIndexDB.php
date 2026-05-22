<?php
namespace App\System\Storage;

/**
 * NexaIndexDB - PHP implementation similar to IndexedDB
 * Provides a database interface with object stores, transactions, and indexes
 */
class NexaIndexDB {
    private $dbPath;
    private $pdo;
    private $dbName;
    private $version;
    private $objectStores = [];
    
    public function __construct($dbName, $version = 1, $dbPath = null) {
        $this->dbName = $dbName;
        $this->version = $version;
        
        // Set default path to project's system/Storage/tabel directory
        if ($dbPath === null) {
            $projectRoot = dirname(dirname(__DIR__)); // Go up from system/Storage to dev/
            $dbPath = $projectRoot . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'tabel';
        }
        
        // Remove trailing slash/separator if exists to avoid double separators
        $dbPath = rtrim($dbPath, DIRECTORY_SEPARATOR . '/');
        
        // Ensure tabel directory exists
        if (!is_dir($dbPath)) {
            mkdir($dbPath, 0755, true);
        }
        
        // Create database file in tabel directory
        $this->dbPath = $dbPath . DIRECTORY_SEPARATOR . $dbName . '.db';
        $this->connect();
    }
    
    /**
     * Connect to SQLite database
     */
    private function connect() {
        try {
            $this->pdo = new \PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Check database integrity
            $this->checkDatabaseIntegrity();
            
            // Create metadata table if not exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS _nexa_metadata (
                    key TEXT PRIMARY KEY,
                    value TEXT
                )
            ");
            
            // Store version
            $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO _nexa_metadata (key, value) VALUES (?, ?)");
            $stmt->execute(['version', $this->version]);
            
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check database integrity and fix corrupted indexes
     */
    private function checkDatabaseIntegrity() {
        try {
            // Run integrity check
            $stmt = $this->pdo->query("PRAGMA integrity_check");
            $result = $stmt->fetchColumn();
            
            if ($result !== 'ok') {
                // If integrity check fails, try to fix corrupted indexes
                $this->fixCorruptedIndexes();
            }
        } catch (\PDOException $e) {
            // If integrity check itself fails, likely due to corrupted schema
            $this->fixCorruptedIndexes();
        }
    }
    
    /**
     * Fix corrupted indexes by dropping and recreating them
     */
    private function fixCorruptedIndexes() {
        try {
            // Get list of indexes
            $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_%'");
            $indexes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($indexes as $indexName) {
                try {
                    $this->pdo->exec("DROP INDEX IF EXISTS `{$indexName}`");
                } catch (\PDOException $e) {
                    // Continue even if drop fails
                    continue;
                }
            }
        } catch (\PDOException $e) {
            // If we can't fix indexes, we might need to recreate the database
            $this->recreateDatabase();
        }
    }
    
    /**
     * Recreate database from scratch
     */
    private function recreateDatabase() {
        try {
            $this->pdo = null;
            if (file_exists($this->dbPath)) {
                unlink($this->dbPath);
            }
            $this->pdo = new \PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new \Exception("Failed to recreate database: " . $e->getMessage());
        }
    }
    
    /**
     * Create an object store (similar to IndexedDB object store)
     */
    public function createObjectStore($storeName, $options = []) {
        $keyPath = isset($options['keyPath']) ? $options['keyPath'] : 'id';
        $autoIncrement = isset($options['autoIncrement']) ? $options['autoIncrement'] : false;
        
        $this->objectStores[$storeName] = [
            'keyPath' => $keyPath,
            'autoIncrement' => $autoIncrement,
            'indexes' => []
        ];
        
        // Create table for object store
        $autoIncrementSQL = $autoIncrement ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'TEXT PRIMARY KEY';
        
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$storeName}` (
                `{$keyPath}` {$autoIncrementSQL},
                `data` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $this->pdo->exec($sql);
        
        return new NexaObjectStore($this->pdo, $storeName, $this->objectStores[$storeName]);
    }
    
    /**
     * Get an existing object store
     */
    public function getObjectStore($storeName) {
        // Check if table exists
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$storeName]);
        
        if (!$stmt->fetch()) {
            throw new \Exception("Object store '{$storeName}' does not exist");
        }
        
        // Get store configuration from metadata or use defaults
        if (!isset($this->objectStores[$storeName])) {
            $this->objectStores[$storeName] = [
                'keyPath' => 'id',
                'autoIncrement' => false,
                'indexes' => []
            ];
        }
        
        return new NexaObjectStore($this->pdo, $storeName, $this->objectStores[$storeName]);
    }
    
    /**
     * Delete an object store
     */
    public function deleteObjectStore($storeName) {
        $this->pdo->exec("DROP TABLE IF EXISTS `{$storeName}`");
        unset($this->objectStores[$storeName]);
    }
    
    /**
     * Start a transaction
     */
    public function transaction($storeNames, $mode = 'readonly') {
        return new NexaTransaction($this->pdo, $storeNames, $mode, $this->objectStores);
    }
    
    /**
     * Close database connection
     */
    public function close() {
        $this->pdo = null;
    }
    
    /**
     * Get database info
     */
    public function getInfo() {
        return [
            'name' => $this->dbName,
            'version' => $this->version,
            'path' => $this->dbPath,
            'objectStores' => array_keys($this->objectStores)
        ];
    }
    
    /**
     * Repair database by dropping corrupted indexes and recreating them
     */
    public function repairDatabase() {
        try {
            $this->fixCorruptedIndexes();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database health status
     */
    public function getHealthStatus() {
        try {
            $stmt = $this->pdo->query("PRAGMA integrity_check");
            $result = $stmt->fetchColumn();
            return $result === 'ok';
        } catch (\PDOException $e) {
            return false;
        }
    }
}

/**
 * NexaObjectStore - Represents an object store within the database
 */
class NexaObjectStore {
    private $pdo;
    private $storeName;
    private $config;
    
    public function __construct($pdo, $storeName, $config) {
        $this->pdo = $pdo;
        $this->storeName = $storeName;
        $this->config = $config;
    }
    
    /**
     * Add data to the object store
     */
    public function add($data, $key = null) {
        $keyPath = $this->config['keyPath'];
        $autoIncrement = $this->config['autoIncrement'];
        
        // If key is provided, use it; otherwise use keyPath from data or auto-generate
        if ($key !== null) {
            $keyValue = $key;
        } elseif (is_array($data) && isset($data[$keyPath])) {
            $keyValue = $data[$keyPath];
        } elseif (is_object($data) && isset($data->$keyPath)) {
            $keyValue = $data->$keyPath;
        } elseif ($autoIncrement) {
            $keyValue = null; // Let SQLite auto-increment
        } else {
            throw new \Exception("Key value is required for non-auto-increment stores");
        }
        
        $jsonData = json_encode($data);
        
        if ($autoIncrement && $keyValue === null) {
            $stmt = $this->pdo->prepare("INSERT INTO `{$this->storeName}` (`data`) VALUES (?)");
            $stmt->execute([$jsonData]);
            return $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO `{$this->storeName}` (`{$keyPath}`, `data`) VALUES (?, ?)");
            $stmt->execute([$keyValue, $jsonData]);
            return $keyValue;
        }
    }
    
    /**
     * Get data by key
     */
    public function get($key) {
        $keyPath = $this->config['keyPath'];
        $stmt = $this->pdo->prepare("SELECT `data` FROM `{$this->storeName}` WHERE `{$keyPath}` = ?");
        $stmt->execute([$key]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            return json_decode($result['data'], true);
        }
        
        return null;
    }
    
    /**
     * Update data (put)
     */
    public function put($data, $key = null) {
        $keyPath = $this->config['keyPath'];
        
        if ($key !== null) {
            $keyValue = $key;
        } elseif (is_array($data) && isset($data[$keyPath])) {
            $keyValue = $data[$keyPath];
        } elseif (is_object($data) && isset($data->$keyPath)) {
            $keyValue = $data->$keyPath;
        } else {
            throw new \Exception("Key value is required");
        }
        
        $jsonData = json_encode($data);
        
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO `{$this->storeName}` 
            (`{$keyPath}`, `data`, `updated_at`) 
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$keyValue, $jsonData]);
        
        return $keyValue;
    }
    
    /**
     * Delete data by key
     */
    public function delete($key) {
        $keyPath = $this->config['keyPath'];
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->storeName}` WHERE `{$keyPath}` = ?");
        $stmt->execute([$key]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Clear all data from the store
     */
    public function clear() {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->storeName}`");
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Get all data
     */
    public function getAll($query = null, $count = null) {
        $sql = "SELECT `data` FROM `{$this->storeName}`";
        $params = [];
        
        if ($query !== null) {
            // Simple query implementation - can be extended
            $sql .= " WHERE `data` LIKE ?";
            $params[] = "%{$query}%";
        }
        
        if ($count !== null) {
            $sql .= " LIMIT ?";
            $params[] = $count;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = json_decode($row['data'], true);
        }
        
        return $results;
    }
    
    /**
     * Count records
     */
    public function count() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->storeName}`");
        $stmt->execute();
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Create an index
     */
    public function createIndex($indexName, $keyPath, $options = []) {
        $unique = isset($options['unique']) ? $options['unique'] : false;
        $uniqueSQL = $unique ? 'UNIQUE' : '';
        
        // Clean index name to avoid double suffixes
        $cleanIndexName = str_replace('_idx', '', $indexName);
        $fullIndexName = "idx_{$this->storeName}_{$cleanIndexName}";
        
        try {
            // First, try to drop any existing corrupted index
            $this->pdo->exec("DROP INDEX IF EXISTS `{$fullIndexName}`");
            
            // For now, skip creating actual SQLite indexes on JSON data
            // This avoids the json_extract syntax issues while maintaining functionality
            // The index configuration is still stored for application-level querying
            
            $this->config['indexes'][$indexName] = [
                'keyPath' => $keyPath,
                'unique' => $unique
            ];
        } catch (\PDOException $e) {
            // If index operations fail, log the error but don't crash the application
            error_log("Failed to manage index {$fullIndexName}: " . $e->getMessage());
            
            // Still store the index configuration for potential future use
            $this->config['indexes'][$indexName] = [
                'keyPath' => $keyPath,
                'unique' => $unique
            ];
        }
    }
    
    /**
     * Get data by index
     */
    public function getByIndex($indexName, $value) {
        if (!isset($this->config['indexes'][$indexName])) {
            throw new \Exception("Index '{$indexName}' does not exist");
        }
        
        $keyPath = $this->config['indexes'][$indexName]['keyPath'];
        
        // Use a simple approach that works across all SQLite versions
        // Get all records and filter in PHP (less efficient but more compatible)
        $stmt = $this->pdo->prepare("SELECT `data` FROM `{$this->storeName}`");
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['data'], true);
            if (isset($data[$keyPath]) && $data[$keyPath] == $value) {
                $results[] = $data;
            }
        }
        
        return $results;
    }
}

/**
 * NexaTransaction - Handle database transactions
 */
class NexaTransaction {
    private $pdo;
    private $storeNames;
    private $mode;
    private $objectStores;
    private $stores = [];
    
    public function __construct($pdo, $storeNames, $mode, $objectStores) {
        $this->pdo = $pdo;
        $this->storeNames = is_array($storeNames) ? $storeNames : [$storeNames];
        $this->mode = $mode;
        $this->objectStores = $objectStores;
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        // Initialize stores for this transaction
        foreach ($this->storeNames as $storeName) {
            $this->stores[$storeName] = new NexaObjectStore($pdo, $storeName, $this->objectStores[$storeName]);
        }
    }
    
    /**
     * Get object store within transaction
     */
    public function objectStore($storeName) {
        if (!in_array($storeName, $this->storeNames)) {
            throw new \Exception("Store '{$storeName}' is not included in this transaction");
        }
        
        return $this->stores[$storeName];
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
}

?>
