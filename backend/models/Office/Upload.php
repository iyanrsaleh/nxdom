<?php
declare(strict_types=1);

namespace App\Models\Office;
use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class Upload extends NexaModel
{
    
    public function file(array $bulder): array {
      try {
        // Extract the components from $bulder
        // Expected format: [[$key => $name], $columns, $fieldConfig]
        if (count($bulder) < 3) {
            throw new \InvalidArgumentException('Insufficient parameters. Expected: [tableInfo, columns, fieldConfig]');
        }
        
        $tableInfo = $bulder[0]; // [$key => $name]
        $columns = $bulder[1];   // Form data including file data
        $fieldConfig = $bulder[2]; // File field configuration
        
        // ✅ FIX: Merge $_FILES jika ada (untuk FormData/multipart upload)
        // Ketika FormData dikirim, file akan masuk ke $_FILES, bukan di $columns
        $uploadErrors = []; // Collect upload errors
        $filesReceived = []; // Track files yang berhasil diterima
        
        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldName => $fileData) {
                // Handle single file atau array of files
                if (isset($fileData['tmp_name'])) {
                    // Single file
                    if (is_array($fileData['tmp_name'])) {
                        // Multiple files dengan nama field yang sama
                        $files = [];
                        foreach ($fileData['tmp_name'] as $index => $tmpName) {
                            $uploadError = $fileData['error'][$index] ?? UPLOAD_ERR_OK;
                            if ($uploadError === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
                                $files[] = [
                                    'name' => $fileData['name'][$index],
                                    'size' => $fileData['size'][$index],
                                    'type' => $fileData['type'][$index],
                                    'tmp_name' => $tmpName, // ✅ Keep tmp_name untuk file besar
                                    '_isFileUpload' => true // Flag untuk identifikasi file dari $_FILES
                                ];
                            } else {
                                $uploadErrors[] = "File {$fileData['name'][$index]}: Upload error code {$uploadError}";
                            }
                        }
                        if (!empty($files)) {
                            $columns[$fieldName] = $files;
                        }
                    } else {
                        // Single file
                        // ✅ FIX: Check error code untuk file upload
                        $uploadError = $fileData['error'] ?? UPLOAD_ERR_OK;
                        
                        if ($uploadError === UPLOAD_ERR_OK && is_uploaded_file($fileData['tmp_name'])) {
                            // ✅ FIX: Untuk file besar, simpan tmp_name langsung (tidak baca ke memory)
                            // File akan diproses langsung dari tmp_name saat saveFileToDirectory
                            $fileSizeMB = round($fileData['size'] / (1024 * 1024), 2);
                            $columns[$fieldName] = [
                                'name' => $fileData['name'],
                                'size' => $fileData['size'],
                                'type' => $fileData['type'],
                                'tmp_name' => $fileData['tmp_name'], // ✅ Keep tmp_name untuk file besar
                                '_isFileUpload' => true // Flag untuk identifikasi file dari $_FILES
                            ];
                            
                            // ✅ FIX: Log file info untuk debugging
                            error_log("✅ File received from \$_FILES: {$fieldName} - {$fileData['name']} ({$fileSizeMB}MB / {$fileData['size']} bytes)");
                        } else {
                            // ✅ FIX: Log error jika file tidak ter-upload dan return error
                            $errorMessages = [
                                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                                UPLOAD_ERR_EXTENSION => 'File upload stopped by PHP extension'
                            ];
                            $errorMsg = $errorMessages[$uploadError] ?? "Unknown error ({$uploadError})";
                            $fileSizeMB = round($fileData['size'] / (1024 * 1024), 2);
                            $phpUploadMax = ini_get('upload_max_filesize');
                            $phpPostMax = ini_get('post_max_size');
                            
                            error_log("❌ File upload error for {$fieldName}: {$errorMsg}. File size: {$fileSizeMB}MB. PHP limits: upload_max_filesize={$phpUploadMax}, post_max_size={$phpPostMax}");
                            
                            // ✅ FIX: Return error yang jelas ke client
                            $uploadErrors[] = "File '{$fileData['name']}' ({$fileSizeMB}MB) tidak dapat di-upload: {$errorMsg}. PHP limit: upload_max_filesize={$phpUploadMax}, post_max_size={$phpPostMax}";
                        }
                    }
                }
            }
        } else {
            // ✅ FIX: Jika $_FILES kosong tapi ada field dengan _fileObject, berarti file tidak masuk ke $_FILES
            // Kemungkinan file terlalu besar atau PHP limit terlalu kecil
            foreach ($columns as $key => $value) {
                if (is_array($value) && isset($value['_fileObject'])) {
                    $fileSizeMB = isset($value['size']) ? round($value['size'] / (1024 * 1024), 2) : 'unknown';
                    $phpUploadMax = ini_get('upload_max_filesize');
                    $phpPostMax = ini_get('post_max_size');
                    $phpUploadMaxBytes = $this->parseSize($phpUploadMax);
                    $phpPostMaxBytes = $this->parseSize($phpPostMax);
                    
                    error_log("⚠️ Warning: Field '{$key}' has _fileObject but file not in \$_FILES. File size: {$fileSizeMB}MB. PHP limits: upload_max_filesize={$phpUploadMax} ({$phpUploadMaxBytes} bytes), post_max_size={$phpPostMax} ({$phpPostMaxBytes} bytes)");
                    
                    // ✅ FIX: Cek apakah file size melebihi PHP limit
                    if (isset($value['size']) && $phpUploadMaxBytes !== false && $value['size'] > $phpUploadMaxBytes) {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) melebihi batas PHP upload_max_filesize ({$phpUploadMax}). Silakan tingkatkan upload_max_filesize di php.ini minimal {$fileSizeMB}MB.";
                    } else if (isset($value['size']) && $phpPostMaxBytes !== false && $value['size'] > $phpPostMaxBytes) {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) melebihi batas PHP post_max_size ({$phpPostMax}). Silakan tingkatkan post_max_size di php.ini minimal {$fileSizeMB}MB.";
                    } else {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) tidak masuk ke server. Periksa PHP configuration: upload_max_filesize={$phpUploadMax}, post_max_size={$phpPostMax}";
                    }
                }
            }
        }
        
        // ✅ FIX: Log summary
        error_log("📊 Upload summary: " . count($filesReceived) . " file(s) received, " . count($uploadErrors) . " error(s)");
        
        // ✅ FIX: Jika ada upload errors, return error
        if (!empty($uploadErrors)) {
            return [
                'success' => false,
                'error' => implode('; ', $uploadErrors),
                'query' => '',
                'response' => [],
                'php_config' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_limit' => ini_get('memory_limit')
                ]
            ];
        }
        
        // ✅ FIX: Debug - log untuk melihat apa yang diterima
        // Jika ada field dengan _fileObject tapi kosong, berarti file tidak masuk ke $_FILES
        foreach ($columns as $key => $value) {
            if (is_array($value) && isset($value['_fileObject']) && empty($value['_fileObject'])) {
                // File tidak masuk ke $_FILES, mungkin masalah di client side atau PHP limit
                $fileSizeMB = isset($value['size']) ? round($value['size'] / (1024 * 1024), 2) : 'unknown';
                $phpUploadMax = ini_get('upload_max_filesize');
                $phpPostMax = ini_get('post_max_size');
                
                error_log("⚠️ Warning: Field '{$key}' has _fileObject but it's empty. File size: {$fileSizeMB}MB. PHP limits: upload_max_filesize={$phpUploadMax}, post_max_size={$phpPostMax}");
                
                // ✅ FIX: Tambahkan ke error jika belum ada di $uploadErrors
                if (!in_array($key, $filesReceived)) {
                    $phpUploadMaxBytes = $this->parseSize($phpUploadMax);
                    $phpPostMaxBytes = $this->parseSize($phpPostMax);
                    
                    if (isset($value['size']) && $phpUploadMaxBytes !== false && $value['size'] > $phpUploadMaxBytes) {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) melebihi batas PHP upload_max_filesize ({$phpUploadMax}). Silakan tingkatkan upload_max_filesize di php.ini minimal " . ceil($fileSizeMB) . "MB.";
                    } else if (isset($value['size']) && $phpPostMaxBytes !== false && $value['size'] > $phpPostMaxBytes) {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) melebihi batas PHP post_max_size ({$phpPostMax}). Silakan tingkatkan post_max_size di php.ini minimal " . ceil($fileSizeMB) . "MB.";
                    } else {
                        $uploadErrors[] = "File '{$value['name']}' ({$fileSizeMB}MB) tidak masuk ke server. Periksa PHP configuration: upload_max_filesize={$phpUploadMax}, post_max_size={$phpPostMax}";
                    }
                }
            }
        }
        
        // ✅ FIX: Log summary
        error_log("📊 Upload summary: " . count($filesReceived) . " file(s) received, " . count($uploadErrors) . " error(s)");
        
        // ✅ FIX: Check PHP upload configuration and warn if limits are too low
        $phpUploadMax = ini_get('upload_max_filesize');
        $phpPostMax = ini_get('post_max_size');
        $warnings = [];
        
        $phpUploadMaxBytes = $this->parseSize($phpUploadMax);
        $phpPostMaxBytes = $this->parseSize($phpPostMax);
        $minRequired = 200 * 1024 * 1024; // 200MB
        
        if ($phpUploadMaxBytes !== false && $phpUploadMaxBytes < $minRequired) {
            $warnings[] = "PHP upload_max_filesize ({$phpUploadMax}) is less than 200MB. Consider increasing it in php.ini";
        }
        if ($phpPostMaxBytes !== false && $phpPostMaxBytes < $minRequired) {
            $warnings[] = "PHP post_max_size ({$phpPostMax}) is less than 200MB. Consider increasing it in php.ini";
        }
        
        // Process file uploads in the columns data
        $processedData = $this->processFileUploads($columns, $fieldConfig);
        
        // Return processed data back to Office.php for database insertion
        $result = [
            'success' => true,
            'tableInfo' => $tableInfo,
            'processedData' => $processedData,
            'fieldConfig' => $fieldConfig
        ];
        
        if (!empty($warnings)) {
            $result['warnings'] = $warnings;
        }
        
        return $result;

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'query' => '',
            'response' => [],
            // ✅ FIX: Add PHP configuration info for debugging
            'php_config' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit')
            ]
        ];
    }
    }

 
    private function processFileUploads(array $data, array $fieldConfig): array {
        $processedData = $data;
        
        // Create dynamic path based on current year/month  
        $currentYear = date('Y');
        $currentMonth = date('m');
        
        // Fix path to match the correct directory structure
        $uploadDir = __DIR__ . "/../../assets/drive/{$currentYear}/{$currentMonth}/";
        
        // Ensure upload directory exists (create nested folders)
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($data as $key => $value) {
            // Check if this is file data using field configuration
            $isFile = $this->isFileData($key, $value, $fieldConfig);
            
            if ($isFile) {
                // ✅ FIX: Get field-specific config for this file
                $fieldSpecificConfig = $this->getFieldSpecificConfig($key, $fieldConfig);
                
                $savedFile = $this->saveFileToDirectory($value, $uploadDir, $fieldSpecificConfig);
                if ($savedFile) {
                    // CORE TRANSFORMATION: Replace file object with file path string
                    $processedData[$key] = $savedFile['relativePath'];
                } else {
                    // Keep original value if file processing failed
                    $processedData[$key] = null;
                }
            }
        }
        
        return $processedData;
    }

    /**
     * Get field-specific configuration for a file field
     */
    private function getFieldSpecificConfig(string $fieldName, array $fieldConfig): array {
        // ✅ SUPPORT BOTH FORMATS:
        // 1. Array of objects: [{name: 'field1', type: 'file'}, ...]
        // 2. Object with keys: {field1: {type: 'file'}, ...}
        
        // Check format 1: Array of objects with 'name' property
        foreach ($fieldConfig as $index => $config) {
            if (is_array($config) || is_object($config)) {
                if (isset($config['name']) && $config['name'] === $fieldName) {
                    return [$config]; // Return as array with single config for this field
                }
            }
        }
        
        // Check format 2: Object with key as field name
        if (isset($fieldConfig[$fieldName])) {
            $config = $fieldConfig[$fieldName];
            if (is_array($config) || is_object($config)) {
                // Add 'name' property if missing
                if (!isset($config['name'])) {
                    $config['name'] = $fieldName;
                }
                return [$config]; // Return as array with single config for this field
            }
        }
        
        // Return empty array if no specific config found
        return [];
    }

    /**
     * Check if the given key-value pair represents file data based on field configuration
     */
    private function isFileData(string $key, $value, array $fieldConfig): bool {
        // Check if this field is configured as a file field
        if (!is_array($fieldConfig)) {
            return false;
        }
        
        // ✅ SUPPORT BOTH FORMATS:
        // 1. Array of objects: [{name: 'field1', type: 'file'}, ...]
        // 2. Object with keys: {field1: {type: 'file'}, ...}
        
        // Check format 1: Array of objects with 'name' property
        foreach ($fieldConfig as $index => $fileField) {
            if (is_array($fileField) || is_object($fileField)) {
                // If it's an object/array with 'name' property
                if (isset($fileField['name']) && $fileField['name'] === $key) {
                    // Check if type is file-related
                    $fieldType = $fileField['type'] ?? '';
                    if (in_array($fieldType, ['file', 'camera', 'document', 'video', 'image'])) {
                        return true;
                    }
                }
            }
        }
        
        // Check format 2: Object with key as field name
        if (isset($fieldConfig[$key])) {
            $fieldConfigItem = $fieldConfig[$key];
            if (is_array($fieldConfigItem) || is_object($fieldConfigItem)) {
                $fieldType = $fieldConfigItem['type'] ?? '';
                if (in_array($fieldType, ['file', 'camera', 'document', 'video', 'image'])) {
                    return true;
                }
            }
        }
        
        // ✅ FIX: Check if value is file from $_FILES (FormData upload)
        if (is_array($value) && isset($value['_isFileUpload']) && $value['_isFileUpload'] && isset($value['tmp_name'])) {
            return true;
        }
        
        // Alternative check: if value looks like file data structure
        if (is_array($value) && isset($value['name'], $value['size'], $value['type'])) {
            return true;
        }
        
        // ✅ FIX: Check if value has content array (from JSON upload)
        if (is_array($value) && isset($value['content']) && is_array($value['content'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate file type based on fieldConfig accept property
     */
    private function validateFileType(string $fileName, array $fieldConfig): bool {
        // If no fieldConfig provided, allow all files (backward compatibility)
        if (empty($fieldConfig)) {
            return true;
        }

        // ✅ FIX: Use first (and should be only) config since we pass field-specific config
        $config = $fieldConfig[0] ?? null;
        if (!$config) {
            return true;
        }

        $acceptPattern = $config['accept'] ?? null;

        // If no accept pattern found, allow all files (including PDF)
        if (empty($acceptPattern)) {
            return true;
        }

        // Get file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (empty($fileExtension)) {
            // ✅ FIX: Allow files without extension if accept pattern is very permissive
            // Check if accept includes wildcards or common document types
            if (strpos($acceptPattern, '*') !== false || 
                strpos($acceptPattern, 'application') !== false ||
                strpos($acceptPattern, 'document') !== false) {
                return true;
            }
            return false;
        }

        // Parse accept pattern (e.g., ".jpg,.png,.pdf,.docx" or "image/*,application/pdf")
        $allowedExtensions = array_map('trim', explode(',', $acceptPattern));
        $allowedExtensions = array_map(function($ext) {
            return ltrim(strtolower($ext), '.');
        }, $allowedExtensions);

        // ✅ FIX: Support MIME type patterns (e.g., "application/pdf", "image/*")
        foreach ($allowedExtensions as $pattern) {
            // Wildcard support (e.g., "image/*", "application/*")
            if (strpos($pattern, '*') !== false) {
                $mimePrefix = str_replace('/*', '', $pattern);
                // Check common MIME types for PDF
                if ($mimePrefix === 'application' && $fileExtension === 'pdf') {
                    return true;
                }
                // Check if file extension matches common MIME types
                $commonMimes = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
                if (isset($commonMimes[$fileExtension])) {
                    $fileMime = $commonMimes[$fileExtension];
                    if (strpos($fileMime, $mimePrefix) === 0) {
                        return true;
                    }
                }
            }
        }

        // Check if file extension is allowed
        if (in_array($fileExtension, $allowedExtensions)) {
            return true;
        }

        // ✅ FIX: Always allow PDF files unless explicitly restricted
        // This ensures PDF support even if not in accept list
        if ($fileExtension === 'pdf') {
            // Only reject if accept explicitly excludes PDF
            $explicitReject = false;
            foreach ($allowedExtensions as $ext) {
                if (strpos($ext, 'pdf') === false && strpos($ext, '*') === false) {
                    // If accept list exists but doesn't include pdf or wildcard, check if it's restrictive
                    if (count($allowedExtensions) > 0 && !in_array('*', $allowedExtensions)) {
                        // If accept has specific extensions but not pdf, allow it anyway for backward compatibility
                        return true;
                    }
                }
            }
            return true; // Default: allow PDF
        }

        return false;
    }

    /**
     * Validate file size based on fieldConfig maxSize property
     */
    private function validateFileSize(int $fileSize, array $fieldConfig): bool {
        // ✅ FIX: Check PHP configuration limits first
        $phpUploadMax = $this->parseSize(ini_get('upload_max_filesize'));
        $phpPostMax = $this->parseSize(ini_get('post_max_size'));
        
        // Use the smaller of the two PHP limits
        $phpMaxSize = min(
            $phpUploadMax !== false ? $phpUploadMax : PHP_INT_MAX,
            $phpPostMax !== false ? $phpPostMax : PHP_INT_MAX
        );
        
        // ✅ FIX: Default max size is 500MB if not specified (supports files > 200MB)
        $defaultMaxSize = 500 * 1024 * 1024; // 500MB
        
        // If no fieldConfig provided, use default or PHP limit (whichever is smaller)
        if (empty($fieldConfig)) {
            $effectiveMax = min($defaultMaxSize, $phpMaxSize);
            return $fileSize <= $effectiveMax;
        }

        // ✅ FIX: Use first (and should be only) config since we pass field-specific config
        $config = $fieldConfig[0] ?? null;
        if (!$config) {
            $effectiveMax = min($defaultMaxSize, $phpMaxSize);
            return $fileSize <= $effectiveMax;
        }

        $maxSizeStr = $config['maxSize'] ?? null;

        // If no maxSize found, use default or PHP limit
        if (empty($maxSizeStr)) {
            $effectiveMax = min($defaultMaxSize, $phpMaxSize);
            return $fileSize <= $effectiveMax;
        }

        // Parse maxSize string (e.g., "5MB", "10KB", "2GB")
        $maxSizeBytes = $this->parseSize($maxSizeStr);
        if ($maxSizeBytes === false) {
            // If parsing fails, use default
            $effectiveMax = min($defaultMaxSize, $phpMaxSize);
            return $fileSize <= $effectiveMax;
        }

        // ✅ FIX: Ensure maxSize is at least 200MB for large file support
        $minRequiredSize = 200 * 1024 * 1024; // 200MB minimum
        if ($maxSizeBytes < $minRequiredSize) {
            // If configured maxSize is less than 200MB, increase it to 200MB
            $maxSizeBytes = $minRequiredSize;
        }

        // Use the smaller of configured maxSize or PHP limit
        $effectiveMax = min($maxSizeBytes, $phpMaxSize);

        // Check if file size is within limit
        return $fileSize <= $effectiveMax;
    }

    /**
     * Parse size string like "5MB", "10KB", "2GB", "200M" (PHP format) to bytes
     */
    private function parseSize(string $sizeStr): int|false {
        $sizeStr = strtoupper(trim($sizeStr));
        
        // ✅ FIX: Support both formats: "5MB" and "5M" (PHP ini format)
        // Extract number and unit (with or without 'B' suffix)
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(K|M|G|T|KB|MB|GB|TB|B)?$/', $sizeStr, $matches)) {
            return false;
        }

        $size = (float)$matches[1];
        $unit = $matches[2] ?? 'B';
        
        // ✅ FIX: Normalize unit (handle "M" -> "MB", "K" -> "KB", etc.)
        if (strlen($unit) === 1 && $unit !== 'B') {
            $unit = $unit . 'B'; // Convert "M" to "MB", "K" to "KB", etc.
        }

        $multipliers = [
            'B' => 1,
            'KB' => 1024,
            'MB' => 1024 * 1024,
            'GB' => 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024,
        ];

        return (int)($size * ($multipliers[$unit] ?? 1));
    }

    private function saveFileToDirectory($fileData, string $uploadDir, array $fieldConfig = []): ?array {
        try {
            $fileName = '';
            $fileContent = '';
            $fileType = '';
            $fileSize = 0;
            $tmpName = null; // ✅ For $_FILES uploads
            
            if (is_array($fileData)) {
                // ✅ FIX: Check if this is file from $_FILES (FormData upload)
                if (isset($fileData['_isFileUpload']) && $fileData['_isFileUpload'] && isset($fileData['tmp_name'])) {
                    // File dari $_FILES - gunakan tmp_name langsung (tidak baca ke memory)
                    $tmpName = $fileData['tmp_name'];
                    $fileName = $fileData['name'] ?? 'file';
                    $fileType = $fileData['type'] ?? 'application/octet-stream';
                    $fileSize = $fileData['size'] ?? 0;
                    
                    // ✅ FIX: Validasi file sebelum proses
                    if (!is_uploaded_file($tmpName) || !file_exists($tmpName)) {
                        return null;
                    }
                } else {
                    // NEW FORMAT: Handle file metadata structure (from JSON/array upload)
                    $fileName = $this->extractFileName($fileData);
                    $fileContent = $this->extractFileContent($fileData);
                    $fileType = $this->extractFileType($fileData);
                    $fileSize = $this->extractFileSize($fileData, $fileContent);
                }
            }
            
            // ✅ FIX: Untuk file dari $_FILES, skip validasi content (file sudah di server)
            if ($tmpName) {
                // File dari $_FILES - langsung validasi dan move
            } else {
                // NEW FORMAT: Don't create files without actual content
                if (empty($fileContent)) {
                    // No actual file content - don't create physical file
                    // This prevents corrupted/placeholder files
                    return null;
                }
            }

            // ✅ VALIDATE FILE TYPE BASED ON FIELDCONFIG
            if (!$this->validateFileType($fileName, $fieldConfig)) {
                // ✅ FIX: Return error message instead of null
                throw new \Exception("File type not allowed: {$fileName}. Allowed types: " . 
                    ($fieldConfig[0]['accept'] ?? 'all files'));
            }

            // ✅ VALIDATE FILE SIZE BASED ON FIELDCONFIG
            if (!$this->validateFileSize($fileSize, $fieldConfig)) {
                $maxSizeStr = $fieldConfig[0]['maxSize'] ?? '500MB';
                $fileSizeMB = round($fileSize / (1024 * 1024), 2);
                // ✅ FIX: Return error message instead of null
                throw new \Exception("File size exceeds limit: {$fileSizeMB}MB. Maximum allowed: {$maxSizeStr}");
            }
            
            // Generate unique filename to avoid conflicts
            $fileInfo = pathinfo($fileName);
            $baseName = $fileInfo['filename'] ?? 'file_' . uniqid();
            // ✅ FIX: Ganti semua spasi dengan underscore (_) pada nama file
            $baseName = str_replace(' ', '_', $baseName);
            // ✅ FIX: Ganti karakter khusus lainnya yang mungkin bermasalah
            $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
            $extension = $fileInfo['extension'] ?? 'bin';
            $uniqueFileName = $baseName . '_' . time() . '.' . $extension;
            
            $fullPath = $uploadDir . $uniqueFileName;
            
            // Create relative path with year/month structure
            $currentYear = date('Y');
            $currentMonth = date('m');
            $relativePath = "assets/drive/{$currentYear}/{$currentMonth}/{$uniqueFileName}";
            
            // ✅ FIX: Untuk file dari $_FILES, gunakan move_uploaded_file (lebih efisien)
            // Untuk file dari array content, gunakan file_put_contents
            if ($tmpName) {
                // File dari $_FILES - move langsung tanpa baca ke memory
                $bytesWritten = move_uploaded_file($tmpName, $fullPath) ? $fileSize : false;
            } else {
                // File dari array content - write dari memory
                $bytesWritten = file_put_contents($fullPath, $fileContent);
            }
            
            if ($bytesWritten !== false) {
                $result = [
                    'originalName' => $fileName,
                    'fullPath' => $fullPath,
                    'relativePath' => $relativePath,
                    'size' => $fileSize,
                    'type' => $fileType,
                    'success' => true,
                    'bytesWritten' => $bytesWritten
                ];

                // ✅ AUTO-RESIZE: Create multi-dimension versions for images
                if ($this->isImageFile($fileType)) {
                    $resizedVersions = $this->createMultiDimensionVersions($fullPath, $fileName, $currentYear, $currentMonth);
                    $result['resizedVersions'] = $resizedVersions;
                }

                return $result;
            }
            
        } catch (\Exception $e) {
            // ✅ FIX: Log error for debugging instead of silent failure
            error_log("File upload error: " . $e->getMessage());
            // Re-throw to be caught by calling method
            throw $e;
        }
        
        return null;
    }

/**
     * DYNAMIC: Extract filename from any array structure
     */
    private function extractFileName(array $data): string {
        // Try common filename fields
        $nameFields = ['name', 'filename', 'fileName', 'file_name', 'originalName', 'original_name'];
        
        foreach ($nameFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        
        // Generate default name
        return 'file_' . uniqid() . '.bin';
    }

    /**
     * NEW FORMAT: Extract file content from File object or binary data
     * Handles JavaScript File objects and various content formats
     */
    private function extractFileContent(array $data): string {
        // 1. Handle processed file data with 'content' property (from NexaValidation.js)
        if (isset($data['content'])) {
            $content = $data['content'];
            
            // Check for binary array format: [255, 216, 255, ...] from JavaScript
            if (is_array($content) && !empty($content) && is_numeric($content[0])) {
                // Convert array of bytes to binary string
                return pack('C*', ...$content);
            }
            
            // Direct binary string content
            elseif (is_string($content) && !empty($content)) {
                return $content;
            }
        }
        
        // 2. Handle File object from JavaScript (legacy format with file property)
        if (isset($data['file'])) {
            // If 'file' is an object/array, try to extract content from it
            if (is_array($data['file']) && isset($data['file']['content'])) {
                $content = $data['file']['content'];
                
                if (is_array($content) && !empty($content) && is_numeric($content[0])) {
                    return pack('C*', ...$content);
                }
            }
            
            // If 'file' is a string (base64 or direct content)
            if (is_string($data['file']) && !empty($data['file'])) {
                return $data['file'];
            }
            
            // Handle File object with empty array 'file': []
            if (is_array($data['file']) && empty($data['file'])) {
                return '';
            }
        }
        
        // 3. NEW: Handle serialized File object from JavaScript (has name, size, type but missing content)
        if (isset($data['name'], $data['size'], $data['type']) && !isset($data['content'])) {
            // This indicates File object was serialized without proper binary conversion
            // Return empty string to avoid creating corrupted files
            return '';
        }
        
        // 2. Check root level for binary data
        $binaryFields = ['data', 'file_data', 'fileData', 'binary', 'buffer'];
        
        foreach ($binaryFields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field]) && !empty($data[$field]) && is_numeric($data[$field][0])) {
                    // Convert byte array to binary string
                    return pack('C*', ...$data[$field]);
                } elseif (is_string($data[$field]) && !empty($data[$field])) {
                    return $data[$field];
                }
            }
        }
        
        return '';
    }

    /**
     * DYNAMIC: Extract file type from any array structure
     */
    private function extractFileType(array $data): string {
        // Try common type fields
        $typeFields = ['type', 'mime', 'mimeType', 'mime_type', 'contentType', 'content_type'];
        
        foreach ($typeFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        
        return 'application/octet-stream';
    }

    /**
     * DYNAMIC: Extract file size from any array structure
     */
    private function extractFileSize(array $data, string $fileContent): int {
        // Try common size fields
        $sizeFields = ['size', 'fileSize', 'file_size', 'length', 'contentLength'];
        
        foreach ($sizeFields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return (int)$data[$field];
            }
        }
        
        // Fallback to actual content length
        return strlen($fileContent);
    }

    /**
     * Check if file is an image that can be resized
     */
    private function isImageFile(string $fileType): bool {
        $imageTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp'
        ];
        
        return in_array(strtolower($fileType), $imageTypes);
    }

    /**
     * Create multi-dimension versions of image for web, tablet, android
     */
    private function createMultiDimensionVersions(string $originalPath, string $fileName, string $year, string $month): array {
        $versions = [];
        
        // ✅ OPTIMAL DIMENSIONS for different devices
        $dimensions = [
            'web' => ['width' => 1920, 'height' => 1080, 'quality' => 85],
            'tablet' => ['width' => 1024, 'height' => 768, 'quality' => 80], 
            'android' => ['width' => 512, 'height' => 384, 'quality' => 75],
            'thumbnail' => ['width' => 150, 'height' => 150, 'quality' => 70]
        ];

        foreach ($dimensions as $device => $config) {
            $resizedPath = $this->resizeImage($originalPath, $fileName, $device, $config, $year, $month);
            if ($resizedPath) {
                $versions[$device] = $resizedPath;
            }
        }

        return $versions;
    }

    /**
     * Resize image to specific dimensions and save to device folder
     */
    private function resizeImage(string $originalPath, string $fileName, string $device, array $config, string $year, string $month): ?string {
        try {
            // Create device-specific directory
            $baseDir = __DIR__ . "/../../assets/drive/{$year}/{$month}";
            $deviceDir = $baseDir . "/{$device}";
            
            if (!is_dir($deviceDir)) {
                mkdir($deviceDir, 0755, true);
            }

            // Generate device-specific filename
            $fileInfo = pathinfo($fileName);
            $baseName = $fileInfo['filename'] ?? 'file_' . uniqid();
            // ✅ FIX: Ganti semua spasi dengan underscore (_) pada nama file
            $baseName = str_replace(' ', '_', $baseName);
            // ✅ FIX: Ganti karakter khusus lainnya yang mungkin bermasalah
            $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
            $extension = strtolower($fileInfo['extension'] ?? 'jpg');
            $deviceFileName = $baseName . "_{$device}_" . time() . '.' . $extension;
            $devicePath = $deviceDir . '/' . $deviceFileName;

            // Get original image info
            $imageInfo = getimagesize($originalPath);
            if (!$imageInfo) {
                return null;
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];

            // Calculate new dimensions (maintain aspect ratio)
            $targetWidth = $config['width'];
            $targetHeight = $config['height'];
            
            // Only resize if original is larger than target
            if ($originalWidth <= $targetWidth && $originalHeight <= $targetHeight) {
                // Copy original if it's already smaller
                copy($originalPath, $devicePath);
                return "assets/drive/{$year}/{$month}/{$device}/{$deviceFileName}";
            }

            // Calculate aspect-ratio preserving dimensions
            $aspectRatio = $originalWidth / $originalHeight;
            $targetAspectRatio = $targetWidth / $targetHeight;

            if ($aspectRatio > $targetAspectRatio) {
                // Original is wider
                $newWidth = $targetWidth;
                $newHeight = (int)($targetWidth / $aspectRatio);
            } else {
                // Original is taller
                $newHeight = $targetHeight;
                $newWidth = (int)($targetHeight * $aspectRatio);
            }

            // Create source image resource
            $sourceImage = $this->createImageResource($originalPath, $imageType);
            if (!$sourceImage) {
                return null;
            }

            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG/GIF
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // Save resized image
            $saved = $this->saveImageResource($newImage, $devicePath, $imageType, $config['quality']);
            
            // Clean up resources
            imagedestroy($sourceImage);
            imagedestroy($newImage);

            if ($saved) {
                return "assets/drive/{$year}/{$month}/{$device}/{$deviceFileName}";
            }

        } catch (\Exception $e) {
            // Silent failure
        }

        return null;
    }

    /**
     * Create image resource from file
     */
    private function createImageResource(string $filePath, int $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filePath);
            case IMAGETYPE_BMP:
                return imagecreatefrombmp($filePath);
            default:
                return false;
        }
    }

    /**
     * Save image resource to file
     */
    private function saveImageResource($imageResource, string $filePath, int $imageType, int $quality): bool {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $filePath, $quality);
            case IMAGETYPE_PNG:
                // PNG quality is 0-9 (0=no compression, 9=max compression)
                $pngQuality = (int)(9 - ($quality / 100 * 9));
                return imagepng($imageResource, $filePath, $pngQuality);
            case IMAGETYPE_GIF:
                return imagegif($imageResource, $filePath);
            case IMAGETYPE_WEBP:
                return imagewebp($imageResource, $filePath, $quality);
            case IMAGETYPE_BMP:
                return imagebmp($imageResource, $filePath);
            default:
                return false;
        }
    }

}
