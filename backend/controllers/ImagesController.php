<?php
declare(strict_types=1);

namespace App\Controllers;

use App\System\NexaController;

/**
 * ImagesController - File Serving Controller untuk images
 *
 * Resolusi: 1. templates/{theme|mobile|tablet|dashboard}/assets/images/ → 2. assets/images/
 * - Request: /images/logo.png → templates/theme/assets/images/logo.png (jika ada)
 * - Request: /images/2026/01/photo.png → assets/images/2026/01/photo.png (fallback)
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
class ImagesController extends NexaController
{
    /**
     * Base path untuk images files
     */
    private string $imagesBasePath;
    
    /**
     * Allowed file extensions untuk security (only images)
     */
    private array $allowedExtensions = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tiff'
    ];
    
    /**
     * Constructor
     */
    public function __construct($template = null, $deviceLayouts = [])
    {
        // ImagesController tidak perlu template karena langsung output file
        // Jika dipanggil dari router, template bisa null
        if ($template !== null) {
            parent::__construct($template, $deviceLayouts);
        }
        
        // Set base path ke assets/images/
        $this->imagesBasePath = dirname(__DIR__) . '/assets/images/';
    }
    
    /**
     * Serve file dari templates/{active}/assets/images/ atau assets/images/
     *
     * URL Pattern: /images/{path}
     * Resolusi: 1. templates/{theme|mobile|tablet|dashboard}/assets/images/ → 2. assets/images/
     *
     * @param mixed $params Parameter dari router (bisa string atau array)
     * @return void
     */
    public function index($params = null): void
    {
        // Parse path dari URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Remove /images/ prefix dan query string
        $path = preg_replace('#^/[^/]*/images/#', '', $requestUri); // Handle project dir
        $path = preg_replace('#^/images/#', '', $path); // Handle root
        $path = strtok($path, '?'); // Remove query string

        // Decode URL encoding (spaces, special chars)
        $path = urldecode($path);

        if (empty($path)) {
            $this->sendError(404, 'Image Not Found');
            return;
        }

        // Deteksi template aktif (cookie atau User-Agent)
        $template = $_COOKIE['nexa_template'] ?? null;
        $allowedFolders = ['theme', 'mobile', 'tablet', 'dashboard'];
        if (!$template || !in_array($template, $allowedFolders, true)) {
            $agent = new \App\System\Helpers\NexaAgent();
            $device = $agent->getDevice();
            $template = $device['is_mobile'] ? 'mobile' : ($device['is_tablet'] ? 'tablet' : 'theme');
        }

        $templatesBasePath = dirname(__DIR__) . '/templates/';

        // 1. Coba templates/{template}/assets/images/
        $templateImagePath = $templatesBasePath . $template . '/assets/images/' . $path;
        // 2. Fallback ke assets/images/
        $rootImagePath = $this->imagesBasePath . $path;

        $filePath = null;
        $realBase = null;

        if (file_exists($templateImagePath) && is_file($templateImagePath)) {
            $filePath = $templateImagePath;
            $realBase = realpath($templatesBasePath . $template . '/assets/images/');
        } elseif (file_exists($rootImagePath) && is_file($rootImagePath)) {
            $filePath = $rootImagePath;
            $realBase = realpath($this->imagesBasePath);
        }

        if (!$filePath || !$realBase) {
            $this->sendError(404, 'Image Not Found');
            return;
        }

        // ═══════════════════════════════════════════════════════════════
        // SECURITY CHECK 1: Directory Traversal Prevention
        // ═══════════════════════════════════════════════════════════════
        $realPath = realpath($filePath);

        if (!$realPath || strpos($realPath, $realBase) !== 0) {
            $this->sendError(404, 'Image Not Found');
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // SECURITY CHECK 2: File Extension Whitelist (Images only)
        // ═══════════════════════════════════════════════════════════════
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->sendError(403, 'File Type Not Allowed - Images Only');
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // FILE EXISTENCE CHECK
        // ═══════════════════════════════════════════════════════════════
        if (!file_exists($realPath) || !is_file($realPath)) {
            $this->sendError(404, 'Image Not Found');
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
        // IMAGE MANIPULATION (Optional - via query params)
        // ═══════════════════════════════════════════════════════════════
        $width = isset($_GET['w']) ? (int)$_GET['w'] : null;
        $height = isset($_GET['h']) ? (int)$_GET['h'] : null;
        
        if ($width || $height) {
            $this->serveResizedImage($realPath, $extension, $width, $height, $etag);
            return;
        }
        
        // ═══════════════════════════════════════════════════════════════
        // SERVE IMAGE
        // ═══════════════════════════════════════════════════════════════
        $mimeType = $this->getMimeType($extension);
        $fileSize = filesize($realPath);
        
        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('ETag: "' . $etag . '"');
        header('Cache-Control: public, max-age=31536000'); // Cache 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
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
     * Serve resized image on-the-fly
     * URL: /images/2026/01/photo.png?w=300&h=200
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
            $this->sendError(400, 'Image dimensions too large');
            return;
        }
        
        // Load image based on type
        $sourceImage = $this->loadImage($imagePath, $extension);
        
        if (!$sourceImage) {
            $this->sendError(500, 'Cannot process image');
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
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                return imagecreatefromwebp($path);
            case 'bmp':
                return imagecreatefrombmp($path);
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
     * Get MIME type berdasarkan extension
     * 
     * @param string $extension File extension
     * @return string MIME type
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
        ];
        
        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
    
    /**
     * Check apakah file besar perlu chunked reading
     * 
     * @param int $fileSize File size in bytes
     * @return bool
     */
    private function shouldUseChunked(int $fileSize): bool
    {
        // Use chunked reading untuk file > 2MB (images biasanya lebih kecil)
        return $fileSize > (2 * 1024 * 1024);
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
                'images_path' => $this->imagesBasePath
            ], JSON_PRETTY_PRINT);
        } else {
            echo $code . ' - ' . $message;
        }
        
        exit;
    }
}
