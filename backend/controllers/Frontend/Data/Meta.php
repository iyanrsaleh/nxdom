<?php
declare(strict_types=1);
namespace App\Controllers\Frontend\Data;

use App\System\NexaController;

/**
 * Data layer untuk meta tags (sinkron dengan indeks dokumentasi di Search).
 */
class meta extends NexaController
{
    /**
     * Meta tags untuk halaman docs: mengisi dari entri Search yang cocok dengan current_path.
     *
     * @param array $params Parameter route (berisi current_path, dll.)
     * @return array meta_title, meta_description, meta_keywords, meta_image
     */
    public function tags(array $params = []): array
    {
        $doc = $this->useData('Search', 'list', [$params]);

        $defaults = [
            'meta_title' => 'Nexa Dom Framework',
            'meta_description' => 'Satu halaman untuk menyiapkan terminal, menyelaraskan database, dan menghasilkan controller — tanpa membuka banyak tab dokumentasi.',
            'meta_keywords' => 'nexa, nexa dom framework, framework php, nexa cli, migrate, make, controller, terminal, database',
            'meta_image' => $this->url('drive/images/logo.png'),
        ];

        if (!is_array($doc) || $doc === [] || !isset($doc['title'])) {
            return $defaults;
        }

        $row = $doc;
        $title = trim((string) ($row['title'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $group = trim((string) ($row['group'] ?? ''));

        $metaTitle = $title !== ''
            ? $title . ' — Nexa Dom'
            : $defaults['meta_title'];

        $metaDescription = $description !== ''
            ? $description
            : $defaults['meta_description'];

        $keywordParts = [];
        if ($title !== '') {
            $keywordParts[] = $title;
        }
        if ($description !== '') {
            foreach (preg_split('/\s*,\s*/', $description) as $chunk) {
                $chunk = trim($chunk);
                if ($chunk !== '') {
                    $keywordParts[] = $chunk;
                }
            }
        }
        if ($group !== '') {
            $keywordParts[] = $group;
        }
        $keywordParts[] = 'nexa dom framework';
        $keywordParts[] = 'dokumentasi';

        $metaKeywords = implode(', ', array_unique($keywordParts));

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'meta_image' => $defaults['meta_image'],
        ];
    }
}
