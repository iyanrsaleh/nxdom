<?php
declare(strict_types=1);

namespace App\Controllers;

use App\System\NexaController;

/**
 * TemplateController - File Serving Controller untuk templates/
 *
 * URL bersih untuk asset di semua subfolder templates (theme, mobile, tablet, dashboard):
 * - /theme/assest/style.css → templates/theme/assest/style.css
 * - /mobile/assest/style.css → templates/mobile/assest/style.css
 * - /tablet/assest/style.css → templates/tablet/assest/style.css
 * - /dashboard/assest/style.css → templates/dashboard/assest/style.css
 *
 * @package   NexaUI
 * @version   2.0.0
 */
class TemplateController extends NexaController
{
    private string $templatesBasePath;
    private string $projectRoot;

    /** Subfolder templates yang diizinkan (whitelist keamanan) */
    private array $allowedFolders = ['theme', 'mobile', 'tablet', 'dashboard'];

    private array $allowedExtensions = [
        'css', 'js', 'json', 'map',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'html', 'txt',
    ];

    /** @var array<string, int> */
    private array $allowedExtSet = [];

    /** realpath templates/{folder} per folder (sekali di ctor) */
    private array $realFolderBases = [];

    /** realpath templates/{folder}/assets per folder */
    private array $realAssetBasesByFolder = [];

    private string $realRootAssets = '';

    public function __construct($template = null, $deviceLayouts = [])
    {
        // Tidak memanggil parent::__construct — sama seperti ModulesController (hindari init() berat per asset)
        $this->projectRoot = dirname(__DIR__);
        $templatesPath = realpath($this->projectRoot . DIRECTORY_SEPARATOR . 'templates');
        $this->templatesBasePath = ($templatesPath ?: $this->projectRoot . DIRECTORY_SEPARATOR . 'templates') . DIRECTORY_SEPARATOR;
        $this->allowedExtSet = array_flip($this->allowedExtensions);

        foreach ($this->allowedFolders as $folder) {
            $fb = realpath($this->templatesBasePath . $folder);
            if ($fb !== false) {
                $this->realFolderBases[$folder] = $fb;
            }
            $ab = realpath($this->templatesBasePath . $folder . DIRECTORY_SEPARATOR . 'assets');
            if ($ab !== false) {
                $this->realAssetBasesByFolder[$folder] = $ab;
            }
        }
        $rootA = realpath($this->projectRoot . DIRECTORY_SEPARATOR . 'assets');
        $this->realRootAssets = $rootA !== false ? $rootA : '';
    }

