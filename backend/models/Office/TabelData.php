<?php
declare(strict_types=1);
namespace App\Models\Office;
use App\System\NexaModel;

/**
 * TabelData — ambil rows dari tabel berdasarkan tableKey dengan pagination
 */
class TabelData extends NexaModel
{
    /**
     * Ambil data rows dari tabel dengan pagination
     *
     * @param array $data {key, name, limit, offset, orderBy, orderDir}
     * @return array {success, table, data, total, limit, offset}
     */
    public function getRows(array $data): array
    {
        try {
            $key      = (int)($data['key']      ?? 0);
            $limit    = min((int)($data['limit']  ?? 50), 500);
            $offset   = (int)($data['offset']   ?? 0);
            $orderBy  = $data['orderBy']  ?? 'id';
            $orderDir = strtoupper($data['orderDir'] ?? 'DESC');
            $orderDir = in_array($orderDir, ['ASC','DESC'], true) ? $orderDir : 'DESC';
            $search   = trim($data['search'] ?? '');

            if ($key === 0) {
                return ['success' => false, 'message' => 'key wajib diisi'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) {
                return ['success' => false, 'message' => "Tabel dengan key '{$key}' tidak ditemukan"];
            }

            // Pastikan kolom orderBy ada di tabel; fallback ke kolom pertama
            $describe = $this->raw("DESCRIBE `{$tableName}`");
            $columns  = array_column($describe ?? [], 'Field');
            if (!in_array($orderBy, $columns, true)) {
                $orderBy = $columns[0] ?? null;
            }

            // Ambil rows
            $query = $this->Storage($tableName)->limit($limit)->offset($offset);
            if ($orderBy) {
                $query = $query->orderBy($orderBy, $orderDir);
            }

            // Terapkan filter pencarian ke semua kolom (OR LIKE)
            if ($search !== '' && !empty($columns)) {
                $like = '%' . $search . '%';
                $query = $query->where($columns[0], 'LIKE', $like);
                for ($i = 1; $i < count($columns); $i++) {
                    $query = $query->orWhere($columns[$i], 'LIKE', $like);
                }
            }

            $rows = $query->get();

            // Hitung total (dengan filter yang sama jika ada pencarian)
            if ($search !== '' && !empty($columns)) {
                $like  = '%' . $search . '%';
                $countQ = $this->Storage($tableName)->where($columns[0], 'LIKE', $like);
                for ($i = 1; $i < count($columns); $i++) {
                    $countQ = $countQ->orWhere($columns[$i], 'LIKE', $like);
                }
                $total = (int) $countQ->count();
            } else {
                $total = (int) $this->Storage($tableName)->count();
            }

            return [
                'success' => true,
                'table'   => $tableName,
                'data'    => $rows  ?: [],
                'total'   => $total,
                'limit'   => $limit,
                'offset'  => $offset,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update satu row berdasarkan id
     *
     * @param array $data {key, id, fields: {kolom: nilai}}
     */
    public function updateRow(array $data): array
    {
        try {
            $key    = (int)($data['key'] ?? 0);
            $id     = $data['id']     ?? null;
            $fields = $data['fields'] ?? [];

            if (!$key || $id === null || empty($fields)) {
                return ['success' => false, 'message' => 'key, id, dan fields wajib diisi'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) {
                return ['success' => false, 'message' => "Tabel dengan key '{$key}' tidak ditemukan"];
            }

            // Pastikan hanya kolom yang benar-benar ada di tabel yang di-update
            $describe      = $this->raw("DESCRIBE `{$tableName}`");
            $validColumns  = array_column($describe ?? [], 'Field');
            $safeFields    = array_intersect_key($fields, array_flip($validColumns));

            if (empty($safeFields)) {
                return ['success' => false, 'message' => 'Tidak ada kolom valid untuk di-update'];
            }

            $this->Storage($tableName)->where('id', $id)->update($safeFields);

            return ['success' => true, 'table' => $tableName, 'id' => $id];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Hapus satu row berdasarkan id
     *
     * @param array $data {key, id}
     */
    public function deleteRow(array $data): array
    {
        try {
            $key = (int)($data['key'] ?? 0);
            $id  = $data['id'] ?? null;

            if (!$key || $id === null) {
                return ['success' => false, 'message' => 'key dan id wajib diisi'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) {
                return ['success' => false, 'message' => "Tabel dengan key '{$key}' tidak ditemukan"];
            }

            $this->Storage($tableName)->where('id', $id)->delete();

            return ['success' => true, 'table' => $tableName, 'id' => $id];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Salin tabel ke nama baru
     *
     * @param array $data {key, dest, mode, opts[]}
     *   mode: structure_only | structure_data | data_only
     *   opts: add_drop | auto_increment | constraints | privileges | switch
     */
    public function copyTable(array $data): array
    {
        try {
            $key  = (int)($data['key']  ?? 0);
            $dest = trim($data['dest']  ?? '');
            $mode = $data['mode'] ?? 'structure_data';
            $opts = (array)($data['opts'] ?? []);

            if (!$key) {
                return ['success' => false, 'message' => 'key wajib diisi'];
            }
            if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', $dest)) {
                return ['success' => false, 'message' => 'Nama tabel tujuan tidak valid'];
            }

            $srcTable = $this->tablesIndex($key);
            if (!$srcTable) {
                return ['success' => false, 'message' => "Tabel sumber tidak ditemukan"];
            }

            // Pisahkan database.tabel jika ada titik
            $parts    = explode('.', $dest, 2);
            $destDb   = count($parts) === 2 ? $parts[0] : null;
            $destTable = count($parts) === 2 ? $parts[1] : $parts[0];

            $destFull  = $destDb
                ? "`{$destDb}`.`{$destTable}`"
                : "`{$destTable}`";

            $sql = [];

            if (in_array('add_drop', $opts, true)) {
                $sql[] = "DROP TABLE IF EXISTS {$destFull};";
            }

            if ($mode === 'data_only') {
                // Tabel harus sudah ada; hanya salin data
                $sql[] = "INSERT INTO {$destFull} SELECT * FROM `{$srcTable}`;";
            } else {
                // Buat struktur tabel baru
                $createRows = $this->raw("SHOW CREATE TABLE `{$srcTable}`");
                if (empty($createRows)) {
                    return ['success' => false, 'message' => 'Gagal membaca struktur tabel sumber'];
                }
                $createSql = $createRows[0]['Create Table'] ?? '';

                // Ganti nama tabel sumber → tujuan
                $createSql = preg_replace(
                    '/CREATE TABLE `[^`]+`/',
                    "CREATE TABLE {$destFull}",
                    $createSql
                );

                // Hapus AUTO_INCREMENT value jika tidak diinginkan
                if (!in_array('auto_increment', $opts, true)) {
                    $createSql = preg_replace('/\s*AUTO_INCREMENT=\d+/', '', $createSql);
                }

                // Hapus constraints (FOREIGN KEY) jika tidak diinginkan
                if (!in_array('constraints', $opts, true)) {
                    $createSql = preg_replace('/,\s*CONSTRAINT[^,]+/i', '', $createSql);
                    $createSql = preg_replace('/,\s*FOREIGN KEY[^,)]+/i', '', $createSql);
                }

                $sql[] = $createSql . ';';

                // Salin data jika mode bukan structure_only
                if ($mode === 'structure_data') {
                    $sql[] = "INSERT INTO {$destFull} SELECT * FROM `{$srcTable}`;";
                }
            }

            // Jalankan semua SQL
            foreach ($sql as $statement) {
                if (trim($statement)) {
                    $this->raw($statement);
                }
            }

            // Hitung key baru untuk tabel tujuan (agar "switch" bisa bekerja)
            $newKey = hexdec(substr(md5($destTable), 0, 12));

            return [
                'success' => true,
                'src'     => $srcTable,
                'dest'    => $destTable,
                'newKey'  => $newKey,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Buat trigger baru pada tabel
     *
     * @param array $data {key, name, time, event, definition, definer}
     *   time:  BEFORE | AFTER
     *   event: INSERT | UPDATE | DELETE
     */
    public function createTrigger(array $data): array
    {
        try {
            $key        = (int)($data['key']        ?? 0);
            $name       = trim($data['name']        ?? '');
            $time       = strtoupper(trim($data['time']  ?? ''));
            $event      = strtoupper(trim($data['event'] ?? ''));
            $definition = trim($data['definition']  ?? '');
            $definer    = trim($data['definer']     ?? '');

            if (!$key) {
                return ['success' => false, 'message' => 'key wajib diisi'];
            }
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                return ['success' => false, 'message' => 'Nama trigger tidak valid (huruf, angka, underscore)'];
            }
            if (!in_array($time, ['BEFORE', 'AFTER'], true)) {
                return ['success' => false, 'message' => 'Time harus BEFORE atau AFTER'];
            }
            if (!in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true)) {
                return ['success' => false, 'message' => 'Event harus INSERT, UPDATE, atau DELETE'];
            }
            if ($definition === '') {
                return ['success' => false, 'message' => 'Definition tidak boleh kosong'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) {
                return ['success' => false, 'message' => "Tabel dengan key '{$key}' tidak ditemukan"];
            }

            // Bangun SQL CREATE TRIGGER
            $definerClause = '';
            if ($definer !== '' && preg_match('/^`?[a-zA-Z0-9_%@.]+`?(@`?[a-zA-Z0-9_%]+`?)?$/', $definer)) {
                $definerClause = "DEFINER={$definer} ";
            }

            $sql = "CREATE {$definerClause}TRIGGER `{$name}` {$time} {$event} ON `{$tableName}` FOR EACH ROW BEGIN {$definition} END";

            $this->raw($sql);

            return [
                'success' => true,
                'trigger' => $name,
                'table'   => $tableName,
                'time'    => $time,
                'event'   => $event,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Ambil struktur kolom tabel (SHOW FULL COLUMNS)
     *
     * @param array $data {key}
     * @return array {success, table, columns[]}
     */
    public function getStructure(array $data): array
    {
        try {
            $key = (int)($data['key'] ?? 0);
            if (!$key) return ['success' => false, 'message' => 'key wajib diisi'];

            $tableName = $this->tablesIndex($key);
            if (!$tableName) return ['success' => false, 'message' => "Tabel tidak ditemukan"];

            $rows = $this->raw("SHOW FULL COLUMNS FROM `{$tableName}`");
            $no   = 1;
            $columns = array_map(function ($col) use (&$no) {
                return [
                    'no'        => $no++,
                    'name'      => $col['Field'],
                    'type'      => $col['Type'],
                    'collation' => $col['Collation'] ?? '',
                    'null'      => strtoupper($col['Null'] ?? 'YES') === 'YES',
                    'key'       => $col['Key']     ?? '',
                    'default'   => $col['Default'],
                    'extra'     => $col['Extra']   ?? '',
                    'comment'   => $col['Comment'] ?? '',
                ];
            }, $rows ?? []);

            return ['success' => true, 'table' => $tableName, 'columns' => $columns];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Ubah definisi kolom (ALTER TABLE … CHANGE)
     *
     * @param array $data {key, column, newName, type, length, null, default, extra, comment}
     */
    public function alterColumn(array $data): array
    {
        try {
            $key     = (int)($data['key']    ?? 0);
            $column  = trim($data['column']  ?? '');
            $newName = trim($data['newName'] ?? '') ?: $column;
            $type    = strtoupper(trim($data['type'] ?? ''));
            $length  = trim($data['length'] ?? '');
            $null    = (bool)($data['null']  ?? true);
            $default = isset($data['default']) && $data['default'] !== '' ? (string)$data['default'] : null;
            $extra   = strtoupper(trim($data['extra'] ?? ''));
            $comment = trim($data['comment'] ?? '');

            if (!$key || !$column || !$type) {
                return ['success' => false, 'message' => 'key, column, dan type wajib diisi'];
            }

            $allowedTypes = [
                'INT','BIGINT','SMALLINT','TINYINT','MEDIUMINT',
                'FLOAT','DOUBLE','DECIMAL',
                'VARCHAR','CHAR','TEXT','LONGTEXT','MEDIUMTEXT','TINYTEXT',
                'DATE','DATETIME','TIMESTAMP','TIME','YEAR',
                'ENUM','SET','JSON','BOOLEAN','BOOL',
                'BLOB','LONGBLOB','MEDIUMBLOB','TINYBLOB',
            ];
            if (!in_array($type, $allowedTypes, true)) {
                return ['success' => false, 'message' => "Tipe kolom tidak didukung"];
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) ||
                !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $newName)) {
                return ['success' => false, 'message' => 'Nama kolom tidak valid'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) return ['success' => false, 'message' => "Tabel tidak ditemukan"];

            // Build type definition
            $typeDef = $type;
            if ($length !== '' && preg_match('/^[\w\d,\'\s]+$/', $length)) {
                $typeDef .= "({$length})";
            }

            $nullDef    = $null ? 'NULL' : 'NOT NULL';
            $defaultDef = '';
            if ($default !== null) {
                $defaultDef = " DEFAULT '" . addslashes($default) . "'";
            } elseif ($null) {
                $defaultDef = ' DEFAULT NULL';
            }

            $allowedExtras = ['', 'AUTO_INCREMENT', 'ON UPDATE CURRENT_TIMESTAMP'];
            $extraDef = (in_array($extra, $allowedExtras, true) && $extra !== '') ? " {$extra}" : '';

            $commentDef = $comment !== '' ? " COMMENT '" . addslashes($comment) . "'" : '';

            $sql = "ALTER TABLE `{$tableName}` CHANGE `{$column}` `{$newName}` {$typeDef} {$nullDef}{$defaultDef}{$extraDef}{$commentDef}";
            $this->raw($sql);

            return ['success' => true, 'table' => $tableName, 'column' => $newName];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Hapus kolom dari tabel (ALTER TABLE … DROP COLUMN)
     *
     * @param array $data {key, column}
     */
    public function dropColumn(array $data): array
    {
        try {
            $key    = (int)($data['key']    ?? 0);
            $column = trim($data['column'] ?? '');

            if (!$key || !$column) return ['success' => false, 'message' => 'key dan column wajib diisi'];
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                return ['success' => false, 'message' => 'Nama kolom tidak valid'];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) return ['success' => false, 'message' => "Tabel tidak ditemukan"];

            $this->raw("ALTER TABLE `{$tableName}` DROP COLUMN `{$column}`");

            return ['success' => true, 'table' => $tableName, 'column' => $column];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * List semua trigger pada database (atau filter by tabel).
     *
     * @param array $data {key?: int, table?: string}
     * @return array
     */
    public function listTriggers(array $data): array
    {
        try {
            $filterTable = trim($data['table'] ?? '');

            // Kalau ada key, resolve ke nama tabel
            if (empty($filterTable) && !empty($data['key'])) {
                $resolved = $this->tablesIndex((int)$data['key']);
                if ($resolved) $filterTable = $resolved;
            }

            $sql    = "SHOW TRIGGERS";
            $params = [];
            if ($filterTable !== '') {
                $sql    = "SHOW TRIGGERS LIKE ?";
                $params = [$filterTable];
            }

            $rows = $this->raw($sql, $params);
            if (!is_array($rows)) $rows = [];

            $result = array_map(fn($r) => [
                'name'    => $r['Trigger']    ?? '',
                'event'   => $r['Event']      ?? '',
                'table'   => $r['Table']      ?? '',
                'timing'  => $r['Timing']     ?? '',
                'statement' => $r['Statement'] ?? '',
                'definer' => $r['Definer']    ?? '',
            ], $rows);

            return ['success' => true, 'triggers' => $result, 'count' => count($result)];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Drop trigger berdasarkan nama.
     *
     * @param array $data {name: string}
     * @return array
     */
    public function dropTrigger(array $data): array
    {
        try {
            $name = trim($data['name'] ?? '');
            if (!$name) return ['success' => false, 'message' => 'Nama trigger wajib diisi'];

            // Validate: hanya huruf, angka, underscore
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                return ['success' => false, 'message' => 'Nama trigger tidak valid'];
            }

            $this->raw("DROP TRIGGER IF EXISTS `{$name}`");
            return ['success' => true, 'dropped' => $name];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Operasi tabel: truncate, delete_from, drop
     *
     * @param array $data {key, operation: 'truncate'|'delete_from'|'drop'}
     */
    public function tableOperation(array $data): array
    {
        try {
            $key       = (int)($data['key']       ?? 0);
            $operation = strtolower(trim($data['operation'] ?? ''));

            if (!$key) return ['success' => false, 'message' => 'key wajib diisi'];

            $allowedOps = ['truncate', 'delete_from', 'drop'];
            if (!in_array($operation, $allowedOps, true)) {
                return ['success' => false, 'message' => "Operasi tidak valid"];
            }

            $tableName = $this->tablesIndex($key);
            if (!$tableName) return ['success' => false, 'message' => "Tabel tidak ditemukan"];

            switch ($operation) {
                case 'truncate':
                    $this->raw("TRUNCATE TABLE `{$tableName}`");
                    return ['success' => true, 'operation' => 'truncate', 'table' => $tableName];
                case 'delete_from':
                    $this->raw("DELETE FROM `{$tableName}`");
                    return ['success' => true, 'operation' => 'delete_from', 'table' => $tableName];
                case 'drop':
                    $this->raw("DROP TABLE `{$tableName}`");
                    return ['success' => true, 'operation' => 'drop', 'table' => $tableName];
            }

            return ['success' => false, 'message' => 'Operasi tidak diproses'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Eksekusi query SQL bebas (SELECT/INSERT/UPDATE/DELETE/SHOW/DESCRIBE).
     * Untuk keamanan, hanya satu statement yang diizinkan per panggilan
     * dan DDL berbahaya (DROP TABLE, TRUNCATE, ALTER TABLE, CREATE TABLE) diblokir.
     *
     * @param array $data {sql: string, limit?: int}
     * @return array {success, columns[], rows[], affected, time_ms}
     */
    public function executeQuery(array $data): array
    {
        try {
            $sql   = trim($data['sql'] ?? '');
            $limit = min((int)($data['limit'] ?? 1000), 5000);

            if ($sql === '') {
                return ['success' => false, 'message' => 'SQL tidak boleh kosong'];
            }

            // Tolak multi-statement (tanda ; di tengah kalimat)
            if (substr_count(rtrim($sql, '; '), ';') > 0) {
                return ['success' => false, 'message' => 'Hanya satu statement per eksekusi'];
            }

            // Blokir DDL berbahaya
            $upperSql = strtoupper(ltrim($sql));
            $blocked = ['DROP TABLE', 'TRUNCATE', 'ALTER TABLE', 'CREATE TABLE', 'RENAME TABLE'];
            foreach ($blocked as $b) {
                if (str_starts_with($upperSql, $b)) {
                    return ['success' => false, 'message' => "Statement '{$b}' tidak diizinkan melalui Query Runner"];
                }
            }

            $isSelect = preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|PRAGMA)/i', $upperSql);

            $start = microtime(true);

            if ($isSelect) {
                // Tambahkan LIMIT otomatis jika belum ada
                if (!preg_match('/\bLIMIT\b/i', $sql)) {
                    $sql .= " LIMIT {$limit}";
                }
                $rows    = $this->raw($sql);
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $columns = !empty($rows) ? array_keys($rows[0]) : [];
                return [
                    'success'  => true,
                    'type'     => 'select',
                    'columns'  => $columns,
                    'rows'     => $rows ?? [],
                    'count'    => count($rows ?? []),
                    'time_ms'  => $elapsed,
                ];
            }

            // DML: INSERT / UPDATE / DELETE
            $this->ensureConnection();
            $stmt    = $this->db->prepare($sql);
            $stmt->execute();
            $elapsed  = round((microtime(true) - $start) * 1000, 2);
            $affected = $stmt->rowCount();

            return [
                'success'  => true,
                'type'     => 'dml',
                'affected' => $affected,
                'time_ms'  => $elapsed,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
