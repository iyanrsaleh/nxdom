<?php
declare(strict_types=1);
namespace App\System\Dom;

use App\System\Dom\NexaFilter;
use App\System\Helpers\NexaAsset;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class NexaDom
{
	private array $_tpldata = [],
		 $_section = [],
		 $files = [],
		 /** Cache isi file per path relatif — satu request, hindari baca disk berulang (header/footer/konten) */
		 $_fileReadCache = [];
	 
	private $root,
		$current_block_data;
		
	// REMOVED: Unused cache properties - these were never actively used
	// private static array $templateCache = [];
	// private static bool $cacheEnabled = true;
	// private static string $cacheDir = '';
		
	private ?NexaFilter $filter = null;
	private ?NexaAsset $asset = null;
		
	public $bShow_language_index = true;
	

	
	/**
	 * Konstruktor. Hanya mengatur direktori root.
	 */
	public function __construct(string $root = "./") {
		// Selalu inisialisasi properti root terlebih dahulu
		$this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
		
		// REMOVED: Cache directory setup - was not actively used
		
		// Lazy loading - hanya inisialisasi filter saat dibutuhkan
		// $this->forceLoadFilter(); // REMOVED - akan di-load saat dibutuhkan
		
		// Optimasi: validasi direktori yang lebih efisien
		$this->validateAndSetupRoot($root);
	}
	
	/**
	 * Optimized root directory validation
	 */
	private function validateAndSetupRoot(string $root): void
	{
		// Normalize path
		$root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
		
		// Quick check without multiple is_dir calls
		if (is_dir($root)) {
			$this->root = $root;
			return;
		}
		
		// Try to create only if it's a reasonable path
		if (strlen($root) < 255 && !str_contains($root, '..')) {
			@mkdir($root, 0755, true);
			if (is_dir($root)) {
				$this->root = $root;
				return;
			}
		}
		
		// Fallback
		$this->root = "./";
	}
	
	/**
	 * Force load NexaFilter using autoloader
	 */
	private function forceLoadFilter(): void
	{
		try {
			// Use Composer autoloader - no need for manual require_once
			$this->filter = new \App\System\Dom\NexaFilter();
			
			// Quick test to ensure it works
			$testResult = $this->filter->Filter('test', 'upper');
			if ($testResult !== 'TEST') {
				throw new \Exception('Filter test failed');
			}
		} catch (\Throwable $e) {
			// If autoloader fails, set to null but don't fail the entire class
			$this->filter = null;
			// Optional: Log error for debugging
			// error_log("NexaFilter initialization failed: " . $e->getMessage());
		}
	}
	
	/**
	 * Lazy load NexaFilter - hanya load saat dibutuhkan
	 */
	private function getFilterLazy(): ?NexaFilter
	{
		if ($this->filter === null) {
			$this->initializeFilter();
		}
		return $this->filter;
	}
	
	/**
	 * Initialize NexaFilter with optimized error handling
	 */
	private function initializeFilter(): void
	{
		try {
			// Try with composer autoloader first
			if (class_exists('\App\System\Dom\NexaFilter')) {
				$this->filter = new \App\System\Dom\NexaFilter();
			} else {
				// Fallback: manual require if autoloader fails
				$filter_path = __DIR__ . '/NexaFilter.php';
				if (file_exists($filter_path)) {
					require_once $filter_path;
					$this->filter = new \App\System\Dom\NexaFilter();
				} else {
					throw new \Exception('NexaFilter.php not found at: ' . $filter_path);
				}
			}
			
		} catch (\Throwable $e) {
			// Silent fail - filter akan tetap null jika tidak bisa diload
			$this->filter = null;
		}
	}
	
	public function __destruct()
	{
		$this->destroy();
	}
	
	public function reset_files(): void
	{
		$this->files = [];
		$this->_fileReadCache = [];
	}
	
	/**
	 * Menghancurkan objek template ini. Harus dipanggil ketika selesai menggunakannya, untuk
	 * membersihkan data template sehingga dapat memuat/mengurai set template baru.
	 */
	public function destroy(): void
	{
		unset( $this->_tpldata );
		unset( $this->files );
		unset( $this->_fileReadCache );
		unset( $this->_section );
		
		unset( $this->root, $this->bShow_language_index);
	}
	
	public function get_block( string $blockname )
	{
		if( isset($this->_tpldata['.'][$blockname]) )
			return $this->_tpldata['.'][$blockname];

		return false;
	}
	
	public function get_section( string $section_name )
	{
		if( isset($this->_section[$section_name]) )
		{
			return $this->_section[$section_name];
		}

		return false;
	}
	
	/**
	 * Mengatur direktori root template untuk objek Template ini.
	 */
	public function set_rootdir(string $dir): bool
	{
		if (!is_dir($dir))
		{
			return false;
		}

		$this->root = $dir;
		return true;
	}

	/**
	 * Penugasan variabel tingkat root. Menambah ke penugasan saat ini:
	 *  $bAppend = false: menimpa penugasan variabel yang ada dengan nama yang sama
	 *  $bAppend = true : menambahkan ke variabel yang ada
	 */
	public function assign_var(string $varname, $varval, bool $bAppend = false): bool
	{
		if( $bAppend && isset($this->_tpldata['.'][$varname]) )
		{
			$this->_tpldata['.'][$varname] .= $varval;
			return true;
		}
		
		$this->_tpldata['.'][$varname] = $varval;
		return true;
	}
	
	/**
	 * Penugasan variabel tingkat root. Menambah ke penugasan saat ini:
	 *  $bAppend = false: menimpa penugasan variabel yang ada dengan nama yang sama
	 *  $bAppend = true : menambahkan ke variabel yang ada
	 */
	public function assign_vars(array $vararray, bool $bAppend = false): bool
	{
		foreach ($vararray as $key => $val)
		{
			if( $bAppend && isset($this->_tpldata['.'][$key]) )
			{
				$this->_tpldata['.'][$key] .= $val;
				continue;
			}
			
			$this->_tpldata['.'][$key] = $val;
		}

		return true;
	}
	
	/**
	 * Penugasan variabel blok dengan auto-detection untuk efisiensi
	 * 
	 * Mendukung 3 format penggunaan:
	 * 1. Single array item: assign_block_vars('post', ['id' => 1, 'title' => 'Post 1'])
	 * 2. Array of arrays: assign_block_vars('post', [['id' => 1], ['id' => 2]])  
	 * 3. Direct assignment: assign_block_vars('post', Storage::getAll())
	 * 
	 * @param string $varblock Nama blok variabel
	 * @param array $vararray Data array (single item atau multiple items)
	 * @return bool Status penugasan berhasil
	 */
	public function assign_block_vars(string $varblock, array $vararray): bool
	{
		if( !is_array($vararray) )
		{
			 throw new InvalidArgumentException("nexa->assign_block_vars(): $varblock vararray bukan array.");
		}
		
		// Initialize block if not exists OR if it's not an array (protection)
		if( !isset($this->_tpldata['.'][$varblock]) || !is_array($this->_tpldata['.'][$varblock]) )
		{
			$this->_tpldata['.'][$varblock] = [];
		}
		
		// ENHANCED: Auto-detect array structure untuk efisiensi
		if (empty($vararray)) {
			// Empty array, nothing to do
			return true;
		}
		
		// Check if this is array of arrays (multi-dimensional)
		$isMultiDimensional = $this->isArrayOfArrays($vararray);
		
		if ($isMultiDimensional) {
			// Format: [['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']]
			foreach ($vararray as $item) {
				if (is_array($item)) {
					// Final safety check before array_push
					if (is_array($this->_tpldata['.'][$varblock])) {
						array_push($this->_tpldata['.'][$varblock], $item);
					}
				}
			}
		} else {
			// Format: ['id' => 1, 'title' => 'Post 1'] - single associative array
			// Final safety check before array_push
			if (is_array($this->_tpldata['.'][$varblock])) {
				array_push($this->_tpldata['.'][$varblock], $vararray);
			}
		}
		
		return true;
	}
	
	/**
	 * Helper method untuk mendeteksi apakah array adalah array of arrays
	 * 
	 * @param array $array Array yang akan diperiksa
	 * @return bool True jika array of arrays, false jika single associative array
	 */
	private function isArrayOfArrays(array $array): bool
	{
		// Jika array kosong, return false
		if (empty($array)) {
			return false;
		}
		
		// Ambil elemen pertama untuk checking
		$firstElement = reset($array);
		
		// Jika elemen pertama bukan array, ini single associative array
		if (!is_array($firstElement)) {
			return false;
		}
		
		// Check beberapa elemen untuk memastikan konsistensi
		$checkCount = min(3, count($array)); // Check max 3 elements
		$arrayCount = 0;
		
		foreach (array_slice($array, 0, $checkCount, true) as $key => $value) {
			if (is_array($value)) {
				$arrayCount++;
			}
		}
		
		// Jika sebagian besar elemen adalah array, kemungkinan ini array of arrays
		// Namun, jika key adalah string dan value adalah mixed, kemungkinan single associative
		$numericKeys = array_filter(array_keys(array_slice($array, 0, $checkCount, true)), 'is_numeric');
		$hasNumericKeys = count($numericKeys) > 0;
		
		// Logika: jika ada numeric keys dan sebagian besar value adalah array, 
		// kemungkinan besar ini array of arrays
		return $hasNumericKeys && ($arrayCount >= ($checkCount * 0.6));
	}
	
	/**
	 * Menugaskan data array bersarang (FITUR BARU untuk notasi titik)
	 * Secara otomatis menangani struktur bersarang yang kompleks
	 */
	public function assign_nested_data(string $varblock, array $data): bool
	{
		if (!is_array($data)) {
			throw new InvalidArgumentException("nexa->assign_nested_data(): data harus berupa array.");
		}
		
		if (!isset($this->_tpldata['.'][$varblock])) {
			$this->_tpldata['.'][$varblock] = [];
		}
		
		// Tangani penugasan struktur bersarang
		foreach ($data as $item) {
			$this->_tpldata['.'][$varblock][] = $item;
		}
		
		return true;
	}
	
	public function assign_file_var(string $varfile, string $varname, $varval): bool
	{
		if( !isset($this->files[$varfile]) )
		{
			 throw new InvalidArgumentException("nexa->assign_file_var(): Belum menambahkan file $varfile.");
		}
		$this->_tpldata['.'][$varfile][$varname] = $varval;

		return true;
	}
	
	public function add_file(string $varfile): bool
	{
		// Pastikan root sudah diatur
		if (!isset($this->root) || empty($this->root)) {
			$this->root = "./";
		}
		
		$full_path = $this->root . $varfile;
		
		if( !is_file($full_path) )
		{
			throw new RuntimeException("nexa->add_file(): Tidak dapat memuat file template '$varfile' di path: $full_path (Root: {$this->root})");
		}
		$this->files[$varfile] = $varfile;
		
		return true;
	}
	
	public function add_tempfile(string $varfile): bool
	{
		if( !is_file($varfile) )
		{
			throw new RuntimeException("nexa->add_file(): Tidak dapat memuat file template " . $varfile);
		}
		$this->files[$varfile] = $varfile;
		
		return true;
	}
	

	/**
	 * Ekstrak blok <script> dan <style> supaya tidak diproses sintaks NexaDom (ternary, dll.).
	 *
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function extractProtectedScriptStyleBlocks(string $content): array
	{
		$stored = [];
		$patterns = [
			'#<script\b[^>]*>.*?</script>#is',
			'#<style\b[^>]*>.*?</style>#is',
		];
		foreach ($patterns as $pattern) {
			$content = preg_replace_callback($pattern, static function (array $matches) use (&$stored): string {
				$placeholder = '___NEXA_PROTECTED_' . count($stored) . '___';
				$stored[$placeholder] = $matches[0];
				return $placeholder;
			}, $content) ?? $content;
		}
		return [$content, $stored];
	}

	private function restoreProtectedScriptStyleBlocks(string $content, array $stored): string
	{
		foreach ($stored as $placeholder => $original) {
			$content = str_replace($placeholder, $original, $content);
		}
		return $content;
	}

	
	public function html_standard( string &$content ): void
	{
		if( !empty($content) ) {
			// Lindungi konten script dari html_standard processing
			$scriptContent = [];
			$scriptPattern = '#<script[^>]*>(.*?)</script>#is';
			
			// Extract dan simpan script content
			$content = preg_replace_callback($scriptPattern, function($matches) use (&$scriptContent) {
				$placeholder = '___SCRIPT_PLACEHOLDER_' . count($scriptContent) . '___';
				$scriptContent[$placeholder] = $matches[0];
				return $placeholder;
			}, $content);
			
			// Proses html standard hanya pada konten non-script
			$content = str_replace( ['& ', ' & '],
						 ['&amp; ', ' &amp; '],
						 $content );
			
			// Kembalikan script content
			foreach ($scriptContent as $placeholder => $originalContent) {
				$content = str_replace($placeholder, $originalContent, $content);
			}
		}
	}
	
	public function compress(string &$codes, bool $bRemoveComment = true): void
	{
		// Lindungi konten script dari kompresi
		$scriptContent = [];
		$scriptPattern = '#<script[^>]*>(.*?)</script>#is';
		
		// Extract dan simpan script content
		$codes = preg_replace_callback($scriptPattern, function($matches) use (&$scriptContent) {
			$placeholder = '___SCRIPT_PLACEHOLDER_' . count($scriptContent) . '___';
			$scriptContent[$placeholder] = $matches[0];
			return $placeholder;
		}, $codes);
		
		// Lindungi konten style dari kompresi
		$styleContent = [];
		$stylePattern = '#<style[^>]*>(.*?)</style>#is';
		
		$codes = preg_replace_callback($stylePattern, function($matches) use (&$styleContent) {
			$placeholder = '___STYLE_PLACEHOLDER_' . count($styleContent) . '___';
			$styleContent[$placeholder] = $matches[0];
			return $placeholder;
		}, $codes);
		
		// Proses kompresi normal setelah script dan style dilindungi
		$codes_array = preg_split( '#<!-- START_SECTION donot_compress -->([\S\W]*)<!-- STOP_SECTION donot_compress -->#U', $codes, - 1, PREG_SPLIT_DELIM_CAPTURE );
		if( ($len = count($codes_array)) )
		{
			for( $i=0, $codes=''; $i<$len; $i++ )
			{
				if( !($i%2) )
					$codes .= preg_replace( '#([\r\n\t]*)|([\s]{2,})#U', '', $codes_array[$i] );
				else
					$codes .= $codes_array[$i];
			}
			unset( $codes_array );
		}
		else 
			$codes = preg_replace( '#([\r\n\t]*)|([\s]{2,})#U', '', $codes );
		
		if( $bRemoveComment )
			$this->strip_comment( $codes );
		
		// Kembalikan script dan style content
		foreach ($scriptContent as $placeholder => $content) {
			$codes = str_replace($placeholder, $content, $codes);
		}
		foreach ($styleContent as $placeholder => $content) {
			$codes = str_replace($placeholder, $content, $codes);
		}
	}
	
	public function strip_comment( string &$content ): void
	{
		if( !empty($content) ) {
			// Lindungi konten script dari penghapusan komentar
			$scriptContent = [];
			$scriptPattern = '#<script[^>]*>(.*?)</script>#is';
			
			// Extract dan simpan script content
			$content = preg_replace_callback($scriptPattern, function($matches) use (&$scriptContent) {
				$placeholder = '___SCRIPT_PLACEHOLDER_' . count($scriptContent) . '___';
				$scriptContent[$placeholder] = $matches[0];
				return $placeholder;
			}, $content);
			
			// Hapus komentar HTML hanya di luar script tags
			$content = preg_replace('#<!--(.*?)-->#', '', $content);
			
			// Kembalikan script content
			foreach ($scriptContent as $placeholder => $originalContent) {
				$content = str_replace($placeholder, $originalContent, $content);
			}
		}
	}
	
	/**
	 * OPTIMIZED VERSION: Proses template dengan performa tinggi
	 * Memuat file untuk handle, mengkompilasi file,
	 * dan menjalankan kode yang dikompilasi. Ini akan mencetak
	 * hasil dari eksekusi template.
	 */
	public function pparse(string &$content, bool $bRemove_VARS = true, bool $bReturn = true, bool $bCompress = false)
	{
		global $lang;
		
		// OPTIMIZATION 1: Early return untuk content kosong
		if (empty($content)) {
			return $bReturn ? '' : true;
		}
		
		// OPTIMIZATION 2: Check if content has template syntax - early return jika tidak ada
		if (strpos($content, '{') === false && 
			strpos($content, '<!--') === false && 
			strpos($content, '_lang{') === false) {
			return $bReturn ? $content : true;
		}
		
		// AUTO-GENERATE SEO META TAGS sebelum rendering
		$this->autoGenerateSeoMeta();
		
		// Proses SSI-style includes PERTAMA sebelum pemrosesan lainnya
		$this->process_ssi_includes($content);
		
		// Proses kondisional gaya PHP untuk variabel global PERTAMA - STABLE VERSION
		// SKIP conditionals inside blocks - they will be processed during block processing
		$this->process_global_conditionals_stable_skip_blocks($content);
		
		// Proses ternary operators sebelum pemrosesan blok
		$this->process_ternary_operators($content);
		
		// OPTIMIZATION 3: Proses loop blok dengan early return
		$this->process_nexa_blocks_optimized($content);
		
		// OPTIMIZATION 3.5: Proses NX_BLOG blocks untuk global data
		$this->process_nx_blog_blocks($content);
		
		// OPTIMIZATION 4: Proses sections dengan caching
		$this->process_sections_optimized($content);
		
		// OPTIMIZATION 5: Proses NexaAsset syntax dengan batch processing
		$this->process_asset_syntax($content);
		
		// OPTIMIZATION 6: Proses modern variables dengan batch processing
		$this->process_modern_variables($content, $bRemove_VARS);
		
		// OPTIMIZATION 7: Batch processing untuk language variables
		$this->process_language_variables_batch($content);
		
		// NEW: Proses custom events untuk interaksi JavaScript
		$this->processCustomEvents($content);
		
		// NEW: Proses token attributes SEBELUM transformasi HTML (compress, html_standard)
		// Ini memastikan token terdeteksi dan tersimpan ke cookies sebelum HTML diubah
		// PENTING: Harus dilakukan sebelum compress/html_standard karena bisa merusak atribut HTML
		$this->processTokenAttributes($content);

		if (!$bReturn) {
			if (!$this->isTemplateRenderingContext()) {
				echo $content;
			}
			return true;
		}
		
		if ($bCompress) {
			$this->compress($content);
		}
		$this->html_standard($content);
		
		// OPTIMIZATION 7: Single regex untuk semua cleanup
		$content = preg_replace([
			'#\{if\s+[^}]+\}#',
			'#\{else\}#',
			'#\{elseif\s+[^}]+\}#',
			'#\{endif\}#'
		], '', $content);
		
		$this->transformHref($content);
		
		return trim($content);
	}
	
	/**
	 * OPTIMIZATION: Proses NEXA blocks dengan minimal regex calls
	 */
	private function process_nexa_blocks_optimized(string &$content): void
	{
		// Early return jika tidak ada NEXA blocks
		if (strpos($content, '<!-- NEXA ') === false) {
			return;
		}
		
		$str_pattern = "#<!-- NEXA ([A-Za-z0-9_]*) -->([\S\W]*)<!-- END \\1 -->#U";
		if (!preg_match_all($str_pattern, $content, $match_array, PREG_SET_ORDER)) {
			return;
		}
		
		foreach ($match_array as $val) {
			if (!isset($val[0], $val[1], $val[2])) {
				continue;
			}
			
			if (isset($this->_tpldata['.'][$val[1]]) && is_array($this->_tpldata['.'][$val[1]])) {
				// Process nested blocks first
				if (preg_match_all($str_pattern, $val[2], $match_array_sub, PREG_SET_ORDER)) {
					foreach ($match_array_sub as $val_sub) {
						if (isset($val_sub[0], $val_sub[1], $val_sub[2]) && 
							isset($this->_tpldata['.'][$val_sub[1]]) && 
							is_array($this->_tpldata['.'][$val_sub[1]])) {
							$this->pparse_block($val_sub[2], $val_sub[1]);
						}
						$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
					}
				}
				$this->pparse_block($val[2], $val[1]);
			}
			$content = str_replace($val[0], $val[2], $content);
		}
	}
	
	/**
	 * OPTIMIZATION: Proses NX_BLOG blocks untuk global data
	 * Mendukung syntax: <!-- NX_BLOG section_name -->...<!-- ENDEX section_name -->
	 */
	private function process_nx_blog_blocks(string &$content): void
	{
		// Early return jika tidak ada NX_BLOG blocks
		if (strpos($content, '<!-- NX_BLOG ') === false) {
			return;
		}
		
		$str_pattern = "#<!-- NX_BLOG ([A-Za-z0-9_]*) -->([\S\W]*)<!-- ENDEX \\1 -->#U";
		if (!preg_match_all($str_pattern, $content, $match_array, PREG_SET_ORDER)) {
			return;
		}
		
		foreach ($match_array as $val) {
			if (!isset($val[0], $val[1], $val[2])) {
				continue;
			}
			
			$section_name = $val[1];
			$block_content = $val[2];
			$result_content = '';
			
			// Check if we have data for this NX_BLOG section
			$block_key = "NX_BLOG_{$section_name}";
			if (isset($this->_tpldata['.'][$block_key]) && is_array($this->_tpldata['.'][$block_key])) {
				// Process the block content for each item in the array
				$temp_content = $block_content;
				$this->pparse_block($temp_content, $block_key);
				$result_content = $temp_content;
			} else {
				// Fallback: Try to find data with nx_blog_ prefix
				$found_data = false;
				foreach ($this->_tpldata['.'] as $key => $value) {
					if (strpos($key, "nx_blog_{$section_name}") === 0 && is_array($value)) {
						$temp_content = $block_content;
						$this->pparse_block($temp_content, $key);
						$result_content .= $temp_content;
						$found_data = true;
					}
				}
				
				if (!$found_data) {
					// If no data found, empty the block
					$result_content = '';
				}
			}
			
			// Replace the original block with processed content
			$content = str_replace($val[0], $result_content, $content);
		}
	}
	
	/**
	 * ENHANCED: Proses NX_BLOG blocks dengan akses ke parent block data
	 * Memungkinkan NX_BLOG di dalam NEXA blocks mengakses data parent
	 */
	private function process_nx_blog_blocks_with_parent(string &$content, array $parent_data = []): void
	{
		// Early return jika tidak ada NX_BLOG blocks
		if (strpos($content, '<!-- NX_BLOG ') === false) {
			return;
		}
		
		$str_pattern = "#<!-- NX_BLOG ([A-Za-z0-9_]*) -->([\S\W]*)<!-- ENDEX \\1 -->#U";
		if (!preg_match_all($str_pattern, $content, $match_array, PREG_SET_ORDER)) {
			return;
		}
		
		foreach ($match_array as $val) {
			if (!isset($val[0], $val[1], $val[2])) {
				continue;
			}
			
			$section_name = $val[1];
			$block_content = $val[2];
			$result_content = '';
			
			// Check if we have data for this NX_BLOG section
			$block_key = "NX_BLOG_{$section_name}";
			if (isset($this->_tpldata['.'][$block_key]) && is_array($this->_tpldata['.'][$block_key])) {
				// Process each item in the NX_BLOG data
				foreach ($this->_tpldata['.'][$block_key] as $nx_blog_item) {
					$temp_content = $block_content;
					
					// ENHANCED: Merge parent data dengan NX_BLOG data
					// Parent data akan di-override oleh NX_BLOG data jika ada konflik
					$merged_data = array_merge($parent_data, $nx_blog_item);
					
					// Process variables dalam block content
					preg_match_all("#\{(?!(?:switch|case|default|endswitch|if|else|elseif|endif)\b)([a-zA-Z0-9_.]+(?:\|[^}]+)?)\}#", $temp_content, $var_matches, PREG_SET_ORDER);
					
					foreach ($var_matches as $var_match) {
						if (!isset($var_match[0]) || !isset($var_match[1])) {
							continue;
						}
						
						$field_with_filters = $var_match[1];
						$parsed = $this->parse_variable_with_filters($field_with_filters);
						$field_name = $parsed['variable'] ?? '';
						$filters = $parsed['filters'] ?? [];
						
						$replacement = '';
						
						// Cari nilai dari merged data (parent + NX_BLOG)
						if (isset($merged_data[$field_name])) {
							$replacement = (string)$merged_data[$field_name];
						} else {
							// Fallback ke global variables
							$found_global_key = $this->find_case_insensitive_key($field_name);
							if ($found_global_key !== false) {
								$global_value = $this->_tpldata['.'][$found_global_key];
								if (is_scalar($global_value)) {
									$replacement = (string)$global_value;
								}
							}
						}
						
						// Terapkan filter jika tersedia
						if (!empty($filters) && $this->isFilterReady()) {
							$replacement = $this->apply_filters($replacement, $filters);
						}
						
						$temp_content = str_replace($var_match[0], $replacement, $temp_content);
					}
					
					$result_content .= $temp_content;
				}
			} else {
				// Fallback: Try to find data with nx_blog_ prefix
				$found_data = false;
				foreach ($this->_tpldata['.'] as $key => $value) {
					if (strpos($key, "nx_blog_{$section_name}") === 0 && is_array($value)) {
						foreach ($value as $nx_blog_item) {
							$temp_content = $block_content;
							
							// Merge parent data dengan NX_BLOG data
							$merged_data = array_merge($parent_data, $nx_blog_item);
							
							// Process variables seperti di atas
							preg_match_all("#\{(?!(?:switch|case|default|endswitch|if|else|elseif|endif)\b)([a-zA-Z0-9_.]+(?:\|[^}]+)?)\}#", $temp_content, $var_matches, PREG_SET_ORDER);
							
							foreach ($var_matches as $var_match) {
								if (!isset($var_match[0]) || !isset($var_match[1])) {
									continue;
								}
								
								$field_with_filters = $var_match[1];
								$parsed = $this->parse_variable_with_filters($field_with_filters);
								$field_name = $parsed['variable'] ?? '';
								$filters = $parsed['filters'] ?? [];
								
								$replacement = '';
								
								if (isset($merged_data[$field_name])) {
									$replacement = (string)$merged_data[$field_name];
								}
								
								// Terapkan filter jika tersedia
								if (!empty($filters) && $this->isFilterReady()) {
									$replacement = $this->apply_filters($replacement, $filters);
								}
								
								$temp_content = str_replace($var_match[0], $replacement, $temp_content);
							}
							
							$result_content .= $temp_content;
							$found_data = true;
						}
					}
				}
				
				if (!$found_data) {
					// If no data found, empty the block
					$result_content = '';
				}
			}
			
			// Replace the original block with processed content
			$content = str_replace($val[0], $result_content, $content);
		}
	}
	
	/**
	 * OPTIMIZATION: Proses sections dengan early return
	 */
	private function process_sections_optimized(string &$content): void
	{
		// Early return jika tidak ada sections
		if (strpos($content, '<!-- START_SECTION ') === false) {
			return;
		}
		
		$str_pattern = "#<!-- START_SECTION ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SECTION \\1 -->#U";
		if (!preg_match_all($str_pattern, $content, $match_array, PREG_SET_ORDER)) {
			return;
		}
		
		$str_pattern_sub = "#<!-- START_SUB ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SUB \\1 -->#U";
		
		foreach ($match_array as $val) {
			// Process sub-sections if available
			if (preg_match_all($str_pattern_sub, $val[2], $match_array_sub, PREG_SET_ORDER)) {
				foreach ($match_array_sub as $val_sub) {
					$var_name = $val_sub[1];
					global ${$var_name};
					
					if (${$var_name}) {
						$str_pattern_child = "#<!-- START_CHILD ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_CHILD \\1 -->#U";
						if (preg_match_all($str_pattern_child, $val_sub[2], $match_array_child, PREG_SET_ORDER)) {
							foreach ($match_array_child as $val_child) {
								if (!isset($val_child[0], $val_child[1], $val_child[2])) {
									continue;
								}
								
								$child_var_name = $val_child[1];
								global ${$child_var_name};
								if (${$child_var_name}) {
									$this->pparse_variables_only($val_child[2], true, true, false);
								} else {
									$val_child[2] = '';
								}
								$val_sub[2] = str_replace($val_child[0], $val_child[2], $val_sub[2]);
							}
						}
					} else {
						$val_sub[2] = '';
					}
					$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
				}
			}
			
			$section_var_name = $val[1];
			global ${$section_var_name};
			
			if ($val[1] === 'donot_compress') {
				$val[2] = $val[0];
			} elseif (${$section_var_name}) {
				$temp_content = $val[2];
				$this->pparse_variables_only($temp_content, true, true, false);
				$val[2] = $temp_content;
				$this->_section[$val[1]] = $val[2];
			} else {
				$val[2] = '';
			}
			
			$content = str_replace($val[0], $val[2], $content);
		}
	}
	
	/**
	 * OPTIMIZED: Proses syntax NexaAsset dengan batch processing
	 * Supports: {css/file}, {js/file}, {img/file}, {font/file}, {drive/file}, {dashboard/file}, {dash/file}, {mobile/file}, {tablet/file}, {theme/file}
	 * Enhanced: ONLY DRIVE supports nested variables like {drive/{avatar}}
	 * Other assets use normal syntax: {css/style.css}, {js/script.js}
	 */
	private function process_asset_syntax(string &$content): void
	{
		// Early return jika NexaAsset tidak ready
		if (!$this->isAssetReady()) {
			return;
		}
		
		// Early return jika tidak ada asset syntax
		if (strpos($content, '{css/') === false && 
			strpos($content, '{js/') === false && 
			strpos($content, '{img/') === false && 
			strpos($content, '{font/') === false &&
			strpos($content, '{drive/') === false &&
			strpos($content, '{dashboard/') === false &&
			strpos($content, '{app/') === false &&
			strpos($content, '{nexaui/') === false &&
			strpos($content, '{dash/') === false && 
			strpos($content, '{mobile/') === false && 
			strpos($content, '{tablet/') === false && 
			strpos($content, '{packages/') === false && 
			strpos($content, '{theme/') === false) {
			return;
		}
		
		// ENHANCED: Pre-process nested variables ONLY for drive assets
		// Only {drive/{avatar}} is supported, others use normal syntax
		if (strpos($content, '{drive/{') !== false) {
			$this->preprocess_nested_asset_variables($content);
		}
		
		$asset = $this->getAssetLazy();
		$replacements = [];
		
		// Asset syntax patterns: {type/filename}
		$asset_patterns = [
			'css' => '#\{(css/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'js' => '#\{(js/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'img' => '#\{(img/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'font' => '#\{(font/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'drive' => '#\{(drive/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'app' => '#\{(app/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'dashboard' => '#\{(dashboard/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'nexaui' => '#\{(nexaui/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'dependencies' => '#\{(dependencies/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'dash' => '#\{(dash/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'packages' => '#\{(packages/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'mobile' => '#\{(mobile/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'tablet' => '#\{(tablet/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'theme' => '#\{(theme/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
		];
		
		foreach ($asset_patterns as $type => $pattern) {
			if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match_val) {
					if (!isset($match_val[0]) || !isset($match_val[1])) {
						continue;
					}
					
					$var_path_with_filters = $match_val[1];
					$parsed = $this->parse_variable_with_filters($var_path_with_filters);
					$var_path = $parsed['variable'];
					$filters = $parsed['filters'];
					
					// Extract filename from path
					$filename = substr($var_path, strlen($type) + 1); // Remove 'type/' prefix
					
					// Generate asset URL using NexaAsset
					$replacement = '';
					try {
						switch ($type) {
							case 'css':
								$replacement = $asset::css($filename);
								break;
							case 'dependencies':
								$replacement = $asset::dependencies($filename);
								break;
							case 'js':
								$replacement = $asset::js($filename);
								break;
							case 'img':
								$replacement = $asset::img($filename);
								break;
							case 'font':
								$replacement = $asset::font($filename);
								break;
							case 'drive':
								$replacement = $asset::drive($filename);
								break;
		                    case 'app':
								$replacement = $asset::app($filename);
								break;
							case 'dashboard':
								$replacement = $asset::dashboard($filename);
								break;
							case 'nexaui':
								$replacement = $asset::nexaui($filename);
								break;
							case 'dash':
								$replacement = $asset::dash($filename);
								break;
							case 'packages':
								$replacement = $asset::packages($filename);
								break;
							case 'mobile':
								$replacement = $asset::mobile($filename);
								break;
							case 'tablet':
								$replacement = $asset::tablet($filename);
								break;
							case 'theme':
								$replacement = $asset::theme($filename);
								break;
						}
						
						// Apply filters if available
						if (!empty($filters) && $this->isFilterReady()) {
							$replacement = $this->apply_filters($replacement, $filters);
						}
						
					} catch (\Throwable $e) {
						$replacement = "<!-- ASSET_ERROR: {$var_path_with_filters} -->";
					}
					
					$replacements[$match_val[0]] = $replacement;
				}
			}
		}
		
		// Batch apply all replacements
		if (!empty($replacements)) {
			$content = str_replace(array_keys($replacements), array_values($replacements), $content);
		}
	}
	
	/**
	 * Pre-process nested variables in asset syntax
	 * Resolves {drive/{avatar}} to {drive/actual_filename.jpg} before asset processing
	 * ONLY SUPPORTS DRIVE - other assets use normal syntax
	 * 
	 * @param string &$content Template content to process
	 * @return void
	 */
	private function preprocess_nested_asset_variables(string &$content): void
	{
		// Pattern untuk menangkap HANYA drive syntax dengan nested variables
		// Hanya mendukung: {drive/{avatar}}
		// Asset lain tetap normal: {css/style.css}, {js/script.js}
		$nested_pattern = '#\{(drive)/\{([a-zA-Z0-9_.]+)\}(?:\|([^}]+))?\}#';
		
		if (preg_match_all($nested_pattern, $content, $matches, PREG_SET_ORDER)) {
			$replacements = [];
			
			foreach ($matches as $match) {
				$full_match = $match[0];          // {drive/{avatar}}
				$asset_type = $match[1];          // drive
				$variable_name = $match[2];       // avatar
				$filters = $match[3] ?? '';       // optional filters
				
				// Resolve the nested variable
				$variable_value = $this->get_variable_value($variable_name);
				
				if (!empty($variable_value)) {
					// Build the resolved asset syntax
					$resolved_syntax = '{' . $asset_type . '/' . $variable_value;
					
					// Add filters if present
					if (!empty($filters)) {
						$resolved_syntax .= '|' . $filters;
					}
					
					$resolved_syntax .= '}';
					
					// Store replacement
					$replacements[$full_match] = $resolved_syntax;
				} else {
					// Variable not found, keep original or use placeholder
					$placeholder = "<!-- NESTED_VAR_NOT_FOUND: {$variable_name} in {$full_match} -->";
					$replacements[$full_match] = $placeholder;
				}
			}
			
			// Apply all replacements at once
			if (!empty($replacements)) {
				$content = str_replace(array_keys($replacements), array_values($replacements), $content);
			}
		}
	}
	
	/**
	 * OPTIMIZED: Batch processing untuk language variables
	 */
	private function process_language_variables_batch(string &$content): void
	{
		global $lang;
		
		// OPTIMIZATION: Single regex call untuk semua language variables
		if (preg_match_all("#_lang\{([^}]*)\}#U", $content, $match_array, PREG_SET_ORDER)) {
			$replacements = [];
			foreach ($match_array as $match_val) {
				$lang_key = $match_val[1];
				$replacement = $this->bShow_language_index ? 
					(empty($lang[$lang_key]) ? $lang_key : $lang[$lang_key]) : 
					($lang[$lang_key] ?? '');
				$replacements[$match_val[0]] = $replacement;
			}
			
			// Batch replace semua language variables sekaligus
			$content = str_replace(array_keys($replacements), array_values($replacements), $content);
		}
	}
	
	/**
	 * Memuat file untuk handle, mengkompilasi file,
	 * dan menjalankan kode yang dikompilasi. Ini akan mencetak
	 * hasil dari eksekusi template.
	 */
	public function pparse_legacy(string &$content, bool $bRemove_VARS = true, bool $bReturn = true, bool $bCompress = false)
	{
		global $lang;
		
		// Proses SSI-style includes PERTAMA sebelum pemrosesan lainnya
		$this->process_ssi_includes($content);
		
		// Proses kondisional gaya PHP untuk variabel global PERTAMA
		// Ini harus dilakukan sebelum pemrosesan blok untuk menghindari konflik
		$this->process_global_conditionals($content);
		
		// FITUR BARU: Proses ternary operators sebelum pemrosesan blok
		// Mendukung syntax: {condition ? true_value : false_value}
		$this->process_ternary_operators($content);
		
		// FITUR BARU: Proses NexaAsset syntax
		$this->process_asset_syntax($content);
		
		// Proses loop blok terlebih dahulu - DIPERBAIKI: Izinkan huruf besar dan kecil
		$str_pattern = "#<!-- NEXA ([A-Za-z0-9_]*) -->([\S\W]*)<!-- END \\1 -->#U";
		if( preg_match_all( $str_pattern, $content, $match_array, PREG_SET_ORDER) )
		{
			foreach ($match_array as $index => $val)
			{
				// FIXED: Validate array keys exist before accessing
				if (!isset($val[0]) || !isset($val[1]) || !isset($val[2])) {
					continue;
				}
				
				if( isset($this->_tpldata['.'][$val[1]]) && is_array($this->_tpldata['.'][$val[1]]) )
				{
					if( preg_match_all( $str_pattern, $val[2], $match_array_sub, PREG_SET_ORDER) )
					{
						foreach ($match_array_sub as $index_sub => $val_sub)
						{
							if( isset($this->_tpldata['.'][$val_sub[1]]) && is_array($this->_tpldata['.'][$val_sub[1]]) )
							{
								$this->pparse_block($val_sub[2], $val_sub[1]);
							}
							$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
						}
						unset($index_sub, $val_sub, $match_array_sub);
					}
					$this->pparse_block($val[2], $val[1]);
				}
				$content = str_replace($val[0], $val[2], $content);
			}
			unset($index, $val, $match_array);
		}
		
		// Proses bagian - DIPERBAIKI: Izinkan huruf besar dan kecil
		$str_pattern = "#<!-- START_SECTION ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SECTION \\1 -->#U";
		if( preg_match_all( $str_pattern, $content, $match_array, PREG_SET_ORDER) )
		{
			$str_pattern_sub = "#<!-- START_SUB ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SUB \\1 -->#U";
			foreach ($match_array as $index => $val)
			{
				// parsing konten sub jika tersedia
				if( preg_match_all( $str_pattern_sub, $val[2], $match_array_sub, PREG_SET_ORDER) )
				{
					// parsing konten dari sub bagian
					foreach ($match_array_sub as $index_sub => $val_sub)
					{
						$var_name = $val_sub[1];
						global ${$var_name};
						if( ${$var_name} )
						{
							$str_pattern_child = "#<!-- START_CHILD ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_CHILD \\1 -->#U";
							if( preg_match_all( $str_pattern_child, $val_sub[2], $match_array_child, PREG_SET_ORDER) )
							{
								foreach ($match_array_child as $index_child => $val_child)
								{
									// FIXED: Validate array keys exist before accessing
									if (!isset($val_child[0]) || !isset($val_child[1]) || !isset($val_child[2])) {
										continue;
									}
									
									$child_var_name = $val_child[1];
									global ${$child_var_name};
									if( ${$child_var_name} )
									{
										$this->pparse($val_child[2], $bRemove_VARS, true, $bCompress);
									}
									else
										$val_child[2] = '';
									$val_sub[2] = str_replace($val_child[0], $val_child[2], $val_sub[2]);
								}
							}
						}
						else
							$val_sub[2] = '';
						$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
					}
				}
				
				$section_var_name = $val[1];
				global ${$section_var_name};
				// parsing konten dari bagian utama
				if( $val[1] == 'donot_compress' ) 
					$val[2] = $val[0];
				elseif( ${$section_var_name} ) {
					// Panggilan rekursif tanpa pemrosesan loop untuk menghindari rekursi tak terbatas
					$temp_content = $val[2];
					$this->pparse_variables_only( $temp_content, $bRemove_VARS, true, $bCompress );
					$val[2] = $temp_content;
					
					// tambahkan hasil parsing ke $_section
					$this->_section[$val[1]] = $val[2];
				}
				else 
					$val[2] = '';
				
				$content = str_replace($val[0], $val[2], $content);
			}
		}
		
		// Proses variabel sintaks {} modern (FITUR BARU)
		$this->process_modern_variables($content, $bRemove_VARS);
		
		// Proses variabel bahasa
		preg_match_all( "#_lang\{(.*)\}#U", $content, $match_array, PREG_SET_ORDER);
		foreach( $match_array as $match_val )
		{
			$content = str_replace( $match_val[0], 
									$this->bShow_language_index ? (empty($lang[$match_val[1]]) ? $match_val[1] : $lang[$match_val[1]]) : $lang[$match_val[1]], 
									$content 
								  );
		}

		if( !$bReturn )
		{
			 // FIXED: Prevent premature output that causes messy view-source
			 // Only echo if explicitly called from non-template context
			 if (!$this->isTemplateRenderingContext()) {
				 echo $content;
			 }
			 return true;
		}
		if( $bCompress )
			$this->compress( $content );
		$this->html_standard( $content );
		
		// Pembersihan akhir: Hapus pernyataan kondisional yang tidak diproses
		$content = preg_replace('#\{if\s+[^}]+\}#', '', $content);
		$content = preg_replace('#\{else\}#', '', $content);
		$content = preg_replace('#\{elseif\s+[^}]+\}#', '', $content);
		$content = preg_replace('#\{endif\}#', '', $content);
		
		// Transform href links yang dimulai dengan "/" di dalam <body>
		$this->transformHref($content);
		
		return trim($content);
	}
	
	/**
	 * Proses hanya variabel tanpa loop (metode pembantu untuk panggilan rekursif)
	 */
	private function pparse_variables_only(string &$content, bool $bRemove_VARS = true, bool $bReturn = true, bool $bCompress = false)
	{
		global $lang;
		
		// Proses variabel sintaks {} modern (FITUR BARU)
		$this->process_modern_variables($content, $bRemove_VARS);
		
		// Proses variabel bahasa
		preg_match_all( "#_lang\{(.*)\}#U", $content, $match_array, PREG_SET_ORDER);
		foreach( $match_array as $match_val )
		{
			$content = str_replace( $match_val[0], 
									$this->bShow_language_index ? (empty($lang[$match_val[1]]) ? $match_val[1] : $lang[$match_val[1]]) : $lang[$match_val[1]], 
									$content 
								  );
		}
		
		if( $bCompress )
			$this->compress( $content );
		$this->html_standard( $content );
		
		return trim($content);
	}
	
	public function pparse_block(string &$content, string $blockname, bool $bReturn = true)
	{
		// Temukan nama blok case-insensitive
		$actual_blockname = $this->find_case_insensitive_key($blockname);
		if ($actual_blockname === false) {
			$content = '';
			return $bReturn ? $content : true;
		}
		
		if (!isset($this->_tpldata['.'][$actual_blockname]) || !is_array($this->_tpldata['.'][$actual_blockname])) {
			$content = '';
			return $bReturn ? $content : true;
		}
		
		$block_length = count($this->_tpldata['.'][$actual_blockname]);
		$res = '';
		
		for ($i = 0; $i < $block_length; $i++) {
			$temp = $content;
			$block_data = $this->_tpldata['.'][$actual_blockname][$i];
			
			// Pastikan $block_data adalah array sebelum diproses
			if (!is_array($block_data)) {
				// Jika bukan array, konversi ke array atau lewati
				if (is_scalar($block_data)) {
					// Konversi skalar ke array dengan indeks sebagai nilai
					$block_data = ['value' => $block_data, 'index' => $i];
				} else {
					// Lewati nilai non-array, non-skalar
					continue;
				}
			}
			
			// ENHANCEMENT: Tambahkan automatic numbering variables
			$block_data['_index'] = $i;                    // 0-based index
			$block_data['_number'] = $i + 1;               // 1-based numbering
			$block_data['_count'] = $block_length;         // Total items
			$block_data['_first'] = ($i === 0);           // Is first item
			$block_data['_last'] = ($i === $block_length - 1); // Is last item
			$block_data['_odd'] = ($i % 2 === 1);          // Is odd row (1-based)
			$block_data['_even'] = ($i % 2 === 0);         // Is even row (1-based)
			
			if (!array_key_exists('no', $block_data)) {
				$block_data['no'] = $i + 1;
			}
			$block_data['index'] = $i;
			$block_data['number'] = $i + 1;
			
			// TAMBAHAN: Proses LINK syntax {link/path} dalam blok terlebih dahulu
		$this->processLinkSyntaxInBlock($temp);
		$this->processAssetsSyntaxInBlock($temp);
		
		// TAMBAHAN: Proses NX_BLOG blocks di dalam NEXA blocks dengan akses parent data
		$this->process_nx_blog_blocks_with_parent($temp, $block_data);
		
		// TAMBAHAN: Proses custom events dalam block context
		$this->processCustomEvents($temp);
			
			// ✅ FIX: Process conditionals FIRST before variable substitution
			if (is_array($block_data)) {
				$this->process_block_conditionals($temp, $block_data);
			}
			
			// FIXED: Updated regex pattern to capture dot notation for nested arrays
			// Pattern mencakup [a-zA-Z0-9_.] untuk mendukung nested array syntax seperti {images.143x110}
			preg_match_all("#\{(?!(?:switch|case|default|endswitch|if|else|elseif|endif)\b)([a-zA-Z0-9_.]+(?:\|[^}]+)?)\}#", $temp, $match_array, PREG_SET_ORDER);
			
			foreach ($match_array as $val) {
				// FIXED: Validate array keys exist before accessing
				if (!isset($val[0]) || !isset($val[1])) {
					continue;
				}
				
				$field_with_filters = $val[1];
				$replacement = '';
				
							// Parsing path variabel dan filter
			$parsed = $this->parse_variable_with_filters($field_with_filters);
			$field_name = $parsed['variable'] ?? '';
			$filters = $parsed['filters'] ?? [];
				
				// ENHANCED: Handle nested array access with dot notation
				if (strpos($field_name, '.') !== false) {
					// This is nested array access like {images.143x110}
					$replacement = $this->get_nested_value_from_block($field_name, $block_data);
					
					// FALLBACK: If not found in block data, try global nested variables
					if ($replacement === '') {
						$replacement = $this->get_nested_value($field_name);
					}
				} else {
					// Regular field access
					$actual_field = $this->find_case_insensitive_field($field_name, $block_data);
					
					if ($actual_field !== false) {
						$field_value = $block_data[$actual_field];
						
						if ($field_value === true) {
							$replacement = 'start_loop_section_' . $actual_blockname . '_' . $i;
							$global_var_name = $replacement;
							eval('global $' . $global_var_name . ';');
							eval('$' . $global_var_name . ' = true;');
						} else {
							$replacement = trim((string)$field_value);
						}
					} else {
						// FALLBACK: If variable not found in block data, check global variables
						$found_global_key = $this->find_case_insensitive_key($field_name);
						if ($found_global_key !== false) {
							$global_value = $this->_tpldata['.'][$found_global_key];
							
							// Handle different value types properly
							if (is_array($global_value)) {
								// Don't replace block variables in global context - they should be processed by loops
								$replacement = '';
							} else if (is_scalar($global_value)) {
								$replacement = (string)$global_value;
							} else if ($global_value === true) {
								$replacement = '1';
							} else if ($global_value === false) {
								$replacement = '0';
							} else if ($global_value === null) {
								$replacement = '';
							} else {
								$replacement = '';
							}
						}
					}
				}
				
				// Terapkan filter jika tersedia
				if (!empty($filters) && $this->isFilterReady()) {
					$replacement = $this->apply_filters($replacement, $filters);
				}
				
				$temp = str_replace($val[0], $replacement, $temp);
			}
			
			$res .= $temp;
		}
		
		$content = $res;
		
		if ($i > 0) {
			$global_var_name = $actual_blockname;
			eval('global $' . $global_var_name . ';');
			eval('$' . $global_var_name . ' = true;');
		}
		
		if (!$bReturn) {
			// FIXED: Prevent premature output that causes messy view-source  
			// Only echo if explicitly called from non-template context
			if (!$this->isTemplateRenderingContext()) {
				echo $content;
			}
			return true;
		}
		return $content;
	}
	
	public function include_file(string $varfile, bool $bRemove_VARS = false, bool $bReturn = true, bool $bCompress = false)
	{
		// sertakan & proses kode PHP dalam file
		ob_start();
        include $varfile;
        $content = ob_get_contents();
		ob_end_clean();
		
		// buat konten tmp baru untuk file yang disertakan ini
		$tempfile = tempnam(sys_get_temp_dir(), 'nexa_');
		$temphandle = fopen($tempfile, "w");
		fwrite($temphandle, $content);
		fclose($temphandle);
		
		// Proses file sementara secara langsung
		$content = $this->pparse_tempfile($tempfile, $bRemove_VARS, true, $bCompress);
		
		// hapus file tmp
		unlink($tempfile);
		
		return $this->pparse($content, $bRemove_VARS, true, $bCompress);
	}
	
	/**
	 * Helper method to validate preg_match result arrays
	 */
	private function validateMatchArray(array $match, int $requiredKeys = 3): bool
	{
		for ($i = 0; $i < $requiredKeys; $i++) {
			if (!isset($match[$i])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Safe access to array elements to prevent undefined key warnings
	 */
	private function safeArrayAccess(array $array, int $key, string $default = ''): string
	{
		return isset($array[$key]) ? (string)$array[$key] : $default;
	}
	
	/**
	 * Metode: pparse_tempfile - Proses file sementara dengan path absolut
	 */
	private function pparse_tempfile(string $tempfile_path, bool $bRemove_VARS = false, bool $bReturn = true, bool $bCompress = false)
	{
		// Baca konten langsung dari path absolut
		$file_content = file_get_contents($tempfile_path);
		
		// DIPERBAIKI: Izinkan huruf besar dan kecil dalam pola loop
		$str_pattern = "#<!-- NEXA ([A-Za-z0-9_]*) -->([\S\W]*)<!-- END \\1 -->#U";
		if( preg_match_all( $str_pattern, $file_content, $match_array, PREG_SET_ORDER) )
		{
			foreach ($match_array as $index => $val)
			{
				// FIXED: Validate array keys exist before accessing
				if (!isset($val[0]) || !isset($val[1]) || !isset($val[2])) {
					continue;
				}
				
				if( isset($this->_tpldata['.'][$val[1]]) && is_array($this->_tpldata['.'][$val[1]]) )
				{
					if( preg_match_all( $str_pattern, $val[2], $match_array_sub, PREG_SET_ORDER) )
					{
						foreach ($match_array_sub as $index_sub => $val_sub)
						{
							// FIXED: Validate array keys exist before accessing
							if (!isset($val_sub[0]) || !isset($val_sub[1]) || !isset($val_sub[2])) {
								continue;
							}
							
							if( isset($this->_tpldata['.'][$val_sub[1]]) && is_array($this->_tpldata['.'][$val_sub[1]]) )
							{
								$this->pparse_block($val_sub[2], $val_sub[1]);
							}
							$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
						}
						unset($index_sub, $val_sub, $match_array_sub);
					}
					$this->pparse_block($val[2], $val[1]);
				}
				$file_content = str_replace($val[0], $val[2], $file_content);
			}
			unset($index, $val, $match_array);
		}
		
		// DIPERBAIKI: Izinkan huruf besar dan kecil dalam pola bagian
		$str_pattern = "#<!-- START_SECTION ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SECTION \\1 -->#U";
		if( preg_match_all( $str_pattern, $file_content, $match_array, PREG_SET_ORDER) )
		{
			$str_pattern_sub = "#<!-- START_SUB ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SUB \\1 -->#U";
			foreach ($match_array as $index => $val)
			{
				// parsing konten sub jika tersedia
				if( preg_match_all( $str_pattern_sub, $val[2], $match_array_sub, PREG_SET_ORDER) )
				{
					// parsing konten dari sub bagian
					foreach ($match_array_sub as $index_sub => $val_sub)
					{
						// FIXED: Validate array keys exist before accessing
						if (!isset($val_sub[0]) || !isset($val_sub[1]) || !isset($val_sub[2])) {
							continue;
						}
						
						$var_name = $val_sub[1];
						global ${$var_name};
						if( ${$var_name} )
						{
							$str_pattern_child = "#<!-- START_CHILD ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_CHILD \\1 -->#U";
							if( preg_match_all( $str_pattern_child, $val_sub[2], $match_array_child, PREG_SET_ORDER) )
							{
								foreach ($match_array_child as $index_child => $val_child)
								{
									// FIXED: Validate array keys exist before accessing
									if (!isset($val_child[0]) || !isset($val_child[1]) || !isset($val_child[2])) {
										continue;
									}
									
									$child_var_name = $val_child[1];
									global ${$child_var_name};
									if( ${$child_var_name} )
									{
										$this->pparse($val_child[2], $bRemove_VARS, true, $bCompress);
									}
									else
										$val_child[2] = '';
									$val_sub[2] = str_replace($val_child[0], $val_child[2], $val_sub[2]);
								}
							}
						}
						else
							$val_sub[2] = '';
						$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
					}
				}
				
				$section_var_name = $val[1];
				global ${$section_var_name};
				// parsing konten dari bagian utama
				if( $val[1] == 'donot_compress' ) 
					$val[2] = $val[0];
				elseif( ${$section_var_name} ) {
					$this->pparse( $val[2], $bRemove_VARS, true, $bCompress );
					
					// tambahkan hasil parsing ke $_section
					$this->_section[$val[1]] = $val[2];
				}
				else 
					$val[2] = '';
				
				$file_content = str_replace($val[0], $val[2], $file_content);
			}
		}

		if( !$bReturn )
		{
			echo $this->pparse($file_content, $bRemove_VARS, true, $bCompress);
			return true;
		}
		return $this->pparse($file_content, $bRemove_VARS, true, $bCompress);
	}
	
	/**
	 * Metode: pparse_file
	 * $varfile 						- File akan diparsing
	 * $bRemove_VARS					- Jangan parsing variabel umum dalam file ini
	 * $bReturn							- Jangan cetak konten file yang diparsing
	 * $reset_files						- Reset variabel files dari template
	 */
	public function pparse_file(string $varfile, bool $bRemove_VARS = false, bool $bReturn = true, bool $bCompress = false)
	{
		// Periksa apakah file terdaftar
		if (!isset($this->files[$varfile])) {
			throw new RuntimeException("nexa->pparse_file(): File '$varfile' tidak ditambahkan. Gunakan add_file() terlebih dahulu.");
		}
		
		// Dapatkan path lengkap untuk file (cache per varfile dalam satu request)
		$full_path = $this->root . $this->files[$varfile];
		if (!isset($this->_fileReadCache[$varfile])) {
			$this->_fileReadCache[$varfile] = file_get_contents($full_path);
		}
		$file_content = $this->_fileReadCache[$varfile];
		
		// DIPERBAIKI: Izinkan huruf besar dan kecil dalam pola loop
		$str_pattern = "#<!-- NEXA ([A-Za-z0-9_]*) -->([\S\W]*)<!-- END \\1 -->#U";
		if( preg_match_all( $str_pattern, $file_content, $match_array, PREG_SET_ORDER) )
		{
			foreach ($match_array as $index => $val)
			{
				if( isset($this->_tpldata['.'][$val[1]]) && is_array($this->_tpldata['.'][$val[1]]) )
				{
					if( preg_match_all( $str_pattern, $val[2], $match_array_sub, PREG_SET_ORDER) )
					{
						foreach ($match_array_sub as $index_sub => $val_sub)
						{
							if( isset($this->_tpldata['.'][$val_sub[1]]) && is_array($this->_tpldata['.'][$val_sub[1]]) )
							{
								$this->pparse_block($val_sub[2], $val_sub[1]);
							}
							$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
						}
						unset($index_sub, $val_sub, $match_array_sub);
					}
					$this->pparse_block($val[2], $val[1]);
				}
				$file_content = str_replace($val[0], $val[2], $file_content);
			}
		}
		
		// DIPERBAIKI: Izinkan huruf besar dan kecil dalam pola bagian
		$str_pattern = "#<!-- START_SECTION ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SECTION \\1 -->#U";
		if( preg_match_all( $str_pattern, $file_content, $match_array, PREG_SET_ORDER) )
		{
			$str_pattern_sub = "#<!-- START_SUB ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_SUB \\1 -->#U";
			foreach ($match_array as $index => $val)
			{
				// parsing konten sub jika tersedia
				if( preg_match_all( $str_pattern_sub, $val[2], $match_array_sub, PREG_SET_ORDER) )
				{
					// parsing konten dari sub bagian
					foreach ($match_array_sub as $index_sub => $val_sub)
					{
						$var_name = $val_sub[1];
						global ${$var_name};
						if( ${$var_name} )
						{
							$str_pattern_child = "#<!-- START_CHILD ([A-Za-z0-9_]*) -->([\S\W]*)<!-- STOP_CHILD \\1 -->#U";
							if( preg_match_all( $str_pattern_child, $val_sub[2], $match_array_child, PREG_SET_ORDER) )
							{
								foreach ($match_array_child as $index_child => $val_child)
								{
									// FIXED: Validate array keys exist before accessing
									if (!isset($val_child[0]) || !isset($val_child[1]) || !isset($val_child[2])) {
										continue;
									}
									
									$child_var_name = $val_child[1];
									global ${$child_var_name};
									if( ${$child_var_name} )
									{
										$this->pparse($val_child[2], $bRemove_VARS, true, $bCompress);
									}
									else
										$val_child[2] = '';
									$val_sub[2] = str_replace($val_child[0], $val_child[2], $val_sub[2]);
								}
							}
						}
						else
							$val_sub[2] = '';
						$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
					}
				}
				
				$section_var_name = $val[1];
				global ${$section_var_name};
				// parsing konten dari bagian utama
				if( $val[1] == 'donot_compress' ) 
					$val[2] = $val[0];
				elseif( ${$section_var_name} ) {
					$this->pparse( $val[2], $bRemove_VARS, true, $bCompress );
					
					// tambahkan hasil parsing ke $_section
					$this->_section[$val[1]] = $val[2];
				}
				else 
					$val[2] = '';
				
				$file_content = str_replace($val[0], $val[2], $file_content);
			}
		}

		if( !$bReturn )
		{
			echo $this->pparse($file_content, $bRemove_VARS, true, $bCompress);
			return true;
		}
		return $this->pparse($file_content, $bRemove_VARS, true, $bCompress);
	}
	
	/**
	 * Proses pernyataan kondisional gaya PHP HANYA untuk konteks global
	 * Mendukung: {if condition}, {else}, {elseif condition}, {endif}
	 */
	private function process_global_conditionals(string &$content): void
	{
		// Proses kondisional IF/ELSE
		$this->process_global_if_conditionals($content);
		
		// Proses pernyataan SWITCH  
		$this->process_global_switch_statements($content);
	}
	
	/**
	 * Proses kondisional IF global (tanpa path bersarang)
	 */
	private function process_global_if_conditionals(string &$content): void
	{
		// Pendekatan sederhana: proses semua blok {if condition}...{endif}
		$iterations = 0;
		$max_iterations = 50;
		
		while ($iterations < $max_iterations) {
			$iterations++;
			$original_content = $content;
			
			// Temukan pernyataan {if} pertama
			if (preg_match('#\{if\s+([^}]+)\}#', $content, $if_match, PREG_OFFSET_CAPTURE)) {
				$if_pos = (int)$if_match[0][1];
				$condition = trim((string)$if_match[1][0]);
				
				// Lewati kondisi dengan path bersarang (titik)
				if (strpos($condition, '.') !== false) {
					// Ganti if spesifik ini dengan placeholder dan lanjutkan
					$content = substr_replace($content, '<!-- SKIP_IF -->', $if_pos, strlen($if_match[0][0]));
					continue;
				}
				
				// Temukan {endif} yang cocok
				$if_count = 1;
				$pos = $if_pos + strlen($if_match[0][0]);
				$endif_pos = false;
				
				while ($pos < strlen($content) && $if_count > 0) {
					// Cari {if} atau {endif} berikutnya
					if (preg_match('#\{(if\s+[^}]+|endif)\}#', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
						$match_pos = (int)$match[0][1];
						$match_text = (string)$match[1][0];
						
						if (strpos($match_text, 'if ') === 0) {
							$if_count++;
						} else if ($match_text === 'endif') {
							$if_count--;
							if ($if_count === 0) {
								$endif_pos = $match_pos;
								break;
							}
						}
						$pos = $match_pos + strlen($match[0][0]);
					} else {
						break;
					}
				}
				
				if ($endif_pos !== false) {
					// Ekstrak konten antara {if} dan {endif}
					$full_start = $if_pos;
					$full_end = $endif_pos + strlen('{endif}');
					$if_content = substr($content, $if_pos + strlen($if_match[0][0]), $endif_pos - ($if_pos + strlen($if_match[0][0])));
					
					// Evaluasi kondisi
					$show_content = $this->parse_simple_condition($condition);
					
					// Ganti seluruh blok {if}...{endif}
					// Catatan: nested if akan diproses di iterasi berikutnya
					$replacement = $show_content ? $if_content : '';
					$content = substr_replace($content, $replacement, $full_start, $full_end - $full_start);
				} else {
					// Tidak ditemukan endif yang cocok, ganti if dengan placeholder
					$content = substr_replace($content, '<!-- ORPHAN_IF -->', $if_pos, strlen($if_match[0][0]));
				}
			} else {
				// Tidak ada lagi pernyataan {if} yang ditemukan
				break;
			}
			
			// Jika konten tidak berubah, keluar untuk menghindari loop tak terbatas
			if ($content === $original_content) {
				break;
			}
		}
		
		// Bersihkan placeholder
		$content = str_replace(['<!-- SKIP_IF -->', '<!-- ORPHAN_IF -->'], '', $content);
	}
	
	/**
	 * Proses pernyataan SWITCH global (tanpa path bersarang)
	 */
	private function process_global_switch_statements(string &$content): void
	{
		$pattern = '#\{switch\s+([^}]+)\}(.*?)\{endswitch\}#is';
		
		$processed = [];
		while (preg_match($pattern, $content, $matches)) {
			$full_match = $matches[0];
			
			// Hindari loop tak terbatas - lewati jika sudah diproses
			if (in_array($full_match, $processed)) {
				break;
			}
			
			$switch_var = trim($matches[1]);
			$switch_content = $matches[2];
			
			// Dapatkan nilai variabel (termasuk nested properties seperti user.settings.theme)
			$switch_value = $this->get_variable_value($switch_var);
			
			$result = '';
			$case_found = false;
			
			// IMPROVED: Pattern untuk parsing case yang lebih robust
			// Menangkap semua case statements termasuk yang sebelum default
			$case_pattern = '#\{case\s+([^}]+)\}(.*?)(?=\{(?:case\s+[^}]+|default|endswitch)\})#is';
			if (preg_match_all($case_pattern, $switch_content, $case_matches, PREG_SET_ORDER)) {
				foreach ($case_matches as $case_match) {
					$case_value = trim($case_match[1], '"\'');
					$case_content = $case_match[2];
					
					// IMPROVED: Gunakan strict comparison dan pastikan tipe data konsisten
					if ((string)$switch_value === (string)$case_value) {
						$result = $case_content;
						$case_found = true;
						break;
					}
				}
			}
			
			// FIXED: Simplified approach untuk default case - cari {default} dan ambil konten setelahnya
			if (!$case_found) {
				// Cari posisi {default}
				$default_pos = strpos($switch_content, '{default}');
				if ($default_pos !== false) {
					// Ambil konten setelah {default}
					$after_default = substr($switch_content, $default_pos + 9); // 9 = length of '{default}'
					
					// Cari posisi {endswitch} untuk menentukan batas akhir
					$endswitch_pos = strpos($after_default, '{endswitch}');
					if ($endswitch_pos !== false) {
						$result = trim(substr($after_default, 0, $endswitch_pos));
					} else {
						$result = trim($after_default);
					}
				}
			}
			
			$content = str_replace($matches[0], $result, $content);
			$processed[] = $full_match;
		}
	}
	
	/**
	 * Proses pernyataan kondisional gaya PHP
	 * Mendukung: {if condition}, {else}, {elseif condition}, {endif}
	 */
	private function process_conditionals(string &$content): void
	{
		// Proses pernyataan if/else/elseif bersarang
		$pattern = '#\{if\s+([^}]+)\}(.*?)(?:\{elseif\s+([^}]+)\}(.*?))*(?:\{else\}(.*?))?\{endif\}#is';
		
		while (preg_match($pattern, $content, $matches)) {
			$condition = trim($matches[1]);
			$if_content = $matches[2];
			$else_content = isset($matches[5]) ? $matches[5] : '';
			
			// Tangani kondisi elseif (dukungan dasar)
			$elseif_conditions = [];
			if (preg_match_all('#\{elseif\s+([^}]+)\}(.*?)(?=\{elseif|\{else\}|\{endif\})#is', $matches[0], $elseif_matches, PREG_SET_ORDER)) {
				foreach ($elseif_matches as $elseif_match) {
					$elseif_conditions[] = [
						'condition' => trim($elseif_match[1]),
						'content' => $elseif_match[2]
					];
				}
			}
			
			$result = $this->evaluate_condition($condition, $if_content, $else_content, $elseif_conditions);
			$content = str_replace($matches[0], $result, $content);
		}
	}
	
	/**
	 * Proses pernyataan switch gaya PHP
	 * Mendukung: {switch variable}, {case 'value'}, {default}, {endswitch}
	 */
	private function process_switch_statements(string &$content): void
	{
		$pattern = '#\{switch\s+([^}]+)\}(.*?)\{endswitch\}#is';
		
		while (preg_match($pattern, $content, $matches)) {
			$switch_var = trim($matches[1]);
			$switch_content = $matches[2];
			
			// Dapatkan nilai variabel
			$switch_value = $this->get_variable_value($switch_var);
			
			$result = '';
			$case_found = false;
			
			// IMPROVED: Pattern untuk parsing case yang lebih robust
			// Menangkap semua case statements termasuk yang sebelum default
			$case_pattern = '#\{case\s+([^}]+)\}(.*?)(?=\{(?:case\s+[^}]+|default|endswitch)\})#is';
			if (preg_match_all($case_pattern, $switch_content, $case_matches, PREG_SET_ORDER)) {
				foreach ($case_matches as $case_match) {
					$case_value = trim($case_match[1], '"\'');
					$case_content = $case_match[2];
					
					// IMPROVED: Gunakan strict comparison dan pastikan tipe data konsisten
					if ((string)$switch_value === (string)$case_value) {
						$result = $case_content;
						$case_found = true;
						break;
					}
				}
			}
			
			// FIXED: Simplified approach untuk default case - cari {default} dan ambil konten setelahnya
			if (!$case_found) {
				// Cari posisi {default}
				$default_pos = strpos($switch_content, '{default}');
				if ($default_pos !== false) {
					// Ambil konten setelah {default}
					$after_default = substr($switch_content, $default_pos + 9); // 9 = length of '{default}'
					
					// Cari posisi {endswitch} untuk menentukan batas akhir
					$endswitch_pos = strpos($after_default, '{endswitch}');
					if ($endswitch_pos !== false) {
						$result = trim(substr($after_default, 0, $endswitch_pos));
					} else {
						$result = trim($after_default);
					}
				}
			}
			
			$content = str_replace($matches[0], $result, $content);
		}
	}
	
	/**
	 * Evaluasi kondisi dan kembalikan konten yang sesuai
	 */
	private function evaluate_condition(string $condition, string $if_content, string $else_content, array $elseif_conditions = []): string
	{
		// Evaluasi kondisi sederhana
		$result = $this->parse_simple_condition($condition);
		
		if ($result) {
			return $if_content;
		}
		
		// Periksa kondisi elseif
		foreach ($elseif_conditions as $elseif) {
			if ($this->parse_simple_condition($elseif['condition'])) {
				return $elseif['content'];
			}
		}
		
		return $else_content;
	}
	
	/**
	 * Parsing kondisi sederhana seperti perbandingan variabel
	 */
	private function parse_simple_condition(string $condition): bool
	{
		$condition = trim($condition);
		
		// Tangani keberadaan variabel sederhana (termasuk notasi titik)
		if (preg_match('/^[a-zA-Z0-9_\.]+$/', $condition)) {
			$value = $this->get_variable_value($condition);
			return !empty($value);
		}
		
		// Tangani perbandingan: variable == 'value' (termasuk notasi titik)
		if (preg_match('/^([a-zA-Z0-9_\.]+)\s*(==|!=|>|<|>=|<=)\s*[\'"]?([^\'"\s]+)[\'"]?$/', $condition, $matches)) {
			$var_name = $matches[1];
			$operator = $matches[2];
			$compare_value = $matches[3];
			
			$var_value = $this->get_variable_value($var_name);
			
			switch ($operator) {
				case '==': return $var_value == $compare_value;
				case '!=': return $var_value != $compare_value;
				case '>': return $var_value > $compare_value;
				case '<': return $var_value < $compare_value;
				case '>=': return $var_value >= $compare_value;
				case '<=': return $var_value <= $compare_value;
				default: return false;
			}
		}
		
		// Tangani fungsi isset() (termasuk notasi titik)
		if (preg_match('/^isset\(([a-zA-Z0-9_\.]+)\)$/', $condition, $matches)) {
			$var_name = $matches[1];
			$value = $this->get_variable_value($var_name);
			return $value !== '';
		}
		
		// Tangani fungsi empty() (termasuk notasi titik)
		if (preg_match('/^empty\(([a-zA-Z0-9_\.]+)\)$/', $condition, $matches)) {
			$var_name = $matches[1];
			$value = $this->get_variable_value($var_name);
			return empty($value);
		}
		
		return false;
	}
	
	/**
	 * Dapatkan nilai variabel dari data template (diperluas untuk kondisional)
	 */
	private function get_variable_value(string $var_name): mixed
	{

		
		// Pertama periksa apakah kita dalam konteks blok dan variabel ada dalam data blok
		if (isset($this->current_block_data)) {
			// Periksa apakah itu path bersarang (berisi titik) dalam data blok
			if (strpos($var_name, '.') !== false) {
				$nested_value = $this->get_nested_value_from_block($var_name, $this->current_block_data);
				if ($nested_value !== '') {
					return $nested_value;
				}
			} else {
				// Akses variabel sederhana dalam data blok
				$found_key = $this->find_case_insensitive_key_in_array($var_name, $this->current_block_data);
				if ($found_key !== false) {
					return $this->current_block_data[$found_key];
				}
			}
		}
		
		// Periksa apakah itu path bersarang (berisi titik) dalam data global
		if (strpos($var_name, '.') !== false) {
			// Untuk data template global
			if (isset($this->_tpldata['.'])) {
				return $this->get_nested_value($var_name);
			}
		} else {
			// Akses variabel sederhana dalam data global
			if (isset($this->_tpldata['.'][$var_name])) {
				return $this->_tpldata['.'][$var_name];
			}
			
			// Coba case-insensitive
			$found_key = $this->find_case_insensitive_key($var_name);
			if ($found_key !== false) {
				return $this->_tpldata['.'][$found_key];
			}
		}
		
		// Periksa variabel global
		if (isset($GLOBALS[$var_name])) {
			return $GLOBALS[$var_name];
		}
		
		return '';
	}
	
	/**
	 * OPTIMIZED: Proses variabel sintaks {} modern dengan batch processing
	 * Enhanced dengan dukungan LINK dan ASSETS path syntax: {link/path}, {assets/path}
	 */
	private function process_modern_variables(string &$content, bool $bRemove_VARS): void
	{
		// OPTIMIZATION 1: Early return jika tidak ada variabel untuk diproses
		if (strpos($content, '{') === false) {
			return;
		}
		
		// OPTIMIZATION 2: Batch processing - kumpulkan semua replacements sekaligus
		$replacements = [];
		
		// Proses notasi LINK path: {link/path}
		if (strpos($content, '{link/') !== false) {
			preg_match_all("#\{(link/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#", $content, $link_matches, PREG_SET_ORDER);
			
			foreach ($link_matches as $match_val) {
				if (!isset($match_val[0]) || !isset($match_val[1])) {
					continue;
				}
				
				$var_path_with_filters = $match_val[1];
				$parsed = $this->parse_variable_with_filters($var_path_with_filters);
				$var_path = $parsed['variable'];
				$filters = $parsed['filters'];
				
				if (strpos($var_path, 'link/') === 0) {
					$link_path = substr($var_path, 5);
					$replacement = $this->generate_url_with_path($link_path);
					
					if (!empty($filters) && $this->isFilterReady()) {
						$replacement = $this->apply_filters($replacement, $filters);
					}
					
					$replacements[$match_val[0]] = $replacement;
				}
			}
		}
		
		// Proses notasi ASSETS path: {assets/path} - Enhanced with NexaAsset
		if (strpos($content, '{assets/') !== false) {
			preg_match_all("#\{(assets/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#", $content, $assets_matches, PREG_SET_ORDER);
			
			foreach ($assets_matches as $match_val) {
				if (!isset($match_val[0]) || !isset($match_val[1])) {
					continue;
				}
				
				$var_path_with_filters = $match_val[1];
				$parsed = $this->parse_variable_with_filters($var_path_with_filters);
				$var_path = $parsed['variable'];
				$filters = $parsed['filters'];
				
				// Handle ASSETS path syntax: assets/path -> generate_url_with_path(assets/path)
				if (strpos($var_path, 'assets/') === 0) {
					// Keep the full assets path including 'assets/' prefix
					$replacement = $this->generate_url_with_path($var_path);
					
					// Terapkan filter jika tersedia
					if (!empty($filters) && $this->isFilterReady()) {
						$replacement = $this->apply_filters($replacement, $filters);
					}
					
					$replacements[$match_val[0]] = $replacement;
				}
			}
		}
		
		// Batch apply semua LINK dan ASSETS replacements
		if (!empty($replacements)) {
			$content = str_replace(array_keys($replacements), array_values($replacements), $content);
		}
		
		// PERBAIKAN: Proses variabel dengan filter - pattern yang lebih luas
		preg_match_all("#\{([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\|[^}]+)?)\}#", $content, $match_array, PREG_SET_ORDER);
		foreach ($match_array as $match_val) {
			// FIXED: Validate array keys exist before accessing
			if (!isset($match_val[0]) || !isset($match_val[1])) {
				continue;
			}
			
			$var_path_with_filters = $match_val[1];
			$replacement = '';
			$variableFound = false;
			
			// Parsing path variabel dan filter
			$parsed = $this->parse_variable_with_filters($var_path_with_filters);
			$var_path = $parsed['variable'];
			$filters = $parsed['filters'];
			
			// Dapatkan nilai variabel
			if (strpos($var_path, '.') !== false) {
				// Path bersarang (berisi titik)
				$replacement = $this->get_nested_value($var_path);
				$variableFound = ($replacement !== ''); // Consider found if nested value exists
			} else {
				// Variabel sederhana (tanpa titik)
				$found_key = $this->find_case_insensitive_key($var_path);
				if ($found_key !== false) {
					$variableFound = true;
					$value = $this->_tpldata['.'][$found_key];
					
					// Tangani tipe nilai yang berbeda dengan benar
					if (is_array($value)) {
						// Jangan ganti variabel blok dalam konteks global - mereka harus diproses oleh loop
						$replacement = '';
					} else if (is_scalar($value)) {
						$replacement = (string)$value;
					} else if ($value === true) {
						$replacement = '1';
					} else if ($value === false) {
						$replacement = '0';
					} else if ($value === null) {
						$replacement = '';
					} else {
						$replacement = '';
					}
				}
			}
			
			// PERBAIKAN UTAMA: Pastikan filter diterapkan jika variable ditemukan dan filter ready
			if ($variableFound && !empty($filters) && $this->isFilterReady()) {
				$replacement = $this->apply_filters($replacement, $filters);
			}
			
			// FIXED LOGIC: Only keep original if variable not found AND not removing vars
			if (!$variableFound && !$bRemove_VARS) {
				$replacement = $match_val[0];
			}
			
			$content = str_replace($match_val[0], $replacement, $content);
		}
		
		// Clean up unused {variables} if removing vars
		if ($bRemove_VARS) {
			$content = preg_replace("#\{([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\|[^}]+)?)\}#", '', $content);
			$content = preg_replace("#\{(link/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#", '', $content);
			$content = preg_replace("#\{(assets/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#", '', $content);
		}
	}
	


	
	/**
	 * Fallback base URL generation jika dynamic method tidak tersedia
	 * 
	 * @return string Fallback base URL
	 */
	private function getFallbackBaseUrl(): string
	{
		// Handle CLI environment
		if (php_sapi_name() === 'cli') {
			return 'http://localhost';
		}
		
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
		
		// Remove any trailing dots from host
		$host = rtrim($host, '.');
		
		// Deteksi base directory dari SCRIPT_NAME
		$baseDir = '';
		$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
		$baseDir = dirname($scriptName);
		if ($baseDir === '/' || $baseDir === '\\' || $baseDir === '.') {
			$baseDir = '';
		}
		
		// Build final URL dengan normalisasi yang lebih baik
		$finalUrl = $protocol . '://' . $host;
		if (!empty($baseDir)) {
			$baseDir = trim($baseDir, '/\\');
			if (!empty($baseDir)) {
				$finalUrl .= '/' . $baseDir;
			}
		}
		
		// Pastikan tidak ada double slash dan tidak berakhir dengan slash
		$finalUrl = rtrim($finalUrl, '/');
		
		return $finalUrl;
	}
	
	/**
	 * Generate URL dengan path menggunakan Nexa.php function url()
	 * 
	 * @param string $path Path yang akan ditambahkan ke base URL
	 * @return string URL lengkap
	 */
	private function generate_url_with_path(string $path): string
	{
		try {
			// Gunakan Nexa::staticUrl() jika tersedia
			if (class_exists('\App\System\Nexa')) {
				return \App\System\Nexa::staticUrl($path);
			}
		} catch (\Throwable $e) {
			// Continue to fallback
		}
		
		// Fallback manual URL generation
		$baseUrl = $this->getFallbackBaseUrl();
		$cleanPath = ltrim($path, '/');
		
		// Pastikan base URL tidak kosong
		if (empty($baseUrl)) {
			$baseUrl = 'http://localhost';
		}
		
		// Pastikan base URL tidak berakhir dengan slash
		$baseUrl = rtrim($baseUrl, '/');
		
		// Bangun URL lengkap dengan pemisah yang benar
		if (!empty($cleanPath)) {
			return $baseUrl . '/' . $cleanPath;
		} else {
			return $baseUrl;
		}
	}
	
	/**
	 * Parsing sintaks variabel dengan filter: variable|filter1|filter2:arg1,arg2
	 */
	private function parse_variable_with_filters(string $var_with_filters): array
	{
		// Pisahkan dengan | untuk memisahkan variabel dari filter
		$parts = explode('|', $var_with_filters);
		$variable = @array_shift($parts); // Bagian pertama adalah variabel
		
		$filters = [];
		foreach ($parts as $filter_string) {
			$filter_string = trim($filter_string);
			if (empty($filter_string)) continue;
			
			// Parsing nama filter dan argumen (filter:arg1,arg2)
			if (strpos($filter_string, ':') !== false) {
				list($name, $args) = explode(':', $filter_string, 2);
				$args = array_map('trim', explode(',', $args));
			} else {
				$name = $filter_string;
				$args = [];
			}
			
			$filters[] = [
				'name' => trim($name),
				'args' => $args
			];
		}
		
		return [
			'variable' => trim($variable),
			'filters' => $filters
		];
	}
	
	/**
	 * Terapkan filter ke nilai menggunakan NexaFilter dengan lazy loading
	 */
	private function apply_filters(string $value, array $filters): string
	{
		$filter = $this->getFilterLazy();
		if ($filter === null) {
			return $value;
		}
		
		foreach ($filters as $filter_config) {
			$value = $filter->Filter($value, $filter_config['name'], $filter_config['args']);
		}
		
		return $value;
	}
	
	/**
	 * Dapatkan nilai bersarang dari path notasi titik
	 */
	private function get_nested_value(string $path): string
	{
		$parts = explode('.', $path);
		$current_data = $this->_tpldata['.'] ?? [];
		
		foreach ($parts as $part) {
			// Coba pencarian case-insensitive
			$found_key = $this->find_case_insensitive_key_in_array($part, $current_data);
			
			if ($found_key !== false && isset($current_data[$found_key])) {
				$current_data = $current_data[$found_key];
			} else {
				return ''; // Path tidak ditemukan
			}
		}
		
		// Kembalikan nilai akhir sebagai string
		return is_scalar($current_data) ? (string)$current_data : '';
	}
	
	/**
	 * Temukan kunci case-insensitive dalam array tertentu
	 */
	private function find_case_insensitive_key_in_array(string $search_key, array $data): string|false
	{
		// Pertama coba pencocokan tepat
		if (isset($data[$search_key])) {
			return $search_key;
		}
		
		// Coba pencarian case-insensitive
		$search_lower = strtolower($search_key);
		foreach ($data as $key => $value) {
			// Konversi kunci ke string sebelum menggunakan strtolower untuk menghindari TypeError
			$key_str = (string)$key;
			if (strtolower($key_str) === $search_lower) {
				return $key_str;
			}
		}
		
		return false;
	}
	
	/**
	 * Temukan kunci case-insensitive dalam data template
	 */
	private function find_case_insensitive_key(string $search_key): string|false
	{
		if (!isset($this->_tpldata['.'])) {
			return false;
		}
		
		// Pertama coba pencocokan tepat
		if (isset($this->_tpldata['.'][$search_key])) {
			return $search_key;
		}
		
		// Coba pencarian case-insensitive
		$search_lower = strtolower($search_key);
		foreach ($this->_tpldata['.'] as $key => $value) {
			// Konversi kunci ke string sebelum menggunakan strtolower untuk menghindari TypeError
			$key_str = (string)$key;
			if (strtolower($key_str) === $search_lower) {
				return $key_str;
			}
		}
		
		return false;
	}
	
	/**
	 * Temukan field case-insensitive dalam data blok
	 */
	private function find_case_insensitive_field(string $search_field, array $block_data): string|false
	{
		// Pertama coba pencocokan tepat
		if (isset($block_data[$search_field])) {
			return $search_field;
		}
		
		// Coba pencarian case-insensitive
		$search_lower = strtolower($search_field);
		foreach ($block_data as $key => $value) {
			// Konversi kunci ke string sebelum menggunakan strtolower untuk menghindari TypeError
			$key_str = (string)$key;
			if (strtolower($key_str) === $search_lower) {
				return $key_str;
			}
		}
		
		return false;
	}
	
	/**
	 * Dapatkan nilai bersarang dari path notasi titik dalam data blok
	 */
	private function get_nested_value_from_block(string $path, array $block_data): string
	{
		$parts = explode('.', $path);
		$current_data = $block_data;
		
		foreach ($parts as $part) {
			// Coba pencarian case-insensitive
			$found_key = $this->find_case_insensitive_key_in_array($part, $current_data);
			
			if ($found_key !== false && isset($current_data[$found_key])) {
				$current_data = $current_data[$found_key];
			} else {
				return ''; // Path tidak ditemukan
			}
		}
		
		// Kembalikan nilai akhir sebagai string
		return is_scalar($current_data) ? (string)$current_data : '';
	}
	
	/**
	 * Proses kondisional dalam iterasi blok
	 */
	private function process_block_conditionals(string &$content, array $block_data): void
	{
		// Simpan data blok saat ini untuk akses selama evaluasi kondisional
		$this->current_block_data = $block_data;
		

		
		// TAMBAHAN: Proses LINK syntax {link/path} dalam conditional blocks
		$this->processLinkSyntaxInBlock($content);
		$this->processAssetsSyntaxInBlock($content);
		
		// TAMBAHAN: Proses NexaAsset syntax dalam conditional blocks
		$this->processAssetSyntaxInBlock($content);


		
		// FITUR BARU: Proses ternary operators dalam konteks blok
		$this->process_ternary_operators($content);
		
		// Proses kondisional dengan konteks blok - STABLE VERSION

		$this->process_if_statements_stable($content);
		$this->process_switch_statements_stable($content);
		
		// Clear the block data
		unset($this->current_block_data);
	}
	
	/**
	 * Proses direktif include gaya SSI: <!--#include="file" -->
	 * ENHANCED: Better template processing untuk included files
	 */
	private function process_ssi_includes(string &$content): void
	{
		// Pola untuk mencocokkan <!--#include="file" --> atau <!--#include='file' -->
		$pattern = '/<!--#include=["\']([^"\']+)["\'][^>]*-->/i';
		
		$max_iterations = 10; // Prevent infinite recursion
		$iteration = 0;
		
		while (preg_match($pattern, $content, $matches) && $iteration < $max_iterations) {
			$iteration++;
			$full_match = $matches[0];
			$include_file = $matches[1];
			$include_result = '';
			
			// Bangun path lengkap
			$full_path = $this->root . $include_file;
			
			// Periksa apakah file ada
			if (file_exists($full_path)) {
				try {
					// Baca konten file
					$file_content = file_get_contents($full_path);
					
					// Tentukan tipe file dan proses sesuai
					$file_extension = strtolower(pathinfo($include_file, PATHINFO_EXTENSION));
					
					if ($file_extension === 'php') {
						// Eksekusi file PHP dan tangkap output
						ob_start();
						include $full_path;
						$include_result = ob_get_clean();
						
						// ENHANCED: Proses hasil PHP output juga sebagai template
						if (!empty($include_result)) {
							$this->process_ssi_includes($include_result);
							$include_result = $this->pparse_content_only($include_result);
						}
					} else {
						// Untuk file HTML/template, proses dengan nexa
						$include_result = $file_content;
						
						// ENHANCED: Debug info untuk troubleshooting
						if (strpos($include_result, '{formModal2') !== false) {
							// Log bahwa kita memproses file dengan formModal2 variables
							error_log("SSI Include: Processing {$include_file} with formModal2 variables");
							
							// Debug: Check if formModal2 data exists
							if (isset($this->_tpldata['.']['formModal2'])) {
								error_log("SSI Include: formModal2 data available with " . count($this->_tpldata['.']['formModal2']) . " items");
							} else {
								error_log("SSI Include: formModal2 data NOT available");
							}
						}
						
						// Rekursif proses SSI includes dalam file yang disertakan
						$this->process_ssi_includes($include_result);
						
						// ENHANCED: Proses konten yang disertakan dengan full template processing
						$include_result = $this->pparse_content_only($include_result);
					}
				} catch (Exception $e) {
					// Pada error, ganti dengan pesan error yang lebih informatif
					$include_result = "<!-- Error SSI Include: {$include_file} - {$e->getMessage()} -->";
					error_log("SSI Include Error: {$include_file} - {$e->getMessage()}");
				}
			} else {
				// File tidak ditemukan, ganti dengan komentar
				$include_result = "<!-- SSI Include: File tidak ditemukan - {$include_file} (Path: {$full_path}) -->";
				error_log("SSI Include: File not found - {$full_path}");
			}
			
			// Ganti direktif include dengan hasil
			$content = str_replace($full_match, $include_result, $content);
		}
		
		// ENHANCED: Warning jika terlalu banyak iterasi
		if ($iteration >= $max_iterations) {
			error_log("SSI Include: Maximum iterations reached, possible infinite recursion");
		}
	}
	
	/**
	 * Proses konten tanpa SSI includes (pembantu untuk pemrosesan rekursif)
	 * ENHANCED: Full template processing untuk included files
	 */
	private function pparse_content_only(string $content): string
	{
		global $lang;
		
		// ENHANCED: Proses kondisional gaya PHP untuk variabel global - gunakan stable version
		$this->process_global_conditionals_stable($content);
		
		// ENHANCED: Proses ternary operators dalam included content
		$this->process_ternary_operators($content);
		
		// ENHANCED: Proses NexaAsset syntax dalam included content
		$this->process_asset_syntax($content);
		
		// Proses loop blok - ENHANCED dengan validasi yang lebih baik
		$str_pattern = "#<!-- NEXA ([A-Za-z0-9_]*) -->([\S\W]*)<!-- END \\1 -->#U";
		if( preg_match_all( $str_pattern, $content, $match_array, PREG_SET_ORDER) )
		{
			foreach ($match_array as $index => $val)
			{
				// ENHANCED: Validasi array keys sebelum akses
				if (!isset($val[0]) || !isset($val[1]) || !isset($val[2])) {
					continue;
				}
				
				if( isset($this->_tpldata['.'][$val[1]]) && is_array($this->_tpldata['.'][$val[1]]) )
				{
					// ENHANCED: Proses nested blocks dalam included content
					if( preg_match_all( $str_pattern, $val[2], $match_array_sub, PREG_SET_ORDER) )
					{
						foreach ($match_array_sub as $index_sub => $val_sub)
						{
							if (!isset($val_sub[0]) || !isset($val_sub[1]) || !isset($val_sub[2])) {
								continue;
							}
							
							if( isset($this->_tpldata['.'][$val_sub[1]]) && is_array($this->_tpldata['.'][$val_sub[1]]) )
							{
								$this->pparse_block($val_sub[2], $val_sub[1]);
							}
							$val[2] = str_replace($val_sub[0], $val_sub[2], $val[2]);
						}
					}
					
					$this->pparse_block($val[2], $val[1]);
				}
				$content = str_replace($val[0], $val[2], $content);
			}
		}
		
		// ENHANCED: Proses sections dalam included content
		$this->process_sections_optimized($content);
		
		// ENHANCED: Proses variabel sintaks {} modern dengan batch processing
		$this->process_modern_variables($content, false); // Don't remove vars in included content
		
		// ENHANCED: Batch processing untuk language variables
		$this->process_language_variables_batch($content);
		
		// ENHANCED: Transform href links
		$this->transformHref($content);
		
		return trim($content);
	}
	

	
	/**
	 * Alias untuk assign_block_vars dengan nama yang lebih deskriptif
	 * 
	 * @param string $blockname Nama blok
	 * @param array $data Data array
	 * @return bool
	 */
	public function assign_block(string $blockname, array $data): bool
	{
		return $this->assign_block_vars($blockname, $data);
	}
	

	public function nexa_block_vars(string $blockname, array $data): bool
	{
		return $this->assign_block_vars($blockname, $data);
	}


	public function nexa_assign_block(string $blockname, array $data): bool
	{
		return $this->assign_block_vars($blockname, $data);
	}



	

	/**
	 * Bulk assignment untuk multiple blocks sekaligus
	 * 
	 * Usage: 
	 * $nexa->assign_blocks([
	 *     'posts' => Storage::getAll(),
	 *     'categories' => Category::getAll(),
	 *     'users' => User::getActive()
	 * ]);
	 * 
	 * @param array $blocks Array dengan key = block name, value = data array
	 * @return bool
	 */
	public function assign_blocks(array $blocks): bool
	{
		foreach ($blocks as $blockname => $data) {
			if (is_string($blockname) && is_array($data)) {
				$this->assign_block_vars($blockname, $data);
			}
		}
		return true;
	}
	
	/**
	 * Assign block dengan automatic numbering yang sudah ditambahkan
	 * 
	 * Usage:
	 * $nexa->assign_numbered_block('posts', [
	 *     ['title' => 'Post 1', 'content' => 'Content 1'],
	 *     ['title' => 'Post 2', 'content' => 'Content 2']
	 * ]);
	 * 
	 * Template akan memiliki akses ke: {no}, {_number}, {_index}, {_first}, {_last}, {_odd}, {_even}, {_count}
	 * 
	 * @param string $blockname Nama blok
	 * @param array $data Data array
	 * @param int $start_number Starting number (default: 1)
	 * @return bool
	 */
	public function assign_numbered_block(string $blockname, array $data, int $start_number = 1): bool
	{
		if (!is_array($data)) {
			throw new InvalidArgumentException("assign_numbered_block(): data harus berupa array.");
		}
		
		// Pre-process data dengan menambahkan numbering
		$numbered_data = [];
		$total_count = count($data);
		
		foreach ($data as $index => $item) {
			if (is_array($item)) {
				// Add numbering to existing item
				$item['_index'] = $index;
				$item['_number'] = $index + $start_number;
				$item['_count'] = $total_count;
				$item['_first'] = ($index === 0);
				$item['_last'] = ($index === $total_count - 1);
				$item['_odd'] = (($index + $start_number) % 2 === 1);
				$item['_even'] = (($index + $start_number) % 2 === 0);
				
				// Alternative naming
				$item['no'] = $index + $start_number;
				$item['index'] = $index;
				$item['number'] = $index + $start_number;
				
				$numbered_data[] = $item;
			} else {
				// Convert scalar to array with numbering
				$numbered_data[] = [
					'value' => $item,
					'_index' => $index,
					'_number' => $index + $start_number,
					'_count' => $total_count,
					'_first' => ($index === 0),
					'_last' => ($index === $total_count - 1),
					'_odd' => (($index + $start_number) % 2 === 1),
					'_even' => (($index + $start_number) % 2 === 0),
					'no' => $index + $start_number,
					'index' => $index,
					'number' => $index + $start_number
				];
			}
		}
		
		return $this->assign_block_vars($blockname, $numbered_data);
	}
	
	/**
	 * Get filter instance for debugging
	 */
	public function getFilter(): ?NexaFilter
	{
		return $this->getFilterLazy();
	}
	
	/**
	 * Check if filter is initialized
	 */
	public function isFilterReady(): bool
	{
		return $this->getFilterLazy() !== null;
	}
	
	/**
	 * Lazy load NexaAsset - hanya load saat dibutuhkan
	 */
	private function getAssetLazy(): ?NexaAsset
	{
		if ($this->asset === null) {
			$this->initializeAsset();
		}
		return $this->asset;
	}
	
	/**
	 * Initialize NexaAsset with optimized error handling
	 */
	private function initializeAsset(): void
	{
		try {
			// Try with composer autoloader first
			if (class_exists('\App\System\Helpers\NexaAsset')) {
				$this->asset = new \App\System\Helpers\NexaAsset();
			} else {
				throw new \Exception('NexaAsset class not found');
			}
		} catch (\Throwable $e) {
			// Silent fail - asset akan tetap null jika tidak bisa diload
			$this->asset = null;
		}
	}
	
	/**
	 * Check if asset helper is ready
	 */
	public function isAssetReady(): bool
	{
		return $this->getAssetLazy() !== null;
	}
	
	/**
	 * Get NexaAsset instance for external use
	 */
	public function getAsset(): ?NexaAsset
	{
		return $this->getAssetLazy();
	}
	
	/**
	 * Manual filter application for testing
	 */
	public function testFilter(string $value, string $filterName, $args = []): string
	{
		if ($this->filter === null) {
			return $value;
		}
		
		// Convert string args to array for compatibility
		if (is_string($args)) {
			$args = [$args];
		} elseif (!is_array($args)) {
			$args = [];
		}
		
		return $this->filter->Filter($value, $filterName, $args);
	}
	
	/**
	 * Public method to parse variable with filters (for testing)
	 */
	public function parseVariableWithFilters(string $var_with_filters): array
	{
		return $this->parse_variable_with_filters($var_with_filters);
	}
	
	/**
	 * Proses ternary operator: {condition ? true_value : false_value}
	 * Mendukung berbagai operator perbandingan dan nested expressions
	 * 
	 * Examples:
	 * {status === 'publish' ? '✅ Published' : '📝 Draft'}
	 * {count > 10 ? 'many' : 'few'}
	 * {user.role === 'admin' ? 'Administrator' : 'User'}
	 */
	private function process_ternary_operators(string &$content): void
	{
		[$work, $protected] = $this->extractProtectedScriptStyleBlocks($content);

		// IMPROVED: Pattern yang menangani multi-line dan whitespace
		$pattern = '#\{([^{}]*?\?[^{}]*?:[^{}]*?)\}#s';
		
		$iterations = 0;
		$max_iterations = 50; // Prevent infinite loops
		
		while ($iterations < $max_iterations && preg_match($pattern, $work)) {
			$iterations++;
			$original_content = $work;
			
			if (preg_match_all($pattern, $work, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					$full_match = $match[0];
					$expression = trim(preg_replace('/\s+/', ' ', $match[1])); // Normalize whitespace
					
					// Skip if this doesn't look like a ternary operator
					if (substr_count($expression, '?') < 1 || substr_count($expression, ':') < 1) {
						continue;
					}
					
					// FIXED: Proper nested ternary parsing
					$parsed = $this->parse_ternary_expression($expression);
					if ($parsed === false) {
						continue;
					}
					
					$condition = $parsed['condition'];
					$true_value = $parsed['true_value'];
					$false_value = $parsed['false_value'];
					
					// Evaluasi kondisi
					$condition_result = $this->evaluate_ternary_condition($condition);
					
					// Pilih nilai berdasarkan hasil kondisi
					$selected_value = $condition_result ? $true_value : $false_value;
					
					// Proses nilai yang dipilih (mungkin mengandung variabel atau nested ternary)
					$processed_value = $this->process_ternary_value($selected_value);
					
					// Ganti expression dengan hasil
					$work = str_replace($full_match, $processed_value, $work);
				}
			}
			
			// Jika konten tidak berubah, keluar untuk menghindari loop tak terbatas
			if ($work === $original_content) {
				break;
			}
		}

		$content = $this->restoreProtectedScriptStyleBlocks($work, $protected);
	}
	
	/**
	 * Parse ternary expression dengan menangani nested ternary
	 * Format: condition ? true_value : false_value
	 */
	private function parse_ternary_expression(string $expression): array|false
	{
		$question_pos = strpos($expression, '?');
		if ($question_pos === false) {
			return false;
		}
		
		$condition = trim(substr($expression, 0, $question_pos));
		$values_part = trim(substr($expression, $question_pos + 1));
		
		// Find the main colon (not inside nested ternary or quotes)
		$colon_pos = $this->find_main_colon_smart($values_part);
		if ($colon_pos === false) {
			return false;
		}
		
		$true_value = trim(substr($values_part, 0, $colon_pos));
		$false_value = trim(substr($values_part, $colon_pos + 1));
		
		return [
			'condition' => $condition,
			'true_value' => $true_value,
			'false_value' => $false_value
		];
	}
	
	/**
	 * Cari posisi colon utama dengan menangani quotes dan nested ternary
	 */
	private function find_main_colon_smart(string $text): int|false
	{
		$depth = 0;
		$in_quotes = false;
		$quote_char = '';
		$length = strlen($text);
		
		for ($i = 0; $i < $length; $i++) {
			$char = $text[$i];
			
			// Handle escaped quotes
			if ($i > 0 && $text[$i-1] === '\\') {
				continue;
			}
			
			// Handle quotes
			if (($char === '"' || $char === "'") && !$in_quotes) {
				$in_quotes = true;
				$quote_char = $char;
				continue;
			} elseif ($char === $quote_char && $in_quotes) {
				$in_quotes = false;
				$quote_char = '';
				continue;
			}
			
			if (!$in_quotes) {
				if ($char === '?') {
					$depth++;
				} elseif ($char === ':') {
					if ($depth === 0) {
						return $i; // Found main colon
					}
					$depth--;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Evaluasi kondisi dalam ternary operator
	 */
	private function evaluate_ternary_condition(string $condition): bool
	{
		$condition = trim($condition);
		
		// Operator yang didukung (urutan penting - cek yang lebih spesifik dulu)
		$operators = ['===', '!==', '==', '!=', '>=', '<=', '>', '<'];
		
		foreach ($operators as $operator) {
			if (strpos($condition, $operator) !== false) {
				$parts = explode($operator, $condition, 2);
				if (count($parts) === 2) {
					$left = trim($parts[0]);
					$right = trim($parts[1]);
					
					// Dapatkan nilai variabel
					$left_value = $this->resolve_ternary_variable($left);
					$right_value = $this->resolve_ternary_variable($right);
					
					// Evaluasi berdasarkan operator
					return $this->compare_ternary_values($left_value, $operator, $right_value);
				}
			}
		}
		
		// Jika tidak ada operator, evaluasi sebagai boolean (keberadaan variabel)
		$value = $this->resolve_ternary_variable($condition);
		return !empty($value);
	}
	
	/**
	 * Resolve variabel atau literal dalam konteks ternary
	 */
	private function resolve_ternary_variable(string $input): mixed
	{
		$input = trim($input);
		
		// Hapus quote jika ada (string literal)
		if ((str_starts_with($input, "'") && str_ends_with($input, "'")) ||
			(str_starts_with($input, '"') && str_ends_with($input, '"'))) {
			return substr($input, 1, -1);
		}
		
		// Cek jika numerik
		if (is_numeric($input)) {
			return is_float($input + 0) ? (float)$input : (int)$input;
		}
		
		// Cek literal boolean
		if (strtolower($input) === 'true') return true;
		if (strtolower($input) === 'false') return false;
		if (strtolower($input) === 'null') return null;
		
		// Variabel - dapatkan nilai dari template data
		return $this->get_variable_value($input);
	}
	
	/**
	 * Bandingkan nilai berdasarkan operator
	 */
	private function compare_ternary_values(mixed $left, string $operator, mixed $right): bool
	{
		switch ($operator) {
			case '===':
				return $left === $right;
			case '!==':
				return $left !== $right;
			case '==':
				return $left == $right;
			case '!=':
				return $left != $right;
			case '>':
				return $left > $right;
			case '<':
				return $left < $right;
			case '>=':
				return $left >= $right;
			case '<=':
				return $left <= $right;
			default:
				return false;
		}
	}
	
	/**
	 * Proses nilai dalam ternary (mungkin mengandung variabel template atau nested ternary)
	 */
	private function process_ternary_value(string $value): string
	{
		$value = trim($value);
		
		// Hapus quote jika ada
		if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
			(str_starts_with($value, '"') && str_ends_with($value, '"'))) {
			return substr($value, 1, -1);
		}
		
		// ENHANCED: Check if this value contains another ternary expression
		if (strpos($value, '?') !== false && strpos($value, ':') !== false) {
			// This might be a nested ternary, try to process it
			$nested_parsed = $this->parse_ternary_expression($value);
			if ($nested_parsed !== false) {
				$nested_condition = $this->evaluate_ternary_condition($nested_parsed['condition']);
				$nested_selected = $nested_condition ? $nested_parsed['true_value'] : $nested_parsed['false_value'];
				return $this->process_ternary_value($nested_selected); // Recursive call
			}
		}
		
		// ENHANCED: Handle simple string concatenation dengan +
		// Format: 'string' + variable atau variable + 'string'
		if (strpos($value, '+') !== false) {
			$parts = array_map('trim', explode('+', $value, 2));
			if (count($parts) === 2) {
				$left = $this->resolve_concatenation_part($parts[0]);
				$right = $this->resolve_concatenation_part($parts[1]);
				return $left . $right;
			}
		}
		
		// Jika mengandung variabel template, resolve
		if (preg_match('/^[a-zA-Z0-9_\.]+$/', $value)) {
			$resolved = $this->get_variable_value($value);
			return (string)$resolved;
		}
		
		return $value;
	}
	
	/**
	 * Resolve bagian dari string concatenation
	 */
	private function resolve_concatenation_part(string $part): string
	{
		$part = trim($part);
		
		// String literal
		if ((str_starts_with($part, "'") && str_ends_with($part, "'")) ||
			(str_starts_with($part, '"') && str_ends_with($part, '"'))) {
			return substr($part, 1, -1);
		}
		
		// Variable
		if (preg_match('/^[a-zA-Z0-9_\.]+$/', $part)) {
			$resolved = $this->get_variable_value($part);
			return (string)$resolved;
		}
		
		return $part;
	}
	
	/**
	 * Proses LINK syntax {link/path} dalam konteks blok
	 * 
	 * @param string &$content Konten template yang akan diproses
	 * @return void
	 */
	private function processLinkSyntaxInBlock(string &$content): void
	{
		// Proses notasi LINK path dalam blok: {link/path}
		preg_match_all("#\{(link/[a-zA-Z0-9_/-]+(?:\|[^}]+)?)\}#", $content, $link_matches, PREG_SET_ORDER);
		
		foreach ($link_matches as $match_val) {
			// FIXED: Validate array keys exist before accessing
			if (!isset($match_val[0]) || !isset($match_val[1])) {
				continue;
			}
			
			$var_path_with_filters = $match_val[1];
			$replacement = '';
			
			try {
				// Parsing path variabel dan filter
				$parsed = $this->parse_variable_with_filters($var_path_with_filters);
				$var_path = $parsed['variable'];
				$filters = $parsed['filters'];
				
				// Handle LINK path syntax: link/path -> generate_url_with_path(path)
				if (strpos($var_path, 'link/') === 0) {
					$link_path = substr($var_path, 5); // Remove 'link/' prefix
					$replacement = $this->generate_url_with_path($link_path);
				}
				
				// Terapkan filter jika tersedia
				if (!empty($filters) && $this->isFilterReady()) {
					$replacement = $this->apply_filters($replacement, $filters);
				}
				
			} catch (\Exception $e) {
				// Silent fail - just use empty replacement
				$replacement = "<!-- LINK_ERROR: {$var_path_with_filters} -->";
			}
			
			$content = str_replace($match_val[0], $replacement, $content);
		}
	}
	private function processAssetsSyntaxInBlock(string &$content): void
	{
		// Proses notasi ASSETS path dalam blok: {assets/path}
		preg_match_all("#\{(assets/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#", $content, $assets_matches, PREG_SET_ORDER);
		
		foreach ($assets_matches as $match_val) {
			// FIXED: Validate array keys exist before accessing
			if (!isset($match_val[0]) || !isset($match_val[1])) {
				continue;
			}
			
			$var_path_with_filters = $match_val[1];
			$replacement = '';
			
			try {
				// Parsing path variabel dan filter
				$parsed = $this->parse_variable_with_filters($var_path_with_filters);
				$var_path = $parsed['variable'];
				$filters = $parsed['filters'];
				
				// Handle ASSETS path syntax: assets/path -> generate_url_with_path(assets/path)
				if (strpos($var_path, 'assets/') === 0) {
					// Keep the full assets path including 'assets/' prefix
					$replacement = $this->generate_url_with_path($var_path);
				}
				
				// Terapkan filter jika tersedia
				if (!empty($filters) && $this->isFilterReady()) {
					$replacement = $this->apply_filters($replacement, $filters);
				}
				
			} catch (\Exception $e) {
				// Silent fail - just use empty replacement
				$replacement = "<!-- ASSETS_ERROR: {$var_path_with_filters} -->";
			}
			
			$content = str_replace($match_val[0], $replacement, $content);
		}
	}
	
	/**
	 * Proses NexaAsset syntax dalam konteks blok
	 * Supports: {css/file}, {js/file}, {img/file}, {font/file}, {drive/file}, {dashboard/file}, {dash/file}, {mobile/file}, {tablet/file}, {theme/file}
	 * Enhanced: ONLY DRIVE supports nested variables like {drive/{avatar}} in block context
	 * Other assets use normal syntax: {css/style.css}, {js/script.js}
	 * 
	 * @param string &$content Konten template yang akan diproses
	 * @return void
	 */
	private function processAssetSyntaxInBlock(string &$content): void
	{
		// Early return jika NexaAsset tidak ready
		if (!$this->isAssetReady()) {
			return;
		}
		
		// ENHANCED: Pre-process nested variables ONLY for drive assets in block context
		// Only {drive/{avatar}} is supported, others use normal syntax
		if (strpos($content, '{drive/{') !== false) {
			$this->preprocess_nested_asset_variables_in_block($content);
		}
		
		$asset = $this->getAssetLazy();
		
		// Asset syntax patterns untuk block context
		$asset_patterns = [
			'css' => '#\{(css/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'js' => '#\{(js/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'img' => '#\{(img/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'font' => '#\{(font/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'drive' => '#\{(drive/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'dashboard' => '#\{(dashboard/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'app' => '#\{(app/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'dash' => '#\{(dash/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'mobile' => '#\{(mobile/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'tablet' => '#\{(tablet/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
			'theme' => '#\{(theme/[a-zA-Z0-9_/.-]+(?:\|[^}]+)?)\}#',
		];
		          
		foreach ($asset_patterns as $type => $pattern) {
			if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match_val) {
					if (!isset($match_val[0]) || !isset($match_val[1])) {
						continue;
					}
					
					$var_path_with_filters = $match_val[1];
					$replacement = '';
					
					try {
						// Parsing path variabel dan filter
						$parsed = $this->parse_variable_with_filters($var_path_with_filters);
						$var_path = $parsed['variable'];
						$filters = $parsed['filters'];
						
						// Extract filename from path
						$filename = substr($var_path, strlen($type) + 1); // Remove 'type/' prefix
						
						// Generate asset URL using NexaAsset
						switch ($type) {
							case 'css':
								$replacement = $asset::css($filename);
								break;
							case 'js':
								$replacement = $asset::js($filename);
								break;
							case 'img':
								$replacement = $asset::img($filename);
								break;
							case 'font':
								$replacement = $asset::font($filename);
								break;
							case 'drive':
								$replacement = $asset::drive($filename);
								break;


							case 'app':
								$replacement = $asset::app($filename);
								break;

							case 'dashboard':
								$replacement = $asset::dashboard($filename);
								break;
							case 'dash':
								$replacement = $asset::dash($filename);
								break;
							case 'mobile':
								$replacement = $asset::mobile($filename);
								break;
							case 'tablet':
								$replacement = $asset::tablet($filename);
								break;
							case 'theme':
								$replacement = $asset::theme($filename);
								break;
						}
						
						// Terapkan filter jika tersedia
						if (!empty($filters) && $this->isFilterReady()) {
							$replacement = $this->apply_filters($replacement, $filters);
						}
						
					} catch (\Exception $e) {
						// Silent fail - just use empty replacement
						$replacement = "<!-- ASSET_BLOCK_ERROR: {$var_path_with_filters} -->";
					}
					
					$content = str_replace($match_val[0], $replacement, $content);
				}
			}
		}
	}
	
	/**
	 * Check if we're in template rendering context to prevent premature output
	 * 
	 * @return bool True if in template rendering context
	 */
	private function isTemplateRenderingContext(): bool
	{
		// Check if called from View class or similar template rendering context
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		foreach ($backtrace as $trace) {
			if (isset($trace['class']) && 
				(str_contains($trace['class'], 'View') || 
				 str_contains($trace['class'], 'Template') ||
				 str_contains($trace['file'] ?? '', 'View.php'))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ambil daftar path dashboard dari lingkungan folder templates/dashboard/
	 * Dinamis: scan subfolder yang ada, tidak perlu definisi manual 1 per 1
	 *
	 * @return array Daftar nama path (account, theme, beasiswa, dll) + path khusus (logout, beranda, home)
	 */
	private function getDashboardPathsFromEnvironment(): array
	{
		$paths = [];
		$dashboardDir = $this->root . 'dashboard' . DIRECTORY_SEPARATOR;

		if (is_dir($dashboardDir)) {
			$items = @scandir($dashboardDir);
			if ($items !== false) {
				foreach ($items as $item) {
					if ($item === '.' || $item === '..') {
						continue;
					}
					$fullPath = $dashboardDir . $item;
					if (is_dir($fullPath) && $item[0] !== '.') {
						$paths[] = strtolower($item);
					}
				}
			}
		}

		// Path khusus tanpa folder (aksi/nav umum)
		$extra = ['logout', 'beranda', 'home'];
		foreach ($extra as $p) {
			if (!in_array($p, $paths)) {
				$paths[] = $p;
			}
		}

		return $paths;
	}

	/**
	 * Transform href links yang dimulai dengan "/" menjadi full URL
	 * Aktif di seluruh konten HTML tanpa pembatasan tag
	 * 
	 * @param string &$content Konten HTML yang akan ditransformasi
	 * @return void
	 */
	private function transformHref(string &$content): void
	{
		// Pattern yang diperbaiki untuk menangkap href dengan benar
		// Menangkap: <a href="/path" atau <a href='/path' atau <a href=/path
		$href_pattern = '#(<a[^>]+href\s*=\s*["\']?)(/[^"\'>\s]+)(["\'][^>]*>)#i';
		
		// Dashboard context: url = base dashboard (http://localhost/iyanrsaleh atau /iyanrsaleh)
		$dashboardBase = null;
		$urlKey = $this->find_case_insensitive_key('url');
		if ($urlKey !== false && isset($this->_tpldata['.'][$urlKey])) {
			$dashboardBase = rtrim((string)$this->_tpldata['.'][$urlKey], '/');
			if ($dashboardBase === '') {
				$dashboardBase = null;
			}
		}
		
		// Path dashboard: deteksi dinamis dari folder templates/dashboard/* (tidak manual 1 per 1)
		$dashboardPaths = $this->getDashboardPathsFromEnvironment();
		
		// Transform semua href yang dimulai dengan "/" di seluruh konten
		$content = preg_replace_callback($href_pattern, function($matches) use ($dashboardBase, $dashboardPaths) {
			$prefix = $matches[1];
			$path = $matches[2];
			$suffix = $matches[3];
			
			$pathSegment = ltrim($path, '/');
			$pathSegment = strtok($pathSegment, '/');
			
			// Di dashboard: /account → http://localhost/iyanrsaleh/account
			if ($dashboardBase !== null && $pathSegment !== false && in_array($pathSegment, $dashboardPaths)) {
				$full_url = $dashboardBase . $path;
				// Jika base belum full URL, gunakan generate_url_with_path
				if (strpos($dashboardBase, '://') === false && strpos($dashboardBase, '//') !== 0) {
					$full_url = $this->generate_url_with_path(ltrim($full_url, '/'));
				}
				return $prefix . $full_url . $suffix;
			}
			
			$full_url = $this->generate_url_with_path($path);
			return $prefix . $full_url . $suffix;
		}, $content);
	}
	
	/**
	 * Pre-process nested variables in asset syntax for block context
	 * Resolves {drive/{avatar}} using both global and block-specific variables
	 * ONLY SUPPORTS DRIVE - other assets use normal syntax
	 * 
	 * @param string &$content Template content to process
	 * @return void
	 */
	private function preprocess_nested_asset_variables_in_block(string &$content): void
	{
		// Pattern untuk menangkap HANYA drive syntax dengan nested variables
		// Hanya mendukung: {drive/{avatar}}
		// Asset lain tetap normal: {css/style.css}, {js/script.js}
		$nested_pattern = '#\{(drive)/\{([a-zA-Z0-9_.]+)\}(?:\|([^}]+))?\}#';
		
		if (preg_match_all($nested_pattern, $content, $matches, PREG_SET_ORDER)) {
			$replacements = [];
			
			foreach ($matches as $match) {
				$full_match = $match[0];          // {drive/{avatar}}
				$asset_type = $match[1];          // drive
				$variable_name = $match[2];       // avatar
				$filters = $match[3] ?? '';       // optional filters
				
				// Resolve the nested variable (prioritize block data if available)
				$variable_value = '';
				
				// First try block context data if available
				if (isset($this->current_block_data) && is_array($this->current_block_data)) {
					$block_key = $this->find_case_insensitive_key_in_array($variable_name, $this->current_block_data);
					if ($block_key !== false) {
						$variable_value = (string)$this->current_block_data[$block_key];
					}
				}
				
				// Fallback to global variable if not found in block
				if (empty($variable_value)) {
					$variable_value = $this->get_variable_value($variable_name);
				}
				
				if (!empty($variable_value)) {
					// Build the resolved asset syntax
					$resolved_syntax = '{' . $asset_type . '/' . $variable_value;
					
					// Add filters if present
					if (!empty($filters)) {
						$resolved_syntax .= '|' . $filters;
					}
					
					$resolved_syntax .= '}';
					
					// Store replacement
					$replacements[$full_match] = $resolved_syntax;
				} else {
					// Variable not found, keep original or use placeholder
					$placeholder = "<!-- NESTED_VAR_NOT_FOUND: {$variable_name} in {$full_match} -->";
					$replacements[$full_match] = $placeholder;
				}
			}
			
			// Apply all replacements at once
			if (!empty($replacements)) {
				$content = str_replace(array_keys($replacements), array_values($replacements), $content);
			}
		}
	}
	
	/**
	 * Debug method untuk testing switch statement
	 * Membantu mendiagnosis masalah dengan switch statement
	 * 
	 * @param string $switch_content Konten switch statement
	 * @param string $switch_var Variabel yang digunakan untuk switch
	 * @return array Debug information
	 */
	public function debugSwitchStatement(string $switch_content, string $switch_var): array
	{
		$switch_value = $this->get_variable_value($switch_var);
		$debug_info = [
			'switch_var' => $switch_var,
			'switch_value' => $switch_value,
			'switch_value_type' => gettype($switch_value),
			'switch_content' => $switch_content,
			'cases_found' => [],
			'default_found' => false,
			'default_content' => '',
			'case_matched' => false,
			'matched_case' => null
		];
		
		// Parse cases
		$case_pattern = '#\{case\s+([^}]+)\}(.*?)(?=\{(?:case\s+[^}]+|default|endswitch)\})#is';
		if (preg_match_all($case_pattern, $switch_content, $case_matches, PREG_SET_ORDER)) {
			foreach ($case_matches as $i => $case_match) {
				$case_value = trim($case_match[1], '"\'');
				$case_content = $case_match[2];
				
				$debug_info['cases_found'][] = [
					'index' => $i,
					'raw_value' => $case_match[1],
					'clean_value' => $case_value,
					'content' => $case_content,
					'matches' => ((string)$switch_value === (string)$case_value)
				];
				
				if ((string)$switch_value === (string)$case_value) {
					$debug_info['case_matched'] = true;
					$debug_info['matched_case'] = $i;
				}
			}
		}
		
		// Parse default - using simplified approach
		$default_pos = strpos($switch_content, '{default}');
		if ($default_pos !== false) {
			$debug_info['default_found'] = true;
			
			// Ambil konten setelah {default}
			$after_default = substr($switch_content, $default_pos + 9); // 9 = length of '{default}'
			
			// Cari posisi {endswitch} untuk menentukan batas akhir
			$endswitch_pos = strpos($after_default, '{endswitch}');
			if ($endswitch_pos !== false) {
				$debug_info['default_content'] = trim(substr($after_default, 0, $endswitch_pos));
			} else {
				$debug_info['default_content'] = trim($after_default);
			}
		}
		
		return $debug_info;
	}
	
	/**
	 * ENHANCED: Proses kondisional IF global dengan dukungan yang lebih kuat
	 * Mendukung: logical operators (&&, ||), comparison dengan quotes, nested conditions
	 */
	private function process_global_if_conditionals_enhanced(string &$content): void
	{
		$iterations = 0;
		$max_iterations = 100; // Increased for complex nested conditions
		
		while ($iterations < $max_iterations) {
			$iterations++;
			$original_content = $content;
			
			// Enhanced pattern untuk menangkap if statements dengan kondisi yang lebih kompleks
			if (preg_match('#\{if\s+([^}]+)\}#', $content, $if_match, PREG_OFFSET_CAPTURE)) {
				$if_pos = (int)$if_match[0][1];
				$condition = trim((string)$if_match[1][0]);
				
				// Enhanced condition parsing - support logical operators
				$condition_result = $this->evaluate_enhanced_condition($condition);
				
				// Find matching endif with improved nesting support
				$endif_data = $this->find_matching_endif_enhanced($content, $if_pos, strlen($if_match[0][0]));
				
				if ($endif_data !== false) {
					$if_content = $endif_data['content'];
					$full_length = $endif_data['full_length'];
					
					// Process else/elseif blocks
					$processed_content = $this->process_if_else_blocks_enhanced($if_content, $condition_result);
					
					// Replace entire if block
					$content = substr_replace($content, $processed_content, $if_pos, $full_length);
				} else {
					// No matching endif found, skip this if
					$content = substr_replace($content, '<!-- ORPHAN_IF -->', $if_pos, strlen($if_match[0][0]));
				}
			} else {
				break; // No more if statements found
			}
			
			if ($content === $original_content) {
				break; // Prevent infinite loop
			}
		}
		
		// Clean up orphaned statements
		$content = str_replace(['<!-- ORPHAN_IF -->', '<!-- SKIP_IF -->'], '', $content);
	}
	
	/**
	 * ENHANCED: Evaluasi kondisi dengan dukungan logical operators
	 */
	private function evaluate_enhanced_condition(string $condition): bool
	{
		$condition = trim($condition);
		
		// Handle logical AND (&&)
		if (strpos($condition, '&&') !== false) {
			$parts = array_map('trim', explode('&&', $condition));
			foreach ($parts as $part) {
				if (!$this->evaluate_single_condition($part)) {
					return false;
				}
			}
			return true;
		}
		
		// Handle logical OR (||)
		if (strpos($condition, '||') !== false) {
			$parts = array_map('trim', explode('||', $condition));
			foreach ($parts as $part) {
				if ($this->evaluate_single_condition($part)) {
					return true;
				}
			}
			return false;
		}
		
		// Single condition
		return $this->evaluate_single_condition($condition);
	}
	
	/**
	 * ENHANCED: Evaluasi kondisi tunggal dengan dukungan yang lebih baik
	 */
	private function evaluate_single_condition(string $condition): bool
	{
		$condition = trim($condition);
		
		// Handle negation (!)
		if (str_starts_with($condition, '!')) {
			$inner_condition = trim(substr($condition, 1));
			return !$this->evaluate_single_condition($inner_condition);
		}
		
		// Handle parentheses - basic support
		if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
			$inner_condition = trim(substr($condition, 1, -1));
			return $this->evaluate_single_condition($inner_condition);
		}
		
		// Enhanced comparison operators (order matters - check longer ones first)
		$operators = ['===', '!==', '==', '!=', '>=', '<=', '>', '<'];
		
		foreach ($operators as $operator) {
			if (strpos($condition, $operator) !== false) {
				$parts = explode($operator, $condition, 2);
				if (count($parts) === 2) {
					$left = trim($parts[0]);
					$right = trim($parts[1]);
					
					$left_value = $this->resolve_condition_value($left);
					$right_value = $this->resolve_condition_value($right);
					
					return $this->compare_enhanced_values($left_value, $operator, $right_value);
				}
			}
		}
		
		// Handle functions
		if (preg_match('/^(isset|empty|!empty)\(([^)]+)\)$/', $condition, $matches)) {
			$function = $matches[1];
			$var_name = trim($matches[2]);
			$value = $this->get_variable_value($var_name);
			
			switch ($function) {
				case 'isset':
					return $value !== '' && $value !== null;
				case 'empty':
					return empty($value);
				case '!empty':
					return !empty($value);
			}
		}
		
		// Simple variable existence check with enhanced support
		if (preg_match('/^[a-zA-Z0-9_\.]+$/', $condition)) {
			$value = $this->get_variable_value($condition);
			// Enhanced truthiness check
			if (is_string($value)) {
				return $value !== '' && strtolower($value) !== 'false' && $value !== '0';
			}
			return !empty($value);
		}
		
		return false;
	}
	
	/**
	 * ENHANCED: Resolve nilai dalam kondisi dengan dukungan quotes dan types
	 */
	private function resolve_condition_value(string $input): mixed
	{
		$input = trim($input);
		
		// Handle quoted strings (single or double quotes)
		if ((str_starts_with($input, "'") && str_ends_with($input, "'")) ||
			(str_starts_with($input, '"') && str_ends_with($input, '"'))) {
			return substr($input, 1, -1);
		}
		
		// Handle numbers
		if (is_numeric($input)) {
			return str_contains($input, '.') ? (float)$input : (int)$input;
		}
		
		// Handle boolean literals
		$lower_input = strtolower($input);
		if ($lower_input === 'true') return true;
		if ($lower_input === 'false') return false;
		if ($lower_input === 'null') return null;
		
		// Handle variables (including dot notation)
		return $this->get_variable_value($input);
	}
	
	/**
	 * ENHANCED: Perbandingan nilai dengan type checking yang lebih baik
	 */
	private function compare_enhanced_values(mixed $left, string $operator, mixed $right): bool
	{
		switch ($operator) {
			case '===':
				return $left === $right;
			case '!==':
				return $left !== $right;
			case '==':
				// Loose comparison with type coercion
				return $left == $right;
			case '!=':
				return $left != $right;
			case '>':
				return $left > $right;
			case '<':
				return $left < $right;
			case '>=':
				return $left >= $right;
			case '<=':
				return $left <= $right;
			default:
				return false;
		}
	}
	
	/**
	 * ENHANCED: Find matching endif dengan dukungan nesting yang lebih baik
	 */
	private function find_matching_endif_enhanced(string $content, int $if_pos, int $if_length): array|false
	{
		$if_count = 1;
		$pos = $if_pos + $if_length;
		$content_length = strlen($content);
		
		while ($pos < $content_length && $if_count > 0) {
			// Look for next if, elseif, else, or endif
			if (preg_match('#\{(if\s+[^}]+|elseif\s+[^}]+|else|endif)\}#', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
				$match_pos = (int)$match[0][1];
				$match_text = (string)$match[1][0];
				
				if (strpos($match_text, 'if ') === 0) {
					$if_count++; // Nested if
				} elseif ($match_text === 'endif') {
					$if_count--;
					if ($if_count === 0) {
						// Found matching endif
						$content_start = $if_pos + $if_length;
						$content_end = $match_pos;
						$content_between = substr($content, $content_start, $content_end - $content_start);
						$full_length = ($match_pos + strlen($match[0][0])) - $if_pos;
						
						return [
							'content' => $content_between,
							'full_length' => $full_length
						];
					}
				}
				$pos = $match_pos + strlen($match[0][0]);
			} else {
				break;
			}
		}
		
		return false;
	}
	
	/**
	 * ENHANCED: Process if/else/elseif blocks dengan dukungan yang lebih baik
	 */
	private function process_if_else_blocks_enhanced(string $content, bool $if_condition_result): string
	{
		$result = '';
		$blocks = $this->parse_if_else_blocks($content);
		
		// Process blocks in order
		foreach ($blocks as $block) {
			if ($block['type'] === 'if' && $if_condition_result) {
				$result = $block['content'];
				break;
			} elseif ($block['type'] === 'elseif' && !$if_condition_result) {
				// Evaluate elseif condition
				if ($this->evaluate_enhanced_condition($block['condition'])) {
					$result = $block['content'];
					break;
				}
			} elseif ($block['type'] === 'else' && !$if_condition_result) {
				// Check if no elseif was matched
				$elseif_matched = false;
				foreach ($blocks as $prev_block) {
					if ($prev_block['type'] === 'elseif' && 
						$this->evaluate_enhanced_condition($prev_block['condition'])) {
						$elseif_matched = true;
						break;
					}
				}
				if (!$elseif_matched) {
					$result = $block['content'];
					break;
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * ENHANCED: Parse if/else/elseif blocks untuk processing yang lebih akurat
	 */
	private function parse_if_else_blocks(string $content): array
	{
		$blocks = [];
		$current_content = '';
		$pos = 0;
		
		// First block is always the "if" content
		if (preg_match('#\{(elseif\s+[^}]+|else)\}#', $content, $match, PREG_OFFSET_CAPTURE)) {
			$blocks[] = [
				'type' => 'if',
				'condition' => '',
				'content' => substr($content, 0, (int)$match[0][1])
			];
			$pos = (int)$match[0][1];
		} else {
			// No else/elseif blocks
			$blocks[] = [
				'type' => 'if',
				'condition' => '',
				'content' => $content
			];
			return $blocks;
		}
		
		// Parse remaining blocks
		while (preg_match('#\{(elseif\s+([^}]+)|else)\}#', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
			$match_pos = (int)$match[0][1];
			$match_text = (string)$match[1][0];
			
			// Add content before this match to previous block (if any)
			if ($pos < $match_pos) {
				$current_content = substr($content, $pos, $match_pos - $pos);
			}
			
			if (strpos($match_text, 'elseif ') === 0) {
				$condition = trim(substr($match_text, 7)); // Remove 'elseif '
				$blocks[] = [
					'type' => 'elseif',
					'condition' => $condition,
					'content' => ''
				];
			} else { // else
				$blocks[] = [
					'type' => 'else',
					'condition' => '',
					'content' => ''
				];
			}
			
			$pos = $match_pos + strlen($match[0][0]);
		}
		
		// Add remaining content to last block
		if ($pos < strlen($content)) {
			$remaining_content = substr($content, $pos);
			if (!empty($blocks)) {
				$blocks[count($blocks) - 1]['content'] = $remaining_content;
			}
		}
		
		return $blocks;
	}
	
	/**
	 * PUBLIC: Enable enhanced IF processing untuk conditional statements yang lebih powerful
	 * Call this method to use advanced IF features dengan logical operators, better nesting, dll
	 * 
	 * @param string &$content Template content to process
	 * @return void
	 */
	public function enable_enhanced_conditionals(string &$content): void
	{
		// Replace the standard conditional processing with enhanced version
		$this->process_global_if_conditionals_enhanced($content);
		$this->process_global_switch_statements($content);
	}
	
	/**
	 * PUBLIC: Method untuk debugging conditional expressions
	 * Berguna untuk testing dan troubleshooting complex conditionals
	 * 
	 * @param string $condition Condition to test
	 * @return array Debug information
	 */
	public function debug_condition(string $condition): array
	{
		return [
			'original_condition' => $condition,
			'result' => $this->evaluate_enhanced_condition($condition),
			'parsed_parts' => $this->parse_condition_debug($condition),
			'variable_values' => $this->get_condition_variables($condition)
		];
	}
	
	/**
	 * HELPER: Parse condition untuk debugging
	 */
	private function parse_condition_debug(string $condition): array
	{
		$debug_info = [
			'has_logical_and' => strpos($condition, '&&') !== false,
			'has_logical_or' => strpos($condition, '||') !== false,
			'has_negation' => str_starts_with(trim($condition), '!'),
			'has_parentheses' => str_contains($condition, '(') && str_contains($condition, ')'),
			'detected_operators' => [],
			'detected_functions' => []
		];
		
		// Detect operators
		$operators = ['===', '!==', '==', '!=', '>=', '<=', '>', '<'];
		foreach ($operators as $op) {
			if (strpos($condition, $op) !== false) {
				$debug_info['detected_operators'][] = $op;
			}
		}
		
		// Detect functions
		if (preg_match_all('/\b(isset|empty|!empty)\s*\([^)]+\)/', $condition, $matches)) {
			$debug_info['detected_functions'] = $matches[1];
		}
		
		return $debug_info;
	}
	
	/**
	 * HELPER: Get all variables referenced in condition
	 */
	private function get_condition_variables(string $condition): array
	{
		$variables = [];
		
		// Extract variables (including dot notation)
		if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\b/', $condition, $matches)) {
			foreach ($matches[1] as $var) {
				// Skip keywords
				if (!in_array(strtolower($var), ['isset', 'empty', 'true', 'false', 'null', 'and', 'or'])) {
					$variables[$var] = $this->get_variable_value($var);
				}
			}
		}
		
		return $variables;
	}
	
	/**
	 * PUBLIC: Enhanced assign dengan conditional helpers
	 * Assign variabel dengan built-in helper untuk conditionals
	 * 
	 * @param array $data Data to assign dengan helper methods
	 * @return bool
	 */
	public function assign_with_helpers(array $data): bool
	{
		// Process data dan tambahkan helper values
		$enhanced_data = $this->add_conditional_helpers($data);
		
		// Assign ke template
		return $this->assign_vars($enhanced_data);
	}
	
	/**
	 * DEBUG: Method untuk debugging template data dan SSI includes
	 * Berguna untuk troubleshooting masalah dengan included files
	 * 
	 * @param string $blockName Optional block name to debug
	 * @return array Debug information
	 */
	public function debug_template_data(string $blockName = ''): array
	{
		$debug_info = [
			'root_directory' => $this->root,
			'total_variables' => count($this->_tpldata['.'] ?? []),
			'total_sections' => count($this->_section ?? []),
			'filter_ready' => $this->isFilterReady(),
			'asset_ready' => $this->isAssetReady(),
			'all_variables' => array_keys($this->_tpldata['.'] ?? []),
			'all_sections' => array_keys($this->_section ?? [])
		];
		
		if (!empty($blockName)) {
			$debug_info['requested_block'] = $blockName;
			$debug_info['block_exists'] = isset($this->_tpldata['.'][$blockName]);
			
			if (isset($this->_tpldata['.'][$blockName])) {
				$block_data = $this->_tpldata['.'][$blockName];
				$debug_info['block_type'] = gettype($block_data);
				
				if (is_array($block_data)) {
					$debug_info['block_count'] = count($block_data);
					$debug_info['block_keys'] = array_keys($block_data);
					
					// Show first item structure if available
					if (!empty($block_data)) {
						$first_item = reset($block_data);
						if (is_array($first_item)) {
							$debug_info['first_item_keys'] = array_keys($first_item);
							$debug_info['first_item_sample'] = array_slice($first_item, 0, 3, true);
						} else {
							$debug_info['first_item_value'] = $first_item;
						}
					}
				} else {
					$debug_info['block_value'] = $block_data;
				}
			}
		}
		
		return $debug_info;
	}
	
	/**
	 * DEBUG: Test SSI include processing
	 * 
	 * @param string $includeFile File to test
	 * @return array Test results
	 */
	public function test_ssi_include(string $includeFile): array
	{
		$full_path = $this->root . $includeFile;
		$test_result = [
			'file' => $includeFile,
			'full_path' => $full_path,
			'file_exists' => file_exists($full_path),
			'file_readable' => is_readable($full_path),
			'file_size' => file_exists($full_path) ? filesize($full_path) : 0,
			'root_directory' => $this->root
		];
		
		if (file_exists($full_path)) {
			$content = file_get_contents($full_path);
			$test_result['content_length'] = strlen($content);
			$test_result['has_formModal2'] = strpos($content, 'formModal2') !== false;
			$test_result['has_filters'] = strpos($content, '|') !== false;
			$test_result['template_variables'] = [];
			
			// Extract template variables
			if (preg_match_all('#\{([a-zA-Z0-9_.]+(?:\|[^}]+)?)\}#', $content, $matches)) {
				$test_result['template_variables'] = array_unique($matches[1]);
			}
			
			// Test processing
			try {
				$processed_content = $this->pparse_content_only($content);
				$test_result['processing_success'] = true;
				$test_result['processed_length'] = strlen($processed_content);
				$test_result['variables_resolved'] = strpos($processed_content, '{formModal2') === false;
			} catch (Exception $e) {
				$test_result['processing_success'] = false;
				$test_result['processing_error'] = $e->getMessage();
			}
		}
		
		return $test_result;
	}
	
	/**
	 * HELPER: Add conditional helper values to data
	 */
	private function add_conditional_helpers(array $data): array
	{
		$enhanced_data = $data;
		
		// Add helper properties untuk common conditionals
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				// Add count helpers
				$enhanced_data[$key . '_count'] = count($value);
				$enhanced_data[$key . '_empty'] = empty($value);
				$enhanced_data[$key . '_exists'] = isset($value);
				
				// Add first/last helpers untuk arrays
				if (!empty($value)) {
					$enhanced_data[$key . '_first'] = reset($value);
					$enhanced_data[$key . '_last'] = end($value);
				}
			} elseif (is_string($value)) {
				// Add string helpers
				$enhanced_data[$key . '_length'] = strlen($value);
				$enhanced_data[$key . '_empty'] = empty($value);
				$enhanced_data[$key . '_lower'] = strtolower($value);
				$enhanced_data[$key . '_upper'] = strtoupper($value);
			} elseif (is_numeric($value)) {
				// Add numeric helpers
				$enhanced_data[$key . '_zero'] = ($value == 0);
				$enhanced_data[$key . '_positive'] = ($value > 0);
				$enhanced_data[$key . '_negative'] = ($value < 0);
			}
		}
		
		return $enhanced_data;
	}
	
	/**
	 * STABLE: Proses kondisional gaya PHP dengan pendekatan yang lebih sederhana dan stabil
	 * Mendukung: {if}, {else}, {elseif}, {endif} dengan logical operators dasar
	 */
	private function process_global_conditionals_stable(string &$content): void
	{
		// Proses IF statements dulu
		$this->process_if_statements_stable($content);
		
		// Kemudian proses switch statements
		$this->process_switch_statements_stable($content);
	}
	
	/**
	 * Process global conditionals but skip those inside NEXA blocks
	 */
	private function process_global_conditionals_stable_skip_blocks(string &$content): void
	{
		// Skip conditionals inside NEXA blocks by temporarily replacing block content
		$blocks_content = [];
		$block_placeholders = [];
		
		// Find and temporarily replace NEXA blocks
		if (preg_match_all('#<!-- NEXA ([a-zA-Z0-9_]+) -->(.*?)<!-- END \1 -->#is', $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $i => $match) {
				$placeholder = "___NEXA_BLOCK_PLACEHOLDER_{$i}___";
				$blocks_content[$placeholder] = $match[0];
				$block_placeholders[] = $placeholder;
				$content = str_replace($match[0], $placeholder, $content);
			}
		}
		
		// Process global conditionals on content without blocks
		$this->process_if_statements_stable($content);
		$this->process_switch_statements_stable($content);
		
		// Restore NEXA blocks
		foreach ($blocks_content as $placeholder => $block_content) {
			$content = str_replace($placeholder, $block_content, $content);
		}
	}
	
	/**
	 * STABLE: Proses IF statements dengan approach yang lebih simple dan reliable
	 * FIXED: Sekarang mendukung nested if statements dengan benar
	 */
	private function process_if_statements_stable(string &$content): void
	{
		$max_iterations = 50;
		$iteration = 0;
		
		while ($iteration < $max_iterations) {
			$iteration++;
			$original_content = $content;
			
			// Cari {if} pertama
			if (preg_match('#\{if\s+([^}]+)\}#', $content, $if_match, PREG_OFFSET_CAPTURE)) {
				$if_pos = (int)$if_match[0][1];
				$condition = trim((string)$if_match[1][0]);
				$if_tag_length = strlen($if_match[0][0]);
				
				// Cari {endif} yang matching dengan menghitung nested if
				$if_count = 1;
				$pos = $if_pos + $if_tag_length;
				$endif_pos = false;
				$endif_length = 0;
				
				while ($pos < strlen($content) && $if_count > 0) {
					// Cari {if} atau {endif} berikutnya
					if (preg_match('#\{(if\s+[^}]+|endif)\}#', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
						$match_pos = (int)$match[0][1];
						$match_text = (string)$match[1][0];
						$match_length = strlen($match[0][0]);
						
						if (strpos($match_text, 'if ') === 0) {
							// Ditemukan nested {if}
							$if_count++;
						} else if ($match_text === 'endif') {
							// Ditemukan {endif}
							$if_count--;
							if ($if_count === 0) {
								// Ini adalah {endif} yang matching
								$endif_pos = $match_pos;
								$endif_length = $match_length;
								break;
							}
						}
						$pos = $match_pos + $match_length;
					} else {
						break;
					}
				}
				
				if ($endif_pos !== false) {
					// Ekstrak konten antara {if} dan {endif}
					$if_content = substr($content, $if_pos + $if_tag_length, $endif_pos - ($if_pos + $if_tag_length));
					
					// Process if/else/elseif content
					$processed_content = $this->process_if_else_content_stable($if_content, $condition);
					
					// Replace entire if block dengan hasil
					$full_start = $if_pos;
					$full_end = $endif_pos + $endif_length;
					$content = substr_replace($content, $processed_content, $full_start, $full_end - $full_start);
				} else {
					// Tidak ditemukan endif yang matching, skip if ini
					$content = substr_replace($content, '<!-- ORPHAN_IF -->', $if_pos, $if_tag_length);
				}
			} else {
				break; // No more if statements
			}
			
			// Prevent infinite loop
			if ($content === $original_content) {
				break;
			}
		}
		
		// Bersihkan placeholder
		$content = str_replace('<!-- ORPHAN_IF -->', '', $content);
	}
	
	/**
	 * STABLE: Process if/else/elseif content dengan approach yang simple
	 * FIXED: Hanya split pada {else} dan {elseif} yang berada di level yang sama (tidak nested)
	 */
	private function process_if_else_content_stable(string $content, string $main_condition): string
	{
		// Evaluate main condition
		$main_result = $this->evaluate_condition_stable($main_condition);
		
		// Cari {else} atau {elseif} yang berada di level yang sama (tidak di dalam nested if)
		$parts = [];
		$current_part = '';
		$if_depth = 0;
		$pos = 0;
		$len = strlen($content);
		
		while ($pos < $len) {
			// Cek apakah ada {if}, {else}, {elseif}, atau {endif}
			if (preg_match('#\{(if\s+[^}]+|else|elseif\s+[^}]+|endif)\}#', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
				$match_pos = (int)$match[0][1];
				$match_text = (string)$match[1][0];
				$match_full = (string)$match[0][0];
				
				// Tambahkan konten sebelum match ke current part
				$current_part .= substr($content, $pos, $match_pos - $pos);
				
				if (strpos($match_text, 'if ') === 0) {
					// Nested if ditemukan
					$if_depth++;
					$current_part .= $match_full;
				} elseif ($match_text === 'endif') {
					// Penutup if
					if ($if_depth > 0) {
						$if_depth--;
						$current_part .= $match_full;
					} else {
						// Ini seharusnya tidak terjadi di sini
						$current_part .= $match_full;
					}
				} elseif (($match_text === 'else' || strpos($match_text, 'elseif ') === 0) && $if_depth === 0) {
					// {else} atau {elseif} di level yang sama
					$parts[] = $current_part;
					$parts[] = $match_text;
					$current_part = '';
				} else {
					// {else} atau {elseif} di dalam nested if
					$current_part .= $match_full;
				}
				
				$pos = $match_pos + strlen($match_full);
			} else {
				// Tidak ada match lagi, tambahkan sisa konten
				$current_part .= substr($content, $pos);
				break;
			}
		}
		
		// Tambahkan part terakhir
		if ($current_part !== '') {
			$parts[] = $current_part;
		}
		
		// Jika tidak ada else/elseif, return berdasarkan kondisi
		if (count($parts) === 1) {
			return $main_result ? $parts[0] : '';
		}
		
		// Process parts
		// First part is always the main if content
		if ($main_result) {
			return $parts[0];
		}
		
		// Check elseif and else blocks
		$i = 1;
		while ($i < count($parts)) {
			$block_type = $parts[$i];
			$block_content = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
			
			if ($block_type === 'else') {
				// Else block - use this if no previous condition was true
				return $block_content;
			} elseif (strpos($block_type, 'elseif ') === 0) {
				// Elseif block
				$elseif_condition = trim(substr($block_type, 7));
				if ($this->evaluate_condition_stable($elseif_condition)) {
					return $block_content;
				}
			}
			
			$i += 2;
		}
		
		return ''; // No condition matched
	}
	
	/**
	 * STABLE: Evaluate condition dengan approach yang simple tapi effective
	 */
	private function evaluate_condition_stable(string $condition): bool
	{
		$condition = trim($condition);
		
		// Handle logical operators - simple approach
		if (strpos($condition, '&&') !== false) {
			$parts = explode('&&', $condition);
			foreach ($parts as $part) {
				if (!$this->evaluate_single_condition_stable(trim($part))) {
					return false;
				}
			}
			return true;
		}
		
		if (strpos($condition, '||') !== false) {
			$parts = explode('||', $condition);
			foreach ($parts as $part) {
				if ($this->evaluate_single_condition_stable(trim($part))) {
					return true;
				}
			}
			return false;
		}
		
		// Single condition
		return $this->evaluate_single_condition_stable($condition);
	}
	
	/**
	 * STABLE: Evaluate single condition dengan compatibility yang baik
	 */
	private function evaluate_single_condition_stable(string $condition): bool
	{
		$condition = trim($condition);
		
		// Handle negation
		if (str_starts_with($condition, '!')) {
			$inner = trim(substr($condition, 1));
			return !$this->evaluate_single_condition_stable($inner);
		}
		
		// Handle isset() function
		if (preg_match('/^isset\(([^)]+)\)$/', $condition, $matches)) {
			$var_name = trim($matches[1]);
			$value = $this->get_variable_value($var_name);
			return $value !== '' && $value !== null;
		}
		
		// Handle empty() function
		if (preg_match('/^empty\(([^)]+)\)$/', $condition, $matches)) {
			$var_name = trim($matches[1]);
			$value = $this->get_variable_value($var_name);
			return empty($value);
		}
		
		// Handle comparison operators
		$operators = ['===', '!==', '==', '!=', '>=', '<=', '>', '<'];
		
		foreach ($operators as $op) {
			if (strpos($condition, $op) !== false) {
				$parts = explode($op, $condition, 2);
				if (count($parts) === 2) {
					$left = $this->resolve_value_stable(trim($parts[0]));
					$right = $this->resolve_value_stable(trim($parts[1]));
					
					$result = match ($op) {
						'===' => $left === $right,
						'!==' => $left !== $right,
						'==' => $left == $right,
						'!=' => $left != $right,
						'>' => $left > $right,
						'<' => $left < $right,
						'>=' => $left >= $right,
						'<=' => $left <= $right,
						default => false
					};
					
					return $result;
				}
			}
		}
		
		// Simple variable check
		$value = $this->get_variable_value($condition);
		
		// Enhanced truthiness check
		if (is_string($value)) {
			return $value !== '' && strtolower($value) !== 'false' && $value !== '0';
		}
		
		return !empty($value);
	}
	
	/**
	 * STABLE: Resolve value untuk comparison
	 */
	private function resolve_value_stable(string $input): mixed
	{
		$input = trim($input);
		
		// Handle quoted strings
		if ((str_starts_with($input, "'") && str_ends_with($input, "'")) ||
			(str_starts_with($input, '"') && str_ends_with($input, '"'))) {
			return substr($input, 1, -1);
		}
		
		// Handle numbers
		if (is_numeric($input)) {
			return str_contains($input, '.') ? (float)$input : (int)$input;
		}
		
		// Handle boolean literals
		$lower = strtolower($input);
		if ($lower === 'true') return true;
		if ($lower === 'false') return false;
		if ($lower === 'null') return null;
		
		// Variable - get the value
		$value = $this->get_variable_value($input);
		
		// Convert numeric strings to numbers for proper comparison
		if (is_string($value) && is_numeric($value)) {
			return str_contains($value, '.') ? (float)$value : (int)$value;
		}
		
		return $value;
	}
	
	/**
	 * STABLE: Process switch statements dengan approach yang simple
	 */
	private function process_switch_statements_stable(string &$content): void
	{
		$pattern = '#\{switch\s+([^}]+)\}(.*?)\{endswitch\}#is';
		
		while (preg_match($pattern, $content, $matches)) {
			$switch_var = trim($matches[1]);
			$switch_content = $matches[2];
			
			$switch_value = $this->get_variable_value($switch_var);
			$result = $this->process_switch_content_stable($switch_content, $switch_value);
			
			$content = str_replace($matches[0], $result, $content);
		}
	}
	
	/**
	 * STABLE: Process switch content
	 */
	private function process_switch_content_stable(string $content, mixed $switch_value): string
	{
		// Find all case blocks
		$case_pattern = '#\{case\s+([^}]+)\}(.*?)(?=\{(?:case\s+[^}]+|default|endswitch)\})#is';
		
		if (preg_match_all($case_pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$case_value = trim($match[1], '"\'');
				$case_content = $match[2];
				
				if ((string)$switch_value === (string)$case_value) {
					return $case_content;
				}
			}
		}
		
		// Check for default case
		if (preg_match('#\{default\}(.*?)(?=\{endswitch\}|$)#is', $content, $match)) {
			return $match[1];
		}
		
		return '';
	}
	
	/**
	 * Process custom events untuk interaksi JavaScript
	 * Mengubah custom events seperti onPress, onModal, dll menjadi onclick handlers
	 * dengan JSON data yang diproses untuk template variables
	 * 
	 * Contoh:
	 * <a onPress='{"id": "{post_id}", "title": "{post_title}"}'>Click</a>
	 * Menjadi:
	 * <a onclick="press({\"id\": \"123\", \"title\": \"Post Title\"});" href="javascript:void(0);">Click</a>
	 */
	private function processCustomEvents(string &$content): void
	{
		// Check if custom events processing is enabled
		if (!$this->customEventsEnabled) {
			return;
		}
		
		// Get dynamic list of custom events
		$customEvents = $this->getCustomEvents();

		foreach ($customEvents as $event) {
			$content = preg_replace_callback(
				'/<a([^>]*?)' . $event . '=(["\'])(.*?)\2([^>]*?)>/is',
				function($matches) use ($event) {
					$before = $matches[1];
					$jsonData = $matches[3];
					$after = $matches[4];
					
					// Process template variables dalam JSON data
					$processedJsonData = $this->processJsonTemplateVariables($jsonData);
					
					// Convert onEventName to eventName (e.g., onPress -> press)
					$eventName = lcfirst(substr($event, 2)); 
					
					// Ensure href="javascript:void(0);" is added if not present
					$hrefAdded = '';
					if (strpos($after, 'href=') === false && strpos($before, 'href=') === false) {
						$hrefAdded = ' href="javascript:void(0);"';
					}
					
					return "<a{$before}onclick=\"{$eventName}(" . htmlspecialchars($processedJsonData, ENT_QUOTES) . ");\" {$hrefAdded}{$after}>";
				},
				$content
			);
		}
		
		// Process custom events pada elemen lain selain <a> (button, div, span, dll)
		$this->processCustomEventsOnOtherElements($content, $customEvents);
	}
	
	/**
	 * Process custom events pada elemen HTML lain selain <a>
	 */
	private function processCustomEventsOnOtherElements(string &$content, array $customEvents): void
	{
		$elements = ['button', 'div', 'span', 'input', 'select', 'textarea', 'img', 'li', 'td', 'th'];
		
		foreach ($elements as $element) {
			foreach ($customEvents as $event) {
				$content = preg_replace_callback(
					'/<' . $element . '([^>]*?)' . $event . '=(["\'])(.*?)\2([^>]*?)>/is',
					function($matches) use ($event, $element) {
						$before = $matches[1];
						$jsonData = $matches[3];
						$after = $matches[4];
						
						// Process template variables dalam JSON data
						$processedJsonData = $this->processJsonTemplateVariables($jsonData);
						
						// Convert onEventName to eventName
						$eventName = lcfirst(substr($event, 2));
						
											// Add onclick handler
					$onclickAdded = '';
					if (strpos($after, 'onclick=') === false && strpos($before, 'onclick=') === false) {
						$onclickAdded = ' onclick="' . $eventName . '(' . htmlspecialchars($processedJsonData, ENT_QUOTES) . ');"';
					}
					
					return "<{$element}{$before}{$onclickAdded}{$after}>";
					},
					$content
				);
			}
		}
	}
	
	/**
	 * Process template variables dalam JSON data
	 * Mengubah {variable_name} menjadi nilai sebenarnya dari template data
	 */
	private function processJsonTemplateVariables(string $jsonData): string
	{
		// Decode JSON data first
		$decodedData = json_decode($jsonData, true);
		
		if ($decodedData !== null) {
			// Process each value in the array for template variables
			array_walk_recursive($decodedData, function(&$value) {
				if (is_string($value) && preg_match('/^\{([^}]+)\}$/', $value, $matches)) {
					$varName = trim($matches[1]);
					
					// Parse variable with filters
					$parsed = $this->parse_variable_with_filters($varName);
					$variableName = $parsed['variable'];
					$filters = $parsed['filters'];
					
					// Get variable value (support block context if available)
					$varValue = '';
					
					// First try block context if available
					if (isset($this->current_block_data) && is_array($this->current_block_data)) {
						if (strpos($variableName, '.') !== false) {
							$varValue = $this->get_nested_value_from_block($variableName, $this->current_block_data);
						} else {
							$block_key = $this->find_case_insensitive_key_in_array($variableName, $this->current_block_data);
							if ($block_key !== false) {
								$varValue = (string)$this->current_block_data[$block_key];
							}
						}
					}
					
					// Fallback to global variables if not found in block
					if (empty($varValue)) {
						if (strpos($variableName, '.') !== false) {
							// Nested variable
							$varValue = $this->get_nested_value($variableName);
						} else {
							// Simple variable
							$found_key = $this->find_case_insensitive_key($variableName);
							if ($found_key !== false) {
								$varValue = $this->_tpldata['.'][$found_key];
								if (is_scalar($varValue)) {
									$varValue = (string)$varValue;
								}
							}
						}
					}
					
					// Apply filters if available
					if (!empty($filters) && $this->isFilterReady()) {
						$varValue = $this->apply_filters($varValue, $filters);
					}
					
					// Replace dengan nilai yang sudah diproses
					if ($varValue !== '') {
						$value = $varValue;
					}
				}
			});
			
			// Re-encode the processed data
			$processedJsonData = json_encode($decodedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} else {
			// Jika bukan JSON valid, process sebagai string biasa
			$processedJsonData = $jsonData;
			
			// Process individual template variables dalam string
			$processedJsonData = preg_replace_callback('/\{([^}]+)\}/', function($matches) {
				$varName = trim($matches[1]);
				
				// Parse variable with filters
				$parsed = $this->parse_variable_with_filters($varName);
				$variableName = $parsed['variable'];
				$filters = $parsed['filters'];
				
				// Get variable value (support block context if available)
				$varValue = '';
				
				// First try block context if available
				if (isset($this->current_block_data) && is_array($this->current_block_data)) {
					if (strpos($variableName, '.') !== false) {
						$varValue = $this->get_nested_value_from_block($variableName, $this->current_block_data);
					} else {
						$block_key = $this->find_case_insensitive_key_in_array($variableName, $this->current_block_data);
						if ($block_key !== false) {
							$varValue = (string)$this->current_block_data[$block_key];
						}
					}
				}
				
				// Fallback to global variables if not found in block
				if (empty($varValue)) {
					if (strpos($variableName, '.') !== false) {
						$varValue = $this->get_nested_value($variableName);
					} else {
						$found_key = $this->find_case_insensitive_key($variableName);
						if ($found_key !== false) {
							$varValue = $this->_tpldata['.'][$found_key];
							if (is_scalar($varValue)) {
								$varValue = (string)$varValue;
							}
						}
					}
				}
				
				// Apply filters if available
				if (!empty($filters) && $this->isFilterReady()) {
					$varValue = $this->apply_filters($varValue, $filters);
				}
				
				return $varValue !== '' ? $varValue : $matches[0];
			}, $processedJsonData);
		}
		
		return $processedJsonData;
	}
	
	/**
	 * PUBLIC: Enable/disable custom events processing
	 * Allows runtime control of custom events feature
	 */
	private bool $customEventsEnabled = true;
	
	public function enableCustomEvents(bool $enabled = true): void
	{
		$this->customEventsEnabled = $enabled;
	}
	
	public function isCustomEventsEnabled(): bool
	{
		return $this->customEventsEnabled;
	}
	
	/**
	 * PUBLIC: Add custom event to the supported events list
	 * Allows dynamic addition of new custom events
	 */
	private array $additionalCustomEvents = [];
	
	public function addCustomEvent(string $eventName): void
	{
		if (!in_array($eventName, $this->additionalCustomEvents)) {
			$this->additionalCustomEvents[] = $eventName;
		}
	}
	
	public function removeCustomEvent(string $eventName): void
	{
		$key = array_search($eventName, $this->additionalCustomEvents);
		if ($key !== false) {
			unset($this->additionalCustomEvents[$key]);
		}
	}
	
	public function getCustomEvents(): array
	{
		$defaultEvents = [
			'onPress', 'onModal', 'onSubmit', 'onRemove', 'onUpdate', 'onView',
			'onEdit', 'onDelete', 'onShow', 'onHide', 'onToggle', 'onRefresh',
			'onLoad', 'onSave'
		];
		
		return array_merge($defaultEvents, $this->additionalCustomEvents);
	}
	
	/**
	 * Assign first item from block as global variables
	 * Berguna untuk mengambil item pertama dari block dan menjadikannya sebagai global variables
	 * 
	 * Usage:
	 * $nexa->assign_block_vars('posts', $posts_data);
	 * $nexa->assign_first_item_as_global('posts', 'featured_post');
	 * 
	 * Template dapat menggunakan: {featured_post_title}, {featured_post_content}, dll
	 * 
	 * @param string $blockName Nama block yang akan diambil item pertamanya
	 * @param string $prefix Prefix untuk global variables (optional)
	 * @return bool Success status
	 */
	public function assign_first_item_as_global(string $blockName, string $prefix = ''): bool
	{
		// Check if block exists and has data
		if (!isset($this->_tpldata['.'][$blockName]) || !is_array($this->_tpldata['.'][$blockName])) {
			return false;
		}
		
		$blockData = $this->_tpldata['.'][$blockName];
		if (empty($blockData)) {
			return false;
		}
		
		// Get first item
		$firstItem = reset($blockData);
		if (!is_array($firstItem)) {
			return false;
		}
		
		// Prepare prefix
		$globalPrefix = !empty($prefix) ? $prefix . '_' : $blockName . '_first_';
		
		// Assign each field as global variable
		foreach ($firstItem as $key => $value) {
			$globalKey = $globalPrefix . $key;
			$this->assign_var($globalKey, $value);
		}
		
		return true;
	}
	
	/**
	 * Assign specific item from block as global variables
	 * Berguna untuk mengambil item tertentu (berdasarkan index) dari block
	 * 
	 * Usage:
	 * $nexa->assign_block_vars('posts', $posts_data);
	 * $nexa->assign_item_as_global('posts', 2, 'third_post'); // Index 2 = item ke-3
	 * 
	 * @param string $blockName Nama block
	 * @param int $index Index item yang akan diambil (0-based)
	 * @param string $prefix Prefix untuk global variables
	 * @return bool Success status
	 */
	public function assign_item_as_global(string $blockName, int $index, string $prefix = ''): bool
	{
		// Check if block exists and has data
		if (!isset($this->_tpldata['.'][$blockName]) || !is_array($this->_tpldata['.'][$blockName])) {
			return false;
		}
		
		$blockData = $this->_tpldata['.'][$blockName];
		if (!isset($blockData[$index]) || !is_array($blockData[$index])) {
			return false;
		}
		
		// Get specific item
		$item = $blockData[$index];
		
		// Prepare prefix
		$globalPrefix = !empty($prefix) ? $prefix . '_' : $blockName . '_item_' . $index . '_';
		
		// Assign each field as global variable
		foreach ($item as $key => $value) {
			$globalKey = $globalPrefix . $key;
			$this->assign_var($globalKey, $value);
		}
		
		return true;
	}
	
	/**
	 * Get first item from block without assigning as global
	 * Berguna untuk mendapatkan item pertama sebagai array
	 * 
	 * @param string $blockName Nama block
	 * @return array|false First item data or false if not found
	 */
	public function get_first_item(string $blockName): array|false
	{
		if (!isset($this->_tpldata['.'][$blockName]) || !is_array($this->_tpldata['.'][$blockName])) {
			return false;
		}
		
		$blockData = $this->_tpldata['.'][$blockName];
		if (empty($blockData)) {
			return false;
		}
		
		$firstItem = reset($blockData);
		return is_array($firstItem) ? $firstItem : false;
	}
	
	/**
	 * Get specific item from block
	 * 
	 * @param string $blockName Nama block
	 * @param int $index Index item (0-based)
	 * @return array|false Item data or false if not found
	 */
	public function get_item(string $blockName, int $index): array|false
	{
		if (!isset($this->_tpldata['.'][$blockName]) || !is_array($this->_tpldata['.'][$blockName])) {
			return false;
		}
		
		$blockData = $this->_tpldata['.'][$blockName];
		if (!isset($blockData[$index]) || !is_array($blockData[$index])) {
			return false;
		}
		
		return $blockData[$index];
	}
	
	/**
	 * Assign only first N items from existing block data
	 * Berguna untuk membatasi jumlah item yang ditampilkan
	 * 
	 * @param string $blockName Nama block
	 * @param int $limit Jumlah item yang akan diambil
	 * @param string $newBlockName Nama block baru (optional, default sama dengan blockName)
	 * @return bool Success status
	 */
	public function limit_block_items(string $blockName, int $limit, string $newBlockName = ''): bool
	{
		if (!isset($this->_tpldata['.'][$blockName]) || !is_array($this->_tpldata['.'][$blockName])) {
			return false;
		}
		
		$blockData = $this->_tpldata['.'][$blockName];
		$limitedData = array_slice($blockData, 0, $limit);
		
		$targetBlockName = !empty($newBlockName) ? $newBlockName : $blockName;
		$this->_tpldata['.'][$targetBlockName] = $limitedData;
		
		return true;
	}
	
	/**
	 * Proses atribut token pada elemen HTML dan simpan ke cookies
	 * Mendeteksi atribut token seperti: <div token="ipkd_ipkd_NX_174356654828164_single_1762142128131_222"></div>
	 * dan menyimpan nilainya ke cookies
	 * 
	 * @param string &$content Konten template yang akan diproses
	 * @return void
	 */
	private function processTokenAttributes(string &$content): void
	{
		// Early return jika tidak ada atribut token
		if (strpos($content, 'token=') === false && strpos($content, 'token =') === false) {
			return;
		}
		
		// ENHANCED: Pattern untuk menangkap atribut token pada elemen HTML
		// Mendukung berbagai format:
		// - <div token="value"> (dengan spasi)
		// - <div id="x"token="value"> (tanpa spasi)
		// - <div id="x" token="value"> (dengan spasi)
		// - <div token='value'> (single quote)
		// - <div token=value> (tanpa quote)
		$pattern = '/<[^>]*token\s*=\s*["\']?([^"\'>\s]+)["\']?[^>]*>/i';
		
		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				if (!isset($match[1]) || empty($match[1])) {
					continue;
				}
				
				$tokenValue = trim($match[1]);
				
				// Simpan token ke cookies
				if (!empty($tokenValue)) {
					$this->saveTokenToCookie($tokenValue);
				}
			}
		}
	}
	
	/**
	 * Simpan nilai token ke cookies
	 * Menggunakan nama cookie 'nexa_token' dan menyimpan multiple tokens sebagai array
	 * Token disimpan dengan path yang spesifik sesuai URL saat ini
	 * 
	 * @param string $tokenValue Nilai token yang akan disimpan
	 * @return void
	 */
	private function saveTokenToCookie(string $tokenValue): void
	{
		// Validasi bahwa headers belum dikirim
		if (headers_sent()) {
			// Jika headers sudah dikirim, log warning tapi jangan error
			error_log("NexaDom: Cannot set cookie 'nexa_token', headers already sent");
			return;
		}
		
		// ENHANCED: Dapatkan path spesifik untuk cookie berdasarkan URL saat ini
		$cookiePath = $this->getCurrentCookiePath();
		
		// ENHANCED: Nama cookie yang unik per path untuk menghindari konflik antar URL
		// Gunakan path sebagai bagian dari key untuk isolasi per URL
		$cookieName = $this->getCookieNameForPath($cookiePath);
		
		// Ambil token yang sudah ada di cookies untuk path ini (jika ada)
		$existingTokens = [];
		if (isset($_COOKIE[$cookieName])) {
			$cookieValue = $_COOKIE[$cookieName];
			// Coba decode JSON jika format JSON
			$decoded = json_decode($cookieValue, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$existingTokens = $decoded;
			} else {
				// Jika bukan JSON, simpan sebagai single value atau array
				$existingTokens = [$cookieValue];
			}
		}
		
		// Tambahkan token baru jika belum ada (menghindari duplikasi)
		if (!in_array($tokenValue, $existingTokens, true)) {
			$existingTokens[] = $tokenValue;
		}
		
		// Simpan sebagai JSON jika multiple tokens, atau string jika single
		if (count($existingTokens) === 1) {
			$cookieValue = $existingTokens[0];
		} else {
			$cookieValue = json_encode($existingTokens, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		
		// Set cookie dengan expiry 30 hari (2592000 detik)
		// httpOnly = false untuk akses JavaScript jika diperlukan
		// secure = false by default (set true jika menggunakan HTTPS)
		$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
		$httponly = false; // Allow JavaScript access jika diperlukan
		
		// ENHANCED: Set cookie dengan path spesifik, bukan '/' global
		setcookie(
			$cookieName,
			$cookieValue,
			[
				'expires' => time() + (30 * 24 * 60 * 60), // 30 hari
				'path' => $cookiePath, // Path spesifik sesuai URL saat ini
				'domain' => '',
				'secure' => $secure,
				'httponly' => $httponly,
				'samesite' => 'Lax'
			]
		);
		
		// Update $_COOKIE superglobal untuk akses langsung
		$_COOKIE[$cookieName] = $cookieValue;
	}
	
	/**
	 * Dapatkan path saat ini untuk cookie berdasarkan REQUEST_URI
	 * Path digunakan untuk membatasi scope cookie hanya pada URL tertentu
	 * 
	 * Contoh:
	 * - http://localhost/portal2/ipkd -> /portal2/ipkd
	 * - http://localhost/portal2/ipkd/list -> /portal2/ipkd
	 * 
	 * @return string Path untuk cookie (tanpa query string)
	 */
	private function getCurrentCookiePath(): string
	{
		// Ambil REQUEST_URI tanpa query string
		$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
		
		// Hapus query string jika ada
		if (($pos = strpos($requestUri, '?')) !== false) {
			$requestUri = substr($requestUri, 0, $pos);
		}
		
		// Normalisasi path
		$requestUri = rtrim($requestUri, '/');
		if (empty($requestUri)) {
			$requestUri = '/';
		}
		
		// ENHANCED: Ambil path hingga segment terakhir (misalnya /portal2/ipkd dari /portal2/ipkd/list)
		// Ini memastikan token berlaku untuk semua sub-path di bawah path utama
		$pathSegments = explode('/', trim($requestUri, '/'));
		
		// Ambil 2 segment pertama (base path + first segment) untuk scope yang lebih luas
		// Misalnya: /portal2/ipkd dari /portal2/ipkd/list/edit
		if (count($pathSegments) >= 2) {
			$cookiePath = '/' . implode('/', array_slice($pathSegments, 0, 2));
		} elseif (count($pathSegments) === 1 && !empty($pathSegments[0])) {
			$cookiePath = '/' . $pathSegments[0];
		} else {
			$cookiePath = '/';
		}
		
		// Pastikan path tidak kosong
		if (empty($cookiePath) || $cookiePath === '/') {
			// Fallback: gunakan SCRIPT_NAME jika tersedia
			$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
			if (!empty($scriptName)) {
				$scriptDir = dirname($scriptName);
				if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '\\') {
					$cookiePath = rtrim($scriptDir, '/');
				}
			}
		}
		
		// Normalisasi akhir
		if (empty($cookiePath) || $cookiePath === '/') {
			$cookiePath = '/';
		}
		
		return $cookiePath;
	}
	
	/**
	 * Generate nama cookie yang unik per path
	 * Memastikan token untuk path berbeda tidak saling bertabrakan
	 * 
	 * @param string $path Path untuk cookie
	 * @return string Nama cookie yang unik
	 */
	private function getCookieNameForPath(string $path): string
	{
		// Normalisasi path untuk digunakan sebagai bagian dari cookie name
		$normalizedPath = str_replace(['/', '\\'], '_', trim($path, '/'));
		if (empty($normalizedPath)) {
			$normalizedPath = 'root';
		}
		
		// Cookie name dengan path identifier
		return 'nexa_token_' . md5($path);
	}
	
	/**
	 * PUBLIC: Ambil semua tokens dari cookies untuk path saat ini
	 * 
	 * @param string|null $path Path spesifik (optional, default menggunakan path saat ini)
	 * @return array Array of tokens
	 */
	public function getTokensFromCookie(?string $path = null): array
	{
		// Gunakan path saat ini jika tidak ditentukan
		if ($path === null) {
			$path = $this->getCurrentCookiePath();
		}
		
		$cookieName = $this->getCookieNameForPath($path);
		
		if (!isset($_COOKIE[$cookieName])) {
			return [];
		}
		
		$cookieValue = $_COOKIE[$cookieName];
		
		// Coba decode JSON
		$decoded = json_decode($cookieValue, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			return $decoded;
		}
		
		// Jika bukan JSON, return sebagai single item array
		return [$cookieValue];
	}
	
	/**
	 * PUBLIC: Hapus semua tokens dari cookies untuk path saat ini
	 * 
	 * @param string|null $path Path spesifik (optional, default menggunakan path saat ini)
	 * @return bool Success status
	 */
	public function clearTokensFromCookie(?string $path = null): bool
	{
		// Gunakan path saat ini jika tidak ditentukan
		if ($path === null) {
			$path = $this->getCurrentCookiePath();
		}
		
		$cookieName = $this->getCookieNameForPath($path);
		
		if (headers_sent()) {
			return false;
		}
		
		// Hapus cookie dengan set expiry di masa lalu
		$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
		
		setcookie(
			$cookieName,
			'',
			[
				'expires' => time() - 3600, // 1 jam yang lalu
				'path' => $path, // Path spesifik
				'domain' => '',
				'secure' => $secure,
				'httponly' => false,
				'samesite' => 'Lax'
			]
		);
		
		// Update $_COOKIE superglobal
		unset($_COOKIE[$cookieName]);
		
		return true;
	}
	
	/**
	 * PUBLIC: Cek apakah token tertentu ada di cookies untuk path saat ini
	 * 
	 * @param string $tokenValue Token value yang akan dicek
	 * @param string|null $path Path spesifik (optional, default menggunakan path saat ini)
	 * @return bool True jika token ditemukan
	 */
	public function hasTokenInCookie(string $tokenValue, ?string $path = null): bool
	{
		$tokens = $this->getTokensFromCookie($path);
		return in_array($tokenValue, $tokens, true);
	}

	/**
	 * ========================================================================
	 * FITUR BARU: NESTED BLOCKS SUPPORT
	 * ========================================================================
	 * Mendukung array bersarang (nested array) dengan syntax BEGIN/END
	 * 
	 * Contoh penggunaan:
	 * 
	 * Controller:
	 * $data = [
	 *     ['id' => 1, 'nama' => 'Produk 1', 'items' => [
	 *         ['kode' => 'A1', 'warna' => 'Merah'],
	 *         ['kode' => 'A2', 'warna' => 'Biru']
	 *     ]],
	 *     ['id' => 2, 'nama' => 'Produk 2', 'items' => [
	 *         ['kode' => 'B1', 'warna' => 'Hijau']
	 *     ]]
	 * ];
	 * $nexa->assign_nested_blocks('produk', $data, 'items');
	 * 
	 * Template:
	 * <!-- BEGIN produk -->
	 *   <h3>{produk.nama}</h3>
	 *   <!-- BEGIN produk_items -->
	 *     <li>{produk_items.kode} - {produk_items.warna}</li>
	 *   <!-- END produk_items -->
	 * <!-- END produk -->
	 * 
	 * @param string $parentBlock Nama parent block
	 * @param array $data Array data dengan nested array
	 * @param string $nestedKey Key yang berisi nested array
	 * @param string $nestedBlockName Nama block untuk nested items (optional, default: {parentBlock}_{nestedKey})
	 * @return bool
	 */
	/**
	 * FITUR TRULY NESTED BLOCKS: Support nested array dengan proper scoping
	 * 
	 * CATATAN PENTING: NexaDom tidak support truly nested blocks secara native.
	 * Method ini menyediakan workaround dengan flatten data tapi menambahkan markers
	 * untuk grouping di template menggunakan conditional rendering.
	 * 
	 * @param string $parentBlock Nama block parent
	 * @param array $data Array data dengan nested arrays
	 * @param string $nestedKey Key untuk nested array di dalam parent data
	 * @param string $nestedBlockName Nama block untuk nested items (optional)
	 * @return bool
	 */
	public function assign_nested_blocks(string $parentBlock, array $data, string $nestedKey, string $nestedBlockName = ''): bool
	{
		if (empty($data) || !is_array($data)) {
			return false;
		}
		
		// Generate nested block name jika tidak disediakan
		if (empty($nestedBlockName)) {
			$nestedBlockName = $parentBlock . '_' . $nestedKey;
		}
		
		// Initialize blocks
		if (!isset($this->_tpldata['.'][$parentBlock])) {
			$this->_tpldata['.'][$parentBlock] = [];
		}
		if (!isset($this->_tpldata['.'][$nestedBlockName])) {
			$this->_tpldata['.'][$nestedBlockName] = [];
		}
		
		// Process each parent item
		$parentIndex = 0;
		foreach ($data as $parentItem) {
			if (!is_array($parentItem)) {
				continue;
			}
			
			// Separate nested data from parent data
			$parentData = $parentItem;
			$nestedData = [];
			
			if (isset($parentItem[$nestedKey]) && is_array($parentItem[$nestedKey])) {
				$nestedData = $parentItem[$nestedKey];
				unset($parentData[$nestedKey]); // Remove nested array from parent
			}
			
			// Add metadata dan markers
			$parentData['_has_' . $nestedKey] = !empty($nestedData);
			$parentData['_' . $nestedKey . '_count'] = count($nestedData);
			$parentData['_parent_index'] = $parentIndex;
			$parentData['_is_first'] = ($parentIndex === 0);
			
			// Assign parent data
			$this->_tpldata['.'][$parentBlock][] = $parentData;
			
			// Assign nested items dengan parent_index marker
			$nestedItemIndex = 0;
			foreach ($nestedData as $nestedItem) {
				if (is_array($nestedItem)) {
					$nestedItem['_parent_index'] = $parentIndex;
					$nestedItem['_is_first_in_parent'] = ($nestedItemIndex === 0);
					$nestedItem['_is_last_in_parent'] = ($nestedItemIndex === count($nestedData) - 1);
					$nestedItem['_item_index'] = $nestedItemIndex;
					$this->_tpldata['.'][$nestedBlockName][] = $nestedItem;
					$nestedItemIndex++;
				}
			}
			
			$parentIndex++;
		}
		
		return true;
	}
	
	/**
	 * FITUR BARU: Assign multiple nested blocks dengan berbagai level
	 * 
	 * Contoh:
	 * $nexa->assign_multi_nested_blocks('kategori', $data, [
	 *     'produk' => 'items',  // kategori.items akan jadi block 'kategori_produk'
	 *     'varian' => 'variants' // produk.variants akan jadi block 'produk_varian'
	 * ]);
	 * 
	 * @param string $parentBlock Nama parent block
	 * @param array $data Array data
	 * @param array $nestedMap Map nested keys [blockName => dataKey]
	 * @return bool
	 */
	public function assign_multi_nested_blocks(string $parentBlock, array $data, array $nestedMap): bool
	{
		if (empty($data) || empty($nestedMap)) {
			return false;
		}
		
		foreach ($nestedMap as $blockName => $dataKey) {
			$nestedBlockName = $parentBlock . '_' . $blockName;
			$this->assign_nested_blocks($parentBlock, $data, $dataKey, $nestedBlockName);
		}
		
		return true;
	}
	
	/**
	 * FITUR BARU: Assign nested blocks dengan auto-grouping
	 * Setiap parent item akan memiliki nested items yang di-group berdasarkan index parent
	 * 
	 * Template akan menggunakan syntax:
	 * <!-- BEGIN produk -->
	 *   <h3>{produk.nama}</h3>
	 *   <!-- BEGIN item -->
	 *     <li>{item.kode}</li>
	 *   <!-- END item -->
	 * <!-- END produk -->
	 * 
	 * @param string $parentBlock Nama parent block
	 * @param array $data Array data dengan nested array
	 * @param string $nestedKey Key yang berisi nested array
	 * @return bool
	 */
	public function assign_grouped_nested_blocks(string $parentBlock, array $data, string $nestedKey): bool
	{
		if (empty($data) || !is_array($data)) {
			return false;
		}
		
		// Initialize parent block
		if (!isset($this->_tpldata['.'][$parentBlock])) {
			$this->_tpldata['.'][$parentBlock] = [];
		}
		
		// Process each parent item
		foreach ($data as $index => $parentItem) {
			if (!is_array($parentItem)) {
				continue;
			}
			
			// Prepare parent data
			$parentData = $parentItem;
			$nestedData = [];
			
			if (isset($parentItem[$nestedKey]) && is_array($parentItem[$nestedKey])) {
				$nestedData = $parentItem[$nestedKey];
			}
			
			// Convert nested array to indexed format for template access
			foreach ($nestedData as $nestedIndex => $nestedItem) {
				if (is_array($nestedItem)) {
					foreach ($nestedItem as $key => $value) {
						// Store as: parent.nested.0.key, parent.nested.1.key, etc
						$parentData[$nestedKey . '.' . $nestedIndex . '.' . $key] = $value;
					}
				}
			}
			
			// Add metadata
			$parentData['_' . $nestedKey . '_count'] = count($nestedData);
			
			// Assign parent with flattened nested data
			$this->_tpldata['.'][$parentBlock][] = $parentData;
		}
		
		return true;
	}
	
	/**
	 * FITUR BARU: Debug helper untuk nested blocks
	 * 
	 * @param string $blockName Nama block yang akan di-debug
	 * @return array Debug information
	 */
	public function debug_nested_blocks(string $blockName = ''): array
	{
		$result = [
			'all_blocks' => array_keys($this->_tpldata['.']),
			'block_counts' => []
		];
		
		foreach ($this->_tpldata['.'] as $name => $data) {
			$result['block_counts'][$name] = is_array($data) ? count($data) : 0;
		}
		
		if (!empty($blockName) && isset($this->_tpldata['.'][$blockName])) {
			$result['block_data'] = $this->_tpldata['.'][$blockName];
			$result['sample_item'] = $this->_tpldata['.'][$blockName][0] ?? null;
		}
		
		return $result;
	}
	
	/**
	 * AUTO SEO META TAGS GENERATOR
	 * 
	 * Otomatis mendeteksi meta tags sederhana dan generate lengkap untuk SEO.
	 * Cukup assign meta tags sederhana, sistem akan auto-generate:
	 * - Standard meta tags (title, description, keywords, image)
	 * - Open Graph tags (og:title, og:description, og:image, dll)
	 * - Twitter Card tags (twitter:title, twitter:description, twitter:image)
	 * 
	 * Contoh penggunaan di controller:
	 * ```php
	 * $this->nexaVars([
	 *     'meta_title' => 'Judul Halaman',
	 *     'meta_description' => 'Deskripsi halaman',
	 *     'meta_keywords' => 'keyword1, keyword2',
	 *     'meta_image' => 'http://example.com/image.png'
	 * ]);
	 * ```
	 * 
	 * Sistem akan otomatis generate semua variable:
	 * {page_title}, {page_description}, {page_keywords}, {og_title}, 
	 * {og_description}, {og_image}, {twitter_title}, {twitter_description}, 
	 * {twitter_image}, {canonical_url}, dll.
	 * 
	 * @return void
	 */
	public function autoGenerateSeoMeta(): void
	{
		// Deteksi meta tags sederhana
		$metaTitle = $this->_tpldata['.']['meta_title'] ?? 
					 $this->_tpldata['.']['title'] ?? 
					 $this->_tpldata['.']['page_title'] ?? '';
		
		$metaDescription = $this->_tpldata['.']['meta_description'] ?? 
						   $this->_tpldata['.']['description'] ?? 
						   $this->_tpldata['.']['page_description'] ?? '';
		
		$metaKeywords = $this->_tpldata['.']['meta_keywords'] ?? 
						$this->_tpldata['.']['keywords'] ?? 
						$this->_tpldata['.']['page_keywords'] ?? '';
		
		$metaImage = $this->_tpldata['.']['meta_image'] ?? 
					 $this->_tpldata['.']['image'] ?? 
					 $this->_tpldata['.']['og_image'] ?? '';
		
		// Jika tidak ada meta tags, skip
		if (empty($metaTitle) && empty($metaDescription)) {
			return;
		}
		
		// Get current URL untuk canonical dan social media
		$currentUrl = $this->_tpldata['.']['canonical_url'] ?? 
					  $this->_tpldata['.']['current_url'] ?? 
					  $this->getCurrentUrl();
		
		// Auto-generate semua meta tags jika belum ada
		$autoMeta = [
			// Standard meta tags
			'page_title' => $metaTitle,
			'page_description' => $metaDescription,
			'page_keywords' => $metaKeywords,
			
			// Open Graph
			'og_type' => $this->_tpldata['.']['og_type'] ?? 'website',
			'og_url' => $this->_tpldata['.']['og_url'] ?? $currentUrl,
			'og_title' => $this->_tpldata['.']['og_title'] ?? $metaTitle,
			'og_description' => $this->_tpldata['.']['og_description'] ?? $metaDescription,
			'og_image' => $metaImage,
			'og_site_name' => $this->_tpldata['.']['og_site_name'] ?? $this->_tpldata['.']['app_name'] ?? '',
			'og_locale' => $this->_tpldata['.']['og_locale'] ?? 'id_ID',
			
			// Twitter Card
			'twitter_card' => $this->_tpldata['.']['twitter_card'] ?? 'summary_large_image',
			'twitter_url' => $this->_tpldata['.']['twitter_url'] ?? $currentUrl,
			'twitter_title' => $this->_tpldata['.']['twitter_title'] ?? $metaTitle,
			'twitter_description' => $this->_tpldata['.']['twitter_description'] ?? $metaDescription,
			'twitter_image' => $this->_tpldata['.']['twitter_image'] ?? $metaImage,
			
			// Additional
			'canonical_url' => $currentUrl,
			'schema_image' => $this->_tpldata['.']['schema_image'] ?? $metaImage,
		];
		
		// Assign hanya jika belum ada (tidak override existing)
		foreach ($autoMeta as $key => $value) {
			if (!isset($this->_tpldata['.'][$key]) && !empty($value)) {
				$this->_tpldata['.'][$key] = $value;
			}
		}
	}
	
	/**
	 * Get current URL
	 * 
	 * @return string Current URL
	 */
	private function getCurrentUrl(): string
	{
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		
		return $protocol . '://' . $host . $uri;
	}
} 