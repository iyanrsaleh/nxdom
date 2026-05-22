<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\Models\Office as OfficeFacade;

/**
 * Mutasi berantai pada beberapa tabel fisik (mis. utama + berelasi), dipanggil eksplisit dari klien.
 * Tidak mengubah {@see JoinTabel::joinQuery} — itu khusus SELECT gabungan.
 *
 * Payload utama:
 * - `steps`: array langkah `{ action, tableKey, name, alias?, columns?, id? }`
 *   - `action`: `insert` | `update` | `delete` | `upload` (upload: simpan berkas via NexaFile + update kolom; tidak lewat CloudController API)
 *   - `tableKey`: indeks tabel seperti pada `setRetInsert` / `setRetUpdate` / `setRettDelete`
 *   - `name`: alias app / pasangan kedua `[tableKey => name]` (biasanya sama dengan appId bucket)
 *   - `alias`: (opsional) nama alias fisik tabel join — disimpan ke `results[]` dan dipakai untuk `afterAlias` pada langkah upload
 *   - `columns`: untuk insert/update (kolom siap simpan)
 *   - `id`: PK untuk update/delete; untuk `upload` bisa digabung `afterAlias` mengacu insert sebelumnya
 *
 * Langkah `upload`: `uploadTable`, `column`, `base64` (atau data-URL), `filename`, dan `id` **atau** `afterAlias` (cocok `alias` insert).
 *
 * Langkah `delete`: opsional `cleanupUploadColumns` + `cleanupUploadTable` — sebelum hapus baris, berkas di
 * kolom tersebut (path `/assets/drive/{tabel}/…`) dihapus dari disk lewat NexaFile (termasuk thumbnail).
 *
 * Placeholder pada nilai string kolom: `{{previousInsertId}}` diganti dengan `id` dari **insert**
 * pada langkah sebelumnya yang sukses (berguna untuk isi FK di langkah kedua).
 *
 * Opsional: `userid` di root payload — digabung ke kolom insert/update (sama pola NexaPayload).
 */
final class JoinOprasi
{
    private const PREV_INSERT_PH = '{{previousInsertId}}';

    /**
     * @return array{success:bool, results?:array, error?:string, failedStep?:int}
     */
    public static function run(OfficeFacade $office, array $data): array
    {
        $steps = $data['steps'] ?? null;
        if (!is_array($steps) || $steps === []) {
            return [
                'success' => false,
                'error' => 'steps harus berisi array langkah mutation tidak kosong.',
                'results' => [],
            ];
        }

        $userid = $data['userid'] ?? null;
        $previousInsertId = null;
        /** @var array<string,int> */
        $insertIdsByAlias = [];
        $results = [];

        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                return [
                    'success' => false,
                    'error' => 'Langkah #' . $i . ': bukan objek/array.',
                    'results' => $results,
                    'failedStep' => $i,
                ];
            }

            $action = strtolower(trim((string)($step['action'] ?? '')));
            $tableKey = isset($step['tableKey']) ? (int)$step['tableKey'] : 0;
            $name = trim((string)($step['name'] ?? ''));

            if ($tableKey <= 0 || $name === '') {
                return [
                    'success' => false,
                    'error' => 'Langkah #' . $i . ': tableKey dan name wajib.',
                    'results' => $results,
                    'failedStep' => $i,
                ];
            }

