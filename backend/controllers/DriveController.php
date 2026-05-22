<?php
declare(strict_types=1);

namespace App\Controllers;

use App\System\NexaController;

/**
 * DriveController - File Serving Controller untuk assets/drive/
 * 
 * Controller ini menangani request untuk file di assets/drive/ dengan URL yang lebih bersih:
 * - Request: /drive/2026/01/image.png
 * - Actual: assets/drive/2026/01/image.png
 * 
 * Features:
 * - Security: Directory traversal prevention
 * - Performance: Browser caching dengan ETag
 * - Flexibility: Bisa ditambah watermark, resize, access control, dll
 * - Logging: Track file access (optional)
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    NexaUI Team
 */
class DriveController extends NexaController
{
    /**
     * Base path untuk drive files
     */
    private string $driveBasePath;
    
    /**
     * Allowed file extensions untuk security
     */
    private array $allowedExtensions = [
        // Images
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        // Archives
        'zip', 'rar', '7z', 'tar', 'gz',
        // Video/Audio
        'mp4', 'avi', 'mov', 'wmv', 'mp3', 'wav', 'ogg',
        // Other
        'json', 'xml'
    ];
    
    /**
     * Constructor
     */
    public function __construct($template = null, $deviceLayouts = [])
    {
        // DriveController tidak perlu template karena langsung output file
        // Jika dipanggil dari router, template bisa null
        if ($template !== null) {
            parent::__construct($template, $deviceLayouts);
        }
        
        // Set base path ke assets/drive/
        $this->driveBasePath = dirname(__DIR__) . '/assets/drive/';
    }
    
