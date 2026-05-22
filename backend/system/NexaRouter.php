<?php
declare(strict_types=1);

namespace App\System;
use App\System\Dom\NexaDom;
use App\System\Helpers\NexaSession;
use App\System\Helpers\NexaRequest;
use Exception;
use ReflectionClass;
use ReflectionMethod;

/**
 * NexaRouter - Modern MVC Router with NexaDom Integration
 * 
 * Features:
 * - RESTful routing
 * - Controller-based architecture
 * - Automatic NexaDom integration
 * - Session-based routing with NexaSession
 * - NexaRequest integration for request handling
 * - Middleware support
 * - Parameter binding
 */
class NexaRouter
{
    private array $routes = [];
    private array $middlewares = [];
    private ?NexaDom $template = null;
    private NexaRequest $request;
    private string $controllersNamespace = 'App\\Controllers\\';
    private string $templatePath = './templates/';
    private string $currentController = '';
    private string $currentAction = '';
    private array $layoutConfig = [];
    
    public function __construct(string $templatePath = './templates/', array $layoutConfig = [], ?NexaRequest $request = null)
    {
        $this->templatePath = $templatePath;
        $this->layoutConfig = $layoutConfig;
        $this->request = $request ?? new NexaRequest();
        $currentPath = $this->request->getCleanPath();
        if (!str_starts_with($currentPath, '/api')) {
            $this->template = new NexaDom($templatePath);
        }
        // REMOVED: Template variable setup - controllers handle their own templates
        
        // Initialize NexaSession
        NexaSession::init();

        // Register static template asset routes (theme, mobile, tablet, dashboard)
        $this->registerTemplateAssetRoutes();
        // Register static directory routes (drive, avatar, images)
        $this->registerStaticDirectoryRoutes();
    }

    /**
     * Register static routes untuk template assets
     * /theme/{path}, /mobile/{path}, /tablet/{path}, /dashboard/{path} → templates/{folder}/{path}
     */
    private function registerTemplateAssetRoutes(): void
    {
        // /assets/{path} → TemplateController@assets (deteksi template aktif via cookie/User-Agent)
        $this->add('/assets/{params}', 'TemplateController@assets');
        foreach (['theme', 'mobile', 'tablet', 'dashboard'] as $folder) {
            $this->add("/{$folder}/{params}", 'TemplateController@index');
        }
    }

    /**
     * Register static directory routes - direktori statik di NexaRouter
     * /drive/{path} → assets/drive/{path}
     * /avatar/{path} → assets/drive/avatar/{path}
     * /images/{path} → assets/images/{path}
     * /modules/{path} → assets/modules/{path}
     */
    private function registerStaticDirectoryRoutes(): void
    {
        $this->add('/drive/{params}', 'DriveController@index');
        $this->add('/avatar/{params}', 'DriveController@avatar');
        $this->add('/images/{params}', 'ImagesController@index');
        $this->add('/modules/{params}', 'ModulesController@index');
        $this->add('/store/{params}', 'Frontend/WorkspaceController@storeServe');
    }
    
    /**
     * REMOVED: All template setup methods
     * Router focuses only on routing logic - template assignment is controller's responsibility
     */
    
    /**
     * Register GET route
     */
    public function get(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Register POST route
     */
    public function post(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Register PUT route
     */
    public function put(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Register DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Register route for any HTTP method
     */
    public function any(string $path, $handler, array $middleware = []): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
        return $this;
    }
    
    /**
     * Register route for GET and POST (alias untuk compatibility)
     */
    public function add(string $path, $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        $this->addRoute('POST', $path, $handler, $middleware);
        return $this;
    }
    
    /**
     * Add route to routes array
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): self
    {
        $path = '/' . trim($path, '/');
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'pattern' => $this->convertToPattern($path)
        ];
        return $this;
    }
    
    /**
     * Convert route path to regex pattern
     */
    private function convertToPattern(string $path): string
    {
        // Replace named parameters {name} with regex using constraints
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/', function ($matches) {
            $paramName = $matches[1];
            
            // Check for constraints (optional parameters)
            $constraints = self::getConstraintPatterns();
            if (isset($constraints[$paramName])) {
                return '(?P<' . $paramName . '>(' . $constraints[$paramName] . ')?)';
            }
            
            return '(?P<' . $paramName . '>[^/]*)';
        }, $path);

        // Replace named parameters {name} with regex using constraints
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($matches) {
            $paramName = $matches[1];
            
            // Check for constraints
            $constraints = self::getConstraintPatterns();
            if (isset($constraints[$paramName])) {
                return '(?P<' . $paramName . '>' . $constraints[$paramName] . ')';
            }
            
            return '(?P<' . $paramName . '>[^/]+)';
        }, $regex);

