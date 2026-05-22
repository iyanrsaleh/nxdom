<?php
declare(strict_types=1);
namespace App\System;
use App\System\Dom\NexaDom;
use App\System\Nexa;
use App\System\NexaModel;
use App\System\Helpers\NexaRaw;
use App\System\Helpers\NexaMethod;
use App\System\Helpers\NexaMapping;
use App\System\Helpers\NexaSession;
use App\System\Helpers\NexaState;
use App\System\Helpers\NexaForm;
use App\System\Helpers\NexaValidation;
use App\System\Helpers\NexaRequest;
use App\System\Helpers\NexaAgent;
use App\System\Helpers\NexaDecode;
use App\System\Helpers\NexaAuth;
use App\System\Helpers\NexaStrings;
use App\System\Helpers\NexaDownload;
use App\System\Helpers\NexaSystem;
use App\System\Helpers\NexaMarkdown;
use App\System\Helpers\NexaArray;
use App\System\Helpers\NexaEncrypt;
use App\System\Helpers\NexaCrypto;
use App\System\Helpers\NexaLicense;
use App\System\Components\NexaJsonViewer;
use App\System\Storage\NexaIndexDB;
use App\System\Helpers\NexaFirebase;
use App\System\Storage\NexaJon;
use Exception;

/**
 * NexaController - Base Controller untuk sistem MVC
 * 
 * Features:
 * - Integrasi penuh dengan NexaDom
 * - Helper methods untuk template management
 * - Request handling utilities
 * - Response utilities
 * - Device-specific layout support
 * - Built-in NexaMapping support (Convention-based routing)
 * - NexaRequest integration (Advanced URL handling & analytics)

 * - Enhanced validation with NexaValidation
 * - Form building with NexaForm
 * - Session management with NexaSession
 * - NexaNode integration (Dynamic controller routing)
 * - NexaIndexDB integration (Advanced database operations)

 * 
 * NexaNode Integration Examples:
 * 
 * 1. Simple controller call:
 *    $this->nodeController('user'); // Calls UserController::index()
 * 
 * 2. Controller with specific method:
 *    $this->nodeController('user', ['method' => 'profile']); // Calls UserController::profile()
 * 
 * 3. Fluent interface:
 *    $this->callController('user')->method('profile')->param('id', 123)->execute();
 * 
 * 4. Multiple controllers:
 *    $this->nodeControllers(['user', 'admin', 'dashboard']);
 * 
 * 5. Custom namespace:
 *    $this->nodeController('api', [], 'App\\Api\\Controllers\\');
 * 
 * NexaIndexDB Integration Examples:
 * 
 * 1. Quick data operations:
 *    $id = $this->storeData(['name' => 'John', 'email' => 'john@example.com']);
 *    $user = $this->getData($id);
 *    $this->updateData(['id' => $id, 'name' => 'John Doe']);
 *    $this->deleteData($id);
 * 
 * 2. Database and store management:
 *    $db = $this->database('my_app', 2); // Custom database with version 2
 *    $store = $this->createStore('users', ['keyPath' => 'id', 'autoIncrement' => true]);
 *    $userStore = $this->getStore('users');
 * 
 * 3. Advanced queries and indexes:
 *    $this->createIndex('users', 'emailIndex', 'email', ['unique' => true]);
 *    $users = $this->queryByIndex('users', 'emailIndex', 'john@example.com');
 *    $allUsers = $this->getAllData('users');
 * 
 * 4. Transaction support:
 *    $result = $this->withTransaction(['users', 'orders'], function($tx) {
 *        $userStore = $tx->objectStore('users');
 *        $orderStore = $tx->objectStore('orders');
 *        $userId = $userStore->add(['name' => 'John']);
 *        $orderStore->add(['user_id' => $userId, 'total' => 100]);
 *        return $userId;
 *    });
 * 
 * 5. User-specific data:
 *    $this->storeUserData(['preference' => 'dark_theme']);
 *    $preference = $this->getUserData(1);
 *    $allUserData = $this->getAllUserData();
 * 
 * 6. Batch operations:
 *    $operations = [
 *        ['method' => 'add', 'data' => ['name' => 'John']],
 *        ['method' => 'add', 'data' => ['name' => 'Jane']],
 *        ['method' => 'delete', 'key' => 'old_record_id']
 *    ];
 *    $results = $this->batchOperations('users', $operations);

 */
abstract class NexaController
{
    use NexaValidation;
    protected ?NexaDom $template = null;
    protected array $data = [];
    protected string $layout = 'index.html';
    protected bool $autoRender = true;
    protected string $deviceType = 'theme'; // Default device type
    protected array $deviceLayouts = [];
    
    // Built-in NexaMapping support
    private ?NexaMapping $mappingHelper = null;
    private ?NexaFirebase $Firebase = null;
    

    
    // NexaForm instance (singleton per controller)
    protected ?NexaForm $form = null;

    /** @see __get — di-load saat pertama kali diakses (hemat init per request) */
    private ?NexaDownload $_lazyDownload = null;
    
    // NexaRequest instance (singleton per controller) - Lazy loaded for performance
    protected ?NexaRequest $request = null;

    private ?NexaAuth $_lazyAuth = null;
    private ?NexaStrings $_lazyStrings = null;
    private ?NexaMarkdown $_lazyMarkdown = null;
    private ?NexaArray $_lazyArray = null;
    private ?NexaEncrypt $_lazyEncrypt = null;
    private ?NexaCrypto $_lazyCrypto = null;
    
    // NexaAgent instance (singleton per controller) - Lazy loaded for performance
    protected ?NexaAgent $agent = null;

    
    // NexaNode integration - Dynamic Controller Routing (NO CACHING)
    private string $baseControllerNamespace = 'App\\Controllers\\';
    
    // NexaSession instance (singleton per controller) - Direct access property
    protected ?NexaSession $session = null;
    
    // User ID from session
    protected ?int $UserId = null;
    
    // Placeholder property untuk backward compatibility (tidak digunakan untuk cache)
    private array $modelReferences = [];
    
    // Placeholder property untuk backward compatibility (tidak digunakan untuk cache)
    private array $paramsReferences = [];
    
    // REMOVED: Template resolution cache - using direct file checking instead
    
    // Database instances - using singleton pattern instead of cache
    private array $databases = [];
  
    
    public function __construct(?NexaDom $template = null, array $deviceLayouts = [])
    {
        $this->template = $template;
        $this->deviceLayouts = !empty($deviceLayouts) ? $deviceLayouts : $this->getDefaultLayoutFiles();
        $this->init();
    }
    
    /**
     * Initialize controller - override di child class jika diperlukan
     */
    protected function init(): void
    {
        // Initialize session management
        NexaSession::init();
        // Initialize session property for direct access
        $this->session = NexaSession::getInstance();
        if ($this->template !== null) {
            // Setup default template variables (skip for API/no-template mode)
            $this->assignVar('controller_name', $this->getControllerName());
            $this->assignVar('action_name', $this->getActionName());
            $this->assignVar('timestamp', time());
            $this->assignVar('request_method', $this->getRequest()->getMethod());
            // Setup NexaRequest-powered template variables (lazy-loaded)
            $this->assignVar('base_url', $this->getBaseUrl());
            $this->assignVar('current_url', $this->getCurrentUrl());
            $this->assignVar('current_path', $this->getPath());
        }
        $this->UserId = $this->session->getUserId();
        // NexaMapping, NexaAuth, Strings, Download, Markdown, NexaArray, Encrypt, Crypto, Firebase:
        // dimuat saat pertama diakses (lihat mapping() / __get / getFirebase)
        // Note: NexaRequest will be lazy-loaded when first accessed for optimal performance
    }

     /**
     * Assign single variable ke template
     */
    public function nexaVar(string $name, $value, bool $append = false): self
    {
        if ($this->template !== null) {
            $this->template->assign_var($name, $value, $append);
        }
        $this->data[$name] = $value;
        return $this;
    }   
    /**
     * Assign single variable ke template
     */
    public function assignVar(string $name, $value, bool $append = false): self
    {
        if ($this->template !== null) {
            $this->template->assign_var($name, $value, $append);
        }
        $this->data[$name] = $value;
        return $this;
    }
       
    /**
     * Assign multiple variables ke template
     */
    public function nexaVars(array $vars, bool $append = false): self
    {
        if ($this->template !== null) {
            $this->template->assign_vars($vars, $append);
        }
        $this->data = array_merge($this->data, $vars);
        return $this;
    } 
    /**
     * Assign multiple variables ke template
     */
    public function assignVars(array $vars, bool $append = false): self
    {
        if ($this->template !== null) {
            $this->template->assign_vars($vars, $append);
        }
        $this->data = array_merge($this->data, $vars);
        return $this;
    }
        /**
     * Assign block variables (untuk loops)
     */
    protected function nexaBlock(string $blockName, array $data): self
    {
        if ($this->template !== null) {
            $this->template->assign_block_vars($blockName, $data);
        }
        return $this;
    }
    /**
     * Assign block variables (untuk loops)
     */
    protected function assignBlock(string $blockName, array $data): self
    {
        if (strpos($blockName, '.') !== false) {
            return $this->assignNestedBlock($blockName, $data);
        }
        $this->template->assign_block($blockName, $data);
        return $this;
    }
    
 

    /**
     * Assign multiple blocks sekaligus
     */
    protected function assignBlocks(array $blocks): self
    {
        $this->template->assign_blocks($blocks);
        return $this;
    }