    /**
     * Serve file dari assets/drive/
     * 
     * URL Pattern: /drive/{path}
     * Example: 
     * - /drive/2026/01/image.png → assets/drive/2026/01/image.png
     * - /drive/150x150/2026/01/image.png → assets/drive/150x150/2026/01/image.png
     * - /drive/300x300/2026/01/image.png → assets/drive/300x300/2026/01/image.png
     * 
     * @param mixed $params Parameter dari router (bisa string atau array)
     * @return void
     */
    public function index($params = null): void
    {
        // Parse path dari URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Remove /drive/ prefix dan query string
        $path = preg_replace('#^/[^/]*/drive/#', '', $requestUri); // Handle project dir
        $path = preg_replace('#^/drive/#', '', $path); // Handle root
        $path = strtok($path, '?'); // Remove query string
        
        // Decode URL encoding (spaces, special chars)
        $path = urldecode($path);
        
        // ═══════════════════════════════════════════════════════════════
        // THUMBNAIL SIZE MAPPING
        // ═══════════════════════════════════════════════════════════════
        // URL: /drive/150x150/2026/01/image.png
        // Maps to: assets/drive/2026/01/150x150/image.png
        //
        // Check if path starts with thumbnail size pattern (150x150, 300x300, 600x600, etc)
        if (preg_match('#^(\d+x\d+)/(.+)$#', $path, $matches)) {
            $size = $matches[1];        // e.g., "150x150"
            $restPath = $matches[2];    // e.g., "2026/01/image.png"
            
            // Extract year/month and filename
            // Pattern: 2026/01/image.png
            if (preg_match('#^(\d{4}/\d{2})/(.+)$#', $restPath, $pathMatches)) {
                $yearMonth = $pathMatches[1];  // "2026/01"
                $filename = $pathMatches[2];    // "image.png"
                
                // Remap: 2026/01/150x150/image.png
                $filePath = $this->driveBasePath . $yearMonth . '/' . $size . '/' . $filename;
            } else {
                // Fallback: use original path structure
                $filePath = $this->driveBasePath . $path;
            }
        } else {
            // Normal path without size prefix
            $filePath = $this->driveBasePath . $path;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // SECURITY CHECK 1: Directory Traversal Prevention
        // ═══════════════════════════════════════════════════════════════
        $realPath = realpath($filePath);
        $realBase = realpath($this->driveBasePath);
        
        if (!$realPath || !$realBase || strpos($realPath, $realBase) !== 0) {
            $this->sendError(404, 'File Not Found');
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // SECURITY CHECK 2: File Extension Whitelist
        // ═══════════════════════════════════════════════════════════════
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->sendError(403, 'File Type Not Allowed');
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // FILE EXISTENCE CHECK
        // ═══════════════════════════════════════════════════════════════
        if (!file_exists($realPath) || !is_file($realPath)) {
            $this->sendError(404, 'File Not Found');
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // ETAG & CONDITIONAL REQUEST (304 Not Modified)
        // ═══════════════════════════════════════════════════════════════
        $etag = md5_file($realPath);
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        
        if ($ifNoneMatch === '"' . $etag . '"') {
            http_response_code(304); // Not Modified
            exit;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // IMAGE RESIZE (if requested and file is image)
        // ═══════════════════════════════════════════════════════════════
        $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
        $width = isset($_GET['w']) ? (int)$_GET['w'] : null;
        $height = isset($_GET['h']) ? (int)$_GET['h'] : null;
        
        if (in_array($extension, $imageExtensions) && ($width || $height)) {
            $this->serveResizedImage($realPath, $extension, $width, $height, $etag);
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // SERVE FILE
        // ═══════════════════════════════════════════════════════════════
        $mimeType = $this->getMimeType($extension);
        $fileSize = filesize($realPath);
        
        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('ETag: "' . $etag . '"');
        header('Cache-Control: public, max-age=31536000'); // Cache 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        // Optional: Add Content-Disposition for downloads
        if (in_array($extension, ['zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
            $filename = basename($realPath);
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        
        // Optional: Security headers
        header('X-Content-Type-Options: nosniff');
        
        // Output file efficiently
        if ($this->shouldUseChunked($fileSize)) {
            $this->readfileChunked($realPath);
        } else {
            readfile($realPath);
        }
        
        exit;
    }

    /**
     * Serve avatar - shortcut /avatar/{path} → assets/drive/avatar/{path}
     * Example: /avatar/2026/03/avatar_4569_1773162822.jpg
     */
    public function avatar($params = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = preg_replace('#^/[^/]*/avatar/#', '', $requestUri);
        $path = preg_replace('#^/avatar/#', '', $path);
        $path = strtok($path, '?');
        $path = urldecode($path);

        if (empty($path)) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $path = 'avatar/' . ltrim($path, '/');
        $filePath = $this->driveBasePath . $path;

        $realPath = realpath($filePath);
        $realBase = realpath($this->driveBasePath);

        if (!$realPath || !$realBase || strpos($realPath, $realBase) !== 0) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->sendError(403, 'File Type Not Allowed');
            return;
        }

        if (!file_exists($realPath) || !is_file($realPath)) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $etag = md5_file($realPath);
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === '"' . $etag . '"') {
            http_response_code(304);
            exit;
        }

        $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
        $width = isset($_GET['w']) ? (int)$_GET['w'] : null;
        $height = isset($_GET['h']) ? (int)$_GET['h'] : null;
        if (in_array($extension, $imageExtensions) && ($width || $height)) {
            $this->serveResizedImage($realPath, $extension, $width, $height, $etag);
            return;
        }

        $mimeType = $this->getMimeType($extension);
        $fileSize = filesize($realPath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('ETag: "' . $etag . '"');
        header('Cache-Control: public, max-age=31536000');
        header('X-Content-Type-Options: nosniff');
        if ($this->shouldUseChunked($fileSize)) {
            $this->readfileChunked($realPath);
        } else {
            readfile($realPath);
        }
        exit;
    }
    
    /**
     * Get MIME type berdasarkan extension
     * 
     * @param string $extension File extension
     * @return string MIME type
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            // Images
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            
            // Video/Audio
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            
            // Other
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Check apakah file besar perlu chunked reading
     * 
     * @param int $fileSize File size in bytes
     * @return bool
     */
    private function shouldUseChunked(int $fileSize): bool
    {
        // Use chunked reading untuk file > 5MB
        return $fileSize > (5 * 1024 * 1024);
    }
    
    /**
     * Read file in chunks untuk memory efficiency (large files)
     * 
     * @param string $filePath Path to file
     * @param int $chunkSize Chunk size in bytes (default 8KB)
     * @return void
     */
    private function readfileChunked(string $filePath, int $chunkSize = 8192): void
    {
        $handle = fopen($filePath, 'rb');
        
        if ($handle === false) {
            $this->sendError(500, 'Cannot Read File');
            return;
        }
        
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            flush(); // Flush output buffer
        }
        
        fclose($handle);
    }
    
    /**
     * Send error response
     * 
     * @param int $code HTTP status code
     * @param string $message Error message
     * @return void
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        
        // Check if debug mode
        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        
        if ($isDebug) {
            echo json_encode([
                'error' => $message,
                'code' => $code,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'drive_path' => $this->driveBasePath
            ], JSON_PRETTY_PRINT);
        } else {
            echo $code . ' - ' . $message;
        }
        
        exit;
    }
    
    /**
     * Serve resized image on-the-fly (NEW: Added for drive images)
     * URL: /drive/2026/01/photo.png?w=300&h=200
     * 
     * @param string $imagePath Path to original image
     * @param string $extension File extension
     * @param int|null $width Target width
     * @param int|null $height Target height
     * @param string $etag Original ETag
     * @return void
     */
    private function serveResizedImage(string $imagePath, string $extension, ?int $width, ?int $height, string $etag): void
    {
        // Validate dimensions
        if (($width && $width > 5000) || ($height && $height > 5000)) {
            $this->sendError(400, 'Image dimensions too large (max 5000px)');
            return;
        }
        
        // Load image based on type
        $sourceImage = $this->loadImage($imagePath, $extension);
        
        if (!$sourceImage) {
            $this->sendError(500, 'Cannot process image - unsupported format or corrupted file');
            return;
        }
        
        // Get original dimensions
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        
        // Calculate new dimensions maintaining aspect ratio
        if (!$width) {
            $width = (int)(($height / $origHeight) * $origWidth);
        }
        if (!$height) {
            $height = (int)(($width / $origWidth) * $origHeight);
        }
        
        // Create resized image
        $resizedImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $width, $height, $transparent);
        }
        
        // Resize with high quality
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $width, $height,
            $origWidth, $origHeight
        );
        
        // Set headers
        $mimeType = $this->getMimeType($extension);
        header('Content-Type: ' . $mimeType);
        header('ETag: "' . $etag . '-' . $width . 'x' . $height . '"');
        header('Cache-Control: public, max-age=2592000'); // Cache 30 days for resized
        header('X-Content-Type-Options: nosniff');
        
        // Output resized image
        $this->outputImage($resizedImage, $extension);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        exit;
    }
    
    /**
     * Load image based on file type
     */
    private function loadImage(string $path, string $extension)
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return @imagecreatefromjpeg($path);
            case 'png':
                return @imagecreatefrompng($path);
            case 'gif':
                return @imagecreatefromgif($path);
            case 'webp':
                return @imagecreatefromwebp($path);
            case 'bmp':
                return @imagecreatefrombmp($path);
            default:
                return false;
        }
    }
    
    /**
     * Output image based on file type
     */
    private function outputImage($image, string $extension): void
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, null, 90);
                break;
            case 'png':
                imagepng($image, null, 9);
                break;
            case 'gif':
                imagegif($image);
                break;
            case 'webp':
                imagewebp($image, null, 90);
                break;
            default:
                imagepng($image);
        }
    }
    
    /**
     * Optional: Tambahkan method untuk access control
     * Example: Only logged-in users can access
     */
    // public function secure($params = null): void { }
}