        return '#^' . str_replace('/', '\/', $regex) . '$#';
    }
    
    /**
     * Register global middleware
     */
    public function middleware(string $name, callable $callback): self
    {
        $this->middlewares[$name] = $callback;
        return $this;
    }
    
    /**
     * Handle the current request
     */
    public function handle(): void
    {
        $method = $this->request->getMethod();
        $path = $this->request->getCleanPath();
        
        // Debug logging
        // error_log("Router handling request: $method $path");

        /*
         * Jalur cepat GET untuk asset statis: hindari NexaSession::checkTimeout,
         * trySessionBasedRouting (session_start + isLoggedIn), dan tryFrontendRouting
         * (loop preg_match semua route). Ini yang paling terasa untuk banyak request CSS/JS/gambar.
         */
        if ($method === 'GET' && $this->dispatchStaticAssetEarly($path)) {
            return;
        }
  
        // API: hindari redirect HTML (Location) saat session habis — biarkan ApiController + JSON/CORS
        if (!str_starts_with($path, '/api')) {
            NexaSession::checkTimeout('/signin');
        }
        
        // Try session-based routing first (for logged-in users)
        if ($this->trySessionBasedRoutingOptimized($method, $path)) {
return;
        }
 
        // Try frontend routing for public pages
        if ($this->tryFrontendRouting($method, $path)) {
            return;
        }
        
        // Regular routing
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                // Extract parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                // Execute middlewares
                $this->executeMiddlewares($route['middleware']);
                
                // Execute handler
                $this->executeHandler($route['handler'], $params);
                return;
            }
        }
        
        // Route not found
        $this->handleNotFound();
    }

    /**
     * Dispatch langsung ke controller file statis tanpa session / routing bertingkat.
     * Hanya GET; path harus cocok prefix yang sama dengan registerTemplateAssetRoutes / registerStaticDirectoryRoutes.
     */
    private function dispatchStaticAssetEarly(string $path): bool
    {
        if ($path === '' || ($path[0] ?? '') !== '/') {
            return false;
        }
        if (str_starts_with($path, '/api')) {
            return false;
        }

        $staticPrefixes = [
            '/assets/' => 'TemplateController@assets',
            '/modules/' => 'ModulesController@index',
            '/drive/' => 'DriveController@index',
            '/avatar/' => 'DriveController@avatar',
            '/images/' => 'ImagesController@index',
        ];
        foreach ($staticPrefixes as $prefix => $handler) {
            if (str_starts_with($path, $prefix)) {
                $this->executeHandler($handler, []);
                return true;
            }
        }

        foreach (['theme', 'mobile', 'tablet', 'dashboard'] as $folder) {
            $prefix = '/' . $folder . '/';
            if (str_starts_with($path, $prefix)) {
                $this->executeHandler('TemplateController@index', []);
                return true;
            }
        }

        return false;
    }
    
    /**
     * Optimized session-based routing dengan NexaSession integration
     */
    private function trySessionBasedRoutingOptimized(string $method, string $uri): bool
    {
        // Handle both GET and POST requests for session-based routing
        if (!in_array($method, ['GET', 'POST'])) {
            return false;
        }
        
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            NexaSession::initialize();
        }
        
        // Get user data using NexaSession (FRESH setiap kali)
        $sessionInstance = NexaSession::getInstance();
        $userData = null;
        $userSlug = null;
        
        if ($sessionInstance->isLoggedIn()) {
            $userData = $sessionInstance->getUser();
            $userSlug = $sessionInstance->getUserSlug();
        }
        
        // Quick exit jika user tidak login
        if (!$userData || !$userSlug) {
            return false;
        }
        
        // Quick URI parsing dengan minimal overhead
        // FIXED: Add null safety for strpos
        if ($uri === null || $uri === '') {
            $uri = '/';
        }
        
        $firstSlash = strpos($uri, '/', 1);
        $firstSegment = $firstSlash !== false ? substr($uri, 1, $firstSlash - 1) : substr($uri, 1);
        
        // Quick check: apakah first segment match dengan user slug (case-insensitive)
        if (strtolower($firstSegment) !== strtolower($userSlug)) {
            return false;
        }
        
        // Parse segments hanya jika diperlukan
        $segments = explode('/', trim($uri, '/'));
        $segments = array_filter($segments);
        $segments = array_values($segments); // Re-index to ensure sequential keys
        
        return $this->handleAdminRouting($segments, $userSlug, $uri);
    }

    /**
     * Optimized dashboard routing dengan caching dan NexaSession integration
     */
    private function handleAdminRouting(array $segments, string $userSlug, string $uri): bool
    {
        // Check controller existence (FRESH setiap request)
        $AdminController = $this->controllersNamespace . "AdminController";
        
        if (!class_exists($AdminController)) {
            return false;
        }
        
        try {
            $AdminControllerInstance = new $AdminController($this->template, $this->layoutConfig);
            
            // Inject dependencies jika available
            if (method_exists($AdminControllerInstance, 'setDependencies')) {
                $AdminControllerInstance->setDependencies();
            }
            
        } catch (Exception $e) {
            return false;
        }
        
        try {
            // Ensure segments is a valid array
            if (!is_array($segments)) {
                error_log("Dashboard routing error: segments is not an array");
                return false;
            }
            
            // Re-index array to ensure sequential numeric keys
            $segments = array_values($segments);
            $segmentCount = count($segments);
            
            // Update current controller info
            $this->currentController = 'AdminController';
            
            if ($segmentCount === 1) {
                // /{username} -> AdminController@index (GET only)
                if ($this->request->isMethod('GET')) {
                    if (method_exists($AdminControllerInstance, 'index')) {
                        $this->currentAction = 'index';
                        $AdminControllerInstance->index(['username' => $userSlug]);
                        return true;
                    }
                }
            } else {
                // /{username}/{page} -> AdminController@page (both GET and POST)
                if (method_exists($AdminControllerInstance, 'page')) {
                    $this->currentAction = 'page';
                    $page = $segments[1] ?? 'index';
                    $params = array_slice($segments, 2);
                    
                    $parameters = [
                        'username' => $userSlug,
                        'page' => $page
                    ];
                    
                    if (!empty($params)) {
                        $parameters['params'] = $params;
                        $parameters['method'] = $params[0] ?? null;
                    }
                    
                    // Inject parameters using reflection
                    $injectedParams = $this->injectParameters($AdminControllerInstance, 'page', [
                        'params' => $parameters
                    ]);
                    
                    // Ensure injectedParams is valid before calling
                    if ($injectedParams !== null && is_array($injectedParams)) {
                        call_user_func_array([$AdminControllerInstance, 'page'], $injectedParams);
                        return true;
                    }
                }
            }
            
        } catch (Exception $e) {
            // Keep this error log as it's critical for debugging routing issues
            error_log("Dashboard routing error: " . $e->getMessage());
        } catch (\Throwable $e) {
            // Catch any other errors including type errors
            error_log("Dashboard routing error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Execute middlewares
     */
    private function executeMiddlewares(array $middlewareNames): void
    {
        foreach ($middlewareNames as $name) {
            if (isset($this->middlewares[$name])) {
                $result = call_user_func($this->middlewares[$name]);
                if ($result === false) {
                    throw new Exception("Middleware '{$name}' blocked the request");
                }
            }
        }
    }
    
    /**
     * Execute route handler
     */
    private function executeHandler($handler, array $params = []): void
    {
        // FIXED: Add null safety for handler
        if ($handler === null) {
            throw new Exception("Handler cannot be null");
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerName, $actionName] = explode('@', $handler);
            $this->executeController($controllerName, $actionName, $params);
        } elseif (is_callable($handler)) {
            // Closure or function
            call_user_func_array($handler, $params);
        } else {
            throw new Exception("Invalid handler type");
        }
    }
    
    /**
     * Execute controller action
     */
    private function executeController(string $controllerName, string $actionName, array $params = []): void
    {
        // Convert forward slashes to namespace separators
        $controllerName = str_replace('/', '\\', $controllerName);
        
        $controllerClass = $this->controllersNamespace . $controllerName;
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller {$controllerClass} not found");
        }
        
        $controller = new $controllerClass($this->template, $this->layoutConfig);
        
        if (!method_exists($controller, $actionName)) {
            throw new Exception("Action {$actionName} not found in {$controllerClass}");
        }
        
        $this->currentController = $controllerName;
        $this->currentAction = $actionName;
        
        // Inject route parameters
        $injectedParams = $this->injectParameters($controller, $actionName, $params);
        
        // Execute controller action
        call_user_func_array([$controller, $actionName], $injectedParams);
    }
    
    /**
     * Inject parameters into controller method
     */
    private function injectParameters($controller, string $method, array $params): array
    {
        $reflection = new ReflectionMethod($controller, $method);
        $methodParams = $reflection->getParameters();
        
        $injectedParams = [];
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            if (isset($params[$paramName])) {
                $injectedParams[] = $params[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $injectedParams[] = $param->getDefaultValue();
            } else {
                $injectedParams[] = null;
            }
        }
        
        return $injectedParams;
    }
    
    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        // Try to render 404 template if exists
        try {
            // REMOVED: Template assignment - 404 handling should be controller's responsibility
            // Router should only provide basic 404 response
            
            if ($this->template !== null && file_exists($this->templatePath . '404.html')) {
                $this->template->add_file('404.html');
                echo $this->template->pparse_file('404.html');
            } else {
                echo $this->getDefault404Page('Route not found - Session-based routing failed');
            }
        } catch (Exception $e) {
            echo $this->getDefault404Page($e);
        }
    }
    
    /**
     * Get default 404 page HTML
     */
    private function getDefault404Page($e=''): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error-container { max-width: 600px; margin: 0 auto; }
        h1 { color: #e74c3c; font-size: 72px; margin: 0; }
        h2 { color: #34495e; }
        p { color: #7f8c8d; }
    </style>
</head>
<body>
'.$e.'
    <div class="error-container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for could not be found.</p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Get current request path (now using NexaRequest)
     */
    private function getCurrentPath(): string
    {
        return $this->request->getCleanPath();
    }
    
    /**
     * Get current full URL (now using NexaRequest)
     */
    private function getCurrentUrl(): string
    {
        return $this->request->getCurrentUrl();
    }
    
    /**
     * Get base URL (now using NexaRequest)
     */
    private function getBaseUrl(): string
    {
        return $this->request->getBaseUrl();
    }
    
    /**
     * Get NexaDom template instance
     */
    public function getTemplate(): ?NexaDom
    {
        return $this->template;
    }
    
    /**
     * Set controllers namespace
     */
    public function setControllersNamespace(string $namespace): self
    {
        $this->controllersNamespace = rtrim($namespace, '\\') . '\\';
        return $this;
    }
    
    /**
     * Get current controller and action info
     */
    public function getCurrentRoute(): array
    {
        $sessionInfo = [];
        
        try {
            $sessionInstance = NexaSession::getInstance();
            $sessionInfo = [
                'user_logged_in' => $sessionInstance->isLoggedIn(),
                'user_slug' => $sessionInstance->isLoggedIn() ? $sessionInstance->getUserSlug() : null,
                'user_id' => $sessionInstance->isLoggedIn() ? $sessionInstance->getUserId() : null,
                'session_active' => NexaSession::isActive(),
                'session_remaining' => NexaSession::getRemainingTime()
            ];
        } catch (Exception $e) {
            $sessionInfo = [
                'user_logged_in' => false,
                'user_slug' => null,
                'user_id' => null,
                'session_active' => false,
                'session_remaining' => 0
            ];
        }
        
        return array_merge([
            'controller' => $this->currentController,
            'action' => $this->currentAction,
            'path' => $this->request->getPath(),
            'clean_path' => $this->request->getCleanPath(),
            'method' => $this->request->getMethod(),
            'is_session_based' => !empty($this->currentController) && $this->currentController === 'AdminController',
            'request_info' => [
                'base_url' => $this->request->getBaseUrl(),
                'current_url' => $this->request->getCurrentUrl(),
                'query_params' => $this->request->getQueryParams(),
                'path_segments' => $this->request->getPathSegments(),
                'slug_array' => $this->request->getSlugArray(),
                'part_array' => $this->request->getPartArray(),
                'is_ajax' => $this->request->isAjax(),
                'is_https' => $this->request->isHttps(),
                'host' => $this->request->getHost(),
                'client_ip' => $this->request->getClientIP(),
                'user_agent' => $this->request->getUserAgent()
            ]
        ], $sessionInfo);
    }
    
    /**
     * Get session instance (helper method)
     */
    public function getSession(): NexaSession
    {
        return NexaSession::getInstance();
    }
    
    /**
     * Check if current route is session-based
     */
    public function isSessionBasedRoute(): bool
    {
        return $this->currentController === 'AdminController';
    }
    
    /**
     * REMOVED: Template variable refresh - not router's responsibility
     * Controllers should handle their own template variables after login/logout
     */

    /**
     * Get constraint patterns for parameters
     */
    public static function getConstraintPatterns(): array
    {
        return [
            'id' => '[0-9]+',
            'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            'slug' => '[a-z0-9-]+',
            'year' => '[0-9]{4}',
            'Y' => '[0-9]{4}',  // Alias for year
            'month' => '0[1-9]|1[0-2]',
            'M' => '0[1-9]|1[0-2]',  // Alias for month
            'day' => '0[1-9]|[12][0-9]|3[01]',
            'D' => '0[1-9]|[12][0-9]|3[01]',  // Alias for day
            'params' => '.+',  // Generic params - allow multiple path segments including slashes
        ];
    }

    /**
     * Get constraint pattern for specific parameter
     */
    public static function constraint(string $name): string
    {
        $constraints = self::getConstraintPatterns();
        return $constraints[$name] ?? '[^/]+';
    }


    /**
     * Get NexaRequest instance
     */
    public function getRequest(): NexaRequest
    {
        return $this->request;
    }

    /**
     * Frontend routing untuk public pages
     * FIXED: Hanya tangkap request yang benar-benar untuk frontend, bukan semua request
     */
    private function tryFrontendRouting(string $method, string $uri): bool
    {
        // Handle both GET and POST requests for frontend routing
        if (!in_array($method, ['GET', 'POST'])) {
            return false;
        }
        
        // Quick URI parsing
        if ($uri === null || $uri === '') {
            $uri = '/';
        }
        
        // Skip API routes - let them be handled by regular routing
        if (strpos($uri, '/api') === 0) {
            return false;
        }
        
        // Parse segments
        $segments = explode('/', trim($uri, '/'));
        $segments = array_filter($segments);
        
        if (empty($segments)) {
            // Root path / -> FrontendController@index
            return $this->handleFrontendRoot();
        }
        
        // FIXED: Check if this is a registered route first
        // Jika ada route yang terdaftar di web.php, jangan tangkap dengan frontend routing
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                // Route ini sudah terdaftar di web.php, biarkan regular routing yang handle
                return false;
            }
        }
        
        // Jika bukan registered route, baru coba frontend routing
        return $this->handleFrontendRouting($segments, $uri);
    }
    
    /**
     * Handle frontend root path (/)
     */
    private function handleFrontendRoot(): bool
    {
        // Check frontend controller existence (FRESH setiap request)
        $FrontendController = $this->controllersNamespace . "FrontendController";
        
        if (!class_exists($FrontendController)) {
            return false;
        }
        
        try {
            $FrontendControllerInstance = new $FrontendController($this->template, $this->layoutConfig);
            
            // Inject dependencies jika available
            if (method_exists($FrontendControllerInstance, 'setDependencies')) {
                $FrontendControllerInstance->setDependencies();
            }
            
        } catch (Exception $e) {
            return false;
        }
        
        try {
            // Update current controller info
            $this->currentController = 'FrontendController';
            
            if ($this->request->isMethod('GET')) {
                if (method_exists($FrontendControllerInstance, 'index')) {
                    $this->currentAction = 'index';
                    $FrontendControllerInstance->index([]);
                    return true;
                }
            }
            
        } catch (Exception $e) {
            // Keep this error log as it's critical for debugging routing issues
            error_log("Frontend root routing error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Handle frontend page routing
     */
    private function handleFrontendRouting(array $segments, string $uri): bool
    {
        // Check frontend controller existence (FRESH setiap request)
        $FrontendController = $this->controllersNamespace . "FrontendController";
        
        if (!class_exists($FrontendController)) {
            return false;
        }
        
        try {
            $FrontendControllerInstance = new $FrontendController($this->template, $this->layoutConfig);
            
            // Inject dependencies jika available
            if (method_exists($FrontendControllerInstance, 'setDependencies')) {
                $FrontendControllerInstance->setDependencies();
            }
            
        } catch (Exception $e) {
            return false;
        }
        
        try {
            $segmentCount = count($segments);
            
            // Update current controller info
            $this->currentController = 'FrontendController';
            
            if ($segmentCount >= 1) {
                // /{page} -> FrontendController@page (both GET and POST)
                if (method_exists($FrontendControllerInstance, 'page')) {
                    $this->currentAction = 'page';
                    $page = $segments[0] ?? 'index';
                    $params = array_slice($segments, 1);
                    
                    $parameters = [
                        'page' => $page
                    ];
                    
                    if (!empty($params)) {
                        $parameters['params'] = $params;
                        $parameters['method'] = $params[0] ?? null;
                    }
                    
                    // Inject parameters using reflection
                    $injectedParams = $this->injectParameters($FrontendControllerInstance, 'page', [
                        'params' => $parameters
                    ]);
                    call_user_func_array([$FrontendControllerInstance, 'page'], $injectedParams);
                    return true;
                }
            }
            
        } catch (Exception $e) {
            // Keep this error log as it's critical for debugging routing issues
            error_log("Frontend routing error: " . $e->getMessage());
        }
        
        return false;
    }

  







}
