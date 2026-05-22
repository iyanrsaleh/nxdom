<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\Models\Office as OfficeFacade;

/**
 * Hapus satu baris **STANDAR** bagi {@see OfficeFacade::setRettDelete()} — ke `tablesRetDelete` dan invalidasi cache mutasi.
 *
 * Jalur lanjutan (hapus banyak, soft delete, cascade, dll.) dapat ditambahkan di kelas ini tanpa membesarkan facade `Office`.
 */
final class Delete
{
    /**
     * @param mixed $key  Indeks tabel dalam metadata (`showTables`)
     * @param mixed $name Pasangan kedua untuk `[key => name]`
     * @param mixed $id    PK baris yang dihapus
     */
    public static function run(OfficeFacade $office, mixed $key, mixed $name, mixed $id): bool
    {
        try {
            $id = (int)$id;
            $key = (int)$key;

            $office->invalidateMutationCache();

            return $office->tablesRetDelete([$key => $name], $id);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