            try {
                switch ($action) {
                    case 'insert':
                        $columns = isset($step['columns']) && is_array($step['columns'])
                            ? self::applyPlaceholder($step['columns'], $previousInsertId)
                            : [];
                        if ($userid !== null && $userid !== '') {
                            $columns['userid'] = $userid;
                        }
                        $out = $office->setRetInsert($tableKey, $name, $columns, null);
                        if (is_array($out) && array_key_exists('success', $out) && $out['success'] === false) {
                            return [
                                'success' => false,
                                'error' => (string)($out['error'] ?? 'Insert gagal'),
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        $aliasRaw = isset($step['alias']) ? trim((string)$step['alias']) : '';
                        $results[] = [
                            'action' => 'insert',
                            'alias' => $aliasRaw !== '' ? $aliasRaw : null,
                            'tableKey' => $tableKey,
                            'name' => $name,
                            'result' => $out,
                        ];
                        if ($aliasRaw !== '' && is_array($out) && isset($out['id'])) {
                            $insertIdsByAlias[$aliasRaw] = (int)$out['id'];
                        }
                        if (is_array($out) && isset($out['id'])) {
                            $previousInsertId = (int)$out['id'];
                        }
                        break;

                    case 'update':
                        $id = isset($step['id']) ? (int)$step['id'] : 0;
                        $columns = isset($step['columns']) && is_array($step['columns'])
                            ? self::applyPlaceholder($step['columns'], $previousInsertId)
                            : [];
                        if ($userid !== null && $userid !== '') {
                            $columns['userid'] = $userid;
                        }
                        if ($id <= 0) {
                            return [
                                'success' => false,
                                'error' => 'Langkah #' . $i . ': update membutuhkan id positif.',
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        $out = $office->setRetUpdate($tableKey, $name, $columns, $id, null);
                        $aliasRaw = isset($step['alias']) ? trim((string)$step['alias']) : '';
                        $results[] = [
                            'action' => 'update',
                            'alias' => $aliasRaw !== '' ? $aliasRaw : null,
                            'tableKey' => $tableKey,
                            'name' => $name,
                            'result' => $out,
                        ];
                        break;

                    case 'delete':
                        $id = isset($step['id']) ? (int)$step['id'] : 0;
                        $cleanupCols = isset($step['cleanupUploadColumns']) && is_array($step['cleanupUploadColumns'])
                            ? $step['cleanupUploadColumns']
                            : [];
                        $cleanupTbl = isset($step['cleanupUploadTable'])
                            ? preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)$step['cleanupUploadTable']))
                            : '';
                        $aliasRaw = isset($step['alias']) ? trim((string)$step['alias']) : '';
                        if ($cleanupTbl === '' && $aliasRaw !== '') {
                            $cleanupTbl = preg_replace('/[^a-zA-Z0-9_-]/', '', $aliasRaw);
                        }
                        if ($id > 0) {
                            if ($cleanupTbl !== '' && $cleanupCols !== []) {
                                self::cleanupDriveUploadColumnsBeforeDelete($office, $cleanupTbl, $id, $cleanupCols);
                            }
                            $out = $office->setRettDelete($tableKey, $name, $id);
                        } else {
                            // Fallback delete by relation key (mis. child.ptmid = parent.id) saat id child tidak tersedia.
                            $whereCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($step['whereColumn'] ?? ''));
                            $whereVal = isset($step['whereValue']) ? (int)$step['whereValue'] : 0;
                            if ($whereCol === '' || $whereVal <= 0 || $cleanupTbl === '') {
                                return [
                                    'success' => false,
                                    'error' => 'Langkah #' . $i . ': delete membutuhkan id positif atau whereColumn/whereValue yang valid.',
                                    'results' => $results,
                                    'failedStep' => $i,
                                ];
                            }
                            $rows = $office->Storage($cleanupTbl)->where($whereCol, $whereVal)->get();
                            if (!is_array($rows)) {
                                $rows = [];
                            }
                            $deleted = 0;
                            foreach ($rows as $rowItem) {
                                if (!is_array($rowItem)) continue;
                                $rid = isset($rowItem['id']) ? (int)$rowItem['id'] : 0;
                                if ($rid <= 0) continue;
                                if ($cleanupCols !== []) {
                                    self::cleanupDriveUploadColumnsBeforeDelete($office, $cleanupTbl, $rid, $cleanupCols);
                                }
                                $office->setRettDelete($tableKey, $name, $rid);
                                $deleted++;
                            }
                            $out = [
                                'success' => true,
                                'deleted' => $deleted,
                                'whereColumn' => $whereCol,
                                'whereValue' => $whereVal,
                            ];
                        }
                        $results[] = [
                            'action' => 'delete',
                            'alias' => $aliasRaw !== '' ? $aliasRaw : null,
                            'tableKey' => $tableKey,
                            'name' => $name,
                            'result' => $out,
                        ];
                        break;

                    case 'upload':
                        $aliasMeta = isset($step['alias']) ? trim((string)$step['alias']) : '';
                        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($step['column'] ?? 'file'));
                        $safeTabel = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($step['uploadTable'] ?? ''));
                        if ($safeTabel === '' && $aliasMeta !== '') {
                            $safeTabel = preg_replace('/[^a-zA-Z0-9_-]/', '', $aliasMeta);
                        }
                        $base64Raw = $step['base64'] ?? '';
                        $filename = (string)($step['filename'] ?? 'upload.bin');
                        if ($safeColumn === '' || $safeTabel === '') {
                            return [
                                'success' => false,
                                'error' => 'Langkah #' . $i . ': upload membutuhkan uploadTable/column yang valid.',
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        if (!is_string($base64Raw) || trim($base64Raw) === '') {
                            return [
                                'success' => false,
                                'error' => 'Langkah #' . $i . ': upload membutuhkan base64 tidak kosong.',
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        $rowId = isset($step['id']) ? (int)$step['id'] : 0;
                        if ($rowId <= 0 && !empty($step['afterAlias'])) {
                            $aa = trim((string)$step['afterAlias']);
                            $rowId = (int)($insertIdsByAlias[$aa] ?? 0);
                        }
                        if ($rowId <= 0) {
                            return [
                                'success' => false,
                                'error' => 'Langkah #' . $i . ': upload membutuhkan id atau afterAlias yang cocok dengan insert sebelumnya.',
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        try {
                            $saved = self::persistJoinDriveUpload($safeTabel, $filename, $base64Raw);
                        } catch (\Throwable $ex) {
                            return [
                                'success' => false,
                                'error' => 'Langkah #' . $i . ': ' . $ex->getMessage(),
                                'results' => $results,
                                'failedStep' => $i,
                            ];
                        }
                        $pathCandidates = array_values(array_unique(array_filter([
                            (string)($saved['dbPath'] ?? ''),
                            (string)($saved['relativePath'] ?? ''),
                            basename((string)($saved['relativePath'] ?? '')),
                        ], static fn($v) => is_string($v) && trim($v) !== '')));
                        $extraCandidates = [];
                        foreach ($pathCandidates as $basePath) {
                            $basePath = (string)$basePath;
                            $len = strlen($basePath);
                            foreach ([255, 191, 160, 128, 100, 80, 64, 48, 32, 24, 16, 12, 8, 4, 1] as $maxLen) {
                                if ($len > $maxLen) {
                                    $extraCandidates[] = substr($basePath, 0, $maxLen);
                                }
                            }
                        }
                        $pathCandidates = array_values(array_unique(array_merge($pathCandidates, $extraCandidates)));

                        $lastUpdateError = null;
                        $updated = false;
                        foreach ($pathCandidates as $candidatePath) {
                            try {
                                $office->Storage($safeTabel)
                                    ->where('id', $rowId)
                                    ->update([
                                        $safeColumn => $candidatePath,
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ]);
                                $updated = true;
                                // Gunakan path yang benar-benar tersimpan agar respons sinkron.
                                $saved['dbPath'] = $candidatePath;
                                break;
                            } catch (\Throwable $updateEx) {
                                $lastUpdateError = $updateEx;
                                // Lanjutkan ke kandidat path yang lebih pendek.
                            }
                        }
                        if (!$updated) {
                            throw ($lastUpdateError ?? new \RuntimeException('Gagal menyimpan path upload ke kolom target.'));
                        }
                        $results[] = [
                            'action' => 'upload',
                            'alias' => $aliasMeta !== '' ? $aliasMeta : null,
                            'tableKey' => $tableKey,
                            'name' => $name,
                            'result' => [
                                'success' => true,
                                'url' => $saved['dbPath'],
                                'path' => $saved['relativePath'],
                                'thumbnails' => $saved['thumbnails'],
                            ],
                        ];
                        break;

                    default:
                        return [
                            'success' => false,
                            'error' => 'Langkah #' . $i . ': action tidak dikenal (insert|update|delete|upload).',
                            'results' => $results,
                            'failedStep' => $i,
                        ];
                }
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'results' => $results,
                    'failedStep' => $i,
                ];
            }
        }
        
        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Simpan berkas join mutation ke assets/drive/{tabel}/ lewat NexaFile (tanpa CloudController).
     *
     * @return array{dbPath:string,relativePath:string,thumbnails?:array<string,string>}
     */
    private static function persistJoinDriveUpload(string $safeTabel, string $filename, string $base64Input): array
    {
        $stripped = preg_replace('#^data:\\s*[^;]+;base64,#i', '', trim($base64Input));
        $binary = base64_decode($stripped, true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Isi base64 tidak valid.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_buffer($finfo, $binary) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!is_string($mimeType) || $mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $isImage = in_array($mimeType, [
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
        ], true);

        $rootDir = dirname(__DIR__, 2);
        $baseDir = $rootDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
            . 'drive' . DIRECTORY_SEPARATOR . $safeTabel . DIRECTORY_SEPARATOR;

        $uploadConfig = [
            'allowedExtensions' => [
                'jpg', 'jpeg', 'png', 'webp', 'gif',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip',
            ],
            'allowedTypes' => [
                'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
                'application/pdf',
                'text/plain', 'text/csv',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-zip-compressed',
            ],
            'baseUploadDir' => $baseDir,
        ];

        if ($isImage) {
            $uploadConfig['thumbnail'] = ['200x150', '500x300', '800x600'];
            $uploadConfig['thumbnailCropMode'] = 'crop';
        }

        $nexaFile = new \App\System\Helpers\NexaFile();
        $nexaFile->setMaxFileSize(PHP_INT_MAX);

        $filePayload = [
            'name' => $filename,
            'type' => $mimeType,
            'size' => strlen($binary),
            'content' => base64_encode($binary),
        ];

        $fileResult = $nexaFile->handleFileUpload($filePayload, $uploadConfig);

        $relativePath = str_replace('\\', '/', $fileResult['path']);
        $dbPath = '/assets/drive/' . $safeTabel . '/' . $relativePath;
        $fullPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        self::joinFixFsPermissions($fullPath);

        $thumbnails = [];
        if (!empty($fileResult['thumbnails']) && is_array($fileResult['thumbnails'])) {
            foreach ($fileResult['thumbnails'] as $size => $thumbRel) {
                $thumbRel = str_replace('\\', '/', (string)$thumbRel);
                $thumbnails[(string)$size] = '/assets/drive/' . $safeTabel . '/' . $thumbRel;
                self::joinFixFsPermissions($baseDir . str_replace('/', DIRECTORY_SEPARATOR, $thumbRel));
            }
        }

        return [
            'dbPath' => $dbPath,
            'relativePath' => $relativePath,
            'thumbnails' => $thumbnails,
        ];
    }

    private static function joinFixFsPermissions(string $filePath): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $real = realpath($filePath);
            if ($real) {
                @shell_exec('icacls "' . $real . '" /grant Everyone:R /Q 2>nul');
            }
        } else {
            @chmod($filePath, 0644);
        }
    }

    /**
     * Sebelum hapus baris: hapus berkas drive + thumbnail untuk kolom yang disebut (path harus `/assets/drive/{tabel}/…`).
     *
     * @param list<string|int|float> $columns
     */
    private static function cleanupDriveUploadColumnsBeforeDelete(
        OfficeFacade $office,
        string $safeTable,
        int $rowId,
        array $columns,
    ): void {
        $safeTable = preg_replace('/[^a-zA-Z0-9_-]/', '', $safeTable);
        if ($safeTable === '' || $rowId <= 0) {
            return;
        }

        try {
            $row = $office->Storage($safeTable)->where('id', $rowId)->first();
        } catch (\Throwable $e) {
            return;
        }
        if (!is_array($row)) {
            return;
        }

        $colList = [];
        foreach ($columns as $c) {
            $sc = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$c);
            if ($sc !== '') {
                $colList[] = $sc;
            }
        }
        $colList = array_values(array_unique($colList));

        // Fallback: bila payload delete tidak kirim cleanupUploadColumns,
        // deteksi otomatis kolom yang berisi path drive untuk tabel ini.
        if ($colList === []) {
            foreach ($row as $colName => $val) {
                $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$colName);
                if ($safeCol === '' || !is_string($val) || trim($val) === '') {
                    continue;
                }
                $relAuto = self::joinDriveRelativePath($safeTable, trim($val));
                if ($relAuto !== null && $relAuto !== '') {
                    $colList[] = $safeCol;
                }
            }
            $colList = array_values(array_unique($colList));
        }
        if ($colList === []) {
            return;
        }