    /**
     * Serve file dari templates/{folder}/
     *
     * URL Pattern: /{folder}/{path}  (folder = theme|mobile|tablet|dashboard)
     */
    public function index($params = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Extract folder (theme/mobile/tablet/dashboard) dan file path dari URL
        // Support: /folder/path atau /project/folder/path
        if (!preg_match('#/(theme|mobile|tablet|dashboard)/(.+)$#', $requestUri, $m)) {
            $this->sendError(404, 'Invalid template path');
            return;
        }

        $folder = strtolower($m[1]);
        $path = $m[2];
        $path = strtok($path, '?');
        $path = urldecode($path);

        // Whitelist: hanya folder yang diizinkan
        if (!in_array($folder, $this->allowedFolders, true)) {
            $this->sendError(403, 'Template folder not allowed');
            return;
        }

        $filePath = $this->templatesBasePath . $folder . '/' . $path;

        // Security: Directory traversal prevention
        $realPath = realpath($filePath);
        $realBase = $this->realFolderBases[$folder] ?? realpath($this->templatesBasePath . $folder . '/');

        if (!$realPath || !$realBase || !str_starts_with($realPath, $realBase)) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

        if (!isset($this->allowedExtSet[$extension])) {
            $this->sendError(403, 'File Type Not Allowed');
            return;
        }

        $etag = $this->buildFileEtag($realPath);
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

        if ($ifNoneMatch === '"' . $etag . '"') {
            http_response_code(304);
            exit;
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
     * Serve asset dari templates/{active}/assets/ dengan fallback ke assets/
     * URL: /assets/css/style.css → templates/{theme|mobile|tablet|dashboard}/assets/css/style.css
     * Deteksi template aktif: cookie nexa_template (dari render) atau User-Agent
     */
    public function assets($params = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Extract path after /assets/
        if (!preg_match('#/assets/(.+)$#', $requestUri, $m)) {
            $this->sendError(404, 'Invalid asset path');
            return;
        }
        $path = $m[1];
        $path = strtok($path, '?');
        $path = urldecode($path);

        if (empty($path)) {
            $this->sendError(404, 'Asset path required');
            return;
        }

        // Cookie dulu; tanpa cookie pakai deteksi UA ringan (bukan NexaAgent — terlalu berat per request /assets)
        $template = $this->resolveActiveTemplateFolder();

        // Coba templates/{template}/assets/{path} - dengan fallback ke theme jika tidak ada
        $pathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $rootAssetPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $pathNormalized;

        $fileToServe = null;
        $realBase = null;

        // Urutan: template aktif → theme → folder lain; satu is_file() per kandidat
        $templatesToTry = array_values(array_unique([$template, 'theme', 'mobile', 'tablet', 'dashboard']));
        foreach ($templatesToTry as $tryTemplate) {
            $tryPath = $this->templatesBasePath . $tryTemplate . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $pathNormalized;
            if (is_file($tryPath)) {
                $fileToServe = $tryPath;
                $realBase = $this->realAssetBasesByFolder[$tryTemplate] ?? (realpath(dirname($tryPath)) ?: '');
                break;
            }
        }

        if (!$fileToServe && is_file($rootAssetPath)) {
            $fileToServe = $rootAssetPath;
            $realBase = $this->realRootAssets !== '' ? $this->realRootAssets : (realpath(dirname($rootAssetPath)) ?: '');
        }

        if (!$fileToServe || $realBase === '') {
            $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            $this->sendError(404, 'File Not Found', $isDebug ? [
                'template_path' => $this->templatesBasePath . $template . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $pathNormalized,
                'root_path' => $rootAssetPath,
                'project_root' => $this->projectRoot,
            ] : []);
            return;
        }

        $realPath = realpath($fileToServe);
        if (!$realPath || !str_starts_with($realPath, $realBase)) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!isset($this->allowedExtSet[$extension])) {
            $this->sendError(403, 'File Type Not Allowed');
            return;
        }

        $etag = $this->buildFileEtag($realPath);
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === '"' . $etag . '"') {
            http_response_code(304);
            exit;
        }

        // Image resize: ?w=300&h=200 (hanya untuk gambar)
        $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
        $width = isset($_GET['w']) ? (int)$_GET['w'] : null;
        $height = isset($_GET['h']) ? (int)$_GET['h'] : null;
        if (in_array($extension, $imageExtensions) && ($width || $height)) {
            try {
                $this->serveResizedImage($realPath, $extension, $width, $height, $etag);
                return;
            } catch (\Throwable $e) {
                // Fallback: serve original file (mis. iCCP/sRGB profile warning di PNG)
            }
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
     * Cookie nexa_template jika valid; else UA ringkas (tablet → mobile → theme).
     * Menghindari NexaAgent pada setiap request /assets/* .
     */
    private function resolveActiveTemplateFolder(): string
    {
        $fromCookie = $_COOKIE['nexa_template'] ?? null;
        if ($fromCookie && in_array($fromCookie, $this->allowedFolders, true)) {
            return $fromCookie;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua !== '' && preg_match('/tablet|ipad|playbook|silk|kindle/i', $ua)) {
            return 'tablet';
        }
        if ($ua !== '' && preg_match('/Mobile|Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            return 'mobile';
        }

        return 'theme';
    }

    private function buildFileEtag(string $realPath): string
    {
        $mtime = @filemtime($realPath) ?: 0;
        $size = @filesize($realPath) ?: 0;

        return sprintf('%x-%x', $mtime, $size);
    }

    private function serveResizedImage(string $imagePath, string $extension, ?int $width, ?int $height, string $etag): void
    {
        if (($width && $width > 5000) || ($height && $height > 5000)) {
            $this->sendError(400, 'Image dimensions too large');
            return;
        }
        $sourceImage = $this->loadImage($imagePath, $extension);
        if (!$sourceImage) {
            $this->sendError(500, 'Cannot process image');
            return;
        }
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if (!$width) {
            $width = (int)(($height / $origHeight) * $origWidth);
        }
        if (!$height) {
            $height = (int)(($width / $origWidth) * $origHeight);
        }
        $resizedImage = imagecreatetruecolor($width, $height);
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $width, $height, $transparent);
        }
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
        header('Content-Type: ' . $this->getMimeType($extension));
        header('ETag: "' . $etag . '-' . $width . 'x' . $height . '"');
        header('Cache-Control: public, max-age=2592000');
        header('X-Content-Type-Options: nosniff');
        $this->outputImage($resizedImage, $extension);
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        exit;
    }

    private function loadImage(string $path, string $extension)
    {
        $prev = set_error_handler(function ($severity, $message) {
            return $severity === E_WARNING && str_contains($message, 'iCCP');
        }, E_WARNING);
        try {
            $result = match ($extension) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($path),
                'png' => @imagecreatefrompng($path),
                'gif' => @imagecreatefromgif($path),
                'webp' => @imagecreatefromwebp($path),
                'bmp' => @imagecreatefrombmp($path),
                default => false,
            };
        } finally {
            restore_error_handler();
        }
        return $result;
    }

    private function outputImage($image, string $extension): void
    {
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, null, 90),
            'png' => imagepng($image, null, 9),
            'gif' => imagegif($image),
            'webp' => imagewebp($image, null, 90),
            default => imagepng($image),
        };
    }

    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'map' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'html' => 'text/html',
            'txt' => 'text/plain',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    private function shouldUseChunked(int $fileSize): bool
    {
        return $fileSize > (5 * 1024 * 1024);
    }

    private function readfileChunked(string $filePath, int $chunkSize = 8192): void
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $this->sendError(500, 'Cannot Read File');
            return;
        }
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            flush();
        }
        fclose($handle);
    }

    private function sendError(int $code, string $message, array $debug = []): void
    {
        http_response_code($code);
        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        if ($isDebug) {
            echo json_encode(array_merge([
                'error' => $message,
                'code' => $code,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            ], $debug), JSON_PRETTY_PRINT);
        } else {
            echo $code . ' - ' . $message;
        }
        exit;
    }
}
