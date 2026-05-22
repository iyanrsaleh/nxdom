<?php
declare(strict_types=1);
namespace App\Controllers;
use App\System\NexaController;
use Exception;

/**
 * HomeController - Enhanced with Integrated NexaNode for Frontend
 * Now uses Frontend namespace for public-facing pages
 */
class ApiController extends NexaController
{
    private string $nodeNamespace = 'App\\Controllers\\Api\\';
    
 /**
     * Constructor - No longer needs NexaNode instantiation
     * 
     * @param object $view View handler
     * @param array $deviceLayouts Device layout configuration
     */
    public function __construct($view, array $deviceLayouts = [])
    {
        parent::__construct($view, $deviceLayouts);

        $this->setControllerNamespace($this->nodeNamespace);
    }
    

    
    /**
     * Home index page - /
     * Enhanced with integrated NexaNode functionality for Frontend
     */
    public function index($params = []): void{

        // Handle preflight OPTIONS (CORS) — hanya jika TIDAK ada Authorization header
        // Jika ada Authorization, teruskan ke SDK sebagai request biasa
        $earlyAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? getallheaders()['Authorization'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && empty($earlyAuth)) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS');
            header('Access-Control-Max-Age: 86400');
            http_response_code(200);
            exit;
        }

        // Set CORS headers untuk semua request
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS');

        
        // Handle both string and array inputs for backward compatibility
        if (is_string($params)) {
            // Convert string to array format
            $params = ['page' => $params];
        } elseif (!is_array($params)) {
            // Fallback to empty array if neither string nor array
            $params = [];
        }
        
        // Parse the requested page to extract controller and method
        // Get page from params or from slug (slug2 after 'api')
        $page = $params['page'] ?? $this->getSlug(2) ?? 'index';
        $method = 'index'; // default method
        
        // Check if page contains a slash (controller/method format)
        if (strpos($page, '/') !== false) {
            $parts = explode('/', $page);
            $page = $parts[0]; // First part is the controller
            $method = $parts[1] ?? 'index'; // Second part is the method (if exists)
        }
        
        // If page is still empty or 'index', try to get from slug
        if (empty($page) || $page === 'index') {
            $page = $this->getSlug(2) ?? '';
        }

        // Cek Authorization header — jika ada, biarkan nodeApiControllerData
        // yang handle routing ke SdkController via production table
        $authHeader = $this->getHeader('Authorization', '');
        if (empty($authHeader)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        $hasSdkAuth = !empty($authHeader);

        // SDK mode: URL = /api/{method}
        // Jika ada slug method di URL → gunakan itu.
        // Jika tidak ada → biarkan $format (HTTP method) yang tentukan: POST→created, GET→index, dll.
        if ($hasSdkAuth) {
            $sdkMethod = $page ?: ($this->getSlug(1) ?: '');
            if (!empty($sdkMethod)) {
                $params['method'] = $sdkMethod; // explicit method dari URL
            }
            // jika kosong, JANGAN set params['method'] — biarkan $format (HTTP-based) jalan
            $page = ''; // reset agar tidak dicari sebagai controller name
        }
        
        try {
            // Jika ada Authorization header ATAU page controller ditemukan, jalankan
            if ($hasSdkAuth || $this->controllerExistsData($page)) {
                $requestMethod = $this->getRequestMethod();
                $formatApiResponse = $this->formatApiResponse($method, $requestMethod);

                $response = $this->nodeApiControllerData($page, (array)$params, $this->nodeNamespace);
                
                 // If controller returned data, send as JSON response
                 if ($response !== null && $response !== true) {
                     if (is_array($response)) {
                         $this->json($response);
                     } else {
                         $this->json(['data' => $response]);
                     }
                    return;
                 }
                
                 // If no response data, send default API response
                 $this->json([
                     'status' => 'success',
                     'message' => 'API endpoint executed successfully',
                     'requestMethod' => $requestMethod,
                     'controller' => $page.'Controller',
                     'method' => $method,
                     'formatApiResponse' => $formatApiResponse,
                     'response' => $response,
                     'timestamp' => time()
                 ]);
                 return;
            } else {
                // Controller not found - return error response
                $this->json([
                    'error' => 'Controller not found',
                    'requested_page' => $page,
                    'controller_class' => $this->nodeNamespace . ucfirst(strtolower($page)) . 'Controller',
                    'timestamp' => time()
                ], 404);
                return;
            }
            
        } catch (Exception $e) {
            $this->json([
                'error' => 'Internal server error',
                'message' => 'API temporarily unavailable.',
                'timestamp' => time()
            ], 500);
            return;
        }
    }
    private function formatApiResponse($data, string $httpMethod): array
    {
        $response = [
            'status' => 'success',
            'code' => 200,
            'message' => 'Success',
            'data' => null,
            'timestamp' => time()
        ];

        switch ($httpMethod) {
            case 'GET':
                $response['code'] = 200;
                $response['message'] = 'Data retrieved successfully';
                break;
            case 'POST':
                $response['code'] = 201;
                $response['message'] = 'Data created successfully';
                break;
            case 'PUT':
                $response['code'] = 200;
                $response['message'] = 'Data updated successfully';
                break;
            case 'HEAD':
                $response['code'] = 200;
                $response['message'] = 'Data updated successfully';
                break;
            case 'PATCH':
                $response['code'] = 200;
                $response['message'] = 'Data updated successfully';
                break;
            case 'DELETE':
                $response['code'] = 200;
                $response['message'] = 'Data deleted successfully';
                break;
        }

        if (is_array($data)) {
            if (isset($data['status'])) $response['status'] = $data['status'];
            if (isset($data['code'])) $response['code'] = $data['code'];
            if (isset($data['message'])) $response['message'] = $data['message'];
            if (isset($data['data'])) {
                $response['data'] = $data['data'];
            } else {
                // If data key not explicitly set, use entire input as data
                $response['data'] = $data;
            }
        } else {
            $response['data'] = $data;
        }

        return $response;
    }
    /**
     * Frontend page routing - /{page}/{method?}
     * Supports dynamic method routing for public pages
     */
  
    

}
