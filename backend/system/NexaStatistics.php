<?php
declare(strict_types=1);
namespace App\System;
/**
 * CONTROLLER AUTOLOAD - DI DALAM SISTEM!
 * Menyimpan semua file controller dalam direktori controllers/
 */
class NexaStatistics
{
    private static $controllerFiles = [];
    private static $controllerDirectories = [];
    private static $basePath = '';
    private static $initialized = false;
    
    /**
     * Initialize - Langsung scan semua controller
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        self::$basePath = dirname(__DIR__) . '/controllers/';
        self::scanControllerDirectories();
        self::$initialized = true;
    }
    
    /**
     * Scan semua direktori controller
     */
    private static function scanControllerDirectories()
    {
        self::scanDirectory(self::$basePath);
    }
    
    /**
     * Scan direktori secara rekursif
     */
    private static function scanDirectory($directory, $namespace = '')
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $fullPath = $directory . $file;
            $relativePath = str_replace(self::$basePath, '', $fullPath);
            
            if (is_dir($fullPath)) {
                // Simpan info direktori
                self::$controllerDirectories[] = [
                    'name' => $file,
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'namespace' => $namespace
                ];
                
                // Scan subdirectory
                $subNamespace = $namespace ? $namespace . '\\' . $file : $file;
                self::scanDirectory($fullPath . '/', $subNamespace);
                
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                // Simpan info file PHP
                $className = pathinfo($file, PATHINFO_FILENAME);
                $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
                
                self::$controllerFiles[$fullClassName] = [
                    'class_name' => $className,
                    'file_name' => $file,
                    'full_path' => $fullPath,
                    'relative_path' => $relativePath,
                    'namespace' => $namespace,
                    'full_class_name' => $fullClassName,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                ];
            }
        }
    }
    
    /**
     * Dapatkan semua file controller
     */
    public static function getControllerFiles()
    {
        self::init();
        return self::$controllerFiles;
    }
    
    /**
     * Dapatkan semua direktori controller
     */
    public static function getControllerDirectories()
    {
        self::init();
        return self::$controllerDirectories;
    }
    
    /**
     * Cari file controller berdasarkan nama class
     */
    public static function getControllerFile($className)
    {
        self::init();
        $className = ltrim($className, '\\');
        return self::$controllerFiles[$className] ?? null;
    }
    
    /**
     * Cek apakah controller ada
     */
    public static function hasController($className)
    {
        self::init();
        $className = ltrim($className, '\\');
        return isset(self::$controllerFiles[$className]);
    }
    
    /**
     * Dapatkan semua nama class controller
     */
    public static function getControllerClassNames()
    {
        self::init();
        return array_keys(self::$controllerFiles);
    }
    
    /**
     * Dapatkan controller berdasarkan namespace
     */
    public static function getControllersByNamespace($namespace = '')
    {
        self::init();
        $result = [];
        foreach (self::$controllerFiles as $className => $fileInfo) {
            if ($fileInfo['namespace'] === $namespace) {
                $result[$className] = $fileInfo;
            }
        }
        return $result;
    }
    
    /**
     * Dapatkan statistik
     */
    public static function getStatistics()
    {
        self::init();
        $totalFiles = count(self::$controllerFiles);
        $totalDirectories = count(self::$controllerDirectories);
        $totalSize = array_sum(array_column(self::$controllerFiles, 'size'));
        
        $namespaces = array_unique(array_column(self::$controllerFiles, 'namespace'));
        $namespaceCount = count($namespaces);
        
        return [
            'total_files' => $totalFiles,
            'total_directories' => $totalDirectories,
            'total_size' => $totalSize,
            'namespaces' => $namespaces,
            'namespace_count' => $namespaceCount,
            'base_path' => self::$basePath
        ];
    }
    
 
}

