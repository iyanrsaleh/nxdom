<?php
declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * NexaUI  Framework v2.0 - Application Bootstrap
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Modern PHP Framework dengan Template Engine yang powerful dan mudah digunakan.
 * File ini adalah entry point utama aplikasi yang menginisialisasi semua
 * komponen framework dan menjalankan aplikasi.
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 * @license   MIT License
 * 
 * ═══════════════════════════════════════════════════════════════════════════════
 */

/**
 * 
 * Load Composer autoloader untuk dependency management dan class autoloading.
 * Ini memungkinkan kita menggunakan semua library dan class tanpa require manual.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * 
 * Import class-class utama framework yang diperlukan untuk menjalankan aplikasi.
 */
use App\System\Nexa;              // Core framework class
use App\System\Helpers\NexaDebug; // Advanced logging & debugging system

/**
 * 
 * Environment variables dan configuration loading sekarang ditangani oleh
 * Nexa core framework untuk separation of concerns yang lebih baik.
 */

/**
 * 
 * Bootstrap aplikasi dengan optimasi performa:
 * - Lazy loading untuk komponen yang tidak selalu dibutuhkan
 * - Early return untuk production mode
 * - Minimal memory footprint
 */

// ⚡ PERFORMANCE TRACKING: Start timer untuk monitoring
$startTime = microtime(true);
$startMemory = memory_get_usage();

try {
    // ⚡ CORE BOOTSTRAP: Harus sebelum cek APP_ENV — .env dimuat di Nexa::__construct()
    $nexa = Nexa::getInstance();

    // ⚡ PERFORMANCE: Tanpa ini, $_ENV['APP_ENV'] belum ada → selalu dianggap development
    // (header no-cache + NexaDebug path ikut jalan di production tanpa perlu)
    $isProduction = $nexa->environment() === 'production';

    // ⚡ CONDITIONAL INITIALIZATION: Hanya load komponen yang diperlukan
    if (!$isProduction) {
        // Prevent caching in development
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // Debug hanya untuk development
          NexaDebug::init();
          NexaDebug::setErrorHandler();
        
        // Architecture components untuk development
        // \App\System\NexaBootstrap::initialize();
    }
    
    // ⚡ OPTIMIZED APPLICATION RUN
    $nexa->Tatiye()->run();
     

} catch (Exception $e) {
    // Handle fatal errors
    $debugMode = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    if ($debugMode) {
        echo "<h1>Fatal Error</h1>";
        echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    } else {
        echo "<h1>Something went wrong</h1>";
        echo "<p>Please try again later.</p>";
    }    
    // Log error if logging is available
    if (function_exists('error_log')) {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}

// ⚡ PERFORMANCE MONITORING: Display performance metrics in development
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true' && ($_ENV['APP_ENV'] ?? 'development') !== 'production') {
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    $executionTime = round(($endTime - $startTime) * 1000, 2); // dalam milliseconds
    $memoryUsage = round(($endMemory - $startMemory) / 1024, 2); // dalam KB
    $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2); // dalam MB
    
    echo "<!-- NEXA PERFORMANCE METRICS -->";
    echo "<!-- Execution Time: {$executionTime}ms -->";
    echo "<!-- Memory Used: {$memoryUsage}KB -->";
    echo "<!-- Peak Memory: {$peakMemory}MB -->";
    echo "<!-- Total Files Loaded: " . count(get_included_files()) . " -->";
}