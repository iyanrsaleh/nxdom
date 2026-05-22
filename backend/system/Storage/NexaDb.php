<?php
namespace App\System\Storage;
use PDO;
use PDOException;
use PDOStatement;
use Exception; 

interface DatabaseInterface {
    public function connect();
    public function close();
    public function prepare($sql);
    public function query($sql);
}

class NexaDb implements DatabaseInterface {
    const CONN_TYPE_PDO = 'pdo';
    const CONN_TYPE_MYSQLI = 'mysqli';
    private static $instance = null;
    private $connection = null;
    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private int $port;
    private string $charset;
    private $pdo = null;
    private $currentDatabase = null;

    private function __construct() {
        try {
            // Check if PDO MySQL extension is loaded
            if (!extension_loaded('pdo_mysql')) {
                throw new \Exception("PDO MySQL extension is not loaded. Please enable it in your php.ini file.");
            }

            // Validate required environment variables
            if (empty($_ENV['DB_HOST']) || empty($_ENV['DB_USERNAME']) || empty($_ENV['DB_DATABASE'])) {
                throw new \Exception("Database configuration missing. Required variables: DB_HOST, DB_USERNAME, DB_DATABASE");
            }

            $this->host = $_ENV['DB_HOST'];
            $this->username = $_ENV['DB_USERNAME'];
            $this->password = $_ENV['DB_PASSWORD'] ?? '';
            $this->database = $_ENV['DB_DATABASE'];
            $this->port = (int)($_ENV['DB_PORT'] ?? 3306);
            $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
            $this->connect();
        } catch (\Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect() {
        if ($this->connection !== null) {
            //;
            return;
        }

        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            );

            //error_log("NexaDb - Attempting connection with DSN: " . $dsn);

            $this->connection = new \PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            //error_log("NexaDb - Connection established successfully");
        } catch(\PDOException $e) {
            $errorMessage = sprintf(
                "Database connection failed: %s. DSN: %s, Username: %s",
                $e->getMessage(),
                $dsn,
                $this->username
            );
            error_log($errorMessage);
            throw new \Exception($errorMessage);
        }
    }

    /**
     * Prepare SQL statement
     * @param string $sql
     * @return \PDOStatement
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * Execute SQL query directly
     * @param string $sql
     * @return \PDOStatement
     */
    public function query($sql) {
        return $this->connection->query($sql);
    }

    /**
     * Get database connection
     * @return \PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Koneksi menggunakan mysqli
     * 
     * Method ini menggunakan ekstensi mysqli untuk koneksi ke MySQL/MariaDB
     * Keuntungan:
     * - Mudah digunakan untuk proyek sederhana
     * - Performa lebih baik untuk MySQL
     * - Mendukung fitur MySQL spesifik
     * 
     * @return \mysqli object koneksi mysqli
     * @throws \Exception jika koneksi gagal
     */
    public function connMysqli(): \mysqli {
        try {
            $this->connection = new \mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new \Exception("Koneksi gagal: " . $this->connection->connect_error);
            }
            
            return $this->connection;
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan PDO MySQL
     * 
     * Method ini menggunakan PDO untuk koneksi ke MySQL/MariaDB
     * Keuntungan:
     * - Lebih aman dari SQL injection
     * - Mendukung prepared statements
     * - Portable ke database lain
     * - Interface yang konsisten
     * 
     * @return \PDO object koneksi PDO
     * @throws \PDOException jika koneksi gagal
     */
    public function connPDO(): ?PDO
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            //error_log("Database connection established successfully");
            
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Koneksi menggunakan SQLite
     * 
     * Method ini menggunakan PDO untuk koneksi ke database SQLite
     * Keuntungan:
     * - Database berbasis file
     * - Tidak memerlukan server database
     * - Cocok untuk aplikasi kecil-menengah
     * - Mudah untuk backup (cukup copy file)
     * 
     * Catatan: Pastikan folder database memiliki permission yang benar
     * 
     * @return \PDO object koneksi PDO SQLite
     * @throws \PDOException jika koneksi gagal
     */
    public function connSQLite() {
        try {
            $path = $this->database; // path ke file .sqlite
            $this->connection = new \PDO("sqlite:" . $path);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->connection;
        } catch(\PDOException $e) {
            die("Koneksi SQLite gagal: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan PostgreSQL
     * 
     * Method ini menggunakan PDO untuk koneksi ke PostgreSQL
     * Keuntungan:
     * - Mendukung fitur advanced database
     * - Sangat baik untuk data kompleks
     * - Mendukung JSON native
     * - Skalabilitas tinggi
     * 
     * Requirement:
     * - Ekstensi pdo_pgsql terinstall
     * - PostgreSQL server (default port: 5432)
     * 
     * @return \PDO object koneksi PDO PostgreSQL
     * @throws \PDOException jika koneksi gagal
     */
    public function connPostgre() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database}";
            $this->connection = new \PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->connection;
        } catch(\PDOException $e) {
            die("Koneksi PostgreSQL gagal: " . $e->getMessage());
        }
    }

    /**
     * Koneksi menggunakan SQL Server
     * 
     * Method ini menggunakan PDO untuk koneksi ke Microsoft SQL Server
     * Keuntungan:
     * - Integrasi baik dengan produk Microsoft
     * - Fitur enterprise level
     * - Mendukung transaksi kompleks
     * 
     * Requirement:
     * - Microsoft SQL Server Driver untuk PHP
     * - Ekstensi pdo_sqlsrv terinstall
     * - SQL Server (default port: 1433)
     * 
     * @return \PDO object koneksi PDO SQL Server
     * @throws \PDOException jika koneksi gagal
     */
    public function connSQLSRV() {
        try {
            $dsn = "sqlsrv:Server={$this->host},{$this->port};Database={$this->database}";
            $this->connection = new \PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            return $this->connection;
        } catch(\PDOException $e) {
            die("Koneksi SQL Server gagal: " . $e->getMessage());
        }
    }

    /**
     * Menutup koneksi database
     * 
     * Method ini akan menutup koneksi database yang aktif
     * Penting untuk memanggil method ini setelah selesai menggunakan database
     * untuk menghemat resources server
     */
    public function close() {
        if($this->connection instanceof \PDO || $this->connection instanceof \mysqli) {
            $this->connection = null;
        }
    }

    public function isConnected(): bool {
        return $this->connection instanceof \PDO;
    }

    public function testConnection(): bool {
        try {
            if (!$this->isConnected()) {
                $this->connect();
            }
            
            // Test the connection with a simple query
            $this->connection->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            error_log("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    public function verifyConfiguration(): bool {
        try {
            //error_log("NexaDb - Verifying configuration...");
            
            // Check if environment variables are set
            $required = ['DB_HOST', 'DB_USERNAME', 'DB_DATABASE'];
            $missing = [];
            
            foreach ($required as $var) {
                if (empty($_ENV[$var])) {
                    $missing[] = $var;
                }
            }
            
            if (!empty($missing)) {
                throw new \Exception("Missing required environment variables: " . implode(', ', $missing));
            }
            
            // Test connection
            if (!$this->testConnection()) {
                throw new \Exception("Database connection test failed");
            }
            
            //error_log("NexaDb - Configuration verified successfully");
            return true;
        } catch (\Exception $e) {
            error_log("NexaDb - Configuration verification failed: " . $e->getMessage());
            return false;
        }
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {}
}
