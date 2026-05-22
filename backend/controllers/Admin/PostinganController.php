<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * PostinganController - Controller untuk mengelola postingan/artikel
 * 
 * Controller ini menyediakan CRUD operations untuk postingan dengan fitur:
 * - Listing dengan pagination dan search
 * - Create postingan baru dengan file upload
 * - Update postingan dengan smart file handling
 * - Delete postingan
 * 
 * @package App\Controllers\Admin
 * @author  Nexa Framework
 * @version 1.0.0
 */
class PostinganController extends NexaController
{
    /**
     * ID artikel dari path .../postingan/update/123 (segmen slug indeks 3).
     * Params resource_id memakai getDecodedSlug (base64); angka polos di URL sering null — jangan hanya mengandalkan itu.
     */
    private function resolvePostinganId(array $params): int
    {
        $raw = trim((string) $this->getSlug(3, ''));
        if ($raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }
        if (!empty($params['resource_id']) && is_numeric((string) $params['resource_id'])) {
            $n = (int) $params['resource_id'];
            return $n >= 1 ? $n : 0;
        }
        return 0;
    }

    /**
     * Opsi kategori untuk form: tabel news_category, fallback ke controllers.keywords (Role/Select).
     *
     * @return list<array{label: string, value: string}>
     */
    private function categoryOptions(): array
    {
        $fromTable = $this->useModels('NewsCategory', 'forSelect', []);
        if (is_array($fromTable) && $fromTable !== [] && !isset($fromTable['status'])) {
            return $fromTable;
        }
        $fallback = $this->useModels('Role/Select', 'option', ['news']);
        if (!is_array($fallback) || isset($fallback['status'])) {
            return [];
        }
        return $fallback;
    }

    /**
     * Slug kategori: huruf kecil, angka, tanda hubung.
     */
    private function normalizeCategorySlug(string $input): string
    {
        $s = strtolower(trim($input));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    /**
     * Peta template dashboard per method URL (/{slug}/postingan/{method}).
     */
    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        if ($method === 'pages') {
            return 'postingan/index';
        }
        if ($requestMethod === 'POST') {
            if ($method === 'create' || $method === 'update') {
                return 'postingan/' . $method;
            }
            if ($method === 'kategori') {
                return 'postingan/kategori';
            }
            return 'postingan/index';
        }
        if ($method !== '' && $method !== 'index') {
            return 'postingan/' . $method;
        }
        return 'postingan/index';
    }

    /**
     * Konfigurasi global untuk form dan validasi
     * 
     * Method ini menyediakan konfigurasi terpusat untuk:
     * - Validation rules (create dan update terpisah)
     * - Form fields yang akan di-populate
     * - Upload configuration
     * - Category options
     * 
     * @param string $key           Key konfigurasi yang diinginkan
     * @param array|null $fields    Field yang akan di-filter (optional)
     * @param bool $exclude         Apakah $fields adalah exclude list
     * @return mixed                Konfigurasi yang diminta
     */
    private function globalsData($key, $fields = null, $exclude = false)
    {
        $categoryOptions = $this->categoryOptions();

        $data = [
            // === CATEGORY OPTIONS ===
            'category' => $categoryOptions,
            
            // === FORM FIELDS YANG AKAN DIISI ===
            'setValue' => [
                'title',
                'deskripsi',
                'detail',
                'keywords',
                'categori',
                'pubdate',
                'status',
                'images'
            ],
            
            // === VALIDATION RULES UNTUK CREATE ===
            'setValidasi' => [
                'title'     => 'Name|3|Judul minimal 3 karakter',
                'deskripsi' => 'Name|3|Deskripsi minimal 3 karakter',
                'status'    => 'Name|null|Status tidak boleh kosong', 
                'detail'    => 'Name|3|Detail minimal 3 karakter',
                'keywords'  => 'Name|3|Keywords minimal 3 karakter',
                'categori'  => 'Name|3|Kategori harus dipilih',
                'pubdate'   => 'Name|3|Tanggal publikasi harus diisi',
                'images'    => 'FileOptional|null|Upload file jika diperlukan'
            ],
            
            // === VALIDATION RULES UNTUK UPDATE ===
            // Tidak termasuk 'images' field - akan ditambahkan conditional
            'setValidasiUpdate' => [
                'title'     => 'Name|3|Judul minimal 3 karakter',
                'deskripsi' => 'Name|3|Deskripsi minimal 3 karakter',
                'status'    => 'Name|null|Status tidak boleh kosong', 
                'detail'    => 'Name|3|Detail minimal 3 karakter',
                'keywords'  => 'Name|3|Keywords minimal 3 karakter',
                'categori'  => 'Name|3|Kategori harus dipilih',
                'pubdate'   => 'Name|3|Tanggal publikasi harus diisi'
            ],
            
            // === UPLOAD CONFIGURATION ===
            'setUpload' => [
                'maxSize'           => '15MB',
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx'],
                'thumbnail'         => ['200x150', '500x300', '800x600'],
                'thumbnailCropMode' => 'crop'
            ]
        ];

        // Filter konfigurasi sesuai permintaan menggunakan helper NexaController
        return $this->filterConfigData($data, $key, $fields, $exclude);
    }

