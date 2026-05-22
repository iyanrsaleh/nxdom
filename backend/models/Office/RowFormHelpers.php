<?php
declare(strict_types=1);

namespace App\Models\Office;

/**
 * Pemvalidasi konten formulir/`$columns` bagi jalur Insert/Update STANDAR Office.
 */
final class RowFormHelpers
{
    /** Minimal satu field non-sistem bernilai “berarti”. */
    public static function hasValidFormData(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $validFields = 0;
        $excludeFields = ['userid', 'id', 'created_at', 'updated_at', 'row'];

        foreach ($data as $field => $value) {
            if (in_array($field, $excludeFields, true)) {
                continue;
            }
            if (self::isValidFieldValue($value)) {
                $validFields++;
            }
        }

        return $validFields > 0;
    }

    public static function isValidFieldValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        $stringValue = trim((string)$value);

        $emptyValues = [
            '', 'NULL', 'null', 'Null',
            '-', '--', '---',
            'N/A', 'n/a', 'NA', 'na',
            '0', '0.0', '0,0',
        ];

        if (in_array($stringValue, $emptyValues, true)) {
            return false;
        }

        if (preg_match('/^[\s\-_.,;:]*$/', $stringValue) === 1) {
            return false;
        }

        return strlen($stringValue) > 0;
    }
}
