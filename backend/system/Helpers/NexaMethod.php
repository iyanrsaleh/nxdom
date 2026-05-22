<?php

namespace App\System\Helpers;

/**
 * NexaMethod Class
 * 
 * Class untuk menampilkan daftar method yang ada dalam sebuah file
 * Mendukung berbagai jenis file termasuk PHP, JavaScript, dan lainnya
 */
class NexaMethod
{
    private $filePath;
    private $fileContent;
    private $supportedExtensions = ['php', 'js', 'ts', 'py', 'java', 'cs', 'cpp', 'c'];
    
    /**
     * Constructor
     * 
     * @param string $filePath Path ke file atau nama class yang akan dianalisis
     * @throws Exception Jika file tidak ditemukan atau tidak didukung
     */
    public function __construct($filePath = null)
    {
        if ($filePath) {
            $this->setFile($filePath);
        }
    }
    
    /**
     * Set file yang akan dianalisis
     * 
     * @param string $filePath Path ke file atau nama class
     * @throws Exception Jika file tidak ditemukan atau tidak didukung
     * @return self
     */
    public function setFile($filePath)
    {
        // Cek apakah input adalah class name (mengandung namespace)
        if (strpos($filePath, '\\') !== false && !file_exists($filePath)) {
            $filePath = $this->resolveClassToFile($filePath);
        }
        
        if (!file_exists($filePath)) {
            throw new \Exception("File tidak ditemukan: {$filePath}");
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->supportedExtensions)) {
            throw new \Exception("Ekstensi file tidak didukung: {$extension}");
        }
        
        $this->filePath = $filePath;
        $this->fileContent = file_get_contents($filePath);
        