    /**
     * Index Method - Menampilkan listing postingan dengan pagination
     * 
     * Fitur yang disediakan:
     * - Listing postingan dengan pagination
     * - Search berdasarkan keyword
     * - Filter berdasarkan kategori
     * - Sorting data
     * - Statistik dashboard (total posts, growth, dll)
     * 
     * @param array $params Parameter routing yang diterima
     * @return void
     */
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
        
        $statisticData = $this->useModels('News', 'percentage', [
            $requestParams['search'],
            $requestParams['sort'],
            $requestParams['categori']
        ]);

        // === SET DATA STATISTIK UNTUK DASHBOARD ===
        $this->setData([
            'total_posts'        => $searchResults['total'] ?? '0',
            'posts_growth'       => '100%',
            'draft_posts'        => $statisticData['draft'] ?? '0',
            'draft_growth'       => $statisticData['perdraft'] ?? '0%',
            'published_posts'    => $statisticData['publish'] ?? '0',
            'published_growth'   => $statisticData['perpublish'] ?? '0%',
            'total_views'        => $statisticData['dilihat'] ?? '0',
            'views_growth'       => $statisticData['perdilihat'] ?? '0%',
            'search_keyword'     => $requestParams['search'],
            'page_index'         => $params['page_index']
        ]);
        
        // === ASSIGN DATA KE TEMPLATE ===
        $this->assignBlocks([
            'post'     => $searchResults['data'],
            'category' => $this->globalsData('category')
        ]);
         $this->setJsController($searchResults['data']);
        
        // === GENERATE BASE URL UNTUK PAGINATION ===
        if (!empty($requestParams['categori'])) {
            $baseUrl = $params['page_index'] . "?sort=" . $requestParams['sort'] . 
                      "&categori=" . $requestParams['categori'] . "&pages/";
        } else {
            $baseUrl = $params['page_index'] . "/pages/";
        }
        
        // === GENERATE DAN ASSIGN PAGINATION ===
        $paginationHTML = $this->NexaPagination()->render(
            $searchResults['current_page'],
            $searchResults['last_page'], 
            $baseUrl
        );
        
        $this->assignVar('pagination', $paginationHTML);
        
        // === ASSIGN PAGINATION DATA TAMBAHAN ===
        $this->assignVars([
            'current_page'   => $searchResults['current_page'],
            'last_page'      => $searchResults['last_page'],
            'total_records'  => $searchResults['total'],
            'base_url'       => $baseUrl
        ]);
        
