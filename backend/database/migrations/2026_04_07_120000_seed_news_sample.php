<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;

/**
 * Isi contoh tabel `news` bila kosong — agar halaman blog (Storage().example().news()) punya data.
 */
class SeedNewsSample extends NexaMigration
{
    public function up(): void
    {
        if (!$this->tableExists('news')) {
            return;
        }

        $stmt = $this->db->query('SELECT COUNT(*) FROM `news`');
        if ($stmt === false) {
            return;
        }
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $this->execute(
            'INSERT INTO `news` (`userid`, `nama`, `title`, `deskripsi`, `images`, `row`, `slug`, `categori`, `pubdate`, `status`, `dilihat`) VALUES '
                . "(?, ?, ?, ?, ?, '1', ?, ?, ?, ?, ?), "
                . '(?, ?, ?, ?, ?, \'1\', ?, ?, ?, ?, ?)',
            [
                '1',
                'Admin',
                'Memulai dengan Nexa Dom',
                'Panduan singkat untuk pengembang yang baru memakai framework.',
                'logo.png',
                'memulai-nexa-dom',
                'berita',
                '2026-04-01',
                'publish',
                0,
                '1',
                'Admin',
                'Routing dan Storage',
                'Ringkasan routing frontend dan API Outside.',
                'logo.png',
                'routing-dan-storage',
                'berita',
                '2026-04-05',
                'publish',
                0,
            ]
        );
    }

    public function down(): void
    {
        if (!$this->tableExists('news')) {
            return;
        }
        $this->execute(
            "DELETE FROM `news` WHERE `slug` IN ('memulai-nexa-dom', 'routing-dan-storage')"
        );
    }
}