        $rootDir = dirname(__DIR__, 2);
        $baseDir = $rootDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
            . 'drive' . DIRECTORY_SEPARATOR . $safeTable . DIRECTORY_SEPARATOR;

        $nexaFile = new \App\System\Helpers\NexaFile();
        $nexaFile->setBaseUploadDir($baseDir);
        $nexaFile->setThumbnailSizes(['200x150', '500x300', '800x600']);
        $nexaFile->setThumbnailCropMode('crop');

        foreach ($colList as $col) {
            if (!\array_key_exists($col, $row)) {
                continue;
            }
            $val = $row[$col];
            if (!is_string($val) || trim($val) === '') {
                continue;
            }
            $rel = self::joinDriveRelativePath($safeTable, trim($val));
            if ($rel === null || $rel === '') {
                continue;
            }
            try {
                $nexaFile->deleteFile(['path' => $rel]);
            } catch (\Throwable $e) {
                // non-fatal — lanjut kolom lain & hapus baris
            }
        }
    }

    /** Path DB seperti `/assets/drive/demo/2026/05/x.png` → relatif dari folder tabel (`2026/05/x.png`). */
    private static function joinDriveRelativePath(string $safeTable, string $dbPath): ?string
    {
        $norm = str_replace('\\', '/', trim($dbPath));
        if ($norm === '') {
            return null;
        }
        if ($norm[0] !== '/') {
            $norm = '/' . $norm;
        }
        $prefix = '/assets/drive/' . $safeTable . '/';
        if (!str_starts_with($norm, $prefix)) {
            return null;
        }

        return substr($norm, \strlen($prefix));
    }

    /**
     * @param array<string,mixed> $columns
     * @return array<string,mixed>
     */
    private static function applyPlaceholder(array $columns, ?int $previousInsertId): array
    {
        if ($previousInsertId === null) {
            return $columns;
        }
        $repl = (string)$previousInsertId;
        $out = [];
        foreach ($columns as $k => $v) {
            if ($v === self::PREV_INSERT_PH) {
                $out[$k] = $previousInsertId;
            } elseif (is_string($v) && str_contains($v, self::PREV_INSERT_PH)) {
                $out[$k] = str_replace(self::PREV_INSERT_PH, $repl, $v);
            } elseif (is_array($v)) {
                $out[$k] = self::applyPlaceholder($v, $previousInsertId);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
