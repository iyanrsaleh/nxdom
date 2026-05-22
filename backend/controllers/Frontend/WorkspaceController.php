<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;

/**
 * WorkspaceController - Frontend Controller
 * URL: /workspace         → index()
 * URL: /workspace/store   → store()      — render halaman store (data dari GitHub)
 * URL: /store/{params}    → storeServe() — serve file binary dari D:\Framework\Store\
 */
class WorkspaceController extends NexaController
{
    // ─── Path repositori Store (di luar web root) ─────────────────────
    private string $storeBasePath = 'D:\\Framework\\Store\\';

    private array $storeAllowedExt = [
        'exe', 'msi', 'dmg', 'deb', 'rpm', 'tar', 'gz', 'zip',
        'apk', 'ipa', 'pdf', 'txt', 'appimage',
    ];

    // Urutan kategori tampilan
    private array $storeCategories = [
        'development',
        'product',
    ];

    private array $storeCategoryLabels = [
        'development' => 'Development',
        'desktop'     => 'Desktop',
        'mobile'      => 'Mobile',
        'web'         => 'Web',
        'template'    => 'Template',
        'product'      => 'product',
    ];

    // ── Workspace index ───────────────────────────────────────────────
    public function index(array $params = []): void
    {
        $this->assignVars(['page_title' => 'Workspace', 'base_url' => $this->getBaseUrl()]);
    }

    // ── Store: render halaman /workspace/store ────────────────────────
    // Data diambil dari GitHub server-side → assignVars → NexaDom blocks
    public function store(array $params = []): void
    {
        $items = $this->fetchStoreData();

        // Kelompokkan per category
        $grouped = [];
        foreach ($items as $item) {
            $cat = $item['category'] ?? 'other';
            $grouped[$cat][] = $item;
        }

        // Buat satu block per category: store_{category}_items
        foreach ($this->storeCategories as $cat) {
            $blockName = 'store_' . $cat . '_items';
            $catItems  = $grouped[$cat] ?? [];
            foreach ($catItems as $item) {
                $this->nexaBlock($blockName, [
                    'store_id'          => $item['id'] ?? '',
                    'store_name'        => $item['name'] ?? '',
                    'store_version'     => $item['version'] ?? '',
                    'store_platform'    => ucfirst($item['platform'] ?? ''),
                    'store_icon'        => $item['icon'] ?? 'fa-solid fa-file-arrow-down',
                    'store_description' => $item['description'] ?? '',
                    'store_size'        => $item['size'] ?? '',
                    'store_url'         => '/store/' . ($item['category'] ?? $cat) . '/' . ($item['subcategory'] ?? '') . '/' . ($item['file'] ?? ''),
                ]);
            }
        }

        $this->assignVars(['page_title' => 'Nexa Store']);
    }

    // ── storeServe: serve file binary — dipanggil route /store/{params} ─
    public function storeServe($params = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        $path = preg_replace('#^/store/#', '', strtok($requestUri, '?') ?: '');
        $path = urldecode($path);

        if (str_contains($path, '..') || str_contains($path, "\0")) {
            $this->storeError(400, 'Bad Request');
            return;
        }

        $base     = realpath($this->storeBasePath);
        $filePath = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $realPath = realpath($filePath);

        if (!$realPath || !$base || !str_starts_with($realPath, $base)) {
            $this->storeError(404, 'File Not Found');
            return;
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->storeAllowedExt, true)) {
            $this->storeError(403, 'Forbidden');
            return;
        }

        $this->storeServeFile($realPath, $ext);
    }

    // ─── Private helpers ─────────────────────────────────────────────

    private string $storeDataUrl = 'https://raw.githubusercontent.com/iyanrsaleh/store/main/data.json';

    /**
     * Fetch data.json dari GitHub.
     */
    private function fetchStoreData(): array
    {
        $ctx = stream_context_create(['http' => [
            'timeout'       => 5,
            'ignore_errors' => true,
            'user_agent'    => 'NexaStore/1.0',
        ]]);

        $content = @file_get_contents($this->storeDataUrl, false, $ctx);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        return $decoded['store'] ?? [];
    }

    private function storeServeFile(string $filePath, string $ext): void
    {
        $mimeTypes = [
            'json' => 'application/json',
            'exe'  => 'application/octet-stream',
            'msi'  => 'application/octet-stream',
            'dmg'  => 'application/octet-stream',
            'deb'  => 'application/octet-stream',
            'rpm'  => 'application/octet-stream',
            'tar'  => 'application/x-tar',
            'gz'   => 'application/gzip',
            'zip'  => 'application/zip',
            'apk'  => 'application/vnd.android.package-archive',
            'ipa'      => 'application/octet-stream',
            'appimage' => 'application/octet-stream',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
        ];

        $mime     = $mimeTypes[$ext] ?? 'application/octet-stream';
        $filename = basename($filePath);

        $etag = '"' . md5_file($filePath) . '"';
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=3600');

        $downloadExts = ['exe', 'msi', 'dmg', 'deb', 'rpm', 'apk', 'ipa', 'zip', 'tar', 'gz', 'appimage'];
        if (in_array($ext, $downloadExts, true)) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline');
        }

        readfile($filePath);
        exit;
    }

    private function storeError(int $code, string $message): void
    {
        http_response_code($code);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            echo $message;
        }
        exit;
    }
}
