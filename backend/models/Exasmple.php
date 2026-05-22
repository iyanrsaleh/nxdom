<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * Contoh model: pola Storage / query builder NexaModel.
 * Tabel default tidak di-set lewat properti di kelas anak; gunakan
 * $this->Storage('nama_tabel') atau $this->table('nama_tabel') (alias sama).
 *
 * @see models/NexaModel.md — Query Builder, CRUD, Storage()
 */
class Exasmple extends NexaModel
{
    /**
     * Contoh tanpa query DB (untuk demo useModels + nexaBlock).
     */
    public function data(string $search): ?array
    {
        return [
            'name' => 'John Doe',
            'email' => $search,
        ];
    }

    /**
     * Contoh membaca satu baris dari tabel `news`.
     * Pastikan tabel & kolom `id` ada (migrasi / SQL), atau sesuaikan nama kolom.
     */
    public function newsById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->Storage('news')->where('id', '=', $id)->first();
    }

    /**
     * Contoh: beberapa baris terbaru (urutkan kolom yang ada di skema Anda).
     */
    public function latestNews(int $limit = 5): array
    {
        return $this->Storage('news')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();
    }
}
