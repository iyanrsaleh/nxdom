<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\System\NexaController;
use App\Models\Office as OfficeFacade;

class CloudController extends NexaController
{
    private function invalidateOfficeQueryCache(): void
    {
        try {
            (new OfficeFacade())->invalidateMutationCache();
        } catch (\Throwable $e) {
            // ignore cache invalidation errors
        }
    }

    private function extractDriveRelativePath(string $safeTable, string $dbPath): ?string
    {
        $norm = str_replace('\\', '/', trim($dbPath));
        if ($norm === '') return null;
        if ($norm[0] !== '/') $norm = '/' . $norm;
        $prefix = '/assets/drive/' . $safeTable . '/';
        if (!str_starts_with($norm, $prefix)) return null;
        return substr($norm, strlen($prefix));
    }

    private function deleteDriveFileWithThumbs(string $safeTable, ?string $dbPath): void
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_-]/', '', $safeTable);
        if ($safeTable === '' || !is_string($dbPath) || trim($dbPath) === '') return;
        $rel = $this->extractDriveRelativePath($safeTable, $dbPath);
        if ($rel === null || $rel === '') return;
        $rootDir = dirname(__DIR__, 2);
        $baseDir = $rootDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
            . 'drive' . DIRECTORY_SEPARATOR . $safeTable . DIRECTORY_SEPARATOR;
        try {
            $nexaFile = new \App\System\Helpers\NexaFile();
            $nexaFile->setBaseUploadDir($baseDir);
            $nexaFile->setThumbnailSizes(['200x150', '500x300', '800x600']);
            $nexaFile->setThumbnailCropMode('crop');
            $nexaFile->deleteFile(['path' => $rel]);
        } catch (\Throwable $e) {
            // ignore cleanup error
        }
    }
    /**
     * Upload avatar - menerima multipart/form-data dari JS FormData
     * POST /api/cloud/avatar
     * $_POST: userid, id, tabel
     * $_FILES: file (image)
     */
    public function avatar($data = []): array
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $file   = $_FILES['file'];
        $userId = $data['userid'] ?? $data['id'] ?? null;

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File terlalu besar. Maksimal 5MB'];
        }

        // Validate MIME type using finfo (same as AccountController)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Format file tidak valid. Hanya JPG, PNG, dan WebP yang diperbolehkan'];
        }

        // Upload directory: assets/drive/avatar/YYYY/MM/ (same as AccountController)
        $year      = date('Y');
        $month     = date('m');
        $uploadDir = "assets/drive/avatar/{$year}/{$month}/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeUserId = $userId !== null ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$userId) : uniqid('', true);
        $filename   = 'avatar_' . $safeUserId . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'Gagal menyimpan file'];
        }

        // Fix Windows permissions: Apache menulis file dengan ACL miliknya sendiri
        // sehingga user lain tidak bisa membaca. icacls grant read ke Everyone.
        if (PHP_OS_FAMILY === 'Windows') {
            $realPath = realpath($uploadPath);
            if ($realPath) {
                @shell_exec('icacls "' . $realPath . '" /grant Everyone:R /Q 2>nul');
            }
        } else {
            @chmod($uploadPath, 0644);
        }

        // Get old avatar for cleanup (same as AccountController)
        $oldUser = $userId !== null
            ? $this->Storage('user')->select(['avatar'])->where('id', (int)$userId)->first()
            : null;

        // Update user table
        $avatarDbPath = '/' . $uploadPath;
        if ($userId !== null) {
            $result = $this->Storage('user')
                ->where('id', (int)$userId)
                ->update(['avatar' => $avatarDbPath]);

            if (!$result) {
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                return ['success' => false, 'message' => 'Gagal menyimpan perubahan'];
            }
        }

        // Delete old avatar file — skip default avatars (same as AccountController)
        $oldPath  = ltrim($oldUser['avatar'] ?? '', '/');
        $isDefault = (strpos($oldPath, 'avatar/pria.png') !== false || strpos($oldPath, 'avatar/wanita.png') !== false
                   || strpos($oldPath, 'images/pria.png') !== false || strpos($oldPath, 'images/wanita.png') !== false);
        if (!empty($oldUser['avatar']) && !$isDefault && file_exists($oldPath)) {
            unlink($oldPath);
        }

        return [
            'success'    => true,
            'message'    => 'Foto profil berhasil diperbarui',
            'avatar_url' => $avatarDbPath,
            'path'       => $uploadPath,
            'filename'   => $filename,
        ];
    }
    public function add($data = []): array
    {
        if (empty($_FILES)) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $tabel       = $data['tabel']       ?? $_POST['tabel']       ?? 'file';
        $userId      = $data['userid']      ?? $_POST['userid']      ?? null;
        // row_id: jika ada → UPDATE row by id, tanpa → INSERT baru
        $rowId       = $_POST['row_id']     ?? $data['row_id']       ?? null;
        $rowId       = ($rowId !== null && $rowId !== '') ? (int)$rowId : null;
        // fieldupload = nama kolom untuk path file di DB
        $fieldUpload = $_POST['fieldupload'] ?? $data['fieldupload'] ?? 'file';
        $fieldUpload = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldUpload);
        // fields = JSON object kolom tambahan
        $extraFields = [];
        $rawFields   = $_POST['fields'] ?? $data['fields'] ?? null;
        if (!empty($rawFields)) {
            $decoded = json_decode($rawFields, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
                    if ($safeKey !== '') {
                        $extraFields[$safeKey] = $v;
                    }
                }
            }
        }

        // Ambil file pertama yang valid dari $_FILES
        $fileKey = null;
        foreach ($_FILES as $key => $f) {
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK) {
                $fileKey = $key;
                break;
            }
        }
        if ($fileKey === null) {
            return ['success' => false, 'message' => 'No valid file uploaded'];
        }

        $file = $_FILES[$fileKey];

        // Detect MIME sebelum upload untuk menentukan apakah gambar (perlu thumbnail)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $isImage   = in_array($mimeType, [
            'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
        ], true);
        $safeTabel = preg_replace('/[^a-zA-Z0-9_-]/', '', $tabel);

        // baseUploadDir per tabel: c:\Tnserver\www\assets\drive\{tabel}\
        // NexaFile akan menambahkan YYYY/MM/ di dalamnya
        $rootDir   = dirname(__DIR__, 2); // c:\Tnserver\www
        $baseDir   = $rootDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
                   . 'drive'  . DIRECTORY_SEPARATOR . $safeTabel . DIRECTORY_SEPARATOR;

        $uploadConfig = [
            'allowedExtensions' => [
                'jpg', 'jpeg', 'png', 'webp', 'gif',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip',
            ],
            'allowedTypes'      => [
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

        // Tambahkan thumbnail hanya untuk gambar (NexaFile skip otomatis untuk non-image)
        if ($isImage) {
            $uploadConfig['thumbnail']         = ['200x150', '500x300', '800x600'];
            $uploadConfig['thumbnailCropMode'] = 'crop';
        }

        try {
            $nexaFile = new \App\System\Helpers\NexaFile();
            $nexaFile->setMaxFileSize(PHP_INT_MAX); // tanpa batas ukuran
            $fileResult = $nexaFile->handleFileUpload($file, $uploadConfig);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // $fileResult['path'] = 'YYYY/MM/filename.ext' (relatif dari baseDir)
        $relativePath = str_replace('\\', '/', $fileResult['path']);
        $dbPath       = '/assets/drive/' . $safeTabel . '/' . $relativePath;
        $fullPath     = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        // Fix Windows NTFS permissions — original file
        $this->fixFilePermissions($fullPath);

        // Thumbnail URLs untuk response dan DB
        $thumbnails = [];
        if (!empty($fileResult['thumbnails'])) {
            foreach ($fileResult['thumbnails'] as $size => $thumbRel) {
                $thumbRel      = str_replace('\\', '/', $thumbRel);
                $thumbDbPath   = '/assets/drive/' . $safeTabel . '/' . $thumbRel;
                $thumbnails[$size] = $thumbDbPath;
                $this->fixFilePermissions($baseDir . str_replace('/', DIRECTORY_SEPARATOR, $thumbRel));
            }
        }

        $insertId = null;
        if (!empty($safeTabel)) {
            $oldPathForCleanup = null;
            if ($rowId !== null) {
                $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $fieldUpload);
                if ($safeField !== '') {
                    $oldRow = $this->Storage($safeTabel)
                        ->select([$safeField])
                        ->where('id', $rowId)
                        ->first();
                    $oldPathForCleanup =
                        $oldRow[$safeField] ??
                        $oldRow['data'][$safeField] ??
                        null;
                }
            }
            $rowData = array_merge(
                [$fieldUpload => $dbPath],
                $extraFields,
                ['updated_at' => date('Y-m-d H:i:s')]
            );

            if ($rowId !== null) {
                // UPDATE row existing by id
                $this->Storage($safeTabel)
                    ->where('id', $rowId)
                    ->update($rowData);
                $insertId = $rowId;
                if (is_string($oldPathForCleanup) && trim($oldPathForCleanup) !== '' && $oldPathForCleanup !== $dbPath) {
                    $this->deleteDriveFileWithThumbs($safeTabel, $oldPathForCleanup);
                }
            } else {
                // INSERT row baru
                $rowData = array_merge(
                    $rowData,
                    [
                        'userid'  => $userId,
                        'pubdate' => date('Y-m-d H:i:s'),
                    ]
                );
                $insertRaw = $this->Storage($safeTabel)->insert($rowData);
                $insertId = is_numeric($insertRaw) ? (int)$insertRaw : null;
                if (!($insertId > 0)) {
                    // Fallback untuk driver yang mengembalikan bool dari insert().
                    $insertedRow = $this->Storage($safeTabel)
                        ->select(['id'])
                        ->where($fieldUpload, '=', $dbPath)
                        ->first();
                    $insertId = isset($insertedRow['id']) ? (int)$insertedRow['id'] : null;
                    if (!($insertId > 0) && isset($insertedRow['data']['id'])) {
                        $insertId = (int)$insertedRow['data']['id'];
                    }
                }
            }
        }

        // Cache executeOperation (JoinTabel) memakai Phpfastcache — invalidasi setelah mutasi.
        $this->invalidateOfficeQueryCache();

        return [
            'success'    => true,
            'message'    => 'File berhasil diunggah',
            'url'        => $dbPath,
            'path'       => $relativePath,
            'mime'       => $mimeType,
            'size'       => $fileResult['size'],
            'thumbnails' => $thumbnails,
            'insert_id'  => $insertId,
        ];
    }

    /**
     * Update file pada record existing — POST /api/cloud/update
     * $_POST: tabel, id, field (kolom yang akan diupdate)
     * $_FILES: file
     */
    public function update($data = []): array
    {
        if (empty($_FILES)) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $tabel  = $data['tabel']  ?? $_POST['tabel']  ?? null;
        $id     = $data['id']     ?? $_POST['id']     ?? $data['userid'] ?? $_POST['userid'] ?? null;
        $field  = $data['field']  ?? $_POST['field']  ?? 'file';

        if (empty($tabel) || $id === null) {
            return ['success' => false, 'message' => 'Parameter tabel dan id wajib diisi'];
        }

        // Ambil file pertama valid
        $fileKey = null;
        foreach ($_FILES as $key => $f) {
            if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK) {
                $fileKey = $key;
                break;
            }
        }
        if ($fileKey === null) {
            return ['success' => false, 'message' => 'No valid file uploaded'];
        }

        $file      = $_FILES[$fileKey];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $isImage   = in_array($mimeType, ['image/jpeg','image/jpg','image/png','image/webp','image/gif'], true);
        $safeTabel = preg_replace('/[^a-zA-Z0-9_-]/', '', $tabel);
        $rootDir   = dirname(__DIR__, 2);
        $baseDir   = $rootDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
                   . 'drive' . DIRECTORY_SEPARATOR . $safeTabel . DIRECTORY_SEPARATOR;

        $uploadConfig = [
            'allowedExtensions' => ['jpg','jpeg','png','webp','gif','pdf','doc','docx','xls','xlsx','csv','txt','zip'],
            'allowedTypes'      => [
                'image/jpeg','image/jpg','image/png','image/webp','image/gif',
                'application/pdf','text/plain','text/csv',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip','application/x-zip-compressed',
            ],
            'baseUploadDir' => $baseDir,
        ];

        if ($isImage) {
            $uploadConfig['thumbnail']         = ['200x150','500x300','800x600'];
            $uploadConfig['thumbnailCropMode'] = 'crop';
        }

        try {
            $nexaFile = new \App\System\Helpers\NexaFile();
            $nexaFile->setMaxFileSize(PHP_INT_MAX);
            $fileResult = $nexaFile->handleFileUpload($file, $uploadConfig);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $relativePath = str_replace('\\', '/', $fileResult['path']);
        $dbPath       = '/assets/drive/' . $safeTabel . '/' . $relativePath;
        $fullPath     = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->fixFilePermissions($fullPath);

        $thumbnails = [];
        if (!empty($fileResult['thumbnails'])) {
            foreach ($fileResult['thumbnails'] as $size => $thumbRel) {
                $thumbRel      = str_replace('\\', '/', $thumbRel);
                $thumbDbPath   = '/assets/drive/' . $safeTabel . '/' . $thumbRel;
                $thumbnails[$size] = $thumbDbPath;
                $this->fixFilePermissions($baseDir . str_replace('/', DIRECTORY_SEPARATOR, $thumbRel));
            }
        }

        // UPDATE row existing
        $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        $oldRow = $this->Storage($safeTabel)
            ->select([$safeField])
            ->where('id', (int)$id)
            ->first();
        $oldPathForCleanup =
            $oldRow[$safeField] ??
            $oldRow['data'][$safeField] ??
            null;
        $this->Storage($safeTabel)
            ->where('id', (int)$id)
            ->update([
                $safeField   => $dbPath,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if (is_string($oldPathForCleanup) && trim($oldPathForCleanup) !== '' && $oldPathForCleanup !== $dbPath) {
            $this->deleteDriveFileWithThumbs($safeTabel, $oldPathForCleanup);
        }

        // Cache executeOperation (JoinTabel) memakai Phpfastcache — invalidasi setelah mutasi.
        $this->invalidateOfficeQueryCache();

        return [
            'success'    => true,
            'message'    => 'File berhasil diperbarui',
            'url'        => $dbPath,
            'path'       => $relativePath,
            'mime'       => $mimeType,
            'size'       => $fileResult['size'],
            'thumbnails' => $thumbnails,
        ];
    }

    /**
     * Perbaiki NTFS permission setelah upload (Windows) atau chmod (Linux).
     */
    private function fixFilePermissions(string $filePath): void
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
}
