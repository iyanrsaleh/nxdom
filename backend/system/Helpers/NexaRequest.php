<?php
namespace App\System\Helpers;

/**
 * NexaEvent - Event Management System
 * 
 * Phase 8 Enhancement: Advanced Features & Performance Optimization
 * Features: Request URI handling and parsing
 */
class NexaRequest
{
    /**
     * Raw REQUEST_URI from $_SERVER
     */
    private string $requestUri;
    
    /**
     * Parsed URI components
     */
    private array $uriComponents = [];
    
    /**
     * Query parameters
     */
    private array $queryParams = [];
    
    /**
     * BASE_DIR of the project
     */
    private string $baseDir;
    
    /**
     * BASE_URL of the project
     */
    private string $baseUrl;
    
    /**
     * Directory name/project name
     */
    private string $directoryName;
    
    /**
     * Server information
     */
    private array $serverInfo = [];

    /** Cache getCleanPath() — dipanggil berkali-kali per request (router, Nexa, controller) */
    private ?string $cleanPathCache = null;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Use SCRIPT_NAME for URL-based path detection (consistent with routing)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        
        // Convert URL path to directory name for consistency
        $this->directoryName = ($scriptDir !== '/' && !empty($scriptDir)) 
            ? basename($scriptDir) 
            : 'www'; // fallback for root installation
            
        // Keep baseDir for file operations (still use SCRIPT_FILENAME)
        $this->baseDir = dirname($_SERVER['SCRIPT_FILENAME'] ?? dirname(__DIR__, 2));
        
