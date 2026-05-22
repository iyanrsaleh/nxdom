<?php
namespace App\System\Components;
use Exception;
class NexaUI {
    // Menyimpan instance komponen
    private static $components = [];
    // Daftar semua komponen yang didukung
    private static $supportedComponents = [
         'modal' => NexaModal::class,
         'pagination' => NexaPagination::class,
    ];

    /**
     * Transform konten dengan komponen yang aktif
     * @param string $content Konten yang akan ditransformasi
     * @return string Konten yang sudah ditransformasi
     */
    public static function transform(string &$content): string {
        try {
            // ✅ Simplified: Transform semua komponen yang tersedia (no module dependency)
            foreach (self::$supportedComponents as $name => $class) {
                if (!isset(self::$components[$name])) {
                    self::$components[$name] = new $class();
                }
                
                // ✅ Check if the component has transform method before calling it
                if (method_exists(self::$components[$name], 'transform')) {
                    $content = self::$components[$name]->transform($content);
                }
            }
            return $content;
        } catch (\Exception $e) {
            error_log("Error in NexaUI transform: " . $e->getMessage());
            return $content;
        }
    }
    /**
     * Mendaftarkan komponen kustom
     * @param string $name Nama komponen
     * @param string $class Nama class komponen
     */
    public static function register(string $name, string $class): void {
        if (!class_exists($class)) {
            throw new \Exception("Class {$class} tidak ditemukan");
        }
        
        self::$supportedComponents[strtolower($name)] = $class;
    }

    /**
     * Mengecek apakah komponen tersedia
     * @param string $name Nama komponen
     * @return bool
     */
    public static function has(string $name): bool {
        return isset(self::$supportedComponents[strtolower($name)]);
    }

    /**
     * Mendapatkan instance komponen
     * @param string $name Nama komponen
     * @return object Instance komponen
     */
    public static function get(string $name): object {
        $name = strtolower($name);
        if (!self::has($name)) {
          
            throw new \Exception("Komponen {$name} tidak terdaftar");
        }
        
        if (!isset(self::$components[$name])) {
            $class = self::$supportedComponents[$name];
          
            // error_log("Creating new instance of class: $class");
            // ✅ Check if class exists before creating instance
            if (!class_exists($class)) {
                error_log("ERROR: Class $class does not exist");
                throw new \Exception("Class {$class} tidak ditemukan");
            }
            
            self::$components[$name] = new $class();
            
        } else {
            error_log("Using cached instance for component: $name");
        }
        
        return self::$components[$name];
    }
}
