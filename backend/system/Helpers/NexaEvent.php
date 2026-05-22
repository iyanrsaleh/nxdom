<?php
declare(strict_types=1);
namespace App\System\Helpers;

class NexaEvent
{
    private static $controllerCache = null;
    private static $registeredFolders = [
        'Admin',
        'Frontend',
        'Api',
        'Docs'
    ];
    
    /**
     * NexaRequest instance for URL handling
     */
    private static $request = null;

    /**
     * Get NexaRequest instance (singleton)
     */
    private static function getRequest(): NexaRequest
    {
        if (self::$request === null) {
            self::$request = new NexaRequest();
        }
        return self::$request;
    }

    /**
     * Register a new folder to be scanned for controllers
     */
    public static function registerFolder($folderName): void
    {
        if (!in_array($folderName, self::$registeredFolders)) {
            self::$registeredFolders[] = $folderName;
            self::$controllerCache = null; // Reset cache
        }
    }

    /**
     * Get all registered folders
     */
    public static function getRegisteredFolders(): array
    {
        return self::$registeredFolders;
    }

    /**
     * Scan a specific folder for controllers
     */
    private static function scanFolder($folderName, $controllersPath): array
    {
        $cache = [];
        $folderPath = $controllersPath . '/' . $folderName . '/';
        
        if (is_dir($folderPath)) {
            foreach (glob($folderPath . '*/', GLOB_ONLYDIR) as $dir) {
                $controllerName = basename($dir) . 'Controller';
                $controllerClass = "\\App\\Controllers\\{$folderName}\\" . basename($dir) . "\\" . $controllerName;
                if (class_exists($controllerClass)) {
                    $cache[] = $controllerClass;
                }
            }
        }
        
        return $cache;
    }

    /**
     * Build cache of available controllers from all registered folders
     */
    private static function buildControllerCache($controllersPath): array
    {
        $cache = [];
        foreach (self::$registeredFolders as $folder) {
            $cache = array_merge($cache, self::scanFolder($folder, $controllersPath));
        }
        return $cache;
    }

    /**
     * Find and execute method from registered controllers
     * @param string $method Method name to find
     * @param array $params Parameters to pass to method
     * @param string $controllersPath Path to controllers directory
     * @return bool True if method found and executed, false otherwise
     */
    public static function findAndExecuteMethod($method, $params = [], $controllersPath = null): bool
    {
        // Default controllers path
        if ($controllersPath === null) {
            $controllersPath = dirname(__DIR__, 2) . '/controllers';
        }

        // Build cache if not exists
        if (self::$controllerCache === null) {
            self::$controllerCache = self::buildControllerCache($controllersPath);
        }

        // Search in cached controllers
        foreach (self::$controllerCache as $controllerClass) {
            if (method_exists($controllerClass, $method)) {
                $controllerInstance = new $controllerClass();
                $controllerInstance->$method($params);
                return true;
            }
        }

        return false;
    }

    /**
     * Clear controller cache (useful for development)
     */
    public static function clearCache(): void
    {
        self::$controllerCache = null;
    }

    /**
     * Get cached controllers (for debugging)
     */
    public static function getCachedControllers(): array
    {
        return self::$controllerCache ?? [];
    }

    // ========================================================================
    // URL HANDLING - Integrated with NexaRequest
    // ========================================================================

    /**
     * Get URL slug by index using NexaRequest
     * @param int $index Index of the slug (0-based)
     * @param string $default Default value if slug doesn't exist
     * @return string
     */
    public static function getSlug(int $index = 0, string $default = ''): string
    {
        return self::getRequest()->getSlug($index, $default);
    }

    /**
     * Get all URL slugs as array using NexaRequest
     * @return array
     */
    public static function getSlugArray(): array
    {
        return self::getRequest()->getSlugArray();
    }

    /**
     * Get URL part by index using NexaRequest
     * @param int $index Index of the part (0-based)
     * @param string $default Default value if part doesn't exist
     * @return string
     */
    public static function getPart(int $index = 0, string $default = ''): string
    {
        return self::getRequest()->getPart($index, $default);
    }

    /**
     * Get all URL parts as array using NexaRequest
     * @return array
     */
    public static function getPartArray(): array
    {
        return self::getRequest()->getPartArray();
    }

    /**
     * Get current path without query string using NexaRequest
     * @return string
     */
    public static function getCurrentPath(): string
    {
        return self::getRequest()->getCleanPath();
    }

