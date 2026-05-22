<?php
declare(strict_types=1);
namespace App\System\Helpers;

use App\System\Helpers\NexaRequest;

/**
 * Secure file download handler
 * Prevents direct access to file locations by using tokenized URLs
 */

// Original file locations for reference
/*
Lokasi File
/assets/drive/2025/06/cover_1751042050_58e555cb.jpg
/assets/drive/2025/06/abdul-gto-cgk-15-jan_1751070296_2f06ae9d.pdf 
*/

class NexaDownload {
    private $basePath = '/assets/drive/';
    private $request;
    
    public function __construct() {
        $this->request = new NexaRequest();
    }
    
    private $allowedTypes = [
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
        'text/markdown',
        
        // Programming
        'application/x-httpd-php', 'text/x-python', 'text/x-java-source', 'text/x-c++src', 'text/x-csrc', 'text/x-chdr', 'application/sql'
    ];

    private $allowedExtensions = [
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
        'md',
        // Programming
        'php', 'py', 'java', 'cpp', 'c', 'h', 'sql'
    ];

    /**
     * Generate a secure token for file download
     * @param string $filePath Original file path
     * @return string Encrypted token
     */
    public function generateDownloadToken($filePath) {
        // Create a unique token using file path and timestamp
        $data = [
            'path' => $filePath,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(8))
        ];
        
        return base64_encode(json_encode($data));
    }

    /**
     * Validate and decode download token
     * @param string $token Download token
     * @return string|false Original file path or false if invalid
     */
    private function validateToken($token) {
        try {
            // Decode the base64 token
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                error_log("Failed to base64 decode token");
                return false;
            }

            // Decode the JSON data
            $data = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Failed to JSON decode token: " . json_last_error_msg());
                return false;
            }

            // Validate required fields
            if (!isset($data['path']) || !isset($data['timestamp']) || !isset($data['random'])) {
                error_log("Missing required fields in token data");
                return false;
            }

            // Check if token is expired (24 hours)
            if (time() - $data['timestamp'] > 86400) {
                error_log("Token expired. Current time: " . time() . ", Token time: " . $data['timestamp']);
                return false;
            }

            // Normalize the path (replace Windows-style backslashes with forward slashes)
            return str_replace('\\', '/', $data['path']);
        } catch (\Exception $e) {
            error_log("Exception in validateToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate file type using both MIME type and extension
     * @param string $filePath File path
     * @param string $actualMimeType Actual MIME type from the file
     * @return bool Whether the file type is allowed
     */
    private function validateFileType($filePath, $actualMimeType = null) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Check if extension is allowed
        if (!in_array($extension, $this->allowedExtensions)) {
            return false;
        }

        // If actual MIME type is provided, check against allowed types
        if ($actualMimeType !== null) {
            return in_array($actualMimeType, $this->allowedTypes);
        }

        // If no MIME type provided, try to detect it
        if (function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($filePath);
            return in_array($detectedMime, $this->allowedTypes);
        }

        // Fallback to extension-only validation if MIME detection is not available
        return true;
    }

    /**
     * Process file download using secure token
     * @param string $token Download token
     * @return bool True if download successful, false otherwise
     */
    public function processDownload($token) {
        // Extract token from URL if full URL is passed
        if (strpos($token, '?token=') !== false) {
            parse_str(parse_url($token, PHP_URL_QUERY), $params);
            $token = $params['token'] ?? '';
        }
        
        // First decode the URL-encoded token
        $token = urldecode($token);
        
        // Validate the token and get the file path
        $filePath = $this->validateToken($token);
        
        if (!$filePath) {
            error_log("Token validation failed for token: " . $token);
            header('HTTP/1.0 403 Forbidden');
            return false;
        }

        // Normalize file path (replace Windows-style backslashes with forward slashes)
        $filePath = str_replace('\\', '/', $filePath);
        
        // Use NexaRequest to get the correct base directory
        $fullPath = $this->request->getBaseDir() . $this->basePath . $filePath;
        
        if (!file_exists($fullPath)) {
            error_log("File not found: " . $fullPath);
            header('HTTP/1.0 404 Not Found');
            return false;
        }

        // Validate file type
        if (!$this->validateFileType($fullPath)) {
            header('HTTP/1.0 403 Forbidden');
            return false;
        }

        // Get actual MIME type
        $mimeType = function_exists('mime_content_type') ? 
            mime_content_type($fullPath) : 
            $this->getMimeTypeFromExtension($fullPath);

        // Set headers for download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file content
        readfile($fullPath);
        return true;
    }

    /**
     * Get MIME type from file extension (fallback method)
     * @param string $filePath File path
     * @return string MIME type
     */
    private function getMimeTypeFromExtension($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $commonMimeTypes = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            // Spreadsheets
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'csv' => 'text/csv',
            // Presentations
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            // Apple iWork
            'pages' => 'application/vnd.apple.pages',
            'numbers' => 'application/vnd.apple.numbers',
            'key' => 'application/vnd.apple.keynote',
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            // Video
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            // Web & Data
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'md' => 'text/markdown',
            // Programming
            'php' => 'application/x-httpd-php',
            'py' => 'text/x-python',
            'java' => 'text/x-java-source',
            'cpp' => 'text/x-c++src',
            'c' => 'text/x-csrc',
            'h' => 'text/x-chdr',
            'sql' => 'application/sql',
        ];

        return isset($commonMimeTypes[$extension]) ? 
            $commonMimeTypes[$extension] : 
            'application/octet-stream';
    }

    /**
     * Generate a secure download URL
     * @param string $filePath Original file path
     * @return string Secure download URL
     */
    public function getSecureDownloadUrl($filePath) {
        // Normalize the file path to use forward slashes
        $filePath = str_replace('\\', '/', trim($filePath));
        
        // Generate the token
        $token = $this->generateDownloadToken($filePath);
        
        // URL encode the token to make it safe for URLs
        return '/download?token=' . urlencode($token);
    }
}

// Example usage:
/*
$download = new Download();

// Generate secure URL for a file
$secureUrl = $download->getSecureDownloadUrl('2025/06/cover_1751042050_58e555cb.jpg');

// Process download (in download handler script)
$download->processDownload($_GET['token']);
*/