    protected function slugify($text) {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '', $text);
        $text = trim($text, '');
        return $text;
    }

        /**
     * Generic method untuk memproses dynamic form data
     * Dapat digunakan oleh controller lain dengan konfigurasi yang berbeda
     * 
     * @param array $config Konfigurasi processing
     * @return \Illuminate\Http\JsonResponse
     */
    protected function dynamicForm(array $config = []) {
        try {
            // Default configuration
            $defaultConfig = [
                'table' => 'demo',
                'required_fields' => [],
                'field_mapping' => [],
                'field_add' => [],
                'success_message' => 'Berhasil menyimpan {count} data',
                'error_message' => 'Tidak ada data valid untuk disimpan',
                'add_timestamps' => true,
                'log_errors' => true
            ];
            
            // Merge dengan default config
            $config = array_merge($defaultConfig, $config);
            
            // Ambil data dari form input
            $data = $this->inputs();
            
            // Log data yang diterima untuk debugging (jika enabled)
            if ($config['log_errors']) {
                error_log("ProcessDynamicForm received data: " . json_encode($data));
            }
            
            // Validasi data yang diperlukan
            if (empty($data)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang diterima'
                ], 400);
            }
            
            // Proses data dinamis dengan format nama_counter
            $insertData = $this->parseInputData($data, $config);
            
            // Jika tidak ada data valid untuk disimpan
            if (empty($insertData)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tidak ada data valid untuk disimpan'
                ], 400);
            }
            
            // Validasi dan prepare data untuk database
            $validEntries = $this->validateAndPrepareEntries($insertData, $config);
            
            if (empty($validEntries)) {
                return $this->json([
                    'success' => false,
                    'message' => $config['error_message']
                ], 400);
            }
            
            // Simpan data ke database
            $result = $this->saveEntriesToDatabase($validEntries, $config);
            
            return $this->json($result, $result['success'] ? 200 : 500);
            
        } catch (Exception $e) {
            // Log error untuk debugging
            if ($config['log_errors'] ?? true) {
                error_log("ProcessDynamicForm Error: " . $e->getMessage());
                error_log("ProcessDynamicForm Error Stack: " . $e->getTraceAsString());
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse input data dengan format nama_counter menjadi grouped data
     */
    private function parseInputData(array $data, array $config): array {
        $insertData = [];
        
        foreach ($data as $key => $value) {
            if (!empty(trim($value))) {
                // Parse key untuk mendapatkan nama field dan counter
                $parts = explode('_', $key);
                if (count($parts) >= 2) {
                    $fieldName = implode('_', array_slice($parts, 0, -1));
                    $counter = end($parts);
                    
                    // Cari apakah sudah ada entry untuk counter ini
                    $existingIndex = null;
                    foreach ($insertData as $index => $item) {
                        if ($item['counter'] == $counter) {
                            $existingIndex = $index;
                            break;
                        }
                    }
                    
                    if ($existingIndex !== null) {
                        // Update existing entry
                        $insertData[$existingIndex][$fieldName] = trim($value);
                    } else {
                        // Create new entry
                        $newEntry = [
                            'counter' => $counter,
                            $fieldName => trim($value)
                        ];
                        
                        // Add timestamps if enabled
                        if ($config['add_timestamps']) {
                            $newEntry['created_at'] = date('Y-m-d H:i:s');
                            $newEntry['updated_at'] = date('Y-m-d H:i:s');
                        }
                        
                        $insertData[] = $newEntry;
                    }
                }
            }
        }
        
        return $insertData;
    }

    /**
     * Validasi entries dan prepare untuk database insertion
     */
    private function validateAndPrepareEntries(array $insertData, array $config): array {
        $validEntries = [];
        $requiredFields = $config['required_fields'];
        $fieldMapping = $config['field_mapping'];
        $fieldAdd = $config['field_add'] ?? [];
        
        foreach ($insertData as $entry) {
            $isValid = true;
            $missingFields = [];
            
            // Validasi required fields
            foreach ($requiredFields as $field) {
                if (!isset($entry[$field]) || empty(trim($entry[$field]))) {
                    $isValid = false;
                    $missingFields[] = $field;
                }
            }
            
            if ($isValid) {
                // Prepare data untuk database insertion dengan field mapping
                $dbEntry = [];
                
                foreach ($entry as $key => $value) {
                    if ($key === 'counter') continue; // Skip counter field
                    
                    // Apply field mapping jika ada
                    $dbFieldName = $fieldMapping[$key] ?? $key;
                    $dbEntry[$dbFieldName] = is_string($value) ? trim($value) : $value;
                }
                
                // Tambahkan field statik dari field_add
                if (!empty($fieldAdd)) {
                    foreach ($fieldAdd as $staticField => $staticValue) {
                        $dbEntry[$staticField] = $staticValue;
                    }
                }
                
                $validEntries[] = $dbEntry;
            } else {
                if ($config['log_errors']) {
                    error_log("Invalid entry for counter {$entry['counter']}: missing fields " . implode(', ', $missingFields));
                }
            }
        }
        
        return $validEntries;
    }

    /**
     * Simpan entries ke database
     */
    private function saveEntriesToDatabase(array $validEntries, array $config): array {
        $table = $config['table'];
        $savedCount = 0;
        $errors = [];
        
        foreach ($validEntries as $item) {
            try {
                $result = $this->Storage($table)->insert($item);
                if ($result) {
                    $savedCount++;
                }
            } catch (Exception $e) {
                $errors[] = "Gagal menyimpan data: " . $e->getMessage();
                if ($config['log_errors']) {
                    error_log("Database insert error: " . $e->getMessage());
                }
            }
        }
        
        // Response berdasarkan hasil
        if ($savedCount > 0) {
            $message = str_replace('{count}', (string)$savedCount, $config['success_message']);
            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'saved_count' => $savedCount,
                    'total_items' => count($validEntries),
                    'errors' => $errors
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan data',
                'errors' => $errors
            ];
        }
    }


   protected function JsEventload()
      {
        $data = $this->inputs();
        try {
            // Get the HTML content using NexaSystem
            $nexaSystem = new \App\System\Helpers\NexaSystem();
            
            // Try to get file content, catch exception if file not found
            try {
                $content = $nexaSystem->getHtmlFileContent($data['file'], $data['template']);
            } catch (\Exception $fileException) {
                // File tidak ditemukan - return success dengan error message content
                // Jangan return 400 error untuk menghindari console error
                $errorContent = '
                    <div class="error-message" style="padding: 2rem; text-align: center;">
                        <h2>Halaman Tidak Ditemukan</h2>
                        <p>File template tidak ditemukan: <code>' . htmlspecialchars($data['file']) . '</code></p>
                        <p><a href="/docs" style="color: #007bff;">← Kembali ke Dokumentasi</a></p>
                    </div>
                ';
                
                $this->json([
                    'success' => true,
                    'content' => $errorContent,
                    'file' => $data['file'],
                    'template' => $data['template'],
                    'file_not_found' => true
                ]);
                return;
            }
            
            // Process template with variables
            $template = new NexaDom();
            
            // ✅ FIX: Smart processing to avoid duplicate data
            $processedBlocks = [];
            $cleanVariables = [];
            
            // Step 1: Process explicit block data first
            if (isset($data['blocks']) && is_array($data['blocks'])) {
                foreach ($data['blocks'] as $blockName => $blockData) {
                    if (is_array($blockData)) {
                        foreach ($blockData as $row) {
                            $template->assign_block_vars($blockName, $row);
                        }
                        $processedBlocks[] = $blockName;
                    }
                }
            }
            
            // Step 2: Handle single block data (for backward compatibility)
            if (isset($data['block_data']) && is_array($data['block_data']) && isset($data['block_name'])) {
                foreach ($data['block_data'] as $row) {
                    $template->assign_block_vars($data['block_name'], $row);
                }
                $processedBlocks[] = $data['block_name'];
            }
            
            // Step 3: Process variables and auto-detect blocks (avoid duplicates)
            if (isset($data['variables']) && is_array($data['variables'])) {
                foreach ($data['variables'] as $key => $value) {
                    // Skip if already processed as explicit block
                    if (in_array($key, $processedBlocks)) {
                        continue;
                    }
                    
                    // Check if this is array data that should be treated as a block
                    if (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0])) {
                        // This looks like block data, assign it as such
                        foreach ($value as $row) {
                            $template->assign_block_vars($key, $row);
                        }
                        $processedBlocks[] = $key;
                    } else {
                        // Regular variable, keep for assign_vars
                        $cleanVariables[$key] = $value;
                    }
                }
                
                // Assign only non-block variables
                if (!empty($cleanVariables)) {
                    $template->assign_vars($cleanVariables);
                }
            }
            
            $result = $template->pparse($content);
            
            // ✅ NEXAUI INTEGRATION - Transform UI components like Modal
            $result = \App\System\Components\NexaUI::transform($result);
            
            // ✅ AUTO-OPTIMIZE MODAL CACHE - Prevent memory issues with multiple modals
            \App\System\Components\NexaModal::autoOptimize();
            
            // Return JSON response with the processed HTML content
            $this->json([
                'success' => true,
                'content' => $result,
                'file' => $data['file'],
                'template' => $data['template'],
                'has_script_tags' => strpos($result, '<script') !== false,
                'debug_info' => [
                    'total_blocks_processed' => count($processedBlocks),
                    'blocks_processed' => $processedBlocks,
                    'clean_variables_count' => count($cleanVariables),
                    'clean_variables' => array_keys($cleanVariables)
                ]
            ]);
            
        } catch (\Exception $e) {
            // Return error response as JSON
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $data['file'] ?? '',
                'template' => $data['template'] ?? '',
                'debug_data' => [
                    'has_variables' => isset($data['variables']),
                    'has_blocks' => isset($data['blocks']),
                    'variables_type' => isset($data['variables']) ? gettype($data['variables']) : 'not_set',
                    'input_data_keys' => array_keys($data ?? [])
                ]
            ], 400);
        }

      }

   protected function JsMarkdownload()
      {
        $data = [];
        try {
            $data = $this->inputs();
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Failed to get input data: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 400);
            return;
        }
        
        try {
            $markdownContent = '';
            $markdownFile = '';
            $template = '';
            $processedBlocks = [];
            $cleanVariables = [];
            
            // Get markdown content - can be from file or direct text
            if (isset($data['file']) && !empty($data['file'])) {
                // Load markdown file directly (not using getHtmlFileContent which only loads .html files)
                try {
                    $template = $data['template'] ?? '';
                    
                    // Use NexaSystem to get correct template directory path using reflection
                    $nexaSystem = new \App\System\Helpers\NexaSystem();
                    $reflection = new \ReflectionClass($nexaSystem);
                    $templateDirProperty = $reflection->getProperty('templateDir');
                    $templateDirProperty->setAccessible(true);
                    $templateDir = $templateDirProperty->getValue($nexaSystem);
                    
                    // Build full path for markdown file
                    $subfolder = !empty($template) ? trim($template, '/\\') . DIRECTORY_SEPARATOR : '';
                    $filename = $data['file'];
                    
                    // Ensure .md extension if not present
                    if (!str_ends_with(strtolower($filename), '.md')) {
                        $filename .= '.md';
                    }
                    
                    $fullPath = $templateDir . $subfolder . $filename;
                    
                    // Normalize path separators
                    $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
                    
                    // Check if file exists
                    if (!file_exists($fullPath)) {
                        // Try without .md extension (in case filename already includes it)
                        $altPath = $templateDir . $subfolder . $data['file'];
                        $altPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $altPath);
                        if (file_exists($altPath)) {
                            $fullPath = $altPath;
                        } else {
                            throw new \Exception('Markdown file not found: ' . $fullPath . ' (also tried: ' . $altPath . ')');
                        }
                    }
                    
                    // Read markdown file content
                    $markdownContent = file_get_contents($fullPath);
                    if ($markdownContent === false || empty($markdownContent)) {
                        throw new \Exception('File content is empty or could not be read: ' . $fullPath);
                    }
                    
                    $markdownFile = $data['file'];
                } catch (\Exception $e) {
                    throw new \Exception('Failed to load markdown file: ' . $e->getMessage());
                }
            } elseif (isset($data['content']) && !empty($data['content'])) {
                // Direct markdown text
                $markdownContent = $data['content'];
                $template = $data['template'] ?? '';
            } else {
                throw new \Exception('Either "file" or "content" parameter is required');
            }
            
            // Process template variables in markdown (simple replacement, not full template processing)
            // This preserves markdown syntax while allowing variable substitution
            if (!empty($markdownContent) && isset($data['variables']) && is_array($data['variables'])) {
                // Simple variable replacement in markdown: {variable} -> value
                foreach ($data['variables'] as $key => $value) {
                    // Skip array values (blocks should be processed after HTML conversion)
                    if (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0])) {
                        // This is block data, skip for now (will be processed after HTML conversion)
                        continue;
                    }
                    
                    // Replace simple variables in markdown
                    $markdownContent = str_replace('{' . $key . '}', (string)$value, $markdownContent);
                }
            }
            
            // Convert markdown to HTML using NexaMarkdown
            try {
                $nexaMarkdown = new NexaMarkdown();
                $htmlContent = $nexaMarkdown->text($markdownContent);
            } catch (\Exception $e) {
                throw new \Exception('Failed to convert markdown: ' . $e->getMessage());
            }
            
            // Process template blocks and complex variables AFTER HTML conversion
            // This allows full template processing on HTML content
            if (!empty($htmlContent) && (isset($data['blocks']) || isset($data['block_data']) || 
                (isset($data['variables']) && is_array($data['variables'])))) {
                $templateProcessor = new NexaDom();
                
                // Step 1: Process explicit block data
                if (isset($data['blocks']) && is_array($data['blocks'])) {
                    foreach ($data['blocks'] as $blockName => $blockData) {
                        if (is_array($blockData)) {
                            foreach ($blockData as $row) {
                                $templateProcessor->assign_block_vars($blockName, $row);
                            }
                            $processedBlocks[] = $blockName;
                        }
                    }
                }
                
                // Step 2: Handle single block data (for backward compatibility)
                if (isset($data['block_data']) && is_array($data['block_data']) && isset($data['block_name'])) {
                    foreach ($data['block_data'] as $row) {
                        $templateProcessor->assign_block_vars($data['block_name'], $row);
                    }
                    $processedBlocks[] = $data['block_name'];
                }
                
                // Step 3: Process array variables as blocks
                if (isset($data['variables']) && is_array($data['variables'])) {
                    foreach ($data['variables'] as $key => $value) {
                        // Skip if already processed as explicit block
                        if (in_array($key, $processedBlocks)) {
                            continue;
                        }
                        
                        // Check if this is array data that should be treated as a block
                        if (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0])) {
                            // This looks like block data, assign it as such
                            foreach ($value as $row) {
                                $templateProcessor->assign_block_vars($key, $row);
                            }
                            $processedBlocks[] = $key;
                        } else {
                            // Regular variable, keep for assign_vars
                            $cleanVariables[$key] = $value;
                        }
                    }
                    
                    // Assign only non-block variables
                    if (!empty($cleanVariables)) {
                        $templateProcessor->assign_vars($cleanVariables);
                    }
                }
                
                // Process HTML content with template engine
                $htmlContent = $templateProcessor->pparse($htmlContent);
            }
            
            // ✅ NEXAUI INTEGRATION - Transform UI components like Modal
            try {
                $htmlContent = \App\System\Components\NexaUI::transform($htmlContent);
            } catch (\Exception $e) {
                // Continue even if transform fails
                error_log('NexaUI transform error: ' . $e->getMessage());
            }
            
            // ✅ AUTO-OPTIMIZE MODAL CACHE - Prevent memory issues with multiple modals
            try {
                \App\System\Components\NexaModal::autoOptimize();
            } catch (\Exception $e) {
                // Continue even if optimize fails
                error_log('NexaModal autoOptimize error: ' . $e->getMessage());
            }
            
            // Return JSON response with the processed HTML content
            $this->json([
                'success' => true,
                'content' => $htmlContent,
                'file' => $markdownFile,
                'template' => $template,
                'has_script_tags' => strpos($htmlContent, '<script') !== false,
                'markdown_length' => strlen($markdownContent),
                'html_length' => strlen($htmlContent),
                'debug_info' => [
                    'source' => isset($data['file']) ? 'file' : 'content',
                    'total_blocks_processed' => count($processedBlocks),
                    'blocks_processed' => $processedBlocks,
                    'clean_variables_count' => count($cleanVariables),
                    'clean_variables' => array_keys($cleanVariables)
                ]
            ]);
            
        } catch (\Throwable $e) {
            // Return error response as JSON - catch both Exception and Error
            $errorData = [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => isset($data['file']) ? $data['file'] : '',
                'template' => isset($data['template']) ? $data['template'] : '',
            ];
            
            // Only include trace in development
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $errorData['trace'] = $e->getTraceAsString();
                $errorData['file_error'] = $e->getFile();
                $errorData['line_error'] = $e->getLine();
            }
            
            $errorData['debug_data'] = [
                'has_file' => isset($data['file']),
                'has_content' => isset($data['content']),
                'has_variables' => isset($data['variables']),
                'has_blocks' => isset($data['blocks']),
                'variables_type' => isset($data['variables']) ? gettype($data['variables']) : 'not_set',
                'input_data_keys' => is_array($data) ? array_keys($data) : []
            ];
            
            $this->json($errorData, 400);
        }

      }

   protected function navMode($page='theme')
      {
       // Redirect mobile and tablet users to base URL immediately
           $device = $this->getDevice();
           if ($device['is_mobile'] || $device['is_tablet']) {
             return [
                    "main" => 'menu-main-primary-container',
                    "sub" => 'menu-item-has-children',
                    "ul" => 'menu',
                    "dropdownExtra" => 'sub-menu',
                    "listPosition" => 'before'
                ];
             //$this->setDeviceType($device['type']);
           } else {
             return [
                    "main" => 'main-menu__nav',      // Default
                    "sub" => 'main-menu__nav_sub',   // Default
                ];
           }
   }
   protected function divert($page='theme')
      {
           $device = $this->getDevice();
           if ($device['is_mobile'] || $device['is_tablet']) {
             // Mobile/tablet: gunakan layout penuh (header, content, footer) dari folder device
             $deviceLayout = $device['type']; // 'mobile' atau 'tablet'
             if (isset($this->deviceLayouts[$deviceLayout])) {
                 $this->setDeviceType($deviceLayout);
             } else {
                 $this->setDeviceType('mobile'); // Fallback jika tablet layout tidak ada
             }
           } else {
             $this->setDeviceType($page);
           }
   }
    /**
     * Assign file-specific variable
     */
    protected function assignFileVar(string $fileName, string $varName, $value): self
    {
        $this->template->assign_file_var($fileName, $varName, $value);
        return $this;
    }
    
    /**
     * Assign nested data structure
     */
    protected function assignNestedData(string $blockName, array $data): self
    {
        $this->template->assign_nested_data($blockName, $data);
        return $this;
    }
    
    /**
     * Get NexaSystem instance for HTML file management
     * @return NexaSystem
     */
    protected function fileSystem($file,$template='theme'): NexaSystem
    {
        $nexaSystem = new NexaSystem();
        $content = $nexaSystem->getHtmlFileContent($file, $template);
        echo $content;
        return $nexaSystem;
    }
    
    /**
     * Get NexaSystem instance for HTML file and folder management
     * @return NexaSystem
     */
    protected function getNexaSystem(): NexaSystem
    {
        return new NexaSystem();
    }

    /**
     * Get NexaMarkdown instance for markdown parsing
     * @return NexaMarkdown
     */
    protected function getMarkdown(): NexaMarkdown
    {
        return $this->Markdown;
    }
    
    /**
     * Convert markdown text to HTML
     * @param string $text The markdown text
     * @return string The HTML output
     */
    protected function markdown(string $text): string
    {
        return $this->getMarkdown()->text($text);
    }

    

    /**
     * Set global blog section data
     * Data ini dapat diakses di dalam dan di luar NEXA blocks
     * Menggunakan prefix 'NX_BLOG' untuk akses global
     */
    protected function sectionBlog(array $blogData): self
    {
        // Store data as global variables with NX_BLOG prefix for outside NEXA blocks
        foreach ($blogData as $sectionName => $sectionData) {
            // Set as global variables for outside NEXA blocks
            $this->assignVars([
                "nx_blog_{$sectionName}" => $sectionData
            ]);
            
            // Set as block data for inside NEXA blocks
            $this->assignBlock("NX_BLOG_{$sectionName}", $sectionData);
        }
        
        // Store complete data for debugging
        $this->assignVars([
            'nx_blog_data' => json_encode($blogData, JSON_PRETTY_PRINT)
        ]);
        
        // PENTING: Simpan data NX_BLOG sebagai global template variables
        // agar dapat diakses di dalam konteks NEXA blocks
        foreach ($blogData as $sectionName => $sectionData) {
            foreach ($sectionData as $index => $item) {
                // Assign individual items as global variables
                foreach ($item as $key => $value) {
                    $this->template->assign_var("nx_blog_{$sectionName}_{$index}_{$key}", $value);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Set layout template
     */
    protected function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }
    
    /**
     * Disable auto render
     */
    protected function disableAutoRender(): self
    {
        $this->autoRender = false;
        return $this;
    }
    
    /**
     * Render template dengan device-specific layout
     * Auto-append .html extension jika tidak ada
     */
    public function render(string $template = '', array $data = [], bool $return = false): string
    {
        if (!empty($data)) {
            $this->assignVars($data);
        }
        
        if (empty($template)) {
            $template = $this->getDefaultTemplate();
        }
        
        // ✅ Auto-append .html extension jika tidak ada
        if (!empty($template) && !str_contains($template, '.')) {
            $template .= '.html';
        }
        
        try {
            // Set cookie agar /assets/ request bisa resolve ke template aktif
            if (!headers_sent()) {
                setcookie('nexa_template', $this->deviceType, [
                    'expires' => time() + 3600,
                    'path' => '/',
                    'samesite' => 'Lax',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                ]);
            }

            // Get device-specific layouts
            $layouts = $this->deviceLayouts[$this->deviceType];
            $output = '';
            
            // Resolve device-specific template path
            $deviceSpecificTemplate = $this->resolveDeviceTemplate($template);
            
            // Render header jika ada
            if (!empty($layouts['header'])) {
                $this->template->add_file($layouts['header']);
                $output .= $this->template->pparse_file($layouts['header'], false, true, false);
            }
            
            // Render content template (device-specific atau fallback)
            $this->template->add_file($deviceSpecificTemplate);
            $output .= $this->template->pparse_file($deviceSpecificTemplate, false, true, false);
            
            // Render footer jika ada
            if (!empty($layouts['footer'])) {
                $this->template->add_file($layouts['footer']);
                $output .= $this->template->pparse_file($layouts['footer'], false, true, false);
            }
            
            if (($_ENV['NEXA_SKIP_HTML_TRANSFORMS'] ?? '') !== 'true') {
                // ✅ NEXAUI INTEGRATION - Transform UI components like Modal
                $output = \App\System\Components\NexaUI::transform($output);
                // ✅ AUTO-OPTIMIZE MODAL CACHE - Prevent memory issues with multiple modals
                \App\System\Components\NexaModal::autoOptimize();
            }
            
            if ($return) {
                return $output;
            }
            
            // Send output immediately and flush buffers
            echo $output;
            
            // Force flush all output buffers to ensure content is sent
            while (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            
            return $output;
        } catch (Exception $e) {
            throw new Exception("Template rendering failed: " . $e->getMessage());
        }
    }
    

    

    
    /**
     * Redirect ke URL lain
     */
    /**
     * Redirect to URL with smart NexaRequest integration
     * Automatically detects and builds proper URLs using NexaRequest
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        // Smart URL processing: if it's a relative path, use NexaRequest to build full URL
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '/')) {
            // Relative path - use NexaRequest to build proper URL
            $url = $this->getRequest()->makeUrl($url);
        }
        
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
    
    /**
     * Add delay before next operation
     * Useful for showing messages before redirect or other operations
     * 
     * @param int $seconds Delay in seconds
     * @return self
     * 
     * Usage:
     * $this->delay(3)->redirect($url);
     * $this->delay(2); // then some other operation
     */
    protected function delay(int $seconds): self
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
        return $this;
    }
    

    
    /**
     * Get request parameter
     */
    protected function getParam(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }
    
    /**
     * Get POST data
     */
    protected function getPost(string $key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get GET data
     */
    protected function getGet(string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Check if request is POST
     */
    protected function isPost(): bool
    {
        return $this->getRequest()->isMethod('POST');
    }
    
    /**
     * Check if request is GET
     */
    protected function isGet(): bool
    {
        return $this->getRequest()->isMethod('GET');
    }
    
    /**
     * Check if request is AJAX
     */
    protected function isAjax(): bool
    {
        return $this->getRequest()->isAjax();
    }
    
    /**
     * Set flash message
     */
    protected function setFlash(string $type, string $message): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['flash'][$type] = $message;
        return $this;
    }
    
    /**
     * Get flash messages
     */
    protected function getFlash(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    /**
     * Assign flash messages ke template
     */
    protected function assignFlash(): self
    {
        $flash = $this->getFlash();
        if (!empty($flash)) {
            $this->assignVar('flash_messages', $flash);
        }
        return $this;
    }
    
    /**
     * Get session instance
     */
    protected function getSession(): NexaSession
    {
        return NexaSession::getInstance();
    }
    
    /**
     * Set session value
     */
    protected function setSession(string $key, $value): self
    {
        $this->getSession()->set($key, $value);
        return $this;
    }
    
    /**
     * Get session value
     */
    protected function getSessionValue(string $key, $default = null)
    {
        return $this->getSession()->get($key, $default);
    }
    
    /**
     * Check if session has key
     */
    protected function hasSession(string $key): bool
    {
        return $this->getSession()->has($key);
    }
    
    /**
     * Remove session key
     */
    protected function removeSession(string $key): self
    {
        $this->getSession()->remove($key);
        return $this;
    }
    
    /**
     * Check if user is logged in
     */
    protected function isLoggedIn(): bool
    {
        return $this->getSession()->isLoggedIn();
    }
     /**
     * Get page item data from session
     * @param string|null $field Optional specific field to get
     * @return array|string|null Full array if no field specified, field value if field specified
     * 
     * Usage:
     * $allData = $this->getPageItem();           // Returns full array
     * $url = $this->getPageItem('url');          // Returns just the URL
     * $current = $this->getPageItem('current');  // Returns current page number
     * $total = $this->getPageItem('total');      // Returns total pages
     */
    protected function getPageItem(?string $field = null)
    {
        $pageData = $this->getSession()->getPageItem();
        
        // If no field specified, return full array
        if ($field === null) {
            return $pageData;
        }
        
        // If field specified, return that field value or default
        if (is_array($pageData) && isset($pageData[$field])) {
            return $pageData[$field];
        }
        
        // Return appropriate default based on field type
        return match($field) {
            'current', 'total' => 1,
            'timestamp' => time(),
            default => ''
        };
    }
    /**
     * Get current user data
     */
    protected function getUser(): ?array
    {
        return $this->getSession()->getUser();
    }
    
    /**
     * Set user data (login)
     */
    protected function setUser(array $userData): self
    {
        $this->getSession()->setUser($userData);
        return $this;
    }
    
    /**
     * Logout user
     */
    protected function setLogout(): self
    {
        $this->getSession()->logout();
        return $this;
    }
    
    // ========================================================================
    // NEXASTATE INTEGRATION - React-like useState for Session State
    // ========================================================================
    
    /**
     * React-like useState for session state
     * Uses SAME session instance as NexaController
     * Returns [currentValue, setterFunction] array like React useState
     * 
     * @param string $key Session state key
     * @param mixed $defaultValue Default value if not set
     * @return array [currentValue, setterFunction]
     * 
     * Usage:
     * [$name, $setName] = $this->useState('user_name', '');
     * echo $name; // Get current value
     * $setName('John Doe'); // Set new value
     */
    protected function useState(string $key, mixed $defaultValue = null): array
    {
        // Use NexaController's session instance directly (compatible with getSessionValue/setSession)
        $currentValue = $this->getSession()->get($key, $defaultValue);
        
        $setter = function(mixed $newValue) use ($key) {
            $this->getSession()->set($key, $newValue);
        };
        
        return [$currentValue, $setter];
    }
    
    /**
     * Counter state helper
     * Returns [currentValue, increment, decrement, reset] functions
     * 
     * @param string $key Session state key
     * @param int $initialValue Initial counter value
     * @return array [currentValue, increment, decrement, reset]
     * 
     * Usage:
     * [$counter, $increment, $decrement, $reset] = $this->useCounter('page_counter', 0);
     * echo $counter; // Current value
     * $increment(); // Increment by 1
     * $increment(5); // Increment by 5
     * $decrement(2); // Decrement by 2
     * $reset(); // Reset to initial value
     */
    protected function useCounter(string $key, int $initialValue = 0): array
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
     * Returns [currentValue, toggle, setTrue, setFalse] functions
     * 
     * @param string $key Session state key
     * @param bool $initialValue Initial boolean value
     * @return array [currentValue, toggle, setTrue, setFalse]
     * 
     * Usage:
     * [$isOpen, $toggle, $setTrue, $setFalse] = $this->useToggle('sidebar_open', false);
     * if ($isOpen) echo "Sidebar is open";
     * $toggle(); // Toggle state
     * $setTrue(); // Set to true
     * $setFalse(); // Set to false
     */
    protected function useToggle(string $key, bool $initialValue = false): array
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
     * Returns [currentValue, append, remove, clear, reset] functions
     * 
     * @param string $key Session state key
     * @param array $initialValue Initial array value
     * @return array [currentValue, append, remove, clear, reset]
     * 
     * Usage:
     * [$items, $addItem, $removeItem, $clearItems, $resetItems] = $this->useArray('cart_items', []);
     * $addItem(['id' => 1, 'name' => 'Product A']);
     * $removeItem(['id' => 1, 'name' => 'Product A']);
     * $clearItems(); // Empty array
     * $resetItems(); // Reset to initial value
     */
    protected function useArray(string $key, array $initialValue = []): array
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
     * Create NexaState instance for advanced usage
     * 
     * @param string $key Session state key
     * @param mixed $defaultValue Default value
     * @return NexaState NexaState instance
     * 
     * Usage:
     * $userState = $this->createState('user_profile', []);
     * $currentData = $userState->getValue();
     * $userState->setValue($newData);
     * $userState->clear();
     * $userState->reset();
     */
    protected function createState(string $key, mixed $defaultValue = null): NexaState
    {
        return new NexaState($key, $defaultValue);
    }
    
    /**
     * Get state value directly (shorthand for reading state)
     * Uses SAME session instance as NexaController
     * 
     * @param string $key Session state key
     * @param mixed $defaultValue Default value if not set
     * @return mixed Current state value
     * 
     * Usage:
     * $username = $this->getState('username', 'guest');
     */
    protected function getState(string $key, mixed $defaultValue = null): mixed
    {
        // Use NexaController's session instance directly (same as getSessionValue)
        return $this->getSession()->get($key, $defaultValue);
    }
    
    /**
     * Set state value directly (shorthand for writing state)
     * Uses SAME session instance as NexaController
     * 
     * @param string $key Session state key
     * @param mixed $value Value to set
     * @return self
     * 
     * Usage:
     * $this->setState('username', 'john_doe');
     */
    protected function setState(string $key, mixed $value): self
    {
        // Prevent storing model instances with PDO connections in session
        if (is_object($value) && method_exists($value, 'table') && property_exists($value, 'db')) {
            throw new \InvalidArgumentException("Cannot store model instances in session state. Model instances contain PDO connections which cannot be serialized.");
        }
        
        // Use NexaController's session instance directly (same as setSession)
        $this->getSession()->set($key, $value);
        return $this;
    }
    
    /**
     * Clear state value (remove from session)
     * Uses SAME session instance as NexaController
     * 
     * @param string $key Session state key to clear
     * @return self
     * 
     * Usage:
     * $this->clearState('temporary_data');
     */
    protected function clearState(string $key): self
    {
        // Use NexaController's session instance directly (same as removeSession)
        $this->getSession()->remove($key);
        return $this;
    }
    
    /**
     * Check if state has value
     * Uses SAME session instance as NexaController
     * 
     * @param string $key Session state key
     * @return bool True if state has value
     * 
     * Usage:
     * if ($this->hasState('user_preferences')) {
     *     // State exists and has value
     * }
     */
    protected function hasState(string $key): bool
    {
        // Use NexaController's session instance directly (same as hasSession)
        return $this->getSession()->has($key);
    }
    
    /**
     * Assign state values to template automatically
     * Useful for making state data available in templates
     * 
     * @param array $stateKeys Array of state keys to assign
     * @param string $prefix Template variable prefix
     * @return self
     * 
     * Usage:
     * $this->assignStates(['username', 'cart_items', 'is_logged_in']);
     * // Creates template variables: state_username, state_cart_items, state_is_logged_in
     * 
     * $this->assignStates(['username', 'cart_items'], 'user_');
     * // Creates template variables: user_username, user_cart_items
     */
    protected function assignStates(array $stateKeys, string $prefix = 'state_'): self
    {
        $stateData = [];
        
        foreach ($stateKeys as $key) {
            $value = $this->getState($key);
            $stateData[$prefix . $key] = $value;
        }
        
        $this->assignVars($stateData);
        return $this;
    }
    
    /**
     * Enhanced useState with template auto-assignment
     * Returns state value and setter, plus automatically assigns to template
     * 
     * @param string $key Session state key
     * @param mixed $defaultValue Default value
     * @param string|null $templateVar Template variable name (null = auto-generate)
     * @return array [currentValue, setterFunction]
     * 
     * Usage:
     * [$name, $setName] = $this->useStateWithTemplate('user_name', '', 'current_user_name');
     * // Creates template variable 'current_user_name' with current value
     */
    protected function useStateWithTemplate(string $key, mixed $defaultValue = null, string $templateVar = null): array
    {
        [$value, $setter] = $this->useState($key, $defaultValue);
        
        // Auto-assign to template
        $varName = $templateVar ?? ('state_' . $key);
        $this->assignVar($varName, $value);
        
        return [$value, $setter];
    }
    
    /**
     * Bulk state operations - set multiple states at once
     * 
     * @param array $states Array of key => value pairs
     * @return self
     * 
     * Usage:
     * $this->setStates([
     *     'username' => 'john_doe',
     *     'email' => 'john@example.com',
     *     'last_login' => time()
     * ]);
     */
    protected function setStates(array $states): self
    {
        foreach ($states as $key => $value) {
            $this->setState($key, $value);
        }
        return $this;
    }
    
    /**
     * Bulk state retrieval - get multiple states at once
     * 
     * @param array $keys Array of state keys to retrieve
     * @param mixed $defaultValue Default value for missing keys
     * @return array Array of key => value pairs
     * 
     * Usage:
     * $userData = $this->getStates(['username', 'email', 'last_login'], '');
     * // Returns: ['username' => 'john_doe', 'email' => 'john@example.com', 'last_login' => 1234567890]
     */
    protected function getStates(array $keys, mixed $defaultValue = null): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->getState($key, $defaultValue);
        }
        return $results;
    }
    
    /**
     * Clear multiple states at once
     * 
     * @param array $keys Array of state keys to clear
     * @return self
     * 
     * Usage:
     * $this->clearStates(['temp_data', 'form_cache', 'search_results']);
     */
    protected function clearStates(array $keys): self
    {
        foreach ($keys as $key) {
            $this->clearState($key);
        }
        return $this;
    }
    
    // ========================================================================
    // SMART DATA FILTERING - Flexible Array Data Management
    // ========================================================================
    
    /**
     * Smart array data filtering with include/exclude support
     * Generic method untuk filter array data dengan mode include/exclude
     * Mendukung associative array dan indexed array
     * 
     * @param array $data Source data array
     * @param string $key Key dari data yang akan diambil
     * @param array|null $fields Field yang akan di-include/exclude
     * @param bool $exclude Mode exclude jika true, include jika false
     * @return array Filtered data
     * 
     * Usage:
     * // Mode normal (ambil semua)
     * $allData = $this->filterConfigData($config, 'setValidasi');
     * 
     * // Mode include (ambil field tertentu saja)
     * $specific = $this->filterConfigData($config, 'setValidasi', ['title', 'images']);
     * 
     * // Mode exclude (ambil semua kecuali field tertentu)
     * $withoutImages = $this->filterConfigData($config, 'setValidasi', ['images'], true);
     * 
     * // Use case untuk form tanpa upload
     * $noUploadValidation = $this->filterConfigData($config, 'setValidasi', ['images', 'files'], true);
     */
    protected function filterConfigData(array $data, string $key, ?array $fields = null, bool $exclude = false): array
    {
        $result = $data[$key] ?? [];
        
        // Jika tidak ada fields yang diminta, return semua data
        if ($fields === null || !is_array($result) || !is_array($fields)) {
            return $result;
        }
        
        // Cek apakah array associative (seperti setValidasi) atau indexed (seperti setValue)
        $isAssociative = array_keys($result) !== range(0, count($result) - 1);
        
        if ($isAssociative) {
            // Untuk associative array (setValidasi, setUpload, dll)
            if ($exclude) {
                // Mode exclude: ambil semua kecuali yang ada di $fields
                $filtered = [];
                foreach ($result as $arrayKey => $value) {
                    if (!in_array($arrayKey, $fields)) {
                        $filtered[$arrayKey] = $value;
                    }
                }
                return $filtered;
            } else {
                // Mode include: ambil hanya yang ada di $fields
                $filtered = [];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $result)) {
                        $filtered[$field] = $result[$field];
                    }
                }
                return $filtered;
            }
        } else {
            // Untuk indexed array (setValue, dll)
            if ($exclude) {
                // Mode exclude: ambil semua kecuali yang ada di $fields
                return array_diff($result, $fields);
            } else {
                // Mode include: ambil hanya yang ada di $fields
                return array_intersect($result, $fields);
            }
        }
    }
    
    /**
     * Advanced config data manager dengan dukungan multiple keys
     * Mendukung filtering multiple keys sekaligus
     * 
     * @param array $data Source data array
     * @param array $keyConfig Configuration array: ['key' => [fields], 'key2' => [fields]]
     * @param bool $exclude Mode exclude jika true, include jika false
     * @return array Result dengan multiple keys
     * 
     * Usage:
     * $config = $this->multiFilterConfigData($globalData, [
     *     'setValidasi' => ['title', 'deskripsi'],
     *     'setValue' => ['title', 'deskripsi', 'categori'],
     *     'setUpload' => ['maxSize']
     * ]);
     * 
     * // Exclude mode
     * $config = $this->multiFilterConfigData($globalData, [
     *     'setValidasi' => ['images', 'files'],
     *     'setValue' => ['images']
     * ], true);
     */
    protected function multiFilterConfigData(array $data, array $keyConfig, bool $exclude = false): array
    {
        $result = [];
        
        foreach ($keyConfig as $key => $fields) {
            $result[$key] = $this->filterConfigData($data, $key, $fields, $exclude);
        }
        
        return $result;
    }
    
    /**
     * Quick method untuk exclude fields dari config data
     * Shorthand untuk filterConfigData dengan mode exclude
     * 
     * @param array $data Source data array
     * @param string $key Key dari data
     * @param array $excludeFields Fields yang akan di-exclude
     * @return array Filtered data tanpa excluded fields
     * 
     * Usage:
     * $validasiTanpaUpload = $this->excludeConfigFields($config, 'setValidasi', ['images', 'files']);
     */
    protected function excludeConfigFields(array $data, string $key, array $excludeFields): array
    {
        return $this->filterConfigData($data, $key, $excludeFields, true);
    }
    
    /**
     * Quick method untuk include only specific fields dari config data
     * Shorthand untuk filterConfigData dengan mode include
     * 
     * @param array $data Source data array
     * @param string $key Key dari data
     * @param array $includeFields Fields yang akan di-include
     * @return array Filtered data dengan only included fields
     * 
     * Usage:
     * $basicValidation = $this->includeConfigFields($config, 'setValidasi', ['title', 'deskripsi']);
     */
    protected function includeConfigFields(array $data, string $key, array $includeFields): array
    {
        return $this->filterConfigData($data, $key, $includeFields, false);
    }
    
    /**
     * Merge multiple config arrays dengan smart filtering
     * Menggabungkan beberapa config array dan apply filtering
     * 
     * @param array $configs Array of config arrays
     * @param array $filterRules Filter rules: ['config_index' => ['key' => [fields]]]
     * @param bool $exclude Mode exclude jika true
     * @return array Merged and filtered config
     * 
     * Usage:
     * $mergedConfig = $this->mergeConfigData([
     *     $config1, $config2, $config3
     * ], [
     *     0 => ['setValidasi' => ['images']], // Config pertama exclude images
     *     1 => ['setValue' => ['title', 'desc']] // Config kedua include title & desc
     * ], true);
     */
    protected function mergeConfigData(array $configs, array $filterRules = [], bool $exclude = false): array
    {
        $merged = [];
        
        foreach ($configs as $index => $config) {
            if (isset($filterRules[$index])) {
                $filtered = $this->multiFilterConfigData($config, $filterRules[$index], $exclude);
                $merged = array_merge_recursive($merged, $filtered);
            } else {
                $merged = array_merge_recursive($merged, $config);
            }
        }
        
        return $merged;
    }
    
    /**
     * Create config builder dengan fluent interface
     * Returns ConfigBuilder instance untuk chaining methods
     * 
     * @param array $data Source data
     * @return ConfigBuilder Builder instance
     * 
     * Usage:
     * $config = $this->configBuilder($globalData)
     *     ->exclude('setValidasi', ['images'])
     *     ->include('setValue', ['title', 'deskripsi'])
     *     ->get();
     */
    protected function configBuilder(array $data): ConfigBuilder
    {
        return new ConfigBuilder($data, $this);
    }
    
    /**
     * Debug method untuk config filtering
     * Menampilkan before/after filtering untuk debugging
     * 
     * @param array $data Source data
     * @param string $key Key yang akan di-filter
     * @param array|null $fields Fields untuk filter
     * @param bool $exclude Mode exclude
     * @return void
     */
    protected function debugConfigFilter(array $data, string $key, ?array $fields = null, bool $exclude = false): void
    {
        $before = $data[$key] ?? [];
        $after = $this->filterConfigData($data, $key, $fields, $exclude);
        
        echo "<div style='padding: 15px; background: #e8f5e8; border: 2px solid #4caf50; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #2e7d32; margin: 0 0 10px 0;'>🔍 Config Filter Debug</h4>";
        echo "<p><strong>Key:</strong> <code>{$key}</code></p>";
        echo "<p><strong>Mode:</strong> " . ($exclude ? 'EXCLUDE' : 'INCLUDE') . "</p>";
        echo "<p><strong>Fields:</strong> " . (is_array($fields) ? '[' . implode(', ', $fields) . ']' : 'ALL') . "</p>";
        
        echo "<h5>Before Filtering:</h5>";
        $this->Raw($before);
        
        echo "<h5>After Filtering:</h5>";
        $this->Raw($after);
        
        echo "</div>";
    }
    
    /**
     * Debug NexaState functionality
     * Useful for troubleshooting state issues
     * 
     * @param string|null $key Optional specific key to debug
     * @return array Debug information
     * 
     * Usage:
     * $this->debugState(); // General debug
     * $this->debugState('form_add'); // Debug specific key
     */
    protected function debugState(string $key = null): array
    {
        $debugInfo = [
            'general' => NexaState::debugGlobal(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($key !== null) {
            $state = new NexaState($key, null);
            $debugInfo['specific_key'] = $state->debug();
        }
        
        return $debugInfo;
    }
    
    /**
     * Debug and display NexaState functionality
     * Visual debugging method for development
     * 
     * @param string|null $key Optional specific key to debug
     * @return void
     * 
     * Usage:
     * $this->debugStateDisplay(); // General debug
     * $this->debugStateDisplay('form_add'); // Debug specific key
     */
    protected function debugStateDisplay(string $key = null): void
    {
        $debugInfo = $this->debugState($key);
        
        echo "<div style='padding: 15px; background: #f8f9fa; border: 2px solid #6c757d; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #495057; margin: 0 0 10px 0;'>🔧 NexaState Debug Information</h4>";
        
        if ($key) {
            echo "<p><strong>Debugging Key:</strong> <code>{$key}</code></p>";
        }
        
        $this->Raw($debugInfo);
        echo "</div>";
    }
    
    /**
     * Get controller name
     */
    protected function getControllerName(): string
    {
        $class = get_class($this);
        $className = basename(str_replace('\\', '/', $class));
        return str_replace('Controller', '', $className);
    }
    
    /**
     * Get action name (dari debug backtrace)
     */
    protected function getActionName(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $item) {
            if (isset($item['class']) && $item['class'] === get_class($this)) {
                return $item['function'] ?? 'unknown';
            }
        }
        return 'unknown';
    }
    
    /**
     * Get base URL for application (Enhanced with NexaRequest)
     */
    protected function getBaseUrl(): string
    {
        return $this->getRequest()->getBaseUrl();
    }
    protected function pagesIntRequest() {
        foreach ([$this->request->getRequestUri(), $this->request->getCleanPath()] as $src) {
            if (preg_match('#page[s]?/(\d+)#', (string) $src, $m)) {
                return max(1, (int) $m[1]);
            }
        }
        return 1;
    }
    /**
     * Get default template berdasarkan controller dan action
     */
    protected function getDefaultTemplate(): string
    {
        $controller = strtolower($this->getControllerName());
        $action = strtolower($this->getActionName());
        return "$controller/$action.html";
    }
    
    /**
     * Add CSS file ke template
     */
    protected function addCSS(string $path): self
    {
        $this->assignBlock('css_files', ['path' => $path]);
        return $this;
    }
    
    /**
     * Add JS file ke template
     */
    protected function addJS(string $path): self
    {
        $this->assignBlock('js_files', ['path' => $path]);
        return $this;
    }
    
    /**
     * Set page title
     */
    protected function setTitle(string $title): self
    {
        $this->assignVar('page_title', $title);
        return $this;
    }
    
    /**
     * Set meta description
     */
    protected function setDescription(string $description): self
    {
        $this->assignVar('meta_description', $description);
        return $this;
    }
    
    /**
     * Set meta keywords
     */
    protected function setKeywords(string $keywords): self
    {
        $this->assignVar('meta_keywords', $keywords);
        return $this;
    }
    
    /**
     * Assign SEO meta data
     */
    protected function setSEO(array $seo): self
    {
        $this->assignVars($seo);
        return $this;
    }
    
    /**
     * Resolve device-specific template path
     * Mobile/tablet: gunakan template dari folder device jika ada, fallback ke theme/
     */
    private function resolveDeviceTemplate(string $template): string
    {
        $templateBasePath = dirname(__DIR__) . '/templates/';
        $deviceSpecificPath = $this->deviceType . '/' . $template;
        $fullDevicePath = $templateBasePath . $deviceSpecificPath;
        
        // Gunakan template device-specific jika ada (mis. mobile/index.html)
        if (file_exists($fullDevicePath)) {
            return $deviceSpecificPath;
        }
        
        // Fallback: untuk mobile/tablet gunakan theme/ jika device template tidak ada
        if (in_array($this->deviceType, ['mobile', 'tablet'])) {
            $themePath = 'theme/' . $template;
            if (file_exists($templateBasePath . $themePath)) {
                return $themePath;
            }
        }
        
        // Fallback ke path asli
        return $template;
    }

    /**
     * Get default layout files configuration (fallback)
     */
    private function getDefaultLayoutFiles(): array
    {
        return [
            'theme' => ['header' => '', 'footer' => ''], // Fallback sederhana
        ];
    }

    /**
     * Get NexaDom template instance
     */
    protected function getTemplate(): NexaDom
    {
        return $this->template;
    }
    
    /**
     * Magic method untuk auto-render
     */
    public function __destruct()
    {
        if ($this->autoRender && !headers_sent()) {
            // Auto assign flash messages
            $this->assignFlash();
        }
    }
    
    /**
     * Set device type untuk layout
     * @param string $deviceType Tipe device (theme, mobile, tablet, dll)
     * @param array $options Array opsional untuk kustomisasi header/footer ['header_footer' => 'mobile']
     */
    protected function setDeviceType(string $deviceType, array $options = []): self
    {
        if (!isset($this->deviceLayouts[$deviceType])) {
            throw new Exception("Device type '$deviceType' not supported. Available types: " . implode(', ', array_keys($this->deviceLayouts)));
        }
        $this->deviceType = $deviceType;
        // Set header dan footer berdasarkan device type
        $layouts = $this->deviceLayouts[$deviceType];
        // Jika ada opsi header_footer, gunakan template dari device type yang ditentukan
        if (!empty($options['header_footer'])) {
            $headerFooterType = $options['header_footer'];
            if (isset($this->deviceLayouts[$headerFooterType])) {
                // Overwrite header/footer di deviceLayouts agar render() pakai yang benar
                $this->deviceLayouts[$deviceType]['header'] = $this->deviceLayouts[$headerFooterType]['header'] ?? '';
                $this->deviceLayouts[$deviceType]['footer'] = $this->deviceLayouts[$headerFooterType]['footer'] ?? '';
                $layouts = $this->deviceLayouts[$deviceType];
            }
        }
        if (!empty($layouts['header'])) {
            $this->assignVar('header_template', $layouts['header']);
        }
        if (!empty($layouts['footer'])) {
            $this->assignVar('footer_template', $layouts['footer']);
        }
        $this->assignVar('device_type', $deviceType);
        return $this;
    }
    
    /**
     * Get current device type (untuk NexaMapping compatibility)
     */
    public function getDeviceType(): string
    {
        return $this->deviceType;
    }
    
    // ===== BUILT-IN NEXAMAPPING METHODS =====
    
    /**
     * Handle resource routing using built-in NexaMapping
     * 
     * @param string $pathInfo Optional path info
     * @param callable|null $fallback Fallback function
     * @return bool True if handled
     */
    protected function handleResourceRouting(string $pathInfo = '', ?callable $fallback = null): bool
    {
        return $this->mapping()->handleRouting($pathInfo, $fallback);
    }
    
    /**
     * Add method mappings for this controller
     * 
     * @param array $mappings Action => Method mappings
     * @return void
     */
    protected function addResourceMappings(array $mappings): void
    {
        $this->mapping()->addMethodMappings($mappings);
    }
    
    /**
     * Auto-discover resource methods
     * 
     * @param string $suffix Method suffix (default: 'Resource')
     * @return int Number of discovered methods
     */
    protected function autoDiscoverResourceMethods(string $suffix = 'Resource'): int
    {
        return $this->mapping()->applyAutoDiscovery($suffix);
    }
    
  /**
     * Get NexaAuth helper auth
     * 
     * @return Authorization
     */
    protected function Authorization($keys='tatiye2025')
    {
        $auth = $this->auth;
        $auth->setSecretKey($keys);
        return $auth;
    } 
    /**
     * Get NexaMapping helper instance
     * 
     * @return NexaMapping
     */
    protected function getMappingHelper(): NexaMapping
    {
        return $this->mappingHelper ??= new NexaMapping($this);
    }
    
    /**
     * Get NexaMapping helper instance (shorthand method)
     *
     * @return NexaMapping
     */
    protected function mapping(): NexaMapping
    {
        return $this->mappingHelper ??= new NexaMapping($this);
    }
    
    /**
     * Handle direct parameter calls dari NexaRouter
     * Enhanced to properly handle render output and method execution
     * 
     * @param string|null $params Parameter string dari route
     * @return bool True jika berhasil ditangani
     */
    protected function handleDirectParamsCall(?string $params): bool
    {
        // Return false if params is null or empty
        if (empty($params)) {
            return false;
        }
        
        // Call mapping helper to handle the parameter call
        $handled = $this->mapping()->handleDirectParamsCall($params);
        
        if ($handled) {
            // Method sudah dipanggil dan render sudah dilakukan
            // Method yang dipanggil bertanggung jawab untuk output management
        }
        
        return $handled;
    }
    
    /**
     * Debug resource routing information
     * 
     * @param array $params Additional debug parameters
     * @return void
     */
    protected function debugResourceRouting(array $params = []): void
    {
        $debugData = array_merge([
            'controller' => get_class($this),
            'device_type' => $this->getDeviceType(),
            'path_info' => $this->getRequest()->getPath(),
            'request_uri' => $this->getRequest()->getRequestUri()
        ], $params);
        
        $this->mapping()->debugRouting($debugData);
    }
    

      /**
     * Enhanced Raw method untuk debugging dan display data
     * Mendukung berbagai format data: single array, array of arrays, objects, SafeParamsArray
     * 
     * @param mixed $data Data yang akan ditampilkan
     * @param bool $detailed Apakah menampilkan detail untuk nested data
     * @return string
     */
    protected function Raw($data, bool $detailed = false): string
    {
        // Handle different data types
        if ($data instanceof SafeParamsArray) {
            // SafeParamsArray - convert to regular array
            $data = $data->toArray();
        } elseif (is_object($data) && method_exists($data, 'toArray')) {
            // Object with toArray method
            $data = $data->toArray();
        } elseif (is_object($data)) {
            // Convert other objects to array
            $data = json_decode(json_encode($data), true);
        } elseif (!is_array($data)) {
            // Convert primitive types to array
            $data = ['value' => $data, 'type' => gettype($data)];
        }
        
        $table = new \App\System\Helpers\NexaRaw($data);
        
        // Capture output buffer
        ob_start();
        
        if ($detailed) {
            $table->renderDetailed(true);
        } else {
            $table->render(true);
        }
        
        $output = ob_get_clean();
        
        echo $output;
        return $output;
    }
    
    /**
     * Shorthand untuk Raw dengan detailed view
     * 
     * @param mixed $data Data yang akan ditampilkan
     * @return string
     */
    protected function RawDetailed($data): string
    {
        return $this->Raw($data, true);
    } 
    protected function getMethod($dataCalss='App\System\NexaController'): string{
        try {
            // Test menggunakan class name yang benar
            $analyzer = new \App\System\Helpers\NexaMethod($dataCalss);
            
            // Mendapatkan daftar method dengan detail
            $detailedMethods = $analyzer->getMethods(true);
            
            // Buat array data dengan struktur No, Methods, Parameters
            // Filter untuk tidak menampilkan magic methods
            $excludeMethods = ['__construct', 'tabelMethod'];
            $data = [];
            $counter = 1;
            foreach ($detailedMethods as $method) {
                // Skip magic methods yang tidak diinginkan
                if (in_array($method['name'], $excludeMethods)) {
                    continue;
                }
                
                $data[] = [
                    'No' => $counter,
                    'Methods' => $method['name'],
                    'Code' =>'$this->'.$method['name'].'()',
                    'Parameters' => empty($method['parameters']) ? 'None' : implode(', ', $method['parameters']),
                    'Visibility' => $method['visibility'] ?? 'public',
                    'Description' => $analyzer->generateMethodDescription(
                        $method['name'], 
                        $method['parameters'] ?? [], 
                        $method['visibility'] ?? 'public'
                    ),
                ];
                $counter++;
            }
            
            // Validasi data sebelum dikirim ke NexaRaw
            if (empty($data)) {
                $data = [
                    [
                        'No' => 1,
                        'Methods' => 'No methods found',
                        'Code' => '-',
                        'Parameters' => '-',
                        'Visibility' => '-',
                        'Description' => 'Tidak ada method yang dapat ditampilkan'
                    ]
                ];
            }
            // Kirim hanya array data ke NexaRaw, bukan wrapper object
            return $this->Raw($data);
            
        } catch (\Exception $e) {
            // Return data dummy untuk NexaRaw agar tidak error
            return $this->Raw([
                [
                    'No' => 1,
                    'Error' => 'Class tidak ditemukan',
                    'Message' => $e->getMessage(),
                    'Solution' => 'Pastikan class exists'
                ]
            ]);
        }

    } 






    /**
     * Get request input (supports POST and GET data)
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $requestData = $this->getRequestData();
        return $requestData[$key] ?? $default;
    }

    /**
     * Get all request inputs (supports POST, GET, and JSON data)
     */
    protected function inputs(): array
    {
        header('Content-Type: application/json; charset=utf-8');
        return $this->getRequestData();
    }

    /**
     * Validate required fields (legacy method)
     */
    protected function validateRequiredFields(array $fields): array
    {
        $errors = [];
        
        foreach ($fields as $field => $label) {
            $value = $this->input($field);
            if (empty($value) || (is_string($value) && trim($value) === '')) {
                $errors[$field] = "{$label} is required";
            }
        }
        
        return $errors;
    }


   
    /**
     * Sanitize input
     */
    protected function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Enhanced setData method with smart array detection
     * Supports multiple data structures automatically:
     * - Single associative array: $this->setData(['name' => 'John', 'email' => 'john@example.com'])
     * - Array of arrays (auto-use first element): $this->setData([['name' => 'John'], ['name' => 'Jane']])
     * - Single key-value: $this->setData('name', 'John')
     * - SafeParamsArray support: $this->setData($safeParamsArray)
     * 
     * @param mixed $key Array data, SafeParamsArray, or string key
     * @param mixed $value Value (when key is string)
     * @return self
     */
    protected function setData($key, $value = null): self
    {
        // Handle SafeParamsArray objects
        if ($key instanceof SafeParamsArray) {
            $key = $key->toArray();
        }
        
        // Handle objects with toArray method
        if (is_object($key) && method_exists($key, 'toArray')) {
            $key = $key->toArray();
        }
        
        if (is_array($key)) {
            // Check if this is an array of arrays (like database query results)
            if ($this->isArrayOfArrays($key)) {
                // Use the first element if it exists and is associative
                if (!empty($key) && is_array($key[0])) {
                    $firstElement = $key[0];
                    
                    // Check if first element is associative array
                    if ($this->isAssociativeArray($firstElement)) {
                        // Use first element as data
                        foreach ($firstElement as $k => $v) {
                            $this->template->assign_var($k, $v);
                            $this->data[$k] = $v;
                        }
                        
                        // Also set the full array for access if needed
                        $this->template->assign_var('data_array', $key);
                        $this->data['data_array'] = $key;
                        
                        // Set count for template use
                        $this->template->assign_var('data_count', count($key));
                        $this->data['data_count'] = count($key);
                        
                        return $this;
                    }
                }
                
                // If not associative, fall through to normal handling
            }
            
            // Normal associative array handling
            foreach ($key as $k => $v) {
                $this->template->assign_var($k, $v);
                $this->data[$k] = $v;
            }
        } else {
            // Single key-value pair
            $this->template->assign_var($key, $value);
            $this->data[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Check if array is an array of arrays (like database results)
     * 
     * @param array $array Array to check
     * @return bool True if array of arrays
     */
    private function isArrayOfArrays(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        
        // Check if all elements are arrays and keys are numeric
        $keys = array_keys($array);
        $isNumericKeys = array_keys($keys) === $keys; // Sequential numeric keys
        
        if (!$isNumericKeys) {
            return false;
        }
        
        // Check if first few elements are arrays
        $checkCount = min(3, count($array)); // Check first 3 elements
        for ($i = 0; $i < $checkCount; $i++) {
            if (!is_array($array[$i])) {
                return false;
            }
        }
        
        return true;
    }
    
         /**
      * Check if array is associative (has string keys)
      * 
      * @param array $array Array to check
      * @return bool True if associative
      */
     private function isAssociativeArray(array $array): bool
     {
         if (empty($array)) {
             return false;
         }
         
         return array_keys($array) !== range(0, count($array) - 1);
     }
     
     /**
      * Set data with explicit first element extraction
      * Forces using first element from array of arrays
      * 
      * @param array $data Array of arrays
      * @param string $arrayKey Optional key for full array storage
      * @return self
      * 
      * Usage:
      * $this->setDataFirst($users); // Uses $users[0] as template data
      * $this->setDataFirst($users, 'all_users'); // Also stores full array as 'all_users'
      */
     protected function setDataFirst(array $data, string $arrayKey = 'data_array'): self
     {
         if (!empty($data) && is_array($data[0])) {
             // Set data from first element
             foreach ($data[0] as $key => $value) {
                 $this->template->assign_var($key, $value);
                 $this->data[$key] = $value;
             }
             
             // Store full array if key provided
             if (!empty($arrayKey)) {
                 $this->template->assign_var($arrayKey, $data);
                 $this->data[$arrayKey] = $data;
             }
             
             // Set count
             $this->template->assign_var('data_count', count($data));
             $this->data['data_count'] = count($data);
         }
         
         return $this;
     }
     
     /**
      * Set data with specific element index
      * Uses specific element from array of arrays
      * 
      * @param array $data Array of arrays
      * @param int $index Index to use (default: 0)
      * @param string $arrayKey Optional key for full array storage
      * @return self
      * 
      * Usage:
      * $this->setDataIndex($users, 0); // Uses $users[0]
      * $this->setDataIndex($users, 2); // Uses $users[2]
      */
     protected function setDataIndex(array $data, int $index = 0, string $arrayKey = 'data_array'): self
     {
         if (isset($data[$index]) && is_array($data[$index])) {
             // Set data from specified element
             foreach ($data[$index] as $key => $value) {
                 $this->template->assign_var($key, $value);
                 $this->data[$key] = $value;
             }
             
             // Store full array if key provided
             if (!empty($arrayKey)) {
                 $this->template->assign_var($arrayKey, $data);
                 $this->data[$arrayKey] = $data;
             }
             
             // Set metadata
             $this->template->assign_var('data_count', count($data));
             $this->template->assign_var('data_index', $index);
             $this->data['data_count'] = count($data);
             $this->data['data_index'] = $index;
         }
         
         return $this;
     }
     
     /**
      * Set data with full array control (no auto-detection)
      * Forces normal array behavior without smart detection
      * 
      * @param array $data Array data
      * @return self
      * 
      * Usage:
      * $this->setDataRaw($users); // Sets {0}, {1}, {2} etc. (old behavior)
      */
     protected function setDataRaw(array $data): self
     {
         foreach ($data as $key => $value) {
             $this->template->assign_var($key, $value);
             $this->data[$key] = $value;
         }
         
         return $this;
     }
     
     /**
      * Set data with array loop preparation
      * Prepares array data for template loops
      * 
      * @param array $data Array of arrays
      * @param string $blockName Block name for template loops
      * @param string $arrayKey Optional key for array storage
      * @return self
      * 
      * Usage:
      * $this->setDataLoop($users, 'user_list');
      * // Template: <!-- BEGIN user_list -->{name}<!-- END user_list -->
      */
     protected function setDataLoop(array $data, string $blockName, string $arrayKey = ''): self
     {
         // Set each element as a block
         foreach ($data as $item) {
             if (is_array($item)) {
                 $this->template->assign_block_vars($blockName, $item);
             }
         }
         
         // Store full array if key provided
         if (!empty($arrayKey)) {
             $this->template->assign_var($arrayKey, $data);
             $this->data[$arrayKey] = $data;
         }
         
         // Set metadata
         $this->template->assign_var($blockName . '_count', count($data));
         $this->data[$blockName . '_count'] = count($data);
         
         return $this;
     }
     
     /**
      * Debug data structure to understand array format
      * Helpful for development and troubleshooting
      * 
      * @param mixed $data Data to analyze
      * @return array Analysis results
      * 
      * Usage:
      * $analysis = $this->debugDataStructure($users);
      * $this->dump($analysis);
      */
     protected function debugDataStructure($data): array
     {
         $analysis = [
             'type' => gettype($data),
             'is_array' => is_array($data),
             'is_object' => is_object($data),
             'is_safeParams' => $data instanceof SafeParamsArray,
             'has_toArray' => is_object($data) && method_exists($data, 'toArray')
         ];
         
         if (is_array($data)) {
             $analysis['array_info'] = [
                 'count' => count($data),
                 'is_empty' => empty($data),
                 'keys' => array_keys($data),
                 'is_associative' => $this->isAssociativeArray($data),
                 'is_array_of_arrays' => $this->isArrayOfArrays($data),
                 'first_element_type' => !empty($data) ? gettype($data[0]) : 'none',
                 'first_element_is_array' => !empty($data) && is_array($data[0]),
                 'first_element_is_associative' => !empty($data) && is_array($data[0]) ? $this->isAssociativeArray($data[0]) : false
             ];
             
             // Show first element structure if it's an array
             if (!empty($data) && is_array($data[0])) {
                 $analysis['first_element'] = [
                     'keys' => array_keys($data[0]),
                     'sample_data' => array_slice($data[0], 0, 3) // First 3 key-value pairs
                 ];
             }
         }
         
         return $analysis;
     }
     
     /**
      * Smart setData with behavior options
      * Provides explicit control over setData behavior
      * 
      * @param mixed $data Data to set
      * @param string $mode Behavior mode: 'auto', 'first', 'raw', 'loop'
      * @param array $options Additional options
      * @return self
      * 
      * Usage:
      * $this->setDataSmart($users, 'auto');  // Smart detection (default)
      * $this->setDataSmart($users, 'first'); // Force first element
      * $this->setDataSmart($users, 'raw');   // Force raw behavior
      * $this->setDataSmart($users, 'loop', ['block' => 'user_list']);
      */
     protected function setDataSmart($data, string $mode = 'auto', array $options = []): self
     {
         switch ($mode) {
             case 'first':
                 if (is_array($data)) {
                     $arrayKey = $options['array_key'] ?? 'data_array';
                     return $this->setDataFirst($data, $arrayKey);
                 }
                 break;
                 
             case 'raw':
                 if (is_array($data)) {
                     return $this->setDataRaw($data);
                 }
                 break;
                 
             case 'loop':
                 if (is_array($data)) {
                     $blockName = $options['block'] ?? 'data_loop';
                     $arrayKey = $options['array_key'] ?? '';
                     return $this->setDataLoop($data, $blockName, $arrayKey);
                 }
                 break;
                 
             case 'index':
                 if (is_array($data)) {
                     $index = $options['index'] ?? 0;
                     $arrayKey = $options['array_key'] ?? 'data_array';
                     return $this->setDataIndex($data, $index, $arrayKey);
                 }
                 break;
                 
             case 'auto':
             default:
                 return $this->setData($data);
         }
         
         // Fallback to normal setData
         return $this->setData($data);
     }
        /**
     * Set page data - sama dengan assign_var
     */
    protected function setValue($key, $value = null, $option = null): self
    {
        // Always set info_message first
        $this->template->assign_var('info_message', '');
        $this->data['info_message'] = '';
        
        // Handle selective options when key is array and value is string (option mode)
        if (is_array($key) && is_string($value) && $option === null) {
            $fields = $key;
            $option = $value;
            $this->setValueSelective($fields, $option);
            return $this;
        }
        
        // Handle explicit option parameter
        if ($option !== null && $option !== 'all') {
            $fields = is_array($key) ? $key : [$key];
            $this->setValueSelective($fields, $option, $value);
            return $this;
        }
        
        // Default behavior (all fields)
        if (is_array($key)) {
            // Check if it's an indexed array of field names or associative array
            if (array_keys($key) === range(0, count($key) - 1)) {
                // Indexed array of field names - initialize empty values
                foreach ($key as $fieldName) {
                    $this->setFieldComplete($fieldName, '');
                }
            } else {
                // Associative array - use as key-value pairs
                foreach ($key as $k => $v) {
                    $this->setFieldComplete($k, $v);
                }
            }
        } else {
            $this->setFieldComplete($key, $value);
        }
        
        return $this;
    }
    
    /**
     * Handle selective field setting based on option
     * 
     * @param array $fields Array of field names
     * @param string $option Option: 'error', 'value', 'name'
     * @param mixed $defaultValue Default value for fields
     * @return void
     */
    private function setValueSelective(array $fields, string $option, $defaultValue = ''): void
    {
        foreach ($fields as $field) {
            switch (strtolower($option)) {
                case 'error':
                case 'errors':
                    // Only set error fields
                    $this->template->assign_var('errors_' . $field, $defaultValue);
                    $this->data['errors_' . $field] = $defaultValue;
                    break;
                    
                case 'value':
                case 'values':
                    // Only set value fields
                    $this->template->assign_var($field . '_value', $defaultValue);
                    $this->data[$field . '_value'] = $defaultValue;
                    break;
                    
                case 'name':
                case 'names':
                case 'field':
                case 'fields':
                    // Only set name fields
                    $this->template->assign_var($field, $defaultValue);
                    $this->data[$field] = $defaultValue;
                    break;
                    
                case 'base':
                    // Set both name and value (without errors)
                    $this->template->assign_var($field, $defaultValue);
                    $this->template->assign_var($field . '_value', $defaultValue);
                    $this->data[$field] = $defaultValue;
                    $this->data[$field . '_value'] = $defaultValue;
                    break;
                    
                case 'all':
                default:
                    // Set all (name, value, error)
                    $this->setFieldComplete($field, $defaultValue);
                    break;
            }
        }
    }
    
    /**
     * Set complete field data (name, value, error)
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function setFieldComplete(string $field, $value): void
    {
        $this->template->assign_var($field, $value);
        $this->template->assign_var($field . '_value', $value);
        $this->template->assign_var('errors_' . $field, '');
        $this->data[$field] = $value;
        $this->data[$field . '_value'] = $value;
        $this->data['errors_' . $field] = '';
    }
   /**
     * Public method for FormBuilder to set data
     * @param array|string $key
     * @param mixed $value
     */
    public function setFormData($key, $value = null) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        } else {
            $this->data[$key] = $value;
        }
    }

        /**
     * Sets multiple key-value pairs or a single value in the view data array
     * Supports both array-based and single value assignments
     * 
     * @param mixed $value Array of key-value pairs or single value
     * @return void
     */
    protected function setKey($value) {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $this->data[$key] = $val;
            }
        } else {
            $this->data[$value] = $value;
        }
    }
    
    /**
     * Set multiple page data
     */
    protected function setDataArray(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

// ========================================================================
    // NEXA VALIDATION INTEGRATION - Enhanced Form Validation
    // ========================================================================

    /**
     * Quick validation with automatic POST sanitization and error handling
     * This method combines sanitization, validation, and response handling
     * 
     * @param array $rules Validation rules in format ['field' => ['Type', min_length, 'Custom Message']]
     * @param bool $redirectOnError Whether to redirect back on validation error
     * @param bool $flashErrors Whether to flash errors to session
     * @return bool|array Returns true if valid, false if invalid, or array of errors if not redirecting
     */
    protected function validateForm(array $rules, bool $redirectOnError = true, bool $flashErrors = true): bool|array
    {
        // Step 1: Sanitize POST data automatically
        $this->sanitizePostData($_POST);
        
        // Step 2: Run validation
        $isValid = $this->validateAll($rules);
        
        if ($isValid) {
            // Validation passed - clear any existing flash errors
            if ($flashErrors) {
                $this->getSession()->remove('validation_errors');
                $this->getSession()->remove('old_input');
            }
            return true;
        }
        
        // Validation failed - get errors
        $errors = $this->getErrors();
        
        if ($redirectOnError) {
            // Store errors and old input in session for display
            if ($flashErrors) {
                $this->getSession()->setFlash('validation_errors', $errors);
                $this->getSession()->setFlash('old_input', $this->getPostValue());
            }
            
            // Redirect back with errors
            $this->redirectBack();
        }
        
        return $errors;
    }



    /**
     * Get old input value for form repopulation
     * Works with both session flash data and current POST data
     * 
     * @param string $key Field name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    protected function old(string $key, mixed $default = ''): mixed
    {
        // First check current POST data (from validation trait)
        $currentValue = $this->getPostValue($key);
        if ($currentValue !== null) {
            return $currentValue;
        }
        
        // Then check session flash data
        $oldInput = $this->getSession()->getFlash('old_input', []);
        if (isset($oldInput[$key])) {
            return $oldInput[$key];
        }
        
        return $default;
    }

    /**
     * Check if field has validation error
     * 
     * @param string $field Field name
     * @return bool
     */
    protected function hasError(string $field): bool
    {
        // Check current validation errors
        $currentErrors = $this->getErrors();
        if (isset($currentErrors['errors_' . $field])) {
            return true;
        }
        
        // Check session flash errors
        $flashErrors = $this->getSession()->getFlash('validation_errors', []);
        return isset($flashErrors['errors_' . $field]);
    }

    /**
     * Get validation error message for specific field
     * 
     * @param string $field Field name
     * @param string $default Default message if no error found
     * @return string
     */
    protected function getFieldError(string $field, string $default = ''): string
    {
        // Check current validation errors first
        $currentErrors = $this->getErrors();
        if (isset($currentErrors['errors_' . $field])) {
            return $currentErrors['errors_' . $field];
        }
        
        // Check session flash errors
        $flashErrors = $this->getSession()->getFlash('validation_errors', []);
        if (isset($flashErrors['errors_' . $field])) {
            return $flashErrors['errors_' . $field];
        }
        
        return $default;
    }

    /**
     * Get all validation errors (current + flash)
     * 
     * @return array
     */
    protected function getAllErrors(): array
    {
        $currentErrors = $this->getErrors();
        $flashErrors = $this->getSession()->getFlash('validation_errors', []);
        
        return array_merge($flashErrors, $currentErrors);
    }

    /**
     * Validate single field with immediate response
     * Useful for dynamic field validation
     * 
     * @param string $field Field name
     * @param string $validationType Validation type (e.g., 'Email', 'Name', etc.)
     * @param int|null $minLength Minimum length if applicable
     * @param string|null $customMessage Custom error message
     * @return array Returns ['valid' => bool, 'message' => string, 'value' => mixed]
     */
    protected function validateSingleField(string $field, string $validationType, int $minLength = null, string $customMessage = null): array
    {
        // Sanitize current POST data
        $this->sanitizePostData($_POST);
        
        // Build validation rule
        $rules = [
            $field => [$validationType, $minLength, $customMessage]
        ];
        
        // Run validation
        $isValid = $this->validateAll($rules);
        $errors = $this->getErrors();
        
        return [
            'valid' => $isValid,
            'message' => $errors['errors_' . $field] ?? 'Valid',
            'value' => $this->getPostValue($field),
            'sanitized_value' => $this->getValidData()[$field] ?? null
        ];
    }

    /**
     * Validate with custom rules and get structured response
     * Perfect for API endpoints and complex validation scenarios
     * 
     * @param array $rules Validation rules
     * @param array $data Optional data to validate (defaults to $_POST)
     * @return array Structured validation result
     */
    protected function validateWithResponse(array $rules, array $data = null): array
    {
        // Use provided data or default to POST
        $data = $data ?? $_POST;
        
        // Sanitize data
        $this->sanitizePostData($data);
        
        // Run validation
        $isValid = $this->validateAll($rules);
        
        return [
            'valid' => $isValid,
            'errors' => $this->getErrors(),
            'valid_data' => $this->getValidData(),
            'sanitized_data' => $this->getPostValue(),
            'error_count' => count($this->getErrors()),
            'validated_fields' => array_keys($rules),
            'timestamp' => time()
        ];
    }

    /**
     * Create validation rules array from simple field definitions
     * Helper method to quickly build validation rules
     * 
     * @param array $fields Simple field definitions
     * @return array Formatted validation rules
     * 
     * Example:
     * $rules = $this->buildValidationRules([
     *     'name' => 'Name:5',                    // Name validation, min 5 chars
     *     'email' => 'Email',                    // Email validation
     *     'password' => 'Password:8',            // Password validation, min 8 chars
     *     'description' => 'Textarea:20'         // Textarea validation, min 20 chars
     * ]);
     */
    protected function buildValidationRules(array $fields): array
    {
        $rules = [];
        
        foreach ($fields as $field => $definition) {
            // Parse definition: "Type:minLength" or just "Type"
            $parts = explode(':', $definition);
            $type = $parts[0];
            $minLength = isset($parts[1]) ? (int) $parts[1] : null;
            
            $rules[$field] = [$type, $minLength];
        }
        
        return $rules;
    }

    /**
     * Helper method to render form with validation errors
     * Automatically includes error data and old input
     * 
     * @param string $view View template name
     * @param array $data Additional data for the view
     * @param string|null $layout Layout template
     * @return void
     */
    protected function renderWithValidation(string $view, array $data = [], string $layout = null): void
    {
        // Add validation data to view
        $validationData = [
            'validation_errors' => $this->getAllErrors(),
            'old_input' => $this->getPostValue() ?: $this->getSession()->getFlash('old_input', []),
            'has_errors' => !empty($this->getAllErrors())
        ];
        
        // Merge with existing data
        $allData = array_merge($data, $validationData);
        
        // Render view
        $this->render($view, $allData, $layout);
    }

    // ========================================================================
    // NEXAFORM INTEGRATION - Advanced Form Builder & Handler
    // ========================================================================
    
    /**
     * Access NexaModel Storage system
     * Provides direct access to NexaModel's Storage() method for database operations
     * 
     * @param string $table Table name
     * @return NexaModel NexaModel instance with table set
     */
    protected function Storage(string $table): NexaModel
    {
        $model = new NexaModel();
        return $model->Storage($table);
    }

    /**
     * Generate license key deterministik berdasarkan user ID.
     * Format: NEXA-XXXX-XXXX-XXXX-XXXX
     *
     * @param int $userId
     * @return string
     */
    protected function licenseGenerate(int $userId): string
    {
        return NexaLicense::generate($userId);
    }

    /**
     * Validasi format license key.
     *
     * @param string $key
     * @return bool
     */
    protected function licenseValidate(string $key): bool
    {
        return NexaLicense::validate($key);
    }

    /**
     * Cek apakah license key cocok dengan user ID.
     *
     * @param string $key
     * @param int    $userId
     * @return bool
     */
    protected function licenseMatches(string $key, int $userId): bool
    {
        return NexaLicense::matches($key, $userId);
    }

    /**
     * Ambil daftar package dari DB (untuk akses kontrol & menu dinamis).
     * Logika ada di App\Models\Package — dipanggil lewat useModels(), lihat docs/09-advanced.md §9.1.
     *
     * @return array [['key'=>'user','label'=>'User Management',...], ...]
     */
    protected function getPackageOptions(): array
    {
        try {
            $options = $this->useModels('Package', 'getOptions', []);
            return is_array($options) ? $options : [];
        } catch (\Throwable $e) {
            return \App\Models\Package::defaultRows();
        }
    }

    /**
     * Package untuk UI (menu admin, Kelola Package, assignment user): tanpa development = 3 (hidden).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getPackageOptionsVisible(): array
    {
        return array_values(array_filter(
            $this->getPackageOptions(),
            static function (array $p): bool {
                return (int) ($p['development'] ?? 2) !== 3;
            }
        ));
    }

    /**
     * NexaSend - Execute SQL queries from NexaModels API integration
     * Processes SQL queries received from frontend NexaModels and executes them
     * 
     * @param array $queryData Query data containing sql, bindings, type, and table
     * @return array Query results or error information
     */
 
     protected function NexaRender(){
         $data = $this->inputs();
        // Check if this is a NexaModels API call with SQL query
        if (isset($data['sql']) && isset($data['type'])) {
            // Process SQL query using NexaSend
            $result = $this->NexaBig($data);
            return $this->json($result);
        }
        
        // For other types of requests, return the input data as before
        return $this->json($data);
    }





    protected function NexaBig(array $queryData): array
    {
        try {
            // Validate required fields
            if (!isset($queryData['sql']) || !isset($queryData['type'])) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields: sql and type',
                    'data' => null
                ];
            }

            $sql =$this->Encrypt->decryptJson($queryData['sql']) ;
            $bindings = $queryData['bindings'] ?? [];
            $type = strtolower($queryData['type']);
            $table =$this->Encrypt->decryptJson($queryData['table']) ?? null;

            // Basic SQL injection protection
            if (!$this->isValidSQLQuery($sql, $type)) {
                return [
                    'success' => false,
                    'message' => 'Invalid or potentially dangerous SQL query',
                    'data' => null
                ];
            }

            // Create NexaModel instance
            $model = new NexaModel();
            
            // Set table if provided (for additional context)
            if ($table) {
                $model = $model->Storage($table);
            }

            // Handle parameter mismatch issue
            // Check if SQL contains placeholders (?) or has embedded values
            $hasPlaceholders = strpos($sql, '?') !== false;
            
            // If no placeholders but has bindings, it means NexaModels sent embedded values
            // In this case, we should execute without bindings
            if (!$hasPlaceholders && !empty($bindings)) {
                // SQL already has values embedded, execute without bindings
                $bindings = [];
            }

            // Execute query based on type
            switch ($type) {
                case 'select':
                    $results = $model->raw($sql, $bindings);
                    return [
                        'success' => true,
                        'message' => 'Query executed successfully',
                        'data' => $results,
                        'count' => count($results),
                        'query_info' => [
                            // 'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type,
                            'has_placeholders' => $hasPlaceholders,
                            'original_bindings_count' => count($queryData['bindings'] ?? [])
                        ]
                    ];
            case 'first':
                    $results = $model->raw($sql, $bindings);
                    return [
                        'success' => true,
                        'message' => 'Query executed successfully',
                        'data' => $results[0],
                        'count' => count($results),
                        'query_info' => [
                            // 'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type,
                            'has_placeholders' => $hasPlaceholders,
                            'original_bindings_count' => count($queryData['bindings'] ?? [])
                        ]
                    ];
                case 'insert':
                    $model->raw($sql, $bindings);
                    return [
                        'success' => true,
                        'message' => 'Insert executed successfully',
                        'data' => ['affected_rows' => 1],
                        'query_info' => [
                            'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type
                        ]
                    ];

                case 'update':
                    $model->raw($sql, $bindings);
                    return [
                        'success' => true,
                        'message' => 'Update executed successfully',
                        'data' => ['affected_rows' => 1],
                        'query_info' => [
                            // 'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type
                        ]
                    ];

                case 'delete':
                    $model->raw($sql, $bindings);
                    return [
                        'success' => true,
                        'message' => 'Delete executed successfully',
                        'data' => ['affected_rows' => 1],
                        'query_info' => [
                            // 'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type
                        ]
                    ];

                case 'count':
                    $results = $model->raw($sql, $bindings);
                    $count = isset($results[0]) ? (int)reset($results[0]) : 0;
                    return [
                        'success' => true,
                        'message' => 'Count executed successfully',
                        'data' => ['count' => $count],
                        'query_info' => [
                            // 'sql' => $sql,
                            'bindings' => $bindings,
                            'type' => $type
                        ]
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported query type: ' . $type,
                        'data' => null
                    ];
            }

        } catch (\Exception $e) {
            // Log error for debugging
            error_log("NexaSend Error: " . $e->getMessage());
            error_log("Query Data: " . json_encode($queryData));
            
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'data' => null,
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Validate SQL query for basic security
     * 
     * @param string $sql SQL query
     * @param string $type Query type
     * @return bool True if valid, false otherwise
     */
    private function isValidSQLQuery(string $sql, string $type): bool
    {
        // Remove extra whitespace and convert to lowercase for checking
        $cleanSql = strtolower(trim($sql));
        
        // Check if query type matches SQL statement
        switch ($type) {
            case 'select':
                if (!preg_match('/^select\s+/', $cleanSql)) {
                    return false;
                }
                break;
            case 'insert':
                if (!preg_match('/^insert\s+/', $cleanSql)) {
                    return false;
                }
                break;
            case 'update':
                if (!preg_match('/^update\s+/', $cleanSql)) {
                    return false;
                }
                break;
            case 'delete':
                if (!preg_match('/^delete\s+/', $cleanSql)) {
                    return false;
                }
                break;
            case 'count':
                if (!preg_match('/^select\s+count\s*\(/', $cleanSql)) {
                    return false;
                }
                break;
        }

        // Block potentially dangerous SQL patterns
        $dangerousPatterns = [
            '/\b(drop|truncate|alter|create|grant|revoke)\s+/i',
            '/\bunion\s+/i',
            '/\binto\s+outfile\s+/i',
            '/\bload_file\s*\(/i',
            '/\bsystem\s*\(/i',
            '/\bexec\s*\(/i',
            '/\bsp_\w+/i',
            '/\bxp_\w+/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $cleanSql)) {
                return false;
            }
        }

        return true;
    }
    
    // ========================================================================
    // NEXAREQUEST INTEGRATION - Advanced Request & URL Handling
    // ========================================================================
    
    /**
     * Get NexaRequest instance (lazy-loaded singleton)
     * Provides comprehensive request and URL handling capabilities
     * 
     * @return NexaRequest
     */
    protected function getRequest(): NexaRequest
    {
        if ($this->request === null) {
            $this->request = new NexaRequest();
        }
        return $this->request;
    }
    

    

    
    /**
     * Get current request URI
     * 
     * @return string
     */
    protected function getRequestUri(): string
    {
        return $this->getRequest()->getRequestUri();
    }
    
    /**
     * Get current path (without query string)
     * 
     * @return string
     */
    protected function getPath(): string
    {
        return $this->getRequest()->getPath();
    }
    
    /**
     * Get clean path (without trailing slash except root)
     * 
     * @return string
     */
    protected function getCleanPath(): string
    {
        return $this->getRequest()->getCleanPath();
    }
    
    /**
     * Get current URL (with protocol and host)
     * 
     * @return string
     */
    protected function getCurrentUrl(): string
    {
        return $this->getRequest()->getCurrentUrl();
    }
    
    /**
     * Build URL relative to base URL
     * 
     * @param string $path Path to append
     * @return string Complete URL
     */
    protected function url(string $path = ''): string
    {
        return $this->getRequest()->makeUrl($path);
    }
    
    /**
     * Get query parameter value
     * 
     * @param string $key Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    protected function getQuery(string $key, $default = null)
    {
        return $this->getRequest()->getQueryParam($key, $default);
    }
    
    /**
     * Get all query parameters
     * 
     * @return array
     */
    protected function getQueryParams(): array
    {
        return $this->getRequest()->getQueryParams();
    }
    
    /**
     * Check if path matches current request path
     * 
     * @param string $path Path to check
     * @return bool
     */
    protected function isCurrentPath(string $path): bool
    {
        return $this->getRequest()->isPath($path);
    }
    
    /**
     * Check if current path starts with given prefix
     * 
     * @param string $prefix Path prefix
     * @return bool
     */
    protected function pathStartsWith(string $prefix): bool
    {
        return $this->getRequest()->pathStartsWith($prefix);
    }
    
    /**
     * Get path segments as array
     * 
     * @return array
     */
    protected function getPathSegments(): array
    {
        return $this->getRequest()->getPathSegments();
    }
    
    /**
     * Get relative path segments (relative to base directory)
     * 
     * @return array
     */
    protected function getRelativePathSegments(): array
    {
        return $this->getRequest()->getRelativePathSegments();
    }
    
    /**
     * Get specific path segment by index
     * 
     * @param int $index Segment index
     * @param string $default Default value if not found
     * @return string
     */
    protected function getPathSegment(int $index, string $default = ''): string
    {
        return $this->getRequest()->getPathSegment($index, $default);
    }
    
    /**
     * Get specific relative path segment by index
     * 
     * @param int $index Segment index
     * @param string $default Default value if not found
     * @return string
     */
    protected function getRelativePathSegment(int $index, string $default = ''): string
    {
        return $this->getRequest()->getRelativePathSegment($index, $default);
    }
    
    // ========================================================================
    // URL ANALYTICS & TRACKING METHODS
    // ========================================================================
    
    /**
     * Get slug array for analytics and routing
     * Creates indexed slug array like: ['slug' => 'about-us', 'slug1' => 'contact-form']
     * 
     * @param string|null $url Optional URL to parse
     * @return array
     */
    protected function getSlugArray(?string $url = null): array
    {
        return $this->getRequest()->getSlugArray($url);
    }
    
    /**
     * Get part array (sanitized version of slugs)
     * Creates indexed part array like: ['part' => 'about us', 'part1' => 'contact form']
     * 
     * @param string|null $url Optional URL to parse
     * @return array
     */
    protected function getPartArray(?string $url = null): array
    {
        return $this->getRequest()->getPartArray($url);
    }
    
    /**
     * Get both slug and part arrays
     * 
     * @param string|null $url Optional URL to parse
     * @return array ['slug' => array, 'part' => array]
     */
    protected function getSegmentArrays(?string $url = null): array
    {
        return $this->getRequest()->getSegmentArrays($url);
    }
    
    /**
     * Get specific slug by index
     * 
     * @param int $index Slug index (0 = 'slug', 1 = 'slug1', etc.)
     * @param string $default Default value
     * @return string
     */
    protected function getSlug(int $index = 0, string $default = ''): string
    {
        return $this->getRequest()->getSlug($index, $default);
    }



    /**
     * Parse product slug to get ID and name
     * Example: "nasi-goreng-123" → ['id' => 123, 'name' => 'nasi-goreng']
     * 
     * @param string $slug Product slug
     * @return array ['id' => int|null, 'name' => string, 'display_name' => string]
     */
    protected function titelSlug(int $id): array
    {
        $slug=$this->getRequest()->getSlug($id);
        $slugParts = explode('-', $slug);
        $productId = null;
        $nameSlug = $slug;
        
        // Check if last part is numeric (ID)
        if (count($slugParts) > 1 && is_numeric(end($slugParts))) {
            $productId = array_pop($slugParts);
            $nameSlug = implode('-', $slugParts);
        }
        
        return [
            'id' => $productId,
            'name' => $nameSlug,
            'display_name' => ucwords(str_replace('-', ' ', $nameSlug))
        ];
    }

    
    /**
     * Get specific part by index
     * 
     * @param int $index Part index (0 = 'part', 1 = 'part1', etc.)
     * @param string $default Default value
     * @return string
     */
    protected function getPart(int $index = 0, string $default = ''): string
    {
        return $this->getRequest()->getPart($index, $default);
    }
    
    // ========================================================================
    // ADDITIONAL SLUG HELPER METHODS - Enhanced Functionality
    // ========================================================================
    
    /**
     * Check if current URL has specific slug at given index
     * 
     * @param int $index Slug index to check
     * @param string $slug Slug value to match
     * @return bool
     */
    protected function hasSlug(int $index, string $slug): bool
    {
        return $this->getSlug($index) === $slug;
    }
    
    /**
     * Check if current URL contains specific slug anywhere
     * 
     * @param string $slug Slug to search for
     * @return bool
     */
    protected function containsSlug(string $slug): bool
    {
        $slugs = array_values($this->getSlugArray());
        return in_array($slug, $slugs);
    }
    
    /**
     * Get first slug (shorthand for getSlug(0))
     * 
     * @param string $default Default value
     * @return string
     */
    protected function getFirstSlug(string $default = ''): string
    {
        return $this->getSlug(0, $default);
    }
    
    /**
     * Get last slug from current URL
     * 
     * @param string $default Default value
     * @return string
     */
    protected function getLastSlug(string $default = ''): string
    {
        $slugs = array_values($this->getSlugArray());
        return empty($slugs) ? $default : end($slugs);
    }
    
    /**
     * Count total number of slugs in current URL
     * 
     * @return int
     */
    protected function getSlugCount(): int
    {
        return count($this->getSlugArray());
    }
    
    /**
     * Get all slug values as simple array (without keys)
     * 
     * @return array
     */
    protected function getSlugValues(): array
    {
        return array_values($this->getSlugArray());
    }
    
    /**
     * Get all part values as simple array (without keys)
     * 
     * @return array
     */
    protected function getPartValues(): array
    {
        return array_values($this->getPartArray());
    }
    
    /**
     * Build breadcrumb array from slugs and parts
     * 
     * @param string $separator Breadcrumb separator
     * @param bool $includeHome Include home link
     * @return array
     */
    protected function getBreadcrumbs(string $separator = ' > ', bool $includeHome = true): array
    {
        $slugs = $this->getSlugValues();
        $parts = $this->getPartValues();
        $breadcrumbs = [];
        
        if ($includeHome) {
            $breadcrumbs[] = [
                'title' => 'Home',
                'slug' => '',
                'url' => $this->url('')
            ];
        }
        
        $currentPath = '';
        foreach ($slugs as $index => $slug) {
            $currentPath .= ($currentPath ? '/' : '') . $slug;
            $breadcrumbs[] = [
                'title' => ucwords($parts[$index] ?? str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'url' => $this->url($currentPath)
            ];
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Generate SEO-friendly page title from slugs/parts
     * 
     * @param string $separator Title separator
     * @param string $suffix Optional suffix (site name)
     * @param bool $reverse Reverse order (most specific first)
     * @return string
     */
    protected function generatePageTitle(string $separator = ' | ', string $suffix = '', bool $reverse = true): string
    {
        $parts = $this->getPartValues();
        
        if (empty($parts)) {
            return $suffix;
        }
        
        // Convert to title case
        $titles = array_map(function($part) {
            return ucwords(str_replace('-', ' ', $part));
        }, $parts);
        
        if ($reverse) {
            $titles = array_reverse($titles);
        }
        
        $title = implode($separator, $titles);
        
        return $suffix ? $title . $separator . $suffix : $title;
    }
    
    /**
     * Check if current URL matches a slug pattern
     * Pattern examples: blog/star/comments, admin/users/star, category/star/page
     * 
     * @param string $pattern Slug pattern with * as wildcard
     * @return bool
     */
    protected function matchesSlugPattern(string $pattern): bool
    {
        $slugs = $this->getSlugValues();
        $patternParts = explode('/', trim($pattern, '/'));
        
        if (count($slugs) !== count($patternParts)) {
            return false;
        }
        
        foreach ($patternParts as $index => $patternPart) {
            if ($patternPart === '*') {
                continue; // Wildcard matches anything
            }
            
            if (!isset($slugs[$index]) || $slugs[$index] !== $patternPart) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract slug parameters from pattern
     * Pattern: blog/star/comments with URL blog/my-post/comments returns my-post
     * 
     * @param string $pattern Slug pattern with * as wildcard
     * @return array Extracted parameters
     */
    protected function extractSlugParams(string $pattern): array
    {
        $slugs = $this->getSlugValues();
        $patternParts = explode('/', trim($pattern, '/'));
        $params = [];
        
        if (count($slugs) !== count($patternParts)) {
            return [];
        }
        
        foreach ($patternParts as $index => $patternPart) {
            if ($patternPart === '*') {
                $params[] = $slugs[$index] ?? '';
            } elseif (!isset($slugs[$index]) || $slugs[$index] !== $patternPart) {
                return []; // Pattern doesn't match
            }
        }
        
        return $params;
    }
    
    /**
     * Assign slug data to template automatically
     * Sets common slug-related variables for templates
     * 
     * @param string $prefix Variable prefix
     * @return self
     */
    protected function assignSlugData(string $prefix = 'slug_'): self
    {
        $this->assignVars([
            $prefix . 'array' => $this->getSlugArray(),
            $prefix . 'part_array' => $this->getPartArray(),
            $prefix . 'values' => $this->getSlugValues(),
            $prefix . 'part_values' => $this->getPartValues(),
            $prefix . 'count' => $this->getSlugCount(),
            $prefix . 'first' => $this->getFirstSlug(),
            $prefix . 'last' => $this->getLastSlug(),
            $prefix . 'breadcrumbs' => $this->getBreadcrumbs(),
            $prefix . 'page_title' => $this->generatePageTitle()
        ]);
        
        return $this;
    }
    
    /**
     * Build canonical URL from current slugs
     * 
     * @param bool $includeQuery Include query parameters
     * @return string
     */
    protected function getCanonicalUrl(bool $includeQuery = false): string
    {
        $path = implode('/', $this->getSlugValues());
        $url = $this->url($path);
        
        if ($includeQuery && !empty($this->getQueryParams())) {
            $url .= '?' . http_build_query($this->getQueryParams());
        }
        
        return $url;
    }
    
    // ========================================================================
    // HTTP HEADERS & SERVER INFORMATION
    // ========================================================================
    
    /**
     * Get all HTTP headers
     * 
     * @return array
     */
    protected function getHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
            // Ensure Authorization header is included (some servers don't include it in getallheaders)
            if (!isset($headers['Authorization']) && !isset($headers['authorization'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
                if (!empty($authHeader)) {
                    $headers['Authorization'] = $authHeader;
                }
            }
            return $headers;
        }
        
        // Fallback for environments where getallheaders() is not available
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $header = ucwords(strtolower($header), '-');
                $headers[$header] = $value;
            }
        }
        
        // Add Authorization header if not already included (handle special cases)
        if (!isset($headers['Authorization'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (!empty($authHeader)) {
                $headers['Authorization'] = $authHeader;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get specific HTTP header
     * 
     * @param string $header Header name
     * @param string $default Default value if header not found
     * @return string
     */
    protected function getHeader(string $header, string $default = ''): string
    {
        $headers = $this->getHeaders();
        
        // Case-insensitive header lookup
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, $header) === 0) {
                return (string) $value;
            }
        }
        
        return $default;
    }
    
    /**
     * Check if header exists
     * 
     * @param string $header Header name
     * @return bool
     */
    protected function hasHeader(string $header): bool
    {
        return $this->getHeader($header) !== '';
    }
    
    // ========================================================================
    // SERVER & ENVIRONMENT INFORMATION
    // ========================================================================
    
    /**
     * Get base directory path
     * 
     * @return string
     */
    protected function getBaseDir(): string
    {
        return $this->getRequest()->getBaseDir();
    }
    
    /**
     * Get project name (directory name)
     * 
     * @return string|null
     */
    protected function getProjectName(): ?string
    {
        return $this->getRequest()->getProjectName();
    }
    
    /**
     * Check if using HTTPS
     * 
     * @return bool
     */
    protected function isHttps(): bool
    {
        return $this->getRequest()->isHttps();
    }
    
    /**
     * Get domain/host
     * 
     * @return string
     */
    protected function getHost(): string
    {
        return $this->getRequest()->getHost();
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    protected function getClientIP(): string
    {
        return $this->getRequest()->getClientIP();
    }
    
    /**
     * Get User Agent
     * 
     * @return string
     */
    protected function getUserAgent(): string
    {
        return $this->getRequest()->getUserAgent();
    }
    
    /**
     * Get server information
     * 
     * @param string|null $key Specific server info key
     * @return mixed
     */
    protected function getServerInfo(?string $key = null)
    {
        return $key ? $this->getRequest()->getServerInfo($key) : $this->getRequest()->getAllServerInfo();
    }
    
    /**
     * Get environment information
     * 
     * @return array
     */
    protected function getEnvironmentInfo(): array
    {
        return $this->getRequest()->getEnvironmentInfo();
    }
    
    // ========================================================================
    // ENHANCED REQUEST METHODS (Complement existing methods)
    // ========================================================================
    

    
    /**
     * Enhanced getMethod using NexaRequest
     * 
     * @return string
     */
    protected function getRequestMethod(): string
    {
        return $this->getRequest()->getMethod();
    }
    
    /**
     * Check if request method matches
     * 
     * @param string $method HTTP method to check
     * @return bool
     */
    protected function isRequestMethod(string $method): bool
    {
        return $this->getRequest()->isMethod($method);
    }


    /**
     * Mengubah array menjadi JSON string dengan penanganan null
     * @param mixed $data Data yang akan diubah menjadi JSON
     * @param bool $prettyPrint Format JSON agar mudah dibaca (opsional)
     * @return string|null JSON string atau null jika data kosong
     */
    protected function toJson($data, $prettyPrint = false) {
        if (!isset($data)) {
            return null;
        }
        
        // Clean data before JSON encoding to handle malformed UTF-8
        $cleanData = $this->cleanUtf8Data($data);
        
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $options |= JSON_PRETTY_PRINT;
        }
        
        $json = json_encode($cleanData, $options);
        
        // Check for JSON encoding errors
        if ($json === false) {
            $error = json_last_error_msg();
            error_log("JSON encoding error: " . $error);
            
            // Fallback: try with basic options only
            $json = json_encode($cleanData, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                // Last resort: return a basic error response
                return json_encode(['error' => 'Failed to encode data as JSON: ' . $error]);
            }
        }
        
        return $json;
    }

    /**
     * Clean malformed UTF-8 characters from data before JSON encoding
     * 
     * @param mixed $data Data to clean
     * @return mixed Cleaned data
     */
    private function cleanUtf8Data($data)
    {
        if (is_string($data)) {
            // Remove or replace malformed UTF-8 characters
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            
            // If still problematic, use a more aggressive approach
            if (!mb_check_encoding($cleaned, 'UTF-8')) {
                // Replace invalid sequences with replacement character
                $cleaned = mb_convert_encoding($data, 'UTF-8', 'auto');
                
                // If still not valid, use preg_replace to remove invalid sequences
                if (!mb_check_encoding($cleaned, 'UTF-8')) {
                    $cleaned = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data);
                }
            }
            
            return $cleaned;
        }
        
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanKey = $this->cleanUtf8Data($key);
                $cleanValue = $this->cleanUtf8Data($value);
                $cleaned[$cleanKey] = $cleanValue;
            }
            return $cleaned;
        }
        
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return $this->cleanUtf8Data($data->toArray());
            }
            
            $cleaned = new \stdClass();
            foreach (get_object_vars($data) as $key => $value) {
                $cleanKey = $this->cleanUtf8Data($key);
                $cleanValue = $this->cleanUtf8Data($value);
                $cleaned->$cleanKey = $cleanValue;
            }
            return $cleaned;
        }
        
        // For other types (int, float, bool, null), return as-is
        return $data;
    }

    /**
     * Send JSON response dengan HTTP headers dan status code
     * Method ini yang dipanggil di jsData dan tempat lainnya
     * 
     * @param mixed $data Data untuk JSON response
     * @param int $statusCode HTTP status code (default: 200)
     * @param bool $exit Whether to exit after sending response (default: true)
     * @return void
     */
    protected function json($data, int $statusCode = 200, bool $exit = true): void
    {
        try {
            // Set CORS headers (tanpa credentials)
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers:*');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS');
            header('Access-Control-Max-Age: 86400'); // Cache preflight untuk 24 jam
            
            // Set HTTP status code
            http_response_code($statusCode);
            
            // Set JSON headers
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            // header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            
            // Convert data to JSON
            $jsonData = $this->toJson($data, false);
            
            // Check if JSON encoding was successful
            if ($jsonData === null || $jsonData === false) {
                // Fallback error response
                $errorData = [
                    'error' => 'Failed to encode response data',
                    'status' => 'error',
                    'message' => 'Internal server error - invalid data format'
                ];
                $jsonData = json_encode($errorData);
                http_response_code(500);
            }
            
            // Send JSON response
            echo $jsonData;
            
            // Exit if requested (default behavior)
            if ($exit) {
                exit;
            }
            
        } catch (Exception $e) {
            // Log the error
            error_log("JSON response error: " . $e->getMessage());
            
            // Send error response
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal server error',
                'status' => 'error',
                'message' => 'Failed to generate JSON response'
            ]);
            
            if ($exit) {
                exit;
            }
        }
    }
    

    protected function redJson() {
        $nexaJon = new NexaJon();
        return $nexaJon;
    }


    protected function  setGlobalSlug($Slug,$k=0){
           $ur =$this->getCleanPath();
           $ur = trim($ur, '/');
           $parts = explode('/', $ur);
           $selected = array_slice($parts, $k, $Slug);
           $result = '/' . implode('/', $selected);
           $GLOBALS['slug'] = [
                 'fullPath' => $result,
                 'version' => '1.0.0'
           ];

    }
   protected function  isFile($page,$thirdSegment,$finalMethod,$Dir='theme'){
         $templateDir='templates/'.$Dir;
         $PathGlobal= $GLOBALS['slug']['fullPath'] ?? $this->getCleanPath();
      
         $templatePath1=$templateDir. $PathGlobal.'.html';
         $fullPath = $this->getRequest()->getBaseDir().'/'.$templatePath1;
         
         // Check full path with /index.html (for deep nested routes like /docs/platform/electron)
         $templatePath1Index = $templateDir. $PathGlobal.'/index.html';
         $fullPathIndex = $this->getRequest()->getBaseDir().'/'.$templatePath1Index;
         
         $fullPath2 =$templateDir.'/'. strtolower($page) . '/' . $thirdSegment.'.html';
         $fullPath3 =$templateDir.'/'. strtolower($page) . '/' . $thirdSegment.'/index.html';
         
             if (is_file($fullPath)) {
                 return $PathGlobal;
              } elseif (is_file($fullPathIndex)) {
                 return $PathGlobal . '/index';
              } else {
                if (is_file($fullPath2)) {
                    return strtolower($page) . '/' . $thirdSegment;
                } elseif (is_file($fullPath3)) {
                    return strtolower($page) . '/' . $thirdSegment . '/index';
                } else {
                    return strtolower($page) . '/' . $finalMethod;
                }
              }

        
     }
     
    protected function  proSlug($Slug=2,$zet=0){
           $ur =$this->getCleanPath();
           $ur = trim($ur, '/');
           $parts = explode('/', $ur);
           $selected = array_slice($parts, $zet, $Slug);
           $result = '/' . implode('/', $selected);
           return $result;

    }
    protected function  isFile1($Slug=2,$finalMethod='',$Dir='theme'){
            $templateDir='/templates/'.$Dir;
            $result=$this->proSlug($Slug);
            $templatePath1=$templateDir.$result.'.html';
            $fullPath = $this->getRequest()->getBaseDir().''.$templatePath1;
            // Level 2 
            $Level2=abs($Slug-1);
            $result2=$this->proSlug($Level2);

             $templatePath2=$templateDir.$result2.'/index.html';
            $fullPath2 = $this->getRequest()->getBaseDir().''.$templatePath2;
    
            if (is_file($fullPath)) {
                return $result;
             } else {
                if (is_file($fullPath2)) {
                    return $result2. '/' . $finalMethod;
                } else {
                     return strtolower($Dir) . '/' . $finalMethod;
                }   
             }
     }


    protected function  isFile2($Slug=2,$finalMethod='',$Dir='theme'){
            $templateDir='/templates';
            $result=$this->proSlug($Slug);
            $templatePath1=$templateDir.$result.'.html';
            $fullPath = $this->getRequest()->getBaseDir().''.$templatePath1;
            // Level 2 
            $Level2=abs($Slug-1);
            $result2=$this->proSlug($Level2);
            $templatePath2=$templateDir.$result2.'/index.html';
            $fullPath2 = $this->getRequest()->getBaseDir().''.$templatePath2;
            if (is_file($fullPath)) {
                return $result;
             } else {
                if (is_file($fullPath2)) {
                    return $result2. '/' . $finalMethod;
                } else {
                     return strtolower($Dir) . '/' . $finalMethod;
                }   
             }
     }

                 protected function  isFile3($Slug=2,$finalMethod='',$Dir='theme'){
            // DIRECT FILE CHECKING - No cache, simple and reliable
            $PathGlobal = $GLOBALS['slug']['fullPath'] ?? '';
             
             $templateDir='/templates/'.$Dir;

            if (!empty($PathGlobal)) {
                $result=$PathGlobal;
                $fullPath = $this->getRequest()->getBaseDir().$templateDir.''.$PathGlobal.'.html';
                $fullPath2 = $this->getRequest()->getBaseDir().$templateDir.''.$PathGlobal.'/index.html';
                $result2=$PathGlobal.'/index';
            } else {
                $result=$this->proSlug($Slug,1);
                $fullPath = $this->getRequest()->getBaseDir().''.$templateDir.$result.'.html';
                $result2=$result.'/index';
                $fullPath2 = $this->getRequest()->getBaseDir().$templateDir.$result2.'.html';
            }
            
            // Level 2 
            $Level2=abs($Slug-1);
            $result3=$this->proSlug($Level2,1);
            $templatePath2=$templateDir.$result3.'/index.html';
            $fullPath3 = $this->getRequest()->getBaseDir().''.$templatePath2;
           
            $finalResult = '';
            
            if (is_file($fullPath)) { 
                $finalResult = $result;
            } else {
                if (is_file($fullPath2)) {
                    $finalResult = $result2;
                } else {
                    if (is_file($fullPath3)) {
                        $finalResult = $result3.'/index';
                    } else {
                        $finalResult = strtolower($Dir) . '/' . $finalMethod;
                    }
                }
            }
            
            // DIRECT RETURN - No caching, fresh result every time
            return $finalResult;
     }

     /**
      * Clear template resolution cache
      * Useful when templates are added/removed during runtime
      * 
      * @param string|null $specificKey Clear specific cache key or all if null
      * @return self
      */
     protected function clearTemplateResolutionCache(?string $specificKey = null): self
     {
         if ($specificKey !== null) {
             unset($this->templateResolutionCache[$specificKey]);
         } else {
             $this->templateResolutionCache = [];
         }
         return $this;
     }

     /**
      * Get template resolution cache statistics
      * Useful for monitoring cache performance
      * 
      * @return array
      */
     protected function getTemplateResolutionCacheStats(): array
     {
         return [
             'total_cached_items' => count($this->templateResolutionCache),
             'cache_keys' => array_keys($this->templateResolutionCache),
             'memory_usage' => memory_get_usage(true),
             'cache_size_bytes' => strlen(serialize($this->templateResolutionCache))
         ];
     }

     /**
      * Debug template resolution cache
      * Display cache contents for debugging
      * 
      * @return void
      */
     protected function debugTemplateResolutionCache(): void
     {
         $stats = $this->getTemplateResolutionCacheStats();
         $this->Raw([
             'template_resolution_cache_stats' => $stats,
             'cached_resolutions' => $this->templateResolutionCache
         ]);
     }

     /**
      * Pre-warm template resolution cache
      * Useful untuk pre-load common template paths
      * 
      * @param array $commonPaths Array of common template resolution parameters
      * @return self
      */
     protected function preWarmTemplateCache(array $commonPaths = []): self
     {
         // Default common paths jika tidak disediakan
         if (empty($commonPaths)) {
             $commonPaths = [
                 ['slug' => 2, 'method' => 'index', 'dir' => 'theme'],
                 ['slug' => 2, 'method' => 'show', 'dir' => 'theme'],
                 ['slug' => 3, 'method' => 'index', 'dir' => 'theme'],
                 ['slug' => 1, 'method' => 'index', 'dir' => 'theme'],
                 ['slug' => 2, 'method' => '', 'dir' => 'mobile'],
                 ['slug' => 2, 'method' => '', 'dir' => 'tablet'],
             ];
         }

         foreach ($commonPaths as $path) {
             $slug = $path['slug'] ?? 2;
             $method = $path['method'] ?? '';
             $dir = $path['dir'] ?? 'theme';
             
             // Pre-load into cache
             $this->isFile3($slug, $method, $dir);
         }

         return $this;
     }

     /**
      * Get cached template resolution if exists
      * Returns null if not cached, useful for conditional caching
      * 
      * @param int $Slug
      * @param string $finalMethod
      * @param string $Dir
      * @return string|null
      */
     protected function getCachedTemplateResolution(int $Slug = 2, string $finalMethod = '', string $Dir = 'theme'): ?string
     {
         $PathGlobal = $GLOBALS['slug']['fullPath'] ?? '';
         $cacheKey = "template_resolution_{$Slug}_{$finalMethod}_{$Dir}_{$PathGlobal}";
         
         return $this->templateResolutionCache[$cacheKey] ?? null;
     }

     protected function isExists($page, $template = 'theme', $type = 'html'){

         $templatePath='templates/'.$template.'/'.$page.'/'.$this->getSlug(1).'.'.$type;
         $filePath=$page.'/'.$this->getSlug(1);
         $fullPath = $this->getRequest()->getBaseDir().'/'.$templatePath;
 
        if (file_exists($fullPath)) {
            return  $filePath;
        } else {
            return $template.'/index';
        }
        
     }
    
    // ========================================================================
    // ANALYTICS & DEBUGGING HELPERS
    // ========================================================================
    
    /**
     * Get complete request analytics data
     * Perfect for debugging, logging, or analytics dashboard
     * 
     * @return array
     */
    protected function getRequestAnalytics(): array
    {
        return $this->getRequest()->toArray();
    }
    
    /**
     * Export request data to JSON
     * 
     * @return string
     */
    protected function getRequestJson(): string
    {
        return $this->getRequest()->toJson();
    }
    
    /**
     * Assign request analytics data to template
     * Useful for debugging or displaying request information
     * 
     * @param string $varName Template variable name
     * @return self
     */
    protected function assignRequestData(string $varName = 'request_data'): self
    {
        $this->assignVar($varName, $this->getRequestAnalytics());
        return $this;
    }
    
    /**
     * Debug request information (display in browser)
     * Useful for development and troubleshooting
     * 
     * @param bool $detailed Show detailed information
     * @return void
     */
    protected function debugRequest(bool $detailed = false): void
    {
        $data = $this->getRequestAnalytics();
        
        if (!$detailed) {
            // Show only essential info
            $data = array_filter($data, function($key) {
                return in_array($key, [
                    'request_uri', 'path', 'clean_path', 'query_params', 
                    'path_segments', 'slug_array', 'part_array', 'method',
                    'is_ajax', 'current_url', 'project_name'
                ]);
            }, ARRAY_FILTER_USE_KEY);
        }
        
        $this->Raw($data);
    }
    
    /**
     * Redirect back to previous page or fallback URL
     * Enhanced with NexaRequest for better referrer handling
     * 
     * @param string $fallback Fallback URL if no referrer
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function redirectBack(string $fallback = '/', int $statusCode = 302): void
    {
        $referrer = $this->getRequest()->getServerInfo('http_referer');
        
        if (!empty($referrer) && $referrer !== 'Unknown') {
            $this->redirect($referrer, $statusCode);
        } else {
            $this->redirect($fallback, $statusCode);
        }
    }
    
    /**
     * Get back URL (previous page URL) without redirecting
     * Returns the referrer URL or fallback URL
     * 
     * @param string $fallback Fallback URL if no referrer (default: '/')
     * @return string Previous page URL or fallback URL
     * 
     * Usage:
     * $backUrl = $this->urlBack();                    // Returns referrer or '/'
     * $backUrl = $this->urlBack('/home');             // Returns referrer or '/home'
     * $backUrl = $this->urlBack($this->url(''));      // Returns referrer or base URL
     * 
     * // Use in templates or conditional logic
     * $this->assignVar('back_url', $this->urlBack());
     * 
     * // Use with page item for smart back URL
     * $pageUrl = $this->getPageItem('url');
     * $backUrl = $this->urlBack($pageUrl ?: '/');
     */
    protected function urlBack(string $fallback = '/'): string
    {
        $referrer = $this->getRequest()->getServerInfo('http_referer');
        
        if (!empty($referrer) && $referrer !== 'Unknown') {
            return $referrer;
        }
        
        return $fallback;
    }
    
    /**
     * Create file path relative to base directory
     * 
     * @param string $path Relative path
     * @return string Complete file path
     */
    protected function makePath(string $path): string
    {
        return $this->getRequest()->asa($path);
    }
    
    /**
     * Safely decode base64 string with validation
     * Validates if the input is a valid base64 encoded string before decoding
     * 
     * @param string $input String to decode
     * @param mixed $default Default value if validation fails
     * @return mixed Decoded value or default value
     */
    protected function safeBase64Decode(string $input, mixed $default = null): mixed
    {
        // Method 1: Check if string is valid base64 format using regex
        if (!$this->isValidBase64($input)) {
            return $default;
        }
        
        // Method 2: Try decode with strict mode
        $decoded = base64_decode($input, true);
        
        if ($decoded === false) {
            return $default;
        }
        
        // Method 3: Verify by re-encoding (optional extra validation)
        if (base64_encode($decoded) !== $input) {
            return $default;
        }
        
        return $decoded;
    }
    
    /**
     * Check if string is valid base64 format
     * 
     * @param string $input String to validate
     * @return bool True if valid base64 format
     */
    protected function isValidBase64(string $input): bool
    {
        // Check if empty
        if (empty($input)) {
            return false;
        }
        
        // Check if string length is multiple of 4 (base64 requirement)
        if (strlen($input) % 4 !== 0) {
            return false;
        }
        
        // Check if contains only valid base64 characters
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $input)) {
            return false;
        }
        
        return true;
    }

    protected function isSlug(int $input, mixed $default = 'not'): mixed
    {

      if ($this->getSlug($input,'')) {
            return $default;
        } else {
            return '';
        }

    }
    /**
     * Alternative method - Simple base64 validation and decode
     * More lenient validation for cases where you want to allow some flexibility
     * 
     * @param string $input String to decode
     * @param mixed $default Default value if decode fails
     * @return mixed Decoded value or default value
     */
    protected function setDecode(string $input, mixed $default = null): mixed
    {
        // Try decode with strict mode
        $decoded = base64_decode($input, true);
        
        // Return decoded value if successful, otherwise return default
        return $decoded !== false ? $decoded : $default;
    }
    
    /**
     * Check if slug at specific index is valid base64
     * Helper method for URL parameter validation
     * 
     * @param int $index Slug index
     * @return bool True if slug is valid base64
     */
    protected function isSlugValidBase64(int $index): bool
    {
        $slug = $this->getSlug($index);
        return !empty($slug) && $this->isValidBase64($slug);
    }
    
    /**
     * Get decoded slug value safely
     * Combines getSlug and safeBase64Decode in one method
     * 
     * @param int $index Slug index
     * @param mixed $default Default value if decode fails
     * @return mixed Decoded slug value or default
     */
    protected function getDecodedSlug(int $index, mixed $default = null): mixed
    {
        $slug = $this->getSlug($index);
        return $this->safeBase64Decode($slug, $default);
    }
    
    /**
     * Check if file exists in project
     * 
     * @param string $filePath File path relative to base directory
     * @return bool
     */
    protected function fileExistsInProject(string $filePath): bool
    {
        return $this->getRequest()->fileExists($filePath);
    }
    
    // ========================================================================
    // NEXAAGENT INTEGRATION - Advanced User Agent Detection & Analysis
    // ========================================================================
    
    /**
     * Get NexaAgent instance (lazy-loaded singleton)
     * Provides comprehensive user agent detection and analysis capabilities
     * 
     * @return NexaAgent
     */
    protected function getAgent(): NexaAgent
    {
        if ($this->agent === null) {
            $this->agent = new NexaAgent();
        }
        return $this->agent;
    }
    
    /**
     * Get comprehensive browser, device, platform, and IP analysis
     * Returns complete analysis from NexaAgent
     * 
     * @return array Complete analysis
     * 
     * Usage:
     * $analysis = $this->getBrowserInfo();
     * echo $analysis['browser']; // Chrome
     * echo $analysis['platform']; // Windows 10/11
     * echo $analysis['device_type']; // Desktop
     * echo $analysis['ip_address']; // 192.168.1.1
     */
    protected function getBrowserInfo(): array
    {
        return $this->getAgent()->analyze();
    }
    
    /**
     * Get browser information only
     * 
     * @return array Browser details
     * 
     * Usage:
     * $browser = $this->getBrowser();
     * echo $browser['name']; // Chrome
     * echo $browser['version']; // 137.0.0.0
     * echo $browser['engine']; // Blink
     */
    protected function getBrowser(): array
    {
        return $this->getAgent()->getBrowser();
    }
    
    /**
     * Get platform/OS information only
     * 
     * @return array Platform details
     * 
     * Usage:
     * $platform = $this->getPlatform();
     * echo $platform['name']; // Windows 10/11
     * echo $platform['version']; // 10.0
     */
    protected function getPlatform(): array
    {
        return $this->getAgent()->getPlatform();
    }
    
    /**
     * Get device information only
     * 
     * @return array Device details
     * 
     * Usage:
     * $device = $this->getDevice();
     * echo $device['type']; // Desktop
     * echo $device['brand']; // Apple
     * echo $device['is_mobile']; // false
     * echo $device['is_touch']; // true
     */
    protected function getDevice(): array
    {
        return $this->getAgent()->getDevice();
    }
    
    /**
     * Get real IP address (behind proxies, CDN, etc.)
     * 
     * @return string Real IP address
     * 
     * Usage:
     * $ip = $this->getRealIP();
     * echo $ip; // 203.0.113.1
     */
    protected function getRealIP(): string
    {
        return $this->getAgent()->getRealIP();
    }
    
    /**
     * Get complete IP information including type and source
     * 
     * @return array IP information
     * 
     * Usage:
     * $ipInfo = $this->getIPInfo();
     * echo $ipInfo['address']; // 203.0.113.1
     * echo $ipInfo['type']; // public
     * echo $ipInfo['source']; // HTTP_CF_CONNECTING_IP
     */
    protected function getIPInfo(): array
    {
        return $this->getAgent()->getIPInfo();
    }
    
    /**
     * Check if current user agent is a bot/crawler
     * 
     * @return bool True if bot
     * 
     * Usage:
     * if ($this->isBot()) {
     *     // Handle bot traffic
     * }
     */
    protected function isBot(): bool
    {
        return $this->getAgent()->isBot();
    }
    
    /**
     * Check if current device is mobile
     * 
     * @return bool True if mobile
     * 
     * Usage:
     * if ($this->isMobile()) {
     *     $this->setDeviceType('mobile');
     * }
     */
    protected function isMobile(): bool
    {
        return $this->getAgent()->isMobile();
    }
    
    /**
     * Check if current device is tablet
     * 
     * @return bool True if tablet
     * 
     * Usage:
     * if ($this->isTablet()) {
     *     $this->setDeviceType('tablet');
     * }
     */
    protected function isTablet(): bool
    {
        return $this->getAgent()->isTablet();
    }
    
    /**
     * Check if current device is desktop
     * 
     * @return bool True if desktop
     * 
     * Usage:
     * if ($this->isDesktop()) {
     *     $this->setDeviceType('theme');
     * }
     */
    protected function isDesktop(): bool
    {
        return $this->getAgent()->isDesktop();
    }
    
    /**
     * Get device capabilities (WebGL, WebRTC, etc.)
     * 
     * @return array Device capabilities
     * 
     * Usage:
     * $capabilities = $this->getDeviceCapabilities();
     * if ($capabilities['supports_webgl']) {
     *     // Enable WebGL features
     * }
     */
    protected function getDeviceCapabilities(): array
    {
        return $this->getAgent()->getDeviceCapabilities();
    }
    
    /**
     * Get security information (bot detection, suspicious patterns, etc.)
     * 
     * @return array Security analysis
     * 
     * Usage:
     * $security = $this->getSecurityInfo();
     * if (!empty($security['suspicious_patterns'])) {
     *     // Log suspicious activity
     * }
     */
    protected function getSecurityInfo(): array
    {
        return $this->getAgent()->getSecurityInfo();
    }
    
    /**
     * Get full analysis with all available information
     * Includes browser, device, platform, IP, capabilities, and security info
     * 
     * @return array Complete analysis
     * 
     * Usage:
     * $fullAnalysis = $this->getFullAnalysis();
     * $this->assignVar('user_analysis', $fullAnalysis);
     */
    protected function getFullAnalysis(): array
    {
        return $this->getAgent()->getFullAnalysis();
    }
    
    /**
     * Auto-detect and set device type for layout rendering
     * Automatically sets device type based on user agent analysis
     * 
     * @return self
     * 
     * Usage:
     * $this->autoDetectDeviceType(); // Auto-sets mobile/tablet/theme
     * $this->render('dashboard/index');
     */
    protected function autoDetectDeviceType(): self
    {
        $device = $this->getDevice();
        
        if ($device['is_mobile']) {
            $this->setDeviceType('mobile');
        } elseif ($device['is_tablet']) {
            $this->setDeviceType('tablet');
        } else {
            $this->setDeviceType('theme'); // Desktop
        }
        
        return $this;
    }
    
    /**
     * Assign browser/device information to template
     * Makes user agent analysis available in templates
     * 
     * @param string $prefix Variable prefix (default: 'agent_')
     * @param bool $includeFullAnalysis Include complete analysis (default: false)
     * @return self
     * 
     * Usage:
     * $this->assignAgentData(); // Sets agent_* variables
     * $this->assignAgentData('browser_'); // Sets browser_* variables
     * $this->assignAgentData('agent_', true); // Include full analysis
     */
    protected function assignAgentData(string $prefix = 'agent_', bool $includeFullAnalysis = false): self
    {
        $analysis = $this->getBrowserInfo();
        
        // Core information
        $this->assignVars([
            $prefix . 'browser' => $analysis['browser'],
            $prefix . 'browser_version' => $analysis['browser_version'],
            $prefix . 'platform' => $analysis['platform'],
            $prefix . 'device_type' => $analysis['device_type'],
            $prefix . 'ip_address' => $analysis['ip_address'],
            $prefix . 'is_mobile' => $analysis['is_mobile'],
            $prefix . 'is_tablet' => $analysis['is_tablet'],
            $prefix . 'is_desktop' => $analysis['is_desktop'],
            $prefix . 'is_bot' => $analysis['is_bot'],
            $prefix . 'language' => $analysis['language'],
        ]);
        
        // Include full analysis if requested
        if ($includeFullAnalysis) {
            $this->assignVar($prefix . 'full_analysis', $this->getFullAnalysis());
        }
        
        return $this;
    }
    
    /**
     * Export user agent analysis to JSON
     * Useful for client-side processing or API responses
     * 
     * @param bool $pretty Pretty print JSON (default: false)
     * @return string JSON string
     * 
     * Usage:
     * $json = $this->getAgentJson();
     * $this->assignVar('agent_json', $json);
     */
    protected function getAgentJson(bool $pretty = false): string
    {
        return $this->getAgent()->toJson($pretty);
    }
    
    /**
     * Debug user agent analysis
     * Display comprehensive analysis for debugging
     * 
     * @param bool $includeHeaders Include raw headers (default: false)
     * @return void
     * 
     * Usage:
     * $this->debugAgent(); // Basic debug
     * $this->debugAgent(true); // Include headers
     */
    protected function debugAgent(bool $includeHeaders = false): void
    {
        $analysis = $includeHeaders ? $this->getFullAnalysis() : $this->getBrowserInfo();
        
        echo "<div style='padding: 20px; background: #e8f5e8; border: 2px solid #4caf50; margin: 15px 0; border-radius: 8px; font-family: Arial, sans-serif;'>";
        echo "<h3 style='color: #2e7d32; margin: 0 0 15px 0;'>🔍 NexaAgent Analysis Debug</h3>";
        echo "<p><strong>User Agent:</strong> <code>" . htmlspecialchars($this->getAgent()->getUserAgent()) . "</code></p>";
        
        $this->Raw($analysis);
        echo "</div>";
    }
    
    /**
     * Create custom NexaAgent instance with different user agent
     * Useful for testing or analyzing other user agents
     * 
     * @param string $userAgent Custom user agent string
     * @param array|null $serverData Optional server data
     * @return NexaAgent New NexaAgent instance
     * 
     * Usage:
     * $customAgent = $this->createCustomAgent('Mozilla/5.0 (iPhone...');
     * $iPhoneInfo = $customAgent->analyze();
     */
    protected function createCustomAgent(string $userAgent, ?array $serverData = null): NexaAgent
    {
        return new NexaAgent($userAgent, $serverData);
    }
    
    /**
     * Compare current user agent with another user agent
     * Useful for testing and comparison
     * 
     * @param string $otherUserAgent User agent to compare with
     * @return array Comparison results
     * 
     * Usage:
     * $comparison = $this->compareAgents('Mozilla/5.0 (iPhone...');
     * echo $comparison['current']['browser']; // Current browser
     * echo $comparison['other']['browser']; // Other browser
     * echo $comparison['same_browser']; // true/false
     */
    protected function compareAgents(string $otherUserAgent): array
    {
        $current = $this->getBrowserInfo();
        $other = $this->createCustomAgent($otherUserAgent)->analyze();
        
        return [
            'current' => $current,
            'other' => $other,
            'same_browser' => $current['browser'] === $other['browser'],
            'same_platform' => $current['platform'] === $other['platform'],
            'same_device_type' => $current['device_type'] === $other['device_type'],
            'both_mobile' => $current['is_mobile'] && $other['is_mobile'],
            'both_desktop' => $current['is_desktop'] && $other['is_desktop'],
        ];
    }
    
    /**
     * Get browser compatibility level (heuristic)
     * Returns compatibility level based on browser and version
     * 
     * @return array Compatibility info
     * 
     * Usage:
     * $compat = $this->getBrowserCompatibility();
     * echo $compat['level']; // modern, legacy, ancient
     * echo $compat['supports_es6']; // true/false
     */
    protected function getBrowserCompatibility(): array
    {
        $browser = $this->getBrowser();
        $capabilities = $this->getDeviceCapabilities();
        
        $compatibility = [
            'level' => 'unknown',
            'supports_es6' => false,
            'supports_css_grid' => false,
            'supports_webgl' => $capabilities['supports_webgl'],
            'supports_webrtc' => $capabilities['supports_webrtc'],
            'supports_websocket' => $capabilities['supports_websocket'],
            'recommended_features' => []
        ];
        
        // Determine compatibility level based on browser and version
        switch ($browser['name']) {
            case 'Chrome':
                if (version_compare($browser['version'], '51.0', '>=')) {
                    $compatibility['level'] = 'modern';
                    $compatibility['supports_es6'] = true;
                    $compatibility['supports_css_grid'] = true;
                } elseif (version_compare($browser['version'], '30.0', '>=')) {
                    $compatibility['level'] = 'legacy';
                } else {
                    $compatibility['level'] = 'ancient';
                }
                break;
                
            case 'Firefox':
                if (version_compare($browser['version'], '52.0', '>=')) {
                    $compatibility['level'] = 'modern';
                    $compatibility['supports_es6'] = true;
                    $compatibility['supports_css_grid'] = true;
                } elseif (version_compare($browser['version'], '25.0', '>=')) {
                    $compatibility['level'] = 'legacy';
                } else {
                    $compatibility['level'] = 'ancient';
                }
                break;
                
            case 'Safari':
                if (version_compare($browser['version'], '10.1', '>=')) {
                    $compatibility['level'] = 'modern';
                    $compatibility['supports_es6'] = true;
                    $compatibility['supports_css_grid'] = true;
                } elseif (version_compare($browser['version'], '7.0', '>=')) {
                    $compatibility['level'] = 'legacy';
                } else {
                    $compatibility['level'] = 'ancient';
                }
                break;
                
            case 'Edge':
                if (version_compare($browser['version'], '16.0', '>=')) {
                    $compatibility['level'] = 'modern';
                    $compatibility['supports_es6'] = true;
                    $compatibility['supports_css_grid'] = true;
                } else {
                    $compatibility['level'] = 'legacy';
                }
                break;
                
            case 'Internet Explorer':
                if (version_compare($browser['version'], '11.0', '>=')) {
                    $compatibility['level'] = 'legacy';
                } else {
                    $compatibility['level'] = 'ancient';
                }
                break;
                
            default:
                $compatibility['level'] = 'legacy';
        }
        
        // Set recommended features based on compatibility level
        switch ($compatibility['level']) {
            case 'modern':
                $compatibility['recommended_features'] = [
                    'ES6 modules', 'CSS Grid', 'WebGL', 'WebRTC', 'Service Workers'
                ];
                break;
            case 'legacy':
                $compatibility['recommended_features'] = [
                    'ES5 compatible code', 'Flexbox', 'Basic WebGL'
                ];
                break;
            case 'ancient':
                $compatibility['recommended_features'] = [
                    'ES3 compatible code', 'Basic CSS', 'No advanced features'
                ];
                break;
        }
        
        return $compatibility;
    }
    
    /**
     * Quick form builder with fluent interface
     * Alternative usage: $this->createForm()
     * Uses singleton pattern to maintain configuration across method calls
     * 
     * @return NexaForm
     */
    protected function createForm(): NexaForm
    {
        if ($this->form === null) {
            $this->form = new NexaForm($this);
        }
        return $this->form;
    }
    
   

    // ========================================================================
    // NEXANODE INTEGRATION - Dynamic Controller Routing
    // ========================================================================
    
        /**
     * Get request data from various sources (POST, PUT, PATCH)
     * 
     * @return array Request data
     */
    protected function getRequestData(): array
    {
        $data = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rawInput = file_get_contents('php://input');
        
        // Handle JSON input
        if (strpos($contentType, 'application/json') !== false) {
            $jsonData = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $jsonData;
            }
        }
        // Handle form-data and x-www-form-urlencoded
        else if (strpos($contentType, 'multipart/form-data') !== false || 
                 strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            // For POST requests, use $_POST directly
            if ($requestMethod === 'POST') {
                $data = $_POST;
            }
            // For PUT/PATCH/OPTIONS requests, parse from raw input if $_POST is empty
            else if (in_array($requestMethod, ['PUT', 'HEAD', 'OPTIONS', 'PATCH', 'DELETE'])) {
                if (!empty($_POST)) {
                    $data = $_POST;
                } else if (!empty($rawInput)) {
                    // Parse application/x-www-form-urlencoded from raw input
                    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                        parse_str($rawInput, $parsedData);
                        $data = $parsedData;
                    }
                    // For multipart/form-data, try to parse manually
                    else if (strpos($contentType, 'multipart/form-data') !== false) {
                        // Parse multipart form data manually
                        $boundary = '';
                        if (preg_match('/boundary=(.*)$/is', $contentType, $matches)) {
                            $boundary = '--' . trim($matches[1]);
                        }
                        if ($boundary && !empty($rawInput)) {
                            $parts = explode($boundary, $rawInput);
                            foreach ($parts as $part) {
                                if (empty(trim($part)) || trim($part) === '--') {
                                    continue;
                                }
                                if (preg_match('/name="([^"]+)"/', $part, $nameMatches)) {
                                    $fieldName = $nameMatches[1];
                                    // Extract value (after headers and before next boundary)
                                    $value = preg_split('/\r\n\r\n/', $part, 2);
                                    if (isset($value[1])) {
                                        $value = trim($value[1]);
                                        // Remove trailing boundary
                                        $value = preg_replace('/--\s*$/', '', $value);
                                        $data[$fieldName] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $data = $_POST;
            }
        }
        // Handle raw input as JSON if valid
        else if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $jsonData;
            } else {
                // Try to parse as form-urlencoded if not JSON
                parse_str($rawInput, $parsedData);
                if (!empty($parsedData)) {
                    $data = $parsedData;
                }
            }
        }
        
        // Merge with query parameters for GET requests
        if ($requestMethod === 'GET') {
            $data = array_merge($data, $_GET);
        }
        
        // Handle file uploads
        if (!empty($_FILES)) {
            $data['files'] = $_FILES;
        }
        
        return $data;
    }
    /**
     * Route to specific controller dynamically (NexaNode integration)
     * Enhanced version with full NexaController compatibility
     * 
     * @param string $page Page/Controller name
     * @param array $params Parameters to pass to controller
     * @param string|null $namespace Optional custom namespace
     * @return mixed Controller method result
     * @throws Exception
     */
   protected function nodeApiControllerData(string $page, array $params = [], string $namespace = null)
    {
        $requestMethod = $this->getRequestMethod();

        // Set default RESTful format based on HTTP method
        switch ($requestMethod) {
            case 'GET':
                $format = 'index';
                break;
            case 'POST':
                $format = 'created';
                break;
            case 'OPTIONS':
                $format = 'options';
                break;
            case 'PUT':
                $format = 'red';
                break;
            case 'PATCH':
                $format = 'patch';
                break;
            case 'DELETE':
                $format = 'deleted';
                break;
            default:
                $format = 'index';
        }



        $authHeader = $this->getHeader('Authorization', '');
        if (empty($authHeader)) {
            // Fallback: check $_SERVER for HTTP_AUTHORIZATION (standard PHP way)
            // Some servers (Apache with mod_rewrite) use REDIRECT_HTTP_AUTHORIZATION
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        
        // Cek apakah ini request untuk SdkController (berdasarkan kredensial)
        $kredensial = null;
        $isSdkController = false;
        
        // Hanya cek kredensial jika ada Authorization header
        if (!empty($authHeader)) {
            $kredensial = $this->Storage('production')
                ->select(['id', 'authorization', 'appid', 'endpoint', 'method_get', 'method_post', 'method_put', 'method_patch', 'method_options', 'method_delete', 'expiration', 'build_query', 'description', 'appname', 'developer', 'version'])
                ->where('authorization', $authHeader)
                ->first();
            
            if (!empty($kredensial['id'])) {
                $controllerName = 'SdkController';
                $isSdkController = true;
            } else {
                $controllerName = ucfirst(strtolower($page)) . 'Controller';
            }
        } else {
            // Jika tidak ada Authorization header, gunakan controller normal
            $controllerName = ucfirst(strtolower($page)) . 'Controller';
        }
            

        // Convert page name to controller class
      
        $controllerClass = ($namespace ?? $this->baseControllerNamespace) . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller not found: {$controllerClass}");
        }

        try {
            // Get controller instance (with caching)
            $controller = $this->getControllerInstance($controllerClass);

            // Inject dependencies if the target controller supports it
            if (method_exists($controller, 'setDependencies')) {
                $controller->setDependencies();
            }

            // Get the requested method from URL slug or params
            $slugArray = $this->getSlugArray();
            $requestedMethod = '';
            
            // Normal controller: /api/{controller}/{method} → method di slug2
            if (isset($slugArray['slug2'])) {
                $requestedMethod = $slugArray['slug2'];
            }
            
            // SDK mode: /api/{method} → method di slug1 (tidak ada nama controller di URL)
            if (empty($requestedMethod) && $isSdkController && isset($slugArray['slug1'])) {
                $requestedMethod = $slugArray['slug1'];
            }
            
            // Fallback: cek params['method'] atau HTTP format default
            if (empty($requestedMethod)) {
                $requestedMethod = $params['method'] ?? $format;
            }

            // SDK mode: setelah semua fallback, jika requestedMethod adalah nama REST method
            // tapi TIDAK cocok dengan HTTP format → paksa gunakan HTTP format.
            // Contoh: GET /api/patch → requestedMethod='patch', format='index' → pakai 'index'
            // Contoh: GET /api/info  → requestedMethod='info', bukan REST method → tetap 'info'
            $httpRestMethods = ['index', 'created', 'patch', 'red', 'deleted', 'options'];
            if ($isSdkController && in_array($requestedMethod, $httpRestMethods) && $requestedMethod !== $format) {
                $requestedMethod = $format;
            }

            // Determine which method to call
            $method = 'index'; // Default fallback
            
            // First try the requested method
            if (!empty($requestedMethod) && method_exists($controller, $requestedMethod)) {
                $method = $requestedMethod;
            }
            // Then try the RESTful format method
            else if (method_exists($controller, $format)) {
                $method = $format;
            }
            // If method still not found, throw exception with details
            else {
                $availableMethods = get_class_methods($controller);
                $errorMsg = "Method '{$format}' not found in controller '{$controllerClass}'. Request method: {$requestMethod}. Available methods: " . implode(', ', $availableMethods);
                throw new Exception($errorMsg);
            }



            // Prepare parameters for the target controller
            $controllerParams = $this->prepareNodeApiControllerParamsData($page, $params, $kredensial, $isSdkController);

            // Cek status dan expired HANYA untuk SdkController
            if ($isSdkController && isset($controllerParams['status']) && $controllerParams['status'] === false) {
                // Jika expired, return pesan error khusus
                if (isset($controllerParams['expired']) && $controllerParams['expired'] === true) {
                    return [
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'Akses ditolak: Token atau kredensial sudah expired',
                        'expired' => true
                    ];
                }
                // Jika status false karena alasan lain (tidak ada kredensial, dll)
                return [
                    'status' => 'error',
                    'code' => 401,
                    'message' => 'Silakan periksa authorization dan endpoint yang digunakan'
                ];
            }

            // Cek apakah request method diizinkan berdasarkan config (HANYA untuk SdkController)
            if ($isSdkController && isset($controllerParams['allowed_methods']) && !empty($controllerParams['allowed_methods'])) {
                $allowedMethods = $controllerParams['allowed_methods'];
                if (!in_array($requestMethod, $allowedMethods)) {
                    return [
                        'status' => 'error',
                        'code' => 405,
                        'message' => "Method {$requestMethod} tidak diizinkan untuk endpoint ini. Method yang diizinkan: " . implode(', ', $allowedMethods),
                        'allowed_methods' => $allowedMethods
                    ];
                }
            }

            // Call the method and get result
            // Check method signature to determine how to call it
            $reflection = new \ReflectionMethod($controller, $method);
            $numParams = $reflection->getNumberOfParameters();
            
            if ($numParams === 0) {
                // Method tidak menerima parameter
                $result = $controller->$method();
            } elseif ($numParams === 1) {
                // Method menerima 1 parameter
                $result = $controller->$method($this->getRequestData());
            } else {
                // Method menerima 2 atau lebih parameter
                $result = $controller->$method($this->getRequestData(), $controllerParams);
            }

            return $result ?? true;

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            // Check if it's a JSON encoding error
            if (strpos($errorMessage, 'JSON') !== false || strpos($errorMessage, 'UTF-8') !== false) {
                throw new Exception("Failed to encode data as JSON: " . $errorMessage);
            }
            
            throw new Exception("Failed to execute controller '{$controllerClass}': " . $errorMessage);
        }
    }
    /**
     * Prepare parameters for target controller (NexaNode style)
     * 
     * @param string $page Page name
     * @param array $params Route parameters
     * @param array|null $kredensial Credentials data (null for non-SdkController)
     * @param bool $isSdkController Whether this is SdkController request
     * @return array Prepared parameters
     */
    private function prepareNodeApiControllerParamsData(string $page, array $params, ?array $kredensial, bool $isSdkController = false): array
    {
        $controllerParams =  $params;

        // Add request parameters
        if (isset($params['params'])) {
            $controllerParams['params'] = $params['params'];
        }

        if (isset($params['method'])) {
            $controllerParams['requested_method'] = $params['method'];
        }

        // Add individual parameters (param_0, param_1, etc.)
        foreach ($params as $key => $value) {
            if (strpos($key, 'param_') === 0 || strpos($key, '_') !== 0) {
                $controllerParams[$key] = $value;
            }
        }
       
        $redID = $this->getUser();
        
        // Get slug array and filter slugs after slug1
        $slugArray = $this->getSlugArray();
        $lastSlug = '';
        $filteredSlug = [];
        
        // Keep all slugs starting from slug2
        foreach ($slugArray as $key => $value) {
            if (preg_match('/^slug([2-9]|\d{2,})$/', $key)) {
                $filteredSlug[$key] = $value;
                // Use the first found slug after slug1 as resource
                if (empty($lastSlug)) {
                    $lastSlug = $value;
                }
            }
        }

        // Add current request context
        // Get Authorization header according to HTTP standard
        // Authorization header format: "Authorization: <type> <credentials>"
        // In PHP, it's available as $_SERVER['HTTP_AUTHORIZATION'] or via getallheaders()

        
        // Baca kredensial dari production table
        $authData = [];
        $isExpired = false;
        $allowedMethods = []; // Array untuk menyimpan method yang diizinkan
        
        if (!empty($kredensial) && isset($kredensial['id'])) {
            // Baca expiration dari kolom JSON production table
            $expiration = [];
            if (!empty($kredensial['expiration'])) {
                $expiration = is_string($kredensial['expiration'])
                    ? (json_decode($kredensial['expiration'], true) ?? [])
                    : $kredensial['expiration'];
            }
            
            // Cek expiry: neverExpires atau dari tanggal preview
            $neverExpires = (bool)($expiration['neverExpires'] ?? false);
            if (!$neverExpires) {
                $expairDate = $expiration['preview'] ?? null;
                if (!empty($expairDate)) {
                    $expairTimestamp = strtotime($expairDate);
                    if ($expairTimestamp !== false && time() > $expairTimestamp) {
                        $isExpired = true;
                    }
                }
            }
            
            // Ambil method yang diizinkan dari kolom boolean production
            $methodMap = [
                'GET'     => 'method_get',
                'POST'    => 'method_post',
                'PUT'     => 'method_put',
                'PATCH'   => 'method_patch',
                'DELETE'  => 'method_delete',
                'OPTIONS' => 'method_options',
            ];
            foreach ($methodMap as $httpMethod => $column) {
                if (!empty($kredensial[$column])) {
                    $allowedMethods[] = $httpMethod;
                }
            }
            
            // Build authData untuk spread ke params
            $authData = [
                'appid'       => $kredensial['appid'] ?? null,
                'endpoint'    => $kredensial['endpoint'] ?? null,
                'description' => $kredensial['description'] ?? null,
                'appname'     => $kredensial['appname'] ?? null,
                'developer'   => $kredensial['developer'] ?? null,
                'version'     => $kredensial['version'] ?? null,
                'build_query' => !empty($kredensial['build_query'])
                    ? (is_string($kredensial['build_query']) ? json_decode($kredensial['build_query'], true) : $kredensial['build_query'])
                    : [],
                'expiration'  => $expiration,
            ];
        }
          $authHeader = $this->getHeader('Authorization', '');
        if (empty($authHeader)) {
            // Fallback: check $_SERVER for HTTP_AUTHORIZATION (standard PHP way)
            // Some servers (Apache with mod_rewrite) use REDIRECT_HTTP_AUTHORIZATION
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        // Set status: 
        // - Untuk SdkController: false jika tidak ada kredensial, tidak ada id, atau sudah expired
        // - Untuk controller lain: selalu true (tidak perlu authorization)
        if ($isSdkController) {
            $status = !empty($kredensial) && isset($kredensial['id']) && !$isExpired;
        } else {
            // Controller biasa tidak perlu authorization, set status = true
            $status = true;
            $isExpired = false;
            $allowedMethods = []; // Reset allowed methods untuk controller biasa
        }
        
        $controllerParams= [
            'endpoints'  => "/api/".$this->getSlug(1),
             ...$authData ?? [],
            'status' => $status,
            'authorization' => $authHeader ?: 'NX_XXXXXXXXXXXXXXXXX',  // HTTP Authorization header value (empty if not provided)
            'expired' => $isExpired, // Flag untuk menandakan apakah token sudah expired
            'allowed_methods' => $allowedMethods, // Array method HTTP yang diizinkan
    // $kredensial['auth']['config']
    // {
 
            // "GET": true,
            // "PUT": false,
            // "POST": true,
            // "PATCH": true,
            // "DELETE": true,
            // "OPTIONS": true,
            // "expair": "2026-01-23",
            // "appname": "Dantrik",
            // "endpoind": "exsampel",
            // "description": "Text Api SDK",
            // "authorization": "NX_1762866008049279283707314106621"
        
    //         }
        ];

        return $controllerParams;
    }

    /**
     * Route to specific controller dynamically (NexaNode integration)
     * Enhanced version with full NexaController compatibility
     * 
     * @param string $page Page/Controller name
     * @param array $params Parameters to pass to controller
     * @param string|null $namespace Optional custom namespace
     * @return mixed Controller method result
     * @throws Exception
     */
   protected function nodeApiController(string $page, array $params = [], string $namespace = null)
    {
        $requestMethod = $this->getRequestMethod();

        // Set default RESTful format based on HTTP method
        switch ($requestMethod) {
            case 'GET':
                $format = 'index';
                break;
            case 'POST':
                $format = 'created';
                break;
            case 'OPTIONS':
                $format = 'options';
                break;
            case 'PUT':
                $format = 'red';
                break;
            case 'PATCH':
                $format = 'patch';
                break;
            case 'DELETE':
                $format = 'deleted';
                break;
            default:
                $format = 'index';
        }

        // Convert page name to controller class
        $controllerName = ucfirst(strtolower($page)) . 'Controller';
        $controllerClass = ($namespace ?? $this->baseControllerNamespace) . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller not found: {$controllerClass}");
        }

        try {
            // Get controller instance (with caching)
            $controller = $this->getControllerInstance($controllerClass);

            // Inject dependencies if the target controller supports it
            if (method_exists($controller, 'setDependencies')) {
                $controller->setDependencies();
            }

            // Get the requested method from URL slug or params
            $slugArray = $this->getSlugArray();
            $requestedMethod = '';
            
            // Check if method exists in slug2
            if (isset($slugArray['slug2'])) {
                $requestedMethod = $slugArray['slug2'];
            }
            
            // If no method in slug, check params
            if (empty($requestedMethod)) {
                $requestedMethod = $params['method'] ?? $format;
            }

            // Determine which method to call
            $method = 'index'; // Default fallback
            
            // First try the requested method
            if (!empty($requestedMethod) && method_exists($controller, $requestedMethod)) {
                $method = $requestedMethod;
            }
            // Then try the RESTful format method
            else if (method_exists($controller, $format)) {
                $method = $format;
            }

            // Prepare parameters for the target controller
            $controllerParams = $this->prepareNodeApiControllerParams($page, $params);

            // Call the method and get result
            $result = $controller->$method($this->getRequestData(), $controllerParams);

            return $result ?? true;

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            // Check if it's a JSON encoding error
            if (strpos($errorMessage, 'JSON') !== false || strpos($errorMessage, 'UTF-8') !== false) {
                throw new Exception("Failed to encode data as JSON: " . $errorMessage);
            }
            
            throw new Exception("Failed to execute controller '{$controllerClass}': " . $errorMessage);
        }
    }



    protected function nodeController(string $page, array $params = [], string $namespace = null)
    {
        // Convert page name to controller class
        $controllerName = ucfirst(strtolower($page)) . 'Controller';
        $controllerClass = ($namespace ?? $this->baseControllerNamespace) . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller not found: {$controllerClass}");
        }

        try {
            // Get controller instance (with caching)
            $controller = $this->getControllerInstance($controllerClass);

            // Inject dependencies if the target controller supports it
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

            // Store method info in params
            $params['_method_used'] = $method;
            $params['_requested_method'] = $requestedMethod;
            $params['_used_fallback'] = $usedFallback;
            $params['_calling_controller'] = get_class($this);

            // Prepare parameters for the target controller
            $controllerParams = $this->prepareNodeControllerParams($page, $params);

            // Call the method and get result
            $result = $controller->$method($controllerParams);

            return $result ?? true;

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            // Check if it's a JSON encoding error
            if (strpos($errorMessage, 'JSON') !== false || strpos($errorMessage, 'UTF-8') !== false) {
                throw new Exception("Failed to encode data as JSON: " . $errorMessage);
            }
            
            throw new Exception("Failed to execute controller '{$controllerClass}': " . $errorMessage);
        }
    }

    /**
     * Get controller instance (NO CACHING - Controllers created fresh each time)
     * 
     * @param string $controllerClass Full controller class name
     * @return object Controller instance
     * @throws Exception
     */
    private function getControllerInstance(string $controllerClass): object
    {
        try {
            // Check if target controller extends NexaController
            $reflection = new \ReflectionClass($controllerClass);
            
            if ($reflection->isSubclassOf(self::class)) {
                // Target controller extends NexaController - pass required dependencies
                $instance = new $controllerClass($this->template, $this->deviceLayouts);
            } else {
                // Regular controller - instantiate normally
                $instance = new $controllerClass();
            }

            return $instance;

        } catch (Exception $e) {
            throw new Exception("Failed to instantiate controller '{$controllerClass}': " . $e->getMessage());
        }
    }

    
    public function userid(){
        return $this->session->getUserId();
    }


    /**
     * Prepare parameters for target controller (NexaNode style)
     * 
     * @param string $page Page name
     * @param array $params Route parameters
     * @return array Prepared parameters
     */
    private function prepareNodeApiControllerParams(string $page, array $params): array
    {
        $controllerParams =  $params;

        // Add request parameters
        if (isset($params['params'])) {
            $controllerParams['params'] = $params['params'];
        }

        if (isset($params['method'])) {
            $controllerParams['requested_method'] = $params['method'];
        }

        // Add individual parameters (param_0, param_1, etc.)
        foreach ($params as $key => $value) {
            if (strpos($key, 'param_') === 0 || strpos($key, '_') !== 0) {
                $controllerParams[$key] = $value;
            }
        }
       
        $redID = $this->getUser();
        
        // Get slug array and filter slugs after slug1
        $slugArray = $this->getSlugArray();
        $lastSlug = '';
        $filteredSlug = [];
        
        // Keep all slugs starting from slug2
        foreach ($slugArray as $key => $value) {
            if (preg_match('/^slug([2-9]|\d{2,})$/', $key)) {
                $filteredSlug[$key] = $value;
                // Use the first found slug after slug1 as resource
                if (empty($lastSlug)) {
                    $lastSlug = $value;
                }
            }
        }
        
        // Add current request context
        $controllerParams= [
            'endpoints' => $this->getPath(),
            'resource' => $this->getSlug(1),
            'last_slug' => $filteredSlug,  // Include all slugs after slug1
            // 'uid' => $redID['user_id']??'',
            // 'uid_user' => $redID['user']??'',
            // 'is_logged_in' => $this->isLoggedIn(),
        ];

        return $controllerParams;
    }
    /**
     * Prepare parameters for target controller (NexaNode style)
     * 
     * @param string $page Page name
     * @param array $params Route parameters
     * @return array Prepared parameters
     */
    private function prepareNodeControllerParams(string $page, array $params): array
    {
        $controllerParams = [
            'page' => $page,
            'username' => $params['username'] ?? '',
            'calling_controller' => get_class($this),
            //'template_instance' => $this->template,
        ];

        // Add request parameters
        if (isset($params['params'])) {
            $controllerParams['params'] = $params['params'];
        }

        if (isset($params['method'])) {
            $controllerParams['requested_method'] = $params['method'];
        }

        // Add individual parameters (param_0, param_1, etc.)
        $customParams = [];
        foreach ($params as $key => $value) {
            if (strpos($key, 'param_') === 0 || strpos($key, '_') !== 0) {
                $customParams[$key] = $value;
            }
        }
        $resource_id = $this->getDecodedSlug(3, null);
        $resource_id2 = $this->getDecodedSlug(4, null);
        $redID=$this->getUser();
        // Add current request context
        $controllerParams= [
            'base_url' => $this->getBaseUrl(),
            'assets' => $this->getBaseUrl().'/assets/drive',
            'page_home'=> rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0), '/'),
            'page_index'=> rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0) . '/' . $this->getSlug(1), '/'),
            'projectName' => $this->getProjectName(),
            'current_url' => $this->getCurrentUrl(),
            'resource_id' =>$resource_id,
            'res_id' =>$resource_id2,
            'mapping' =>$this->getSlug(2,''),
            'current_path' => $this->getPath(),
            ...$this->getSlugArray() ,
            'uid' => $redID['user_id']??'',
            'uid_user' => $redID['user']??'',
            //... $this->getUser(),
            'is_logged_in' => $this->isLoggedIn(),
            ...$customParams, // FIXED: Merge custom params at the end
        ];
        return $controllerParams;
    }
    protected function drive(string $Name)
    {
        return dirname(__DIR__).'/assets/drive/'.$Name;
    }
    /**
     * Enhanced node controller with method chaining and fluent interface
     * 
     * @param string $controllerName Controller name
     * @return NodeControllerProxy Proxy object for fluent interface
     */
    protected function callController(string $controllerName): NodeControllerProxy
    {
        return new NodeControllerProxy($this, $controllerName);
    }

    /**
     * Route to multiple controllers in sequence
     * 
     * @param array $controllers Array of controller configurations
     * @return array Results from all controllers
     */
    protected function nodeControllers(array $controllers): array
    {
        $results = [];
        
        foreach ($controllers as $config) {
            if (is_string($config)) {
                // Simple controller name
                $results[] = $this->nodeController($config);
            } elseif (is_array($config)) {
                // Configuration array
                $controllerName = $config['controller'] ?? $config['name'] ?? '';
                $params = $config['params'] ?? [];
                $namespace = $config['namespace'] ?? null;
                
                if (!empty($controllerName)) {
                    $results[] = $this->nodeController($controllerName, $params, $namespace);
                }
            }
        }
        
        return $results;
    }

    /**
     * Check if controller exists
     * 
     * @param string $controllerName Controller name
     * @param string|null $namespace Optional namespace
     * @return bool
     */
    protected function controllerExists(string $controllerName, string $namespace = null): bool
    {
        $controllerClass = ucfirst(strtolower($controllerName)) . 'Controller';
        $fullClass = ($namespace ?? $this->baseControllerNamespace) . $controllerClass;
        
        return class_exists($fullClass);
    }



    protected function controllerExistsData(string $controllerName, string $namespace = null): bool
    {
        $controllerClass = ucfirst(strtolower($controllerName)) . 'Controller';
        $fullClass = ($namespace ?? $this->baseControllerNamespace) . $controllerClass;

        return class_exists($fullClass);
    }


    /**
     * Get available controllers in namespace
     * 
     * @param string|null $namespace Optional namespace
     * @return array Available controller names
     */
    protected function getAvailableControllers(string $namespace = null): array
    {
        $namespace = $namespace ?? $this->baseControllerNamespace;
        $controllers = [];
        
        // Convert namespace to directory path
        $path = str_replace('\\', '/', $namespace);
        $path = str_replace('App/', 'app/', $path);
        
        if (is_dir($path)) {
            $files = glob($path . '/*Controller.php');
            foreach ($files as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                $controllerName = str_replace('Controller', '', $className);
                $controllers[] = strtolower($controllerName);
            }
        }
        
        return $controllers;
    }

    /**
     * Set base controller namespace
     * 
     * @param string $namespace Base namespace for controllers
     * @return self
     */
    protected function setControllerNamespace(string $namespace): self
    {
        $this->baseControllerNamespace = rtrim($namespace, '\\') . '\\';
        return $this;
    }



    /**
     * Debug controller routing information
     * 
     * @param string $controllerName Controller name to debug
     * @param array $params Optional parameters
     * @return array Debug information
     */
    protected function debugNodeController(string $controllerName, array $params = []): array
    {
        $controllerClass = ucfirst(strtolower($controllerName)) . 'Controller';
        $fullClass = $this->baseControllerNamespace . $controllerClass;
        
        $debugInfo = [
            'controller_name' => $controllerName,
            'controller_class' => $controllerClass,
            'full_class_name' => $fullClass,
            'class_exists' => class_exists($fullClass),
            'base_namespace' => $this->baseControllerNamespace,
            'cache_enabled' => $this->cacheControllerInstances,
            'cached_controllers' => array_keys(self::$controllerInstances),
            'available_controllers' => $this->getAvailableControllers(),
            'params' => $params,
        ];

        if (class_exists($fullClass)) {
            try {
                $reflection = new \ReflectionClass($fullClass);
                $debugInfo['is_nexacontroller_subclass'] = $reflection->isSubclassOf(self::class);
                $debugInfo['public_methods'] = array_filter(
                    get_class_methods($fullClass),
                    function($method) {
                        return !str_starts_with($method, '_') && !str_starts_with($method, '__');
                    }
                );
            } catch (\Exception $e) {
                $debugInfo['reflection_error'] = $e->getMessage();
            }
        }

        return $debugInfo;
    }

    /**
     * Render debug information for node controller
     * 
     * @param string $controllerName Controller name to debug
     * @param array $params Optional parameters
     * @return void
     */
    protected function debugNodeControllerRender(string $controllerName, array $params = []): void
    {
        $debugInfo = $this->debugNodeController($controllerName, $params);
        $this->Raw($debugInfo);
    }





    protected function routerAction(array $params= [],array $variable = []){
        $result = [];
        // Build action URLs
        foreach ($variable as $key => $value) {
            $result[$key] = $this->getBaseUrl().'/'.$this->getSlug(0).'/'.$this->getSlug(1) . '/' . $value;
        }
        return [
            'action' =>array(
               ...$result
            ) 
        ];
    }





    
    // ========================================================================
    // NEXAUI COMPONENTS INTEGRATION - Magic Method Access
    // ========================================================================
    
    /**
     * REMOVED: UI Component Cache - using direct factory pattern instead
     */
    
    /**
     * Magic method untuk mengakses property instances secara lazy
     * Memungkinkan akses langsung ke $this->request, $this->session, dll
     * 
     * Usage:
     * $url = $this->request->getCurrentUrl();
     * $user = $this->session->getUser();
     */
    public function __get(string $name)
    {
        try {
            switch ($name) {
                case 'request':
                    if ($this->request === null) {
                        $this->request = $this->getRequest();
                    }
                    return $this->request;
                    
                case 'session':
                    // Session should already be initialized in init()
                    if ($this->session === null) {
                        $this->session = $this->getSession();
                    }
                    return $this->session;
                    
                case 'agent':
                    if ($this->agent === null) {
                        $this->agent = $this->getAgent();
                    }
                    return $this->agent;

                case 'auth':
                    return $this->_lazyAuth ??= new NexaAuth();
                case 'Strings':
                    return $this->_lazyStrings ??= new NexaStrings();
                case 'Download':
                    return $this->_lazyDownload ??= new NexaDownload();
                case 'Markdown':
                    return $this->_lazyMarkdown ??= new NexaMarkdown();
                case 'array':
                    return $this->_lazyArray ??= new NexaArray();
                case 'Encrypt':
                    return $this->_lazyEncrypt ??= new NexaEncrypt();
                case 'Crypto':
                    return $this->_lazyCrypto ??= new NexaCrypto();

                default:
                    throw new \BadMethodCallException("Property '{$name}' not found in " . get_class($this));
            }
        } catch (\Exception $e) {
            // Log the error and re-throw with more context
            error_log("NexaController::__get() error for property '$name': " . $e->getMessage());
            throw new \Exception("Failed to access property '{$name}' in " . get_class($this) . ": " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Magic method untuk mengecek apakah property tersedia
     * 
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'request', 'session', 'agent',
            'auth', 'Strings', 'Download', 'Markdown', 'array', 'Encrypt', 'Crypto',
        ], true);
    }

    /**
     * Kompatibel dengan kode yang mem-assign $this->Encrypt dll. (ForgeController, OutsideController)
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'auth' => $this->_lazyAuth = $value instanceof NexaAuth ? $value : null,
            'Strings' => $this->_lazyStrings = $value instanceof NexaStrings ? $value : null,
            'Download' => $this->_lazyDownload = $value instanceof NexaDownload ? $value : null,
            'Markdown' => $this->_lazyMarkdown = $value instanceof NexaMarkdown ? $value : null,
            'array' => $this->_lazyArray = $value instanceof NexaArray ? $value : null,
            'Encrypt' => $this->_lazyEncrypt = $value instanceof NexaEncrypt ? $value : null,
            'Crypto' => $this->_lazyCrypto = $value instanceof NexaCrypto ? $value : null,
            default => throw new \BadMethodCallException("Property '{$name}' cannot be set on " . get_class($this)),
        };
    }
    
    /**
     * Magic method untuk mengakses NexaUI components
     * Hanya aktif untuk method yang dimulai dengan "Nexa" untuk menghindari konflik
     * 
     * Usage:
     * $pagination = $this->NexaPagination();
     * $avatar = $this->NexaAvatar();
     * $spinner = $this->NexaSpinner();
     */
    public function __call(string $name, array $arguments)
    {
        // ✅ DEBUG: Log magic method call
       // error_log("NexaController::__call() invoked with method: '$name'");
        
        // ✅ HANYA proses jika dimulai dengan "Nexa" (lebih efisien)
        if (str_starts_with($name, 'Nexa')) {
            $componentName = strtolower(str_replace('Nexa', '', $name));
            //error_log("Processing UI component: '$componentName' from method '$name'");
            
            // DIRECT FACTORY PATTERN - No cache, fresh component every time
            if (\App\System\Components\NexaUI::has($componentName)) {
                // Direct component creation - simple and reliable
                return \App\System\Components\NexaUI::get($componentName);
            }
            
            // Jika Nexa* tapi component tidak ada
            //error_log("ERROR: UI Component '$componentName' not found in NexaUI");
            throw new \BadMethodCallException("UI Component '{$componentName}' not found in NexaUI");
        }
        
        // ✅ Untuk method non-Nexa, langsung throw exception (tidak memproses)
        //error_log("ERROR: Method '$name' not found in class " . get_class($this));
        throw new \BadMethodCallException("Method '{$name}' not found in " . get_class($this));
    }
    
    /**
     * REMOVED: clearUICache() - no longer needed since we don't use cache
     */
    
    protected function secretKey($Key='nexaui2025'){
        $nexaDecode = new NexaDecode($Key);
        return $nexaDecode;
    }

    /**
     * Get Firebase instance
     * 
     * @return NexaFirebase Firebase instance
     */
    protected function getFirebase(): NexaFirebase
    {
        return $this->Firebase ??= new NexaFirebase();
    }

    protected function indexPagin(array $params = [],$Slug=3,$Slug1=1){
        if ($this->getSlug(2,'')) {
            if ($this->getSlug(2) =='pages') {
               $Render=$params['page_index'].'/pages/';
            } else {
               $Render=$params['page_home'].$this->proSlug($Slug,$Slug1).'/&pages/';
            }
        } else {
           $Render=$params['page_index'].'/pages/';
        }
        return $Render;
    }
    // BATAS
    /**
     * Enhanced log method - Display or Clear logs based on parameter
     * Simple API untuk developer: satu metode untuk dua fungsi
     * 
     * @param bool|null $clearLogs If true: clear logs, if false/null: display logs
     * @param array $options Options untuk clearing (optional)
     * @return array|string Returns log data (display) or clear result (clear)
     * 
     * Usage:
     * $this->log();                        // Display today's log
     * $this->log(false);                   // Display today's log (explicit)
     * $this->log(true);                    // Clear ALL logs including today
     * $this->log(true, ['keep_today' => true]); // Clear logs but keep today
     * $this->log(true, ['days' => 7]);     // Clear logs older than 7 days
     */
    protected function log($clearLogs = false, array $options = [])
    {
        // ✅ CLEAR LOGS MODE
        if ($clearLogs === true) {
            // Parse options untuk clearing
            $keepCurrentDay = $options['keep_today'] ?? false;  // Default: hapus semua termasuk hari ini
            $days = $options['days'] ?? null;
            
            if ($days !== null) {
                // Clear old logs by days
                $result = $this->clearOldLogs($days);
            } else {
                // Clear all logs (default: hapus semua termasuk hari ini)
                $result = $this->clearAllLogs($keepCurrentDay);
            }
           
            
            return $result;
        }
        
        // ✅ DISPLAY LOGS MODE (default behavior)
        $date = date('Y-m-d');
        $appPath = __DIR__."/log/error_{$date}.json";
        
        // Check if log file exists
        if (!file_exists($appPath)) {
            
            return ['message' => 'No log file found for today', 'file' => $appPath];
        }
        
        $jsonData = file_get_contents($appPath);
        $data = json_decode($jsonData, true);
        
        // Handle empty or invalid JSON
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            // echo "⚠️ Log file exists but contains invalid JSON: " . json_last_error_msg() . "\n";
            return ['message' => 'Invalid JSON in log file', 'error' => json_last_error_msg()];
        }
        
      
        $output = NexaJsonViewer::renderJson($data, $this->getBaseUrl(), [
            'class' => 'container2'.uniqid(),
            'title' => "Log Entries for $date (" . count($data) . " entries)",
            'id' => 'json-viewer-' . uniqid()
        ]);
        echo $output;
        return $data;
    }
    /**
     * Dump data using JSON viewer with auto-merge support
     * Supports multiple arrays/objects as parameters and merges them automatically
     * Now supports SafeParamsArray and other array-like objects
     * 
     * @param mixed ...$data Multiple arrays/objects to dump (variadic parameters)
     * 
     * Usage:
     * $this->dump($params);                        // Single array
     * $this->dump($params, $paramsID);             // Multiple arrays (auto-merge)
     * $this->dump($params, $paramsID, $extraData); // Multiple arrays (auto-merge)
     * $this->dump($params, $safeParamsArray);      // Works with SafeParamsArray
     */
    protected function dump(...$data): never
    {
        // If no parameters provided, use empty array
        if (empty($data)) {
            $data = [[]];
        }
        
        // Convert all data to arrays and merge
        $mergedParams = [];
        $convertedCount = 0;
        
        foreach ($data as $item) {
            $convertedCount++;
            
            // Handle different data types
            if (is_array($item)) {
                // Regular array
                $mergedParams = array_merge($mergedParams, $item);
            } elseif ($item instanceof SafeParamsArray) {
                // SafeParamsArray - convert to regular array
                $mergedParams = array_merge($mergedParams, $item->toArray());
            } elseif (is_object($item) && method_exists($item, 'toArray')) {
                // Object with toArray method
                $mergedParams = array_merge($mergedParams, $item->toArray());
            } elseif (is_object($item)) {
                // Regular object - convert to array
                $mergedParams = array_merge($mergedParams, (array) $item);
            } else {
                // Primitive types - wrap in array with index
                $mergedParams['item_' . $convertedCount] = $item;
            }
        }
        
        $output = NexaJsonViewer::renderJson($mergedParams, $this->getBaseUrl(), [
            'class' => 'container1'.uniqid(),
            'title' => 'Data Dump - JSON View' . (count($data) > 1 ? ' (Merged ' . count($data) . ' items)' : ''),
            'id' => 'json-viewer-' . uniqid()
        ]);
        echo $output;
        exit;
    }

    /**
     * Tampilkan data debug dalam format JSON rapi (tanpa dependensi eksternal)
     * Fallback ketika NexaJsonViewer/assets tidak tersedia
     */
    protected function debugPrint(mixed $data, string $title = 'Debug Output'): never
    {
        $arr = is_object($data) && method_exists($data, 'toArray')
            ? $data->toArray()
            : (is_array($data) ? $data : (array) $data);
        $json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Debug</title>';
        echo '<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;margin:0;}';
        echo 'pre{background:#252526;padding:16px;border-radius:8px;overflow:auto;white-space:pre-wrap;word-wrap:break-word;}';
        echo '</style></head><body>';
        echo '<h2 style="color:#4ec9b0">' . htmlspecialchars($title) . '</h2>';
        echo '<pre>' . htmlspecialchars($json) . '</pre></body></html>';
        exit;
    }
 // ========================================================================
    // UNIFIED DATA & AJAX RESPONSE HANDLING
    // ========================================================================

    /**
     * Universal method untuk set data dan handle AJAX response secara otomatis
     * Menggabungkan setData() dan AJAX JSON response dalam satu method
     * Now supports SafeParamsArray and other array-like objects
     * 
     * LOGIC:
     * - Jika AJAX request: Set data + return JSON response dan exit
     * - Jika normal request: Hanya set data untuk view
     * 
     * @param mixed $data Data untuk view dan/atau AJAX response (array, SafeParamsArray, etc.)
     * @param int $statusCode HTTP status code untuk AJAX response (default: 200)
     * @param bool $forceJson Force JSON response bahkan untuk non-AJAX request
     * @return void
     */
    protected function jsData($data, int $statusCode = 200, bool $forceJson = false): void
    {
        // Convert data to array if needed
        if ($data instanceof SafeParamsArray) {
            $arrayData = $data->toArray();
        } elseif (is_object($data) && method_exists($data, 'toArray')) {
            $arrayData = $data->toArray();
        } elseif (is_array($data)) {
            $arrayData = $data;
        } else {
            // Wrap primitive types
            $arrayData = ['data' => $data];
        }
        
        // ✅ ALWAYS SET DATA untuk view (untuk non-AJAX requests)
        $this->setData($arrayData);
        
        // ✅ AUTO-DETECT AJAX atau forced JSON
        if ($this->isAjax() || $forceJson) {
            // Return JSON response dan exit
            $this->json($arrayData, $statusCode);
        }
        
        // For non-AJAX requests, data is already set for view rendering
    }

    /**
     * Simplified jsData dengan parameter yang dapat dikonfigurasi
     * Versi yang lebih mudah untuk kustomisasi variabel
     * 
     * @param array $config Configuration array dengan struktur:
     * [
     *   'success' => bool,           // Status success/error
     *   'message' => string,         // Pesan untuk user
     *   'errors' => array,           // Validation errors (optional)
     *   'data' => array,             // Data tambahan (optional)
     *   'redirect' => string,        // URL redirect untuk AJAX (optional)
     *   'statusCode' => int,         // HTTP status code (default: 200/422)
     *   'forceJson' => bool          // Force JSON response (default: false)
     * ]
     * @return void
     */
    protected function jsResponse(array $config): void
    {
        // Default values
        $defaults = [
            'success' => true,
            'message' => '',
            'errors' => [],
            'data' => [],
            'redirect' => null,
            'statusCode' => null,
            'forceJson' => false
        ];
        
        // Merge config dengan defaults
        $config = array_merge($defaults, $config);
        
        // Auto-determine status code jika tidak diset
        if ($config['statusCode'] === null) {
            $config['statusCode'] = $config['success'] ? 200 : 422;
        }
        
        // Build response data
        $responseData = [
            'success' => $config['success'],
            'message' => $config['message']
        ];
        
        // Tambahkan errors jika ada
        if (!empty($config['errors'])) {
            $responseData['errors'] = $config['errors'];
            
            // Tambahkan individual field errors
            foreach ($config['errors'] as $key => $value) {
                if (strpos($key, 'errors_') === 0) {
                    $responseData[$key] = $value;
                }
            }
            
            // Tambahkan old input values untuk form repopulation
            $postData = $this->getPostValue();
            if (!empty($postData)) {
                foreach ($postData as $field => $value) {
                    if (strpos(strtolower($field), 'password') === false) {
                        $responseData[$field] = $value;
                    } else {
                        $responseData[$field] = ''; // Clear password fields
                    }
                }
            }
        }
        
        // Tambahkan redirect jika ada
        if ($config['redirect'] !== null) {
            $responseData['redirect'] = $config['redirect'];
        }
        
        // Tambahkan data tambahan
        if (!empty($config['data'])) {
            $responseData = array_merge($responseData, $config['data']);
        }
        
        // Execute jsData
        $this->jsData($responseData, $config['statusCode'], $config['forceJson']);
    }

    /**
     * jsData khusus untuk validation errors using NexaAjax
     * Optimized untuk handling validation dengan struktur data yang konsisten
     * 
     * @param array $errors Validation errors dari getErrors()
     * @param string $message General error message
     * @param array $additionalData Additional data untuk view/response
     * @return void
     */
    protected function jsValidationError(array $errors, string $message = 'Validation failed', array $additionalData = []): void
    {
        if ($this->isAjax()) {
            // For AJAX requests, use NexaAjax validation error response
            NexaAjax::validationError($errors, $message);
        } else {
            // For normal requests, use existing jsData logic
            $data = array_merge([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $additionalData);
            
            // Tambahkan individual field errors untuk kemudahan akses di view
            foreach ($errors as $key => $value) {
                if (strpos($key, 'errors_') === 0) {
                    $data[$key] = $value;
                }
            }
            
            // Tambahkan old input values untuk form repopulation
            $postData = $this->getPostValue();
            if (!empty($postData)) {
                foreach ($postData as $field => $value) {
                    // Jangan override password fields untuk security
                    if (strpos(strtolower($field), 'password') === false) {
                        $data[$field] = $value;
                    } else {
                        $data[$field] = ''; // Clear password fields
                    }
                }
            }
            
            $this->jsData($data, 422); // 422 Unprocessable Entity untuk validation errors
        }
    }

    /**
     * jsData khusus untuk success response using NexaAjax
     * Optimized untuk handling success dengan struktur data yang konsisten
     * 
     * @param string $message Success message
     * @param string|null $redirect Redirect URL untuk AJAX (optional)
     * @param array $additionalData Additional data untuk view/response
     * @return void
     */
    protected function jsSuccess(string $message = 'Operation successful', string $redirect = null, array $additionalData = []): void
    {
        if ($this->isAjax()) {
            // For AJAX requests, use NexaAjax success response
            $data = $additionalData;
            if ($redirect !== null) {
                $data['redirect'] = $redirect;
            }
            NexaAjax::success($message, $data);
        } else {
            // For normal requests, use existing jsData logic
            $data = array_merge([
                'success' => true,
                'message' => $message,
            ], $additionalData);
            
            if ($redirect !== null) {
                $data['redirect'] = $redirect;
            }
            
            $this->jsData($data, 200);
        }
    }

    /**
     * jsData dengan auto-redirect untuk normal requests
     * Untuk AJAX: return JSON, untuk normal: redirect dengan flash message
     * 
     * @param array $jsonData Data untuk AJAX response
     * @param string $redirectUrl URL untuk redirect normal request
     * @param string $flashMessage Flash message untuk normal request
     * @param string $flashType Flash message type (success, error, info, warning)
     * @return void
     */
    protected function jsRedirect(array $jsonData, string $redirectUrl, string $flashMessage = '', string $flashType = 'success'): void
    {
        if ($this->isAjax()) {
            // AJAX request: return JSON
            $this->json($jsonData);
        } else {
            // Normal request: flash message dan redirect
            if (!empty($flashMessage)) {
                $this->session->setFlash($flashType, $flashMessage);
            }
            $this->redirect($redirectUrl);
        }
    }

    /**
     * Enhanced version dengan validation support
     * Khusus untuk form handling dengan validation
     * 
     * @param bool $isValid Validation result
     * @param array $errors Validation errors (jika ada)
     * @param array $successData Data untuk success case
     * @param array $errorData Data untuk error case
     * @param string $successMessage Success message
     * @param string $errorMessage Error message
     * @return void
     */
    protected function jsValidationResponse(
        bool $isValid, 
        array $errors = [], 
        array $successData = [], 
        array $errorData = [],
        string $successMessage = 'Data berhasil diproses',
        string $errorMessage = 'Please fix the errors below'
    ): void 
    {
        if ($isValid) {
            // Validation SUCCESS
            $data = array_merge([
                'success' => true,
                'message' => $successMessage,
            ], $successData);
            
            $this->jsData($data, 200);
        } else {
            // Validation FAILED
            $this->jsValidationError($errors, $errorMessage, $errorData);
        }
    }

    /**
     * Simple boolean response dengan pesan
     * Untuk operasi yang hanya perlu success/fail response
     * 
     * @param bool $success Operation success status
     * @param string $successMessage Message untuk success
     * @param string $errorMessage Message untuk error
     * @param array $additionalData Additional data
     * @return void
     */
    protected function jsBooleanResponse(
        bool $success, 
        string $successMessage = 'Operation successful', 
        string $errorMessage = 'Operation failed',
        array $additionalData = []
    ): void 
    {
        $data = array_merge([
            'success' => $success,
            'message' => $success ? $successMessage : $errorMessage,
        ], $additionalData);
        
        $statusCode = $success ? 200 : 400;
        $this->jsData($data, $statusCode);
    }

    /**
     * Method untuk debugging jsData
     * Menampilkan data yang akan dikirim ke view/JSON
     * 
     * @param array $data Data to debug
     * @param bool $exit Whether to exit after debugging
     * @return void
     */
    protected function debugJsData(array $data, bool $exit = true): void
    {
        $debugInfo = [
            'Is AJAX Request' => $this->isAjax() ? 'YES' : 'NO',
            'Request Method' => $this->getRequestMethod(),
            'User Agent' => $this->getUserAgent(),
            'Accept Header' => $this->getHeader('Accept', 'Not Set'),
            'Content Type' => $this->getHeader('Content-Type', 'Not Set'),
            'Data to Send' => $data,
            'Data Count' => count($data),
            'Data Keys' => array_keys($data),
            'Timestamp' => date('Y-m-d H:i:s'),
        ];
        
        echo "<div style='background: #e3f2fd; padding: 20px; margin: 15px; border: 2px solid #2196f3; border-radius: 8px; font-family: monospace;'>";
        echo "<h3 style='color: #1976d2; margin: 0 0 15px 0;'>🔧 JS-DATA DEBUG INFORMATION</h3>";
        
        foreach ($debugInfo as $key => $value) {
            echo "<div style='margin: 8px 0;'>";
            echo "<strong style='color: #333;'>{$key}:</strong> ";
            
            if (is_array($value)) {
                echo "<pre style='background: #f5f5f5; padding: 8px; margin: 5px 0; border-radius: 3px; overflow-x: auto;'>";
                echo htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT));
                echo "</pre>";
            } else {
                echo "<span style='color: #1976d2;'>" . htmlspecialchars((string)$value) . "</span>";
            }
            echo "</div>";
        }
        
        echo "</div>";
        
        if ($exit) {
            exit;
        }
    }

  protected function useVisitor(array $params = []): void
  {
    $this->useModels('Role/Visitor', 'Agen', [
            $this->session->getVisitorId(),
            $this->getBrowserInfo(),
            $params['link'] ?? $this->getPath(),
            $params['title'],
            $params['mode'],
        ]); 
   }


    /**
     * Flexible getter untuk mengambil data dari array.
     * 
     * @param array $data   Array sumber data
     * @param string $key   Key yang ingin diambil
     * @param bool $single  Jika true dan value array numerik, return elemen pertama
     * @return mixed
     */
    protected  function accessorData($data, $key, $exclude = false)
    {
        if (isset($data[$key])) {
            $value = $data[$key];
            if ($exclude && is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                return $value[0];
            }
            return $value;
        }
        return $data;
    }


