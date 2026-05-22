<?php
namespace App\System\Helpers;
use App\System\Helpers\NexaValidation;
use App\System\Helpers\NexaFile;

/**
 * NexaForm - Form handling and validation class
 * 
 * Example Usage:
 * ```php
 * // Basic form creation with validation
 * $form = NexaForm::createForm()
 *     ->fields([
 *         'username' => 'required|min:3|max:20|alphaNum',
 *         'email' => 'required|email',
 *         'age' => 'required|numeric|min:18',
 *         'status' => 'required|in:active,pending,inactive',
 *         'bio' => 'required|min:10|max:1000',
 *         'profile_pic' => 'fileOptional|max:2048' // max 2MB
 *     ])
 *     ->setUpload([
 *         'path' => 'uploads/profiles',
 *         'allowed_types' => ['jpg', 'png'],
 *         'max_size' => 2048
 *     ])
 *     ->setSuccess('Profile updated successfully!')
 *     ->setError('Please check the form errors.');
 * 
 * // Process the form
 * $result = $form->process();
 * 
 * // Handle the result
 * if ($result['success']) {
 *     // Access validated data
 *     $validData = $result['data'];
 *     
 *     // Access uploaded files info
 *     $files = $result['files'] ?? [];
 *     
 *     // Do something with the data...
 * } else {
 *     // Get validation errors
 *     $errors = $result['errors'];
 * }
 * 
 * // Available Validation Rules:
 * // - required            : Field wajib diisi
 * // - min:x              : Minimum x karakter untuk string atau nilai minimum untuk angka
 * // - max:x              : Maximum x karakter untuk string atau nilai maximum untuk angka
 * // - email              : Validasi format email
 * // - numeric            : Harus berupa angka
 * // - alpha              : Hanya boleh berisi huruf
 * // - alphaNum           : Hanya boleh berisi huruf dan angka
 * // - in:val1,val2,val3  : Nilai harus salah satu dari yang ditentukan
 * // - file               : File upload (required)
 * // - fileOptional       : File upload (optional)
 * // - password           : Validasi password
 * // - passwordConfirm    : Konfirmasi password
 * // - phone              : Validasi nomor telepon
 * // - json               : Validasi format JSON
 * 
 * // Example with AJAX:
 * $form = NexaForm::createForm()
 *     ->setAjax(true)
 *     ->fields([
 *         'title' => 'required|min:5|max:100',
 *         'content' => 'required|min:20',
 *         'category' => 'required|in:news,article,blog',
 *         'image' => 'fileOptional'
 *     ])
 *     ->setUpload([
 *         'path' => 'uploads/posts',
 *         'allowed_types' => ['jpg', 'png', 'gif'],
 *         'max_size' => 1024
 *     ]);
 * 
 * // Process akan otomatis mengirim JSON response untuk AJAX
 * $form->process();
 */
class NexaForm {
    use NexaValidation;
    
    private $nexaFile;
    private $validationRules = [];
    private $uploadConfig = [];
    private $successMessage = 'Form berhasil disubmit!';
    private $errorMessage = 'Terjadi kesalahan dalam pengisian form.';
    private $isAjax = false;
    private $controller = null;
    private $redirectUrl = null;
    private $callback = null;
    
    public function __construct($controller = null) {
        $this->nexaFile = new NexaFile();
        $this->controller = $controller;
    }
    
    /**
     * Create a new form instance
     * @return NexaForm
     */
    public static function createForm() {
        return new self();
    }
    
    /**
     * Set validation rules for form fields
     * @param array $fields
     * @return NexaForm
     */
    public function fields(array $fields) {
        $this->validationRules = $fields;
        return $this;
    }
    
    /**
     * Set upload configuration
     * @param array $config
     * @return NexaForm
     */
    public function setUpload(array $config) {
        $this->uploadConfig = $config;
        return $this;
    }
    
    /**
     * Set success message
     * @param string $message
     * @return NexaForm
     */
    public function setSuccess(string $message) {
        $this->successMessage = $message;
        return $this;
    }
    
