<?php
declare(strict_types=1);
namespace App\System\Helpers;
use App\System\NexaController;

/**
 * NexaMapping - Convention-Based Resource Routing Helper
 * Compatible with NexaDom Framework v2.0 and Device Layout System
 * 
 * Provides automatic method discovery and routing based on URL segments
 * Pattern: /{resource}/{action}/{id}/{params}
 * 
 * Usage Options:
 * 1. As Trait: use NexaMappingTrait in your controller
 * 2. As Helper: $mapping = new NexaMapping($this); $mapping->handleRouting($params)
 * 3. Direct Integration: Add handleRouting() method to your controller
 * 
 * @author NexaUI Team
 * @version 2.0.0 - Compatible with NexaDom Framework
 */
class NexaMapping 
{
    /**
     * Controller context for resource name extraction
     */
    private ?NexaController $controller = null;

    /**
     * Constructor with controller context
     */
    public function __construct(?NexaController $controller = null)
    {
        $this->controller = $controller;
    }

    /**
     * Method mappings - fully controlled by user
     * NO default mappings, user has full control
     */
    protected array $methodMappings = [];

    /**
     * Handle convention-based routing
     * Call this method from your controller's method
     * 
     * @param string $pathInfo PATH_INFO from URL (e.g., "/edit/123")
     * @param callable|null $fallback Fallback function if no action found
     * @return bool True if handled, false if not
     */
    public function handleRouting(string $pathInfo = '', ?callable $fallback = null): bool
    {
        if (!$this->controller) {
            throw new \Exception("NexaMapping requires a controller context");
        }

        // Get path from PATH_INFO or construct from URL
        if (empty($pathInfo)) {
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            if (empty($pathInfo)) {
                // Fallback to parsing REQUEST_URI
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                $parsedUrl = parse_url($requestUri);
                $pathInfo = $parsedUrl['path'] ?? '';
                
                // Remove script name if present
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                if ($scriptName && strpos($pathInfo, $scriptName) === 0) {
                    $pathInfo = substr($pathInfo, strlen($scriptName));
                }
            }
        }
        
        $pathSegments = explode('/', trim($pathInfo, '/'));
        
        if (empty($pathSegments) || empty($pathSegments[0])) {
            return false;
        }

        $action = $pathSegments[0];
        $additionalParams = array_slice($pathSegments, 1);
        
        // Get method name based on action
        $methodName = $this->getMethodName($action);
        
        if (method_exists($this->controller, $methodName)) {
            // Prepare parameters for the method
            $methodParams = $this->prepareMethodParams($additionalParams, $pathSegments, $action);
            
            // Call the discovered method on the controller
            $this->controller->$methodName($methodParams);
            return true;
        } else {
            // Method not found, try custom handler
            if ($this->handleCustomAction($action, $additionalParams, $pathSegments)) {
                return true;
            }
            
            // If fallback provided, call it
            if ($fallback && is_callable($fallback)) {
                $fallback($action, $additionalParams, $pathSegments);
                return true;
            }
        }

        return false;
    }

    /**
     * Convert action to method name using mappings
     * Override this method in child classes for custom mapping
     * 
     * @param string $action URL action segment
     * @return string Method name
     */
    protected function getMethodName(string $action): string
    {
        return $this->methodMappings[$action] ?? $action;
    }
    
    /**
     * Prepare parameters for method calls
     * Compatible with NexaController parameter expectations
     * 
     * @param array $additionalParams Additional URL segments after action
     * @param array $allSegments All URL segments
     * @param string $action Original action name
     * @return array Prepared parameters
     */
    protected function prepareMethodParams(array $additionalParams, array $allSegments, string $action): array
    {
        return [
            'id' => $additionalParams[0] ?? null,
            'params' => implode('/', $additionalParams),
            'segments' => $allSegments,
            'additional' => $additionalParams,
            'action' => $action,
            'resource_id' => $this->decodeId($additionalParams[0] ?? null),
            'query_params' => $_GET ?? [],
            'post_data' => $_POST ?? [],
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'is_ajax' => $this->isAjaxRequest()
        ];
    }
    
