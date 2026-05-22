<?php
declare(strict_types=1);
namespace App\System\Helpers;

class NexaFile{
    protected $baseUploadDir;
    protected $maxFileSize;
    protected $allowedTypes;
    protected $allowedExtensions;
    protected $thumbnailSizes;
    protected $thumbnailCropMode;
    protected $saveOriginal;

    public function __construct() {
        // Set up error logging        
        //ini_set('log_errors', 1);
        //ini_set('error_log', dirname(dirname(__DIR__)) . '/log/error.log');
        
        $this->baseUploadDir = dirname(__DIR__, 2) .'/assets/drive/';
        $this->maxFileSize = 15 * 1024 * 1024; // 15MB dalam bytes
        $this->allowedTypes = [
            // Images
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp', 'image/x-icon',
            
            // Documents
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/rtf', 'application/vnd.oasis.opendocument.text',
            
            // Spreadsheets
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroEnabled.12', 'application/vnd.oasis.opendocument.spreadsheet', 'text/csv',
            
            // Presentations
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.presentation',
            
            // Apple iWork
            'application/vnd.apple.pages', 'application/vnd.apple.numbers', 'application/vnd.apple.keynote',
            
            // Archives
            'application/zip', 'application/x-zip-compressed', 'application/vnd.rar', 'application/x-rar-compressed', 
            'application/x-7z-compressed', 'application/x-tar', 'application/gzip', 'application/x-gzip',
            'application/octet-stream', // Generic binary type often used by browsers for archives
            
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/aac', 'audio/mp4',
            
            // Video
            'video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/x-matroska', 'video/webm',
            
            // Web & Data
            'text/html', 'text/css', 'application/javascript', 'application/json', 'application/xml',
            'text/markdown', // Tambahkan ini untuk MIME type markdown
            
            // Programming
            'application/x-httpd-php', 'text/x-python', 'text/x-java-source', 'text/x-c++src', 'text/x-csrc', 'text/x-chdr', 'application/sql'
        ];
        $this->allowedExtensions = [
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
            // Documents
            'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt',
            // Spreadsheets
            'xls', 'xlsx', 'xlsm', 'ods', 'csv',
            // Presentations
            'ppt', 'pptx', 'odp',
            // Apple iWork
            'pages', 'numbers', 'key',
            // Archives
            'zip', 'rar', '7z', 'tar', 'gz',
            // Audio
            'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a',
            // Video
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm',
            // Web & Data
            'html', 'htm', 'css', 'js', 'json', 'xml',
            'md', // Tambahkan ini untuk file markdown
            // Programming
            'php', 'py', 'java', 'cpp', 'c', 'h', 'sql'
        ];
        $this->thumbnailSizes = [];
        $this->thumbnailCropMode = 'fit'; // Default: 'fit' (preserve aspect ratio) or 'crop' (fill canvas)
        $this->saveOriginal = true; // Default: save original file
        
        // Buat direktori upload jika belum ada
        if (!is_dir($this->baseUploadDir)) {
            mkdir($this->baseUploadDir, 0755, true);
        }
    }

    /**
     * Konversi bytes ke format yang mudah dibaca (contoh: 1048576 -> "1MB")
     * 
     * @param int $bytes Ukuran dalam bytes
     * @param int $precision Jumlah digit desimal (default: 2)
     * @return string Ukuran dalam format yang mudah dibaca
     */
    public function formatBytesToHuman($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Konversi string size ke bytes (contoh: "5MB" -> 5242880)
     * 
     * @param string|int $size Size dalam format string atau integer
     * @return int Size dalam bytes
     */
    public function parseSizeToBytes($size) {
        // Jika sudah berupa integer, return as is
        if (is_numeric($size)) {
            return (int)$size;
        }
        
        // Konversi ke string dan uppercase
        $size = strtoupper(trim($size));
        
        // Extract angka dan unit
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT]?B?)$/', $size, $matches)) {
            $number = (float)$matches[1];
            $unit = $matches[2];
            
