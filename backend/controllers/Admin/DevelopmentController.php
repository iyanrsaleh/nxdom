<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * DevelopmentController — ringkasan semua package (baca saja).
 * URL: /{username}/development
 * Template: templates/dashboard/development/index.html
 */
class DevelopmentController extends NexaController
{
    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        if (!empty($method) && $method !== 'index') {
            return 'development/' . $method;
        }
        return 'development/index';
    }

    public function index(array $params = []): void
    {
        $slug = $this->getSession()->getUserSlug();
        $packages = $this->getPackageOptionsVisible();
        $rows = [];
        $no = 0;
        foreach ($packages as $p) {
            $key = (string) ($p['key'] ?? '');
            // Jangan tampilkan kartu "development" di halaman ini (sudah di /development)
            if ($key === 'development') {
                continue;
            }
            $no++;
            $dev = (int) ($p['development'] ?? 2);
            $path = trim((string) ($p['url'] ?? ''));
            $rel = $path !== '' ? ltrim($path, '/') : $key;
            $moduleUrl = rtrim($this->url('/' . $slug . '/' . $rel), '/');
            $rows[] = array_merge($p, [
                'no' => $no,
                'development' => $dev,
                'development_label' => $dev === 1 ? 'System' : 'Public',
                'module_url' => $moduleUrl,
            ]);
        }

        $this->nexaBlock('dev_package_row', $rows);
        $this->assignVar('package_count', count($rows));
        $this->assignVar(
            'package_manage_url',
            rtrim($this->url('/' . $this->getSession()->getUserSlug() . '/package'), '/')
        );
        $this->assignVar('page_title', 'Development — Semua Package');
    }
}
