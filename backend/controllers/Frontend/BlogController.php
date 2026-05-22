<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;

use App\System\NexaController;

/**
 * BlogController - Handle blog routes with year and params
 * 
 * Route: $router->get('{Y}/{params}', 'BlogController@detail');
 * 
 * Examples:
 * - /2024/article-title → Y=2024, params=article-title
 * - /2023/category/tech → Y=2023, params=category/tech
 * - /2022/author/john/post/123 → Y=2022, params=author/john/post/123
 */
class BlogController extends NexaController
{
     /**
     * Kunci yang di-spread ke template — selalu ada agar {avatar} dll. tidak tertinggal mentah.
     */
    private function dataGlobalKeys(): array
    {
        return ['role', 'email', 'avatar', 'nama', 'package'];
    }

    /**
     * Data user untuk assignVars / spread ke template.
     * Nilai null / false / kosong dijadikan string '' (bukan placeholder tak terisi).
     */
    private function dataGlobal(array $params = []): array
    {
        $empty = array_fill_keys($this->dataGlobalKeys(), '');
        try {
            $userId = $this->session?->getUserId();
            if ($userId === null || $userId === 0) {
                return $empty;
            }
            $row = $this->Storage('user')
                ->select($this->dataGlobalKeys())
                ->where('id', $userId)
                ->first();
            if ($row === null) {
                return $empty;
            }
            $data = is_array($row) ? $row : (array) $row;
            $out = $empty;
            foreach ($this->dataGlobalKeys() as $key) {
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $v = $data[$key];
                if ($v === null || $v === false) {
                    $out[$key] = '';
                } elseif (is_scalar($v)) {
                    $out[$key] = (string) $v;
                } else {
                    $out[$key] = '';
                }
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('FrontendController::dataGlobal: ' . $e->getMessage());
            return $empty;
        }
    }   

    public function index(array $params = []): void
    {
       
        // === AMBIL PARAMETER REQUEST ===
        $page = $this->pagesIntRequest();          // Current page untuk pagination
        $requestParams = $this->paramsKeys();      // Search, sort, category parameters
        
        // === QUERY DATA DARI MODEL ===
        $searchResults = $this->useModels('News', 'search', [
            $requestParams['search'],              // Search keyword
            $requestParams['sort'],                // Sort field
            $page,                                 // Current page
            $requestParams['categori']             // Category filter
        ]);
        
        // === ASSIGN DATA KE TEMPLATE ===
        $this->assignBlocks([
            'post'     => $searchResults['data'],
           
        ]);

         $metaTags = [
            'meta_title' => 'Berita & artikel — tips, tutorial, dan update untuk developer',
            'meta_description' => 'Kumpulan berita, tutorial, dan tulisan praktis untuk developer: framework, tooling, praktik kode, dan pembaruan dari tim Nexa Dom. Temukan artikel terbaru, cari topik, dan ikuti perkembangan ekosistem pengembangan web.',
            'meta_keywords' => 'berita developer, blog teknologi, tutorial programming, Nexa Dom, framework PHP, tips coding, artikel IT, pengembangan web',
            'meta_image' =>  $this->url('drive/images/logo.png'),
        ];
          $this->assignVars($metaTags);  // Assign meta tags  

         // $this->setJsController($searchResults['data']);
        
        // // === GENERATE BASE URL UNTUK PAGINATION ===
        // if (!empty($requestParams['categori'])) {
        //     $baseUrl = $params['page_index'] . "?sort=" . $requestParams['sort'] . 
        //               "&categori=" . $requestParams['categori'] . "&pages/";
        // } else {
        //     $baseUrl = $params['page_index'] . "/pages/";
        // }
        
        // // === GENERATE DAN ASSIGN PAGINATION ===
        // $paginationHTML = $this->NexaPagination()->render(
        //     $searchResults['current_page'],
        //     $searchResults['last_page'], 
        //     $baseUrl
        // );
        
        // $this->assignVar('pagination', $paginationHTML);
    }



    
    /**
     * Handle blog detail route - {Y}/{params}
     * 
     * @param string $Y The year parameter from URL
     * @param string $params The remaining URL parameters
     * @return void
     */
    public function detail(string $Y = '', string $params = ''): void
    {
        $paramArray = !empty($params) ? explode('/', trim($params, '/')) : [];
        $slug = $paramArray !== [] ? (string) end($paramArray) : '';

        $detail = $slug !== ''
            ? $this->Storage('news')->where('slug', $slug)->first()
            : null;

        $detailArr = is_array($detail) ? $detail : (array) ($detail ?? []);

        $relatedRows = [];
        if ($slug !== '') {
            $raw = $this->Storage('news')->orderBy('id', 'desc')->limit(8)->get();
            foreach ((array) $raw as $row) {
                $row = is_array($row) ? $row : (array) $row;
                if (($row['slug'] ?? '') === $slug) {
                    continue;
                }
                $relatedRows[] = $row;
                if (count($relatedRows) >= 3) {
                    break;
                }
            }
        }

        // AUTO SEO META TAGS - Cukup define 4 variable sederhana
        // Sistem akan otomatis generate semua meta tags (og:*, twitter:*, canonical, dll)
        $metaTags = [
            'meta_title' => $detailArr['title'] ?? 'Blog Detail',
            'meta_description' => $detailArr['deskripsi'] ?? 'Read our latest blog post',
            'meta_keywords' => $detailArr['keywords'] ?? 'blog, article, news',
            'meta_image' => $this->url('drive/'.$detailArr['images'])  ?? $this->url('drive/images/logo.png'),
        ];

        $this->assignVars($this->dataGlobal());
        $this->assignVars($detailArr);
        $this->assignVars($metaTags);  // Assign meta tags
        $this->assignBlock('related', $relatedRows);
        $this->render('blog/detail');
    }



    
}