    /**
     * Get current URL with protocol and host using NexaRequest
     * @return string
     */
    public static function getCurrentUrl(): string
    {
        return self::getRequest()->getCurrentUrl();
    }

    /**
     * Check if specific slug exists at index using NexaRequest
     * @param int $index Index to check
     * @param string $slug Slug value to match
     * @return bool
     */
    public static function hasSlug(int $index, string $slug): bool
    {
        return self::getSlug($index) === $slug;
    }

    /**
     * Get slug count using NexaRequest
     * @return int
     */
    public static function getSlugCount(): int
    {
        $slugArray = self::getSlugArray();
        return count($slugArray);
    }

    /**
     * Get path segments using NexaRequest
     * @return array
     */
    public static function getPathSegments(): array
    {
        return self::getRequest()->getPathSegments();
    }

    /**
     * Get relative path segments using NexaRequest
     * @return array
     */
    public static function getRelativePathSegments(): array
    {
        return self::getRequest()->getRelativePathSegments();
    }

    /**
     * Get query parameters using NexaRequest
     * @return array
     */
    public static function getQueryParams(): array
    {
        return self::getRequest()->getQueryParams();
    }

    /**
     * Get specific query parameter using NexaRequest
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function getQueryParam(string $key, $default = null)
    {
        return self::getRequest()->getQueryParam($key, $default);
    }

    /**
     * Check if request is AJAX using NexaRequest
     * @return bool
     */
    public static function isAjax(): bool
    {
        return self::getRequest()->isAjax();
    }

    /**
     * Get request method using NexaRequest
     * @return string
     */
    public static function getMethod(): string
    {
        return self::getRequest()->getMethod();
    }

    /**
     * Check if request method matches using NexaRequest
     * @param string $method Method to check
     * @return bool
     */
    public static function isMethod(string $method): bool
    {
        return self::getRequest()->isMethod($method);
    }

    /**
     * Get base URL using NexaRequest
     * @return string
     */
    public static function getBaseUrl(): string
    {
        return self::getRequest()->getBaseUrl();
    }

    /**
     * Get project name using NexaRequest
     * @return string|null
     */
    public static function getProjectName(): ?string
    {
        return self::getRequest()->getProjectName();
    }

    /**
     * Complete method routing with URL parsing using NexaRequest
     * @param string $fallbackMethod Default method to call if no method found
     * @param array $params Parameters to pass to method
     * @param string $controllersPath Path to controllers directory
     * @param int $methodSlugIndex Index of slug that contains method name (default: 1)
     * @return bool True if method found and executed, false otherwise
     */
    public static function routeMethod(string $fallbackMethod = 'default', array $params = [], string $controllersPath = null, int $methodSlugIndex = 1): bool
    {
        $method = self::getSlug($methodSlugIndex, $fallbackMethod);
        return self::findAndExecuteMethod($method, $params, $controllersPath);
    }

    /**
     * Method routing based on URL structure
     * For URL /debug/access/method -> routes to AccessController->method()
     * @param object $currentController Current controller instance (ignored for URL-based routing)
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name
     * @param string $controllersPath Path to controllers directory
     * @param int $methodSlugIndex Index of slug that contains method name (default: 1)
     * @return void
     */
    public static function Method($currentController, $params = [], string $fallbackMethod = 'default', string $controllersPath = null, int $methodSlugIndex = 1): void
    {
        $method = self::getSlug($methodSlugIndex, $fallbackMethod);
        
        // For URL like /debug/access/method -> find AccessController
        $targetController = self::findControllerByUrl($methodSlugIndex);
        
        if ($targetController) {
            // Try to find method in target controller
            if (method_exists($targetController, $method)) {
                $targetController->$method($params);
                return;
            }
            
            // Method not found, call default method in target controller
            if (method_exists($targetController, $fallbackMethod)) {
                $targetController->$fallbackMethod($params);
                return;
            }
        }
        
        // Fallback: use current controller
        if (method_exists($currentController, $method)) {
            $currentController->$method($params);
            return;
        }
        
        if (method_exists($currentController, $fallbackMethod)) {
            $currentController->$fallbackMethod($params);
        }
    }

