<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\System\NexaController;
use Exception;

/**
 * Text Controller untuk API endpoints
 */
class ForgeController extends NexaController
{
    /**
     * Text endpoint
     */
    public function index(): array
    {
        // Handle SQL queries (like ApplicationsController::Fetch)
        $requestData = $this->getRequestData();
        
        // Check if this is a NexaModels API call with SQL query
        if (isset($requestData['sql']) && isset($requestData['type'])) {
            // Process SQL query using NexaBig (same as NexaRender)
            return $this->NexaBig($requestData);
        }
        
        // Handle dynamic routing (controller atau model)
        // Check if request contains controller routing info
        if (isset($requestData['controllers']) && isset($requestData['method'])) {
            return $this->routeToController($requestData);
        }
        
        // Check if request contains model routing info
        if (isset($requestData['model']) && isset($requestData['method'])) {
            return $this->routeToModel($requestData);
        }
        
        return [
            'status' => 'success',
            'message' => 'Text API endpoint working!',
            'timestamp' => time(),
            'server_time' => date('Y-m-d H:i:s'),
            'data' => [
                'text' => true,
                'version' => '1.0.0'
            ]
        ];
    }
public function red($data = [], $params = []): array
    {
        // Jika data kosong, coba ambil dari POST/GET request
        if (empty($data)) {
            $data = $this->inputs();
        }
        
        // Validasi data user minimal
        if (empty($data) || !isset($data['user_id'])) {
            return [
                'status' => 'error',
                'message' => 'User data tidak valid. Diperlukan user_id minimal.',
                'timestamp' => time(),
                'data' => null,
                'params' => $params
            ];
        }
        
        // Format data user seperti di OauthController
        $userEntity = isset($data['nama']) ? $this->slugify($data['nama']) : ($data['user_name'] ?? 'user');
        
        // Handle avatar URL - cek jika sudah full URL, jangan tambahkan base URL lagi
        $avatarUrl = 'images/pria.png'; // default
        if (isset($data['avatar']) && !empty($data['avatar'])) {
            // Jika sudah full URL (http:// atau https://), gunakan langsung
            if (preg_match('/^https?:\/\//', $data['avatar'])) {
                $avatarUrl = $data['avatar'];
            } else {
                // Jika relative path, tambahkan base URL
                $avatarUrl = $this->url($data['avatar']);
            }
        } else {
            $avatarUrl = $this->url('images/pria.png');
        }
        
        // Prepare user data untuk setUser (format sama dengan OauthController)
        $userData = [
            'user_id' => $data['user_id'] ?? null,
            'userid' => $data['userid'] ?? $data['user_id'] ?? null,
            'user_name' => $userEntity,
            'user_real_name' => $data['nama'] ?? $data['user_real_name'] ?? $data['name'] ?? 'User',
            'email' => $data['email'] ?? '',
            'avatar' => $avatarUrl,
            'status' => $data['status'] ?? $data['role'] ?? 'user',
            'role' => $data['role'] ?? $data['status'] ?? 'user',
            'login_time' => time(),
            'last_activity' => time()
        ];
        
        // Set user ke session (mengaktifkan semua session methods)
        $this->setUser($userData);
        
        // Set flash message (opsional untuk API)
        $this->setFlash('success', 'Login berhasil! Selamat datang ' . $userData['user_real_name']);
        
        // Set visitor key untuk tracking
        $this->setJsController([
            'uniqueId' => $this->session->getVisitorKey(),
        ]);
        
        // Verifikasi user sudah login dan semua session methods aktif
        $isLoggedIn = $this->isLoggedIn();
        $currentUser = $this->getUser();
        $userId = $this->getUserId();
        $sessionId = $this->session->getId();
        
        // Pastikan session benar-benar tersimpan dengan memanggil beberapa session methods
        $sessionActive = $this->isLoggedIn() && $this->getUser() !== null;
        
        // Response dengan informasi session lengkap
        return [
            'status' => 'success',
            'message' => 'User session berhasil diaktifkan',
            'timestamp' => time(),
            'data' => [
                'authenticated' => true,
                'is_logged_in' => $isLoggedIn,
                'user' => $currentUser,
                'session_active' => $sessionActive,
                'visitor_key' => $this->session->getVisitorKey(),
                'session_id' => $sessionId,
                'login_time' => $userData['login_time'],
                'last_activity' => $userData['last_activity']
            ],
            'params' => $params,
            'session_info' => [
                'has_session' => $isLoggedIn,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'can_access_user_methods' => $isLoggedIn,
                'session_methods_active' => [
                    'isLoggedIn' => $this->isLoggedIn(),
                    'getUser' => $this->getUser() !== null,
                    'getUserId' => $this->getUserId() !== null,
                    'getSession' => $this->getSession() !== null,
                    'hasSession_user' => $this->hasSession('_user_data')
                ]
            ]
        ];
    }


    
    /**
     * Helper method untuk routing dinamis ke controller
     * 
     * @param array $requestData Data dari request dengan format:
     *   - controllers: Nama controller (e.g., "ApplicationsControllers")
     *   - method: Nama method yang akan dipanggil (e.g., "metaDataPublic")
     *   - params: Parameter untuk method (optional, bisa string atau array)
     * 
     * @return array Response dari controller method
     * @throws Exception Jika controller atau method tidak ditemukan
     */
    protected function routeToController(array $requestData): array
    {
        try {
            $controllerName = $requestData['controllers'] ?? '';
            $methodName = $requestData['method'] ?? '';
            
            if (empty($controllerName) || empty($methodName)) {
                return [
                    'status' => 'error',
                    'message' => 'controllers dan method harus diisi',
                    'error' => 'MISSING_REQUIRED_FIELDS',
                    'timestamp' => time()
                ];
            }
            
            // Decrypt params jika ada (seperti eventsControllers)
            $params = [];
            if (isset($requestData['params']) && !empty($requestData['params'])) {
                try {
                    // Periksa apakah Encrypt instance tersedia
                    if ($this->Encrypt === null) {
                        $this->Encrypt = new \App\System\Helpers\NexaEncrypt();
                    }
                    $params = $this->Encrypt->decryptJson($requestData['params']) ?? [];
                } catch (\Exception $e) {
                    error_log("RouteToController Decrypt Error: " . $e->getMessage());
                    // Jika decrypt gagal, coba gunakan params langsung
                    $params = is_array($requestData['params']) ? $requestData['params'] : [];
                }
            }
            
            // Normalize controller name menggunakan helper method (seperti handleCrossControllerCall)
            $normalizedControllerName = $this->normalizeControllerName($controllerName);
            
            // Validasi format controller name (seperti handleCrossControllerCall)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $normalizedControllerName)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid controller name format',
                    'error' => 'INVALID_CONTROLLER_NAME',
                    'timestamp' => time()
                ];
            }
            
