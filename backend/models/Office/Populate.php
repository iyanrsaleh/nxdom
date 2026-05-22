<?php
declare(strict_types=1);

namespace App\Models\Office;
use App\System\NexaModel;
use Phpfastcache\Helper\Psr16Adapter;

/**
 * Insert class untuk operasi insert data dengan dukungan file upload
 */
class Populate extends NexaModel
{
    private static function isMutationApplied(mixed $result): bool
    {
        if ($result === false || $result === null) {
            return false;
        }
        if (is_bool($result)) {
            return $result;
        }
        if (is_int($result) || is_float($result)) {
            return $result > 0;
        }
        if (is_string($result) && is_numeric($result)) {
            return ((float)$result) > 0;
        }
        // Untuk driver yang return object/array truthy.
        return true;
    }

    private static function invalidateQueryCache(): void
    {
        try {
            $cachePath = sys_get_temp_dir() . '/nexacache';
            $cacheConfig = new \Phpfastcache\Drivers\Files\Config([
                'path' => $cachePath,
            ]);
            (new Psr16Adapter('files', $cacheConfig))->clear();
            // Fallback hard-clean untuk menghindari stale cache file pada beberapa environment.
            self::purgeCacheDirectory($cachePath);
        } catch (\Throwable $e) {
            // jangan blok operasi populate bila cache gagal dibersihkan
        }
    }