// BATAS
    // ========================================================================
    // MODELS INTEGRATION - Dynamic Model Loading via NexaState
    // ========================================================================
    
    /**
     * Load model dari folder models dan call method tertentu
     * Integrated dengan NexaState untuk caching dan controller injection
     * 
     * @param string $modelPath Path model (e.g., 'User', 'Access/Akun', 'Domain/Product')
     * @param string $method Method name to call
     * @param array $params Parameters untuk method
     * @return mixed Result dari method call
     * @throws Exception
     * 
     * Usage dalam controller:
     * $users = $this->useModels('User', 'getActiveUsers');
     * $user = $this->useModels('User', 'findByEmail', ['john@example.com']);
     * $account = $this->useModels('Access/Akun', 'getAccount', [123]);
     * $login = $this->useModels('User', 'login', ['email@example.com', 'password']);
     */
    protected function useModels(string $modelPath, string $method, array $params = []): mixed
    {
        // Create NexaState instance for model management
        $state = new NexaState('temp_model_call_' . uniqid(), null);
        
        // Call useModels dengan controller injection
        return $state->useModels($modelPath, $method, $params, $this);
    }
    
    protected function useData(string $dataPath, string $method, array $params = []): mixed
    {
        // Langsung gunakan NexaState::callData agar konsisten
        return NexaState::callData($dataPath, $method, $params, $this);
    }

    
    protected function useParams(string $dataPath, string $method, array $params = []): mixed
    {
        // Langsung gunakan NexaState::callData agar konsisten
        return NexaState::callParams($dataPath, $method, $params, $this);
    }

    /**
     * Get available models (untuk debugging/discovery)
     * 
     * @param string $subfolder Optional subfolder
     * @return array Available models
     */
    protected function getAvailableModels(string $subfolder = ''): array
    {
        $state = new NexaState('temp_models_list', null);
        return $state->getAvailableModels($subfolder);
    }


        /**
     * Smart query parameters getter with auto-filtering and safe access
     * Returns SafeParamsArray that automatically provides '' for missing keys
     * 
     * @param array|string|null $keys Optional keys to filter
     * @param bool $safeMode Return SafeParamsArray wrapper (default: true)
     * @return SafeParamsArray|array|string|null Safe parameters or specific value
     * 
     * Usage patterns:
     * $params = $this->paramsKeys();           // Get safe array (auto '' for missing keys)
     * $params['search'];                       // Returns value or '' (no error!)
     * $params['missing'];                      // Returns '' (no error!)
     * 
     * $this->paramsKeys(['search', 'page'])    // Get safe filtered array
     * $this->paramsKeys('search')              // Get single key value or ''
     * $this->paramsKeys(null, false)           // Get raw array (old behavior)
     */
    protected function paramsKeys($keys = null, bool $safeMode = true)
    {
        // Get all query parameters
        $allParams = $this->getQueryParams();
        
        // Pattern 1: No parameters - return all query params
        if ($keys === null) {
            return $safeMode ? new SafeParamsArray($allParams) : $allParams;
        }
        
        // Pattern 2: String key - return single value with safety
        if (is_string($keys)) {
            return $allParams[$keys] ?? '';
        }
        
        // Pattern 3: Array of keys - return filtered array with only specified keys
        if (is_array($keys)) {
            $filteredParams = [];
            foreach ($keys as $key) {
                if (isset($allParams[$key])) {
                    $filteredParams[$key] = $allParams[$key];
                }
            }
            return $safeMode ? new SafeParamsArray($filteredParams) : $filteredParams;
        }
        
        // Fallback: return all params with safety
        return $safeMode ? new SafeParamsArray($allParams) : $allParams;
    }
     
     /**
      * Get multiple query parameters with default values
      * Enhanced version with default value support for missing keys
      * 
      * @param array $keysWithDefaults Array of key => default_value pairs
      * @return array Parameters with defaults applied
      * 
      * Usage:
      * $params = $this->paramsWithDefaults([
      *     'search' => '',
      *     'page' => 1,
      *     'limit' => 10
      * ]);
      */
     protected function paramsWithDefaults(array $keysWithDefaults): array
     {
         $allParams = $this->getQueryParams();
         $result = [];
         
         foreach ($keysWithDefaults as $key => $defaultValue) {
             $result[$key] = $allParams[$key] ?? $defaultValue;
         }
         
         return $result;
     }
     
     /**
      * Check if specific query parameters exist
      * 
      * @param array|string $keys Keys to check
      * @return bool|array True/false for single key, or array of key => bool for multiple keys
      * 
      * Usage:
      * $hasSearch = $this->hasParams('search');           // Returns bool
      * $checks = $this->hasParams(['search', 'page']);    // Returns ['search' => true, 'page' => false]
      */
     protected function hasParams($keys)
     {
         $allParams = $this->getQueryParams();
         
         if (is_string($keys)) {
             return isset($allParams[$keys]);
         }
         
         if (is_array($keys)) {
             $result = [];
             foreach ($keys as $key) {
                 $result[$key] = isset($allParams[$key]);
             }
             return $result;
         }
         
         return false;
     }
     
     /**
      * Get non-empty query parameters only
      * Filters out empty strings, null values, and empty arrays
      * 
      * @param array|null $keys Optional keys to filter
      * @return array Non-empty parameters
      * 
      * Usage:
      * $nonEmpty = $this->nonEmptyParams();              // All non-empty params
      * $filtered = $this->nonEmptyParams(['search', 'category']); // Specific non-empty params
      */
     protected function nonEmptyParams(?array $keys = null): array
     {
         $params = $keys ? $this->paramsKeys($keys) : $this->paramsKeys();
         
         return array_filter($params, function($value) {
             return !empty($value) || $value === '0' || $value === 0;
         });
     }
     
     /**
      * Get safe query parameters that return empty string for missing keys
      * Creates an array-like object that returns '' for undefined keys instead of errors
      * 
      * @param array|null $keys Optional keys to include
      * @return SafeParamsArray Safe array wrapper
      * 
      * Usage:
      * $params = $this->safeParams();
      * echo $params['search'];  // Returns actual value or ''
      * echo $params['missing']; // Returns '' instead of error
      */
     protected function safeParams(?array $keys = null): SafeParamsArray
     {
         $params = $keys ? $this->paramsKeys($keys) : $this->paramsKeys();
         return new SafeParamsArray($params);
     }
     
     /**
      * Get single parameter value safely with default
      * 
      * @param string $key Parameter key
      * @param string $default Default value (empty string by default)
      * @return string Parameter value or default
      * 
      * Usage:
      * $search = $this->safeParam('search');        // Returns value or ''
      * $page = $this->safeParam('page', '1');       // Returns value or '1'
      */
     protected function safeParam(string $key, string $default = ''): string
     {
         $params = $this->getQueryParams();
         return (string) ($params[$key] ?? $default);
     }
     
     /**
      * Alternative paramsKeys with safe access behavior (DEPRECATED - use paramsKeys directly)
      * This method is now redundant as paramsKeys() has built-in safe mode
      * 
      * @param array|string|null $keys Optional keys to filter
      * @return SafeParamsArray|string Safe parameters
      * 
      * @deprecated Use $this->paramsKeys() directly (safe by default)
      */
     protected function paramsKeysSafe($keys = null)
     {
         // Redirect to main paramsKeys method
         return $this->paramsKeys($keys, true);
     }
     
     /**
      * Get raw (unsafe) parameters - for backward compatibility
      * Use this only if you need the old behavior without SafeParamsArray wrapper
      * 
      * @param array|string|null $keys Optional keys to filter
      * @return array|string|null Raw parameters (may cause undefined index errors)
      * 
      * Usage:
      * $params = $this->paramsKeysRaw();        // Raw array (old behavior)
      * $search = $this->paramsKeysRaw('search'); // Raw single value
      */
     protected function paramsKeysRaw($keys = null)
     {
         return $this->paramsKeys($keys, false);
     }
     
     /**
      * Debug SafeParamsArray functionality
      * Shows information about SafeParamsArray behavior and contents
      * 
      * @param SafeParamsArray|null $safeParams Optional SafeParamsArray to debug
      * @return void
      */
     protected function debugSafeParams(?SafeParamsArray $safeParams = null): void
     {
         if ($safeParams === null) {
             $safeParams = $this->paramsKeys();
         }
         
         $debugInfo = [
             'type' => get_class($safeParams),
             'count' => $safeParams->count(),
             'isEmpty' => $safeParams->isEmpty(),
             'keys' => $safeParams->keys(),
             'values' => $safeParams->values(),
             'raw_data' => $safeParams->toArray(),
             'sample_access' => [
                 'existing_key' => $safeParams['search'] ?? 'No search param',
                 'missing_key' => $safeParams['nonexistent'],
                 'custom_default' => $safeParams->get('missing', 'custom_default')
             ]
         ];
         
         echo "<div style='padding: 15px; background: #e1f5fe; border: 2px solid #0288d1; margin: 10px 0; border-radius: 5px;'>";
         echo "<h4 style='color: #01579b; margin: 0 0 10px 0;'>🔍 SafeParamsArray Debug Information</h4>";
         
         $this->Raw($debugInfo);
         echo "</div>";
     }
     
     /**
      * Test SafeParamsArray behavior with various access patterns
      * Useful for development and testing
      * 
      * @return void
      */
     protected function testSafeParams(): void
     {
         $params = $this->paramsKeys();
         
         echo "<div style='padding: 15px; background: #f3e5f5; border: 2px solid #9c27b0; margin: 10px 0; border-radius: 5px;'>";
         echo "<h4 style='color: #7b1fa2; margin: 0 0 10px 0;'>🧪 SafeParamsArray Testing</h4>";
         
         echo "<h5>Safe Access Test:</h5>";
         echo "<p><strong>search:</strong> '" . $params['search'] . "'</p>";
         echo "<p><strong>page:</strong> '" . $params['page'] . "'</p>";
         echo "<p><strong>missing:</strong> '" . $params['missing'] . "'</p>";
         echo "<p><strong>nonexistent:</strong> '" . $params['nonexistent'] . "'</p>";
         
         echo "<h5>Method Access Test:</h5>";
         echo "<p><strong>get('search'):</strong> '" . $params->get('search') . "'</p>";
         echo "<p><strong>get('missing', 'default'):</strong> '" . $params->get('missing', 'default') . "'</p>";
         
         echo "<h5>Array Info:</h5>";
         echo "<p><strong>Count:</strong> " . $params->count() . "</p>";
         echo "<p><strong>Keys:</strong> " . implode(', ', $params->keys()) . "</p>";
         echo "<p><strong>Is Empty:</strong> " . ($params->isEmpty() ? 'Yes' : 'No') . "</p>";
         
         echo "</div>";
     }
     
         
    /**
     * Debug model information
     * 
     * @param string $modelPath Model path
     * @return array Debug information
     */
    protected function debugModel(string $modelPath): array
    {
        $state = new NexaState('temp_model_debug', null);
        return $state->debugModel($modelPath);
    }
    
    /**
     * Display model debug information visually
     * 
     * @param string $modelPath Model path
     * @return void
     */
    protected function debugModelDisplay(string $modelPath): void
    {
        $debugInfo = $this->debugModel($modelPath);
        
        echo "<div style='padding: 15px; background: #e8f5e8; border: 2px solid #4caf50; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #2e7d32; margin: 0 0 10px 0;'>🔍 Model Debug Information</h4>";
        echo "<p><strong>Model Path:</strong> <code>{$modelPath}</code></p>";
        
        $this->Raw($debugInfo);
        echo "</div>";
    }
    
    /**
     * Get model methods (untuk debugging/discovery)
     * 
     * @param string $modelPath Model path
     * @return array Available methods
     */
    protected function getModelMethods(string $modelPath): array
    {
        $state = new NexaState('temp_model_methods', null);
        return $state->getModelMethods($modelPath);
    }
    
    /**
     * Display available models dan methods
     * 
     * @param string $subfolder Optional subfolder
     * @return void
     */
    protected function displayAvailableModels(string $subfolder = ''): void
    {
        $models = $this->getAvailableModels($subfolder);
        
        echo "<div style='padding: 15px; background: #f3e5f5; border: 2px solid #9c27b0; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #7b1fa2; margin: 0 0 10px 0;'>📂 Available Models" . ($subfolder ? " in {$subfolder}" : "") . "</h4>";
        
        if (empty($models)) {
            echo "<p>No models found.</p>";
        } else {
            echo "<ul>";
            foreach ($models as $model) {
                echo "<li><strong>{$model}</strong>";
                
                // Show methods for each model
                $methods = $this->getModelMethods($model);
                if (!empty($methods)) {
                    echo "<ul style='margin-top: 5px;'>";
                    foreach ($methods as $methodInfo) {
                        $params = empty($methodInfo['parameters']) ? '()' : '(' . implode(', ', $methodInfo['parameters']) . ')';
                        echo "<li><code>{$methodInfo['name']}{$params}</code></li>";
                    }
                    echo "</ul>";
                }
                echo "</li>";
            }
            echo "</ul>";
        }
        
        echo "</div>";
    }
    
    /**
     * Shorthand untuk model operations dengan state caching
     * 
     * @param string $modelPath Model path
     * @param string $method Method name
     * @param array $params Method parameters
     * @param string $stateKey Optional state key untuk caching hasil
     * @return mixed
     */
    protected function callModel(string $modelPath, string $method, array $params = [], string $stateKey = ''): mixed
    {
        // DIRECT EXECUTION - No cache, fresh data every time
        // This ensures data consistency and avoids stale cache issues
        return $this->useModels($modelPath, $method, $params);
    }
    
    /**
     * Batch model operations
     * 
     * @param array $operations Array of operations: [['model' => 'User', 'method' => 'getActiveUsers'], ...]
     * @return array Results dari semua operations
     */
    protected function batchModelOperations(array $operations): array
    {
        $results = [];
        
        foreach ($operations as $key => $operation) {
            if (!isset($operation['model']) || !isset($operation['method'])) {
                $results[$key] = ['error' => 'Invalid operation format'];
                continue;
            }
            
            try {
                $params = $operation['params'] ?? [];
                $results[$key] = $this->useModels($operation['model'], $operation['method'], $params);
            } catch (Exception $e) {
                $results[$key] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * REMOVED: clearModelCache() - no longer needed since we don't use cache
     */
    
    // ========================================================================
    // MODEL ACCESS INTERFACE - Public methods untuk model integration
    // ========================================================================
    
    /**
     * Public wrapper untuk setSession - Accessible dari model
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     * @return bool Success status
     */
    public function modelSetSession(string $key, mixed $value): bool
    {
        try {
            $this->setSession($key, $value);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Public wrapper untuk getSessionValue - Accessible dari model
     * 
     * @param string $key Session key
     * @param mixed $default Default value
     * @return mixed Session value
     */
    public function modelGetSession(string $key, mixed $default = null): mixed
    {
        try {
            return $this->getSessionValue($key, $default);
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Public wrapper untuk setUser - Accessible dari model
     * 
     * @param array $userData User data
     * @return bool Success status
     */
    public function modelSetUser(array $userData): bool
    {
        try {
            $this->setUser($userData);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Public wrapper untuk getUser - Accessible dari model
     * 
     * @return array|null Current user data
     */
    public function modelGetUser(): ?array
    {
        try {
            return $this->getUser();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Public wrapper untuk setLogout - Accessible dari model
     * 
     * @return bool Success status
     */
    public function modelLogout(): bool
    {
        try {
            $this->setLogout();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Public wrapper untuk isLoggedIn - Accessible dari model
     * 
     * @return bool Login status
     */
    public function modelIsLoggedIn(): bool
    {
        try {
            return $this->isLoggedIn();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Public wrapper untuk setState - Accessible dari model
     * 
     * @param string $key State key
     * @param mixed $value State value
     * @return bool Success status
     */
    public function modelSetState(string $key, mixed $value): bool
    {
        try {
            $this->setState($key, $value);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Public wrapper untuk getState - Accessible dari model
     * 
     * @param string $key State key
     * @param mixed $default Default value
     * @return mixed State value
     */
    public function modelGetState(string $key, mixed $default = null): mixed
    {
        try {
            return $this->getState($key, $default);
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Public wrapper untuk setFlash - Accessible dari model
     * 
     * @param string $type Flash message type
     * @param string $message Flash message
     * @return bool Success status
     */
    public function modelSetFlash(string $type, string $message): bool
    {
        try {
            $this->setFlash($type, $message);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get controller information untuk model debugging
     * 
     * @return array Controller info
     */
    public function modelGetControllerInfo(): array
    {
        return [
            'class_name' => get_class($this),
            'controller_name' => $this->getControllerName(),
            'action_name' => $this->getActionName(),
            'is_logged_in' => $this->modelIsLoggedIn(),
            'current_user' => $this->modelGetUser(),
            'base_url' => $this->getBaseUrl(),
            'assets' => $this->getBaseUrl().'/assets/drive',
            'current_url' => $this->getCurrentUrl()
        ];
    }

    /**
     * Clear all log files - Log management utility
     * Menghapus semua file log di folder system/log/
     * 
     * @param bool $keepCurrentDay Apakah tetap menyimpan log hari ini (default: true)
     * @param bool $includeDebugFiles Apakah termasuk debug files (default: true)
     * @return array Info tentang file yang dihapus
     * 
     * Usage:
     * $this->clearAllLogs();                    // Hapus semua kecuali log hari ini
     * $this->clearAllLogs(false);               // Hapus semua termasuk log hari ini
     * $this->clearAllLogs(true, false);         // Hapus semua kecuali log hari ini dan debug files
     */
    protected function clearAllLogs(bool $keepCurrentDay = true, bool $includeDebugFiles = true): array
    {
        $logDir = __DIR__ . '/log/';
        $today = date('Y-m-d');
        $deletedFiles = [];
        $skippedFiles = [];
        $errors = [];
        
        // Pastikan folder log exists
        if (!is_dir($logDir)) {
            return [
                'success' => false,
                'message' => 'Log directory not found: ' . $logDir,
                'deleted' => [],
                'skipped' => [],
                'errors' => ['Log directory does not exist']
            ];
        }
        
        try {
            // Patterns file yang akan dihapus
            $patterns = [
                'error_*.json',
                'error_*.log'
            ];
            
            if ($includeDebugFiles) {
                $patterns = array_merge($patterns, [
                    'smart_cache.json',
                    'smart_debug.log',
                    'global_filter_debug.log'
                ]);
            }
            
            foreach ($patterns as $pattern) {
                $files = glob($logDir . $pattern);
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    
                    // Skip file hari ini jika $keepCurrentDay = true
                    if ($keepCurrentDay && strpos($filename, $today) !== false) {
                        $skippedFiles[] = $filename . ' (current day)';
                        continue;
                    }
                    
                    // Coba hapus file
                    if (file_exists($file)) {
                        if (unlink($file)) {
                            $deletedFiles[] = $filename;
                        } else {
                            $errors[] = "Failed to delete: $filename";
                        }
                    }
                }
            }
            
            // Clear NexaDebug smart logging cache
            if (class_exists('App\System\Helpers\NexaDebug')) {
                \App\System\Helpers\NexaDebug::clearSmartLoggingCache();
            }
            
            return [
                'success' => true,
                'message' => count($deletedFiles) . ' log files cleared successfully',
                'deleted' => $deletedFiles,
                'skipped' => $skippedFiles,
                'errors' => $errors,
                'total_deleted' => count($deletedFiles),
                'total_skipped' => count($skippedFiles),
                'log_directory' => $logDir
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error clearing logs: ' . $e->getMessage(),
                'deleted' => $deletedFiles,
                'skipped' => $skippedFiles,
                'errors' => array_merge($errors, [$e->getMessage()])
            ];
        }
    }
    
    /**
     * Clear specific log files by date
     * Menghapus log berdasarkan tanggal tertentu
     * 
     * @param string $date Format: Y-m-d (e.g., '2024-01-15')
     * @return array Info tentang file yang dihapus
     * 
     * Usage:
     * $this->clearLogsByDate('2024-01-15');
     */
    protected function clearLogsByDate(string $date): array
    {
        $logDir = __DIR__ . '/log/';
        $deletedFiles = [];
        $errors = [];
        
        if (!is_dir($logDir)) {
            return [
                'success' => false,
                'message' => 'Log directory not found',
                'deleted' => [],
                'errors' => ['Log directory does not exist']
            ];
        }
        
        try {
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new \InvalidArgumentException('Invalid date format. Use Y-m-d format.');
            }
            
            $patterns = [
                "error_{$date}.json",
                "error_{$date}.log",
                "error_{$date}_*.json",
                "error_{$date}_*.log"
            ];
            
            foreach ($patterns as $pattern) {
                $files = glob($logDir . $pattern);
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    
                    if (file_exists($file)) {
                        if (unlink($file)) {
                            $deletedFiles[] = $filename;
                        } else {
                            $errors[] = "Failed to delete: $filename";
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => count($deletedFiles) . " log files for date $date cleared successfully",
                'deleted' => $deletedFiles,
                'errors' => $errors,
                'date' => $date,
                'total_deleted' => count($deletedFiles)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error clearing logs: ' . $e->getMessage(),
                'deleted' => $deletedFiles,
                'errors' => array_merge($errors, [$e->getMessage()])
            ];
        }
    }
    
    /**
     * Clear logs older than X days
     * Menghapus log yang lebih lama dari X hari
     * 
     * @param int $days Jumlah hari (log lebih lama dari ini akan dihapus)
     * @return array Info tentang file yang dihapus
     * 
     * Usage:
     * $this->clearOldLogs(7);    // Hapus log lebih dari 7 hari
     * $this->clearOldLogs(30);   // Hapus log lebih dari 30 hari
     */
    protected function clearOldLogs(int $days = 7): array
    {
        $logDir = __DIR__ . '/log/';
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $deletedFiles = [];
        $skippedFiles = [];
        $errors = [];
        
        if (!is_dir($logDir)) {
            return [
                'success' => false,
                'message' => 'Log directory not found',
                'deleted' => [],
                'skipped' => [],
                'errors' => ['Log directory does not exist']
            ];
        }
        
        try {
            $patterns = ['error_*.json', 'error_*.log'];
            
            foreach ($patterns as $pattern) {
                $files = glob($logDir . $pattern);
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    $fileTime = filemtime($file);
                    
                    if ($fileTime < $cutoffTime) {
                        if (unlink($file)) {
                            $deletedFiles[] = $filename . ' (age: ' . date('Y-m-d H:i:s', $fileTime) . ')';
                        } else {
                            $errors[] = "Failed to delete: $filename";
                        }
                    } else {
                        $skippedFiles[] = $filename . ' (newer than ' . $days . ' days)';
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => count($deletedFiles) . " old log files (>{$days} days) cleared successfully",
                'deleted' => $deletedFiles,
                'skipped' => $skippedFiles,
                'errors' => $errors,
                'days_threshold' => $days,
                'cutoff_date' => date('Y-m-d H:i:s', $cutoffTime),
                'total_deleted' => count($deletedFiles),
                'total_skipped' => count($skippedFiles)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error clearing old logs: ' . $e->getMessage(),
                'deleted' => $deletedFiles,
                'skipped' => $skippedFiles,
                'errors' => array_merge($errors, [$e->getMessage()])
            ];
        }
    }
    
    /**
     * Get log directory info and statistics
     * Mendapatkan informasi folder log dan statistik
     * 
     * @return array Informasi log directory
     * 
     * Usage:
     * $info = $this->getLogInfo();
     * $this->dump($info);
     */
    protected function getLogInfo(): array
    {
        $logDir = __DIR__ . '/log/';
        
        if (!is_dir($logDir)) {
            return [
                'directory_exists' => false,
                'path' => $logDir,
                'message' => 'Log directory not found'
            ];
        }
        
        $files = glob($logDir . '*');
        $logFiles = [];
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $totalSize += $size;
                $fileCount++;
                
                $logFiles[] = [
                    'name' => basename($file),
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'age_days' => round((time() - filemtime($file)) / (24 * 60 * 60), 1)
                ];
            }
        }
        
        // Sort by modification time (newest first)
        usort($logFiles, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return [
            'directory_exists' => true,
            'path' => $logDir,
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'files' => $logFiles,
            'oldest_file' => !empty($logFiles) ? end($logFiles)['modified'] : null,
            'newest_file' => !empty($logFiles) ? $logFiles[0]['modified'] : null,
            'scan_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */

protected function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}



    protected function formatBytesX(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Display log management interface
     * Menampilkan interface untuk management log
     * 
     * @return void
     * 
     * Usage:
     * $this->displayLogManager();
     */
    protected function displayLogManager(): void
    {
        $logInfo = $this->getLogInfo();
        
        echo "<div style='font-family: monospace; padding: 20px; background: #f5f5f5;'>";
        echo "<h2>NexaController Log Manager</h2>";
        echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>Log Statistics</h3>";
        echo "<p><strong>Total Log Files:</strong> {$logInfo['total_files']}</p>";
        echo "<p><strong>Total Size:</strong> {$logInfo['total_size']}</p>";
        echo "<p><strong>Oldest Log:</strong> {$logInfo['oldest_log']}</p>";
        echo "<p><strong>Newest Log:</strong> {$logInfo['newest_log']}</p>";
        echo "</div>";
        
        echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>Actions</h3>";
        echo "<p><a href='?action=clear_old&days=7' style='color: #007bff;'>Clear logs older than 7 days</a></p>";
        echo "<p><a href='?action=clear_all' style='color: #dc3545;'>Clear all logs (keep current day)</a></p>";
        echo "</div>";
        
        if (!empty($logInfo['recent_files'])) {
            echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>Recent Log Files</h3>";
            foreach ($logInfo['recent_files'] as $file) {
                echo "<p><strong>{$file['name']}</strong> - {$file['size']} - {$file['modified']}</p>";
            }
            echo "</div>";
        }
        
        echo "</div>";
    }
    protected function project_drive($file) {
        if (!empty($this->getProjectName())) {
           return '/'.$this->getProjectName() . '/assets/drive'.'/'.$file;
        } else {
          return '/assets/drive'.'/'.$file;
        }
        
       
    }
    protected function projectAssets($file) {
        if (!empty($this->getProjectName())) {
           return '/'.$this->getProjectName() . '/assets'.'/'.$file;
        } else {
          return '/assets'.'/'.$file;
        }
        
       
    }
    // ========================================================================
    // NEXAJS INTEGRATION - JavaScript Data Management
    // ========================================================================
    
    /**
     * Set data untuk dikirim ke JavaScript via NexaJs
     * Data akan tersedia di JavaScript melalui NEXA.controllers.data
     * 
     * @param array $data Data yang akan dikirim ke JavaScript
     * @param string|null $controllerName Nama controller (optional, auto-detect jika null)
     * @return self
     */
    /**
     * Clean data to make it serializable by removing PDO connections and other unserializable objects
     * 
     * @param mixed $data Data to clean
     * @return mixed Cleaned data
     */
    private function cleanUnserializableData($data)
    {
        if (is_object($data)) {
            // Check if object is PDO or contains PDO
            if ($data instanceof \PDO) {
                return '[PDO Connection - Removed for serialization]';
            }
            
            // Check if object is serializable
            try {
                serialize($data);
                return $data;
            } catch (\Exception $e) {
                // If object is not serializable, convert to array or string representation
                if (method_exists($data, 'toArray')) {
                    return $this->cleanUnserializableData($data->toArray());
                } elseif (method_exists($data, '__toString')) {
                    return (string) $data;
                } else {
                    return '[Object of type ' . get_class($data) . ' - Not serializable]';
                }
            }
        } elseif (is_array($data)) {
            // Recursively clean array elements
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = $this->cleanUnserializableData($value);
            }
            return $cleaned;
        }
        
        // For primitives (string, int, float, bool, null), return as is
        return $data;
    }

    protected function setJsController(array $data, $globalData = null): self
    {
        // Auto-detect controller name
        $controllerName = $this->getControllerName();
        
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clean data to prevent PDO serialization errors
        $cleanedData = $this->cleanUnserializableData($data);
        $cleanedGlobalData = $this->cleanUnserializableData($globalData);
        
        // Cek apakah parameter kedua adalah string (cara lama) atau array (cara baru)
        if (is_string($globalData)) {
            // Cara lama: parameter kedua adalah controllerName
            $controllerName = $globalData;
            $jsData = [
                'data' => $cleanedData,
                'page_uri' => $this->getCurrentUrl(),
                'packages'=>$this->getSlug(1),
                'page_home' =>  rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0), '/'),
                'page_index' => rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0) . '/' . $this->getSlug(1), '/'),
                'projectName' => $this->getProjectName(),
                'request_time' => date('Y-m-d H:i:s'),
                'controller_name' => $controllerName,
                'method_name' => $this->getActionName()
            ];
        } elseif (is_array($globalData)) {
            // Cara baru: parameter kedua adalah callback data
            $jsData = [
                // Data utama (parameter 1) - CUKUP SATU VARIABEL
                'data' => $cleanedData,
                
                // Data callback (parameter 2) - HANYA data custom dari user
                'callback' => $cleanedGlobalData,
                
                // Metadata
                'meta' => [
                    'controller_name' => $controllerName,
                    'method_name' => $this->getActionName(),
                    'request_time' => date('Y-m-d H:i:s'),
                    'timestamp' => time()
                ],
                
                // System/routing data - TIDAK di dalam callback
                'page_uri' => $this->getCurrentUrl(),
                'packages'=>  $this->getSlug(1),
                'page_home' =>  rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0), '/'),
                'page_index' => rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0) . '/' . $this->getSlug(1), '/'),
                'project_name' => $this->getProjectName(),
                'project_assets' => $this->getProjectName() . '/assets',
                'project_drive' => $this->getProjectName() . '/assets/drive',
                'base_url' => $this->getBaseUrl(),
                
                 // $this->assignVar('assets', $this->getBaseUrl('/assets/drive'));
                'current_controller' => $controllerName,
                'current_method' => $this->getActionName(),
                'is_logged_in' => $this->isLoggedIn(),
                'user_data' => $this->getUser(),
                'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)),
                
                // Backward compatibility - tetap ada untuk kode lama
                'projectName' => $this->getProjectName(),
                'request_time' => date('Y-m-d H:i:s'),
                'controller_name' => $controllerName,
                'method_name' => $this->getActionName()
            ];
        } else {
            // Default: tidak ada parameter kedua
            $jsData = [
                'data' => $cleanedData,
                'page_uri' => $this->getCurrentUrl(),
                'packages'=>  $this->getSlug(1),
                'page_home' =>  rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0), '/'),
                'page_index' => rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getSlug(0) . '/' . $this->getSlug(1), '/'),
                'projectName' => $this->getProjectName(),
                'request_time' => date('Y-m-d H:i:s'),
                'controller_name' => $controllerName,
                'method_name' => $this->getActionName()
            ];
        }
        
        // Simpan ke session dengan key yang unik
        $sessionKey = 'js_controller_data';
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }
        
        // Update data untuk controller ini
        $_SESSION[$sessionKey][$controllerName] = $jsData;
        
        // Set flag bahwa ada data controller
        $_SESSION['has_js_controller_data'] = true;
        
        // Set CSRF token jika belum ada dan menggunakan cara baru
        if (is_array($globalData) && !isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $jsData['csrf_token'];
        }
        
        // Log untuk debugging (optional)
        if (defined('NEXA_DEBUG') && NEXA_DEBUG) {
            error_log("NexaJs: Data set for controller '{$controllerName}': " . json_encode($jsData));
        }
        
        return $this;
    }

   
    protected function getUserId(): bool{
      return  $this->session->getUserId() ; 
    }
    /**
     * Check apakah ada data JavaScript controller yang tersedia
     * 
     * @return bool
     */
    
    protected function hasJsControllerData(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = 'js_controller_data';
        return isset($_SESSION[$sessionKey]) && !empty($_SESSION[$sessionKey]);
    }
    
    /**
     * Ambil semua data JavaScript controller
     * 
     * @return array
     */
    protected function getJsControllerData(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = 'js_controller_data';
        return $_SESSION[$sessionKey] ?? [];
    }
    
    /**
     * Ambil data JavaScript untuk controller tertentu
     * 
     * @param string $controllerName Nama controller
     * @return array|null
     */
    protected function getJsControllerDataFor(string $controllerName): ?array
    {
        $allData = $this->getJsControllerData();
        return $allData[$controllerName] ?? null;
    }
    
    /**
     * Clear data JavaScript controller
     * 
     * @param string|null $controllerName Nama controller spesifik atau null untuk clear semua
     * @return self
     */
    protected function clearJsControllerData(?string $controllerName = null): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = 'js_controller_data';
        
        if ($controllerName === null) {
            // Clear semua data
            unset($_SESSION[$sessionKey]);
            unset($_SESSION['has_js_controller_data']);
        } else {
            // Clear data untuk controller tertentu
            if (isset($_SESSION[$sessionKey][$controllerName])) {
                unset($_SESSION[$sessionKey][$controllerName]);
                
                // Jika tidak ada data lagi, clear flag
                if (empty($_SESSION[$sessionKey])) {
                    unset($_SESSION['has_js_controller_data']);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Debug data JavaScript controller
     * 
     * @return array
     */
    protected function debugJsControllerData(): array
    {
        return [
            'has_data' => $this->hasJsControllerData(),
            'all_data' => $this->getJsControllerData(),
            'current_controller' => $this->getControllerName(),
            'current_method' => $this->getActionName(),
            'session_status' => session_status(),
            'session_id' => session_id()
        ];
    }

    public function getTemplateInstance() {
        return $this->template;
    }
    public function getDeviceLayouts() {
        return $this->deviceLayouts;
    }

    protected function assignNestedBlock(string $blockPath, array $data): self
    {
        $parts = explode('.', $blockPath);
        $blockName = array_shift($parts);
        
        if (empty($parts)) {
            // If no dots, use regular assignBlock
            return $this->assignBlock($blockName, $data);
        }
        
        // For nested blocks, create the nested structure
        $nestedData = $data;
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $nestedData = [$parts[$i] => $nestedData];
        }
        
        return $this->assignBlock($blockName, $nestedData);
    }


    /**
     * Get model reference for property-style access
     * Returns model instance that can be used as $this->ModelName->method()
     * 
     * @param string $modelPath Model path (e.g., 'Jurnal/Kas')
     * @return object Model instance
     * 
     * Usage:
     * $Kas = $this->refModels('Jurnal/Kas');
     * $refNo = $Kas->generateReferenceNumber('TDS');
     * 
     * Or with property access:
     * $this->Kas = $this->refModels('Jurnal/Kas');
     * $refNo = $this->Kas->generateReferenceNumber('TDS');
     */
    /**
     * Get model reference with chaining support
     * 
     * @param string|null $modelPath Model path (null for chaining mode)
     * @return object Model instance or ModelChainProxy for chaining
     * 
     * Usage:
     * // Traditional usage (backward compatible)
     * $Projects = $this->refModels('Planning\Projects');
     * 
     * // New chaining syntax
     * $Projects = $this->refModels()->Planning()->Projects();
     * $User = $this->refModels()->User();
     * $KasModel = $this->refModels()->Jurnal()->Kas();
     * 
     * // Property-style chaining
     * $Projects = $this->refModels()->Planning->Projects;
     */
    protected function refModels(string $modelPath = null): object
    {
        // If no path provided, return chaining proxy
        if ($modelPath === null) {
            return new ModelChainProxy($this);
        }
        
        // Traditional direct path usage
        return $this->refModelsOriginal($modelPath);
    }
    
    /**
     * Get multiple model references at once
     * 
     * @param array $modelPaths Array of model paths
     * @return array Array of model instances
     */
    protected function refMultipleModels(array $modelPaths): array
    {
        $models = [];
        foreach ($modelPaths as $key => $path) {
            $models[is_string($key) ? $key : $path] = $this->refModelsOriginal($path);
        }
        return $models;
    }
    
    /**
     * Original refModels implementation (renamed for internal use)
     * 
     * @param string $modelPath Model path
     * @return object Model instance
     */
    protected function refModelsOriginal(string $modelPath): object
    {
        // DIRECT INSTANTIATION - No cache, fresh instance every time
        // This ensures clean state and avoids shared instance issues
        
        // Create NexaState instance for model management
        $state = new NexaState('temp_model_ref_' . uniqid(), null);
        
        // Get fresh model instance through NexaState
        return $state->getModelInstance($modelPath, $this);
    }

    /**
     * Get model reference with automatic property assignment
     * Creates a model reference and assigns it to controller property
     * 
     * @param string $modelPath Model path
     * @param string|null $propertyName Optional property name (defaults to model class name)
     * @return object Model instance
     * 
     * Usage:
     * $this->assignModel('Jurnal/Kas', 'Kas');
     * $refNo = $this->Kas->generateReferenceNumber('TDS');
     * 
     * Or auto-assign:
     * $this->assignModel('Jurnal/Kas'); // Creates $this->Kas
     * $refNo = $this->Kas->generateReferenceNumber('TDS');
     */
    protected function assignModel(string $modelPath, ?string $propertyName = null): object
    {
        // Get model instance
        $modelInstance = $this->refModels($modelPath);
        
        // Determine property name
        if ($propertyName === null) {
            $pathParts = explode('/', $modelPath);
            $propertyName = end($pathParts);
        }
        
        // Assign to controller property
        $this->$propertyName = $modelInstance;
        
        return $modelInstance;
    }

    /**
     * Batch assign multiple models
     * 
     * @param array $models Array of model paths or path => property mappings
     * @return array Array of model instances
     * 
     * Usage:
     * $this->assignModels(['Jurnal/Kas', 'User/Auth']);
     * // Creates $this->Kas and $this->Auth
     * 
     * Or with custom names:
     * $this->assignModels([
     *     'Jurnal/Kas' => 'KasModel',
     *     'User/Auth' => 'AuthModel'
     * ]);
     */
    protected function assignModels(array $models): array
    {
        $instances = [];
        
        foreach ($models as $key => $value) {
            if (is_numeric($key)) {
                // Simple array: ['Jurnal/Kas', 'User/Auth']
                $modelPath = $value;
                $propertyName = null;
            } else {
                // Associative array: ['Jurnal/Kas' => 'KasModel']
                $modelPath = $key;
                $propertyName = $value;
            }
            
            $instances[$propertyName ?: basename($modelPath)] = $this->assignModel($modelPath, $propertyName);
        }
        
        return $instances;
    }
    
    /**
     * Clear model references cache
     * 
     * @param string|null $modelPath Optional specific model to clear
     * @return self
     */
    protected function clearModelReferences(?string $modelPath = null): self
    {
        if ($modelPath === null) {
            // Clear all model references
            $this->modelReferences = [];
        } else {
            // Clear specific model reference
            $cacheKey = 'ref_model_' . str_replace(['/', '\\'], '_', $modelPath);
            unset($this->modelReferences[$cacheKey]);
        }
        
        return $this;
    }
    
    /**
     * Get model references cache statistics
     * 
     * @return array Cache statistics
     */
    protected function getModelReferencesStats(): array
    {
        // Calculate safe cache size without serializing PDO objects
        $safeCacheSize = 0;
        try {
            // Create a safe array with just class names and basic info
            $safeReferences = [];
            foreach ($this->modelReferences as $key => $model) {
                $safeReferences[$key] = [
                    'class' => get_class($model),
                    'memory_usage' => memory_get_usage(),
                    'object_hash' => spl_object_hash($model)
                ];
            }
            $safeCacheSize = strlen(serialize($safeReferences));
        } catch (\Exception $e) {
            // If serialization still fails, estimate size differently
            $safeCacheSize = count($this->modelReferences) * 1024; // Rough estimate
        }
        
        return [
            'total_cached_models' => count($this->modelReferences),
            'cached_models' => array_keys($this->modelReferences),
            'cache_size_bytes' => $safeCacheSize,
            'chain_proxy_stats' => ModelChainProxy::getCacheStats()
        ];
    }
    
    /**
     * Debug model references and chaining
     * 
     * @param bool $includeChainStats Include chain proxy statistics
     * @return array Debug information
     */
    protected function debugModelReferences(bool $includeChainStats = true): array
    {
        $debug = [
            'model_references' => $this->getModelReferencesStats(),
            'available_models' => $this->getAvailableModels(),
            'model_folders' => []
        ];
        
        // Get available model folders
        $modelsPath = dirname(__DIR__, 2) . '/models';
        if (is_dir($modelsPath)) {
            $folders = array_filter(glob($modelsPath . '/*'), 'is_dir');
            $debug['model_folders'] = array_map('basename', $folders);
        }
        
        if ($includeChainStats) {
            $debug['chain_proxy_stats'] = ModelChainProxy::getCacheStats();
        }
        
        return $debug;
    }
    
    /**
     * Display model references debug information
     * 
     * @param bool $includeChainStats Include chain proxy statistics
     * @return void
     */
    protected function debugModelReferencesDisplay(bool $includeChainStats = true): void
    {
        $debug = $this->debugModelReferences($includeChainStats);
        
        echo "<h3>Model References Debug Information</h3>";
        echo "<pre>" . json_encode($debug, JSON_PRETTY_PRINT) . "</pre>";
    }
    

    
    /**
     * Clear all model-related caches
     * 
     * @return self
     */
    protected function clearAllModelCaches(): self
    {
        // Clear controller model references
        $this->modelReferences = [];
        
        // Clear ModelChainProxy cache
        ModelChainProxy::clearCache();
        
        // REMOVED: Clear params references - no longer using cache
        
        // Clear ParamsChainProxy cache
        ParamsChainProxy::clearCache();
        
        // Clear NexaState model cache if method exists
        if (method_exists(\App\System\Helpers\NexaState::class, 'clearModelCache')) {
            \App\System\Helpers\NexaState::clearModelCache();
        }
        
        return $this;
    }

    /**
     * Get params controller reference with chaining support
     * 
     * @param string|null $controllerPath Controller path (null for chaining mode)
     * @return object Controller instance or ParamsChainProxy for chaining
     * 
     * Usage:
     * // Traditional usage (backward compatible)
     * $managerController = $this->refParams('Manager/Drive');
     * $result = $managerController->manager($search, $sort, $page, $params, $uid);
     * 
     * // New chaining syntax
     * $DriveManager = $this->refParams()->Manager()->Drive();
     * $AccessGeneral = $this->refParams()->Access()->General();
     * $UserController = $this->refParams()->User();
     * 
     * // Property-style chaining
     * $DriveManager = $this->refParams()->Manager->Drive;
     */
    protected function refParams(string $controllerPath = null): object
    {
        // If no path provided, return chaining proxy
        if ($controllerPath === null) {
            return new ParamsChainProxy($this);
        }
        
        // Traditional direct path usage
        return $this->refParamsOriginal($controllerPath);
    }
    
    /**
     * Original refParams implementation (renamed for internal use)
     * 
     * @param string $controllerPath Controller path
     * @return object Controller instance
     */
    protected function refParamsOriginal(string $controllerPath): object
    {
        // DIRECT INSTANTIATION - No cache, fresh instance every time
        
        // Build controller class name
        $className = $this->buildParamsControllerClassName($controllerPath);
        
        // Check if class exists
        if (!class_exists($className)) {
            throw new \BadMethodCallException("Params controller class '{$className}' not found. Path: {$controllerPath}");
        }
        
        try {
            // Create fresh controller instance
            if (is_subclass_of($className, '\\App\\System\\NexaController')) {
                // For NexaController subclasses, provide required constructor arguments
                $instance = new $className($this->getTemplateInstance(), $this->getDeviceLayouts());
            } else {
                // For regular classes
                $instance = new $className();
            }
            
            // Inject parent controller if method exists
            if (method_exists($instance, 'setParentController')) {
                $instance->setParentController($this);
            }
            
            return $instance;
            
        } catch (\Exception $e) {
            throw new \BadMethodCallException("Failed to instantiate params controller '{$className}': " . $e->getMessage());
        }
    }

    /**
     * Build params controller class name from path
     * 
     * @param string $controllerPath Controller path
     * @return string Full class name
     */
    private function buildParamsControllerClassName(string $controllerPath): string
    {
        // Convert path separators to namespace separators
        $namespacePath = str_replace(['/', '\\'], '\\', trim($controllerPath, '/\\'));
        $pathParts = explode('\\', $namespacePath);
        $className = array_pop($pathParts);
        
        // Build full namespace for Admin controllers
        $namespace = 'App\\Controllers\\Admin';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }
        
        return $namespace . '\\' . $className;
    }

    /**
     * Get params controller reference with automatic property assignment
     * Creates a params controller reference and assigns it to controller property
     * 
     * @param string $controllerPath Controller path
     * @param string|null $propertyName Optional property name (defaults to controller class name)
     * @return object Controller instance
     * 
     * Usage:
     * $this->assignParams('Manager/Drive', 'DriveManager');
     * $result = $this->DriveManager->manager($search, $sort, $page, $params, $uid);
     * 
     * Or auto-assign:
     * $this->assignParams('Manager/Drive'); // Creates $this->Drive
     * $result = $this->Drive->manager($search, $sort, $page, $params, $uid);
     */
    protected function assignParams(string $controllerPath, ?string $propertyName = null): object
    {
        // Get controller instance
        $controllerInstance = $this->refParams($controllerPath);
        
        // Determine property name
        if ($propertyName === null) {
            $pathParts = explode('/', $controllerPath);
            $propertyName = end($pathParts);
        }
        
        // Assign to controller property
        $this->$propertyName = $controllerInstance;
        
        return $controllerInstance;
    }

    /**
     * Batch assign multiple params controllers
     * 
     * @param array $controllers Array of controller paths or path => property mappings
     * @return array Array of controller instances
     * 
     * Usage:
     * $this->assignParamsControllers(['Manager/Drive', 'Access/General']);
     * // Creates $this->Drive and $this->General
     * 
     * Or with custom names:
     * $this->assignParamsControllers([
     *     'Manager/Drive' => 'DriveManager',
     *     'Access/General' => 'AccessController'
     * ]);
     */
    protected function assignParamsControllers(array $controllers): array
    {
        $instances = [];
        
        foreach ($controllers as $key => $value) {
            if (is_numeric($key)) {
                // Simple array: ['Manager/Drive', 'Access/General']
                $controllerPath = $value;
                $propertyName = null;
            } else {
                // Associative array: ['Manager/Drive' => 'DriveManager']
                $controllerPath = $key;
                $propertyName = $value;
            }
            
            $instances[$propertyName ?: basename($controllerPath)] = $this->assignParams($controllerPath, $propertyName);
        }
        
        return $instances;
    }

    /**
     * REMOVED: clearParamsCache() - no longer needed since we don't use cache
     */

    /**
     * Debug params controller information
     * 
     * @param string $controllerPath Controller path
     * @return array Debug information
     */
    protected function debugParams(string $controllerPath): array
    {
        $className = $this->buildParamsControllerClassName($controllerPath);
        
        $debugInfo = [
            'controller_path' => $controllerPath,
            'class_name' => $className,
            'class_exists' => class_exists($className),
            'cached_instances' => [], // REMOVED: No longer using cache
            'controllers_folder_path' => dirname(__DIR__, 2) . '/controllers/Admin'
        ];
        
        // Get available methods if class exists
        if (class_exists($className)) {
            try {
                $reflection = new \ReflectionClass($className);
                $methods = [];
                
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    // Skip magic methods and constructor
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
                
                $debugInfo['available_methods'] = $methods;
            } catch (\Exception $e) {
                $debugInfo['methods_error'] = $e->getMessage();
            }
        }
        
        return $debugInfo;
    }

    /**
     * Display params controller debug information
     * 
     * @param string $controllerPath Controller path
     * @return void
     */
    protected function debugParamsDisplay(string $controllerPath): void
    {
        $debugInfo = $this->debugParams($controllerPath);
        
        echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba;'>";
        echo "<h3>🔧 Params Controller Debug: {$controllerPath}</h3>";
        echo "<pre>" . print_r($debugInfo, true) . "</pre>";
        echo "</div>";
    }

    /**
     * Get available params controllers in Admin folder
     * 
     * @param string $subfolder Optional subfolder (e.g., 'Manager', 'Access')
     * @return array Available controller files
     */
    protected function getAvailableParamsControllers(string $subfolder = ''): array
    {
        $controllersPath = dirname(__DIR__) . '/controllers';
        $controllers = [];
        
        if ($subfolder) {
            $path = $controllersPath . '/' . $subfolder;
            if (is_dir($path)) {
                $files = glob($path . '/*.php');
                foreach ($files as $file) {
                    $className = basename($file, '.php');
                    if (strpos($className, 'Controller') !== false) {
                        $controllers[] = $subfolder . '/' . $className;
                    }
                }
            }
        } else {
            $this->scanControllersRecursively($controllersPath, '', $controllers);
        }
        
        return $controllers;
    }
    
    /**
     * Clear params references cache
     * 
     * @param string|null $controllerPath Optional specific controller to clear
     * @return self
     */
    protected function clearParamsReferences(?string $controllerPath = null): self
    {
        // REMOVED: No longer using paramsReferences cache
        return $this;
    }
    
    /**
     * REMOVED: getParamsReferencesStats() - no longer needed since we don't use cache
     */
    
    /**
     * REMOVED: debugParamsReferences(), debugParamsReferencesDisplay(), clearAllParamsCaches() 
     * - no longer needed since we don't use cache
     */

    // ========================================================================
    // NEXA EVENT INTEGRATION - Dynamic Routing System
    // ========================================================================

    /**
     * Execute method based on explicit path without URL parsing
     * For path 'Access/viewUsers' -> routes to AccessController->viewUsers()
     * 
     * @param string $path Path in format 'ControllerName/methodName'
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name if method not found
     * @param string $controllersPath Path to controllers directory
     * @param string $namespace Controller namespace (default: Admin)
     * @param bool $jsonOutput Whether to output JSON response (default: true)
     * @return bool True if method found and executed, false otherwise
     */
         protected function eventsControllers(){
         try {
             $data = $this->inputs();

             // Validasi input yang diperlukan
             if (!isset($data['method']) || empty($data['method'])) {
                 return $this->json([
                     'success' => false,
                     'message' => 'Method name is required',
                     'error' => 'METHOD_NAME_MISSING'
                 ], 400);
             }
             
             $methodName = trim($data['method']);
             
             // Decrypt params jika ada
             $params = [];
             if (isset($data['params']) && !empty($data['params'])) {
                try {
                    $params = $this->Encrypt->decryptJson($data['params']) ?? [];
                 } catch (\Exception $e) {
                     error_log("EventsControllers Decrypt Error: " . $e->getMessage());
                     // Jika decrypt gagal, coba gunakan params langsung
                     $params = is_array($data['params']) ? $data['params'] : [];
                 }
             }
             
             // Cek apakah ada parameter 'controllers' untuk cross-controller call
             if (isset($data['controllers']) && !empty($data['controllers'])) {
                 return $this->handleCrossControllerCall($data['controllers'], $methodName, $params);
             }
             
             // Validasi format method name
             if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
                 return $this->json([
                     'success' => false,
                     'message' => 'Invalid method name format',
                     'error' => 'INVALID_METHOD_NAME'
                 ], 400);
             }
             
             // Periksa apakah method ada di controller ini dan dapat diakses (public)
             if (!method_exists($this, $methodName)) {
                 return $this->json([
                     'success' => false,
                     'message' => "Method '{$methodName}' not found in controller",
                     'error' => 'METHOD_NOT_FOUND'
                 ], 404);
             }
             
             // Periksa apakah method adalah public (untuk keamanan)
             $reflection = new \ReflectionMethod($this, $methodName);
             if (!$reflection->isPublic()) {
                 return $this->json([
                     'success' => false,
                     'message' => "Method '{$methodName}' is not accessible",
                     'error' => 'METHOD_NOT_ACCESSIBLE'
                 ], 403);
             }
             
             // Panggil method dengan parameter jika ada
             if (!empty($params) && is_array($params)) {
                 $result = $this->$methodName($params);
             } else {
                 $result = $this->$methodName();
             }
             
             // Return hasil
             return $this->json([
                 'success' => true,
                 'data' => $result,
                 'method' => $methodName,
                 'timestamp' => date('Y-m-d H:i:s')
             ]);
             
         } catch (\Exception $e) {
             error_log("EventsControllers Exception: " . $e->getMessage());
             return $this->json([
                 'success' => false,
                 'message' => 'An error occurred while processing the request',
                 'error' => 'PROCESSING_ERROR',
                 'details' => $e->getMessage()
             ], 500);
         }
     }

     /**
      * Handle cross-controller method calls
      * 
      * @param string $controllerName Name of target controller
      * @param string $methodName Name of method to call
      * @param array $params Parameters to pass
      * @return array JSON response
      */
     private function handleCrossControllerCall(string $controllerName, string $methodName, array $params): array
     {
         try {
             // Log untuk debugging
             
             
             // Clean controller name - hapus suffix 'Controllers' atau 'Controller'
             $cleanControllerName = $controllerName;
             if (str_ends_with($controllerName, 'Controllers')) {
                 $cleanControllerName = substr($controllerName, 0, -11); // Remove 'Controllers'
             } elseif (str_ends_with($controllerName, 'Controller')) {
                 $cleanControllerName = substr($controllerName, 0, -10); // Remove 'Controller'
             }
             
             // Validasi format controller name
             if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $cleanControllerName)) {
                 $errorResponse = [
                     'success' => false,
                     'message' => 'Invalid controller name format',
                     'error' => 'INVALID_CONTROLLER_NAME'
                 ];
                 $this->json($errorResponse, 400);
                 return $errorResponse;
             }
             
             // Validasi format method name
             if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
                 $errorResponse = [
                     'success' => false,
                     'message' => 'Invalid method name format',
                     'error' => 'INVALID_METHOD_NAME'
                 ];
                 $this->json($errorResponse, 400);
                 return $errorResponse;
             }
             
             // Build controller class name
             $controllerClass = "App\\Controllers\\Admin\\{$cleanControllerName}Controller";
             
             // Log untuk debugging
           
             // Check if controller class exists
             if (!class_exists($controllerClass)) {
                 $errorResponse = [
                     'success' => false,
                     'message' => "Controller '{$cleanControllerName}' not found. Looking for class: {$controllerClass}",
                     'error' => 'CONTROLLER_NOT_FOUND'
                 ];
                 $this->json($errorResponse, 404);
                 return $errorResponse;
             }
             
             // Create controller instance
             $controller = new $controllerClass($this->template, $this->deviceLayouts);
             
             // Check if method exists
             if (!method_exists($controller, $methodName)) {
                 $errorResponse = [
                     'success' => false,
                     'message' => "Method '{$methodName}' not found in controller '{$cleanControllerName}'",
                     'error' => 'METHOD_NOT_FOUND'
                 ];
                 $this->json($errorResponse, 404);
                 return $errorResponse;
             }
             
             // Check if method is public
             $reflection = new \ReflectionMethod($controller, $methodName);
             if (!$reflection->isPublic()) {
                 $errorResponse = [
                     'success' => false,
                     'message' => "Method '{$methodName}' is not accessible in controller '{$cleanControllerName}'",
                     'error' => 'METHOD_NOT_ACCESSIBLE'
                 ];
                 $this->json($errorResponse, 403);
                 return $errorResponse;
             }
             
             // Call the method
             if (!empty($params) && is_array($params)) {
                 $result = $controller->$methodName($params);
             } else {
                 $result = $controller->$methodName();
             }
             
             // Return hasil
             $response = [
                 'success' => true,
                 'data' => $result,
                 'controller' => $controllerName,
                 'method' => $methodName,
                 'timestamp' => date('Y-m-d H:i:s')
             ];
             $this->json($response);
             return $response;
             
         } catch (\Exception $e) {
             error_log("CrossControllerCall Exception: " . $e->getMessage());
             $errorResponse = [
                 'success' => false,
                 'message' => 'An error occurred while calling cross-controller method',
                 'error' => 'CROSS_CONTROLLER_ERROR',
                 'details' => $e->getMessage()
             ];
             $this->json($errorResponse, 500);
             return $errorResponse;
         }
     }
   protected function eventsModel(){
    try {
        $data = $this->inputs();
        
        // Validasi input yang diperlukan
        if (!isset($data['model']) || empty($data['model'])) {
            return $this->json([
                'success' => false,
                'message' => 'Model path is required',
                'error' => 'MODEL_PATH_MISSING'
            ], 400);
        }
        
        if (!isset($data['method']) || empty($data['method'])) {
            return $this->json([
                'success' => false,
                'message' => 'Method name is required',
                'error' => 'METHOD_NAME_MISSING'
            ], 400);
        }
        
        // Sanitasi input
        $modelPath = trim($data['model']);
        $methodName = trim($data['method']);
        $params = [];
        
        // Decrypt params with better error handling
        if (isset($data['params']) && !empty($data['params'])) {
            try {
                $decryptedParams = $this->Encrypt->decryptJson($data['params']);
                $params = is_array($decryptedParams) ? $decryptedParams : [];
            } catch (\Exception $e) {
                error_log("Failed to decrypt params: " . $e->getMessage());
                $params = [];
            }
        }
        
        // Validasi format model path
        if (!preg_match('/^[a-zA-Z0-9_\/\\\\]+$/', $modelPath)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid model path format',
                'error' => 'INVALID_MODEL_PATH'
            ], 400);
        }
        
        // Validasi format method name
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid method name format',
                'error' => 'INVALID_METHOD_NAME'
            ], 400);
        }
        
        // Dapatkan instance model
        $modelInstance = $this->refModels($modelPath);
        
        // Periksa apakah method ada
        if (!method_exists($modelInstance, $methodName)) {
            return $this->json([
                'success' => false,
                'message' => "Method '{$methodName}' not found in model '{$modelPath}'",
                'error' => 'METHOD_NOT_FOUND'
            ], 404);
        }
        
        // Panggil method dengan parameter jika ada
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
            return $this->json([
                'success' => false,
                'message' => 'Invalid parameter types passed to method',
                'error' => 'INVALID_PARAMETER_TYPES',
                'details' => $e->getMessage()
            ], 400);
        }
        
        // Return hasil
        return $this->json([
            'success' => true,
            'data' => $result,
            'model' => $modelPath,
            'method' => $methodName,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (\BadMethodCallException $e) {
        error_log("FetchModels BadMethodCallException: " . $e->getMessage());
        return $this->json([
            'success' => false,
            'message' => 'Model not found or could not be instantiated',
            'error' => 'MODEL_INSTANTIATION_ERROR',
            'details' => $e->getMessage()
        ], 404);
        
    } catch (\Exception $e) {
        error_log("FetchModels Exception: " . $e->getMessage());
        return $this->json([
            'success' => false,
            'message' => 'An error occurred while processing the request',
            'error' => 'PROCESSING_ERROR',
            'details' => $e->getMessage()
        ], 500);
    }
}






    protected function eventsAccess(array $params = []){

           $data = $this->inputs();
           // Hapus 'path' dari request karena sudah ada di level utama
           $cleanData = $data;
           $keyData=$this->Encrypt->decryptJson($cleanData['data']);
           unset($cleanData['path']);
           
           // Kirim data terpisah: request dan params method
           $requestData = [
               'request' => isset($keyData) ? $keyData : $cleanData,
               'params' => $params
           ];
           $this->eventsMethod($data['path'], $requestData);
    }

    protected function eventsMethod(
          string $path, $params = [], 
          string $fallbackMethod = null, 
          string $controllersPath = null, 
          string $namespace = 'Admin', 
          bool $jsonOutput = true): bool
    {
      
        return \App\System\Helpers\NexaEvent::eventsMethod(
            $path, 
            $params, 
            $fallbackMethod, 
            $controllersPath, 
            $namespace, 
            $jsonOutput
        );
    }

    /**
     * Execute method based on explicit path with advanced options
     * Extended version of eventsMethod with more configuration options
     * 
     * @param string $path Path in format 'ControllerName/methodName'
     * @param mixed $params Parameters to pass to method
     * @param array $options Configuration options
     * @return array Execution result with details
     */
    protected function eventsMethodAdvanced(string $path, $params = [], array $options = []): array
    {
        return \App\System\Helpers\NexaEvent::eventsMethodAdvanced($path, $params, $options);
    }
    /**
     * Dynamic method routing using NexaEvent system
     * This is the main routing method that should be called in controller index methods
     * 
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name
     * @param string $controllersPath Path to controllers directory
     * @param int $methodSlugIndex Index of slug that contains method name
     * @return void
     */
    protected function nexaMethod($params = [], string $fallbackMethod = 'default', string $controllersPath = null, int $methodSlugIndex = 1): void
    {
        \App\System\Helpers\NexaEvent::Method($this, $params, $fallbackMethod, $controllersPath, $methodSlugIndex);
    }

    /**
     * Register a new folder to be scanned for controllers
     * 
     * @param string $folderName Folder name to register
     * @return self
     */
    protected function registerControllerFolder(string $folderName): self
    {
        \App\System\Helpers\NexaEvent::registerFolder($folderName);
        return $this;
    }

    /**
     * Get all registered controller folders
     * 
     * @return array
     */
    protected function getRegisteredControllerFolders(): array
    {
        return \App\System\Helpers\NexaEvent::getRegisteredFolders();
    }

    /**
     * Clear controller cache
     * 
     * @return self
     */
    protected function clearControllerCache(): self
    {
        \App\System\Helpers\NexaEvent::clearCache();
        return $this;
    }

    /**
     * Get cached controllers (for debugging)
     * 
     * @return array
     */
    protected function getCachedControllers(): array
    {
        return \App\System\Helpers\NexaEvent::getCachedControllers();
    }

    /**
     * Find and execute method from registered controllers
     * 
     * @param string $method Method name to find
     * @param array $params Parameters to pass to method
     * @param string $controllersPath Path to controllers directory
     * @return bool True if method found and executed, false otherwise
     */
    protected function findAndExecuteMethod(string $method, array $params = [], string $controllersPath = null): bool
    {
        return \App\System\Helpers\NexaEvent::findAndExecuteMethod($method, $params, $controllersPath);
    }

    /**
     * Route method with URL parsing
     * 
     * @param string $fallbackMethod Default method to call if no method found
     * @param array $params Parameters to pass to method
     * @param string $controllersPath Path to controllers directory
     * @param int $methodSlugIndex Index of slug that contains method name
     * @return bool True if method found and executed, false otherwise
     */
    protected function routeMethod(string $fallbackMethod = 'default', array $params = [], string $controllersPath = null, int $methodSlugIndex = 1): bool
    {
        return \App\System\Helpers\NexaEvent::routeMethod($fallbackMethod, $params, $controllersPath, $methodSlugIndex);
    }

    /**
     * Get comprehensive request information using NexaEvent
     * 
     * @return array
     */
    protected function getEventRequestInfo(): array
    {
        return \App\System\Helpers\NexaEvent::getRequestInfo();
    }

    /**
     * Get NexaRequest instance for advanced operations through NexaEvent
     * 
     * @return \App\System\Helpers\NexaRequest
     */
    protected function eventRequest(): \App\System\Helpers\NexaRequest
    {
        return \App\System\Helpers\NexaEvent::request();
    }

    /**
     * Enhanced URL slug handling with NexaEvent integration
     * This overrides the existing getSlug method to use NexaEvent
     * 
     * @param int $index Index of the slug
     * @param string $default Default value if slug doesn't exist
     * @return string
     */
    protected function getEventSlug(int $index = 0, string $default = ''): string
    {
        return \App\System\Helpers\NexaEvent::getSlug($index, $default);
    }

    /**
     * Get all URL slugs as array using NexaEvent
     * 
     * @return array
     */
    protected function getEventSlugArray(): array
    {
        return \App\System\Helpers\NexaEvent::getSlugArray();
    }

    /**
     * Get URL part by index using NexaEvent
     * 
     * @param int $index Index of the part
     * @param string $default Default value if part doesn't exist
     * @return string
     */
    protected function getEventPart(int $index = 0, string $default = ''): string
    {
        return \App\System\Helpers\NexaEvent::getPart($index, $default);
    }

    /**
     * Get all URL parts as array using NexaEvent
     * 
     * @return array
     */
    protected function getEventPartArray(): array
    {
        return \App\System\Helpers\NexaEvent::getPartArray();
    }

    /**
     * Get current path without query string using NexaEvent
     * 
     * @return string
     */
    protected function getEventCurrentPath(): string
    {
        return \App\System\Helpers\NexaEvent::getCurrentPath();
    }

    /**
     * Get current URL with protocol and host using NexaEvent
     * 
     * @return string
     */
    protected function getEventCurrentUrl(): string
    {
        return \App\System\Helpers\NexaEvent::getCurrentUrl();
    }

    /**
     * Check if specific slug exists at index using NexaEvent
     * 
     * @param int $index Index to check
     * @param string $slug Slug value to match
     * @return bool
     */
    protected function hasEventSlug(int $index, string $slug): bool
    {
        return \App\System\Helpers\NexaEvent::hasSlug($index, $slug);
    }

    /**
     * Get slug count using NexaEvent
     * 
     * @return int
     */
    protected function getEventSlugCount(): int
    {
        return \App\System\Helpers\NexaEvent::getSlugCount();
    }

    /**
     * Get path segments using NexaEvent
     * 
     * @return array
     */
    protected function getEventPathSegments(): array
    {
        return \App\System\Helpers\NexaEvent::getPathSegments();
    }

    /**
     * Get relative path segments using NexaEvent
     * 
     * @return array
     */
    protected function getEventRelativePathSegments(): array
    {
        return \App\System\Helpers\NexaEvent::getRelativePathSegments();
    }

    /**
     * Get query parameters using NexaEvent
     * 
     * @return array
     */
    protected function getEventQueryParams(): array
    {
        return \App\System\Helpers\NexaEvent::getQueryParams();
    }

    /**
     * Get specific query parameter using NexaEvent
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getEventQueryParam(string $key, $default = null)
    {
        return \App\System\Helpers\NexaEvent::getQueryParam($key, $default);
    }

    /**
     * Check if request is AJAX using NexaEvent
     * 
     * @return bool
     */
    protected function isEventAjax(): bool
    {
        return \App\System\Helpers\NexaEvent::isAjax();
    }

    /**
     * Get request method using NexaEvent
     * 
     * @return string
     */
    protected function getEventMethod(): string
    {
        return \App\System\Helpers\NexaEvent::getMethod();
    }

    /**
     * Check if request method matches using NexaEvent
     * 
     * @param string $method Method to check
     * @return bool
     */
    protected function isEventMethod(string $method): bool
    {
        return \App\System\Helpers\NexaEvent::isMethod($method);
    }

    /**
     * Get base URL using NexaEvent
     * 
     * @return string
     */
    protected function getEventBaseUrl(): string
    {
        return \App\System\Helpers\NexaEvent::getBaseUrl();
    }

    /**
     * Get project name using NexaEvent
     * 
     * @return string|null
     */
    protected function getEventProjectName(): ?string
    {
        return \App\System\Helpers\NexaEvent::getProjectName();
    }

    /**
     * Debug NexaEvent system
     * 
     * @return void
     */
    protected function debugNexaEvent(): void
    {
        echo "<h3>NexaEvent Debug Information</h3>";
        echo "<hr>";
        
        echo "<h4>URL Information:</h4>";
        echo "Current path: " . $this->getEventCurrentPath() . "<br>";
        echo "Current URL: " . $this->getEventCurrentUrl() . "<br>";
        echo "Base URL: " . $this->getEventBaseUrl() . "<br>";
        echo "Project name: " . ($this->getEventProjectName() ?? 'Root installation') . "<br>";
        echo "Request method: " . $this->getEventMethod() . "<br>";
        echo "Is AJAX: " . ($this->isEventAjax() ? 'Yes' : 'No') . "<br>";
        echo "<hr>";
        
        echo "<h4>URL Segments:</h4>";
        echo "Slug array: " . implode(' / ', $this->getEventSlugArray()) . "<br>";
        echo "Part array: " . implode(' / ', $this->getEventPartArray()) . "<br>";
        echo "Path segments: " . implode(' / ', $this->getEventPathSegments()) . "<br>";
        echo "Relative path segments: " . implode(' / ', $this->getEventRelativePathSegments()) . "<br>";
        echo "Slug count: " . $this->getEventSlugCount() . "<br>";
        echo "<hr>";
        
        echo "<h4>Query Parameters:</h4>";
        $queryParams = $this->getEventQueryParams();
        if (!empty($queryParams)) {
            foreach ($queryParams as $key => $value) {
                echo "{$key}: {$value}<br>";
            }
        } else {
            echo "No query parameters<br>";
        }
        echo "<hr>";
        
        echo "<h4>Controller System:</h4>";
        echo "Registered folders: " . implode(', ', $this->getRegisteredControllerFolders()) . "<br>";
        echo "Cached controllers: " . count($this->getCachedControllers()) . "<br>";
        
        if (!empty($this->getCachedControllers())) {
            echo "<h5>Available Controllers:</h5>";
            foreach ($this->getCachedControllers() as $controller) {
                echo "- " . $controller . "<br>";
            }
        }
        
        echo "<hr>";
        echo "<h4>Method Routing Debug (Index 2):</h4>";
        $debugInfo = \App\System\Helpers\NexaEvent::debugMethodRouting($this, 2, 'default');
        echo "<pre>" . print_r($debugInfo, true) . "</pre>";
    }

    /**
     * Simplified routing method - most common usage
     * For URLs like /controller/method, use methodSlugIndex = 1
     * For URLs like /controller/folder/method, use methodSlugIndex = 2
     * 
     * @param mixed $params Parameters to pass to method
     * @param int $methodSlugIndex Index of slug that contains method name
     * @param string $fallbackMethod Fallback method name
     * @return void
     */
    protected function accessEvent($params = [], int $methodSlugIndex = 1, string $fallbackMethod = 'default'): void
    { 

        $this->nexaMethod($params, $fallbackMethod, null, $methodSlugIndex);
    }

    protected function accessJsEvent($params = [], int $methodSlugIndex = 1, string $fallbackMethod = 'default'): void
    {
       
         $this->nexaMethod($params, $fallbackMethod, null, $methodSlugIndex);
    }
    /**
     * Quick routing for common patterns
     * For standard controller/method pattern
     * 
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name
     * @return void
     */
    protected function  eventKamikaze($params = [], string $fallbackMethod = 'default'): void
    {
        $this->accessEvent($params, 1, $fallbackMethod);
    }

    /**
     * Quick routing for nested patterns
     * For controller/folder/method pattern
     * 
     * @param mixed $params Parameters to pass to method
     * @param string $fallbackMethod Fallback method name
     * @return void
     */
    protected function eventRaptor($params = [], string $fallbackMethod = 'default'): void
    {
      
         $this->accessEvent($params, 2, $fallbackMethod);
    }


}

/**
 * NodeControllerProxy - Provides fluent interface for controller method calls
 */
class NodeControllerProxy
{
    private NexaController $parentController;
    private string $controllerName;
    private array $params = [];
    private ?string $namespace = null;

    public function __construct(NexaController $parentController, string $controllerName)
    {
        $this->parentController = $parentController;
        $this->controllerName = $controllerName;
    }

    /**
     * Set parameters for controller
     * 
     * @param array $params Parameters array
     * @return self
     */
    public function withParams(array $params): self
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Set method to call
     * 
     * @param string $method Method name
     * @return self
     */
    public function method(string $method): self
    {
        $this->params['method'] = $method;
        return $this;
    }

    /**
     * Set namespace
     * 
     * @param string $namespace Controller namespace
     * @return self
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Add single parameter
     * 
     * @param string $key Parameter key
     * @param mixed $value Parameter value
     * @return self
     */
    public function param(string $key, $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * Execute the controller call
     * 
     * @return mixed Controller result
     */
    public function execute()
    {
        // Use reflection to call protected method
        $reflection = new \ReflectionMethod($this->parentController, 'nodeController');
        $reflection->setAccessible(true);
        
        return $reflection->invoke(
            $this->parentController, 
            $this->controllerName, 
            $this->params, 
            $this->namespace
        );
    }

    /**
     * Magic method to execute on toString
     * 
     * @return string
     */
    public function __toString(): string
    {
        try {
            $result = $this->execute();
            return is_string($result) ? $result : '';
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}

/**
 * ConfigBuilder - Fluent interface for config data filtering
 * Provides method chaining for complex config filtering operations
 */
class ConfigBuilder
{
    private array $data;
    private NexaController $controller;
    private array $operations = [];

    public function __construct(array $data, NexaController $controller)
    {
        $this->data = $data;
        $this->controller = $controller;
    }

    /**
     * Exclude specific fields from a key
     * 
     * @param string $key Config key
     * @param array $fields Fields to exclude
     * @return self
     */
    public function exclude(string $key, array $fields): self
    {
        $this->operations[] = ['type' => 'exclude', 'key' => $key, 'fields' => $fields];
        return $this;
    }

    /**
     * Include only specific fields from a key
     * 
     * @param string $key Config key
     * @param array $fields Fields to include
     * @return self
     */
    public function include(string $key, array $fields): self
    {
        $this->operations[] = ['type' => 'include', 'key' => $key, 'fields' => $fields];
        return $this;
    }

    /**
     * Get the filtered result
     * 
     * @return array Filtered config data
     */
    public function get(): array
    {
        $result = $this->data;
        
        foreach ($this->operations as $operation) {
            $exclude = ($operation['type'] === 'exclude');
            
            // Use reflection to access protected method
            $reflection = new \ReflectionMethod($this->controller, 'filterConfigData');
            $reflection->setAccessible(true);
            
            $filteredData = $reflection->invoke(
                $this->controller,
                $result,
                $operation['key'],
                $operation['fields'],
                $exclude
            );
            
            $result[$operation['key']] = $filteredData;
        }
        
        return $result;
    }

    /**
     * Get specific key only
     * 
     * @param string $key Config key to get
     * @return array Filtered data for specific key
     */
    public function getKey(string $key): array
    {
        $result = $this->get();
        return $result[$key] ?? [];
    }

    /**
     * Reset operations and start fresh
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->operations = [];
        return $this;
    }

    /**
     * Debug the operations
     * 
     * @return array Operations info
     */
    public function debug(): array
    {
        return [
            'data_keys' => array_keys($this->data),
            'operations_count' => count($this->operations),
            'operations' => $this->operations
        ];
    }
}

/**
    * SafeParamsArray - Array wrapper that returns empty string for missing keys
   * Implements ArrayAccess to provide safe parameter access without undefined index errors
  */
class SafeParamsArray implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private array $data;
    private string $defaultValue;

    public function __construct(array $data, string $defaultValue = '')
    {
        $this->data = $data;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Check if offset exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Get value at offset - returns default value if key doesn't exist
     */
    public function offsetGet(mixed $offset): string
    {
        return (string) ($this->data[$offset] ?? $this->defaultValue);
    }

    /**
     * Set value at offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Unset value at offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Count elements
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get iterator for foreach loops
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Convert to regular array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get all keys
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Get all values
     */
    public function values(): array
    {
        return array_values($this->data);
    }

    /**
     * Check if array is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Get safe value with custom default
     */
    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->data[$key] ?? $default);
    }

    /**
     * Debug representation
     */
    public function __toString(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }
}

/**
 * ModelChainProxy - Proxy class for chaining model access
 * Enables syntax like $this->refModels()->Planning()->Projects()
 * 
 * Usage:
 * $Projects = $this->refModels()->Planning()->Projects();
 * $User = $this->refModels()->User();
 * $KasModel = $this->refModels()->Jurnal()->Kas();
 * 
 * Features:
 * - Supports unlimited chaining depth
 * - Automatic path building
 * - Caching for performance
 * - Error handling for invalid paths
 * - Debug information
 */
class ModelChainProxy
{
    private NexaController $controller;
    private array $pathSegments = [];
    private static array $chainCache = [];
    private bool $debugMode = false;
    
    public function __construct(NexaController $controller, bool $debugMode = false)
    {
        $this->controller = $controller;
        $this->debugMode = $debugMode;
    }
    
    /**
     * Get the controller instance
     */
    public function getController(): NexaController
    {
        return $this->controller;
    }
    
    /**
     * Get debug mode status
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }
    
    /**
     * Magic method to handle chaining calls
     * Each method call adds a segment to the path
     * 
     * @param string $name Method name (becomes path segment)
     * @param array $arguments Method arguments (unused in chaining)
     * @return mixed ModelChainProxy for chaining or model instance if final
     */
    public function __call(string $name, array $arguments): mixed
    {
        // Add segment to path
        $this->pathSegments[] = $name;
        
        // Build current path
        $currentPath = implode('\\', $this->pathSegments);
        
        // Check if this is a valid model path (try to resolve it)
        if ($this->isValidModelPath($currentPath)) {
            // This is a valid final path, get model instance
            $model = $this->resolveModel($currentPath);
            
            // Create a smart wrapper that handles method calls and auto-resets
            return new SmartModelWrapper($model, $this);
        }
        
        // If we have multiple segments, check if the previous path was a valid model
        // and this is a method call on that model
        if (count($this->pathSegments) > 1) {
            $modelPath = implode('\\', array_slice($this->pathSegments, 0, -1));
            $methodName = end($this->pathSegments);
            
            if ($this->isValidModelPath($modelPath)) {
                // Resolve the model and call the method
                $model = $this->resolveModel($modelPath);
                
                // Check if method exists on the model
                if (method_exists($model, $methodName)) {
                    $result = $model->$methodName(...$arguments);
                    
                    // Reset path segments for next chaining
                    $this->pathSegments = [];
                    
                    return $result;
                }
            }
        }
        
        // Continue chaining
        return $this;
    }
    
    /**
     * Check if the current path represents a valid model
     * 
     * @param string $path Model path to check
     * @return bool True if valid model exists
     */
    private function isValidModelPath(string $path): bool
    {
        // Create cache key for path validation
        $cacheKey = 'path_validation_' . md5($path);
        
        if (isset(self::$chainCache[$cacheKey])) {
            return self::$chainCache[$cacheKey];
        }
        
        try {
            // Try direct path first
            $className = $this->buildModelClassName($path);
            if (class_exists($className)) {
                self::$chainCache[$cacheKey] = true;
                return true;
            }
            
            // If direct path fails and we have a single segment, try common namespaces
            if (strpos($path, '\\') === false) {
                $commonNamespaces = ['Planning', 'Domain', 'Role', 'Jurnal'];
                
                foreach ($commonNamespaces as $namespace) {
                    $namespacedPath = $namespace . '\\' . $path;
                    $namespacedClassName = $this->buildModelClassName($namespacedPath);
                    
                    if (class_exists($namespacedClassName)) {
                        // Cache the result with the namespaced path
                        self::$chainCache[$cacheKey] = true;
                        return true;
                    }
                }
            }
            
            // Cache negative result
            self::$chainCache[$cacheKey] = false;
            return false;
            
        } catch (\Exception $e) {
            // Cache negative result
            self::$chainCache[$cacheKey] = false;
            return false;
        }
    }
    
    /**
     * Build model class name from path (same logic as NexaState)
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
        
        // Build full namespace
        $namespace = 'App\\Models';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }
        
        return $namespace . '\\' . $className;
    }
    
    /**
     * Resolve model instance using controller's refModels method
     * 
     * @param string $modelPath Model path to resolve
     * @return object Model instance
     */
    private function resolveModel(string $modelPath): object
    {
        // Use reflection to call the original refModels method
        $reflection = new \ReflectionMethod($this->controller, 'refModelsOriginal');
        $reflection->setAccessible(true);
        
        // Try direct path first
        try {
            return $reflection->invoke($this->controller, $modelPath);
        } catch (\Exception $e) {
            // If direct path fails and we have a single segment, try common namespaces
            if (strpos($modelPath, '\\') === false) {
                $commonNamespaces = ['Planning', 'Domain', 'Role', 'Jurnal'];
                
                foreach ($commonNamespaces as $namespace) {
                    try {
                        $namespacedPath = $namespace . '\\' . $modelPath;
                        return $reflection->invoke($this->controller, $namespacedPath);
                    } catch (\Exception $e) {
                        // Continue to next namespace
                        continue;
                    }
                }
            }
            
            // If all attempts fail, throw the original exception
            throw $e;
        }
    }
    
    /**
     * Force resolution of current path as model
     * Useful when you want to force resolution even if path validation fails
     * 
     * @return object Model instance
     * @throws \Exception If model cannot be resolved
     */
    public function resolve(): object
    {
        $currentPath = implode('\\', $this->pathSegments);
        
        if (empty($currentPath)) {
            throw new \BadMethodCallException('Cannot resolve empty model path. Use chaining like ->Planning()->Projects()');
        }
        
        return $this->resolveModel($currentPath);
    }
    
    /**
     * Get current path being built
     * 
     * @return string Current model path
     */
    public function getCurrentPath(): string
    {
        return implode('\\', $this->pathSegments);
    }
    
    /**
     * Get path segments array
     * 
     * @return array Path segments
     */
    public function getPathSegments(): array
    {
        return $this->pathSegments;
    }
    
    /**
     * Reset path segments for new chaining
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->pathSegments = [];
        return $this;
    }
    
    /**
     * Create a new proxy instance from current one
     * Useful for branching into different model paths
     * 
     * @return ModelChainProxy New proxy instance
     */
    public function branch(): ModelChainProxy
    {
        return new ModelChainProxy($this->controller, $this->debugMode);
    }
    
    /**
     * Debug information about current chain state
     * 
     * @return array Debug information
     */
    public function debug(): array
    {
        $currentPath = $this->getCurrentPath();
        
        return [
            'current_path' => $currentPath,
            'path_segments' => $this->pathSegments,
            'segments_count' => count($this->pathSegments),
            'is_valid_path' => $this->isValidModelPath($currentPath),
            'expected_class' => $this->buildModelClassName($currentPath),
            'class_exists' => class_exists($this->buildModelClassName($currentPath)),
            'cache_stats' => [
                'total_cached_validations' => count(self::$chainCache),
                'cached_paths' => array_keys(self::$chainCache)
            ]
        ];
    }
    
    /**
     * Enable debug mode for verbose output
     * 
     * @param bool $enable Enable or disable debug mode
     * @return self
     */
    public function debugMode(bool $enable = true): self
    {
        $this->debugMode = $enable;
        return $this;
    }
    
    /**
     * Clear chain cache
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$chainCache = [];
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function getCacheStats(): array
    {
        return [
            'total_entries' => count(self::$chainCache),
            'cached_paths' => array_keys(self::$chainCache),
            'cache_size_bytes' => strlen(serialize(self::$chainCache))
        ];
    }
    
    /**
     * String representation for debugging
     * 
     * @return string Current path or debug info
     */
    public function __toString(): string
    {
        if ($this->debugMode) {
            return json_encode($this->debug(), JSON_PRETTY_PRINT);
        }
        
        return $this->getCurrentPath() ?: 'EmptyModelChain';
    }
    
    /**
     * Handle property access (alternative to method chaining)
     * Allows syntax like $this->refModels()->Planning->Projects
     * 
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->__call($name, []);
    }
    
    /**
     * Check if property exists (for isset() calls)
     * 
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $tempSegments = $this->pathSegments;
        $tempSegments[] = $name;
        $tempPath = implode('\\', $tempSegments);
        
                 return $this->isValidModelPath($tempPath);
     }
}

/**
 * ParamsChainProxy - Proxy class for chaining params controller access
 * Enables syntax like $this->refParams()->Manager()->Drive()
 * 
 * Usage:
 * $DriveManager = $this->refParams()->Manager()->Drive();
 * $AccessGeneral = $this->refParams()->Access()->General();
 * $UserController = $this->refParams()->User();
 * 
 * Features:
 * - Supports unlimited chaining depth
 * - Automatic path building for Admin controllers
 * - Caching for performance
 * - Error handling for invalid paths
 * - Debug information
 */
/**
 * Smart wrapper class for model instances to handle method calls and auto-reset
 */
class SmartModelWrapper
{
    private object $model;
    private ModelChainProxy $proxy;
    
    public function __construct(object $model, ModelChainProxy $proxy)
    {
        $this->model = $model;
        $this->proxy = $proxy;
    }
    
    /**
     * Handle method calls - both chaining and direct method calls
     */
    public function __call(string $name, array $arguments): mixed
    {
        // Check if this is a method call on the model
        if (method_exists($this->model, $name)) {
            $result = $this->model->$name(...$arguments);
            
            // Reset proxy path segments for next chaining call
            $this->proxy->reset();
            
            return $result;
        }
        
        // If not a method on the model, treat as chaining
        // Reset current proxy to clear any previous path segments
        $this->proxy->reset();
        
        // Use the current proxy to handle chaining
        return $this->proxy->__call($name, $arguments);
    }
    
    /**
     * Forward property access to the wrapped model
     */
    public function __get(string $name): mixed
    {
        return $this->model->$name;
    }
    
    /**
     * Forward property setting to the wrapped model
     */
    public function __set(string $name, mixed $value): void
    {
        $this->model->$name = $value;
    }
    
    /**
     * Forward isset checks to the wrapped model
     */
    public function __isset(string $name): bool
    {
        return isset($this->model->$name);
    }
    
    /**
     * Forward unset to the wrapped model
     */
    public function __unset(string $name): void
    {
        unset($this->model->$name);
    }
}

class SmartParamsWrapper
{
    private object $controller;
    private ParamsChainProxy $proxy;
    
    public function __construct(object $controller, ParamsChainProxy $proxy)
    {
        $this->controller = $controller;
        $this->proxy = $proxy;
    }
    
    public function __call(string $name, array $arguments): mixed
    {
        // Check if this is a chaining call (for another controller)
        if ($this->proxy->isValidControllerPath($name)) {
            // Reset current proxy to clear any previous path segments
            $this->proxy->reset();
            // Use the current proxy to handle chaining
            return $this->proxy->__call($name, $arguments);
        }
        
        // Call method on current controller instance
        if (method_exists($this->controller, $name)) {
            return $this->controller->$name(...$arguments);
        }
        
        throw new \BadMethodCallException("Method '{$name}' not found on controller " . get_class($this->controller));
    }
    
    public function __get(string $name): mixed
    {
        return $this->controller->$name ?? null;
    }
    
    public function __set(string $name, mixed $value): void
    {
        $this->controller->$name = $value;
    }
    
    public function __isset(string $name): bool
    {
        return isset($this->controller->$name);
    }
    
    public function __unset(string $name): void
    {
        unset($this->controller->$name);
    }
}

class ParamsChainProxy
{
    private NexaController $controller;
    private array $pathSegments = [];
    private static array $chainCache = [];
    private bool $debugMode = false;
    
    public function __construct(NexaController $controller, bool $debugMode = false)
    {
        $this->controller = $controller;
        $this->debugMode = $debugMode;
    }
    
    /**
     * Magic method to handle chaining calls
     * Each method call adds a segment to the path
     * 
     * @param string $name Method name (becomes path segment)
     * @param array $arguments Method arguments (unused in chaining)
     * @return mixed ParamsChainProxy for chaining or SmartParamsWrapper if final
     */
    public function __call(string $name, array $arguments): mixed
    {
        // Add segment to path
        $this->pathSegments[] = $name;
        
        // Build current path
        $currentPath = implode('/', $this->pathSegments);
        
        // Check if this is a valid controller path (try to resolve it)
        if ($this->isValidControllerPath($currentPath)) {
            // This is a valid final path, return wrapped controller instance
            $controllerInstance = $this->resolveController($currentPath);
            return new SmartParamsWrapper($controllerInstance, $this);
        }
        
        // Continue chaining
        return $this;
    }
    
    /**
     * Check if the current path represents a valid controller
     * 
     * @param string $path Controller path to check
     * @return bool True if valid controller exists
     */
    public function isValidControllerPath(string $path): bool
    {
        // Create cache key for path validation
        $cacheKey = 'params_path_validation_' . md5($path);
        
        if (isset(self::$chainCache[$cacheKey])) {
            return self::$chainCache[$cacheKey];
        }
        
        try {
            // Try direct path first
            $className = $this->buildControllerClassName($path);
            if (class_exists($className)) {
                self::$chainCache[$cacheKey] = true;
                return true;
            }
            
            // If path doesn't contain backslash, try common namespaces
            if (strpos($path, '\\') === false) {
                $commonNamespaces = ['Admin', 'Api', 'Frontend'];
                foreach ($commonNamespaces as $namespace) {
                    $namespacedPath = $namespace . '\\' . $path;
                    $className = $this->buildControllerClassName($namespacedPath);
                    if (class_exists($className)) {
                        self::$chainCache[$cacheKey] = true;
                        return true;
                    }
                }
            }
            
            // Cache negative result
            self::$chainCache[$cacheKey] = false;
            return false;
            
        } catch (\Exception $e) {
            // Cache negative result
            self::$chainCache[$cacheKey] = false;
            return false;
        }
    }
    
    /**
     * Build controller class name from path (same logic as NexaController)
     * 
     * @param string $controllerPath Controller path
     * @return string Full class name
     */
    private function buildControllerClassName(string $controllerPath): string
    {
        // Convert path separators to namespace separators
        $namespacePath = str_replace(['/', '\\'], '\\', trim($controllerPath, '/\\'));
        $pathParts = explode('\\', $namespacePath);
        $className = array_pop($pathParts);
        
        // Build full namespace for Admin controllers
        $namespace = 'App\\Controllers\\Admin';
        if (!empty($pathParts)) {
            $namespace .= '\\' . implode('\\', $pathParts);
        }
        
        return $namespace . '\\' . $className;
    }
    
    /**
     * Resolve controller instance using controller's refParams method
     * 
     * @param string $controllerPath Controller path to resolve
     * @return object Controller instance
     */
    private function resolveController(string $controllerPath): object
    {
        // Try direct path first
        try {
            $reflection = new \ReflectionMethod($this->controller, 'refParamsOriginal');
            $reflection->setAccessible(true);
            return $reflection->invoke($this->controller, $controllerPath);
        } catch (\Exception $e) {
            // If direct path fails and path doesn't contain backslash, try common namespaces
            if (strpos($controllerPath, '\\') === false) {
                $commonNamespaces = ['Admin', 'Api', 'Frontend'];
                foreach ($commonNamespaces as $namespace) {
                    try {
                        $namespacedPath = $namespace . '\\' . $controllerPath;
                        $reflection = new \ReflectionMethod($this->controller, 'refParamsOriginal');
                        $reflection->setAccessible(true);
                        return $reflection->invoke($this->controller, $namespacedPath);
                    } catch (\Exception $namespaceException) {
                        // Continue to next namespace
                        continue;
                    }
                }
            }
            
            // If all attempts fail, throw the original exception
            throw $e;
        }
    }
    
    /**
     * Force resolution of current path as controller
     * Useful when you want to force resolution even if path validation fails
     * 
     * @return object Controller instance
     * @throws \Exception If controller cannot be resolved
     */
    public function resolve(): object
    {
        $currentPath = implode('/', $this->pathSegments);
        
        if (empty($currentPath)) {
            throw new \BadMethodCallException('Cannot resolve empty controller path. Use chaining like ->Manager()->Drive()');
        }
        
        return $this->resolveController($currentPath);
    }
    
    /**
     * Get current path being built
     * 
     * @return string Current controller path
     */
    public function getCurrentPath(): string
    {
        return implode('/', $this->pathSegments);
    }
    
    /**
     * Get path segments array
     * 
     * @return array Path segments
     */
    public function getPathSegments(): array
    {
        return $this->pathSegments;
    }
    
    /**
     * Reset path and start fresh
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->pathSegments = [];
        return $this;
    }
    
    /**
     * Debug information about current chain state
     * 
     * @return array Debug information
     */
    public function debug(): array
    {
        $currentPath = $this->getCurrentPath();
        
        return [
            'current_path' => $currentPath,
            'path_segments' => $this->pathSegments,
            'segments_count' => count($this->pathSegments),
            'is_valid_path' => $this->isValidControllerPath($currentPath),
            'expected_class' => $this->buildControllerClassName($currentPath),
            'class_exists' => class_exists($this->buildControllerClassName($currentPath)),
            'cache_stats' => [
                'total_cached_validations' => count(self::$chainCache),
                'cached_paths' => array_keys(self::$chainCache)
            ]
        ];
    }
    
    /**
     * Enable debug mode for verbose output
     * 
     * @param bool $enable Enable or disable debug mode
     * @return self
     */
    public function debugMode(bool $enable = true): self
    {
        $this->debugMode = $enable;
        return $this;
    }
    
    /**
     * Clear chain cache
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$chainCache = [];
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function getCacheStats(): array
    {
        return [
            'total_entries' => count(self::$chainCache),
            'cached_paths' => array_keys(self::$chainCache),
            'cache_size_bytes' => strlen(serialize(self::$chainCache))
        ];
    }
    
    /**
     * String representation for debugging
     * 
     * @return string Current path or debug info
     */
    public function __toString(): string
    {
        if ($this->debugMode) {
            return json_encode($this->debug(), JSON_PRETTY_PRINT);
        }
        
        return $this->getCurrentPath() ?: 'EmptyParamsChain';
    }
    
    /**
     * Handle property access (alternative to method chaining)
     * Allows syntax like $this->refParams()->Manager->Drive
     * 
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->__call($name, []);
    }
    
    /**
     * Check if property exists (for isset() calls)
     * 
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $tempSegments = $this->pathSegments;
        $tempSegments[] = $name;
        $tempPath = implode('/', $tempSegments);
        
        return $this->isValidControllerPath($tempPath);
    }
    
    // ====================================================================
    // NEXAINDEXDB INTEGRATION METHODS
    // ====================================================================
    
    /**
     * Get or create a NexaIndexDB database instance
     * 
     * @param string $dbName Database name
     * @param int $version Database version
     * @param string|null $dbPath Custom database path
     * @return NexaIndexDB
     */
    protected function database(string $dbName, int $version = 1, ?string $dbPath = null): NexaIndexDB
    {
        $cacheKey = $dbName . '_v' . $version . '_' . ($dbPath ?? 'default');
        
        if (!isset($this->databases[$cacheKey])) {
            $this->databases[$cacheKey] = new NexaIndexDB($dbName, $version, $dbPath);
        }
        
        return $this->databases[$cacheKey];
    }
    
    /**
     * Get the default application database
     * 
     * @return NexaIndexDB
     */
    protected function db(): NexaIndexDB
    {
        return $this->database('app_database');
    }
    
    /**
     * Create a new object store in the default database
     * 
     * @param string $storeName Name of the object store
     * @param array $options Store configuration options
     * @return mixed Object store instance
     */
    protected function createStore(string $storeName, array $options = []): mixed
    {
        $db = $this->db();
        return $db->createObjectStore($storeName, $options);
    }
    
    /**
     * Get an existing object store from the default database
     * 
     * @param string $storeName Name of the object store
     * @return mixed Object store instance
     */
    protected function getStore(string $storeName): mixed
    {
        $db = $this->db();
        return $db->getObjectStore($storeName);
    }
    
    /**
     * Delete an object store from the default database
     * 
     * @param string $storeName Name of the object store to delete
     * @return self
     */
    protected function deleteStore(string $storeName): self
    {
        $db = $this->db();
        $db->deleteObjectStore($storeName);
        return $this;
    }
    
    /**
     * Start a database transaction
     * 
     * @param array|string $storeNames Store names to include in transaction
     * @param string $mode Transaction mode ('readonly' or 'readwrite')
     * @param string|null $dbName Custom database name (optional)
     * @return mixed Transaction instance
     */
    protected function transaction($storeNames, string $mode = 'readonly', ?string $dbName = null): mixed
    {
        $db = $dbName ? $this->database($dbName) : $this->db();
        return $db->transaction($storeNames, $mode);
    }
    
    /**
     * Quick data storage - store data in a default 'data' store
     * 
     * @param mixed $data Data to store
     * @param string|null $key Optional key (auto-generated if null)
     * @return mixed The key of the stored data
     */
    protected function storeData($data, ?string $key = null): mixed
    {
        try {
            $store = $this->getStore('data');
        } catch (Exception $e) {
            // Create the store if it doesn't exist
            $store = $this->createStore('data', [
                'keyPath' => 'id',
                'autoIncrement' => true
            ]);
        }
        
        return $store->add($data, $key);
    }
    
    /**
     * Quick data retrieval - get data from default 'data' store
     * 
     * @param string|int $key Key to retrieve
     * @return mixed Retrieved data or null
     */
    protected function getData($key): mixed
    {
        try {
            $store = $this->getStore('data');
            return $store->get($key);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Quick data update - update data in default 'data' store
     * 
     * @param mixed $data Data to update
     * @param string|null $key Optional key
     * @return mixed The key of the updated data
     */
    protected function updateData($data, ?string $key = null): mixed
    {
        try {
            $store = $this->getStore('data');
        } catch (Exception $e) {
            $store = $this->createStore('data', [
                'keyPath' => 'id',
                'autoIncrement' => true
            ]);
        }
        
        return $store->put($data, $key);
    }
    
    /**
     * Quick data deletion - delete data from default 'data' store
     * 
     * @param string|int $key Key to delete
     * @return bool Success status
     */
    protected function deleteData($key): bool
    {
        try {
            $store = $this->getStore('data');
            return $store->delete($key);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all data from a store
     * 
     * @param string $storeName Store name (defaults to 'data')
     * @param string|null $query Optional search query
     * @param int|null $limit Optional result limit
     * @return array Array of results
     */
    protected function getAllData(string $storeName = 'data', ?string $query = null, ?int $limit = null): array
    {
        try {
            $store = $this->getStore($storeName);
            return $store->getAll($query, $limit);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Count records in a store
     * 
     * @param string $storeName Store name (defaults to 'data')
     * @return int Record count
     */
    protected function countData(string $storeName = 'data'): int
    {
        try {
            $store = $this->getStore($storeName);
            return $store->count();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Clear all data from a store
     * 
     * @param string $storeName Store name (defaults to 'data')
     * @return int Number of deleted records
     */
    protected function clearStore(string $storeName = 'data'): int
    {
        try {
            $store = $this->getStore($storeName);
            return $store->clear();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Create an index on a store
     * 
     * @param string $storeName Store name
     * @param string $indexName Index name
     * @param string $keyPath Field to index
     * @param array $options Index options
     * @return self
     */
    protected function createIndex(string $storeName, string $indexName, string $keyPath, array $options = []): self
    {
        try {
            $store = $this->getStore($storeName);
            $store->createIndex($indexName, $keyPath, $options);
        } catch (Exception $e) {
            // Handle error silently or log it
        }
        
        return $this;
    }
    
    /**
     * Query data by index
     * 
     * @param string $storeName Store name
     * @param string $indexName Index name
     * @param mixed $value Value to search for
     * @return array Array of matching records
     */
    protected function queryByIndex(string $storeName, string $indexName, $value): array
    {
        try {
            $store = $this->getStore($storeName);
            return $store->getByIndex($indexName, $value);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Store user-specific data
     * 
     * @param mixed $data Data to store
     * @param string|null $key Optional key
     * @return mixed The key of the stored data
     */
    protected function storeUserData($data, ?string $key = null): mixed
    {
        $userId = $this->getUserId() ?: 'guest';
        $storeName = 'user_data_' . $userId;
        
        try {
            $store = $this->getStore($storeName);
        } catch (Exception $e) {
            $store = $this->createStore($storeName, [
                'keyPath' => 'id',
                'autoIncrement' => true
            ]);
        }
        
        return $store->add($data, $key);
    }
    
    /**
     * Get user-specific data
     * 
     * @param string|int $key Key to retrieve
     * @return mixed Retrieved data or null
     */
    protected function getUserData($key): mixed
    {
        $userId = $this->getUserId() ?: 'guest';
        $storeName = 'user_data_' . $userId;
        
        try {
            $store = $this->getStore($storeName);
            return $store->get($key);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get all user-specific data
     * 
     * @param string|null $query Optional search query
     * @param int|null $limit Optional result limit
     * @return array Array of results
     */
    protected function getAllUserData(?string $query = null, ?int $limit = null): array
    {
        $userId = $this->getUserId() ?: 'guest';
        $storeName = 'user_data_' . $userId;
        
        return $this->getAllData($storeName, $query, $limit);
    }
    
    /**
     * Close a specific database connection
     * 
     * @param string|null $dbName Database name (null for all)
     * @return self
     */
    protected function closeDatabase(?string $dbName = null): self
    {
        if ($dbName === null) {
            // Close all databases
            foreach ($this->databases as $db) {
                $db->close();
            }
            $this->databases = [];
        } else {
            // Close specific database
            foreach ($this->databases as $key => $db) {
                if (strpos($key, $dbName . '_') === 0) {
                    $db->close();
                    unset($this->databases[$key]);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Get database information
     * 
     * @param string|null $dbName Database name (null for default)
     * @return array Database information
     */
    protected function getDatabaseInfo(?string $dbName = null): array
    {
        $db = $dbName ? $this->database($dbName) : $this->db();
        return $db->getInfo();
    }
    
    /**
     * Execute a database operation with automatic transaction handling
     * 
     * @param array|string $storeNames Store names
     * @param callable $operation Operation to execute
     * @param string $mode Transaction mode
     * @param string|null $dbName Database name
     * @return mixed Operation result
     * @throws Exception If operation fails
     */
    protected function withTransaction($storeNames, callable $operation, string $mode = 'readwrite', ?string $dbName = null): mixed
    {
        $transaction = $this->transaction($storeNames, $mode, $dbName);
        
        try {
            $result = $operation($transaction);
            $transaction->commit();
            return $result;
        } catch (Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }
    
    /**
     * Batch operations helper
     * 
     * @param string $storeName Store name
     * @param array $operations Array of operations
     * @param string|null $dbName Database name
     * @return array Results array
     */
    protected function batchOperations(string $storeName, array $operations, ?string $dbName = null): array
    {
        return $this->withTransaction($storeName, function($transaction) use ($storeName, $operations) {
            $store = $transaction->objectStore($storeName);
            $results = [];
            
            foreach ($operations as $operation) {
                $method = $operation['method'] ?? 'add';
                $data = $operation['data'] ?? null;
                $key = $operation['key'] ?? null;
                
                switch ($method) {
                    case 'add':
                        $results[] = $store->add($data, $key);
                        break;
                    case 'put':
                        $results[] = $store->put($data, $key);
                        break;
                    case 'delete':
                        $results[] = $store->delete($key ?? $data);
                        break;
                    default:
                        $results[] = null;
                }
            }
            
            return $results;
        }, 'readwrite', $dbName);
    }
    
    /**
     * Debug database status
     * 
     * @return array Debug information
     */
    protected function debugDatabase(): array
    {
        $debug = [
            'active_databases' => count($this->databases),
            'database_info' => []
        ];
        
        foreach ($this->databases as $key => $db) {
            $debug['database_info'][$key] = $db->getInfo();
        }
        
        return $debug;
    }
    
    /**
     * Initialize common stores for the application
     * 
     * @return self
     */
    protected function initializeCommonStores(): self
    {
        // Create common stores if they don't exist
        $commonStores = [
            'users' => ['keyPath' => 'id', 'autoIncrement' => true],
            'sessions' => ['keyPath' => 'session_id', 'autoIncrement' => false],
            'logs' => ['keyPath' => 'id', 'autoIncrement' => true],
            'cache' => ['keyPath' => 'key', 'autoIncrement' => false],
            'data' => ['keyPath' => 'id', 'autoIncrement' => true]
        ];
        
        $db = $this->db();
        foreach ($commonStores as $storeName => $options) {
            try {
                $db->getObjectStore($storeName);
            } catch (Exception $e) {
                $db->createObjectStore($storeName, $options);
            }
        }
        
        return $this;
    }
    
    /**
     * Store controller state in database
     * 
     * @param string $key State key
     * @param mixed $value State value
     * @return mixed Storage key
     */
    protected function storeControllerState(string $key, $value): mixed
    {
        $controllerName = $this->getControllerName();
        $stateData = [
            'controller' => $controllerName,
            'key' => $key,
            'value' => $value,
            'timestamp' => time(),
            'user_id' => $this->getUserId()
        ];
        
        return $this->storeData($stateData);
    }
    
    /**
     * Get controller state from database
     * 
     * @param string $key State key
     * @param mixed $default Default value
     * @return mixed State value or default
     */
    protected function getControllerState(string $key, $default = null): mixed
    {
        $controllerName = $this->getControllerName();
        $userId = $this->getUserId();
        
        $allStates = $this->getAllData('data');
        foreach ($allStates as $state) {
            if (isset($state['controller']) && 
                isset($state['key']) && 
                isset($state['user_id']) &&
                $state['controller'] === $controllerName &&
                $state['key'] === $key &&
                $state['user_id'] === $userId) {
                return $state['value'];
            }
        }
        
        return $default;
    }

    /**
     * ========================================================================
     * FITUR BARU: NESTED BLOCKS SUPPORT - Wrapper Methods
     * ========================================================================
     */
    
    /**
     * Assign nested blocks - mendukung array bersarang
     * 
     * Contoh penggunaan:
     * 
     * $produk = [
     *     ['id' => 1, 'nama' => 'Laptop', 'items' => [
     *         ['kode' => 'LP-01', 'warna' => 'Hitam'],
     *         ['kode' => 'LP-02', 'warna' => 'Silver']
     *     ]],
     *     ['id' => 2, 'nama' => 'Mouse', 'items' => [
     *         ['kode' => 'MS-01', 'warna' => 'Hitam']
     *     ]]
     * ];
     * 
     * $this->assignNestedBlocks('produk', $produk, 'items');
     * 
     * Template:
     * <!-- BEGIN produk -->
     *   <h3>{produk.nama}</h3>
     *   <!-- BEGIN produk_items -->
     *     <li>{produk_items.kode} - {produk_items.warna}</li>
     *   <!-- END produk_items -->
     * <!-- END produk -->
     * 
     * @param string $parentBlock Nama parent block
     * @param array $data Array data dengan nested array
     * @param string $nestedKey Key yang berisi nested array
     * @param string $nestedBlockName Nama block untuk nested items (optional)
     * @return self
     */
    protected function assignNestedBlocks(string $parentBlock, array $data, string $nestedKey, string $nestedBlockName = ''): self
    {
        $this->template->assign_nested_blocks($parentBlock, $data, $nestedKey, $nestedBlockName);
        return $this;
    }
    
    /**
     * Assign multiple nested blocks dengan berbagai level
     * 
     * @param string $parentBlock Nama parent block
     * @param array $data Array data
     * @param array $nestedMap Map nested keys [blockName => dataKey]
     * @return self
     */
    protected function assignMultiNestedBlocks(string $parentBlock, array $data, array $nestedMap): self
    {
        $this->template->assign_multi_nested_blocks($parentBlock, $data, $nestedMap);
        return $this;
    }
    
    /**
     * Assign grouped nested blocks - untuk akses dot notation
     * 
     * @param string $parentBlock Nama parent block
     * @param array $data Array data dengan nested array
     * @param string $nestedKey Key yang berisi nested array
     * @return self
     */
    protected function assignGroupedNestedBlocks(string $parentBlock, array $data, string $nestedKey): self
    {
        $this->template->assign_grouped_nested_blocks($parentBlock, $data, $nestedKey);
        return $this;
    }
    
    /**
     * Debug nested blocks
     * 
     * @param string $blockName Nama block yang akan di-debug
     * @return array Debug information
     */
    protected function debugNestedBlocks(string $blockName = ''): array
    {
        return $this->template->debug_nested_blocks($blockName);
    }
}