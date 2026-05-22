<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\Models\Office as OfficeFacade;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use Throwable;

/**
 * Impor CSV / XLSX ke tabel utama app (satu {@see OfficeFacade::setRetInsert} per baris).
 *
 * Pemetaan kolom:
 * - `columnMapMode` `header` (bawaan): baris pertama = header teks; cocokkan ke formulir / fallback nama kolom.
 * - `columnMapMode` `formulirKeys`: tanpa header — kolom ke-i dari file = field formulir urutan {@see array_keys}
 *   (lewati `type: file`), nilai diisi ke kolom DB dari `failed`.
 */
final class Importing
{
    private const DEFAULT_MAX_ROWS = 5000;
    /** @var array<int, string> */
    private const RESERVED_IMPORT_COLUMNS = ['id', 'created_at', 'updated_at', 'row'];

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function run(OfficeFacade $office, array $data): array
    {
        $tableKey = (int)($data['tableKey'] ?? 0);
        $name = trim((string)($data['name'] ?? $data['appId'] ?? ''));
        $base64In = $data['base64'] ?? $data['content'] ?? '';
        if (!is_string($base64In)) {
            $base64In = '';
        }

        if ($tableKey < 1 || $name === '') {
            throw new InvalidArgumentException('Import: tableKey dan name/appId wajib valid.');
        }

        $binary = self::decodeBase64Payload($base64In);
        if ($binary === '' || $binary === false) {
            throw new InvalidArgumentException('Import: base64/file kosong atau tidak valid.');
        }

        $binary = self::stripLeadingUtf8Bom($binary);

        $maxBytes = (int)($data['maxBytes'] ?? 12 * 1024 * 1024);
        if ($maxBytes < 4096) {
            $maxBytes = 4096;
        }
        if (strlen($binary) > $maxBytes) {
            throw new InvalidArgumentException('Import: file melebihi batas ukuran.');
        }

        $filename = strtolower((string)($data['filename'] ?? 'upload.csv'));
        $maxRows = (int)($data['maxRows'] ?? self::DEFAULT_MAX_ROWS);
        if ($maxRows < 1) {
            $maxRows = self::DEFAULT_MAX_ROWS;
        }

        $buildQuery = $data['buildQuery'] ?? [];
        $formulir = is_array($buildQuery['formulir'] ?? null) ? $buildQuery['formulir'] : [];
        $mainAlias = self::mainTableAlias(is_array($buildQuery) ? $buildQuery : []);

        $delimiter = (string)($data['delimiter'] ?? ',');
        if ($delimiter === '') {
            $delimiter = ',';
        }

        $extEarly = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isCsvLike = $extEarly === 'csv' || $extEarly === '';
        $delimiterExplicit = isset($data['delimiter'])
            && trim((string)$data['delimiter']) !== ''
            && trim((string)$data['delimiter']) !== ',';
        // Excel locale ID/Eropa sering export CSV pemisah `;` — koma salah → satu kolom → hanya nama terisi.
        if ($isCsvLike && !$delimiterExplicit && ($delimiter === ',' || $delimiter === '')) {
            $delimiter = self::sniffCsvDelimiter($binary);
        }
        if ($delimiter === '') {
            $delimiter = ',';
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'nx_imp_');
        if ($tempPath === false) {
            throw new InvalidArgumentException('Import: gagal membuat file sementara.');
        }

        try {
            if (file_put_contents($tempPath, $binary) === false) {
                throw new InvalidArgumentException('Import: gagal menulis file sementara.');
            }

            $spreadsheet = self::loadSpreadsheet($tempPath, $filename, $delimiter);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (Throwable $e) {
            @unlink($tempPath);
            throw new InvalidArgumentException('Import: gagal membaca spreadsheet — ' . $e->getMessage(), 0, $e);
        }

        @unlink($tempPath);

        if ($rows === [] || !isset($rows[0])) {
            return [
                'success' => false,
                'inserted' => 0,
                'skipped' => 0,
                'message' => 'Import: tidak ada baris.',
                'errors' => [],
            ];
        }

        $userid = $data['userid'] ?? null;

        $columnMapMode = strtolower(trim((string)($data['columnMapMode'] ?? 'header')));
        $byFormulirOrder = $columnMapMode === 'formulirkeys';

        if ($byFormulirOrder) {
            return self::runFormulirKeyColumnOrder(
                $office,
                $rows,
                $formulir,
                $mainAlias,
                $userid,
                $tableKey,
                $name,
                $maxRows,
                $isCsvLike,
                $delimiter,
            );
        }

        $headers = array_shift($rows);
        $headers = self::normalizeSheetVector($headers);
        $headerMap = self::buildHeaderToColumnMap($headers, $formulir, $mainAlias);

        if ($headerMap === []) {
        return [
            'success' => false,
                'inserted' => 0,
                'skipped' => 0,
                'message' => 'Import: tidak ada kolom yang cocok antara header file dan formulir.',
                'errors' => [],
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $office->invalidateMutationCache();

        $processed = 0;
        foreach ($rows as $idx => $line) {
            $processed++;
            if ($processed > $maxRows) {
                $errors[] = [
                    'row' => $idx + 2,
                    'message' => 'Berhenti: mencapai maxRows.',
                ];
                break;
            }

            if (!is_array($line)) {
                $skipped++;
                continue;
            }

            $line = self::normalizeSheetVector(is_array($line) ? $line : []);
            $columns = self::rowToColumns($line, $headers, $headerMap);
            if ($columns === []) {
                $skipped++;
                continue;
            }
            
            if ($userid !== null && $userid !== '') {
                $columns['userid'] = (int)$userid;
            }

            if (!RowFormHelpers::hasValidFormData($columns)) {
                $skipped++;
                continue;
            }
            
            try {
                $out = $office->setRetInsert($tableKey, $name, $columns, null);
                if (is_array($out) && isset($out['success']) && $out['success'] === false) {
                    $errors[] = [
                        'row' => $idx + 2,
                        'message' => (string)($out['error'] ?? $out['message'] ?? 'Insert gagal'),
                    ];
                } else {
                    $inserted++;
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'row' => $idx + 2,
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'success' => $inserted > 0,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'mappedColumns' => array_values(array_unique(array_values($headerMap))),
            'detectedDelimiter' => $isCsvLike ? $delimiter : null,
            'headerCells' => array_values($headers),
            'columnMapMode' => 'header',
            'errors' => $errors,
            'message' => $inserted > 0
                ? "Import selesai: {$inserted} baris."
                : 'Tidak ada baris yang disisipkan.',
        ];
    }
    
    /**
     * Pemetaan posisi kolom = urutan key formulir (tanpa baris header spreadsheet).
     *
     * @param list<array<int, mixed>> $rows
     * @param array<string|int, mixed> $formulir
     * @return array<string, mixed>
     */
    private static function runFormulirKeyColumnOrder(
        OfficeFacade $office,
        array $rows,
        array $formulir,
        string $mainAlias,
        mixed $userid,
        int $tableKey,
        string $name,
        int $maxRows,
        bool $isCsvLike,
        string $delimiter,
    ): array {
        $ordered = self::orderedFormulirImportSpecs($formulir, $mainAlias);
        if ($ordered === []) {
            return [
                'success' => false,
                'inserted' => 0,
                'skipped' => 0,
                'message' => 'Import (formulirKeys): tidak ada field formulir non-file dengan failed valid.',
                'errors' => [],
                'columnMapMode' => 'formulirKeys',
                'formulirKeyOrder' => [],
                'mappedColumns' => [],
            ];
        }

        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $office->invalidateMutationCache();

        $processed = 0;
        foreach ($rows as $idx => $line) {
            $processed++;
            if ($processed > $maxRows) {
                $errors[] = [
                    'row' => $idx + 1,
                    'message' => 'Berhenti: mencapai maxRows.',
                ];
                break;
            }

            if (!is_array($line)) {
                $skipped++;
                continue;
            }

            $line = self::normalizeSheetVector($line);
            $columns = self::rowToColumnsByFormulirOrder($line, $ordered);
            if ($columns === []) {
                $skipped++;
                continue;
            }

            if ($userid !== null && $userid !== '') {
                $columns['userid'] = (int)$userid;
            }

            if (!RowFormHelpers::hasValidFormData($columns)) {
                $skipped++;
                continue;
            }

            try {
                $out = $office->setRetInsert($tableKey, $name, $columns, null);
                if (is_array($out) && isset($out['success']) && $out['success'] === false) {
                    $errors[] = [
                        'row' => $idx + 1,
                        'message' => (string)($out['error'] ?? $out['message'] ?? 'Insert gagal'),
                    ];
                } else {
                    $inserted++;
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'row' => $idx + 1,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $mapped = array_column($ordered, 'column');
        $keys = array_column($ordered, 'key');

        return [
            'success' => $inserted > 0,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'mappedColumns' => $mapped,
            'formulirKeyOrder' => $keys,
            'detectedDelimiter' => $isCsvLike ? $delimiter : null,
            'headerCells' => [],
            'columnMapMode' => 'formulirKeys',
            'errors' => $errors,
            'message' => $inserted > 0
                ? "Import selesai: {$inserted} baris (urutan key formulir)."
                : 'Tidak ada baris yang disisipkan.',
        ];
    }

    /**
     * Urutan sama dengan array_keys(formulir) di PHP (insertion order untuk objek JSON dari JS).
     *
     * @param array<string|int, mixed> $formulir
     * @return list<array{key: string, column: string}>
     */
    private static function orderedFormulirImportSpecs(array $formulir, string $mainAlias): array
    {
        $out = [];
        foreach (array_keys($formulir) as $fieldKey) {
            $meta = $formulir[$fieldKey];
            if (!is_array($meta)) {
                continue;
            }
            if (($meta['type'] ?? '') === 'file') {
                continue;
            }
            $failedRaw = (string)($meta['failed'] ?? '');
            $col = self::columnFromFailed($failedRaw, $mainAlias)
                ?? self::columnFromFailedRelaxed($failedRaw);
            if ($col === null) {
                continue;
            }
            if (self::isReservedImportColumn($col)) {
                continue;
            }
            $out[] = [
                'key' => (string)$fieldKey,
                'column' => $col,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, mixed> $line
     * @param list<array{key: string, column: string}> $ordered
     * @return array<string, scalar|null>
     */
    private static function rowToColumnsByFormulirOrder(array $line, array $ordered): array
    {
        $columns = [];
        foreach ($ordered as $j => $spec) {
            $dbCol = $spec['column'];
            if (!array_key_exists($j, $line)) {
                continue;
            }
            $val = $line[$j];
            if ($val === null) {
                continue;
            }
            if (is_string($val)) {
                $val = trim(self::stripCellBom($val));
            }
            if ($val === '') {
                continue;
            }
            $columns[$dbCol] = is_scalar($val) ? $val : null;
        }

        return $columns;
    }

    /**
     * @return array<int|string, string> normalized header → db column
     * @param array<int, mixed> $headers
     * @param array<string, mixed> $formulir
     */
    private static function buildHeaderToColumnMap(array $headers, array $formulir, string $mainAlias): array
    {
        $map = [];
        $mapLoose = [];

        if ($formulir !== []) {
            foreach ($formulir as $fieldKey => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                if (($meta['type'] ?? '') === 'file') {
                    continue;
                }
                $failedRaw = (string)($meta['failed'] ?? '');
                $col = self::columnFromFailed($failedRaw, $mainAlias)
                    ?? self::columnFromFailedRelaxed($failedRaw);
                if ($col === null) {
                    continue;
                }
                if (self::isReservedImportColumn($col)) {
                    continue;
                }

                $synonyms = self::formulirFieldSynonyms($fieldKey, $meta, $col);

                $fa = trim((string)($meta['fieldAlias'] ?? ''));
                if (
                    $fa !== ''
                    && preg_match('/\bas\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*$/i', $fa, $m)
                ) {
                    $synonyms[] = $m[1];
                }

                foreach (array_unique(array_filter(array_map('trim', $synonyms))) as $syn) {
                    self::registerHeaderSynonym($map, $mapLoose, $syn, $col);
                }
            }
        }

        $out = [];
        foreach ($headers as $i => $h) {
            if ($h === null || $h === '') {
                continue;
            }
            $hn = self::normHeader(self::stripCellBom((string)$h));
            if ($hn === '') {
                continue;
            }

            if ($formulir !== [] && isset($map[$hn])) {
                $out[(int)$i] = $map[$hn];
                continue;
            }

            $hl = self::normHeaderLooseKey($hn);
            if ($formulir !== [] && $hl !== '' && isset($mapLoose[$hl])) {
                $out[(int)$i] = $mapLoose[$hl];
                continue;
            }

            // Header = nama kolom DB (Nik→nik, Tanggal_lahir→tanggal_lahir) jika tidak ada di formulir
            $fromHeader = self::headerTextToDbColumn((string)$h);
            if ($fromHeader !== null && !self::isReservedImportColumn($fromHeader)) {
                $out[(int)$i] = $fromHeader;
            }
        }

        return $out;
    }

    /**
     * Ubah teks header spreadsheet menjadi nama kolom SQL aman (huruf kecil, spasi/tanda → underscore).
     */
    private static function headerTextToDbColumn(string $raw): ?string
    {
        $raw = self::stripCellBom(trim($raw));
        if ($raw === '') {
            return null;
        }
        $s = strtolower(str_replace([' ', '-', '.'], '_', $raw));
        $s = preg_replace('/_+/u', '_', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9_]/u', '', $s) ?? '';
        if ($s === '' || $s[0] === '_') {
            return null;
        }
        $s = trim($s, '_');

        return preg_match('/^[a-z][a-z0-9_]*$/', $s) ? $s : null;
    }

    /**
     * Sinonim header dari kunci objek formulir + meta (supaya kolom CSV cocok dengan key "deskripsi", "title", dll.).
     *
     * @param array<string,mixed> $meta
     * @return string[]
     */
    private static function formulirFieldSynonyms(string|int $fieldKey, array $meta, string $dbColumn): array
    {
        $syn = [];
        $fk = trim((string)$fieldKey);
        if ($fk !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fk)) {
            $syn[] = $fk;
        }
        foreach (['name', 'id', 'label', 'placeholder'] as $k) {
            if (!array_key_exists($k, $meta)) {
                continue;
            }
            $v = $meta[$k];
            if ($v === null || $v === '') {
                continue;
            }
            $syn[] = trim((string)$v);
        }
        $syn[] = $dbColumn;

        return $syn;
    }

    /**
     * @param array<string, string> $map normalized header → db column
     * @param array<string, string> $mapLoose alphanumeric-only key → db column
     */
    private static function registerHeaderSynonym(array &$map, array &$mapLoose, string $synonym, string $col): void
    {
        $n = self::normHeader($synonym);
        if ($n === '') {
            return;
        }
        $map[$n] = $col;
        $loose = self::normHeaderLooseKey($n);
        if ($loose !== '') {
            $mapLoose[$loose] = $col;
        }
    }

    /** Kunci longgar: huruf/angka saja (untuk header "Desk ripsi" vs "deskripsi"). */
    private static function normHeaderLooseKey(string $normalizedLowerHeader): string
    {
        $s = preg_replace('/[^a-z0-9]+/u', '', $normalizedLowerHeader) ?? '';

        return $s;
    }

    private static function isReservedImportColumn(string $col): bool
    {
        return in_array(strtolower(trim($col)), self::RESERVED_IMPORT_COLUMNS, true);
    }

    /**
     * Jika alias di failed tidak sama dengan mainAlias (mis. buildQuery beda urutan), tetap ambil nama kolom DB.
     */
    private static function columnFromFailedRelaxed(string $failed): ?string
    {
        $failed = trim($failed);
        if ($failed === '') {
            return null;
        }
        $dot = strrpos($failed, '.');
        $col = $dot === false ? $failed : substr($failed, $dot + 1);

        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col) ? $col : null;
    }

    private static function normHeader(string $s): string
    {
        $s = self::stripCellBom(trim($s));
        $s = strtolower($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return $s;
    }

    private static function stripLeadingUtf8Bom(string $binary): string
    {
        if (str_starts_with($binary, "\xEF\xBB\xBF")) {
            return substr($binary, 3);
        }

        return $binary;
    }

    private static function stripCellBom(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            return substr($s, 3);
        }

        return $s;
    }

    /**
     * Deteksi pemisah baris pertama CSV (koma vs titik koma vs tab).
     */
    private static function sniffCsvDelimiter(string $binary): string
    {
        $first = strtok($binary, "\r\n");
        if ($first === false || $first === '') {
            return ',';
        }
        $semi = substr_count($first, ';');
        $comma = substr_count($first, ',');
        $tab = substr_count($first, "\t");
        if ($tab >= $semi && $tab >= $comma && $tab > 0) {
            return "\t";
        }
        if ($semi > $comma) {
            return ';';
        }

        return ',';
    }

    /**
     * Rapatkan indeks 0..n agar kolom selaras dengan header (PhpSpreadsheet kadang sparse).
     *
     * @param array<int|string, mixed> $row
     * @return array<int, mixed>
     */
    private static function normalizeSheetVector(array $row): array
    {
        if ($row === []) {
            return [];
        }
        $max = -1;
        foreach (array_keys($row) as $k) {
            if (is_int($k)) {
                $max = max($max, $k);
            }
        }
        if ($max < 0) {
            return array_values($row);
        }
        $out = [];
        for ($i = 0; $i <= $max; $i++) {
            $out[$i] = array_key_exists($i, $row) ? $row[$i] : null;
        }

        return $out;
    }

    /** Ambil nama kolom DB dari `alias.kolom`, filter ke tabel utama bila alias diketahui. */
    private static function columnFromFailed(string $failed, string $mainAlias): ?string
    {
        $failed = trim($failed);
        if ($failed === '') {
            return null;
        }
        $dot = strrpos($failed, '.');
        if ($dot === false) {
            return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $failed) ? $failed : null;
        }
        $alias = substr($failed, 0, $dot);
        $col = substr($failed, $dot + 1);
        if ($mainAlias !== '' && $alias !== $mainAlias) {
            return null;
        }

        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col) ? $col : null;
    }

    /** @param array<string,mixed> $bq */
    private static function mainTableAlias(array $bq): string
    {
        $tn = $bq['tabelName'] ?? null;
        if (is_array($tn) && $tn !== []) {
            return (string)($tn[array_key_first($tn)] ?? $tn[0]);
        }
        $op = $bq['operasi'] ?? null;
        if (is_array($op) && $op !== []) {
            return (string)array_key_first($op);
        }

        return '';
    }

    /**
     * @param array<int, mixed> $line
     * @param array<int, mixed> $headers
     * @param array<int, string> $headerMap index kolom → db column
     * @return array<string, scalar|null>
     */
    private static function rowToColumns(array $line, array $headers, array $headerMap): array
    {
        $columns = [];
        foreach ($headerMap as $colIdx => $dbCol) {
            if (!array_key_exists($colIdx, $line)) {
                continue;
            }
            $val = $line[$colIdx];
            if ($val === null) {
                continue;
            }
            if (is_string($val)) {
                $val = trim(self::stripCellBom($val));
            }
            if ($val === '') {
                continue;
            }
            $columns[$dbCol] = is_scalar($val) ? $val : null;
        }

        return $columns;
    }

    /** @return non-empty-string|false */
    private static function decodeBase64Payload(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return false;
        }
        if (str_starts_with($raw, 'data:')) {
            $pos = stripos($raw, 'base64,');
            if ($pos !== false) {
                $raw = substr($raw, $pos + 7);
            }
        }
        $decoded = base64_decode($raw, true);

        return $decoded === false ? false : $decoded;
    }

    private static function loadSpreadsheet(string $path, string $filename, string $delimiter): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === '') {
            $reader = new CsvReader();
            $reader->setDelimiter($delimiter);
            $reader->setEnclosure('"');
            $reader->setEscapeCharacter('\\');
            $reader->setInputEncoding('UTF-8');

            return $reader->load($path);
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }
}
