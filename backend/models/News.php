<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class News extends NexaModel
{
    protected $table = 'news';
    protected $failed = 'title';
   

    private $controller = null;
    
    public function setController($controller): self {
        $this->controller = $controller;
        return $this;
    }

    /**
     * Search news by title with safe return
     * Always returns array, empty if no results found
     * 
     * @param string $search Search keyword
     * @return array Found news data or empty array
     */
    public function search(
        string $search,
        string $sort='desc',
        int $page=1,
        string $categori='',
      ): array {
        if (!empty($categori) && $categori !== 'all') {
            $failed = "categori";
            $failedSearch = $categori;
        } else {
            $failed = $this->failed;
            $failedSearch = $search;
        }
        
        $result = $this->Storage($this->table) 
            ->where($failed, 'LIKE', "%{$failedSearch}%")
           ->orderBy('id', $sort)
           ->paginate($page, 8); // halaman 2, 15 item per halaman;
        
        // ✅ Safe return: always return array
        return $result ?? [];
    }
    
    /**
     * Search news with nullable return (alternative approach)
     * Returns null if no results found
     * 
     * @param string $search Search keyword
     * @return array|null Found news data or null
     */
    public function percentage(
        string $search,
        string $sort='desc',
        string $categori='',
      ): array {
        if (!empty($categori) && $categori !== 'all') {
            $failed = "categori";
            $failedSearch = $categori;
        } else {
            $failed = $this->failed;
            $failedSearch = $search;
        }
        $countWith = $this->Storage($this->table) 
            ->where($failed, 'LIKE', "%{$failedSearch}%")
            ->countWithPercentage('status', ['publish', 'draft']);

        $dilihat = $this->Storage('news')
        ->where($failed, 'LIKE', "%{$failedSearch}%")
        ->sumColumnsWithPercentage(['dilihat']);
         return array_merge($countWith,$dilihat) ?? [];
    }
    
    /**
     * Get all news matching search criteria
     * Returns array of results
     * 
     * @param string $search Search keyword
     * @return array Array of news items
     */
    public function searchAll(string $search): array {
        $results = $this->Storage($this->table) 
            ->where($this->failed, 'LIKE', "%{$search}%")
            ->get();
        
        return $results ?? [];
    }

  public function create( array $postData): array {
        try {
            $result = $this->Storage($this->table)->insert([
               'title'      => $postData['title'] ?? '',
               'categori'   => $postData['categori'] ?? '',
               'keywords'   => $postData['keywords'] ?? '',
               'detail'     => $postData['detail'] ?? '',
               'images'     => $postData['images'] ?? '',                          // Path gambar utama
               'thumbnails' => $postData['thumbnails'] ?? '',     // Path thumbnails dalam format JSON
               'slug'       => $this->addSlug($postData['title'] ?? ''),
               'deskripsi'  => $postData['deskripsi'] ?? '',
               'status'     => $postData['status'] ?? 'draft',
               'pubdate'    => $postData['pubdate'] ?? date('Y-m-d H:i:s')
        ]);
        
            return [
                'success' => true,
                'message' => 'News created successfully',
                'data' => $result
            ];
   
        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
  public function editById(array $postData, int $id): array {
        try {
            $result = $this->Storage($this->table)->where('id', $id)->update([
               'title'      => $postData['title'] ?? '',
               'categori'   => $postData['categori'] ?? '',
               'keywords'   => $postData['keywords'] ?? '',
               'detail'     => $postData['detail'] ?? '',
               'slug'       => $this->addSlug($postData['title'] ?? ''),
               'deskripsi'  => $postData['deskripsi'] ?? '',
               'status'     => $postData['status'] ?? 'draft',
               'pubdate'    => $postData['pubdate'] ?? date('Y-m-d H:i:s')
        ]);
        
            return [
                'success' => true,
                'message' => 'News updated successfully',
                'data' => $result
            ];
   
        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

  public function updateById(array $postData, int $id): array {
        try {
            $result = $this->Storage($this->table)->where('id', $id)->update([
               'title'      => $postData['title'] ?? '',
               'categori'   => $postData['categori'] ?? '',
               'keywords'   => $postData['keywords'] ?? '',
               'detail'     => $postData['detail'] ?? '',
               'images'     => $postData['images'] ?? '',                          // Path gambar utama
               'thumbnails' => $postData['thumbnails'] ?? '',     // Path thumbnails dalam format JSON
               'slug'       => $this->addSlug($postData['title'] ?? ''),
               'deskripsi'  => $postData['deskripsi'] ?? '',
               'status'     => $postData['status'] ?? 'draft',
               'pubdate'    => $postData['pubdate'] ?? date('Y-m-d H:i:s')
        ]);
        
            return [
                'success' => true,
                'message' => 'News updated successfully',
                'data' => $result
            ];
   
        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }


    public function removeById(int $id) {
        $this->Storage($this->table)->where('id', $id)->delete();
    }
    /**
     * Get news by ID with safe return
     * 
     * @param int $id News ID
     * @return array News data or empty array
     */
    public function findById(int|null $id): array {
        if ($id === null || $id < 1) {
            return [];
        }
        $result = $this->Storage($this->table)
            ->where('id', '=', $id)
            ->first();

        return $result ?? [];
    }
    
    /**
     * Get all news with pagination support
     * 
     * @param int $limit Number of items per page
     * @param int $offset Offset for pagination
     * @return array News list
     */
    public function getAll(int $limit = 10, int $offset = 0): array {
        $results = $this->Storage($this->table) 
            ->limit($limit)
            ->offset($offset)
            ->get();
        
        return $results ?? [];
    }
    
    /**
     * Get recent news
     * 
     * @param int $limit Number of recent news to get
     * @return array Recent news list
     */
    public function getRecent(int $limit = 5): array {
        $results = $this->Storage($this->table) 
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
        
        return $results ?? [];
    }

    /**
     * Agregat jumlah artikel per nilai news.categori (untuk halaman statistik).
     *
     * @return list<array{categori: string, total: int|string, publish: int|string, draft: int|string, views: int|string}>
     */
    public function statsGroupedByCategory(): array
    {
        try {
            $db = \App\System\Storage\NexaDb::getInstance()->getConnection();
            $sql = 'SELECT `categori`, COUNT(*) AS `total`, '
                . "SUM(CASE WHEN LOWER(TRIM(COALESCE(`status`,''))) = 'publish' THEN 1 ELSE 0 END) AS `publish`, "
                . "SUM(CASE WHEN LOWER(TRIM(COALESCE(`status`,''))) = 'draft' THEN 1 ELSE 0 END) AS `draft`, "
                . 'COALESCE(SUM(CAST(`dilihat` AS SIGNED)), 0) AS `views` '
                . 'FROM `news` WHERE `categori` IS NOT NULL AND TRIM(`categori`) <> \'\' '
                . 'GROUP BY `categori` ORDER BY `total` DESC';
            $stmt = $db->query($sql);
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get sample data for testing
     *
     * @param string $search Search keyword for testing
     * @return array Sample data
     */
    public function data(string $search): array {
        return [
            'id' => 1,
            'title' => 'Sample News: ' . $search,
            'content' => 'This is sample content for: ' . $search,
            'search_keyword' => $search,
            'table' => $this->table,
            'timestamp' => time(),
            'controller_available' => $this->controller !== null
        ];
    }
} 