    /**
     * Handle custom actions that don't have dedicated methods
     * Compatible with NexaDom template system
     * 
     * @param string $action Action name
     * @param array $params Additional parameters
     * @param array $allSegments All URL segments
     * @return bool True if handled, false if not
     */
    protected function handleCustomAction(string $action, array $params, array $allSegments): bool
    {
        if (!$this->controller) return false;
        
        // Try to render action-specific template
        $templatePath = $this->getResourceName() . "/$action.html";
        
        if ($this->templateExists($templatePath)) {
            // Use NexaController's assignVar method
            $this->controller->assignVar('action', $action);
            $this->controller->assignVar('action_params', $params);
            $this->controller->assignVar('url_segments', $allSegments);
            $this->controller->assignVar('resource_name', $this->getResourceName());
            
            // Render using controller's render method
            $this->controller->render($templatePath);
            return true;
        }

        // Log unknown action for debugging
        $this->logUnknownAction($action, $params);
        
        return false;
    }
    
    /**
     * Get resource name for template paths
     * Uses controller context
     * 
     * @return string Resource name (e.g., 'form', 'user', 'post')
     */
    public function getResourceName(): string
    {
        if (!$this->controller) return 'unknown';
        
        $className = get_class($this->controller);
        $shortName = substr($className, strrpos($className, '\\') + 1);
        return strtolower(str_replace('Controller', '', $shortName));
    }
    
    /**
     * Check if template exists using NexaDom template system
     * 
     * @param string $template Template path
     * @return bool
     */
    protected function templateExists(string $template): bool
    {
        if (!$this->controller) return false;
        
        // Get template directory from controller
        $templateDir = dirname(__DIR__, 2) . '/templates/';
        
        // Check for device-specific template first
        if (method_exists($this->controller, 'getDeviceType')) {
            $deviceType = $this->controller->getDeviceType() ?? 'theme';
            $deviceTemplate = $templateDir . $deviceType . '/' . $template;
            if (file_exists($deviceTemplate)) {
                return true;
            }
        }
        
        // Check for general template
        return file_exists($templateDir . $template);
    }
    
    /**
     * Decode ID (handles base64 and other encodings)
     * 
     * @param string|null $id Encoded ID
     * @return string|null Decoded ID
     */
    protected function decodeId(?string $id): ?string
    {
        if (!$id) return null;
        
        // Try base64 decode
        if (base64_decode($id, true) !== false) {
            return base64_decode($id);
        }
        
        // Return as-is if not encoded
        return $id;
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    protected function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }
    
    /**
     * Log unknown action for debugging
     * 
     * @param string $action Action name
     * @param array $params Parameters
     * @return void
     */
    protected function logUnknownAction(string $action, array $params): void
    {
        $className = $this->controller ? get_class($this->controller) : 'Unknown';
        // $message = "NexaMapping: Unknown action '$action' in $className with params: " . json_encode($params);
        
        // if (function_exists('error_log')) {
        //     error_log($message);
        // }
    }
    
    /**
     * Add custom method mapping
     * 
     * @param string $action URL action
     * @param string $method Method name
     * @return void
     */
    public function addMethodMapping(string $action, string $method): void
    {
        $this->methodMappings[$action] = $method;
    }
    
    /**
     * Add multiple method mappings - THE ONLY WAY to define action-to-method routing
     * No automatic discovery, no conventions, user has complete control
     * 
     * Usage in controller init():
     * $this->addResourceMappings([
     *     'contact' => 'contactForm',
     *     'add' => 'contactForm', 
     *     'registration' => 'registrationForm'
     * ]);
     * 
     * @param array $mappings Array of action => method mappings
     * @return void
     */
    public function addMethodMappings(array $mappings): void
    {
        $this->methodMappings = array_merge($this->methodMappings, $mappings);
    }
    
    /**
     * Get current method mappings
     * 
     * @return array Current mappings
     */
    public function getMethodMappings(): array
    {
        return $this->methodMappings;
    }

