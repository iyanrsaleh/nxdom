<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * Model tabel package — daftar modul akses & menu dinamis.
 *
 * Dipanggil dari controller via useModels('Package', 'getOptions', []).
 */
class Package extends NexaModel
{
    protected $table = 'package';

    /**
     * Daftar package dari DB (untuk akses kontrol & menu).
     * Fallback ke getDefaults() jika tabel kosong / error.
     * Query via Storage() + select + orderBy (models/NexaModel.md).
     * Kolom `key` reserved: select `tabel.key AS pkg_key`, lalu map ke `key` (AS key memicu error SQL).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        try {
            $t = $this->table;
            $rows = $this->Storage($this->table)
                ->select([
                    'id',
                    $t . '.key AS pkg_key',
                    'label',
                    'icon',
                    'sort_order',
                    'development',
                    'url',
                ])
                ->orderBy('sort_order', 'ASC')
                ->get();

            if (!is_array($rows) || $rows === []) {
                return $this->getDefaults();
            }

            foreach ($rows as $i => $row) {
                if (isset($row['pkg_key'])) {
                    $rows[$i]['key'] = $row['pkg_key'];
                    unset($rows[$i]['pkg_key']);
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            return $this->getDefaults();
        }
    }

    /**
     * Default package jika tabel belum ada atau tidak bisa dibaca.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaults(): array
    {
        return self::defaultRows();
    }

    /**
     * Baris default (tanpa koneksi DB) — dipakai fallback controller jika useModels gagal.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        return [
            [
             'id' => 0, 
            'key' => 'example', 
            'label' => 'Example Pages', 
            'icon' => 'fas fa-code', 
            'sort_order' => 30, 
            'development' => 2
         ],
        ];
    }
}
