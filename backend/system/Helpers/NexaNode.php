<?php
namespace App\System\Helpers;

/**
 * NexaNode - General Purpose Controller Router
 * Provides dynamic controller routing and method handling
 */
class NexaNode
{
    private $view;
    private $baseNamespace = '\\App\\Controllers\\';

    /**
     * Constructor
     * 
     * @param object $view View handler
     */
    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * Route to specific controller
     * 
     * @param string $page Page name
     * @param array $params All parameters from router
     * @param string $namespace Optional custom namespace
     * @return mixed False if controller not found, string for view name, array for data, true for default behavior
     */
    public function NodeController(string $page, array $params, string $namespace = null)
    {
        // Convert page name to controller class
        $controllerName = ucfirst(strtolower($page)) . 'Controller';
        $controllerClass = ($namespace ?? $this->baseNamespace) . $controllerName;

        if (!class_exists($controllerClass)) {
            return false;
        }

        try {
            $controller = new $controllerClass();

            // Inject dependencies if available
            if (method_exists($controller, 'setDependencies')) {
                $controller->setDependencies();
            }

            // Determine method to call
            $requestedMethod = $params['method'] ?? 'index';
            $method = 'index'; // default fallback
            $usedFallback = false;

            if (!empty($requestedMethod) && $requestedMethod !== 'index') {
                if (method_exists($controller, $requestedMethod)) {
                    $method = $requestedMethod;
                } else {
                    $usedFallback = true;
                }
            }

            // Store fallback info
            $params['_method_used'] = $method;
            $params['_requested_method'] = $requestedMethod;
            $params['_used_fallback'] = $usedFallback;

            // Prepare parameters for the controller
            $controllerParams = $this->prepareControllerParams($page, $params);

            // Call the method and get result
            $result = $controller->$method($controllerParams);

            return $result ?? true;

        } catch (\Exception $e) {
            error_log("Controller error ({$controllerClass}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare parameters for controller
     * 
     * @param string $page Page name
     * @param array $params Route parameters
     * @return array
     */
    private function prepareControllerParams(string $page, array $params): array
    {
        $controllerParams = [
            'page' => $page,
            'username' => $params['username'] ?? '',
        ];

        // Add additional parameters
        if (isset($params['params'])) {
            $controllerParams['params'] = $params['params'];
        }

        if (isset($params['method'])) {
            $controllerParams['requested_method'] = $params['method'];
        }

        // Add individual parameters (param_0, param_1, etc.)
        foreach ($params as $key => $value) {
            if (strpos($key, 'param_') === 0) {
                $controllerParams[$key] = $value;
            }
        }

        return $controllerParams;
    }


}
