<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel license_devices
 * Menyimpan device yang terdaftar per license key.
 * device_id = SHA-256(hostname::MAC::platform) — dibuat di Electron main process.
 */
class CreateLicenseDevicesTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('license_devices', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('license_id', 'INT(11) NOT NULL');
            $table->column('device_id', 'VARCHAR(64) NOT NULL COMMENT \'SHA-256 hex\'');
            $table->column('app_id', 'VARCHAR(100) NOT NULL DEFAULT \'\' COMMENT \'Nama app dari package.json\'');
            $table->column('registered_at', 'INT UNSIGNED NOT NULL');
            $table->column('last_seen_at', 'INT UNSIGNED NOT NULL');
            $table->index('license_id');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        $this->dropTable('license_devices');
    }
}
