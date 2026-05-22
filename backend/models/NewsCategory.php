<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * Kategori artikel (tabel news_category).
 */
class NewsCategory extends NexaModel
{
    protected $table = 'news_category';

    /**
     * Opsi untuk select: [['label' => ..., 'value' => slug], ...]
     */
    public function forSelect(): array
    {
        try {
            $rows = $this->Storage($this->table)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows ?? [] as $r) {
            if (empty($r['slug'])) {
                continue;
            }
            $out[] = [
                'label' => $r['label'] ?? $r['slug'],
                'value' => $r['slug'],
            ];
        }
        return $out;
    }

    /**
     * Baris penuh untuk halaman daftar kategori.
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        try {
            return $this->Storage($this->table)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get() ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Tambah baris kategori. Slug harus unik.
     *
     * @param array{slug?: string, label?: string, icon?: string, sort_order?: int} $data
     * @return array{success: bool, message?: string}
     */
    public function create(array $data): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $slug));
        $slug = trim($slug, '-');

        $labelLen = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
        if ($labelLen < 2) {
            return ['success' => false, 'message' => 'Label minimal 2 karakter.'];
        }
        if (strlen($slug) < 2) {
            return ['success' => false, 'message' => 'Slug minimal 2 karakter (huruf/angka, pisah dengan tanda hubung).'];
        }

        try {
            $exists = $this->Storage($this->table)->where('slug', $slug)->first();
            if (!empty($exists)) {
                return ['success' => false, 'message' => 'Slug sudah dipakai, pilih slug lain.'];
            }
            $icon = $this->normalizeIconClass((string) ($data['icon'] ?? ''));

            $this->Storage($this->table)->insert([
                'slug'       => $slug,
                'label'      => $label,
                'icon'       => $icon,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()];
        }
    }

    public function deleteById(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        try {
            $this->Storage($this->table)->where('id', $id)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizeIconClass(string $icon): string
    {
        $icon = trim(preg_replace('/[^a-z0-9\-\s]/i', '', $icon));
        $icon = preg_replace('/\s+/', ' ', $icon);
        return $icon !== '' ? $icon : 'fas fa-folder';
    }
}
