<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\System\NexaController;

/**
 * DistroController — upload paket ZIP hanya ke layout dashboard (templates/dashboard/).
 *
 * @package App\Controllers\Admin
 */
class DistroController extends NexaController
{
    private const ALLOWED_LAYOUTS = ['dashboard'];
    private const AJAX_METHODS = ['uploadZip', 'uploadFile'];

    /**
     * @param string $method Requested method from URL
     * @param string $requestMethod HTTP method (GET/POST)
     * @return string|null Template path, or null for AJAX endpoints (no render)
     */
    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        if (in_array($method, self::AJAX_METHODS)) {
            return null;
        }
        if ($requestMethod === 'POST') {
            return 'distro/index';
        }
        if (!empty($method) && $method !== 'index') {
            return 'distro/' . $method;
        }
        return 'distro/index';
    }

    private const ALLOWED_EXTENSIONS = ['html', 'htm'];
    private const MAX_SINGLE_FILE = 5 * 1024 * 1024;
    private const MAX_ZIP_SIZE = 20 * 1024 * 1024;

    public function index(array $params = []): void
    {
        $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
        $this->setJsController(array_merge($jsData, [
            'max_zip_mb' => self::MAX_ZIP_SIZE / 1024 / 1024,
        ]));
        $this->setData([
            'max_zip_mb' => self::MAX_ZIP_SIZE / 1024 / 1024,
        ]);
        $this->assignVars([
            'max_zip_mb' => self::MAX_ZIP_SIZE / 1024 / 1024,
        ]);
    }

    /**
     * Upload single file .html (/{username}/distro/uploadFile)
     */
    public function uploadFile(array $params = []): void
    {
        if (!$this->session || !$this->session->getUserId()) {
            $this->jsonResponse(['success' => false, 'message' => 'Login diperlukan', 'error' => 'UNAUTHORIZED']);
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed', 'error' => 'METHOD_NOT_ALLOWED']);
            }

            $layout = $this->sanitizeLayout($_POST['layout'] ?? '');
            $relativePath = $this->sanitizePath($_POST['path'] ?? '');

            if (!$layout || !$relativePath) {
                $this->jsonResponse(['success' => false, 'message' => 'Layout dan path wajib diisi', 'error' => 'MISSING_PARAMS']);
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $this->jsonResponse(['success' => false, 'message' => $this->getUploadError($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE), 'error' => 'UPLOAD_ERROR']);
            }

            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
                $this->jsonResponse(['success' => false, 'message' => 'Hanya .html/.htm yang diizinkan', 'error' => 'INVALID_EXTENSION']);
            }

            if ($file['size'] > self::MAX_SINGLE_FILE) {
                $this->jsonResponse(['success' => false, 'message' => 'File maksimal ' . (self::MAX_SINGLE_FILE / 1024 / 1024) . 'MB', 'error' => 'FILE_TOO_LARGE']);
            }

            $filePath = $layout . '/' . $relativePath;
            if (!str_ends_with($filePath, '.html') && !str_ends_with($filePath, '.htm')) {
                $filePath .= ($ext === 'htm' ? '.htm' : '.html');
            }

            if (!$this->isPathAllowed($filePath)) {
                $this->jsonResponse(['success' => false, 'message' => 'Layout tidak valid', 'error' => 'INVALID_LAYOUT']);
            }

            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                $this->jsonResponse(['success' => false, 'message' => 'Gagal membaca file', 'error' => 'READ_ERROR']);
            }

            $nexaSystem = $this->getNexaSystem();
            $exists = $nexaSystem->htmlFileExistsWithPath($filePath);

            if ($exists['exists']) {
                $nexaSystem->editHtmlFileWithPath($filePath, $content);
                $action = 'updated';
            } else {
                $nexaSystem->addHtmlFileWithPath($filePath, $content);
                $action = 'created';
            }

            $this->jsonResponse(['success' => true, 'message' => 'File berhasil di-' . $action, 'action' => $action, 'path' => $filePath]);
        } catch (\Exception $e) {
            error_log('DistroController uploadFile: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage(), 'error' => 'EXCEPTION']);
        }
    }

    /**
     * Upload & ekstrak ZIP (/{username}/distro/uploadZip)
     */
    public function uploadZip(array $params = []): void
    {
        if (!$this->session || !$this->session->getUserId()) {
            $this->jsonResponse(['success' => false, 'message' => 'Login diperlukan', 'error' => 'UNAUTHORIZED']);
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Method not allowed', 'error' => 'METHOD_NOT_ALLOWED']);
            }

            $layout = $this->sanitizeLayout($_POST['layout'] ?? 'dashboard');

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $this->jsonResponse(['success' => false, 'message' => $this->getUploadError($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE), 'error' => 'UPLOAD_ERROR']);
            }

            $file = $_FILES['file'];
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
                $this->jsonResponse(['success' => false, 'message' => 'File harus .zip', 'error' => 'INVALID_EXTENSION']);
            }

            if ($file['size'] > self::MAX_ZIP_SIZE) {
                $this->jsonResponse(['success' => false, 'message' => 'ZIP maksimal ' . (self::MAX_ZIP_SIZE / 1024 / 1024) . 'MB', 'error' => 'FILE_TOO_LARGE']);
            }

            if (!class_exists('ZipArchive')) {
                $this->jsonResponse(['success' => false, 'message' => 'ZipArchive tidak tersedia', 'error' => 'ZIP_NOT_AVAILABLE']);
            }

            $zip = new \ZipArchive();
            if ($zip->open($file['tmp_name'], \ZipArchive::RDONLY) !== true) {
                $this->jsonResponse(['success' => false, 'message' => 'File ZIP tidak valid', 'error' => 'ZIP_INVALID']);
            }

            $templateDir = dirname(__DIR__, 2) . '/templates/';
            $targetBase = $templateDir . $layout . '/';
            $baseReal = realpath($templateDir) ?: $templateDir;
            $allowedInZip = ['html', 'htm', 'css', 'js'];
            $extracted = 0;
            $errors = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false || str_ends_with($entry, '/')) {
                    continue;
                }

                $entryPath = $this->normalizeZipPath($entry);
                $ext = strtolower(pathinfo($entryPath, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedInZip)) {
                    continue;
                }

                $targetPath = $targetBase . $entryPath;
                $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);
                $targetDir = dirname($targetPath);

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetDirReal = realpath($targetDir);
                if ($targetDirReal === false || strpos($targetDirReal, $baseReal) !== 0) {
                    $errors[] = 'Path traversal: ' . $entry;
                    continue;
                }

                $content = $zip->getFromIndex($i);
                if ($content !== false && file_put_contents($targetPath, $content) !== false) {
                    $extracted++;
                }
            }
            $zip->close();

            $this->jsonResponse([
                'success' => true,
                'message' => $extracted . ' file diekstrak ke ' . $layout,
                'extracted_count' => $extracted,
                'layout' => $layout,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            error_log('DistroController uploadZip: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage(), 'error' => 'EXCEPTION']);
        }
    }

    public function Fetch(): mixed
    {
        return $this->NexaRender();
    }

    public function FetchEvents(array $params = []): void
    {
        $this->eventsAccess($params);
    }

    public function FetchControllers(): mixed
    {
        return $this->eventsControllers();
    }

    public function FetchModels(): mixed
    {
        return $this->eventsModel();
    }

    private function jsonResponse(array $result): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    private function sanitizeLayout(string $layout): string
    {
        $layout = trim(strtolower($layout));
        return in_array($layout, self::ALLOWED_LAYOUTS) ? $layout : '';
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace(['..', "\0", '\\'], ['', '', '/'], $path);
        $path = trim($path, '/');
        return preg_replace('#/+#', '/', $path) ?: '';
    }

    private function normalizeZipPath(string $entry): string
    {
        $path = str_replace('\\', '/', $entry);
        $path = preg_replace('#/+#', '/', $path);
        $path = str_replace(['..', "\0"], '', $path);
        return trim($path, '/');
    }

    private function isPathAllowed(string $path): bool
    {
        $first = explode('/', $path)[0] ?? '';
        return in_array(strtolower($first), self::ALLOWED_LAYOUTS);
    }

    private function getUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File melebihi upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'Upload tidak lengkap',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file',
            UPLOAD_ERR_NO_TMP_DIR => 'Tmp dir tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan ekstensi',
            default => 'Kesalahan upload',
        };
    }
}
