<?php
declare(strict_types=1);

namespace App\System\Helpers;

use Exception;

/**
 * Session State Helper
 * Provides React-like useState interface using NexaSession as backend
 * 
 * Usage:
 * [$nama, $setNama] = useState('nama', '');
 * [$counter, $setCounter] = useState('counter', 0);
 * 
 * // Using the values
 * echo $nama; // Get current value
 * $setNama('John Doe'); // Set new value
 * 
 * // Or using direct NexaState instance
 * $nameState = new NexaState('name', 'Default Name');
 * $currentName = $nameState->getValue();
 * $nameState->setValue('New Name');
 */

/**
 * Global helper function - React-like useState for session
 * 
 * @param string $key Session key
 * @param mixed $defaultValue Default value if not set
 * @return array [currentValue, setterFunction]
 */
function useState(string $key, mixed $defaultValue = null): array
{
    return NexaState::use($key, $defaultValue);
}

class NexaState
{
    private string $key;
    private mixed $defaultValue;
    // REMOVED: Static session cache - using fresh instance access instead

    public function __construct(string $key, mixed $defaultValue = null)
    {
        $this->key = $key;
        $this->defaultValue = $defaultValue;
        
        // DIRECT SESSION ACCESS - No static caching
        // Ensure PHP session is started for fallback
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Static method to create useState-like interface
     * 
     * @param string $key
     * @param mixed $defaultValue
     * @return array [currentValue, setterClosure]
     */
    public static function use(string $key, mixed $defaultValue = null): array
    {
        $state = new self($key, $defaultValue);
        
        // Return current value and setter function
        return [
            $state->getValue(),
            function(mixed $newValue) use ($state) {
                $state->setValue($newValue);
            }
        ];
    }

    /**
     * Get current value from session
     * 
     * @return mixed
     */
    public function getValue(): mixed
    {
        // DIRECT ACCESS - Try NexaSession first, then fallback
        try {
            $session = NexaSession::getInstance();
            if ($session->isStarted()) {
                return $session->get($this->key, $this->defaultValue);
            }
        } catch (\Exception $e) {
            // Fall through to direct access
        }
        
        // Fallback to direct session access
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return $_SESSION[$this->key] ?? $this->defaultValue;
    }

    /**
     * Set new value to session
     * 
     * @param mixed $value
     * @return void
     */
    public function setValue(mixed $value): void
    {
        // DIRECT ACCESS - Try NexaSession first, then fallback
        try {
            $session = NexaSession::getInstance();
            if ($session->isStarted()) {
                $session->set($this->key, $value);
                return; // Success, no need for fallback
            }
        } catch (\Exception $e) {
            // Fall through to direct access
        }
        
        // Fallback to direct session access
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $_SESSION[$this->key] = $value;
    }

    /**
     * Toggle boolean value (useful for checkboxes, flags)
     * 
     * @return bool New value after toggle
     */
    public function toggle(): bool
    {
        $currentValue = $this->getValue();
        $newValue = !$currentValue;
        $this->setValue($newValue);
        return $newValue;
    }

    /**
     * Increment numeric value
     * 
     * @param int|float $amount Amount to increment
     * @return int|float New value
     */
    public function increment(int|float $amount = 1): int|float
    {
        $currentValue = $this->getValue() ?? 0;
        $newValue = $currentValue + $amount;
        $this->setValue($newValue);
        return $newValue;
    }

    /**
     * Decrement numeric value
     * 
     * @param int|float $amount Amount to decrement
     * @return int|float New value
     */
    public function decrement(int|float $amount = 1): int|float
    {
        $currentValue = $this->getValue() ?? 0;
        $newValue = $currentValue - $amount;
        $this->setValue($newValue);
        return $newValue;
    }

    /**
     * Append to array value
     * 
     * @param mixed $item Item to append
     * @return array New array value
     */
    public function append(mixed $item): array
    {
        $currentValue = $this->getValue() ?? [];
        if (!is_array($currentValue)) {
            $currentValue = [$currentValue];
        }
        $currentValue[] = $item;
        $this->setValue($currentValue);
        return $currentValue;
    }

    /**
     * Remove from array value
     * 
     * @param mixed $item Item to remove
     * @return array New array value
     */
    public function remove(mixed $item): array
    {
        $currentValue = $this->getValue() ?? [];
        if (!is_array($currentValue)) {
            return [];
        }
        
        $newValue = array_filter($currentValue, fn($val) => $val !== $item);
        $this->setValue(array_values($newValue)); // Re-index array
        return array_values($newValue);
    }

    /**
     * Reset to default value
     * 
     * @return mixed Default value
     */
    public function reset(): mixed
    {
        $this->setValue($this->defaultValue);
        return $this->defaultValue;
    }

    /**
     * Check if has value (not null and not default)
     * 
     * @return bool
     */
    public function hasValue(): bool
    {
        $value = $this->getValue();
        return $value !== null && $value !== $this->defaultValue;
    }

    /**
     * Clear value (remove from session)
     * 
     * @return void
     */
    public function clear(): void
    {
        // DIRECT ACCESS - Try NexaSession first, then fallback
        try {
            $session = NexaSession::getInstance();
            if ($session->isStarted()) {
                $session->remove($this->key);
                return; // Success, no need for fallback
            }
        } catch (\Exception $e) {
            // Fall through to direct access
        }
        
        // Fallback to direct session access
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        unset($_SESSION[$this->key]);
    }
    
    /**
     * Debug method to check NexaState status
     * 
     * @return array Debug information
     */
    public function debug(): array
    {
        $sessionActive = session_status() === PHP_SESSION_ACTIVE;
        
        // DIRECT CHECK - No static cache
        $nexaSessionAvailable = false;
        try {
            $session = NexaSession::getInstance();
            $nexaSessionAvailable = $session !== null;
        } catch (\Exception $e) {
            $nexaSessionAvailable = false;
        }
        
        $debugInfo = [
            'key' => $this->key,
            'default_value' => $this->defaultValue,
            'php_session_active' => $sessionActive,
            'nexa_session_available' => $nexaSessionAvailable,
            'session_status' => session_status(),
            'session_id' => $sessionActive ? session_id() : 'N/A'
        ];
        
        // Test getValue access
        try {
            $debugInfo['current_value'] = $this->getValue();
            $debugInfo['getValue_success'] = true;
        } catch (\Exception $e) {
            $debugInfo['getValue_error'] = $e->getMessage();
            $debugInfo['getValue_success'] = false;
        }
        
        // Direct session check
        if ($sessionActive) {
            $debugInfo['direct_session_value'] = $_SESSION[$this->key] ?? 'NOT_SET';
            $debugInfo['session_keys'] = array_keys($_SESSION);
        }
        
        // NexaSession test - DIRECT ACCESS
        if ($nexaSessionAvailable) {
            try {
                $session = NexaSession::getInstance();
                $debugInfo['nexa_session_test'] = $session->get($this->key, 'TEST_DEFAULT');
                $debugInfo['nexa_session_success'] = true;
            } catch (\Exception $e) {
                $debugInfo['nexa_session_error'] = $e->getMessage();
                $debugInfo['nexa_session_success'] = false;
            }
        }
        
        return $debugInfo;
    }
    
    /**
     * Static debug method for general NexaState status
     * 
     * @return array
     */
    public static function debugGlobal(): array
    {
        return [
            'php_session_status' => session_status(),
            'php_session_status_text' => match(session_status()) {
                PHP_SESSION_DISABLED => 'DISABLED',
                PHP_SESSION_NONE => 'NOT_STARTED',
                PHP_SESSION_ACTIVE => 'ACTIVE',
                default => 'UNKNOWN'
            },
            'nexa_session_instance' => 'Direct Access - No Static Cache',
            'session_data_count' => session_status() === PHP_SESSION_ACTIVE ? count($_SESSION) : 0,
            'session_keys' => session_status() === PHP_SESSION_ACTIVE ? array_keys($_SESSION) : []
        ];
    }
    
    // ========================================================================
    // MODELS INTEGRATION - Dynamic Model Loading & Method Calling
    // ========================================================================
    
    /**
     * Placeholder property untuk backward compatibility (tidak digunakan untuk cache)
     * REMOVED: Model instances cache - using direct instantiation instead
     */
    private static array $modelInstances = [];
    
    /**
     * Load model dari folder models dan call method tertentu
     * 
     * @param string $modelPath Path model (e.g., 'User', 'Access/Akun', 'Domain/Product')
     * @param string $method Method name to call
     * @param array $params Parameters untuk method
     * @param object|null $controller Optional controller instance untuk injection
     * @return mixed Result dari method call
     * @throws Exception
     * 
     * Usage:
     * $users = $this->useModels('User', 'getActiveUsers');
     * $user = $this->useModels('User', 'findByEmail', ['john@example.com']);
     * $account = $this->useModels('Access/Akun', 'getAccount', [123]);
     */
    public function useModels(string $modelPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        try {
            // Get model instance (cached atau buat baru)
            $model = $this->getModelInstance($modelPath, $controller);
            
            // Check if method exists
            if (!method_exists($model, $method)) {
                throw new Exception("Method '{$method}' not found in model '{$modelPath}'");
            }
            
            // Call method dengan parameters
            $result = call_user_func_array([$model, $method], $params);
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception("useModels error ({$modelPath}::{$method}): " . $e->getMessage());
        }
    }
    
    /**
     * Get model instance (dengan caching)
     * 
     * @param string $modelPath Path model
     * @param object|null $controller Optional controller instance
     * @return object Model instance
     * @throws Exception
     */
    public function getModelInstance(string $modelPath, ?object $controller = null): object
    {
        // DIRECT INSTANTIATION - No cache, fresh instance every time
        
        // Build class name dari path
        $className = $this->buildModelClassName($modelPath);
        
        // Check if class exists
        if (!class_exists($className)) {
            throw new Exception("Model class '{$className}' not found. Path: {$modelPath}");
        }
        
        try {
            // Create fresh model instance
            $instance = new $className();
            
            // Inject controller jika ada dan model support
            if ($controller && method_exists($instance, 'setController')) {
                $instance->setController($controller);
            }
            
            return $instance;
            
        } catch (Exception $e) {
            throw new Exception("Failed to instantiate model '{$className}': " . $e->getMessage());
        }
    }
    
    /**
     * Build model class name dari path
     * 
     * @param string $modelPath Model path
     * @return string Full class name
     */
    private function buildModelClassName(string $modelPath): string
    {
        // Convert path separators to namespace separators
        $namespacePath = str_replace(['/', '\\'], '\\', trim($modelPath, '/\\'));
        
        // Split path to get class name
        $pathParts = explode('\\', $namespacePath);
        $className = array_pop($pathParts);
        
        // Build full namespace - Updated to match actual namespace format
        $namespace = 'App\\Models';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }
        
        return $namespace . '\\' . $className;
    }
    
