<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Tabel package - Daftar akses/modul yang dapat diberikan ke user
 * Kolom development: 1 = system, 2 = public, 3 = hidden (tidak di menu / Kelola Package)
 */
class CreatePackageTable extends NexaMigration
{
    public function up(): void
    {
        $this->createTable('package', function (NexaSchema $table) {
            $table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->column('key', 'VARCHAR(50) NOT NULL COMMENT \'Slug untuk URL dan user.package\'');
            $table->column('label', 'VARCHAR(100) NOT NULL');
            $table->column('icon', 'VARCHAR(50) DEFAULT \'fas fa-circle\'');
            $table->column('sort_order', 'INT(11) DEFAULT 0');
            $table->column('url', 'VARCHAR(150) DEFAULT NULL COMMENT \'Path relatif, kosong=gunakan key\'');
            $table->column('development', 'INT(11) NOT NULL DEFAULT 2 COMMENT \'1=system,2=public,3=hidden\'');
            $table->unique('key');
        });
        $this->execute("INSERT INTO `package` (`key`, label, icon, sort_order, development) VALUES
            ('package', 'Kelola Package', 'fas fa-key', 0, 1),
            ('user', 'User Management', 'fas fa-users', 10, 1),
            ('theme', 'Theme', 'fas fa-palette', 20, 1),
            ('distro', 'Distro', 'fas fa-box-open', 25, 1),
            ('postingan', 'Postingan', 'fas fa-newspaper', 25, 2),
            ('development', 'Development', 'fas fa-code-branch', 25, 2),
            ('example', 'Example Pages', 'fas fa-code', 30, 2)");
    }

    public function down(): void
    {
        $this->dropTable('package');
    }
}
