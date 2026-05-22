<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Standar Beasiswa: Tabel user
 * Referensi: database/beasiswa.sql
 */
class CreateUserTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('user', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('status', 'VARCHAR(25) DEFAULT NULL');
            $table->column('nama', 'VARCHAR(100) DEFAULT NULL');
            $table->column('instansi', 'VARCHAR(250) DEFAULT NULL');
            $table->column('jabatan', 'VARCHAR(100) DEFAULT NULL');
            $table->column('role', 'VARCHAR(25) DEFAULT NULL');
            $table->column('email', 'VARCHAR(50) DEFAULT NULL');
            $table->column('password', 'VARCHAR(255) DEFAULT NULL');
            $table->column('telepon', 'VARCHAR(25) DEFAULT NULL');
            $table->column('alamat', 'VARCHAR(250) DEFAULT NULL');
            $table->column('avatar', 'VARCHAR(250) DEFAULT NULL');
            $table->column('package', 'VARCHAR(250) DEFAULT NULL');
            $table->column('gender', 'VARCHAR(25) DEFAULT NULL');
            $table->column('token', 'VARCHAR(250) DEFAULT NULL');
            $table->column('expired', 'VARCHAR(25) DEFAULT NULL');
            $table->column('row', "ENUM('1') NOT NULL");
            $table->column('provinsi', 'VARCHAR(100) DEFAULT NULL');
            $table->column('kabupaten', 'VARCHAR(100) DEFAULT NULL');
            $table->column('kecamatan', 'VARCHAR(100) DEFAULT NULL');
            $table->column('desa', 'VARCHAR(100) DEFAULT NULL');
            $table->column('nik', 'VARCHAR(25) DEFAULT NULL');
            $table->column('login_time', 'INT UNSIGNED DEFAULT NULL COMMENT \'Unix timestamp saat login\'');
            $table->column('last_activity', 'INT UNSIGNED DEFAULT NULL COMMENT \'Unix timestamp aktivitas terakhir\'');
            $table->column('last_seen', 'DATETIME DEFAULT NULL');
            $table->column('is_online', 'TINYINT(1) DEFAULT 0');
            $table->column('online_status', "ENUM('online','away','offline') DEFAULT 'offline'");
            $table->column('last_ip', 'VARCHAR(45) DEFAULT NULL');
            $table->column('session_id', 'VARCHAR(100) DEFAULT NULL');
            $table->index('login_time');
            $table->index('last_activity');
            $table->index('last_seen');
            $table->index('is_online');
        });
    }

    public function down(): void
    {
        $this->dropTable('user');
    }
}
