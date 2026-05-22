<?php
declare(strict_types=1);
namespace App\System\Helpers;

/**
 * NexaLicense — License key generator & validator.
 *
 * Usage:
 *   $key   = NexaLicense::generate($userId);              // NEXA-A7KM-3QV9-H2RB-5WFT
 *   $valid = NexaLicense::validate('NEXA-A7KM-3QV9-H2RB-5WFT');
 *   $uid   = NexaLicense::resolveUserId('NEXA-A7KM-3QV9-H2RB-5WFT', $candidateId);
 */
class NexaLicense
{
    /** Salt rahasia — ganti di production */
    private const SALT = 'NEXA-LICENSE-SALT-';

    /** Charset campuran huruf + angka, tanpa O/0/I/L agar tidak ambigu */
    private const CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Generate license key deterministik berdasarkan user ID.
     * User ID yang sama selalu menghasilkan key yang sama.
     * Format: NEXA-XXXX-XXXX-XXXX-XXXX
     */
    public static function generate(int $userId): string
    {
        $charset = self::CHARSET;
        $base    = strlen($charset);
        $hash    = hash('sha256', self::SALT . $userId);

        $raw = '';
        for ($i = 0; $i < 16; $i++) {
            $byte  = hexdec(substr($hash, $i * 2, 2));
            $raw  .= $charset[$byte % $base];
        }

        return 'NEXA-' . implode('-', str_split($raw, 4));
    }

    /**
     * Validasi format license key (tidak mengecek ke DB).
     * Format valid: NEXA-XXXX-XXXX-XXXX-XXXX (22 karakter)
     */
    public static function validate(string $key): bool
    {
        return (bool) preg_match('/^NEXA-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $key);
    }

    /**
     * Cek apakah key cocok dengan user ID tertentu.
     */
    public static function matches(string $key, int $userId): bool
    {
        return hash_equals(self::generate($userId), $key);
    }
}
