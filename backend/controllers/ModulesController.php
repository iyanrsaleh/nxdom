<?php
declare(strict_types=1);

namespace App\Controllers;

use App\System\NexaController;

/**
 * ModulesController - File Serving Controller untuk assets/modules/
 *
 * Melayani file publik dari assets/modules/ (JS libraries, CSS frameworks, dll)
 * URL bersih: /modules/{path} → assets/modules/{path}
 * 
 * Contoh:
 * - /modules/Nexa.js → assets/modules/Nexa.js
 * - /modules/Select2/select2.min.js → assets/modules/Select2/select2.min.js
 * - /modules/Modal/modal.css → assets/modules/Modal/modal.css
 *
 * @package   NexaUI
 * @version   2.0.0
 */
class ModulesController extends NexaController
{
    private string $modulesBasePath;
    private string $projectRoot;

    private array $allowedExtensions = [
        'css', 'js', 'json', 'map',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'html', 'txt', 'md',
    ];

    /** @var array<string, int> lookup O(1) untuk ekstensi */
    private array $allowedExtSet = [];

    /** realpath(base modules) sekali — hindari realpath berulang per request */
    private string $modulesRealBase = '';

    public function __construct($template = null, $deviceLayouts = [])
    {
        // Tidak memanggil parent::__construct — pengiriman statik tidak butuh NexaDom / init() berat
        // (NexaMapping, NexaAuth, NexaFirebase, …). Router tetap mengirim $template; diabaikan di sini.
        $this->projectRoot = dirname(__DIR__);
        $modulesPath = realpath($this->projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'modules');
        $this->modulesBasePath = ($modulesPath ?: $this->projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'modules') . DIRECTORY_SEPARATOR;
        $this->modulesRealBase = $modulesPath !== false ? $modulesPath : (string) realpath(rtrim($this->modulesBasePath, '/\\'));
        $this->allowedExtSet = array_flip($this->allowedExtensions);
    }

    /**
     * Serve file dari assets/modules/
     *
     * URL Pattern: /modules/{path}
     */
    public function index($params = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Extract path after /modules/
        if (!preg_match('#/modules/(.+)$#', $requestUri, $m)) {
            $this->sendError(404, 'Invalid modules path');
            return;
        }

        $path = $m[1];
        $path = strtok($path, '?');
        $path = urldecode($path);

        if (empty($path)) {
            $this->sendError(404, 'Module path required');
            return;
        }

        // Normalize path untuk Windows/Linux
        $pathNormalized = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $filePath = $this->modulesBasePath . $pathNormalized;

        // Security: Directory traversal prevention
        $realPath = realpath($filePath);
        $realBase = $this->modulesRealBase !== '' ? $this->modulesRealBase : (string) realpath($this->modulesBasePath);
        if (!$realPath || $realBase === '' || !str_starts_with($realPath, $realBase)) {
            $this->sendError(404, 'File Not Found');
            return;
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

        if (!isset($this->allowedExtSet[$extension])) {
            $this->sendError(403, 'File Type Not Allowed');
            return;
        }

        // realpath yang valid untuk file = file ada; hindari stat tambahan

        // ETag dari mtime+size — md5_file() membaca seluruh file dan memperlambat asset besar
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

    /** ETag cepat: tanpa membaca isi file (cukup untuk cache busting statik) */
    private function buildFileEtag(string $realPath): string
    {
        $mtime = @filemtime($realPath) ?: 0;
        $size = @filesize($realPath) ?: 0;

        return sprintf('%x-%x', $mtime, $size);
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
            'md' => 'text/markdown',
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