            switch ($unit) {
                case 'TB':
                    return (int)($number * 1024 * 1024 * 1024 * 1024);
                case 'GB':
                    return (int)($number * 1024 * 1024 * 1024);
                case 'MB':
                    return (int)($number * 1024 * 1024);
                case 'KB':
                    return (int)($number * 1024);
                case 'B':
                case '':
                    return (int)$number;
                default:
                    throw new \InvalidArgumentException("Unknown size unit: {$unit}");
            }
        }
        
        throw new \InvalidArgumentException("Invalid size format: {$size}");
    }

    protected function getFileLibraryCategory($mimeType, $extension) {
        // Images
        if (in_array($mimeType, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 
            'image/svg+xml', 'image/bmp', 'image/x-icon'
        ])) {
            return 'Images';
        }
        
        // Videos
        if (in_array($mimeType, [
            'video/mp4', 'video/x-msvideo', 'video/quicktime', 
            'video/x-ms-wmv', 'video/x-flv', 'video/x-matroska', 
            'video/webm'
        ]) || in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'])) {
            return 'Videos';
        }
        
        // Audio
        if (in_array($mimeType, [
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 
            'audio/aac', 'audio/mp4', 'audio/mp3'
        ]) || in_array($extension, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'])) {
            return 'Audio';
        }
        
        // Documents (default for all other types)
        return 'Documents';
    }

    public function handleFileUpload($fileData, $config = []) {
        if (empty($fileData)) {
            throw new \Exception("No file data received");
        }

        // Handle both direct $_FILES array and formatted data
        if (isset($fileData['tmp_name'])) {
            // Convert $_FILES format to our internal format
            $fileData = [
                'name' => $fileData['name'],
                'type' => $fileData['type'],
                'size' => $fileData['size'],
                'content' => base64_encode(file_get_contents($fileData['tmp_name'])),
                'tmp_name' => $fileData['tmp_name']
            ];
        }

        // Validate required fields
        if (!isset($fileData['name']) || !isset($fileData['type']) || !isset($fileData['size']) || !isset($fileData['content'])) {
            throw new \Exception("Invalid file data format");
        }

        // Override default configuration if provided
        if (isset($config['maxSize'])) {
            $this->maxFileSize = $this->parseSizeToBytes($config['maxSize']);
        }
        if (isset($config['allowedTypes'])) {
            $this->allowedTypes = $config['allowedTypes'];
        }
        if (isset($config['allowedExtensions'])) {
            $this->allowedExtensions = $config['allowedExtensions'];
        }
        if (isset($config['thumbnail'])) {
            $this->thumbnailSizes = $config['thumbnail'];
        }
        if (isset($config['thumbnailCropMode'])) {
            $this->setThumbnailCropMode($config['thumbnailCropMode']);
        }
        if (isset($config['baseUploadDir'])) {
            $this->setBaseUploadDir($config['baseUploadDir']);
        }
        if (isset($config['saveOriginal'])) {
            $this->saveOriginal = $config['saveOriginal'];
        }

        // Tentukan direktori upload dengan path yang aman
        $yearMonth = date('Y/m/');
        $uploadDir = $this->baseUploadDir . $yearMonth;

        // Buat direktori berdasarkan tahun/bulan jika belum ada
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \Exception("Failed to create upload directory");
            }
        }

        // Validasi file
        $this->validateFile($fileData);

        // Generate nama file yang aman
        $fileInfo = pathinfo($fileData['name']);
        $safeFileName = $this->generateSafeFileName($fileInfo['filename'], $fileInfo['extension']);
        $uploadPath = $uploadDir . $safeFileName;

        // Decode dan simpan file
        $fileContent = base64_decode($fileData['content']);
        if ($fileContent === false) {
            throw new \Exception("Invalid file content encoding");
        }
        
        // Handle original file resizing if specified
        if ($this->saveOriginal !== true && is_string($this->saveOriginal) && $this->isImage($fileData['type'])) {
            $resizedContent = $this->resizeOriginalFile($fileContent, $uploadPath, $this->saveOriginal);
            if ($resizedContent !== false) {
                $fileContent = $resizedContent;
            }
        }
        
        if (file_put_contents($uploadPath, $fileContent)) {
            $result = [
                //'original_name' => $fileData['name'],
                //'saved_name' => $safeFileName,
                'path' => $yearMonth . $safeFileName, // Path relatif untuk database
                //'full_path' => $uploadPath,           // Path lengkap file
                'sizefor' => $fileData['size'],
                'size' => $this->formatBytesToHuman($fileData['size']),
                //'size_formatted' => $this->formatBytesToHuman($fileData['size']),
                 'library' => $this->getFileLibraryCategory($fileData['type'], strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION))),
                'type' => strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION)),
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            // Generate thumbnails if needed (skip if using saveOriginal resize)
            if (!empty($this->thumbnailSizes) && $this->isImage($fileData['type']) && $this->saveOriginal === true) {
                $result['thumbnails'] = $this->generateThumbnails($uploadPath, $yearMonth, $safeFileName);
            }

            return $result;
        }
        
        throw new \Exception('Failed to save file');
    }

    protected function validateFile($fileData) {
        // Validasi ukuran file
        if ($fileData['size'] > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum limit of ' . ($this->maxFileSize / (1024 * 1024)) . 'MB');
        }

        // Validasi ekstensi file (ekstensi adalah prioritas utama)
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception('File extension not allowed');
        }

        // Validasi tipe file dengan fallback untuk archive files
        $mimeValid = in_array($fileData['type'], $this->allowedTypes);
        
        // Jika MIME type tidak valid, cek apakah ini archive file dengan MIME generic
        if (!$mimeValid) {
            $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz'];
            $genericMimes = ['application/octet-stream', 'binary/octet-stream', 'application/x-download'];
            
            // Untuk archive files, izinkan generic MIME types
            if (in_array($extension, $archiveExtensions) && in_array($fileData['type'], $genericMimes)) {
                $mimeValid = true;
            }
        }
        
        if (!$mimeValid) {
            throw new \Exception('File type not allowed');
        }
    }

    protected function generateSafeFileName($filename, $extension) {
        // Bersihkan nama file dari karakter yang tidak aman
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '-', $filename);
        $safeName = strtolower($safeName);
        
        // Tambahkan timestamp dan random string untuk menghindari duplikasi
        $uniqueName = $safeName . '_' . time() . '_' . substr(md5((string)rand()), 0, 8);
        
        return $uniqueName . '.' . $extension;
    }

    protected function isImage($mimeType) {
        return in_array($mimeType, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 
            'image/svg+xml', 'image/bmp', 'image/x-icon'
        ]);
    }

    protected function generateThumbnails($sourcePath, $yearMonth, $filename) {
        $thumbnails = [];
        $fileInfo = pathinfo($filename);
        
        foreach ($this->thumbnailSizes as $size) {
           
            
            try {
                list($width, $height) = explode('x', strtolower($size));
                $width = (int)$width;
                $height = (int)$height;
                
                // Create size-specific directory
                $thumbDir = $this->baseUploadDir . $yearMonth . $size . '/';
                if (!is_dir($thumbDir)) {
                    if (!mkdir($thumbDir, 0755, true)) {
                        continue;
                    }
                }
                
                // Create thumbnail filename
                $thumbFilename = $fileInfo['filename'] . '.' . $fileInfo['extension'];
                $thumbPath = $thumbDir . $thumbFilename;
                
                // Load source image
                $sourceImage = $this->loadImage($sourcePath);
                if (!$sourceImage) {
                    continue;
                }
                
                // Get original dimensions
                $origWidth = imagesx($sourceImage);
                $origHeight = imagesy($sourceImage);
                
                // Create new image with desired dimensions
                $thumbImage = imagecreatetruecolor($width, $height);
                if (!$thumbImage) {
                    continue;
                }
                
                // Preserve transparency for PNG images
                if (strtolower($fileInfo['extension']) === 'png') {
                    imagealphablending($thumbImage, false);
                    imagesavealpha($thumbImage, true);
                    $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                    imagefilledrectangle($thumbImage, 0, 0, $width, $height, $transparent);
                } else {
                    // Fill with white background for JPG
                    $white = imagecolorallocate($thumbImage, 255, 255, 255);
                    imagefilledrectangle($thumbImage, 0, 0, $width, $height, $white);
                }
                
                // Enable image interpolation for better quality
                imagesetinterpolation($thumbImage, IMG_BICUBIC);
                
                if ($this->thumbnailCropMode === 'crop') {
                    // CROP MODE: Fill entire canvas, may crop parts of image
                    $scaleX = $width / $origWidth;
                    $scaleY = $height / $origHeight;
                    $scale = max($scaleX, $scaleY); // Use larger scale to fill canvas
                    
                    $newWidth = (int)round($origWidth * $scale);
                    $newHeight = (int)round($origHeight * $scale);
                    
                    // Calculate crop position (center crop)
                    $srcX = 0;
                    $srcY = 0;
                    $srcWidth = $origWidth;
                    $srcHeight = $origHeight;
                    
                    if ($newWidth > $width) {
                        // Need to crop horizontally
                        $srcWidth = (int)round($width / $scale);
                        $srcX = (int)round(($origWidth - $srcWidth) / 2);
                    }
                    
                    if ($newHeight > $height) {
                        // Need to crop vertically
                        $srcHeight = (int)round($height / $scale);
                        $srcY = (int)round(($origHeight - $srcHeight) / 2);
                    }
                    
                    // Resize and crop image to fill entire canvas
                    if (!imagecopyresampled(
                        $thumbImage, $sourceImage,
                        0, 0, $srcX, $srcY,
                        $width, $height,
                        $srcWidth, $srcHeight
                    )) {
                        continue;
                    }
                } else {
                    // FIT MODE: Preserve aspect ratio, may have empty spaces
                    $ratio = min($width / $origWidth, $height / $origHeight);
                    $newWidth = (int)round($origWidth * $ratio);
                    $newHeight = (int)round($origHeight * $ratio);
                    
                    // Calculate centering position
                    $x = (int)floor(($width - $newWidth) / 2);
                    $y = (int)floor(($height - $newHeight) / 2);
                    
                    // Resize image with preserved aspect ratio
                    if (!imagecopyresampled(
                        $thumbImage, $sourceImage,
                        $x, $y, 0, 0,
                        $newWidth, $newHeight,
                        $origWidth, $origHeight
                    )) {
                        continue;
                    }
                }
                
                // Save thumbnail with high quality
                if ($this->saveImage($thumbImage, $thumbPath, $fileInfo['extension'])) {
                    $thumbnails[$size] = $yearMonth . $size . '/' . $thumbFilename;
                }
                
                // Free memory
                imagedestroy($thumbImage);
                
            } catch (\Exception $e) {
                // Silently continue on thumbnail generation errors
            }
        }
        
        // Free source image memory
        if (isset($sourceImage)) {
            if ($sourceImage instanceof \GdImage || is_resource($sourceImage)) {
                imagedestroy($sourceImage);
            }
        }
        
        return $thumbnails;
    }

    /**
     * Resize original file to specified dimensions
     * 
     * @param string $fileContent Raw file content
     * @param string $uploadPath Target upload path
     * @param string $targetSize Target size (e.g., '150x150')
     * @return string|false Resized file content or false on failure
     */
    protected function resizeOriginalFile($fileContent, $uploadPath, $targetSize)
    {
        try {
            // Parse target dimensions
            list($width, $height) = explode('x', strtolower($targetSize));
            $width = (int)$width;
            $height = (int)$height;
            
            // Create temporary file for processing
            $tempFile = tempnam(sys_get_temp_dir(), 'nexa_resize_');
            if (!$tempFile || !file_put_contents($tempFile, $fileContent)) {
                return false;
            }
            
            // Load source image
            $sourceImage = $this->loadImage($tempFile);
            if (!$sourceImage) {
                unlink($tempFile);
                return false;
            }
            
            // Get file extension for proper saving
            $extension = strtolower(pathinfo($uploadPath, PATHINFO_EXTENSION));
            
            // Create resized image
            $resizedImage = imagecreatetruecolor($width, $height);
            if (!$resizedImage) {
                imagedestroy($sourceImage);
                unlink($tempFile);
                return false;
            }
            
            // Preserve transparency for PNG
            if ($extension === 'png') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $width, $height, $transparent);
            }
            
            // Get original dimensions
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);
            
            // Resize with crop mode (fill entire canvas)
            $scaleX = $width / $origWidth;
            $scaleY = $height / $origHeight;
            $scale = max($scaleX, $scaleY);
            
            $newWidth = (int)round($origWidth * $scale);
            $newHeight = (int)round($origHeight * $scale);
            
            // Calculate crop position (center crop)
            $srcX = 0;
            $srcY = 0;
            $srcWidth = $origWidth;
            $srcHeight = $origHeight;
            
            if ($newWidth > $width) {
                $srcWidth = (int)round($width / $scale);
                $srcX = (int)round(($origWidth - $srcWidth) / 2);
            }
            
            if ($newHeight > $height) {
                $srcHeight = (int)round($height / $scale);
                $srcY = (int)round(($origHeight - $srcHeight) / 2);
            }
            
            // Perform resize
            if (!imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, $srcX, $srcY,
                $width, $height,
                $srcWidth, $srcHeight
            )) {
                imagedestroy($sourceImage);
                imagedestroy($resizedImage);
                unlink($tempFile);
                return false;
            }
            
            // Save resized image to temporary file
            $tempResized = tempnam(sys_get_temp_dir(), 'nexa_resized_');
            if (!$this->saveImage($resizedImage, $tempResized, $extension)) {
                imagedestroy($sourceImage);
                imagedestroy($resizedImage);
                unlink($tempFile);
                return false;
            }
            
            // Get resized content
            $resizedContent = file_get_contents($tempResized);
            
            // Cleanup
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            unlink($tempFile);
            unlink($tempResized);
            
            return $resizedContent;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function loadImage($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        // Use getimagesize to detect actual image type (more reliable than extension)
        $imageInfo = getimagesize($path);
        if ($imageInfo === false) {
            return false;
        }
        
        $imageType = $imageInfo[2]; // IMAGETYPE_* constant
        
        try {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    return imagecreatefromjpeg($path);
                case IMAGETYPE_PNG:
                    return imagecreatefrompng($path);
                case IMAGETYPE_GIF:
                    return imagecreatefromgif($path);
                case IMAGETYPE_WEBP:
                    return imagecreatefromwebp($path);
                case IMAGETYPE_BMP:
                    return imagecreatefrombmp($path);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function saveImage($image, $path, $extension) {
        $result = false;
        try {
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    $result = imagejpeg($image, $path, 95); // Increased quality to 95
                    break;
                case 'png':
                    $result = imagepng($image, $path, 1); // Reduced compression for better quality (1-9, lower is better quality)
                    break;
                case 'gif':
                    $result = imagegif($image, $path);
                    break;
                case 'webp':
                    $result = imagewebp($image, $path, 95); // High quality WebP
                    break;
                case 'bmp':
                    $result = imagebmp($image, $path);
                    break;
            }
        } catch (\Exception $e) {
            // Silently handle image saving errors
        }
        return $result;
    }

    // Setter methods
    public function setMaxFileSize($size) {
        $this->maxFileSize = $size;
    }

    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }

    public function setAllowedExtensions($extensions) {
        $this->allowedExtensions = $extensions;
    }

    public function setBaseUploadDir($dir) {
        $this->baseUploadDir = $dir;
    }

    public function setThumbnailSizes($sizes) {
        $this->thumbnailSizes = $sizes;
    }

    public function setThumbnailCropMode($mode) {
        // Validate mode
        if (!in_array($mode, ['fit', 'crop'])) {
            throw new \InvalidArgumentException("Invalid crop mode. Use 'fit' or 'crop'");
        }
        $this->thumbnailCropMode = $mode;
    }

    /**
     * Upload file secara otomatis dengan konfigurasi default
     * 
     * @param array $fileData Data file dari $_FILES
     * @param array $options Opsi tambahan (opsional)
     * @return array Informasi file yang diupload
     */
    public function autoUpload($fileData, $options = []) {
        // Set konfigurasi default (sync with FormBuilder)
        $defaultConfig = [
            'maxSize' => '15MB', // 15MB
            'allowedTypes' => $this->allowedTypes, // Use comprehensive types list
            'allowedExtensions' => $this->allowedExtensions // Use comprehensive extensions list
        ];

        // Merge dengan opsi yang diberikan
        $config = array_merge($defaultConfig, $options);

        // Set konfigurasi
        $this->setMaxFileSize($this->parseSizeToBytes($config['maxSize']));
        $this->setAllowedTypes($config['allowedTypes']);
        $this->setAllowedExtensions($config['allowedExtensions']);

        // Handle upload
        return $this->handleFileUpload($fileData);
    }

    /**
     * Mendapatkan pesan error upload yang user-friendly
     * 
     * @param int $errorCode Error code dari $_FILES
     * @return string Pesan error yang sesuai
     */
    public function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Validasi file upload dari $_FILES
     * 
     * @param array $fileData Data file dari $_FILES
     * @param string $fieldName Nama field file (opsional, untuk pesan error)
     * @return array ['valid' => bool, 'error' => string]
     */
    public function validateFileUpload($fileData, $fieldName = 'file') {
        // Cek apakah data file ada
        if (!isset($fileData) || !is_array($fileData)) {
            return [
                'valid' => false,
                'error' => 'No ' . $fieldName . ' data received'
            ];
        }

        // Cek apakah field yang diperlukan ada
        $requiredFields = ['name', 'type', 'size', 'error'];
        foreach ($requiredFields as $field) {
            if (!isset($fileData[$field])) {
                return [
                    'valid' => false,
                    'error' => 'Missing ' . $field . ' field in file data'
                ];
            }
        }

        // Cek error upload
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => $this->getUploadErrorMessage($fileData['error'])
            ];
        }

        // Cek apakah file benar-benar diupload (untuk $_FILES)
        if (isset($fileData['tmp_name']) && !is_uploaded_file($fileData['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'File was not uploaded via HTTP POST'
            ];
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    /**
     * Upload file dengan validasi otomatis
     * 
     * @param array $fileData Data file dari $_FILES
     * @param array $config Konfigurasi upload (opsional)
     * @param string $fieldName Nama field untuk pesan error (opsional)
     * @return array Hasil upload atau throw Exception jika gagal
     */
    public function uploadWithValidation($fileData, $config = [], $fieldName = 'file') {
        // Validasi file terlebih dahulu
        $validation = $this->validateFileUpload($fileData, $fieldName);
        
        if (!$validation['valid']) {
            throw new \Exception($validation['error']);
        }

        // Jika valid, lakukan upload
        return $this->handleFileUpload($fileData, $config);
    }

    /**
     * Hapus file dan thumbnail-nya
     * 
     * @param string|array $fileInfo Path file atau array dengan informasi file
     *                               String: '2023/12/photo_1703123456_def67890.jpg'
     *                               Array: ['path' => '...', 'thumbnails' => ['100x100' => '...']]
     * @return array Hasil penghapusan file
     */
    public function deleteFile($fileInfo) {
        // Jika hanya string path, konversi ke array
        if (is_string($fileInfo)) {
            $fileInfo = ['path' => $fileInfo];
        }

        if (!isset($fileInfo['path']) || empty($fileInfo['path'])) {
            throw new \Exception("File path is required and cannot be empty");
        }

        // Sanitize path untuk keamanan
        $relativePath = $this->sanitizePath($fileInfo['path']);
        
        $result = [
            'main_file' => false,
            'thumbnails' => [],
            'errors' => [],
            'deleted_files' => []
        ];

        // Hapus file utama
        $mainPath = $this->baseUploadDir . $relativePath;
        if (file_exists($mainPath)) {
            if (unlink($mainPath)) {
                $result['main_file'] = true;
                $result['deleted_files'][] = $relativePath;
            } else {
                $result['errors'][] = "Failed to delete main file: " . $relativePath;
            }
        } else {
            $result['errors'][] = "Main file not found: " . $relativePath;
        }

        // Hapus thumbnails jika ada informasi thumbnail
        if (isset($fileInfo['thumbnails']) && is_array($fileInfo['thumbnails'])) {
            foreach ($fileInfo['thumbnails'] as $size => $thumbPath) {
                $sanitizedThumbPath = $this->sanitizePath($thumbPath);
                $fullThumbPath = $this->baseUploadDir . $sanitizedThumbPath;
                
                if (file_exists($fullThumbPath)) {
                    if (unlink($fullThumbPath)) {
                        $result['thumbnails'][$size] = true;
                        $result['deleted_files'][] = $sanitizedThumbPath;
                    } else {
                        $result['errors'][] = "Failed to delete thumbnail [{$size}]: " . $sanitizedThumbPath;
                    }
                } else {
                    $result['errors'][] = "Thumbnail not found [{$size}]: " . $sanitizedThumbPath;
                }
            }
        } else {
            // Auto-detect dan hapus thumbnails jika tidak ada informasi eksplisit
            $autoDeletedThumbnails = $this->autoDetectAndDeleteThumbnails($relativePath);
            $result['thumbnails'] = array_merge($result['thumbnails'], $autoDeletedThumbnails['thumbnails']);
            $result['errors'] = array_merge($result['errors'], $autoDeletedThumbnails['errors']);
            $result['deleted_files'] = array_merge($result['deleted_files'], $autoDeletedThumbnails['deleted_files']);
        }

        return $result;
    }

    /**
     * Auto-detect dan hapus thumbnails berdasarkan file utama
     * 
     * @param string $relativePath Path relatif file utama
     * @param array $customSizes Ukuran thumbnail custom (opsional)
     * @return array Hasil penghapusan thumbnails
     */
    protected function autoDetectAndDeleteThumbnails($relativePath, $customSizes = null) {
        $result = [
            'thumbnails' => [],
            'errors' => [],
            'deleted_files' => []
        ];

        $pathInfo = pathinfo($relativePath);
        $directory = $pathInfo['dirname'] === '.' ? '' : $pathInfo['dirname'] . '/';
        $filename = $pathInfo['basename'];

        // Prioritas ukuran thumbnail:
        // 1. Custom sizes yang diberikan parameter
        // 2. Ukuran dari konfigurasi class ($this->thumbnailSizes)
        // 3. Scan direktori untuk menemukan ukuran yang ada
        $sizesToCheck = [];
        
        if ($customSizes !== null && is_array($customSizes)) {
            // Gunakan custom sizes
            $sizesToCheck = $customSizes;
        } elseif (!empty($this->thumbnailSizes)) {
            // Gunakan ukuran dari konfigurasi class
            $sizesToCheck = $this->thumbnailSizes;
        } else {
            // Scan direktori untuk mencari thumbnail yang ada
            $sizesToCheck = $this->scanForExistingThumbnailSizes($directory, $filename);
        }
        
        foreach ($sizesToCheck as $size) {
            $thumbPath = $directory . $size . '/' . $filename;
            $fullThumbPath = $this->baseUploadDir . $thumbPath;
            
            if (file_exists($fullThumbPath)) {
                if (unlink($fullThumbPath)) {
                    $result['thumbnails'][$size] = true;
                    $result['deleted_files'][] = $thumbPath;
                } else {
                    $result['errors'][] = "Failed to delete auto-detected thumbnail [{$size}]: " . $thumbPath;
                }
            }
        }

        return $result;
    }

    /**
     * Scan direktori untuk mencari ukuran thumbnail yang ada
     * 
     * @param string $directory Direktori dasar
     * @param string $filename Nama file
     * @return array Daftar ukuran thumbnail yang ditemukan
     */
    protected function scanForExistingThumbnailSizes($directory, $filename) {
        $sizes = [];
        $baseDir = $this->baseUploadDir . $directory;
        
        if (!is_dir($baseDir)) {
            return $sizes;
        }
        
        // Scan subdirektori untuk pola ukuran (contoh: 100x100, 300x300, dll)
        $dirs = scandir($baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            
            $fullDirPath = $baseDir . $dir;
            
            // Cek apakah ini direktori dan punya pola ukuran
            if (is_dir($fullDirPath) && preg_match('/^\d+x\d+$/', $dir)) {
                // Cek apakah file ada di direktori ini
                $thumbFile = $fullDirPath . '/' . $filename;
                if (file_exists($thumbFile)) {
                    $sizes[] = $dir;
                }
            }
        }
        
        return $sizes;
    }

    /**
     * Sanitize path untuk keamanan
     * 
     * @param string $path Path yang akan disanitasi
     * @return string Path yang sudah aman
     */
    protected function sanitizePath($path) {
        // Hapus leading slash
        $path = ltrim($path, '/\\');
        
        // Hapus path traversal attempts
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        
        return $path;
    }


}
