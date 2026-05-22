<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;

/**
 * Tambah kolom app_id ke license_devices
 * agar satu license_key tidak bisa dipakai di aplikasi berbeda.
 */
class AddAppIdToLicenseDevices extends NexaMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `license_devices` ADD COLUMN `app_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Nama app dari package.json' AFTER `device_id`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `license_devices` DROP COLUMN `app_id`");
    }
}
