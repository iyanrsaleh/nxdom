<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel news — struktur mengikuti dump `database/migrations/news.sql`.
 * Data sample tetap di news.sql (import manual bila perlu).
 */
class CreateNewsTable extends NexaMigration
{
    public function up(): void
    {
        if ($this->tableExists('news')) {
            return;
        }

        $this->createTable('news', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('userid', 'VARCHAR(11) DEFAULT NULL');
            $table->column('nama', 'VARCHAR(250) DEFAULT NULL');
            $table->column('title', 'VARCHAR(100) DEFAULT NULL');
            $table->column('deskripsi', 'LONGTEXT DEFAULT NULL');
            $table->column('images', 'VARCHAR(250) DEFAULT NULL');
            $table->column('row', "ENUM('1') NOT NULL");
            $table->column('slug', 'VARCHAR(250) DEFAULT NULL');
            $table->column('categori', 'VARCHAR(150) DEFAULT NULL');
            $table->column('pubdate', 'VARCHAR(50) DEFAULT NULL');
            $table->column('thumbnails', 'JSON DEFAULT NULL');
            $table->column('keywords', 'VARCHAR(150) DEFAULT NULL');
            $table->column('detail', 'LONGTEXT DEFAULT NULL');
            $table->column('created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
            $table->column('updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            $table->column('status', 'VARCHAR(25) DEFAULT NULL');
            $table->column('dilihat', 'INT(11) DEFAULT NULL');
        });
    }

    public function down(): void
    {
        $this->dropTable('news');
    }
}