    /**
     * Set error message
     * @param string $message
     * @return NexaForm
     */
    public function setError(string $message) {
        $this->errorMessage = $message;
        return $this;
    }
    
    /**
     * Set temporary data for validation (public access to trait method)
     * @param string|array $key Key or array of key-value pairs
     * @param mixed $value Value (if key is string)
     * @return NexaForm
     */
    public function setTempData($key, $value = null) {
        $this->setTemp($key, $value);
        return $this;
    }
    
        /**
     * Get temporary data (public access to trait method)
     * @param string|null $key Key to get (null for all data)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getTempData($key = null, $default = null) {
        return $this->getTemp($key, $default);
    }

    /**
     * Delete file using NexaFile helper
     * @param string|array $fileInfo File path or file info array
     * @return NexaForm
     */
    public function deleteFile($fileInfo) {
        if (!empty($fileInfo)) {
            try {
                $deleteResult = $this->nexaFile->deleteFile($fileInfo);
                
                // Store delete result for reference
                $this->setTemp('delete_result', $deleteResult);
                
                // Log successful deletion
                if ($deleteResult['main_file']) {
                    $filePath = is_string($fileInfo) ? $fileInfo : ($fileInfo['path'] ?? 'unknown');
                    if (!empty($filePath) && $filePath !== 'unknown') {
                        error_log("File deleted successfully: " . $filePath);
                    }
                }
                
                // Log any errors
                if (!empty($deleteResult['errors'])) {
                    foreach ($deleteResult['errors'] as $error) {
                        error_log("File deletion error: " . $error);
                    }
                }
                
            } catch (\Exception $e) {
                // Log error but don't throw exception to prevent form processing interruption
                error_log("Error deleting file: " . $e->getMessage());
                $this->setTemp('delete_error', $e->getMessage());
            }
        }
        
        return $this;
    }

    /**
     * Get file deletion result
     * @return array|null
     */
    public function getDeleteResult() {
        return $this->getTemp('delete_result');
    }

    /**
     * Get file deletion error
     * @return string|null
     */
    public function getDeleteError() {
        return $this->getTemp('delete_error');
    }

