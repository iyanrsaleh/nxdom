<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * PackageController - Kelola daftar package akses
 * URL: /{username}/package
 * Package options diambil dari DB, tidak hardcode
 */
class PackageController extends NexaController
{
    private const AJAX_METHODS = ['add', 'edit', 'delete'];

    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        if (in_array($method, self::AJAX_METHODS)) {
            return null;
        }
        return $method !== 'index' ? 'package/' . $method : 'package/index';
    }

    public function index(array $params = []): void
    {
        $packages = $this->getPackagesFromDb();
        $me = $this->Storage('user')->select(['role'])->where('id', $this->session->getUserId())->first();
        $canEdit = ($me['role'] ?? '') === 'admin';

        $this->nexaBlock('package_row', $packages);
        $this->assignVar('package_count', count($packages));
        $this->assignVar('can_edit', $canEdit ? '1' : '');
        $base = rtrim($this->url('/' . $this->getSession()->getUserSlug() . '/package'), '/');
        $this->assignVar('package_add_url', $base . '/add');
        $this->assignVar('package_edit_url', $base . '/edit');
        $this->assignVar('package_delete_url', $base . '/delete');
    }

    public function add(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ensureAdmin();
        try {
            $key = trim($_POST['key'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fas fa-circle');
            if (empty($key) || empty($label)) {
                throw new \Exception('Key dan Label wajib diisi');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $key)) {
                throw new \Exception('Key hanya boleh huruf kecil, angka, underscore');
            }
            $exists = $this->Storage('package')->where('`key`', $key)->first();
            if ($exists) {
                throw new \Exception('Key sudah terdaftar');
            }
            $development = $this->normalizeDevelopment($_POST['development'] ?? null);
            $maxOrder = $this->Storage('package')->select(['MAX(sort_order) AS mx'])->first();
            $sortOrder = (int) ($maxOrder['mx'] ?? 0) + 10;
            $db = \App\System\Storage\NexaDb::getInstance()->getConnection();
            $stmt = $db->prepare('INSERT INTO `package` (`key`, label, icon, sort_order, development) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$key, $label, $icon, $sortOrder, $development]);
            echo json_encode(['success' => true, 'message' => 'Package berhasil ditambahkan']);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getTraceAsString() : null
            ]);
        }
        exit;
    }

    public function edit(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ensureAdmin();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            $development = $this->normalizeDevelopment($_POST['development'] ?? null);
            if ($id < 1 || empty($label)) {
                throw new \Exception('Data tidak valid');
            }
            $this->Storage('package')->where('id', $id)->update([
                'label' => $label,
                'icon' => $icon ?: 'fas fa-circle',
                'development' => $development,
            ]);
            echo json_encode(['success' => true, 'message' => 'Package berhasil diperbarui']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ensureAdmin();
        try {
            $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id < 1) {
                throw new \Exception('ID tidak valid');
            }
            $pkg = $this->Storage('package')->where('id', $id)->first();
            if ($pkg && $pkg['key'] === 'package') {
                throw new \Exception('Package "package" tidak dapat dihapus');
            }
            $this->Storage('package')->where('id', $id)->delete();
            echo json_encode(['success' => true, 'message' => 'Package berhasil dihapus']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function ensureAdmin(): void
    {
        $me = $this->Storage('user')->select(['role'])->where('id', $this->session->getUserId())->first();
        if (($me['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengatur package']);
            exit;
        }
    }

    private function getPackagesFromDb(): array
    {
        $packages = $this->getPackageOptions();
        $withNo = [];
        foreach ($packages as $i => $p) {
            $dev = (int) ($p['development'] ?? 2);
            $label = match ($dev) {
                1 => 'System',
                3 => 'Hidden',
                default => 'Public',
            };
            $withNo[] = array_merge($p, [
                'no' => $i + 1,
                'development' => $dev,
                'development_label' => $label,
            ]);
        }
        return $withNo;
    }

    /** 1 = system, 2 = public, 3 = hidden */
    private function normalizeDevelopment(mixed $value): int
    {
        $v = (int) $value;
        return match ($v) {
            1 => 1,
            3 => 3,
            default => 2,
        };
    }
}