    /**
     * Auto-discover methods and create mappings
     * Finds all methods ending with 'Resource' and creates mappings
     * 
     * @param string $suffix Method suffix to look for (default: 'Resource')
     * @return array Discovered mappings
     */
    public function autoDiscoverMethods(string $suffix = 'Resource'): array
    {
        if (!$this->controller) return [];
        
        $class = new \ReflectionClass($this->controller);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $discovered = [];
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and parent methods
            if (str_starts_with($methodName, '__') || 
                $method->getDeclaringClass()->getName() !== get_class($this->controller)) {
                continue;
            }
            
            // Check if method ends with suffix
            if (str_ends_with($methodName, $suffix)) {
                $action = strtolower(str_replace($suffix, '', $methodName));
                $discovered[$action] = $methodName;
            }
        }
        
        return $discovered;
    }
    
    /**
     * Apply auto-discovered mappings
     * 
     * @param string $suffix Method suffix to discover
     * @return int Number of mappings added
     */
    public function applyAutoDiscovery(string $suffix = 'Resource'): int
    {
        $discovered = $this->autoDiscoverMethods($suffix);
        $this->addMethodMappings($discovered);
        
        return count($discovered);
    }
    
    /**
     * Add conditional mappings based on user role, environment, etc.
     * 
     * @param array $conditions Array of condition => mappings
     * @return void
     */
    public function addConditionalMappings(array $conditions): void
    {
        foreach ($conditions as $condition => $mappings) {
            if ($this->evaluateCondition($condition)) {
                $this->addMethodMappings($mappings);
            }
        }
    }
    
    /**
     * Evaluate condition for conditional mappings
     * 
     * @param string $condition Condition to evaluate
     * @return bool True if condition is met
     */
    protected function evaluateCondition(string $condition): bool
    {
        switch ($condition) {
            case 'admin':
                return $this->isAdmin();
            case 'development':
                return $this->isDevelopment();
            case 'api':
                return $this->isApiRequest();
            case 'mobile':
                return $this->isMobileRequest();
            default:
                // Custom condition evaluation
                return $this->customConditionCheck($condition);
        }
    }
    
    /**
     * Load mappings from configuration file
     * 
     * @param string $configFile Path to config file
     * @return void
     */
    public function loadMappingsFromConfig(string $configFile): void
    {
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config) && isset($config['mappings'])) {
                $this->addMethodMappings($config['mappings']);
            }
        }
    }
    
    /**
     * Dynamic mapping based on database or external source
     * 
     * @param callable $provider Function that returns mappings array
     * @return void
     */
    public function addDynamicMappings(callable $provider): void
    {
        $mappings = $provider();
        if (is_array($mappings)) {
            $this->addMethodMappings($mappings);
        }
    }
    
    /**
     * Remove mapping
     * 
     * @param string $action Action to remove
     * @return void
     */
    public function removeMapping(string $action): void
    {
        unset($this->methodMappings[$action]);
    }
    
    /**
     * Clear all mappings
     * 
     * @return void
     */
    public function clearMappings(): void
    {
        $this->methodMappings = [];
    }
    
    /**
     * Merge mappings from another NexaMapping instance
     * 
     * @param NexaMapping $other Another NexaMapping instance
     * @return void
     */
    public function mergeMappingsFrom(NexaMapping $other): void
    {
        $this->addMethodMappings($other->getMethodMappings());
    }

    // Helper methods for condition evaluation
    protected function isAdmin(): bool
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    protected function isDevelopment(): bool
    {
        return defined('APP_ENV') && constant('APP_ENV') === 'development';
    }
    
    protected function isApiRequest(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
    
    protected function isMobileRequest(): bool
    {
        return str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Mobile');
    }
    
    protected function customConditionCheck(string $condition): bool
    {
        // Override in child classes for custom conditions
        return false;
    }

    /**
     * Handle direct parameter calls dari NexaRouter
     * Simplified method resolution untuk {params} pattern
     * 
     * @param string $params Parameter string dari NexaRouter route
     * @return bool True jika berhasil ditangani
     */
    /**
     * Handle direct parameter calls - ONLY uses user-defined mappings via addResourceMappings()
     * NO automatic method discovery, user has full control
     * 
     * @param string $params Parameter string dari NexaRouter route
     * @return bool True jika berhasil ditangani
     */
    public function handleDirectParamsCall(string $params): bool
    {
        if (!$this->controller) return false;
        
        $segments = explode('/', trim($params, '/'));
        $action = $segments[0] ?? '';
        
        if (empty($action)) return false;
        
        // ONLY use user-defined mappings via addResourceMappings()
        // No automatic discovery, no conventions, user decides everything
        if (isset($this->methodMappings[$action])) {
            $methodName = $this->methodMappings[$action];
            if (method_exists($this->controller, $methodName)) {
                $additionalParams = array_slice($segments, 1);
                $this->callControllerMethod($methodName, $additionalParams);
                return true;
            } else {
                // Method mapped but doesn't exist - show helpful error
                $this->showMappingError($action, $methodName);
                return true;
            }
        }
        
        // No mapping found - return false so controller can handle default behavior
        return false;
    }
    
    /**
     * Call controller method dengan parameter injection
     * Enhanced to properly handle render output
     * 
     * @param string $methodName Method name to call
     * @param array $params Parameters untuk method
     * @return void
     */
    private function callControllerMethod(string $methodName, array $params = []): void
    {
        if (!$this->controller || !method_exists($this->controller, $methodName)) {
            return;
        }
        
        $reflection = new \ReflectionMethod($this->controller, $methodName);
        $methodParams = $reflection->getParameters();
        $injectedParams = [];
        
        foreach ($methodParams as $index => $param) {
            if (isset($params[$index])) {
                $value = $params[$index];
                
                // Type casting berdasarkan parameter type
                $type = $param->getType();
                if ($type) {
                    $typeName = $type->getName();
                    switch ($typeName) {
                        case 'int':
                            $value = (int)$value;
                            break;
                        case 'float':
                            $value = (float)$value;
                            break;
                        case 'bool':
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            break;
                        default:
                            $value = (string)$value;
                    }
                }
                
                $injectedParams[] = $value;
            } elseif ($param->isDefaultValueAvailable()) {
                $injectedParams[] = $param->getDefaultValue();
            } else {
                $injectedParams[] = null;
            }
        }
        
        try {
            // Call method dengan injected parameters
            // Method yang dipanggil bertanggung jawab untuk render sendiri
            call_user_func_array([$this->controller, $methodName], $injectedParams);
            
        } catch (\Exception $e) {
            // Log error atau handle exception
            error_log("Error calling controller method '$methodName': " . $e->getMessage());
            
            // Show simple error message instead of trying to render template
            echo "<div style='padding: 20px; background: #ffebee; border: 1px solid #f44336; border-radius: 4px; margin: 20px;'>";
            echo "<h3 style='color: #d32f2f; margin: 0 0 10px 0;'>🚨 Controller Method Error</h3>";
            echo "<p><strong>Method:</strong> $methodName</p>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
            echo "<p style='margin-top: 15px; font-size: 0.9em; color: #666;'>Fix the error in your controller method to resolve this issue.</p>";
            echo "</div>";
        }
    }

    /**
     * Show helpful error when mapped method doesn't exist
     * 
     * @param string $action Action that was requested
     * @param string $methodName Method that was mapped but doesn't exist
     * @return void
     */
    private function showMappingError(string $action, string $methodName): void
    {
        $controllerName = $this->controller ? get_class($this->controller) : 'Unknown';
        
        echo "<div style='padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin: 20px;'>";
        echo "<h3 style='color: #856404; margin: 0 0 10px 0;'>⚠️ Mapping Configuration Error</h3>";
        echo "<p><strong>Action:</strong> '$action'</p>";
        echo "<p><strong>Mapped to method:</strong> '$methodName'</p>";
        echo "<p><strong>Controller:</strong> $controllerName</p>";
        echo "<p><strong>Problem:</strong> Method '$methodName' does not exist in controller.</p>";
        echo "<div style='margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 3px;'>";
        echo "<strong>💡 Solution:</strong><br>";
        echo "1. Create method <code>public function $methodName()</code> in your controller<br>";
        echo "2. Or update your mapping in <code>addResourceMappings()</code><br>";
        echo "3. Current mappings: <code>" . json_encode($this->methodMappings) . "</code>";
        echo "</div>";
        echo "</div>";
    }

    /**
     * Enable debug mode for routing
     * 
     * @param array $params Method parameters
     * @return void
     */
    public function debugRouting(array $params): void
    {
        echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px; border-left: 4px solid #007cba;'>";
        echo "<h3>🔍 NexaMapping Debug Info</h3>";
        echo "<p><strong>Controller:</strong> " . ($this->controller ? get_class($this->controller) : 'None') . "</p>";
        echo "<p><strong>Resource:</strong> " . $this->getResourceName() . "</p>";
        echo "<p><strong>Parameters:</strong> " . json_encode($params, JSON_PRETTY_PRINT) . "</p>";
        echo "<p><strong>Method Mappings:</strong> " . json_encode($this->methodMappings, JSON_PRETTY_PRINT) . "</p>";
        echo "<p><strong>Device Support:</strong> " . (method_exists($this->controller ?? new stdClass(), 'setDeviceType') ? 'Yes' : 'No') . "</p>";
        echo "</div>";
    }
}