    /**
     * Static method untuk useModels (global access)
     * 
     * @param string $modelPath Model path
     * @param string $method Method name
     * @param array $params Method parameters
     * @param object|null $controller Optional controller
     * @return mixed
     */
    public static function callModel(string $modelPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        $state = new self('temp_model_call', null);
        return $state->useModels($modelPath, $method, $params, $controller);
    }
    
    /**
     * Get available models dalam folder models
     * 
     * @param string $subfolder Optional subfolder (e.g., 'Access', 'Domain')
     * @return array Available model files
     */
    public function getAvailableModels(string $subfolder = ''): array
    {
        $modelsPath = dirname(__DIR__, 2) . '/models';
        
        if (!empty($subfolder)) {
            $modelsPath .= '/' . trim($subfolder, '/');
        }
        
        if (!is_dir($modelsPath)) {
            return [];
        }
        
        $models = [];
        $files = glob($modelsPath . '/*.php');
        
        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $models[] = !empty($subfolder) ? $subfolder . '/' . $fileName : $fileName;
        }
        
        return $models;
    }
    
    /**
     * Get model methods (untuk debugging/discovery)
     * 
     * @param string $modelPath Model path
     * @return array Available methods
     */
    public function getModelMethods(string $modelPath): array
    {
        try {
            $className = $this->buildModelClassName($modelPath);
            
            if (!class_exists($className)) {
                return [];
            }
            
            $reflection = new \ReflectionClass($className);
            $methods = [];
            
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Skip magic methods dan constructor
                if (!str_starts_with($method->getName(), '__')) {
                    $methods[] = [
                        'name' => $method->getName(),
                        'parameters' => array_map(function($param) {
                            return $param->getName();
                        }, $method->getParameters()),
                        'class' => $method->getDeclaringClass()->getName()
                    ];
                }
            }
            
            return $methods;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Debug model information
     * 
     * @param string $modelPath Model path
     * @return array Debug information
     */
    public function debugModel(string $modelPath): array
    {
        $className = $this->buildModelClassName($modelPath);
        
        return [
            'model_path' => $modelPath,
            'class_name' => $className,
            'class_exists' => class_exists($className),
            'available_methods' => $this->getModelMethods($modelPath),
            'cached_instances' => [], // REMOVED: No longer using cache
            'models_folder_path' => dirname(__DIR__, 2) . '/models',
            'available_models' => $this->getAvailableModels()
        ];
    }
    
    /**
     * Clear model cache
     * 
     * @param string|null $modelPath Optional specific model to clear
     * @return void
     */
    public static function clearModelCache(?string $modelPath = null): void
    {
        // REMOVED: No longer using model cache - this method kept for backward compatibility
        // All models are now instantiated fresh every time
    }

    /**
     * Static method untuk useData (global access)
     * 
     * @param string $dataPath Data controller path
     * @param string $method Method name
     * @param array $params Method parameters
     * @param object|null $controller Optional controller
     * @return mixed
     */
    public static function callData(string $dataPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        $state = new self('temp_data_call', null);
        return $state->useDataController($dataPath, $method, $params, $controller);
    }

    /**
     * Load data controller dari App\Controllers\Frontend\Data dan call method tertentu
     * 
     * @param string $dataPath Path data controller (e.g., 'UserData', 'ProductData')
     * @param string $method Method name to call
     * @param array $params Parameters untuk method
     * @param object|null $controller Optional controller instance untuk injection
     * @return mixed Result dari method call
     * @throws Exception
     */
    public function useDataController(string $dataPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        try {
            $controllerInstance = $this->getDataControllerInstance($dataPath, $controller);

            if (!method_exists($controllerInstance, $method)) {
                throw new Exception("Method '{$method}' not found in data controller '{$dataPath}'");
            }

            return call_user_func_array([$controllerInstance, $method], $params);

        } catch (Exception $e) {
            throw new Exception("useData error ({$dataPath}::{$method}): " . $e->getMessage());
        }
    }

    /**
     * Get data controller instance (dengan caching)
     * 
     * @param string $dataPath Path data controller
     * @param object|null $controller Optional controller instance
     * @return object Data controller instance
     * @throws Exception
     */
    private function getDataControllerInstance(string $dataPath, ?object $controller = null): object
    {
        // DIRECT INSTANTIATION - No cache, fresh instance every time
        
        $className = $this->buildDataControllerClassName($dataPath);

        if (!class_exists($className)) {
            throw new Exception("Data controller class '{$className}' not found. Path: {$dataPath}");
        }

        // Create fresh instance - Jika turunan NexaController, harus diberikan argumen konstruktor
        if (is_subclass_of($className, '\\App\\System\\NexaController')) {
            // Ambil template dan deviceLayouts dari parent controller jika ada
            if ($controller !== null && method_exists($controller, 'getTemplateInstance') && method_exists($controller, 'getDeviceLayouts')) {
                $instance = new $className($controller->getTemplateInstance(), $controller->getDeviceLayouts());
            } else {
                // Fallback: buat dummy NexaDom dan array kosong
                $dummyTemplate = new \App\System\Dom\NexaDom();
                $instance = new $className($dummyTemplate, []);
            }
        } else {
            $instance = new $className();
        }

        if ($controller && method_exists($instance, 'setController')) {
            $instance->setController($controller);
        }

        return $instance;
    }

    /**
     * Build data controller class name dari path
     * 
     * @param string $dataPath Data controller path
     * @return string Full class name
     */
    private function buildDataControllerClassName(string $dataPath): string
    {
        $namespacePath = str_replace(['/', '\\'], '\\', trim($dataPath, '/\\'));
        $pathParts = explode('\\', $namespacePath);
        $className = array_pop($pathParts);

        $namespace = 'App\\Controllers\\Frontend\\Data';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Static method untuk useParams (global access)
     * 
     * @param string $paramsPath Params controller path
     * @param string $method Method name
     * @param array $params Method parameters
     * @param object|null $controller Optional controller
     * @return mixed
     */
    public static function callParams(string $paramsPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        $state = new self('temp_params_call', null);
        return $state->useParamsController($paramsPath, $method, $params, $controller);
    }

    /**
     * Load params controller dari App\Controllers\Admin\Data dan call method tertentu
     * 
     * @param string $paramsPath Path params controller (e.g., 'UserParams', 'ProductParams')
     * @param string $method Method name to call
     * @param array $params Parameters untuk method
     * @param object|null $controller Optional controller instance untuk injection
     * @return mixed Result dari method call
     * @throws Exception
     */
    public function useParamsController(string $paramsPath, string $method, array $params = [], ?object $controller = null): mixed
    {
        try {
            $controllerInstance = $this->getParamsControllerInstance($paramsPath, $controller);

            if (!method_exists($controllerInstance, $method)) {
                throw new Exception("Method '{$method}' not found in params controller '{$paramsPath}'");
            }

            return call_user_func_array([$controllerInstance, $method], $params);

        } catch (Exception $e) {
            throw new Exception("useParams error ({$paramsPath}::{$method}): " . $e->getMessage());
        }
    }

    /**
     * Get params controller instance (dengan caching)
     * 
     * @param string $paramsPath Path params controller
     * @param object|null $controller Optional controller instance
     * @return object Params controller instance
     * @throws Exception
     */
    private function getParamsControllerInstance(string $paramsPath, ?object $controller = null): object
    {
        // DIRECT INSTANTIATION - No cache, fresh instance every time
        
        $className = $this->buildParamsControllerClassName($paramsPath);

        if (!class_exists($className)) {
            throw new Exception("Params controller class '{$className}' not found. Path: {$paramsPath}");
        }

        // Create fresh instance - Jika turunan NexaController, harus diberikan argumen konstruktor
        if (is_subclass_of($className, '\\App\\System\\NexaController')) {
            // Ambil template dan deviceLayouts dari parent controller jika ada
            if ($controller !== null && method_exists($controller, 'getTemplateInstance') && method_exists($controller, 'getDeviceLayouts')) {
                $instance = new $className($controller->getTemplateInstance(), $controller->getDeviceLayouts());
            } else {
                // Fallback: buat dummy NexaDom dan array kosong
                $dummyTemplate = new \App\System\Dom\NexaDom();
                $instance = new $className($dummyTemplate, []);
            }
        } else {
            $instance = new $className();
        }

        if ($controller && method_exists($instance, 'setController')) {
            $instance->setController($controller);
        }

        return $instance;
    }

    /**
     * Build params controller class name dari path
     * 
     * @param string $paramsPath Params controller path
     * @return string Full class name
     */
    private function buildParamsControllerClassName(string $paramsPath): string
    {
        $namespacePath = str_replace(['/', '\\'], '\\', trim($paramsPath, '/\\'));
        $pathParts = explode('\\', $namespacePath);
        $className = array_pop($pathParts);

        $namespace = 'App\\Controllers\\Admin';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }

        return $namespace . '\\' . $className;
    }
}