        $this->baseUrl = $this->generateBaseUrl();
        $this->serverInfo = $this->collectServerInfo();
        $this->parseRequestUri();
    }
    
    /**
     * Get the raw REQUEST_URI
     */
    public function getRequestUri(): string
    {
        return $this->requestUri;
    }
    
    /**
     * Get the path component (without query string)
     */
    public function getPath(): string
    {
        return $this->uriComponents['path'] ?? '/';
    }
    
    /**
     * Get query parameters as array
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }
    
    /**
     * Get specific query parameter
     */
    public function getQueryParam(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }
    
    /**
     * Get multiple query parameters by keys
     * 
     * @param array $keys Array of parameter names to retrieve
     * @param mixed $default Default value for missing parameters
     * @return array Associative array with requested parameters
     */
    public function getQueryParamsKeys(array $keys, $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->queryParams[$key] ?? $default;
        }
        return $result;
    }
    
    /**
     * Get the query string
     */
    public function getQueryString(): string
    {
        return $this->uriComponents['query'] ?? '';
    }
    
    /**
     * Get the fragment (hash) part
     */
    public function getFragment(): string
    {
        return $this->uriComponents['fragment'] ?? '';
    }
    
    /**
     * Check if request is for a specific path
     */
    public function isPath(string $path): bool
    {
        return $this->getPath() === $path;
    }
    
    /**
     * Check if path starts with given prefix
     */
    public function pathStartsWith(string $prefix): bool
    {
        return str_starts_with($this->getPath(), $prefix);
    }
    
    /**
     * Get path segments as array (from root domain)
     */
    public function getPathSegments(): array
    {
        $path = trim($this->getPath(), '/');
        return $path === '' ? [] : explode('/', $path);
    }
    
    /**
     * Get path segments relative to BASE_DIR
     */
    public function getRelativePathSegments(): array
    {
        // Get current script info
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $this->getPath();
        
        // Extract the part after the script name
        $scriptPos = strpos($requestUri, $scriptName);
        if ($scriptPos !== false) {
            // Get everything after script name (path info)
            $pathInfo = substr($requestUri, $scriptPos + strlen($scriptName));
            $pathInfo = trim($pathInfo, '/');
            
            // Get script filename without extension
            $scriptFile = basename($scriptName, '.php');
            
            // Build result: [script_name, ...path_info_segments]
            $result = [$scriptFile];
            
            if (!empty($pathInfo)) {
                $pathSegments = explode('/', $pathInfo);
                $result = array_merge($result, $pathSegments);
            }
            
            return $result;
        }
        
        // Fallback: just split the full path
        $fullPath = trim($requestUri, '/');
        return empty($fullPath) ? [] : explode('/', $fullPath);
    }
    
    /**
     * Get specific path segment by index (from root domain)
     */
    public function getPathSegment(int $index, string $default = ''): string
    {
        $segments = $this->getPathSegments();
        return $segments[$index] ?? $default;
    }
    
    /**
     * Get specific relative path segment by index (relative to BASE_DIR)
     */
    public function getRelativePathSegment(int $index, string $default = ''): string
    {
        $segments = $this->getRelativePathSegments();
        return $segments[$index] ?? $default;
    }
    
    /**
     * Sanitize and validate REQUEST_URI
     */
    public function sanitizeUri(): string
    {
        // Remove multiple slashes
        $uri = preg_replace('#/+#', '/', $this->requestUri);
        
        // Remove dangerous characters
        $uri = filter_var($uri, FILTER_SANITIZE_URL);
        
        // Decode URL encoding
        $uri = urldecode($uri);
        
        return $uri;
    }
    
    /**
     * Parse REQUEST_URI into components
     */
    private function parseRequestUri(): void
    {
        // Initialize arrays to prevent uninitialized property errors
        $this->uriComponents = [];
        $this->queryParams = [];
        
        // Parse the URI
        $parsed = parse_url($this->requestUri);
        $this->uriComponents = $parsed ?: [];
        
        // Parse query parameters
        $queryString = $this->uriComponents['query'] ?? '';
        if (!empty($queryString)) {
            parse_str($queryString, $this->queryParams);
        }
        
        // Ensure path exists
        if (!isset($this->uriComponents['path'])) {
            $this->uriComponents['path'] = '/';
        }
    }
    
    /**
     * Check if URI contains specific query parameter
     */
    public function hasQueryParam(string $key): bool
    {
        return isset($this->queryParams[$key]);
    }
    
    /**
     * Get all URI components
     */
    public function getUriComponents(): array
    {
        return $this->uriComponents;
    }
    
    /**
     * Build URI from components
     */
    public function buildUri(array $components = null): string
    {
        $components = $components ?? $this->uriComponents;
        
        $uri = '';
        
        if (isset($components['path'])) {
            $uri .= $components['path'];
        }
        
        if (isset($components['query']) && !empty($components['query'])) {
            $uri .= '?' . $components['query'];
        }
        
        if (isset($components['fragment']) && !empty($components['fragment'])) {
            $uri .= '#' . $components['fragment'];
        }
        
        return $uri;
    }
    
    /**
     * Add or update query parameter
     */
    public function withQueryParam(string $key, $value): self
    {
        $clone = clone $this;
        $clone->queryParams[$key] = $value;
        $clone->uriComponents['query'] = http_build_query($clone->queryParams);
        return $clone;
    }
    
    /**
     * Remove query parameter
     */
    public function withoutQueryParam(string $key): self
    {
        $clone = clone $this;
        unset($clone->queryParams[$key]);
        $clone->uriComponents['query'] = http_build_query($clone->queryParams);
        return $clone;
    }
    
    /**
     * Get the current URL (with protocol and host)
     */
    public function getCurrentUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . $host . $this->requestUri;
    }
    
    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get request method
     */
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Check if request method matches
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->getMethod()) === strtoupper($method);
    }
    
    /**
     * Get clean path - removes trailing slash except for root and query strings
     * Automatically detects and removes base path using SCRIPT_NAME for proper routing
     * This ensures consistent behavior with directory detection logic
     */
    public function getCleanPath(): string
    {
        if ($this->cleanPathCache !== null) {
            return $this->cleanPathCache;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Auto-detect base path from SCRIPT_NAME (consistent with constructor)
        $basePath = $this->getAutoDetectedBasePath();
        
        // Remove base path from URI if it exists
        if ($basePath !== '/' && !empty($basePath) && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Ensure URI starts with slash
        if (empty($uri) || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        
        // Remove trailing slash except for root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Remove query string if present
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        $this->cleanPathCache = $uri;
        return $this->cleanPathCache;
    }
    
    /**
     * Auto-detect base path from SCRIPT_NAME using server directory intelligence
     * This is used consistently across the class for URL path operations
     * Uses the same logic as getDirectoryName() for consistency
     */
    private function getAutoDetectedBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        // Normalize path separators
        $basePath = str_replace('\\', '/', $basePath);
        
        // Always use script directory for stripping - ensures /subdir/docs → /docs for routing
        // Ketika app di subdirectory (mis. /Tnserver/www/), path /Tnserver/www/docs harus jadi /docs
        if ($basePath === '/' || $basePath === '.' || empty($basePath)) {
            return '/'; // No base path to remove (app at document root)
        }
        
        return $basePath;
    }

    /**
     * Generate slug array from path segments (using advanced parsing logic)
     * Creates indexed slug array like: ['slug' => 'about-us', 'slug1' => 'contact-form']
     * 
     * @param string|null $url Optional URL to parse, defaults to current URL
     */
    public function getSlugArray(?string $url = null): array
    {
        $urlToParse = $url ?? $this->getCurrentUrl();
        $parsed = $this->parseUrlSegments($urlToParse);
        return $parsed['slug'];
    }
    
    /**
     * Generate part array from path segments (sanitized version, using advanced parsing logic)
     * Creates indexed part array like: ['part' => 'about us', 'part1' => 'contact form']
     * 
     * @param string|null $url Optional URL to parse, defaults to current URL
     */
    public function getPartArray(?string $url = null): array
    {
        $urlToParse = $url ?? $this->getCurrentUrl();
        $parsed = $this->parseUrlSegments($urlToParse);
        return $parsed['part'];
    }
    
    /**
     * Get both slug and part arrays in one call
     * Returns array with 'slug' and 'part' keys containing respective arrays
     * 
     * @param string|null $url Optional URL to parse, defaults to current URL
     */
    public function getSegmentArrays(?string $url = null): array
    {
        return [
            'slug' => $this->getSlugArray($url),
            'part' => $this->getPartArray($url)
        ];
    }
    
    /**
     * Parse URL and extract slug/part arrays with advanced logic
     * Handles project directory detection and URL cleaning
     * 
     * @param string $url The URL to parse
     * @return array Array containing 'slug' and 'part' arrays
     */
    public function parseUrlSegments(string $url): array
    {
        // Validasi URL
        if (empty($url)) {
            return [
                'slug' => [],
                'part' => []
            ];
        }
        
        $parsed_url = parse_url($url);
        $pathToUse = !empty($parsed_url['path']) ? $parsed_url['path'] : '';
        
        if (!empty($this->getBaseDirProyek())) {
            $partres = $this->getBaseDirProyek() . '/';
            // Hapus base_url dari path jika ada
            $ID = explode($partres, $url);
            // Bersihkan path
            $parts = explode('/', trim($ID[1] ?? '', '/'));
        } else {
            // Menghitung normal - langsung dari path
            $parts = explode('/', trim($pathToUse, '/'));
        }
        
        // Inisialisasi array hasil
        $slug_result = [];
        $part_result = [];
        
        foreach ($parts as $index => $value) {
            if (empty($value)) continue; // Skip empty parts
            
            $InisialisasiKey = abs($index);
            
            // Untuk slug (original value)
            $slug_key = $InisialisasiKey === 0 ? 'slug' : 'slug' . $InisialisasiKey;
            $slug_result[$slug_key] = trim($value);
            
            // Untuk part (sanitized value)
            $part_key = $InisialisasiKey === 0 ? 'part' : 'part' . $InisialisasiKey;
            $sanitized_value = str_replace('-', ' ', $value);
            $part_result[$part_key] = trim(htmlspecialchars($sanitized_value, ENT_QUOTES, 'UTF-8'));
        }
        
        return [
            'slug' => $slug_result,
            'part' => $part_result
        ];
    }
    
    /**
     * Get specific slug by index
     */
    public function getSlug(int $index = 0, string $default = ''): string
    {
        $slugs = $this->getSlugArray();
        $key = $index === 0 ? 'slug' : 'slug' . $index;
        $result = $slugs[$key] ?? $default;
        
        // Filter jika ada ? jangan ambil
        if (($pos = strpos($result, '?')) !== false) {
            $result = substr($result, 0, $pos);
        }
        
        return $result;
    }
    
    /**
     * Get specific part by index  
     */
    public function getPart(int $index = 0, string $default = ''): string
    {
        $parts = $this->getPartArray();
        $key = $index === 0 ? 'part' : 'part' . $index;
        $result = $parts[$key] ?? $default;
        
        // Filter jika ada ? jangan ambil
        if (($pos = strpos($result, '?')) !== false) {
            $result = substr($result, 0, $pos);
        }
        
        return $result;
    }
    
    // ========== SERVER INFO METHODS (from ServerInfo.php) ==========
    
    /**
     * Get BASE_DIR
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }
    
    /**
     * Get BASE_URL
     */
    public function getBaseUrl(): string
    {
     
        if (!empty($_ENV['APP_URL'])) {  
           return $_ENV['APP_URL'];
        } else {
          return $this->baseUrl;
        }
    }
    
    /**
     * Get directory name/project name
     * Returns null if current directory is a server directory (www, www8, htdocs, etc.)
     */
    public function getDirectoryName(): ?string
    {
        // Check if current directory is a server directory
        $serverDirs = ['www', 'www8', 'htdocs', 'public_html', 'web', 'wwwroot'];
        
        if (in_array(strtolower($this->directoryName), $serverDirs)) {
            return null; // No specific project directory
        }
        
        return $this->directoryName;
    }
    
    /**
     * Get project name (alias for getDirectoryName)
     */
    public function getProjectName(): ?string
    {
        return $this->getDirectoryName();
    }
    
    /**
     * Get BASE_DIR_SERVER - the web server directory (www, www8, htdocs, etc.)
     */
    public function getBaseDirServer(): string
    {
        // Parse the base directory path
        $baseDirParts = explode('/', str_replace('\\', '/', $this->baseDir));
        
        // Look for common web server directory names
        $webDirPatterns = ['www', 'www8', 'htdocs', 'public_html', 'web'];
        
        foreach ($baseDirParts as $dir) {
            // Check if directory matches web server patterns
            foreach ($webDirPatterns as $pattern) {
                if (strpos(strtolower($dir), $pattern) === 0) {
                    return $dir;
                }
            }
        }
        
        // Fallback: try to find directory that comes before project structure
        // Looking for pattern like: /Tnserver/www8/Riset/...
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!empty($documentRoot)) {
            $docRootParts = explode('/', str_replace('\\', '/', $documentRoot));
            // Get the last part of document root (usually www, www8, etc.)
            return end($docRootParts) ?: 'www';
        }
        
        return 'www'; // Default fallback
    }
    
    /**
     * Get BASE_DIR_PROYEK - the current project directory name
     * Returns null if no specific project directory (script in server root)
     */
    public function getBaseDirProyek(): ?string
    {
        return $this->getDirectoryName();
    }
    
    /**
     * Get all server information
     */
    public function getAllServerInfo(): array
    {
        return $this->serverInfo;
    }
    
    /**
     * Get specific server information
     */
    public function getServerInfo(string $key): ?string
    {
        return $this->serverInfo[$key] ?? null;
    }
    
    /**
     * Check if using HTTPS
     */
    public function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
    
    /**
     * Get domain/host
     */
    public function getHost(): string
    {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Get User Agent
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Create safe path relative to BASE_DIR
     */
    public function makePath(string $path): string
    {
        // Ensure baseDir doesn't end with slash
        $baseDir = rtrim($this->baseDir, '/');
        
        // If path is empty, return baseDir as is
        if (empty($path)) {
            return $baseDir;
        }
        
        // Clean the path and ensure it doesn't start with slash for concatenation
        $path = ltrim($path, '/');
        
        // Combine baseDir and path with single slash
        return $baseDir . '/' . $path;
    }
    
    /**
     * Create safe URL relative to BASE_URL
     */
    public function makeUrl(string $path = ''): string
    {
        // Ensure baseUrl doesn't end with slash (except for root)
        $baseUrl = rtrim($this->baseUrl, '/');
      
        
        // Clean the path and ensure it starts with slash
        $path = '/' . ltrim($path, '/');

        if (!empty($_ENV['APP_URL'])) {  

            if (empty($path)) {
               return $_ENV['APP_URL'];
           }
            

           return $_ENV['APP_URL']. $path;
        } else {
         
        // If path is empty, return baseUrl as is
        if (empty($path)) {
            return $baseUrl;
        }




         return $baseUrl . $path;
        }
        
        // Combine baseUrl and path, ensuring no double slashes
        
    }
    
    /**
     * Check if file exists in project
     */
    public function fileExists(string $filePath): bool
    {
        $fullPath = $this->makePath($filePath);
        return file_exists($fullPath);
    }
    
    /**
     * Get environment information
     */
    public function getEnvironmentInfo(): array
    {
        return [
            'php_version' => phpversion(),
            'php_sapi' => php_sapi_name(),
            'os' => php_uname(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'current_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Export all information to array
     */
    public function toArray(): array
    {
        return [
            'request_uri' => $this->getRequestUri(),
            'path' => $this->getPath(),
            'clean_path' => $this->getCleanPath(),
            'query_params' => $this->getQueryParams(),
            'query_string' => $this->getQueryString(),
                         'path_segments' => $this->getPathSegments(),
             'relative_path_segments' => $this->getRelativePathSegments(),
             'slug_array' => $this->getSlugArray(),
             'part_array' => $this->getPartArray(),
             'segment_arrays' => $this->getSegmentArrays(),
            'method' => $this->getMethod(),
            'is_ajax' => $this->isAjax(),
            'current_url' => $this->getCurrentUrl(),
            'base_dir' => $this->getBaseDir(),
            'base_url' => $this->getBaseUrl(),
            'base_dir_server' => $this->getBaseDirServer(),
            'base_dir_proyek' => $this->getBaseDirProyek(),
            'directory_name' => $this->getDirectoryName(),
            'project_name' => $this->getProjectName(),
            'is_https' => $this->isHttps(),
            'host' => $this->getHost(),
            'client_ip' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'server_info' => $this->getAllServerInfo(),
            'environment' => $this->getEnvironmentInfo()
        ];
    }
    
    /**
     * Export to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    // ========== PRIVATE HELPER METHODS ==========
    
    /**
     * Generate BASE_URL from server variables
     */
    private function generateBaseUrl(): string
    {
        $protocol = $this->isHttps() ? 'https' : 'http';
        $host = $this->getHost();
        
        // Special handling for PHP CLI server (development server)
        if (php_sapi_name() === 'cli-server') {
            return $protocol . '://' . $host;
        }
        
        // Use SCRIPT_NAME (tanpa path info) instead of PHP_SELF (dengan path info)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = dirname($scriptName);
        
        // Handle edge cases for dirname
        if ($path === '.' || $path === '\\' || empty($path)) {
            $path = '';
        }
        
        // Normalize path separators untuk Windows
        $path = str_replace('\\', '/', $path);
        
        // Ensure path starts with slash if not empty and isn't already starting with one
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // Remove trailing slash except for root
        if ($path !== '/' && !empty($path)) {
            $path = rtrim($path, '/');
        }
        
        // Build final URL without double slashes
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Collect server information
     */
    private function collectServerInfo(): array
    {
        return [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Unknown',
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'Unknown',
            'http_accept' => $_SERVER['HTTP_ACCEPT'] ?? 'Unknown',
            'http_accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
            'http_accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'Unknown',
            'http_connection' => $_SERVER['HTTP_CONNECTION'] ?? 'Unknown',
            'http_referer' => $_SERVER['HTTP_REFERER'] ?? 'Unknown'
        ];
    }
    
    /**
     * Magic method for debugging
     */
    public function __toString(): string
    {
        return "NexaRequest: {$this->getDirectoryName()} at {$this->getPath()}";
    }
} 