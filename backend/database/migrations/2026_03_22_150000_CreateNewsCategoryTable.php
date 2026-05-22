<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel kategori artikel — dipakai form postingan & halaman Kategori.
 * Nilai `slug` disimpan di kolom news.categori (bukan FK, agar kompatibel data lama).
 */
class CreateNewsCategoryTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('news_category', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('slug', 'VARCHAR(80) NOT NULL COMMENT \'Nilai disimpan di news.categori\'');
            $table->column('label', 'VARCHAR(150) NOT NULL');
            $table->column('icon', "VARCHAR(80) NOT NULL DEFAULT 'fas fa-folder' COMMENT 'Font Awesome, contoh: fas fa-newspaper'");
            $table->column('sort_order', 'INT(11) NOT NULL DEFAULT 0');
            $table->unique('slug');
            $table->index('sort_order');
        });

        $this->execute("INSERT INTO `news_category` (`slug`, `label`, `icon`, `sort_order`) VALUES
            ('berita', 'Berita', 'fas fa-newspaper', 10),
            ('pengumuman', 'Pengumuman', 'fas fa-bullhorn', 20),
            ('tips', 'Tips', 'fas fa-lightbulb', 30),
            ('opini', 'Opini', 'fas fa-comment-dots', 40)");
    }

    public function down(): void
    {
        $this->dropTable('news_category');
    }
}