/**
 * Additional helper functions for common use cases
 */

/**
 * Counter state helper
 * 
 * @param string $key
 * @param int $initialValue
 * @return array [currentValue, increment, decrement, reset]
 */
function useCounter(string $key, int $initialValue = 0): array
{
    $state = new NexaState($key, $initialValue);
    
    return [
        $state->getValue(),
        fn(int $amount = 1) => $state->increment($amount),
        fn(int $amount = 1) => $state->decrement($amount),
        fn() => $state->reset()
    ];
}

/**
 * Toggle state helper (for boolean values)
 * 
 * @param string $key
 * @param bool $initialValue
 * @return array [currentValue, toggle, setTrue, setFalse]
 */
function useToggle(string $key, bool $initialValue = false): array
{
    $state = new NexaState($key, $initialValue);
    
    return [
        $state->getValue(),
        fn() => $state->toggle(),
        fn() => $state->setValue(true),
        fn() => $state->setValue(false)
    ];
}

/**
 * Array state helper
 * 
 * @param string $key
 * @param array $initialValue
 * @return array [currentValue, append, remove, clear, reset]
 */
function useArray(string $key, array $initialValue = []): array
{
    $state = new NexaState($key, $initialValue);
    
    return [
        $state->getValue(),
        fn(mixed $item) => $state->append($item),
        fn(mixed $item) => $state->remove($item),
        fn() => $state->setValue([]),
        fn() => $state->reset()
    ];
}

