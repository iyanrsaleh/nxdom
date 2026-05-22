<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel controllers - Sesuai development (1).sql
 * Dipakai oleh MainController->bankData() untuk akses user
 */
class CreateControllersTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('controllers', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('userid', 'VARCHAR(11) DEFAULT NULL');
            $table->column('categori', 'VARCHAR(150) DEFAULT NULL');
            $table->column('label', 'VARCHAR(250) DEFAULT NULL');
            $table->column('version', 'VARCHAR(25) DEFAULT NULL');
            $table->column('status', 'VARCHAR(11) DEFAULT NULL');
            $table->column('approval', 'VARCHAR(11) DEFAULT NULL');
            $table->column('acinsert', 'VARCHAR(11) DEFAULT NULL');
            $table->column('acdelete', 'VARCHAR(11) DEFAULT NULL');
            $table->column('acupdate', 'VARCHAR(11) DEFAULT NULL');
            $table->column('all', 'VARCHAR(11) DEFAULT NULL');
            $table->column('appname', 'VARCHAR(125) DEFAULT NULL');
            $table->column('appid', 'VARCHAR(230) DEFAULT NULL');
            $table->column('appicon', 'VARCHAR(25) DEFAULT NULL');
            $table->column('data', 'JSON DEFAULT NULL');
            $table->column('updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            $table->column('kabupaten', 'VARCHAR(50) DEFAULT NULL');
            $table->column('kecamatan', 'VARCHAR(50) DEFAULT NULL');
            $table->column('provinsi', 'VARCHAR(11) DEFAULT NULL');
            $table->column('desa', 'VARCHAR(50) DEFAULT NULL');
            $table->index('categori');
            $table->index('userid');
        });
    }

    public function down(): void
    {
        $this->dropTable('controllers');
    }
}
