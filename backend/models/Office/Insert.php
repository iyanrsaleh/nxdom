<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\Models\Office as OfficeFacade;

/**
 * Insert baris **STANDAR** bagi {@see OfficeFacade::setRetInsert()} — langsung ke `tablesRetInsert`.
 *
 * Jalur IMPOR / UNGGAH tidak lagi di sini; atur di facade atau lapisan atas sebelum memanggil `Office::setRetInsert`
 * dengan payload kolom yang sudah siap (tanpa lewat `$fieldConfig` untuk Importing/Upload).
 */
final class Insert
{
    /**
     * @param mixed       $key         Indeks tabel dalam metadata `showTables()`
     * @param mixed       $name        Alias kedua untuk `[key => name]`
     * @param array       $columns     Data kolom siap simpan (setelah preprocessing di luar kelas ini bila ada)
     * @param mixed|null  $fieldConfig Diabaikan sementara — dipreserve untuk signature API facade; IMPOR/UNGGAH akan dipilah di tempat lain
     */
    public static function run(OfficeFacade $office, mixed $key, mixed $name, array $columns = [], mixed $fieldConfig = null): array
    {
        try {
            $key = (int)$key;
            $office->invalidateMutationCache();

            if (RowFormHelpers::hasValidFormData($columns)) {
                return $office->tablesRetInsert([$key => $name], $columns);
            }

            return [
                'success' => true,
                'message' => 'No valid data to insert',
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
