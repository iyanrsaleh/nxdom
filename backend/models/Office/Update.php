<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\Models\Office as OfficeFacade;

/**
 * Update baris **STANDAR** bagi {@see OfficeFacade::setRetUpdate()} — langsung ke `tablesRetUpdate`.
 *
 * IMPOR / UNGGAH dipindah keluar kelas ini; panggilan ke `Office::setRetUpdate` diasumsikan sudah dapat kolom akhir yang valid.
 *
 * @return array|bool `tablesRetUpdate` mengembalikan bool; `hasValidFormData` false bisa mengembalikan array pesan ringan
 */
final class Update
{
    /**
     * @param mixed       $key         Indeks tabel metadata
     * @param mixed       $name        Alias kedua untuk `[key => name]`
     * @param mixed       $columns     Payload update (field `id` dihapus bila digunakan untuk `$resolvedIdFinal`)
     * @param mixed|null  $id          PK eksplisit; alternatif: `columns.id` sebelum merge
     * @param mixed|null  $fieldConfig Diabaikan; IMPOR/UNGGAH akan di lapisan atas
     */
    public static function run(OfficeFacade $office, mixed $key, mixed $name, mixed $columns = [], mixed $id = null, mixed $fieldConfig = null): mixed
    {
        try {
            $columns = \is_array($columns) ? $columns : [];

            $resolvedId = null;
            if ($id !== null && (int)$id > 0) {
                $resolvedId = (int)$id;
            } elseif (isset($columns['id']) && (int)$columns['id'] > 0) {
                $resolvedId = (int)$columns['id'];
            }

            unset($columns['id']);
            $resolvedIdFinal = (int)($resolvedId ?? 0);
            $key = (int)$key;

            $office->invalidateMutationCache();

            if (RowFormHelpers::hasValidFormData($columns)) {
                return $office->tablesRetUpdate([$key => $name], $columns, $resolvedIdFinal);
            }

            return [
                'success' => true,
                'message' => 'No valid data to update',
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