            // Validasi format method name (seperti handleCrossControllerCall)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid method name format',
                    'error' => 'INVALID_METHOD_NAME',
                    'timestamp' => time()
                ];
            }
            
            // Resolve namespace menggunakan helper method (dinamis, seperti handleCrossControllerCall)
            $namespace = $this->resolveControllerNamespace($normalizedControllerName);
            $controllerClass = $namespace . '\\' . $normalizedControllerName;
            
            // Check if controller class exists (seperti handleCrossControllerCall)
            if (!class_exists($controllerClass)) {
                return [
                    'status' => 'error',
                    'message' => "Controller '{$normalizedControllerName}' tidak ditemukan. Mencari class: {$controllerClass}",
                    'error' => 'CONTROLLER_NOT_FOUND',
                    'timestamp' => time()
                ];
            }
            
            // Create controller instance (seperti handleCrossControllerCall)
            $template = $this->getTemplateInstance();
            $deviceLayouts = $this->getDeviceLayouts();
            $controller = new $controllerClass($template, $deviceLayouts);
            
            // Check if method exists (seperti handleCrossControllerCall)
            if (!method_exists($controller, $methodName)) {
                return [
                    'status' => 'error',
                    'message' => "Method '{$methodName}' not found in controller '{$normalizedControllerName}'",
                    'error' => 'METHOD_NOT_FOUND',
                    'timestamp' => time()
                ];
            }
            
            // Check if method is public (seperti handleCrossControllerCall)
            $reflection = new \ReflectionMethod($controller, $methodName);
            if (!$reflection->isPublic()) {
                return [
                    'status' => 'error',
                    'message' => "Method '{$methodName}' is not accessible in controller '{$normalizedControllerName}'",
                    'error' => 'METHOD_NOT_ACCESSIBLE',
                    'timestamp' => time()
                ];
            }
            
            // Call the method (seperti handleCrossControllerCall)
            if (!empty($params) && is_array($params)) {
                $result = $controller->$methodName($params);
            } else {
                $result = $controller->$methodName();
            }
            
            // Return hasil (seperti handleCrossControllerCall)
            return [
                'status' => 'success',
                'data' => $result,
                'controller' => $controllerName,
                'method' => $methodName,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            error_log("RouteToController Exception: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'An error occurred while calling cross-controller method',
                'error' => 'CROSS_CONTROLLER_ERROR',
                'details' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }
    
    /**
     * Helper method untuk routing dinamis ke model
     * 
     * @param array $requestData Data dari request dengan format:
     *   - model: Nama model (e.g., "Office")
     *   - method: Nama method yang akan dipanggil (e.g., "tablesShow")
     *   - params: Parameter untuk method (optional, bisa string atau array)
     * 
     * @return array Response dari model method
     * @throws Exception Jika model atau method tidak ditemukan
     */
    protected function routeToModel(array $requestData): array
    {
        try {
            // Validasi input yang diperlukan (seperti eventsModel)
            if (!isset($requestData['model']) || empty($requestData['model'])) {
                return [
                    'status' => 'error',
                    'message' => 'Model path is required',
                    'error' => 'MODEL_PATH_MISSING',
                    'timestamp' => time()
                ];
            }
            
            if (!isset($requestData['method']) || empty($requestData['method'])) {
                return [
                    'status' => 'error',
                    'message' => 'Method name is required',
                    'error' => 'METHOD_NAME_MISSING',
                    'timestamp' => time()
                ];
            }
            
            // Sanitasi input (seperti eventsModel)
            $modelPath = trim($requestData['model']);
            $methodName = trim($requestData['method']);
            $params = [];
            
            // ✅ FIX: Cek Content-Type untuk menentukan sumber data
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isMultipartFormData = strpos($contentType, 'multipart/form-data') !== false;
            
            // ✅ FIX: Jika Content-Type adalah multipart/form-data, ambil data dari $_POST
            // Ini penting untuk file upload karena FormData mengirim data ke $_POST dan file ke $_FILES
            if ($isMultipartFormData && !empty($_POST)) {
                // ✅ FIX: Untuk FormData, params bisa ada di $_POST langsung atau di $_POST['params']
                if (isset($_POST['params']) && !empty($_POST['params'])) {
                    // Params ada di $_POST['params'] (mungkin encrypted)
                    try {
                        if ($this->Encrypt === null) {
                            $this->Encrypt = new \App\System\Helpers\NexaEncrypt();
                        }
                        $decryptedParams = $this->Encrypt->decryptJson($_POST['params']);
                        $params = is_array($decryptedParams) ? $decryptedParams : [];
                    } catch (\Exception $e) {
                        error_log("Failed to decrypt params from \$_POST: " . $e->getMessage());
                        // Fallback: gunakan $_POST langsung (jika tidak encrypted)
                        $params = $_POST;
                    }
                } else {
                    // ✅ FIX: Jika params tidak ada di $_POST['params'], gunakan $_POST langsung
                    // Ini untuk kasus dimana FormData dikirim tanpa encryption
                    $params = $_POST;
                    error_log("✅ Using \$_POST directly for multipart/form-data (no params key found)");
                }
                
                // ✅ FIX: Log untuk debugging
                error_log("✅ FormData detected: Content-Type={$contentType}, \$_POST keys=" . implode(', ', array_keys($_POST)) . ", \$_FILES keys=" . implode(', ', array_keys($_FILES ?? [])));
            } else {
                // ✅ FIX: Untuk JSON atau request lainnya, ambil dari $requestData['params']
                if (isset($requestData['params']) && !empty($requestData['params'])) {
                    try {
                        // Periksa apakah Encrypt instance tersedia
                        if ($this->Encrypt === null) {
                            $this->Encrypt = new \App\System\Helpers\NexaEncrypt();
                        }
                        $decryptedParams = $this->Encrypt->decryptJson($requestData['params']);
                        $params = is_array($decryptedParams) ? $decryptedParams : [];
                    } catch (\Exception $e) {
                        error_log("Failed to decrypt params: " . $e->getMessage());
                        $params = [];
                    }
                }
            }
            
            // Validasi format model path (seperti eventsModel)
            if (!preg_match('/^[a-zA-Z0-9_\/\\\\]+$/', $modelPath)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid model path format',
                    'error' => 'INVALID_MODEL_PATH',
                    'timestamp' => time()
                ];
            }
            
            // Validasi format method name (seperti eventsModel)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid method name format',
                    'error' => 'INVALID_METHOD_NAME',
                    'timestamp' => time()
                ];
            }
            
            // Dapatkan instance model (seperti eventsModel)
            $modelInstance = $this->refModels($modelPath);
            
            // Extract userid dari params jika ada dan set ke model instance
            // Ini memungkinkan model menggunakan userid tanpa session
            if (!empty($params) && is_array($params)) {
                // Cek apakah userid ada di params (bisa di level pertama atau nested)
                $userId = null;
                
                // Cek di level pertama params
                if (isset($params['userid']) && !empty($params['userid'])) {
                    $userId = (int)$params['userid'];
                } 
                // Cek di array pertama jika params adalah array of arrays
                elseif (isset($params[0]) && is_array($params[0]) && isset($params[0]['userid'])) {
                    $userId = (int)$params[0]['userid'];
                }
                // Cek di semua elemen params jika array
                else {
                    foreach ($params as $param) {
                        if (is_array($param) && isset($param['userid']) && !empty($param['userid'])) {
                            $userId = (int)$param['userid'];
                            break;
                        }
                    }
                }
                
                // Set userid ke model instance jika ditemukan
                if ($userId !== null && method_exists($modelInstance, 'setRequestUserId')) {
                    $modelInstance->setRequestUserId($userId);
                }
            }
            
            // Periksa apakah method ada (seperti eventsModel)
            if (!method_exists($modelInstance, $methodName)) {
                return [
                    'status' => 'error',
                    'message' => "Method '{$methodName}' not found in model '{$modelPath}'",
                    'error' => 'METHOD_NOT_FOUND',
                    'timestamp' => time()
                ];
            }
            
            // Panggil method dengan parameter jika ada (seperti eventsModel)
            try {
                if (!empty($params) && is_array($params)) {
                    // Ensure all params are valid (not null for array type hints)
                    $validParams = array_filter($params, function($param) {
                        return $param !== null;
                    });
                    $result = $modelInstance->$methodName(...$validParams);
                } else {
                    $result = $modelInstance->$methodName();
                }
            } catch (\TypeError $e) {
                error_log("TypeError in method call: " . $e->getMessage());
                return [
                    'status' => 'error',
                    'message' => 'Invalid parameter types passed to method',
                    'error' => 'INVALID_PARAMETER_TYPES',
                    'details' => $e->getMessage(),
                    'timestamp' => time()
                ];
            }
            
            // Return hasil (seperti eventsModel)
            return [
                'status' => 'success',
                'data' => $result,
                'model' => $modelPath,
                'method' => $methodName,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\BadMethodCallException $e) {
            error_log("RouteToModel BadMethodCallException: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Model not found or could not be instantiated',
                'error' => 'MODEL_INSTANTIATION_ERROR',
                'details' => $e->getMessage(),
                'timestamp' => time()
            ];
            
        } catch (\Exception $e) {
            error_log("RouteToModel Exception: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'An error occurred while processing the request',
                'error' => 'PROCESSING_ERROR',
                'details' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }
    
    /**
     * Resolve model namespace secara dinamis
     * 
     * @param string $modelName Nama model
     * @return string Namespace lengkap tanpa nama class
     */
    protected function resolveModelNamespace(string $modelName): string
    {
        // Daftar namespace yang umum digunakan untuk models
        $commonNamespaces = [
            'App\\Models',
            'App\\Models\\Office',
            'App\\Models\\Role',
            'App\\Models\\Drive',
        ];
        
        // Coba setiap namespace untuk menemukan model
        foreach ($commonNamespaces as $namespace) {
            $fullClassName = $namespace . '\\' . $modelName;
            if (class_exists($fullClassName)) {
                return $namespace;
            }
        }
        
        // Jika tidak ditemukan, coba scan direktori models
        $foundNamespace = $this->scanModelDirectories($modelName);
        if ($foundNamespace !== null) {
            return $foundNamespace;
        }
        
        // Default: App\Models namespace (fallback)
        return 'App\\Models';
    }
    
    /**
     * Scan direktori models untuk menemukan file model
     * 
     * @param string $modelName Nama model
     * @return string|null Namespace jika ditemukan, null jika tidak
     */
    protected function scanModelDirectories(string $modelName): ?string
    {
        $modelsBasePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'models';
        $fileName = $modelName . '.php';
        
        // Normalize path separators
        $modelsBasePath = realpath($modelsBasePath);
        if ($modelsBasePath === false) {
            return null;
        }
        
        // Scan direktori models secara rekursif
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsBasePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $fileName) {
                // Get absolute path of the file
                $filePath = $file->getPath();
                $filePath = realpath($filePath);
                
                if ($filePath === false) {
                    continue;
                }
                
                // Calculate relative path from models base
                $relativePath = str_replace($modelsBasePath, '', $filePath);
                $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
                
                // Convert path separators to namespace separators
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                
                // Convert path to namespace
                // e.g., "" -> "App\\Models"
                // e.g., "Office" -> "App\\Models\\Office"
                // e.g., "Role" -> "App\\Models\\Role"
                if (empty($relativePath)) {
                    return 'App\\Models';
                } else {
                    return 'App\\Models\\' . $relativePath;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Normalize controller name (handle plural/singular variations)
     * 
     * @param string $name Controller name dari request
     * @return string Normalized controller name
     */
    protected function normalizeControllerName(string $name): string
    {
        // If ends with "Controllers", replace with "Controller"
        if (substr($name, -11) === 'Controllers') {
            return substr($name, 0, -1); // Remove last "s" -> "ApplicationsController"
        }
        
        // If already ends with "Controller", return as is
        if (substr($name, -10) === 'Controller') {
            return $name;
        }
        
        // If ends with "s" but not "Controllers", might be plural form
        // e.g., "Users" -> "UsersController"
        if (substr($name, -1) === 's') {
            return $name . 'Controller';
        }
        
        // Default: add "Controller" suffix
        return $name . 'Controller';
    }
    
    /**
     * Resolve controller namespace secara dinamis
     * Mencari controller di berbagai namespace yang umum digunakan
     * 
     * @param string $controllerName Nama controller
     * @return string Namespace lengkap tanpa nama class
     */
    protected function resolveControllerNamespace(string $controllerName): string
    {
        // Daftar namespace yang umum digunakan (dalam urutan prioritas)
        $commonNamespaces = [
            'App\\Controllers\\Admin',
            'App\\Controllers\\Api',
            'App\\Controllers\\Frontend',
            'App\\Controllers\\Docs',
            'App\\Controllers',
        ];
        
        // Coba setiap namespace untuk menemukan controller
        foreach ($commonNamespaces as $namespace) {
            $fullClassName = $namespace . '\\' . $controllerName;
            if (class_exists($fullClassName)) {
                return $namespace;
            }
        }
        
        // Jika tidak ditemukan, coba scan direktori controllers
        $foundNamespace = $this->scanControllerDirectories($controllerName);
        if ($foundNamespace !== null) {
            return $foundNamespace;
        }
        
        // Default: Admin namespace (fallback)
        return 'App\\Controllers\\Admin';
    }
    
    /**
     * Scan direktori controllers untuk menemukan file controller
     * 
     * @param string $controllerName Nama controller
     * @return string|null Namespace jika ditemukan, null jika tidak
     */
    protected function scanControllerDirectories(string $controllerName): ?string
    {
        $controllersBasePath = dirname(__DIR__); // Path ke folder controllers
        $fileName = $controllerName . '.php';
        
        // Normalize path separators
        $controllersBasePath = realpath($controllersBasePath);
        if ($controllersBasePath === false) {
            return null;
        }
        
        // Scan direktori controllers secara rekursif
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllersBasePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $fileName) {
                // Get absolute path of the file
                $filePath = $file->getPath();
                $filePath = realpath($filePath);
                
                if ($filePath === false) {
                    continue;
                }
                
                // Calculate relative path from controllers base
                $relativePath = str_replace($controllersBasePath, '', $filePath);
                $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
                
                // Convert path separators to namespace separators
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                
                // Convert path to namespace
                // e.g., "Admin" -> "App\\Controllers\\Admin"
                // e.g., "Api" -> "App\\Controllers\\Api"
                // e.g., "Admin\\Access" -> "App\\Controllers\\Admin\\Access"
                if (empty($relativePath)) {
                    return 'App\\Controllers';
                } else {
                    return 'App\\Controllers\\' . $relativePath;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Prepare method params - handle string params (might be encoded/encrypted)
     * Menggunakan decryptJson seperti eventsControllers dan eventsModel
     * 
     * @param mixed $params Params dari request
     * @return mixed Array atau value yang sudah diproses
     */
    protected function prepareMethodParams($params)
    {
        // If params is empty, return empty array
        if (empty($params)) {
            return [];
        }
        
        // If params is already an array/object, return as is (tidak perlu decrypt)
        if (is_array($params)) {
            return $params;
        }
        
        // If params is string, try to decrypt it using Encrypt->decryptJson
        // (seperti yang dilakukan di eventsControllers dan eventsModel)
        if (is_string($params) && !empty($params)) {
            try {
                // Periksa apakah Encrypt instance tersedia
                if ($this->Encrypt === null) {
                    $this->Encrypt = new \App\System\Helpers\NexaEncrypt();
                }
                
                // Decrypt params menggunakan decryptJson (XOR + Base64)
                $decryptedParams = $this->Encrypt->decryptJson($params);
                
                // Jika hasil decrypt adalah array, return langsung
                if (is_array($decryptedParams)) {
                    return $decryptedParams;
                }
                
                // Jika hasil decrypt adalah string, coba parse sebagai JSON
                if (is_string($decryptedParams)) {
                    $jsonDecoded = json_decode($decryptedParams, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                        return $jsonDecoded;
                    }
                    return [$decryptedParams];
                }
                
                // Jika hasil decrypt adalah nilai lain, wrap dalam array
                return [$decryptedParams];
                
            } catch (\Exception $e) {
                // Jika decrypt gagal, log error dan coba decode base64 biasa
                error_log("UserController Decrypt Error: " . $e->getMessage());
                
                // Fallback: try simple base64 decode
                $decoded = base64_decode($params, true);
                if ($decoded !== false) {
                    $jsonDecoded = json_decode($decoded, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                        return $jsonDecoded;
                    }
                    return [$decoded];
                }
                
                // Jika semua gagal, coba parse sebagai JSON langsung
                $jsonDecoded = json_decode($params, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                    return $jsonDecoded;
                }
                
                // Jika semua gagal, return params sebagai string dalam array
                return [$params];
            }
        }
        
        // Default: wrap dalam array
        return [$params];
    }
    
    /**
     * Created endpoint for POST requests
     */
    public function created($data = [], $params = []): array
    {
        // Handle SQL queries (like ApplicationsController::Fetch)
        // Check if this is a NexaModels API call with SQL query
        if (isset($data['sql']) && isset($data['type'])) {
            // Process SQL query using NexaBig (same as NexaRender)
            return $this->NexaBig($data);
        }
        
        // Handle dynamic controller routing for POST requests
        if (isset($data['controllers']) && isset($data['method'])) {
            return $this->routeToController($data);
        }
        
        // Handle dynamic model routing for POST requests
        if (isset($data['model']) && isset($data['method'])) {
            return $this->routeToModel($data);
        }
        
        return [
            'status' => 'success',
            'params' => $data
        ];
    }
    
    /**
     * Updated endpoint for PUT/PATCH requests
     */
    public function updated($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Text updated successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
    }
    
    /**
     * Deleted endpoint for DELETE requests
     */
    public function deleted($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Text deleted successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
    }
}

