<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * UserController - Admin Controller
 * URL: /{username}/user
 * Template: templates/dashboard/user/index.html
 *
 * Fitur: Daftar user dengan pagination (mengikuti pola PostinganController)
 */
class UserController extends NexaController
{
    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        if ($method === 'updatePackage') {
            return null;
        }
        if ($method === 'pages' || $method === 'index') {
            return 'user/index';
        }
        return 'user/' . $method;
    }

    /**
     * Index Method - Menampilkan listing user dengan pagination
     */
    public function index(array $params = []): void
    {
        $page = $this->pagesIntRequest();
        $requestParams = $this->paramsKeys();
        $search = trim((string) $requestParams['search']);

        $searchResults = $this->useModels('User', 'list', [$page, $search]);

        $users = $searchResults['data'] ?? [];
        $currentPage = (int) ($searchResults['current_page'] ?? 1);
        $perPage = (int) ($searchResults['per_page'] ?? 5);
        $offset = ($currentPage - 1) * $perPage;

        $usersWithNo = [];
        foreach ($users as $i => $u) {
            $usersWithNo[] = array_merge($u, ['no' => $offset + $i + 1]);
        }

        $me = $this->Storage('user')->select(['role'])->where('id', $this->session->getUserId())->first();
        $canEditPackage = ($me['role'] ?? '') === 'admin';

        $this->setData([
            'user_count'        => $searchResults['total'] ?? 0,
            'can_edit_package'   => $canEditPackage ? '1' : '',
            'user_update_url'    => $this->url('/' . $this->getSession()->getUserSlug() . '/user/updatePackage'),
            'page_index'         => $params['page_index'] ?? '',
            'search_keyword'     => htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
        ]);

        $this->assignBlocks([
            'user_row' => $usersWithNo,
            'pkg_opt'  => array_map(function ($p) {
                return ['key' => $p['key'], 'label' => $p['label']];
            }, $this->getPackageOptionsVisible()),
        ]);

        $pageIndex = rtrim($params['page_index'] ?? '', '/');
        if ($search !== '') {
            $baseUrl = $pageIndex . '?search=' . rawurlencode($search) . '&pages/';
        } else {
            $baseUrl = $pageIndex . '/pages/';
        }
        $paginationHTML = $this->NexaPagination()->render(
            $searchResults['current_page'],
            $searchResults['last_page'],
            $baseUrl
        );
        $this->assignVar('pagination', $paginationHTML);
        $this->assignVars([
            'current_page'   => $searchResults['current_page'],
            'last_page'      => $searchResults['last_page'],
            'total_records'  => $searchResults['total'],
            'base_url'       => $baseUrl,
        ]);
    }

    /**
     * Update package user (AJAX)
     */
    public function updatePackage(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method tidak diizinkan');
            }

            $currentUser = $this->Storage('user')
                ->select(['role'])
                ->where('id', $this->session->getUserId())
                ->first();
            if (($currentUser['role'] ?? '') !== 'admin') {
                throw new \Exception('Hanya admin yang dapat mengatur package');
            }

            $userId = (int) ($_POST['user_id'] ?? 0);
            $packageInput = $_POST['package'] ?? '';

            if ($userId < 1) {
                throw new \Exception('User ID tidak valid');
            }

            $selected = array_filter(array_map('trim', explode(',', $packageInput)));
            $allowed = array_column($this->getPackageOptionsVisible(), 'key');
            $package = implode(',', array_intersect($selected, $allowed));

            $result = $this->Storage('user')
                ->where('id', $userId)
                ->update(['package' => $package ?: null]);

            if (!$result) {
                throw new \Exception('Gagal memperbarui package');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Package berhasil diperbarui',
                'package' => $package ?: null,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }

   




    
}