/**
 * NexaMappingTrait - Trait version for easy integration into controllers
 * 
 * Usage: 
 * class MyController extends NexaController {
 *     use NexaMappingTrait;
 *     
 *     public function index() {
 *         if (!$this->handleResourceRouting()) {
 *             // Default index behavior
 *             $this->render('index.html');
 *         }
 *     }
 * }
 */
trait NexaMappingTrait
{
    private ?NexaMapping $mappingHelper = null;
    
    /**
     * Get or create mapping helper instance
     */
    protected function getMappingHelper(): NexaMapping
    {
        if ($this->mappingHelper === null) {
            $this->mappingHelper = new NexaMapping($this);
        }
        return $this->mappingHelper;
    }
    
    /**
     * Handle resource routing using NexaMapping
     * 
     * @param string $pathInfo Optional path info
     * @param callable|null $fallback Fallback function
     * @return bool True if handled
     */
    protected function handleResourceRouting(string $pathInfo = '', ?callable $fallback = null): bool
    {
        return $this->getMappingHelper()->handleRouting($pathInfo, $fallback);
    }
    
    /**
     * Add method mappings for this controller - THE ONLY WAY to define routing
     * This is the primary method for user to control all action-to-method mappings
     * 
     * Example usage in controller init():
     * $this->addResourceMappings([
     *     'contact' => 'contactForm',        // /form/contact -> contactForm()
     *     'add' => 'contactForm',            // /form/add -> contactForm() 
     *     'registration' => 'registrationForm', // /form/registration -> registrationForm()
     *     'edit' => 'editForm'               // /form/edit -> editForm()
     * ]);
     * 
     * @param array $mappings Action => Method mappings
     * @return void
     */
    protected function addResourceMappings(array $mappings): void
    {
        $this->getMappingHelper()->addMethodMappings($mappings);
    }
    
    /**
     * Auto-discover resource methods
     * 
     * @param string $suffix Method suffix (default: 'Resource')
     * @return int Number of discovered methods
     */
    protected function autoDiscoverResourceMethods(string $suffix = 'Resource'): int
    {
        return $this->getMappingHelper()->applyAutoDiscovery($suffix);
    }
    
    /**
     * Handle direct parameter calls dari NexaRouter
     * 
     * @param string $params Parameter string dari route
     * @return bool True jika berhasil ditangani
     */
    protected function handleDirectParamsCall(string $params): bool
    {
        return $this->getMappingHelper()->handleDirectParamsCall($params);
    }
} 