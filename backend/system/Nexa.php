<?php
declare(strict_types=1);

namespace App\System;

use App\System\NexaRouter;
use App\System\Helpers\NexaAsset;
use App\System\Helpers\NexaRequest;
use Exception;

/**
 * Nexa - Main Application Bootstrap
 * Modern PHP MVC Framework dengan NexaDom Template Engine
 */
class Nexa
{
    private static ?Nexa $instance = null;
    private NexaRouter $router;
    private NexaRequest $request;
    private array $config = [];
    private bool $booted = false;
    
    private function __construct()
    {
        $this->request = new NexaRequest();
        NexaAsset::setRequest($this->request);
        $this->loadEnvironmentVariables();
        $this->loadConfiguration();
        $this->initializeRouter();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Bootstrap the application
     */
    public function Tatiye(): self
    {
        if ($this->booted) {
            return $this;
        }
        
        $this->registerErrorHandlers();
        $this->loadRoutes();
        $this->booted = true;
        
        return $this;
    }
    
    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            $this->router->handle();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
    
    /**
     * Get router instance
     */
    public function getRouter(): NexaRouter
    {
        return $this->router;
    }
    
    /**
     * Get request instance
     */
    public function getRequest(): NexaRequest
    {
        return $this->request;
    }
    
    /**
     * Get configuration
     */
    public function config(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Load environment variables (simplified version)
     */
    private function loadEnvironmentVariables(): void
    {
        $configFile = dirname(__DIR__, 1) . '/.env';
        
        if (file_exists($configFile)) {
            $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comment lines (yang dimulai dengan #)
                if (strpos(trim($line), '#') === 0) continue;
                
                // Skip baris yang tidak memiliki format key=value
                if (strpos($line, '=') === false) continue;
                
                // Parse key=value dan simpan ke $_ENV
                list($name, $value) = explode('=', $line, 2);
                $key = trim($name);
                $val = trim($value, '"\'');
                
                $_ENV[$key] = $val;
            }
        }
        
        // Set default values for database configuration
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
        $_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
        $_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'nexa_db';
        $_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
        $_ENV['APP_URL'] = $_ENV['APP_URL'] ?? '';
        $_ENV['DB_CHARSET'] = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'development';
        $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'true';
        $_ENV['APP_TIMEZONE'] = $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta';
        $_ENV['NEXA_SECRET_KEY'] = $_ENV['NEXA_SECRET_KEY'] ?? 'NexaUI25';
    }

    /**
     * Load configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'app' => [
                'framework' => 'NexaUI Framework',
                'version' => '2.0.0',
                'env' => $_ENV['APP_ENV'] ?? 'development',
                'nsk' => $_ENV['NEXA_SECRET_KEY'] ?? 'NexaUI25',
                'debug' => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta'
            ],
            'paths' => [
                'base' => dirname(__DIR__),
                'templates' => dirname(__DIR__) . '/templates/',
                'controllers' => dirname(__DIR__) . '/controllers/',
                'routes' => dirname(__DIR__) . '/routes/'
            ],
            'layouts' => [
                'dashboard' => ['header' => 'dashboard/header.html', 'footer' => 'dashboard/footer.html'],
                'mobile'    => ['header' => 'mobile/header.html',    'footer' => 'mobile/footer.html'],
                'tablet'    => ['header' => 'tablet/header.html',    'footer' => 'tablet/footer.html'],
                'theme'     => ['header' => 'theme/header.html',     'footer' => 'theme/footer.html'],
                'docs'      => ['header' => 'docs/header.html',      'footer' => 'docs/footer.html'],
                'standalone'=> ['header' => '', 'footer' => ''], // Layout tanpa header dan footer
            ]
        ];
        
        // Set timezone
        date_default_timezone_set($this->config['app']['timezone']);
    }
    
    /**
     * Initialize router.
     * Catatan performa: NexaRouter::handle() memakai jalur cepat GET untuk /assets, /modules, /theme, …
     * (lihat dispatchStaticAssetEarly) agar tidak memanggil session & routing frontend untuk file statis.
     */
    private function initializeRouter(): void
    {
        $this->router = new NexaRouter(
            $this->config['paths']['templates'],
            $this->config['layouts'],
            $this->request
        );
        
        // Setup global template variables for non-API requests only
        $template = $this->router->getTemplate();
        if ($template !== null) {
            $template->assign_vars([
                'app_name' => $this->config['app']['framework'],
                'app_version' => $this->config['app']['version'],
                'base_url' => $this->request->getBaseUrl(),
                'current_url' => $this->request->getCurrentUrl(),
                'current_year' => date('Y'),
                'timestamp' => time(),
                'env' => $this->config['app']['env'],
                'debug_mode' => $this->config['app']['debug'],
                'request_method' => $this->request->getMethod(),
                'request_path' => $this->request->getPath(),
                'clean_path' => $this->request->getCleanPath(),
                'query_params' => $this->request->getQueryParams(),
                'is_https' => $this->request->isHttps(),
                'host' => $this->request->getHost(),
                'is_ajax' => $this->request->isAjax()
            ]);
        }
    }
    
    /**
     * Load routes from files
     */
    private function loadRoutes(): void
    {
        $router = $this->router;
        
        // Load web routes
        $webRoutes = $this->config['paths']['routes'] . 'web.php';
        if (file_exists($webRoutes)) {
            require $webRoutes;
        }
        
        // Load API routes
        $apiRoutes = $this->config['paths']['routes'] . 'api.php';
        if (file_exists($apiRoutes)) {
            require $apiRoutes;
        }
    }
    
    /**
     * Register error handlers
     */
    private function registerErrorHandlers(): void
    {
        if ($this->config['app']['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            
            set_error_handler(function($severity, $message, $file, $line) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }
    
    /**
     * Handle exceptions
     */
    private function handleException(Exception $e): void
    {
        if ($this->config['app']['debug']) {
            echo "<h1>Nexa Framework Error</h1>";
            echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
            echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            http_response_code(500);
            echo "Application Error";
        }
    }
    
    /**
     * Get base URL (now using NexaRequest)
     */
    private function getBaseUrl(): string
    {
        return $this->request->getBaseUrl();
    }
    
    /**
     * Get version information
     */
    public function version(): string
    {
        return $this->config['app']['version'];
    }
    
    /**
     * Check if in debug mode
     */
    public function isDebug(): bool
    {
        return $this->config['app']['debug'];
    }
    
    /**
     * Get environment
     */
    public function environment(): string
    {
        return $this->config['app']['env'];
    }
} 