    /**
     * Execute method based on explicit path without URL parsing
     * For path 'Access/viewUsers' -> routes to AccessController->viewUsers()
     * @param string $path Path in format 'ControllerName/methodName'
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name if method not found
     * @param string $controllersPath Path to controllers directory
     * @param string $namespace Controller namespace (default: Admin)
     * @param bool $jsonOutput Whether to output JSON response (default: true)
     * @return bool True if method found and executed, false otherwise
     */
    public static function eventsMethod(string $path, $params = [], string $fallbackMethod = null, string $controllersPath = null, string $namespace = 'Admin', bool $jsonOutput = true): bool
    {
        // Set default fallback method langsung di sini
        if ($fallbackMethod === null) {
            $fallbackMethod = 'index';
        }
        
        // Default controllers path
        if ($controllersPath === null) {
            $controllersPath = dirname(__DIR__, 2) . '/controllers';
        }

        // Parse path: 'Access/viewUsers' -> ['Access', 'viewUsers']
        $pathParts = explode('/', trim($path, '/'));
        
        if (count($pathParts) < 2) {
            if ($jsonOutput) {
                self::outputJson([
                    'success' => false,
                    'error' => 'Invalid path format. Expected: ControllerName/methodName',
                    'path' => $path
                ], 400);
            }
            return false;
        }
        
        $controllerName = $pathParts[0];
        $methodName = $pathParts[1];
        
        // Build controller class name
        $controllerClass = "\\App\\Controllers\\{$namespace}\\" . ucfirst($controllerName) . "\\" . ucfirst($controllerName) . "Controller";
        
        // Check if controller class exists
        if (!class_exists($controllerClass)) {
            if ($jsonOutput) {
                self::outputJson([
                    'success' => false,
                    'error' => 'Controller not found',
                    'path' => $path
                ], 404);
            }
            return false;
        }
        
        // Create controller instance
        $controllerInstance = self::createControllerInstance($controllerClass);
        
        if (!$controllerInstance) {
            if ($jsonOutput) {
                self::outputJson([
                    'success' => false,
                    'error' => 'Failed to create controller instance',
                    'path' => $path
                ], 500);
            }
            return false;
        }
        
        // Try to find and execute method
        $foundMethod = self::findMethodInController($controllerInstance, $methodName);
        
        if ($foundMethod) {
            try {
                // Capture any output from the method
                ob_start();
                $result = $controllerInstance->$foundMethod($params);
                $output = ob_get_clean();
                
                if ($jsonOutput) {
                    $response = [
                        'success' => true,
                        'message' => 'Method executed successfully',
                        'method' => $foundMethod,
                        'path' => $path
                    ];
                    
                    // Pisahkan data request dan method params jika ada
                    if (isset($params['request']) && isset($params['params'])) {
                        $response['request'] = $params['request'];
                        $response['params'] = $params['params'];
                    } else {
                        $response['params'] = $params;
                    }
                    
                    // Include method result if it's not null
                    if ($result !== null) {
                        $response['result'] = $result;
                    }
                    
                    // Include any output captured
                    if (!empty($output)) {
                        $response['output'] = $output;
                    }
                    
                    self::outputJson($response);
                }
                return true;
            } catch (\Exception $e) {
                // Clean any output buffer
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if ($jsonOutput) {
                    self::outputJson([
                        'success' => false,
                        'error' => 'Method execution failed',
                        'exception' => $e->getMessage(),
                        'method' => $foundMethod,
                        'path' => $path
                    ], 500);
                }
                return false;
            }
        }
        
        // Method not found, try fallback method
        if (method_exists($controllerInstance, $fallbackMethod)) {
            try {
                // Capture any output from the method
                ob_start();
                $result = $controllerInstance->$fallbackMethod($params);
                $output = ob_get_clean();
                
                if ($jsonOutput) {
                    $response = [
                        'success' => true,
                        'result' => false,
                        'message' => "Method '{$methodName}' not found, executed fallback method '{$fallbackMethod}' instead",
                        'method' => $fallbackMethod,
                        'requested_method' => $methodName,
                        'path' => $path,
                        'warning' => "The requested method '{$methodName}' does not exist in the controller"
                    ];
                    
                    // Pisahkan data request dan method params jika ada
                    if (isset($params['request']) && isset($params['params'])) {
                        $response['request'] = $params['request'];
                        $response['params'] = $params['params'];
                    } else {
                        $response['params'] = $params;
                    }
                    
                    // Include method result if it's not null
                    if ($result !== null) {
                        $response['result'] = $result;
                    }
                    
                    // Include any output captured
                    if (!empty($output)) {
                        $response['output'] = $output;
                    }
                    
                    self::outputJson($response);
                }
                return true;
            } catch (\Exception $e) {
                // Clean any output buffer
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                if ($jsonOutput) {
                    self::outputJson([
                        'success' => false,
                        'error' => 'Fallback method execution failed',
                        'exception' => $e->getMessage(),
                        'method' => $fallbackMethod,
                        'path' => $path
                    ], 500);
                }
                return false;
            }
        }
        
        if ($jsonOutput) {
            self::outputJson([
                'success' => false,
                'error' => 'Method not found',
                'requested_method' => $methodName,
                'fallback_method' => $fallbackMethod,
                'path' => $path
            ], 404);
        }
        
        return false;
    }