        // === CLEAR FORM STATE ===
        $this->clearState('form_add');
    }

    /**
     * Create Method - Menampilkan form create dan memproses create postingan baru
     * 
     * GET Request:  Menampilkan form kosong untuk create
     * POST Request: Memproses data yang disubmit dan create postingan baru
     * 
     * Fitur:
     * - Form validation dengan file upload
     * - Redirect setelah sukses create
     * - Error state management dengan session
     * 
     * @param array $params Parameter routing yang diterima
     * @return void
     */
    public function create(array $params = []): void
    {
        // === SETUP FORM DISPLAY ===
        $this->setValue($this->globalsData('setValue'));
        $this->assignBlocks([
            'category' => $this->globalsData('category')
        ]);
        
        // === RESTORE FORM STATE JIKA ADA ERROR SEBELUMNYA ===
        $formState = $this->getState('form_add', $params);
        $this->nexaVars($formState);
        $this->clearState('form_add');
        
        // === PROSES FORM SUBMISSION ===
        if ($this->isPost()) {
            // Setup form dengan validation rules untuk create
            $form = $this->createForm()
                ->fields($this->globalsData('setValidasi'))
                ->setUpload($this->globalsData('setUpload'))
                ->setSuccess('Berhasil menambahkan postingan baru')
                ->setError('Mohon perbaiki kesalahan form input');

            // Proses form dan ambil hasilnya
            $result = $form->process();
            
            if ($result['success']) {
                // === SUKSES: SIMPAN KE DATABASE DAN REDIRECT ===
                $this->useModels('News', 'create', [$result['data']]);
                $this->redirect($params['page_index']);
            }
            
            // === ERROR: PREPARE ERROR STATE DAN REDIRECT KEMBALI ===
            $clearValues = $result['success'] ?? false;
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan error state ke session untuk ditampilkan di form
            $this->setState('form_add', array_merge($templateVars, $result));
            $this->redirect($params['current_url']);
        }
    }

    /**
     * Update Method - Menampilkan form edit dan memproses update postingan
     * 
     * GET Request:  Menampilkan form yang sudah terisi data existing
     * POST Request: Memproses update data dengan smart file handling
     * 
     * Fitur Canggih:
     * - Conditional file validation (hanya validasi file jika ada upload)
     * - Smart file replacement (hapus file lama hanya jika ada file baru)
     * - Berbeda method update berdasarkan jenis input (data saja vs data+file)
     * - Preserve existing file jika user tidak upload file baru
     * 
     * @param array $params Parameter routing yang diterima (harus ada resource_id)
     * @return void
     */
    public function update(array $params = []): void
    {
        $id = $this->resolvePostinganId($params);
        if ($id < 1) {
            $this->redirect($params['page_index'] ?? '/');
            return;
        }

        // === AMBIL DATA EXISTING DARI DATABASE ===
        $existingData = $this->useModels('News', 'findById', [$id]);
        if (empty($existingData['id'])) {
            $this->redirect($params['page_index']);
            return;
        }

        // === SETUP FORM DISPLAY ===
        $this->setValue($this->globalsData('setValue'), 'errors');
        $this->assignBlocks([
            'category' => $this->globalsData('category')
        ]);
        
        // === MERGE DATA EXISTING DENGAN FORM STATE ===
        $formState = $this->getState('form_add', $params);
        $merged = array_merge($formState, $existingData);
        // Samakan pola _value dengan setFieldComplete agar input/select konsisten
        foreach (['title', 'deskripsi', 'detail', 'keywords', 'categori', 'pubdate', 'status'] as $f) {
            if (array_key_exists($f, $merged)) {
                $merged[$f . '_value'] = $merged[$f];
            }
        }
        $this->nexaVars($merged);
        $this->clearState('form_add');
        
        // === PROSES FORM SUBMISSION ===
        if ($this->isPost()) {
            
            // === CONDITIONAL VALIDATION: IMAGES FIELD ===
            // Mulai dengan validation rules untuk update (tanpa images)
            $validationRules = $this->globalsData('setValidasiUpdate');
            
            // Tambahkan validasi images HANYA jika ada file yang diupload
            if (!empty($_FILES['images']['tmp_name'])) {
                $validationRules['images'] = 'FileOptional|null|Upload file jika diperlukan';
            }

            // === SETUP FORM DENGAN SMART VALIDATION ===
            $form = $this->createForm()
                ->fields($validationRules)
                ->setUpload([
                    'maxSize'           => '15MB',
                    'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx'],
                    'thumbnail'         => ['200x150', '500x300', '800x600'],
                    'thumbnailCropMode' => 'crop',
                    'validation'        => false  // Skip default file validation
                ])
                ->setSuccess('Berhasil memperbarui postingan')
                ->setError('Mohon perbaiki kesalahan form input');

            // === PROSES FORM DAN DAPATKAN INFORMASI INPUT ===
            $result = $form->process();
            
            // === JIKA FORM BERHASIL DIVALIDASI ===
            if ($result['success']) {
                
                // === SMART FILE MANAGEMENT ===
                // Hapus file lama HANYA jika:
                // 1. Ada file baru yang diupload (informasi = 'file')
                // 2. Ada file lama yang perlu diganti
                if ($result['informasi'] == 'file' && !empty($existingData['images'])) {
                    $form->deleteFile($existingData['images']);
                }
                
                // === SMART DATABASE UPDATE ===
                if ($result['informasi'] == 'file') {
                    // User upload file baru + ubah data lain
                    // Update semua field termasuk path file baru
                    $this->useModels('News', 'updateById', [$result['data'], $id]);
                } else {
                    // User hanya ubah data, tidak upload file
                    // Update field non-file saja (preserve existing file)
                    $this->useModels('News', 'editById', [$result['data'], $id]);
                }
                
                // === REDIRECT SETELAH SUKSES ===
                $this->redirect($params['page_index']);
            }
            
            // === ERROR HANDLING ===
            // Jika ada error, kembalikan ke form dengan error messages
            $clearValues = $result['success'] ?? false;
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan error state ke session
            $this->setState('form_add', array_merge($templateVars, $result));
            $this->redirect($params['current_url']);
        }
    }

    /**
     * Delete Method - Menghapus postingan berdasarkan ID
     * 
     * Method ini melakukan soft delete atau hard delete tergantung implementasi model.
     * Setelah delete berhasil, redirect kembali ke halaman index.
     * 
     * @param array $params Parameter routing (harus ada resource_id)
     * @return void
     */
    public function delete(array $params = []): void
    {
        $id = $this->resolvePostinganId($params);
        if ($id >= 1) {
            $this->useModels('News', 'removeById', [$id]);
        }
        
        // === REDIRECT KEMBALI KE INDEX ===
        $this->redirect($params['page_index']);
    }

    /**
     * Daftar kategori artikel (tabel news_category; fallback sama seperti form).
     * POST: tambah kategori atau hapus (action di form).
     */
    public function kategori(array $params = []): void
    {
        $pageIndex = $params['page_index'] ?? '';
        $kategoriUrl = rtrim($pageIndex, '/') . '/kategori';

        $formError = '';
        $catLabel = '';
        $catSlug = '';
        $catSort = '0';
        $catIcon = '';

        if ($this->isPost()) {
            $action = (string) ($_POST['_cat_action'] ?? 'add');
            if ($action === 'delete') {
                $delId = (int) ($_POST['cat_id'] ?? 0);
                if ($delId >= 1) {
                    $this->useModels('NewsCategory', 'deleteById', [$delId]);
                }
                $this->redirect($kategoriUrl);
                return;
            }

            $label = trim((string) ($_POST['cat_label'] ?? ''));
            $slugInput = trim((string) ($_POST['cat_slug'] ?? ''));
            $sort = (int) ($_POST['cat_sort'] ?? 0);
            $iconInput = trim((string) ($_POST['cat_icon'] ?? ''));
            $slug = $slugInput !== '' ? $this->normalizeCategorySlug($slugInput) : $this->normalizeCategorySlug(str_replace(' ', '-', strtolower($label)));

            $result = $this->useModels('NewsCategory', 'create', [
                [
                    'slug'       => $slug,
                    'label'      => $label,
                    'icon'       => $iconInput,
                    'sort_order' => $sort,
                ],
            ]);

            if (!empty($result['success'])) {
                $this->redirect($kategoriUrl . '?added=1');
                return;
            }

            $formError = (string) ($result['message'] ?? 'Gagal menambah kategori.');
            $catLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $catSlug = htmlspecialchars($slugInput, ENT_QUOTES, 'UTF-8');
            $catSort = (string) $sort;
            $catIcon = htmlspecialchars($iconInput, ENT_QUOTES, 'UTF-8');
        }

        $flashOk = !empty($_GET['added']) ? '1' : '';

        $rows = $this->useModels('NewsCategory', 'listAll', []);
        $categories = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (empty($r['slug'])) {
                continue;
            }
            $ic = trim((string) ($r['icon'] ?? ''));
            $categories[] = [
                'id'         => (string) ($r['id'] ?? ''),
                'label'      => $r['label'] ?? $r['slug'],
                'value'      => $r['slug'],
                'icon'       => $ic !== '' ? $ic : 'fas fa-folder',
                'sort_order' => (string) ($r['sort_order'] ?? 0),
            ];
        }
        if ($categories === []) {
            foreach ($this->categoryOptions() as $item) {
                if (!is_array($item) || !isset($item['label'], $item['value'])) {
                    continue;
                }
                $categories[] = [
                    'id'         => '',
                    'label'      => $item['label'],
                    'value'      => $item['value'],
                    'icon'       => 'fas fa-tag',
                    'sort_order' => '—',
                ];
            }
        }

        $count = count($categories);
        $this->setData([
            'page_index'      => $pageIndex,
            'category_count'  => $count,
            'kategori_url'    => $kategoriUrl,
        ]);
        $this->assignVars([
            'kategori_url'         => $kategoriUrl,
            'category_form_error' => $formError,
            'category_flash_ok'   => $flashOk,
            'cat_label_value'     => $catLabel,
            'cat_slug_value'      => $catSlug,
            'cat_sort_value'      => $catSort,
            'cat_icon_value'      => $catIcon,
        ]);
        $this->assignBlocks([
            'category' => $categories,
        ]);
    }

    /**
     * Ringkasan statistik postingan (sama seperti data agregat di index).
     */
    public function statistik(array $params = []): void
    {
        $requestParams = $this->paramsKeys();
        $searchResults = $this->useModels('News', 'search', [
            $requestParams['search'],
            $requestParams['sort'],
            1,
            $requestParams['categori'],
        ]);
        $statisticData = $this->useModels('News', 'percentage', [
            $requestParams['search'],
            $requestParams['sort'],
            $requestParams['categori'],
        ]);

        $pageIndex = rtrim((string) ($params['page_index'] ?? ''), '/');
        $grouped = $this->useModels('News', 'statsGroupedByCategory', []);
        $bySlug = [];
        foreach (is_array($grouped) ? $grouped : [] as $g) {
            $slug = (string) ($g['categori'] ?? '');
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = $g;
        }

        $statByCat = [];
        $catRows = $this->useModels('NewsCategory', 'listAll', []);
        foreach (is_array($catRows) ? $catRows : [] as $def) {
            $slug = (string) ($def['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $g = $bySlug[$slug] ?? [];
            unset($bySlug[$slug]);
            $statByCat[] = [
                'cat_label'   => (string) ($def['label'] ?? $slug),
                'cat_slug'    => $slug,
                'cat_icon'    => trim((string) ($def['icon'] ?? '')) !== '' ? trim((string) $def['icon']) : 'fas fa-folder',
                'st_total'    => (string) (int) ($g['total'] ?? 0),
                'st_publish'  => (string) (int) ($g['publish'] ?? 0),
                'st_draft'    => (string) (int) ($g['draft'] ?? 0),
                'st_views'    => (string) (int) ($g['views'] ?? 0),
                'filter_url'  => $pageIndex . '?categori=' . rawurlencode($slug),
            ];
        }
        foreach ($bySlug as $slug => $g) {
            $statByCat[] = [
                'cat_label'   => $slug,
                'cat_slug'    => $slug,
                'cat_icon'    => 'fas fa-tag',
                'st_total'    => (string) (int) ($g['total'] ?? 0),
                'st_publish'  => (string) (int) ($g['publish'] ?? 0),
                'st_draft'    => (string) (int) ($g['draft'] ?? 0),
                'st_views'    => (string) (int) ($g['views'] ?? 0),
                'filter_url'  => $pageIndex . '?categori=' . rawurlencode($slug),
            ];
        }

        $this->setData([
            'total_posts'       => $searchResults['total'] ?? '0',
            'posts_growth'      => '100%',
            'draft_posts'       => $statisticData['draft'] ?? '0',
            'draft_growth'      => $statisticData['perdraft'] ?? '0%',
            'published_posts'   => $statisticData['publish'] ?? '0',
            'published_growth'  => $statisticData['perpublish'] ?? '0%',
            'total_views'       => $statisticData['dilihat'] ?? '0',
            'views_growth'      => $statisticData['perdilihat'] ?? '0%',
            'search_keyword'    => $requestParams['search'],
            'page_index'        => $params['page_index'] ?? '',
            'stat_cat_count'    => count($statByCat),
        ]);
        $this->assignBlocks([
            'stat_by_cat' => $statByCat,
        ]);
    }

    /**
     * Artikel yang memiliki file gambar utama, dengan pagination pages/n.
     */
    public function media(array $params = []): void
    {
        $page = $this->pagesIntRequest();
        $result = $this->Storage('news')
            ->select(['id', 'title', 'images', 'status', 'categori', 'pubdate'])
            ->where('images', '!=', '')
            ->orderBy('id', 'desc')
            ->paginate($page, 12);

        $rows = [];
        foreach ($result['data'] ?? [] as $row) {
            if (empty($row['images'])) {
                continue;
            }
            $rows[] = [
                'title'    => $row['title'] ?? '',
                'thumb'    => $row['images'],
                'status'   => $row['status'] ?? '',
                'categori' => $row['categori'] ?? '',
                'pubdate'  => $row['pubdate'] ?? '',
            ];
        }

        $pageIndex = rtrim($params['page_index'] ?? '', '/');
        $baseUrl = $pageIndex . '/media/pages/';

        $this->setData([
            'page_index'  => $params['page_index'] ?? '',
            'media_count' => $result['total'] ?? count($rows),
        ]);
        $this->assignBlocks(['media_row' => $rows]);
        $lastPage = max(1, (int) ($result['last_page'] ?? 1));
        $this->assignVar(
            'pagination',
            $this->NexaPagination()->render(
                $result['current_page'] ?? 1,
                $lastPage,
                $baseUrl
            )
        );
        $this->assignVars([
            'current_page'  => $result['current_page'] ?? 1,
            'last_page'     => $lastPage,
            'total_records' => $result['total'] ?? 0,
            'base_url'      => $baseUrl,
        ]);
    }
} 
