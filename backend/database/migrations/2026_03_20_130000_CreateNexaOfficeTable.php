<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel nexa_office - Sesuai development.sql
 * Dipakai oleh MainController->bankData()
 */
class CreateNexaOfficeTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('nexa_office', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('user_id', 'INT(11) NOT NULL');
            $table->column('data_type', 'VARCHAR(100) NOT NULL');
            $table->column('authorization', 'VARCHAR(250) DEFAULT NULL');
            $table->column('version', 'VARCHAR(25) DEFAULT NULL');
            $table->column('title', 'VARCHAR(255) DEFAULT NULL');
            $table->column('description', 'TEXT DEFAULT NULL');
            $table->column('status', 'VARCHAR(100) DEFAULT NULL');
            $table->column('metadata', 'JSON DEFAULT NULL');
            $table->column('created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
            $table->column('updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            $table->column('data_key', 'VARCHAR(200) DEFAULT NULL');
            $table->column('data_value', 'JSON DEFAULT NULL');
            $table->column('to_id', 'VARCHAR(125) DEFAULT NULL');
            $table->column('navigasi', 'VARCHAR(25) DEFAULT NULL');
            $table->column('appname', 'VARCHAR(25) DEFAULT NULL');
            $table->column('icon', 'VARCHAR(55) DEFAULT NULL');
            $table->column('userid', 'VARCHAR(11) DEFAULT NULL');
            $table->index('data_type');
        });

        // Data default agar MainController->bankData() tidak error
        $this->execute(
            "INSERT INTO `nexa_office` (user_id, data_type, data_key, title, version, description, data_value) VALUES
            (1, 'System', 'version', NULL, NULL, NULL, NULL),
            (1, 'Apps', NULL, 'NexaUI', '1.0.0', 'NexaUI Framework', '{}')"
        );
    }

    public function down(): void
    {
        $this->dropTable('nexa_office');
    }
}