    /**
     * Process the form submission
     * @param bool $autoRedirect Whether to automatically redirect after successful submission
     * @return array Returns result data with success/error status
     */
    public function process($autoRedirect = true) {
        // Only process POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'processed' => false,
                'informasi' => 'no_submission',
                'message' => 'No form submission detected',
                'errors' => []
            ];
        }
        
        try {
            // Parse JSON input if present
            $this->parseJsonInput();
            
            // Log POST data for debugging
            error_log("POST data received: " . json_encode($_POST));
            
            // Sanitize POST data
            $this->sanitizePostData();
            
            // Get fields based on validation rules
            $fields = $this->getFields($this->validationRules);
            
            // Validate all fields
            if (!$this->validateAll($this->validationRules)) {
                $errors = $this->getErrors();
                if (empty($errors)) {
                    $errors = ['Validation failed. Please check your input.'];
                }
                
                // Deteksi jenis input yang disubmit untuk error handling
                $hasRegularData = $this->hasRegularData();
                $hasFileUploads = $this->hasFileUploads();
                
                $inputInfo = '';
                if ($hasRegularData && $hasFileUploads) {
                    $inputInfo = 'file';
                } elseif ($hasFileUploads) {
                    $inputInfo = 'file';
                } elseif ($hasRegularData) {
                    $inputInfo = 'data';
                } else {
                    $inputInfo = 'tidak_ada_input';
                }
                
                $result = [
                    'success' => false,
                    'processed' => true,
                    'informasi' => $inputInfo,
                    'message' => $this->errorMessage,
                    'errors' => $errors,
                    'data' => $this->getValidData(),
                    'postData' => $this->getPostValue()
                ];
                
                // Auto-handle AJAX response for validation errors
                if ($this->isAjax && $this->isAjaxRequest()) {
                    $this->sendJsonResponse($result);
                }
                
                return $result;
            }
            
            // Deteksi jenis input yang disubmit
            $hasRegularData = $this->hasRegularData();
            $hasFileUploads = $this->hasFileUploads();
            
            $inputInfo = '';
            if ($hasRegularData && $hasFileUploads) {
                $inputInfo = 'file';
            } elseif ($hasFileUploads) {
                $inputInfo = 'file';
            } elseif ($hasRegularData) {
                $inputInfo = 'data';
            } else {
                $inputInfo = 'tidak_ada_input';
            }
            
            $result = [
                'success' => true,
                'processed' => true,
                'informasi' => $inputInfo,
                'data' => $this->getValidData(),
                'postData' => $this->getPostValue(),
                'message' => $this->successMessage,
                'errors' => []
            ];
            
            // Add dataId to result if present in POST data
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result['data']['id'] = $_POST['id'];
            }
            
            // Add redirect URL if set
            if ($this->redirectUrl) {
                $result['redirect'] = $this->redirectUrl;
            }
            
            // Handle file upload if configuration is set
            if (!empty($this->uploadConfig) && $this->hasFileUploads()) {
                try {
                    $fileResults = $this->handleFileUploads();
                    $result['files'] = $fileResults;
                    
                    // Untuk field dengan tipe File/FileOptional, set nilai dengan "path" dari upload result
                    foreach ($this->validationRules as $fieldName => $rules) {
                        if (is_string($rules)) {
                            $parts = explode('|', $rules, 3);
                            $validationFunction = strtolower($parts[0]);
                        } elseif (is_array($rules)) {
                            $validationFunction = strtolower($rules[0]);
                        } else {
                            continue;
                        }
                        
                        // Jika field bertipe File atau FileOptional dan ada hasil upload
                        if (($validationFunction === 'file' || $validationFunction === 'fileoptional') && 
                            isset($fileResults[$fieldName]) && isset($fileResults[$fieldName]['path'])) {
                            // Set nilai field dengan path dari upload result (untuk database)
                            $result['data'][$fieldName] = $fileResults[$fieldName]['path'];
                        }
                    }
                    
                    // Gabungkan metadata upload KECUALI field "path" (karena sudah ada di field file)
                    foreach ($fileResults as $fieldName => $fileData) {
                        foreach ($fileData as $key => $value) {
                            if ($key !== 'path') {  // Jangan sertakan "path" karena sudah ada di field file
                                $result['data'][$key] = $value;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Deteksi jenis input untuk file upload error
                    $hasRegularData = $this->hasRegularData();
                    $hasFileUploads = $this->hasFileUploads();
                    
                    $inputInfo = '';
                    if ($hasRegularData && $hasFileUploads) {
                        $inputInfo = 'file';
                    } elseif ($hasFileUploads) {
                        $inputInfo = 'file';
                    } elseif ($hasRegularData) {
                        $inputInfo = 'data';
                    } else {
                        $inputInfo = 'tidak_ada_input';
                    }
                    
                    $errorResult = [
                        'success' => false,
                        'processed' => true,
                        'informasi' => $inputInfo,
                        'message' => 'Error upload file: ' . $e->getMessage(),
                        'errors' => ['file_upload' => 'Error upload file: ' . $e->getMessage()],
                        'data' => $this->getValidData(),
                        'postData' => $this->getPostValue()
                    ];
                    
                    // Auto-handle AJAX response for file upload errors
                    if ($this->isAjax && $this->isAjaxRequest()) {
                        $this->sendJsonResponse($errorResult);
                    }
                    
                    return $errorResult;
                }
            }
            
            // Auto-handle AJAX response for success
            if ($this->isAjax && $this->isAjaxRequest()) {
                $this->sendJsonResponse($result);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // Deteksi jenis input untuk system error
            $hasRegularData = $this->hasRegularData();
            $hasFileUploads = $this->hasFileUploads();
            
            $inputInfo = '';
            if ($hasRegularData && $hasFileUploads) {
                $inputInfo = 'file';
            } elseif ($hasFileUploads) {
                $inputInfo = 'file';
            } elseif ($hasRegularData) {
                $inputInfo = 'data';
            } else {
                $inputInfo = 'tidak_ada_input';
            }
            
            $errorResult = [
                'success' => false,
                'processed' => true,
                'informasi' => $inputInfo,
                'message' => 'Error: ' . $e->getMessage(),
                'errors' => ['system' => 'Error: ' . $e->getMessage()],
                'data' => [],
                'postData' => $this->getPostValue()
            ];
            
            // Auto-handle AJAX response for system errors
            if ($this->isAjax && $this->isAjaxRequest()) {
                $this->sendJsonResponse($errorResult);
            }
            
            return $errorResult;
        }
    }
    
    /**
     * Send JSON response and exit
     * @param array $data Response data
     * @return void
     */
    private function sendJsonResponse(array $data) {
        // Execute callback if set and request was successful
        if ($this->callback && $data['success']) {
            $callbackResult = call_user_func($this->callback, $data['data']);
            if ($callbackResult !== false) {
                $data['callback_result'] = $callbackResult;
            }
        }

        // Set JSON headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        http_response_code(200); // Always use 200 to avoid console errors
        
        // Convert data to JSON
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Send response and exit
        echo $jsonData;
        exit;
    }
    
    /**
     * Check if form has regular data input (non-file fields)
     * @return bool
     */
    private function hasRegularData() {
        // Cek apakah ada data POST yang bukan file
        if (empty($_POST)) {
            return false;
        }
        
        // Filter data POST untuk mendapatkan field non-file berdasarkan validation rules
        $regularFields = [];
        foreach ($this->validationRules as $fieldName => $rules) {
            if (is_string($rules)) {
                $parts = explode('|', $rules, 3);
                $validationFunction = strtolower($parts[0]);
            } elseif (is_array($rules)) {
                $validationFunction = strtolower($rules[0]);
            } else {
                continue;
            }
            
            // Jika bukan field file dan ada di POST data
            if ($validationFunction !== 'file' && $validationFunction !== 'fileoptional') {
                if (isset($_POST[$fieldName]) && $_POST[$fieldName] !== '') {
                    $regularFields[] = $fieldName;
                }
            }
        }
        
        return !empty($regularFields);
    }
    
    /**
     * Check if form has file uploads
     * @return bool
     */
    private function hasFileUploads() {
        return !empty($_FILES) && count(array_filter($_FILES, function($file) {
            return $file['error'] !== UPLOAD_ERR_NO_FILE;
        })) > 0;
    }
    
    /**
     * Handle multiple file uploads
     * @return array Returns array with fieldName => upload_result mapping
     */
    private function handleFileUploads() {
        $fileResults = [];
        
        foreach ($_FILES as $fieldName => $fileData) {
            if ($fileData['error'] !== UPLOAD_ERR_NO_FILE) {
                $fileResult = $this->nexaFile->handleFileUpload($fileData, $this->uploadConfig);
                $fileResults[$fieldName] = $fileResult;
            }
        }
        
        return $fileResults;
    }
    
    /**
     * Get template variables from NexaValidation trait
     * @param array $additionalVars Additional variables to merge
     * @param bool $clearValues Whether to clear field values (useful after successful submission)
     * @return array
     */
    public function Response($additionalVars = [], $clearValues = false) {
        // Langsung panggil trait method dengan cara yang benar
        $templateVars = [];
        $errors = $this->getErrors();
        
        foreach ($this->validationRules as $fieldName => $rules) {
            $validationFunction = strtolower($rules[0]);
            // Add field value (for regular input fields, not files)
            if ($validationFunction !== 'file' && $validationFunction !== 'fileoptional' && !str_contains($fieldName, 'file')) {
                // Kosongkan field values jika $clearValues = true (setelah sukses submit)
                $templateVars[$fieldName] = $clearValues ? '' : htmlspecialchars($this->getPostValue($fieldName) ?? '');
                $templateVars[$fieldName . '_value'] = $clearValues ? '' : htmlspecialchars($this->getPostValue($fieldName) ?? '');
            }
            
            // Add error message
            $templateVars['errors_' . $fieldName] = $errors['errors_' . $fieldName] ?? '';
        }
        
        // Handle file info for file fields
        foreach ($this->validationRules as $fieldName => $rules) {
            if (is_string($rules)) {
                $parts = explode('|', $rules, 3);
                $validationFunction = strtolower($parts[0]);
            } elseif (is_array($rules)) {
                $validationFunction = strtolower($rules[0]);
            } else {
                continue;
            }
            
            if ($validationFunction === 'file' || $validationFunction === 'fileoptional') {
                $templateVars[$fieldName . '_info'] = $this->getFileInfo($fieldName);
            }
        }
        
        // Add single info message based on validation status
        $hasErrors = !empty($errors) && array_filter($errors, function($error) { return !empty($error); });
        $templateVars['info_message'] = $hasErrors ? $this->errorMessage : $this->successMessage;
        
        // Merge with additional variables if provided
        return array_merge($templateVars, $additionalVars);
    }
    
    /**
     * Get file upload information message
     */
    private function getFileInfo($fieldName) {
        // Cek apakah ada file yang berhasil diupload (tanpa error)
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $fileName = htmlspecialchars($_FILES[$fieldName]['name']);
            $fileSize = number_format($_FILES[$fieldName]['size'] / 1024, 2);
            return '<small style="color: green;"><em>✓ File berhasil dipilih: "' . $fileName . '" (' . $fileSize . ' KB)</em></small>';
        }
        
        // Jika ada error validasi, tampilkan informasi error
        $errors = $this->getErrors();
        if (!empty($errors)) {
            $previousFile = $this->getTemp('previous_file_' . $fieldName);
            
            if (!empty($previousFile)) {
                return '<small style="color: blue;"><em>File sebelumnya: "' . htmlspecialchars($previousFile) . '" - Pilih ulang file yang sama</em></small>';
            } else {
                return '<small style="color: orange;"><em>* File perlu dipilih ulang jika ada error validasi</em></small>';
            }
        }
        
        // Jika tidak ada file dan tidak ada error, tampilkan pesan netral
        return '';
    }
    
    /**
     * Get current validation errors
     * @return array
     */
    public function getCurrentErrors() {
        return $this->getErrors();
    }

    /**
     * Enable AJAX mode - will automatically handle JSON responses
     * @param bool $enabled
     * @return NexaForm
     */
    public function setAjax(bool $enabled = true) {
        $this->isAjax = $enabled;
        return $this;
    }
    
    /**
     * Enable JSON mode - alias for setAjax for clarity when using JSON requests
     * @param bool $enabled
     * @return NexaForm
     */
    public function setJson(bool $enabled = true) {
        return $this->setAjax($enabled);
    }
    
    /**
     * Set redirect URL for successful submissions
     * @param string $url
     * @return NexaForm
     */
    public function setRedirect(string $url) {
        $this->redirectUrl = $url;
        return $this;
    }
    
    /**
     * Set a callback function to be executed before sending AJAX response
     * @param callable $callback Function that receives validated data and returns processed data
     * @return NexaForm
     */
    public function setCallback(callable $callback) {
        $this->callback = $callback;
        return $this;
    }
    
    /**
     * Check if current request is AJAX or JSON
     * @return bool
     */
    public function isAjaxRequest() {
        // Check traditional AJAX header
        $isXmlHttpRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        // Check JSON content type
        $isJsonRequest = $this->isJsonRequest();
        
        return $isXmlHttpRequest || $isJsonRequest;
    }
    
    /**
     * Check if current request has JSON content type
     * @return bool
     */
    public function isJsonRequest() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return strpos(strtolower($contentType), 'application/json') !== false;
    }
    
    /**
     * Parse JSON input data and merge with $_POST
     * @return void
     */
    private function parseJsonInput() {
        if ($this->isJsonRequest()) {
            $jsonInput = file_get_contents('php://input');
            if (!empty($jsonInput)) {
                $decodedData = json_decode($jsonInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                    // Merge JSON data with existing POST data
                    $_POST = array_merge($_POST, $decodedData);
                }
            }
        }
    }
}