/**
 * Global helper function - Load model dan call method
 * 
 * @param string $modelPath Model path (e.g., 'User', 'Access/Akun')
 * @param string $method Method name to call
 * @param array $params Method parameters
 * @param object|null $controller Optional controller for injection
 * @return mixed Result dari method call
 * 
 * Usage:
 * $users = useModels('User', 'getActiveUsers');
 * $user = useModels('User', 'findByEmail', ['john@example.com']);
 * $account = useModels('Access/Akun', 'getAccount', [123]);
 */
function useModels(string $modelPath, string $method, array $params = [], ?object $controller = null): mixed
{
    return NexaState::callModel($modelPath, $method, $params, $controller);
}

/**
 * Global helper function - Get available models
 * 
 * @param string $subfolder Optional subfolder
 * @return array Available models
 */
function getAvailableModels(string $subfolder = ''): array
{
    $state = new NexaState('temp_models_list', null);
    return $state->getAvailableModels($subfolder);
}

/**
 * Global helper function - Debug model
 * 
 * @param string $modelPath Model path
 * @return array Debug information
 */
function debugModel(string $modelPath): array
{
    $state = new NexaState('temp_model_debug', null);
    return $state->debugModel($modelPath);
}

/**
 * Global helper function - Load data controller dan call method
 * 
 * @param string $dataPath Data controller path (e.g., 'UserData', 'ProductData')
 * @param string $method Method name to call
 * @param array $params Method parameters
 * @param object|null $controller Optional controller for injection
 * @return mixed Result dari method call
 * 
 * Usage:
 * $result = useData('UserData', 'getList');
 */
function useData(string $dataPath, string $method, array $params = [], ?object $controller = null): mixed
{
    return NexaState::callData($dataPath, $method, $params, $controller);
}

/**
 * Global helper function - Load params controller dan call method
 * 
 * @param string $paramsPath Params controller path (e.g., 'UserParams', 'ProductParams')
 * @param string $method Method name to call
 * @param array $params Method parameters
 * @param object|null $controller Optional controller for injection
 * @return mixed Result dari method call
 * 
 * Usage:
 * $result = useParams('UserParams', 'getList');
 */
function useParams(string $paramsPath, string $method, array $params = [], ?object $controller = null): mixed
{
    return NexaState::callParams($paramsPath, $method, $params, $controller);
} 