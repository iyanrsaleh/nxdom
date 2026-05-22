<?php
namespace App\System\Helpers;
use App\System\NexaController;

class NexaJs extends NexaController {
    /**
     * Generate main.js secara dinamis menggunakan data dari setJsController()
     * Dapat diakses melalui /app/main.js
     * 
     * Juga menangani data langsung ketika parameter ?live=1 ada
     * 
     * @return void
     */

    
    /**
     * Constructor override untuk menangani kasus khusus
     * 
     * @param NexaDom|null $template Template instance atau null
     * @param array $deviceLayouts Device layouts
     */
    public function __construct(?\App\System\Dom\NexaDom $template = null, array $deviceLayouts = [])
    {
        // Jika tidak ada template yang diberikan, buat dummy template
        if ($template === null) {
            $template = new \App\System\Dom\NexaDom();
        }
        
        // Panggil parent constructor
        parent::__construct($template, $deviceLayouts);
        
        // Disable auto render karena kita tidak perlu render template
        $this->disableAutoRender();
    }
    


    public function main() {
        // Ensure session is started and consistent
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        

        // Periksa apakah ini request data langsung
        $isLiveRequest = isset($_GET['live']) && $_GET['live'] == '1';
        $isDebugRequest = isset($_GET['debug']) && $_GET['debug'] == '1';
        
        if ($isLiveRequest) {
            // Mode data langsung - kembalikan JSON
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $controllerData = $this->getControllerData();
            error_log('Live request data: ' . json_encode($controllerData));
            echo json_encode($controllerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        if ($isDebugRequest) {
            // Mode debug - tampilkan JavaScript sebagai plain text
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            // Mode file JavaScript normal
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
        }
        
        try { 
            // Ambil konfigurasi dinamis dengan fallback
            $appUrl = $this->normalizeUrl($this->getBaseUrl());
            $baseDir = $this->normalizeUrl($this->getCurrentUrl());
            $appName = 'NexaUI Framework';
            
            // Ambil data spesifik pengguna jika login
            $isLoggedIn = isset($_SESSION['user_id']) ? 'true' : 'false';
            $userId = $this->session->getUserId()??'';
            $userSlug = $this->session->getUserSlug()??'';
            
            // Pastikan CSRF token selalu ada - generate jika tidak ada
            $csrfToken = $_SESSION['csrf_token'] ?? '';
            if (empty($csrfToken)) {
                // Generate CSRF token baru jika kosong
                $csrfToken = bin2hex(random_bytes(32));
                $_SESSION['csrf_token'] = $csrfToken;
            }
            
            // Ambil data dari controller mana pun yang memanggil setJsController()
            $controllerData = $this->getControllerData();
            
            // Ambil debug info terpisah
            $debugInfo = $this->getDebugInfo();
            
            // Construct api_base dengan aman (hindari double slash)
            $apiBase = rtrim($appUrl, '/') . '/api';
            $apiBase = $this->normalizeUrl($apiBase);
            
            // Buat konten JavaScript dengan validasi ketat
            $jsContent = $this->generateMainJs([
                'app_url' => $appUrl,
                'base_dir' => $baseDir,
                'app_name' => $appName,
                'is_logged_in' => $isLoggedIn,
                'user_id' => $userId,
                'user_slug' => $userSlug,
                'csrf_token' => $csrfToken,
                'api_base' => $apiBase,
                'controller_data' => $controllerData,
                'debug_info' => $debugInfo
            ]);
            
            if ($isDebugRequest) {
                echo "=== DEBUG MODE - JavaScript Content ===\n\n";
                echo $jsContent;
                echo "\n\n=== END DEBUG ===";
            } else {
                echo $jsContent;
            }
            
        } catch (\Exception $e) {
            // Fallback jika ada error - generate minimal working JavaScript
            echo $this->generateFallbackJs($e->getMessage());
        }
        exit;
    }
    
    /**
     * Ambil data yang dikirim oleh controller melalui setJsController()
     * 
     * @return array
     */
    private function getControllerData() {
        // Periksa apakah ada data dari controllers
        if (!parent::hasJsControllerData()) {
            // Fallback: Coba regenerate data jika HomeController pernah diakses
            if (isset($_SESSION['last_controller']) && $_SESSION['last_controller'] === 'home') {
                            return [
                'source' => 'HomeController (fallback)',
                'from_page' => '/',
                'data' => [
                    'message' => 'Fallback data - session mismatch detected',
                    'fallback' => true,
                    'user_name' => 'John Doe',
                    'page_loaded_at' => date('Y-m-d H:i:s')
                ],
                'sent_at' => date('Y-m-d H:i:s')
            ];
            }
            
            return [
                'message' => 'Tidak ada data controller tersedia',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Ambil semua data dari controllers
        $allControllerData = parent::getJsControllerData();
        
        // Safety check untuk memastikan $allControllerData adalah array
        if (!is_array($allControllerData) || empty($allControllerData)) {
            return [
                'message' => 'Data controller kosong atau tidak valid',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Cari controller yang paling baru berdasarkan request_time
        $latestController = null;
        $latestTime = 0;
        
        foreach ($allControllerData as $controller => $info) {
            $requestTime = isset($info['request_time']) ? strtotime($info['request_time']) : 0;
            if ($requestTime > $latestTime) {
                $latestTime = $requestTime;
                $latestController = $controller;
            }
        }
        
        // Jika ada controller terbaru, gunakan data tersebut
        if ($latestController && isset($allControllerData[$latestController])) {
            $controllerData = $allControllerData[$latestController];
            
            // TETAP SEPERTI ASLI - untuk backward compatibility
            $result = [
                'source' => ucfirst($latestController) . 'Controller',
                'base_url' => $this->normalizeUrl($this->getBaseUrl()),
                'from_page' => $controllerData['page_uri'] ?? '/',
                'page_index' => $controllerData['page_index'] ?? '/',
                'page_home' =>  $controllerData['page_home'] ?? '/',
                'projectName' =>$controllerData['projectName'] ?? '/',
                'projectAssets' =>$controllerData['projectName'].'/assets' ?? '/assets',
                'projectDrive' => $controllerData['projectName'].'/assets/drive' ?? '/assets/drive',
                'packages' => $controllerData['packages'] ?? '/',
                'data' => $controllerData['data'] ?? [],
                'sent_at' => $controllerData['request_time'] ?? date('Y-m-d H:i:s'),
                'all_controllers' => array_keys($allControllerData)
            ];
            
            // TAMBAHAN BARU - jika ada data dengan skema baru
            if (isset($controllerData['callback']) || isset($controllerData['meta'])) {
                $result['callback'] = $controllerData['callback'] ?? [];
                $result['meta'] = $controllerData['meta'] ?? [
                    'controller_name' => $latestController,
                    'method_name' => 'unknown',
                    'request_time' => date('Y-m-d H:i:s'),
                    'timestamp' => time()
                ];
            }
            
            return $result;
        }
        
        return [
            'message' => 'Tidak ditemukan data controller yang valid',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Ambil debug information terpisah dari controller data
     * 
     * @return array
     */
    private function getDebugInfo() {
        // Debug: Log session status
        $debug = [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'session_data_keys' => isset($_SESSION) ? array_keys($_SESSION) : [],
            'has_controller_data_flag' => isset($_SESSION['has_js_controller_data']) ? $_SESSION['has_js_controller_data'] : 'not_set',
            'timestamp' => date('Y-m-d H:i:s'),
            'hasJsControllerData_result' => parent::hasJsControllerData() ? 'true' : 'false',
            'static_data_count' => count(parent::getJsControllerData() ?? [])
        ];
        
        return $debug;
    }
    
    /**
     * Generate simple main JavaScript content using controller data
     * 
     * @param array $vars Variables to inject into JavaScript
     * @return string
     */
    private function generateMainJs($vars) {
        $js = "/**\n";
        $js .= " * Main JavaScript file - from " . ($vars['controller_data']['source'] ?? 'Unknown Controller') . "\n";
        $js .= " * Data sent from: " . ($vars['controller_data']['from_page'] ?? '/') . "\n";
        $js .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $js .= " */\n\n";
        
        // Safe encoding untuk controller data dengan validasi
        $controllerDataJson = 'null';
        if (isset($vars['controller_data']) && $vars['controller_data'] !== null) {
            // Clean data sebelum encoding untuk menghindari masalah karakter khusus
            $cleanData = $this->cleanDataForJs($vars['controller_data']);
            $encoded = json_encode($cleanData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false && json_last_error() === JSON_ERROR_NONE) {
                $controllerDataJson = $encoded;
            } else {
                // Fallback jika JSON encoding gagal
                $errorMsg = json_last_error_msg();
                $controllerDataJson = '{"error": "JSON encoding failed", "message": "' . addslashes($errorMsg) . '"}';
            }
        }
        
        // Safe values untuk JavaScript dengan validasi ketat
        $safeUrl = addslashes($vars['app_url'] ?? '');
        $safeBaseDir = addslashes($vars['base_dir'] ?? '');
        $safeName = addslashes($vars['app_name'] ?? 'NexaUI Framework');
        $safeApiBase = addslashes($vars['api_base'] ?? '');



        
        // Pastikan CSRF token tidak kosong - generate jika perlu
        $csrfToken = $vars['csrf_token'] ?? '';
        if (empty($csrfToken)) {
            $csrfToken = bin2hex(random_bytes(32));
        }
        $safeCsrfToken = addslashes($csrfToken);
        $isLoggedIn = ($vars['is_logged_in'] === 'true') ? 'true' : 'false';
        
        // Handle userId dengan benar - pastikan null atau integer yang valid
        $userId = 'null';
        if (isset($vars['user_id']) && $vars['user_id'] !== null && $vars['user_id'] !== 'null' && $vars['user_id'] !== '') {
            // Pastikan userId adalah integer untuk JavaScript
            if (is_numeric($vars['user_id'])) {
                $userId = (int)$vars['user_id']; // Cast ke integer, tidak pakai tanda kutip
            } else {
                $userId = 'null'; // Jika bukan numerik, set ke null
            }
        }
  
        // Handle userSlug dengan benar - pastikan null atau string yang valid
        $userSlug = 'null';
        if (isset($vars['user_slug']) && $vars['user_slug'] !== null && $vars['user_slug'] !== 'null' && $vars['user_slug'] !== '') {
            $userSlug = "'" . addslashes($vars['user_slug']) . "'"; // Escape untuk JavaScript string
        }
        
        // Inject configuration with controller data
        $js .= "// Application Configuration with Controller Data\n";
        $js .= "window.NEXA = {\n";
        $js .= "    // Basic app config\n";
        $js .= "    url: '{$safeUrl}',\n";
        $js .= "    baseDir: '{$safeBaseDir}',\n";
        $js .= "    name: '{$safeName}',\n";
        $js .= "    isLoggedIn: {$isLoggedIn},\n";
        $js .= "    userId: {$userId},\n";
        $js .= "    userSlug: {$userSlug},\n";
        $js .= "    csrfToken: '{$safeCsrfToken}',\n";
        $js .= "    apiBase: '{$safeApiBase}',\n";
        $js .= "    \n";
        $js .= "    // Data from Controller via setJsController()\n";
        $js .= "    controllers: " . $controllerDataJson . ",\n";
        $js .= "    \n";
        
        // Safe encoding untuk debug info
        $debugInfoJson = 'null';
        if (isset($vars['debug_info']) && $vars['debug_info'] !== null) {
            $cleanDebugData = $this->cleanDataForJs($vars['debug_info']);
            $encoded = json_encode($cleanDebugData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false && json_last_error() === JSON_ERROR_NONE) {
                $debugInfoJson = $encoded;
            }
        }
        
        $js .= "    // Debug information (terpisah dari controllers)\n";
        $js .= "    _debug: " . $debugInfoJson . ",\n";
        $js .= "    \n";
        $js .= "    // Framework metadata\n";
        $js .= "    version: '1.0.0',\n";
        $js .= "    generated: '" . date('Y-m-d H:i:s') . "'\n";
        $js .= "};\n\n";
        
        // Add utility functions
        $js .= "// Utility Functions\n";
        $js .= "window.NEXA.utils = {\n";
        $js .= "    // TETAP SEPERTI ASLI - Get data yang dikirim dari controller\n";
        $js .= "    getControllerData: function() {\n";
        $js .= "        return NEXA.controllers.data || {};\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // TETAP SEPERTI ASLI - Get specific data field dari controller\n";
        $js .= "    getData: function(key, defaultValue) {\n";
        $js .= "        const data = this.getControllerData();\n";
        $js .= "        if (key === undefined) return data;\n";
        $js .= "        return data[key] !== undefined ? data[key] : (defaultValue || null);\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // TETAP SEPERTI ASLI - Check apakah dari HomeController\n";
        $js .= "    isFromHome: function() {\n";
        $js .= "        return NEXA.controllers.source === 'HomeController';\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // TETAP SEPERTI ASLI - Get info controller yang mengirim data\n";
        $js .= "    getSourceInfo: function() {\n";
        $js .= "        return {\n";
        $js .= "            controller: NEXA.controllers.source || 'Unknown',\n";
        $js .= "            page: NEXA.controllers.from_page || '/',\n";
        $js .= "            page_home: NEXA.controllers.page_home || '/',\n";
        $js .= "            packages: NEXA.controllers.packages || '/',\n";
        $js .= "            projectName: NEXA.controllers.projectName || '/',\n"; 
        $js .= "            projectAssets: NEXA.controllers.projectAssets || '/',\n"; 
        $js .= "            projectDrive: NEXA.controllers.projectDrive || '/',\n"; 
        $js .= "            page_index: NEXA.controllers.page_index || '/',\n";
        $js .= "            sent_at: NEXA.controllers.sent_at || 'Unknown'\n";
        $js .= "        };\n";
        $js .= "    },\n";
        $js .= "    \n";

        $js .= "    // NEW FUNCTIONS - Get callback data (Parameter 2)\n";
        $js .= "    getCallback: function(key, defaultValue) {\n";
        $js .= "        const callbackData = NEXA.controllers.callback || {};\n";
        $js .= "        if (key === undefined) return callbackData;\n";
        $js .= "        return callbackData[key] !== undefined ? callbackData[key] : (defaultValue || null);\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // ALIAS untuk backward compatibility\n";
        $js .= "    getGlobalData: function(key, defaultValue) {\n";
        $js .= "        return this.getCallback(key, defaultValue);\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // NEW FUNCTIONS - Get metadata\n";
        $js .= "    getMeta: function(key, defaultValue) {\n";
        $js .= "        const meta = NEXA.controllers.meta || {};\n";
        $js .= "        if (key === undefined) return meta;\n";
        $js .= "        return meta[key] !== undefined ? meta[key] : (defaultValue || null);\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // NEW FUNCTIONS - Check controller source\n";
        $js .= "    isFromController: function(controllerName) {\n";
        $js .= "        const source = NEXA.controllers.source || '';\n";
        $js .= "        return source.toLowerCase().includes(controllerName.toLowerCase());\n";
        $js .= "    },\n";
        $js .= "    \n";


        $js .= "    // Standard API request helper\n";
        $js .= "    makeRequest: function(url, options = {}) {\n";
        $js .= "        const defaultOptions = {\n";
        $js .= "            headers: {\n";
        $js .= "                'Content-Type': 'application/json',\n";
        $js .= "                'X-Requested-With': 'XMLHttpRequest'\n";
        $js .= "            }\n";
        $js .= "        };\n";
        $js .= "        \n";
        $js .= "        if (NEXA.csrfToken && NEXA.csrfToken !== 'null' && NEXA.csrfToken !== '') {\n";
        $js .= "            defaultOptions.headers['X-CSRF-Token'] = NEXA.csrfToken;\n";
        $js .= "        }\n";
        $js .= "        \n";
        $js .= "        return fetch(url, Object.assign(defaultOptions, options));\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // Realtime data polling\n";
        $js .= "    startRealtime: function(interval = 5000) {\n";
        $js .= "        if (NEXA._realtimeInterval) {\n";
        $js .= "            clearInterval(NEXA._realtimeInterval);\n";
        $js .= "        }\n";
        $js .= "        \n";
        $js .= "        NEXA._realtimeInterval = setInterval(() => {\n";
        $js .= "            fetch(NEXA.url + '/app/main.js?live=1')\n";
        $js .= "                .then(r => r.json())\n";
        $js .= "                .then(data => {\n";
        $js .= "                    const oldData = NEXA.controllers;\n";
        $js .= "                    NEXA.controllers = data;\n";
        $js .= "                    \n";
        $js .= "                    // Trigger custom event jika data berubah\n";
        $js .= "                    if (JSON.stringify(oldData) !== JSON.stringify(data)) {\n";
        $js .= "                        document.dispatchEvent(new CustomEvent('nexaDataUpdate', {\n";
        $js .= "                            detail: { oldData, newData: data }\n";
        $js .= "                        }));\n";
        $js .= "                    }\n";
        $js .= "                })\n";
        $js .= "                .catch(e => console.warn('Realtime data fetch failed:', e));\n";
        $js .= "        }, interval);\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // Stop realtime polling\n";
        $js .= "    stopRealtime: function() {\n";
        $js .= "        if (NEXA._realtimeInterval) {\n";
        $js .= "            clearInterval(NEXA._realtimeInterval);\n";
        $js .= "            NEXA._realtimeInterval = null;\n";
        $js .= "        }\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // Debug information\n";
        $js .= "    getDebugInfo: function() {\n";
        $js .= "        return NEXA._debug || null;\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // Show debug console\n";
        $js .= "    showDebug: function() {\n";
        $js .= "        const debug = this.getDebugInfo();\n";
        $js .= "        if (debug) {\n";
        $js .= "            console.group('NEXA Debug Information');\n";
        $js .= "            console.log('Session Status:', debug.session_status);\n";
        $js .= "            console.log('Session ID:', debug.session_id);\n";
        $js .= "            console.log('Session Keys:', debug.session_data_keys);\n";
        $js .= "            console.log('Has Controller Data:', debug.has_controller_data_flag);\n";
        $js .= "            console.log('Full Debug:', debug);\n";
        $js .= "            console.groupEnd();\n";
        $js .= "        } else {\n";
        $js .= "            console.log('No debug information available');\n";
        $js .= "        }\n";
        $js .= "    },\n";
        $js .= "    \n";
        $js .= "    // Get session information\n";
        $js .= "    getSessionInfo: function() {\n";
        $js .= "        const debug = this.getDebugInfo();\n";
        $js .= "        return debug ? {\n";
        $js .= "            session_id: debug.session_id,\n";
        $js .= "            session_status: debug.session_status,\n";
        $js .= "            data_keys: debug.session_data_keys\n";
        $js .= "        } : null;\n";
        $js .= "    }\n";
        $js .= "};\n\n";
        
        // Add ready event
  
        
        return $js;
    }

    /**
     * Fallback method untuk handle semua /app/ routes
     * 
     * @param string|null $params Additional parameters from URL
     * @return void
     */
    public function index($params = null) {
        // Set header untuk JavaScript
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=3600'); // Cache selama 1 jam
        
        // Handle different app routes
        if ($params === 'main.js' || empty($params)) {
            // Redirect to main() method
            $this->main();
            return;
        }
        
        // Handle other potential app files
        if (str_ends_with($params, '.js')) {
            $methodName = str_replace('.js', '', $params);
            
            // Check if specific method exists
            if (method_exists($this, $methodName)) {
                call_user_func([$this, $methodName]);
                return;
            }
        }
        
        // Default: return basic app configuration
        $this->generateBasicAppConfig();
    }
    
    /**
     * Generate basic app configuration for unknown routes
     * 
     * @return void
     */
    private function generateBasicAppConfig() {
        // Ambil konfigurasi dasar
        $appUrl = $this->normalizeUrl($this->getBaseUrl());
        $baseDir = $this->normalizeUrl($this->getBaseUrl());


        $appName = 'NexaUI Framework';
        
        // Generate minimal JavaScript config
        $js = "/**\n";
        $js .= " * Basic App Configuration\n";
        $js .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $js .= " */\n\n";
        
        $js .= "// Basic App Configuration\n";
        $js .= "window.NEXA = {\n";
        $js .= "    url: '{$appUrl}',\n";
        $js .= "    baseDir: '{$baseDir}',\n";
        $js .= "    name: '{$appName}',\n";
        $js .= "    version: '1.0.0',\n";
        $js .= "    isLoggedIn: " . (isset($_SESSION['user_id']) ? 'true' : 'false') . ",\n";
        $js .= "    message: 'App configuration loaded'\n";
        $js .= "};\n\n";
        
        $js .= "console.log('NEXA App Config:', window.NEXA);\n";
        
        echo $js;
        exit;
    }

    /**
     * Normalize URL: remove trailing slashes, fix double slashes, ensure HTTPS if page is HTTPS
     * 
     * @param string $url URL to normalize
     * @return string Normalized URL
     */
    private function normalizeUrl($url) {
        if (empty($url)) {
            return $url;
        }
        
        // Remove trailing slashes (except for root URLs like http://example.com/)
        $url = rtrim($url, '/');
        
        // If it's a root URL (ends with ://), add back one slash
        if (preg_match('/^https?:\/\/[^\/]+$/', $url)) {
            $url .= '/';
        }
        
        // Fix double slashes (except after protocol like http:// or https://)
        $url = preg_replace('/([^:]\/)\/+/', '$1', $url);
        
        // Ensure HTTPS if page is loaded over HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $url = preg_replace('/^http:\/\//i', 'https://', $url);
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $url = preg_replace('/^http:\/\//i', 'https://', $url);
        }
        
        return $url;
    }

    /**
     * Clean data for JavaScript to avoid syntax errors
     * 
     * @param mixed $data Data to clean
     * @return mixed Cleaned data
     */
    private function cleanDataForJs($data) {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = $this->cleanDataForJs($value);
            }
            return $cleaned;
        } elseif (is_string($data)) {
            // Remove potentially problematic characters
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            // Escape quotes and backslashes properly
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * Generate fallback JavaScript content for error handling
     * 
     * @param string $errorMessage Error message to include in the fallback JavaScript
     * @return string
     */
    private function generateFallbackJs($errorMessage) {
        $safeMesg = addslashes($errorMessage);
        
        $js = "/**\n";
        $js .= " * Fallback JavaScript content\n";
        $js .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $js .= " */\n\n";
        
        $js .= "// Fallback JavaScript content\n";
        $js .= "window.NEXA = {\n";
        $js .= "    error: true,\n";
        $js .= "    message: 'JavaScript generation failed',\n";
        $js .= "    details: '{$safeMesg}'\n";
        $js .= "};\n\n";
        
        $js .= "console.error('JavaScript generation error: {$safeMesg}');\n";
        
        return $js;
    }
} 

// // Mulai realtime updates (polling setiap 5 detik)
// NEXA.utils.startRealtime();

// // Custom interval (polling setiap 2 detik)
// NEXA.utils.startRealtime(2000);

// // Listen untuk data changes
// document.addEventListener('nexaDataUpdate', function(event) {
//     console.log('Data berubah!', event.detail.newData);
//     // Update UI di sini
// });

// // Stop realtime
// NEXA.utils.stopRealtime();