    private static function purgeCacheDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }
        try {
            $items = @scandir($path);
            if (!is_array($items)) {
                return;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $full = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
                if (is_dir($full)) {
                    self::purgeCacheDirectory($full);
                    @rmdir($full);
                } elseif (is_file($full)) {
                    @unlink($full);
                }
            }
        } catch (\Throwable $_) {
            // ignore
        }
    }

    /**
     * Build arithmetic formula for field calculations
     * Supports field-to-field operations, field-to-value operations, and complex nested formulas
     */
    public function build(array $params): array {
        try {
            $status = $params['status'] ?? 'insert';
            $targets = $params['targets'] ?? [];
            $index = $params['index'] ?? null;
            $allowBulkByUserid = (bool)($params['allowBulkByUserid'] ?? false);

            if (!is_array($targets)) {
                return [
                    'success' => false,
                    'message' => 'Invalid targets payload',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }

            if (!in_array($status, ['insert', 'update', 'delete'], true)) {
                return [
                    'success' => false,
                    'message' => 'Unsupported status, expected insert|update|delete',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }

            // Index hanya dipakai sebagai konteks pencarian/mapping, bukan target mutasi update/delete.
            // Mutasi populate dijalankan pada target percabangan saja.
            $workItems = [];
            if ($status === 'insert' && is_array($index)) {
                $idxTabel = $index['tabel'] ?? null;
                $idxValue = $index['value'] ?? null;
                if ($idxTabel !== null && is_array($idxValue) && $idxValue !== []) {
                    $workItems[] = [
                        'tabel' => $idxTabel,
                        'value' => $idxValue,
                        '__source' => 'index',
                    ];
                }
            }
            foreach ($targets as $t) {
                $workItems[] = $t;
            }

            $results = [];
            $didMutate = false;

            foreach ($workItems as $target) {
                $tableId = (int)($target['tabel'] ?? 0);
                $value = $target['value'] ?? [];

                if (!$tableId || !is_array($value) || empty($value)) {
                    $results[] = [
                        'success' => false,
                        'tabel' => $target['tabel'] ?? null,
                        'message' => 'Skipped: invalid tabel/value payload',
                        'status' => $status,
                    ];
                    continue;
                }

                $tableName = $this->tablesIndex($tableId);
                if (!$tableName) {
                    $results[] = [
                        'success' => false,
                        'tabel' => $tableId,
                        'message' => 'Table not found by tabel id',
                        'status' => $status,
                    ];
                    continue;
                }

                $operationResult = false;
                $message = null;

                if ($status === 'insert') {
                    $rawInsert = $this->Storage($tableName)->insert($value);
                    $operationResult = self::isMutationApplied($rawInsert);
                }

                if ($status === 'update') {
                    $relation = $this->resolveRelationCondition($value);
                    if ($relation === null) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => 'Relation key not found or ambiguous in target value',
                            'value' => $value,
                        ];
                        continue;
                    }
                    [$relationKey, $relationValue] = $relation;
                    $existing = $this->Storage($tableName)
                        ->where($relationKey, $relationValue)
                        ->first();
                    if ((!is_array($existing) || $existing === []) && is_string($relationKey) && str_ends_with($relationKey, '_id')) {
                        // Fallback update: row relasi lama belum bergeser (contoh petani_id masih 13, nilai baru 14).
                        // Coba cari baris existing berdasarkan identitas record yang stabil.
                        $fallbackQuery = $this->Storage($tableName);
                        $hasFallbackFilter = false;
                        if (array_key_exists('userid', $value) && $value['userid'] !== null && $value['userid'] !== '') {
                            $fallbackQuery->where('userid', $value['userid']);
                            $hasFallbackFilter = true;
                        }
                        if (array_key_exists('nama', $value) && $value['nama'] !== null && trim((string)$value['nama']) !== '') {
                            $fallbackQuery->where('nama', $value['nama']);
                            $hasFallbackFilter = true;
                        }
                        if ($hasFallbackFilter) {
                            $fallbackExisting = $fallbackQuery->first();
                            if (is_array($fallbackExisting) && $fallbackExisting !== [] && array_key_exists($relationKey, $fallbackExisting)) {
                                $existing = $fallbackExisting;
                                $relationValue = $fallbackExisting[$relationKey];
                            }
                        }
                    }
                    if (!is_array($existing) || $existing === []) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => "No matching row for {$relationKey}={$relationValue}",
                            'value' => $value,
                        ];
                        continue;
                    }
                    if ($relationKey === 'userid' && !$allowBulkByUserid) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => 'Blocked unsafe update: relation `userid` terlalu umum. Sertakan id/ptmid/*_id atau set allowBulkByUserid=true.',
                            'value' => $value,
                        ];
                        continue;
                    }
                    $updateValue = $value;
                    // field helper internal populate, bukan kolom tabel fisik
                    unset($updateValue['source_userid']);
                    unset($updateValue['__relationKey'], $updateValue['__relationValue']);
                    // Kunci relasi dipakai untuk WHERE saja, jangan ikut diset ulang pada payload update.
                    if (is_string($relationKey) && $relationKey !== '') {
                        unset($updateValue[$relationKey]);
                    }
                    // Guard tambahan: id primary key tidak boleh ikut diset ulang.
                    unset($updateValue['id']);

                    $rawUpdate = $this->Storage($tableName)
                        ->where($relationKey, $relationValue)
                        ->update($updateValue);
                    $operationResult = self::isMutationApplied($rawUpdate);

                    if (!$operationResult && $message === null) {
                        $message = 'No rows updated';
                    }
                }

                if ($status === 'delete') {
                    $relation = $this->resolveRelationCondition($value);
                    if ($relation === null) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => 'Relation key not found or ambiguous in target value',
                            'value' => $value,
                        ];
                        continue;
                    }
                    [$relationKey, $relationValue] = $relation;
                    $existing = $this->Storage($tableName)
                        ->where($relationKey, $relationValue)
                        ->first();
                    if ((!is_array($existing) || $existing === []) && is_string($relationKey) && str_ends_with($relationKey, '_id')) {
                        // Fallback delete: relasi lama belum bergeser (mirip update).
                        $fallbackQuery = $this->Storage($tableName);
                        $hasFallbackFilter = false;
                        if (array_key_exists('userid', $value) && $value['userid'] !== null && $value['userid'] !== '') {
                            $fallbackQuery->where('userid', $value['userid']);
                            $hasFallbackFilter = true;
                        }
                        if (array_key_exists('nama', $value) && $value['nama'] !== null && trim((string)$value['nama']) !== '') {
                            $fallbackQuery->where('nama', $value['nama']);
                            $hasFallbackFilter = true;
                        }
                        if ($hasFallbackFilter) {
                            $fallbackExisting = $fallbackQuery->first();
                            if (is_array($fallbackExisting) && $fallbackExisting !== [] && array_key_exists($relationKey, $fallbackExisting)) {
                                $existing = $fallbackExisting;
                                $relationValue = $fallbackExisting[$relationKey];
                            }
                        }
                    }
                    if (!is_array($existing) || $existing === []) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => "No matching row for {$relationKey}={$relationValue}",
                            'value' => $value,
                        ];
                        continue;
                    }
                    if ($relationKey === 'userid' && !$allowBulkByUserid) {
                        $results[] = [
                            'success' => false,
                            'tabel' => $tableId,
                            'table_name' => $tableName,
                            'status' => $status,
                            'message' => 'Blocked unsafe delete: relation `userid` terlalu umum. Sertakan id/ptmid/*_id atau set allowBulkByUserid=true.',
                            'value' => $value,
                        ];
                        continue;
                    }

                    $rawDelete = $this->Storage($tableName)
                        ->where($relationKey, $relationValue)
                        ->delete();
                    $operationResult = self::isMutationApplied($rawDelete);

                    if (!$operationResult) {
                        $message = 'No rows deleted';
                    }
                }

                if ($operationResult) {
                    $didMutate = true;
                }

                $results[] = [
                    'success' => $operationResult,
                    'tabel' => $tableId,
                    'table_name' => $tableName,
                    'status' => $status,
                    'message' => $message,
                    'matched_id' => isset($existing['id']) ? $existing['id'] : null,
                    'value' => $value,
                ];
            }

            if ($didMutate) {
                self::invalidateQueryCache();
            }

            return [
                'success' => true,
                'response' => [
                    'index' => $params['index'] ?? null,
                    'targets' => $results,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Tentukan kondisi where untuk update/delete populate.
     *
     * Prioritas aman:
     * - id (paling spesifik)
     * - ptmid
     * - *_id lain
     * - userid dari source_userid (khusus flow relasi userid)
     * - userid langsung (fallback terakhir)
     */
    private function resolveRelationCondition(array $value): ?array
    {
        if (array_key_exists('__relationKey', $value)) {
            $rk = trim((string)$value['__relationKey']);
            if ($rk !== '' && array_key_exists($rk, $value)) {
                return [$rk, $value[$rk]];
            }
            if ($rk !== '' && array_key_exists('__relationValue', $value)) {
                return [$rk, $value['__relationValue']];
            }
        }

        if (array_key_exists('id', $value)) {
            return ['id', $value['id']];
        }

        if (array_key_exists('ptmid', $value)) {
            return ['ptmid', $value['ptmid']];
        }

        // Banyak konfigurasi populate percabangan memakai slug sebagai FK logis.
        if (array_key_exists('slug', $value)) {
            return ['slug', $value['slug']];
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === 'source_userid') {
                continue;
            }
            if (str_ends_with($key, '_id')) {
                return [$key, $value[$key]];
            }
        }

        if (array_key_exists('source_userid', $value)) {
            return ['userid', $value['source_userid']];
        }

        if (array_key_exists('userid', $value)) {
            return ['userid', $value['userid']];
        }

        return null;
    }

}