        return $this;
    }
    
    /**
     * Resolve class name ke file path
     * 
     * @param string $className Nama class dengan namespace
     * @return string File path
     * @throws Exception Jika class tidak ditemukan
     */
    private function resolveClassToFile($className)
    {
        // Coba menggunakan Reflection untuk mendapatkan file path
        try {
            $reflection = new \ReflectionClass($className);
            return $reflection->getFileName();
        } catch (\ReflectionException $e) {
            // Jika reflection gagal, coba resolve manual berdasarkan convention
            return $this->manualClassResolve($className);
        }
    }
    
    /**
     * Manual resolve class name ke file path berdasarkan convention
     * 
     * @param string $className
     * @return string
     * @throws Exception
     */
    private function manualClassResolve($className)
    {
        // Convention mapping untuk framework
        $conventions = [
            'App\\System\\' => __DIR__ . '/../',
            'App\\Controllers\\' => __DIR__ . '/../../controllers/',
            'App\\Models\\' => __DIR__ . '/../../models/',
            'Nexa\\System\\Helpers\\' => __DIR__ . '/',
        ];
        
        foreach ($conventions as $namespace => $basePath) {
            if (strpos($className, $namespace) === 0) {
                $relativePath = substr($className, strlen($namespace));
                $filePath = $basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relativePath) . '.php';
                
                if (file_exists($filePath)) {
                    return $filePath;
                }
            }
        }
        
        throw new \Exception("Class tidak ditemukan: {$className}");
    }
    
    /**
     * Mendapatkan daftar semua method dalam file
     * 
     * @param bool $detailed Apakah mengembalikan informasi detail
     * @return array Daftar method
     */
    public function getMethods($detailed = false)
    {
        if (!$this->filePath) {
            throw new \Exception("File belum di-set. Gunakan setFile() terlebih dahulu.");
        }
        
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'php':
                return $this->getPHPMethods($detailed);
            case 'js':
            case 'ts':
                return $this->getJavaScriptMethods($detailed);
            case 'py':
                return $this->getPythonMethods($detailed);
            case 'java':
                return $this->getJavaMethods($detailed);
            case 'cs':
                return $this->getCSharpMethods($detailed);
            case 'cpp':
            case 'c':
                return $this->getCppMethods($detailed);
            default:
                return [];
        }
    }
    
    /**
     * Mengextract method dari file PHP
     * 
     * @param bool $detailed
     * @return array
     */
    private function getPHPMethods($detailed = false)
    {
        $methods = [];
        $pattern = '/(?:public|private|protected|static)?\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)/';
        
        if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                $fullMatch = $match[0][0];
                $offset = $match[0][1];
                
                if ($detailed) {
                    $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                    $visibility = $this->extractVisibility($fullMatch);
                    $parameters = $this->extractParameters($fullMatch);
                    $isStatic = strpos($fullMatch, 'static') !== false;
                    
                    $methods[] = [
                        'name' => $methodName,
                        'visibility' => $visibility,
                        'parameters' => $parameters,
                        'line' => $lineNumber,
                        'is_static' => $isStatic,
                        'signature' => trim($fullMatch)
                    ];
                } else {
                    $methods[] = $methodName;
                }
            }
        }
        
        return $methods;
    }
    
    /**
     * Mengextract method dari file JavaScript/TypeScript
     * 
     * @param bool $detailed
     * @return array
     */
    private function getJavaScriptMethods($detailed = false)
    {
        $methods = [];
        
        // Pattern untuk function declarations dan methods
        $patterns = [
            '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)/',
            '/([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*function\s*\([^)]*\)/',
            '/([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/',
            '/([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\([^)]*\)\s*=>/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $methodName = isset($match[1]) ? $match[1][0] : $match[0][0];
                    
                    if ($detailed) {
                        $offset = $match[0][1];
                        $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                        
                        $methods[] = [
                            'name' => $methodName,
                            'line' => $lineNumber,
                            'signature' => trim($match[0][0])
                        ];
                    } else {
                        $methods[] = $methodName;
                    }
                }
            }
        }
        
        return array_unique($methods, SORT_REGULAR);
    }
    
    /**
     * Mengextract method dari file Python
     * 
     * @param bool $detailed
     * @return array
     */
    private function getPythonMethods($detailed = false)
    {
        $methods = [];
        $pattern = '/def\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\):/';
        
        if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                
                if ($detailed) {
                    $offset = $match[0][1];
                    $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                    
                    $methods[] = [
                        'name' => $methodName,
                        'line' => $lineNumber,
                        'signature' => trim($match[0][0])
                    ];
                } else {
                    $methods[] = $methodName;
                }
            }
        }
        
        return $methods;
    }
    
    /**
     * Mengextract method dari file Java
     * 
     * @param bool $detailed
     * @return array
     */
    private function getJavaMethods($detailed = false)
    {
        $methods = [];
        $pattern = '/(?:public|private|protected)?\s*(?:static)?\s*(?:\w+\s+)?(\w+)\s*\([^)]*\)\s*\{/';
        
        if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                
                // Skip constructors dan keywords
                if (in_array($methodName, ['class', 'interface', 'enum', 'if', 'for', 'while'])) {
                    continue;
                }
                
                if ($detailed) {
                    $offset = $match[0][1];
                    $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                    
                    $methods[] = [
                        'name' => $methodName,
                        'line' => $lineNumber,
                        'signature' => trim($match[0][0])
                    ];
                } else {
                    $methods[] = $methodName;
                }
            }
        }
        
        return $methods;
    }
    
    /**
     * Mengextract method dari file C#
     * 
     * @param bool $detailed
     * @return array
     */
    private function getCSharpMethods($detailed = false)
    {
        $methods = [];
        $pattern = '/(?:public|private|protected|internal)?\s*(?:static)?\s*(?:virtual|override)?\s*\w+\s+(\w+)\s*\([^)]*\)/';
        
        if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                
                if ($detailed) {
                    $offset = $match[0][1];
                    $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                    
                    $methods[] = [
                        'name' => $methodName,
                        'line' => $lineNumber,
                        'signature' => trim($match[0][0])
                    ];
                } else {
                    $methods[] = $methodName;
                }
            }
        }
        
        return $methods;
    }
    
    /**
     * Mengextract method dari file C/C++
     * 
     * @param bool $detailed
     * @return array
     */
    private function getCppMethods($detailed = false)
    {
        $methods = [];
        $pattern = '/(?:\w+\s+)?(\w+)\s*\([^)]*\)\s*\{/';
        
        if (preg_match_all($pattern, $this->fileContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $methodName = $match[1][0];
                
                // Skip keywords umum
                if (in_array($methodName, ['if', 'for', 'while', 'switch', 'main'])) {
                    continue;
                }
                
                if ($detailed) {
                    $offset = $match[0][1];
                    $lineNumber = substr_count(substr($this->fileContent, 0, $offset), "\n") + 1;
                    
                    $methods[] = [
                        'name' => $methodName,
                        'line' => $lineNumber,
                        'signature' => trim($match[0][0])
                    ];
                } else {
                    $methods[] = $methodName;
                }
            }
        }
        
        return $methods;
    }
    
    /**
     * Extract visibility dari signature method PHP
     * 
     * @param string $signature
     * @return string
     */
    private function extractVisibility($signature)
    {
        if (strpos($signature, 'private') !== false) return 'private';
        if (strpos($signature, 'protected') !== false) return 'protected';
        if (strpos($signature, 'public') !== false) return 'public';
        return 'public'; // default
    }
    
    /**
     * Extract parameters dari signature method
     * 
     * @param string $signature
     * @return array
     */
    private function extractParameters($signature)
    {
        preg_match('/\(([^)]*)\)/', $signature, $matches);
        if (!isset($matches[1]) || empty(trim($matches[1]))) {
            return [];
        }
        
        $paramString = trim($matches[1]);
        $params = explode(',', $paramString);
        $parameters = [];
        
        foreach ($params as $param) {
            $param = trim($param);
            if (!empty($param)) {
                $parameters[] = $param;
            }
        }
        
        return $parameters;
    }
    
    /**
     * Menampilkan daftar method dalam format yang mudah dibaca
     * 
     * @param bool $detailed
     * @return string
     */
    public function displayMethods($detailed = false)
    {
        $methods = $this->getMethods($detailed);
        $output = "Daftar Method dalam file: " . basename($this->filePath) . "\n";
        $output .= str_repeat("=", 50) . "\n\n";
        
        if (empty($methods)) {
            $output .= "Tidak ada method yang ditemukan.\n";
            return $output;
        }
        
        if ($detailed) {
            foreach ($methods as $index => $method) {
                $output .= ($index + 1) . ". " . $method['name'] . "\n";
                if (isset($method['visibility'])) {
                    $output .= "   Visibility: " . $method['visibility'] . "\n";
                }
                if (isset($method['is_static']) && $method['is_static']) {
                    $output .= "   Static: Yes\n";
                }
                $output .= "   Line: " . $method['line'] . "\n";
                if (!empty($method['parameters'])) {
                    $output .= "   Parameters: " . implode(', ', $method['parameters']) . "\n";
                }
                $output .= "   Signature: " . $method['signature'] . "\n\n";
            }
        } else {
            foreach ($methods as $index => $method) {
                $output .= ($index + 1) . ". " . $method . "\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Mendapatkan statistik method
     * 
     * @return array
     */
    public function getStatistics()
    {
        $methods = $this->getMethods(true);
        $stats = [
            'total_methods' => count($methods),
            'file_path' => $this->filePath,
            'file_extension' => pathinfo($this->filePath, PATHINFO_EXTENSION),
            'file_size' => filesize($this->filePath),
        ];
        
        // Statistik khusus PHP
        if (pathinfo($this->filePath, PATHINFO_EXTENSION) === 'php') {
            $visibility = array_count_values(array_column($methods, 'visibility'));
            $static_count = count(array_filter($methods, function($m) { 
                return isset($m['is_static']) && $m['is_static']; 
            }));
            
            $stats['visibility_breakdown'] = $visibility;
            $stats['static_methods'] = $static_count;
        }
        
        return $stats;
    }
    
    /**
     * Cari method berdasarkan nama
     * 
     * @param string $methodName Nama method yang dicari
     * @param bool $caseSensitive Apakah case sensitive
     * @return array Method yang ditemukan
     */
    public function findMethod($methodName, $caseSensitive = true)
    {
        $methods = $this->getMethods(true);
        $found = [];
        
        foreach ($methods as $method) {
            $name = is_array($method) ? $method['name'] : $method;
            
            if ($caseSensitive) {
                if ($name === $methodName) {
                    $found[] = $method;
                }
            } else {
                if (strtolower($name) === strtolower($methodName)) {
                    $found[] = $method;
                }
            }
        }
        
        return $found;
    }
    
    /**
     * Export daftar method ke format JSON
     * 
     * @param bool $detailed
     * @return string JSON string
     */
    public function exportToJson($detailed = false)
    {
        $data = [
            'file_path' => $this->filePath,
            'timestamp' => date('Y-m-d H:i:s'),
            'methods' => $this->getMethods($detailed),
            'statistics' => $this->getStatistics()
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Export daftar method ke format CSV
     * 
     * @return string CSV string
     */
    public function exportToCsv()
    {
        $methods = $this->getMethods(true);
        $csv = "No,Method Name,Visibility,Line,Parameters,Signature\n";
        
        foreach ($methods as $index => $method) {
            $name = is_array($method) ? $method['name'] : $method;
            $visibility = isset($method['visibility']) ? $method['visibility'] : '';
            $line = isset($method['line']) ? $method['line'] : '';
            $parameters = isset($method['parameters']) ? implode(';', $method['parameters']) : '';
            $signature = isset($method['signature']) ? str_replace('"', '""', $method['signature']) : '';
            
            $csv .= ($index + 1) . ',"' . $name . '","' . $visibility . '",' . $line . ',"' . $parameters . '","' . $signature . '"' . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Membuat asumsi/deskripsi singkat tentang penggunaan method
     * 
     * @param string $methodName Nama method
     * @param array $parameters Parameter method
     * @param string $visibility Visibility method
     * @return string Deskripsi singkat
     */
    public function generateMethodDescription($methodName, $parameters = [], $visibility = 'public')
    {
        $methodLower = strtolower($methodName);
        $paramCount = count($parameters);
        
        // Asumsi berdasarkan nama method (versi singkat)
        $assumptions = [
            // CRUD Operations
            'get' => 'Ambil data',
            'set' => 'Set nilai',
            'create' => 'Buat data',
            'insert' => 'Tambah ke DB',
            'update' => 'Update data',
            'delete' => 'Hapus data',
            'remove' => 'Hapus item',
            'save' => 'Simpan data',
            'load' => 'Muat data',
            'fetch' => 'Ambil data',
            
            // Validation & Processing
            'validate' => 'Validasi data',
            'check' => 'Cek kondisi',
            'verify' => 'Verifikasi',
            'process' => 'Proses data',
            'parse' => 'Parse data',
            'format' => 'Format data',
            'convert' => 'Konversi',
            'transform' => 'Transform',
            
            // Display & Render
            'render' => 'Render view',
            'display' => 'Tampilkan',
            'show' => 'Tampilkan',
            'print' => 'Cetak',
            'output' => 'Output',
            'export' => 'Export data',
            
            // Navigation & Routing
            'redirect' => 'Redirect',
            'route' => 'Routing',
            'navigate' => 'Navigasi',
            
            // Authentication & Security
            'login' => 'Login',
            'logout' => 'Logout',
            'auth' => 'Autentikasi',
            'encrypt' => 'Enkripsi',
            'decrypt' => 'Dekripsi',
            'hash' => 'Hash data',
            
            // Utility
            'find' => 'Cari data',
            'search' => 'Pencarian',
            'filter' => 'Filter data',
            'sort' => 'Urutkan',
            'count' => 'Hitung',
            'calculate' => 'Kalkulasi',
            'generate' => 'Generate',
            'build' => 'Build',
            'init' => 'Inisialisasi',
            'configure' => 'Konfigurasi',
            'setup' => 'Setup',
            'clean' => 'Bersihkan',
            'reset' => 'Reset',
            'clear' => 'Kosongkan',
            
            // API & Response
            'json' => 'Response JSON',
            'xml' => 'Response XML',
            'response' => 'Response',
            'request' => 'Handle request',
            'handle' => 'Handle',
            'execute' => 'Eksekusi',
            'run' => 'Jalankan',
            'call' => 'Panggil method',
            'invoke' => 'Invoke',
            
            // File Operations
            'upload' => 'Upload file',
            'download' => 'Download',
            'read' => 'Baca file',
            'write' => 'Tulis file',
            'open' => 'Buka file',
            'close' => 'Tutup file',
            
            // Array & Collection
            'add' => 'Tambah item',
            'push' => 'Push array',
            'pop' => 'Pop array',
            'merge' => 'Gabung data',
            'split' => 'Pisah data',
            'join' => 'Join string',
            
            // State & Status
            'enable' => 'Aktifkan',
            'disable' => 'Nonaktifkan',
            'toggle' => 'Toggle',
            'activate' => 'Aktifkan',
            'deactivate' => 'Nonaktifkan',
            'start' => 'Mulai',
            'stop' => 'Stop',
            'pause' => 'Pause',
            'resume' => 'Resume',
        ];
        
        // Cari kecocokan berdasarkan awalan nama method
        $description = 'Operasi umum';
        foreach ($assumptions as $prefix => $desc) {
            if (strpos($methodLower, $prefix) === 0) {
                $description = $desc;
                break;
            }
        }
        
        // Persingkat - hanya tambahkan info parameter jika lebih dari 0
        if ($paramCount > 0) {
            $description .= " ({$paramCount}p)";
        }
        
        return $description;
    }
}