    /**
     * Output JSON response with proper headers
     * @param array $data Data to output as JSON
     * @param int $statusCode HTTP status code (default: 200)
     * @param bool $exit Whether to exit after output (default: true)
     * @return void
     */
    private static function outputJson(array $data, int $statusCode = 200, bool $exit = true): void
    {
        // Set JSON content type header
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($statusCode);
        }
        
        // Add timestamp and request info
        $data['timestamp'] = date('Y-m-d H:i:s');
        $data['status_code'] = $statusCode;
        
        // Output JSON
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($exit) {
            exit;
        }
    }

    /**
     * Execute method based on explicit path with advanced options
     * Extended version of eventsMethod with more configuration options
     * @param string $path Path in format 'ControllerName/methodName'
     * @param mixed $params Parameters to pass to method
     * @param array $options Configuration options
     * @return array Execution result with details
     */
    public static function eventsMethodAdvanced(string $path, $params = [], array $options = []): array
    {
        // Default options
        $defaultOptions = [
            'fallback_method' => 'index',
            'controllers_path' => null,
            'namespace' => 'Admin',
            'throw_on_error' => false,
            'return_result' => false,
            'json_output' => true,
            'auto_exit' => true
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $result = [
            'success' => false,
            'executed_method' => null,
            'executed_controller' => null,
            'error' => null,
            'result' => null,
            'path' => $path,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        try {
            // Default controllers path
            if ($options['controllers_path'] === null) {
                $options['controllers_path'] = dirname(__DIR__, 2) . '/controllers';
            }

            // Parse path
            $pathParts = explode('/', trim($path, '/'));
            
            if (count($pathParts) < 2) {
                $result['error'] = 'Invalid path format. Expected: ControllerName/methodName';
                
                if ($options['json_output']) {
                    self::outputJson($result, 400, $options['auto_exit']);
                }
                
                return $result;
            }
            
            $controllerName = $pathParts[0];
            $methodName = $pathParts[1];
            
            // Build controller class name
            $controllerClass = "\\App\\Controllers\\{$options['namespace']}\\" . ucfirst($controllerName) . "\\" . ucfirst($controllerName) . "Controller";
            
            // Check if controller class exists
            if (!class_exists($controllerClass)) {
                $result['error'] = "Controller class not found: {$controllerClass}";
                $result['controller_class'] = $controllerClass;
                
                if ($options['json_output']) {
                    self::outputJson($result, 404, $options['auto_exit']);
                }
                
                return $result;
            }
            
            // Create controller instance
            $controllerInstance = self::createControllerInstance($controllerClass);
            
            if (!$controllerInstance) {
                $result['error'] = "Failed to create controller instance: {$controllerClass}";
                $result['controller_class'] = $controllerClass;
                
                if ($options['json_output']) {
                    self::outputJson($result, 500, $options['auto_exit']);
                }
                
                return $result;
            }
            
            // Try to find and execute method
            $foundMethod = self::findMethodInController($controllerInstance, $methodName);
            
            if ($foundMethod) {
                $result['executed_method'] = $foundMethod;
                $result['executed_controller'] = $controllerClass;
                
                if ($options['return_result']) {
                    $result['result'] = $controllerInstance->$foundMethod($params);
                } else {
                    $controllerInstance->$foundMethod($params);
                }
                
                $result['success'] = true;
                $result['message'] = 'Method executed successfully';
                
                if ($options['json_output']) {
                    self::outputJson($result, 200, $options['auto_exit']);
                }
                
                return $result;
            }
            
            // Method not found, try fallback method
            if (method_exists($controllerInstance, $options['fallback_method'])) {
                $result['executed_method'] = $options['fallback_method'];
                $result['executed_controller'] = $controllerClass;
                $result['requested_method'] = $methodName;
                
                if ($options['return_result']) {
                    $result['result'] = $controllerInstance->{$options['fallback_method']}($params);
                } else {
                    $controllerInstance->{$options['fallback_method']}($params);
                }
                
                $result['success'] = true;
                $result['message'] = 'Fallback method executed successfully';
                
                if ($options['json_output']) {
                    self::outputJson($result, 200, $options['auto_exit']);
                }
                
                return $result;
            }
            
            $result['error'] = "Method '{$methodName}' and fallback method '{$options['fallback_method']}' not found in {$controllerClass}";
            $result['controller_class'] = $controllerClass;
            $result['requested_method'] = $methodName;
            $result['fallback_method'] = $options['fallback_method'];
            
            if ($options['json_output']) {
                self::outputJson($result, 404, $options['auto_exit']);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['exception'] = get_class($e);
            
            if ($options['json_output']) {
                self::outputJson($result, 500, $options['auto_exit']);
            }
            
            if ($options['throw_on_error']) {
                throw $e;
            }
        }
        
        return $result;
    }
    
    /**
     * Find controller based on URL structure
     * For /debug/access/method -> returns AccessController instance
     */
    private static function findControllerByUrl(int $methodSlugIndex): ?object
    {
        // For methodSlugIndex = 2, look at slug index 1 for folder name
        $folderSlugIndex = $methodSlugIndex - 1;
        $folderName = self::getSlug($folderSlugIndex, '');
        
        if (empty($folderName)) {
            return null;
        }
        
        // Convert folder name to controller class
        $controllerClass = "\\App\\Controllers\\Admin\\" . ucfirst($folderName) . "\\" . ucfirst($folderName) . "Controller";
        
        if (class_exists($controllerClass)) {
            return self::createControllerInstance($controllerClass);
        }
        
        return null;
    }
    
    /**
     * Create controller instance with smart parameter detection
     */
    private static function createControllerInstance(string $controllerClass): ?object
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            $constructor = $reflection->getConstructor();
            
            if ($constructor === null) {
                return new $controllerClass();
            }
            
            $parameters = $constructor->getParameters();
            
            if (empty($parameters)) {
                return new $controllerClass();
            }
            
            $args = [];
            foreach ($parameters as $param) {
                $paramType = $param->getType();
                
                if ($paramType && $paramType->getName() === 'App\System\Dom\NexaDom') {
                    if (class_exists('App\System\Dom\NexaDom')) {
                        $args[] = new \App\System\Dom\NexaDom();
                    } else {
                        if ($param->isOptional()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            return null;
                        }
                    }
                } else {
                    if ($param->isOptional()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        return null;
                    }
                }
            }
            
            return $reflection->newInstanceArgs($args);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get comprehensive request information using NexaRequest
     * @return array
     */
    public static function getRequestInfo(): array
    {
        return self::getRequest()->toArray();
    }

    /**
     * Get NexaRequest instance for advanced operations
     * @return NexaRequest
     */
    public static function request(): NexaRequest
    {
        return self::getRequest();
    }

    /**
     * Debug method routing process
     * @param object $currentController Current controller instance
     * @param int $methodSlugIndex Index of slug that contains method name
     * @param string $fallbackMethod Fallback method name
     * @return array Debug information
     */
    public static function debugMethodRouting($currentController, int $methodSlugIndex = 1, string $fallbackMethod = 'default'): array
    {
        $method = self::getSlug($methodSlugIndex, $fallbackMethod);
        $currentControllerClass = get_class($currentController);
        
        $debugInfo = [
            'requested_method' => $method,
            'fallback_method' => $fallbackMethod,
            'current_controller' => $currentControllerClass,
            'method_slug_index' => $methodSlugIndex,
            'slug_array' => self::getSlugArray(),
            'current_path' => self::getCurrentPath(),
            'routing_steps' => []
        ];
        
        // Step 1: Check if method is fallback method
        if ($method === $fallbackMethod) {
            $debugInfo['routing_steps'][] = "Method '{$method}' is fallback method";
            $debugInfo['routing_steps'][] = method_exists($currentController, $fallbackMethod) ? 
                "✓ Fallback method '{$fallbackMethod}' exists in current controller" : 
                "✗ Fallback method '{$fallbackMethod}' NOT found in current controller";
            return $debugInfo;
        }
        
        // Step 2: Check current controller
        $foundMethod = self::findMethodInController($currentController, $method);
        if ($foundMethod) {
            $debugInfo['routing_steps'][] = "✓ Method '{$foundMethod}' found in current controller '{$currentControllerClass}'";
            $debugInfo['found_method'] = $foundMethod;
            $debugInfo['executed_in'] = $currentControllerClass;
            return $debugInfo;
        } else {
            $debugInfo['routing_steps'][] = "✗ Method '{$method}' NOT found in current controller '{$currentControllerClass}'";
            
            // Check normalization
            $normalizedMethod = self::normalizeMethodName($method);
            if ($normalizedMethod !== $method) {
                $debugInfo['routing_steps'][] = "Tried normalized method '{$normalizedMethod}' - " . 
                    (method_exists($currentController, $normalizedMethod) ? "✓ Found" : "✗ Not found");
            }
        }
        
        // Step 3: Check registered controllers
        $debugInfo['routing_steps'][] = "Searching in registered controllers...";
        $debugInfo['registered_controllers'] = self::$controllerCache ?? [];
        
        foreach (self::$controllerCache ?? [] as $controllerClass) {
            if (class_exists($controllerClass)) {
                $controllerInstance = self::createControllerInstance($controllerClass);
                if ($controllerInstance) {
                    // Skip current controller
                    if (get_class($controllerInstance) === get_class($currentController)) {
                        $debugInfo['routing_steps'][] = "Skipped {$controllerClass} (same as current controller)";
                        continue;
                    }
                    
                    $foundMethod = self::findMethodInController($controllerInstance, $method);
                    if ($foundMethod) {
                        $debugInfo['routing_steps'][] = "✓ Method '{$foundMethod}' found in '{$controllerClass}'";
                        $debugInfo['found_method'] = $foundMethod;
                        $debugInfo['executed_in'] = $controllerClass;
                        return $debugInfo;
                    } else {
                        $debugInfo['routing_steps'][] = "✗ Method '{$method}' NOT found in '{$controllerClass}'";
                    }
                }
            }
        }
        
        // Step 4: Fallback
        $debugInfo['routing_steps'][] = "Falling back to '{$fallbackMethod}' method in current controller";
        $debugInfo['routing_steps'][] = method_exists($currentController, $fallbackMethod) ? 
            "✓ Fallback method '{$fallbackMethod}' exists in current controller" : 
            "✗ Fallback method '{$fallbackMethod}' NOT found in current controller";
        
        $debugInfo['found_method'] = $fallbackMethod;
        $debugInfo['executed_in'] = $currentControllerClass;
        
        return $debugInfo;
    }

    /**
     * Find method in controller with various naming conventions
     * @param object $controller Controller instance
     * @param string $method Method name to find
     * @return string|null Found method name or null if not found
     */
    private static function findMethodInController($controller, string $method): ?string
    {
        // Try exact match first
        if (method_exists($controller, $method)) {
            return $method;
        }
        
        // Try normalized method name
        $normalizedMethod = self::normalizeMethodName($method);
        if ($normalizedMethod !== $method && method_exists($controller, $normalizedMethod)) {
            return $normalizedMethod;
        }
        
        // Try camelCase conversion
        $camelCaseMethod = self::toCamelCase($method);
        if ($camelCaseMethod !== $method && method_exists($controller, $camelCaseMethod)) {
            return $camelCaseMethod;
        }
        
        // Try snake_case conversion
        $snakeCaseMethod = self::toSnakeCase($method);
        if ($snakeCaseMethod !== $method && method_exists($controller, $snakeCaseMethod)) {
            return $snakeCaseMethod;
        }
        
        return null;
    }

    /**
     * Normalize method name by removing special characters and converting to lowercase
     * @param string $method Method name to normalize
     * @return string Normalized method name
     */
    private static function normalizeMethodName(string $method): string
    {
        // Remove special characters and convert to lowercase
        $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $method);
        return strtolower($normalized);
    }

    /**
     * Convert string to camelCase
     * @param string $str String to convert
     * @return string CamelCase string
     */
    private static function toCamelCase(string $str): string
    {
        // Split by non-alphanumeric characters
        $parts = preg_split('/[^a-zA-Z0-9]+/', $str);
        $camelCase = '';
        
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                $camelCase .= strtolower($part);
            } else {
                $camelCase .= ucfirst(strtolower($part));
            }
        }
        
        return $camelCase;
    }

    /**
     * Convert string to snake_case
     * @param string $str String to convert
     * @return string snake_case string
     */
    private static function toSnakeCase(string $str): string
    {
        // Split by non-alphanumeric characters and join with underscores
        $parts = preg_split('/[^a-zA-Z0-9]+/', $str);
        return strtolower(implode('_', array_filter($parts)));
    }
}