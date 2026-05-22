<?php
namespace App\System\Helpers;

class NexaSystem
{
	private $templateDir;
	
	public function __construct()
	{
		$this->templateDir = dirname(__DIR__, 2) . '/templates/';
	}
	
	/**
	 * 1. Dapat menambah file HTML
	 * Add a new HTML file
	 */
	public function addHtmlFile($filename, $content = '', $subfolder = '')
	{
		$fullPath = $this->getFullPath($filename, $subfolder);
		
		// Create directory if it doesn't exist
		$directory = dirname($fullPath);
		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (file_exists($fullPath)) {
			throw new \Exception("File already exists: " . $fullPath);
		}
		
		$defaultContent = $content ?: $this->getDefaultHtmlContent();
		
		if (file_put_contents($fullPath, $defaultContent) !== false) {
			return "HTML file created successfully: " . $fullPath;
		} else {
			throw new \Exception("Failed to create HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 1B. Menambah file HTML dengan menentukan lokasi penyimpanan secara lengkap
	 * Add HTML file with custom full path location
	 */
	public function addHtmlFileWithPath($filePath, $content = '')
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($filePath);
		
		// Create directory if it doesn't exist
		$directory = dirname($fullPath);
		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (file_exists($fullPath)) {
			throw new \Exception("File already exists: " . $fullPath);
		}
		
		$defaultContent = $content ?: $this->getDefaultHtmlContent();
		
		if (file_put_contents($fullPath, $defaultContent) !== false) {
			return [
				'success' => true,
				'message' => "HTML file created successfully",
				'path' => $fullPath,
				'relative_path' => str_replace($this->templateDir, '', $fullPath)
			];
		} else {
			throw new \Exception("Failed to create HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 2. Dapat mengubah isi file HTML
	 * Edit HTML file content
	 */
	public function editHtmlFile($filename, $newContent, $subfolder = '')
	{
		$fullPath = $this->getFullPath($filename, $subfolder);
		
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		if (file_put_contents($fullPath, $newContent) !== false) {
			return "HTML file updated successfully: " . $fullPath;
		} else {
			throw new \Exception("Failed to update HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 2B. Mengubah isi file HTML berdasarkan lokasi file (path lengkap)
	 * Edit HTML file content based on file location (full path)
	 */
	public function editHtmlFileWithPath($filePath, $newContent)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($filePath);
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		// Backup original content before editing
		$originalContent = file_get_contents($fullPath);
		
		if (file_put_contents($fullPath, $newContent) !== false) {
			return [
				'success' => true,
				'message' => "HTML file updated successfully",
				'path' => $fullPath,
				'relative_path' => str_replace($this->templateDir, '', $fullPath),
				'file_size_before' => strlen($originalContent),
				'file_size_after' => strlen($newContent),
				'backup_content' => $originalContent // Optional: bisa digunakan untuk undo
			];
		} else {
			throw new \Exception("Failed to update HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 2C. Mengubah sebagian isi file HTML berdasarkan lokasi (replace content)
	 * Replace partial content in HTML file based on location
	 */
	public function replaceContentInFile($filePath, $searchText, $replaceText, $caseSensitive = false)
	{
		// Normalize path
		$fullPath = $this->normalizePath($filePath);
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		// Read current content
		$content = file_get_contents($fullPath);
		
		// Count occurrences before replacement
		if ($caseSensitive) {
			$occurrences = substr_count($content, $searchText);
			$newContent = str_replace($searchText, $replaceText, $content);
		} else {
			$occurrences = substr_count(strtolower($content), strtolower($searchText));
			$newContent = str_ireplace($searchText, $replaceText, $content);
		}
		
		// Count replacements (should equal occurrences if all were replaced)
		$replaceCount = $occurrences;
		
		// Write back
		if (file_put_contents($fullPath, $newContent) !== false) {
			return [
				'success' => true,
				'message' => "Content replaced successfully",
				'path' => $fullPath,
				'replacements_count' => $replaceCount,
				'search_text' => $searchText,
				'replace_text' => $replaceText
			];
		} else {
			throw new \Exception("Failed to update HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 3. Dapat menghapus file HTML
	 * Delete HTML file
	 */
	public function deleteHtmlFile($filename, $subfolder = '')
	{
		$fullPath = $this->getFullPath($filename, $subfolder);
		
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		if (unlink($fullPath)) {
			return "HTML file deleted successfully: " . $fullPath;
		} else {
			throw new \Exception("Failed to delete HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 3B. Menghapus file HTML berdasarkan lokasi file (path lengkap)
	 * Delete HTML file based on file location (full path)
	 */
	public function deleteHtmlFileWithPath($filePath)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($filePath);
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		// Validasi: Hanya boleh menghapus file di dalam templateDir
		if (!$this->isPathInTemplateDir($fullPath)) {
			throw new \Exception("File deletion is only allowed within templates directory. Path: " . $fullPath);
		}
		
		// Validasi: Tidak boleh menghapus file di dalam folder yang dilindungi
		if ($this->isProtectedFolder($fullPath)) {
			$protectedFolders = implode(', ', $this->getProtectedFolders());
			throw new \Exception("Cannot delete file in protected folders: " . $protectedFolders . ". Path: " . $fullPath);
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		// Get file info before deletion
		$fileInfo = [
			'path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'size' => filesize($fullPath),
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
		
		if (unlink($fullPath)) {
			return [
				'success' => true,
				'message' => "HTML file deleted successfully",
				'deleted_file' => $fileInfo
			];
		} else {
			throw new \Exception("Failed to delete HTML file: " . $fullPath);
		}
	}
	
	/**
	 * 4. Dapat melihat daftar file berdasarkan struktur foldernya
	 * List HTML files based on folder structure
	 */
	public function listHtmlFiles($subfolder = '', $recursive = true)
	{
		$searchPath = $this->templateDir;
		
		if (!empty($subfolder)) {
			$searchPath .= trim($subfolder, '/') . '/';
		}
		
		if (!is_dir($searchPath)) {
			throw new \Exception("Directory not found: " . $searchPath);
		}
		
		return $this->scanHtmlFiles($searchPath, $recursive);
	}
	
	/**
	 * Get HTML file content
	 */
	public function getHtmlFileContent($filename, $subfolder = '')
	{
		$fullPath = $this->getFullPath($filename, $subfolder);
		
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		return file_get_contents($fullPath);
	}
	
	/**
	 * Get HTML file content berdasarkan lokasi file (path lengkap)
	 * Get HTML file content based on file location (full path)
	 */
	public function getHtmlFileContentWithPath($filePath)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($filePath);
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		if (!file_exists($fullPath)) {
			throw new \Exception("HTML file not found: " . $fullPath);
		}
		
		if (!$this->isHtmlFile($fullPath)) {
			throw new \Exception("File is not an HTML file: " . $fullPath);
		}
		
		$content = file_get_contents($fullPath);
		
		return [
			'content' => $content,
			'path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'size' => strlen($content),
			'lines' => substr_count($content, "\n") + 1,
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
	}
	
	/**
	 * Check if file exists
	 */
	public function htmlFileExists($filename, $subfolder = '')
	{
		$fullPath = $this->getFullPath($filename, $subfolder);
		
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		return file_exists($fullPath) && $this->isHtmlFile($fullPath);
	}
	
	/**
	 * Check if file exists berdasarkan lokasi file (path lengkap)
	 * Check if file exists based on file location (full path)
	 */
	public function htmlFileExistsWithPath($filePath)
	{
		// Normalize path
		$fullPath = $this->normalizePath($filePath);
		
		// Add .html extension if not present
		if (!str_ends_with($fullPath, '.html')) {
			$fullPath .= '.html';
		}
		
		$exists = file_exists($fullPath) && $this->isHtmlFile($fullPath);
		
		if ($exists) {
			return [
				'exists' => true,
				'path' => $fullPath,
				'relative_path' => str_replace($this->templateDir, '', $fullPath),
				'size' => filesize($fullPath),
				'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
			];
		}
		
		return [
			'exists' => false,
			'path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath)
		];
	}
	
	/**
	 * FITUR BARU: Membuat folder/direktori baru
	 * Create new folder/directory
	 */
	public function createFolder($folderName, $parentFolder = '')
	{
		$fullPath = $this->templateDir;
		
		if (!empty($parentFolder)) {
			$fullPath .= trim($parentFolder, '/') . '/';
		}
		
		$fullPath .= trim($folderName, '/');
		
		if (is_dir($fullPath)) {
			throw new \Exception("Folder already exists: " . $fullPath);
		}
		
		if (mkdir($fullPath, 0755, true)) {
			return "Folder created successfully: " . $fullPath;
		} else {
			throw new \Exception("Failed to create folder: " . $fullPath);
		}
	}
	
	/**
	 * FITUR BARU: Membuat struktur folder bertingkat
	 * Create nested folder structure
	 */
	public function createNestedFolders($folderPath)
	{
		$fullPath = $this->templateDir . trim($folderPath, '/');
		
		if (is_dir($fullPath)) {
			throw new \Exception("Folder structure already exists: " . $fullPath);
		}
		
		if (mkdir($fullPath, 0755, true)) {
			return "Nested folder structure created successfully: " . $fullPath;
		} else {
			throw new \Exception("Failed to create nested folder structure: " . $fullPath);
		}
	}
	
	/**
	 * FITUR BARU: Menghapus folder (kosong atau dengan isi)
	 * Delete folder (empty or with contents)
	 */
	public function deleteFolder($folderName, $parentFolder = '', $forceDelete = false)
	{
		$fullPath = $this->templateDir;
		
		if (!empty($parentFolder)) {
			$fullPath .= trim($parentFolder, '/') . '/';
		}
		
		$fullPath .= trim($folderName, '/');
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Folder not found: " . $fullPath);
		}
		
		if ($forceDelete) {
			$this->deleteFolderRecursive($fullPath);
			return "Folder and all contents deleted successfully: " . $fullPath;
		} else {
			if (rmdir($fullPath)) {
				return "Empty folder deleted successfully: " . $fullPath;
			} else {
				throw new \Exception("Failed to delete folder (may not be empty): " . $fullPath);
			}
		}
	}
	
	/**
	 * Menghapus folder berdasarkan lokasi folder (path lengkap)
	 * Delete folder based on folder location (full path)
	 */
	public function deleteFolderWithPath($folderPath, $forceDelete = false)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		// Validasi: Hanya boleh menghapus folder di dalam templateDir
		if (!$this->isPathInTemplateDir($fullPath)) {
			throw new \Exception("Folder deletion is only allowed within templates directory. Path: " . $fullPath);
		}
		
		// Validasi: Tidak boleh menghapus folder yang dilindungi
		if ($this->isProtectedFolder($fullPath)) {
			$protectedFolders = implode(', ', $this->getProtectedFolders());
			throw new \Exception("Cannot delete protected folders: " . $protectedFolders . ". Path: " . $fullPath);
		}
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Folder not found: " . $fullPath);
		}
		
		// Get folder info before deletion
		// Normalize path separators untuk perbandingan yang benar
		$templateDirNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->templateDir);
		$fullPathNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
		$relativePath = str_replace($templateDirNormalized, '', $fullPathNormalized);
		$relativePath = trim($relativePath, '/\\');
		// Normalize kembali ke forward slash untuk konsistensi
		$relativePath = str_replace('\\', '/', $relativePath);
		
		$folderInfo = [
			'path' => $fullPath,
			'relative_path' => $relativePath,
			'created' => date('Y-m-d H:i:s', filectime($fullPath)),
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
		
		// Count files and subfolders before deletion
		try {
			$htmlFiles = $this->scanHtmlFiles($fullPath, true);
			$subFolders = $this->getSubFolders($fullPath);
			$folderInfo['html_files_count'] = count($htmlFiles);
			$folderInfo['subfolders_count'] = count($subFolders);
			$folderInfo['total_items'] = count($htmlFiles) + count($subFolders);
		} catch (\Exception $e) {
			$folderInfo['html_files_count'] = 0;
			$folderInfo['subfolders_count'] = 0;
			$folderInfo['total_items'] = 0;
		}
		
		if ($forceDelete) {
			// Delete folder and all contents recursively
			$this->deleteFolderRecursive($fullPath);
			$folderInfo['deletion_type'] = 'recursive';
			
			return [
				'success' => true,
				'message' => "Folder and all contents deleted successfully",
				'deleted_folder' => $folderInfo
			];
		} else {
			// Try to delete empty folder only
			if (rmdir($fullPath)) {
				$folderInfo['deletion_type'] = 'empty';
				
				return [
					'success' => true,
					'message' => "Empty folder deleted successfully",
					'deleted_folder' => $folderInfo
				];
			} else {
				throw new \Exception("Failed to delete folder (may not be empty). Use forceDelete=true to delete folder with contents: " . $fullPath);
			}
		}
	}
	
	/**
	 * FITUR BARU: Melihat struktur folder
	 * View folder structure
	 */
	public function getFolderStructure($startPath = '', $maxDepth = 3)
	{
		$basePath = $this->templateDir;
		
		if (!empty($startPath)) {
			$basePath .= trim($startPath, '/') . '/';
		}
		
		if (!is_dir($basePath)) {
			throw new \Exception("Directory not found: " . $basePath);
		}
		
		return $this->buildFolderTree($basePath, 0, $maxDepth);
	}
	
	/**
	 * FITUR BARU: Memindahkan/rename folder
	 * Move/rename folder
	 */
	public function moveFolder($oldPath, $newPath)
	{
		$oldFullPath = $this->templateDir . trim($oldPath, '/');
		$newFullPath = $this->templateDir . trim($newPath, '/');
		
		if (!is_dir($oldFullPath)) {
			throw new \Exception("Source folder not found: " . $oldFullPath);
		}
		
		if (is_dir($newFullPath)) {
			throw new \Exception("Destination folder already exists: " . $newFullPath);
		}
		
		// Create parent directory if it doesn't exist
		$parentDir = dirname($newFullPath);
		if (!is_dir($parentDir)) {
			mkdir($parentDir, 0755, true);
		}
		
		if (rename($oldFullPath, $newFullPath)) {
			return "Folder moved successfully from " . $oldFullPath . " to " . $newFullPath;
		} else {
			throw new \Exception("Failed to move folder from " . $oldFullPath . " to " . $newFullPath);
		}
	}
	
	/**
	 * FITUR BARU: Cek apakah folder ada
	 * Check if folder exists
	 */
	public function folderExists($folderPath)
	{
		// Normalize path untuk konsistensi (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		return is_dir($fullPath);
	}
	
	/**
	 * FITUR BARU: Mendapatkan informasi folder
	 * Get folder information
	 */
	public function getFolderInfo($folderPath)
	{
		$fullPath = $this->templateDir . trim($folderPath, '/');
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Folder not found: " . $fullPath);
		}
		
		$htmlFiles = $this->scanHtmlFiles($fullPath, false);
		$subFolders = $this->getSubFolders($fullPath);
		
		return [
			'path' => $fullPath,
			'relative_path' => trim($folderPath, '/'),
			'html_files_count' => count($htmlFiles),
			'html_files' => $htmlFiles,
			'subfolders_count' => count($subFolders),
			'subfolders' => $subFolders,
			'created' => date('Y-m-d H:i:s', filectime($fullPath)),
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
	}
	
	/**
	 * 4B. Melihat daftar file HTML berdasarkan lokasi folder (path lengkap)
	 * List HTML files based on folder location (full path)
	 */
	public function listHtmlFilesWithPath($folderPath, $recursive = true)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Directory not found: " . $fullPath);
		}
		
		$htmlFiles = $this->scanHtmlFiles($fullPath, $recursive);
		
		return [
			'folder_path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'total_files' => count($htmlFiles),
			'files' => $htmlFiles,
			'recursive' => $recursive
		];
	}
	
	/**
	 * 4C. Melihat daftar isi folder (file dan subfolder) berdasarkan lokasi (path lengkap)
	 * List folder contents (files and subfolders) based on location (full path)
	 */
	public function listFolderContentsWithPath($folderPath, $recursive = false)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Directory not found: " . $fullPath);
		}
		
		$htmlFiles = $this->scanHtmlFiles($fullPath, $recursive);
		$subFolders = $this->getSubFolders($fullPath);
		
		// If recursive, get all subfolders recursively
		if ($recursive) {
			$subFolders = $this->getSubFoldersRecursive($fullPath);
		}
		
		return [
			'folder_path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'html_files_count' => count($htmlFiles),
			'html_files' => $htmlFiles,
			'subfolders_count' => count($subFolders),
			'subfolders' => $subFolders,
			'total_items' => count($htmlFiles) + count($subFolders),
			'recursive' => $recursive,
			'created' => date('Y-m-d H:i:s', filectime($fullPath)),
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
	}
	
	/**
	 * 4D. Melihat struktur folder berdasarkan lokasi (path lengkap)
	 * Get folder structure based on location (full path)
	 */
	public function getFolderStructureWithPath($folderPath, $maxDepth = 3)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Directory not found: " . $fullPath);
		}
		
		$tree = $this->buildFolderTree($fullPath, 0, $maxDepth);
		
		return [
			'folder_path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'max_depth' => $maxDepth,
			'structure' => $tree
		];
	}
	
	/**
	 * 4E. Mendapatkan informasi folder berdasarkan lokasi (path lengkap)
	 * Get folder information based on location (full path)
	 */
	public function getFolderInfoWithPath($folderPath)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Folder not found: " . $fullPath);
		}
		
		$htmlFiles = $this->scanHtmlFiles($fullPath, false);
		$subFolders = $this->getSubFolders($fullPath);
		
		return [
			'path' => $fullPath,
			'relative_path' => str_replace($this->templateDir, '', $fullPath),
			'html_files_count' => count($htmlFiles),
			'html_files' => $htmlFiles,
			'subfolders_count' => count($subFolders),
			'subfolders' => $subFolders,
			'total_items' => count($htmlFiles) + count($subFolders),
			'created' => date('Y-m-d H:i:s', filectime($fullPath)),
			'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
		];
	}
	
	/**
	 * 4F. Mendapatkan struktur menu tree untuk JavaScript berdasarkan lokasi (path lengkap)
	 * Get menu tree structure for JavaScript based on location (full path)
	 * 
	 * Format output sesuai dengan struktur menu tree yang digunakan di NexaDirectory.js:
	 * {
	 *   "main_menu": [
	 *     {
	 *       "label": "Folder Name",
	 *       "folder": "Folder Name",
	 *       "children": [
	 *         {
	 *           "label": "File Name",
	 *           "file": "File Name",
	 *           "folder": "path",
	 *           "href": "path/to/file",
	 *           "hrefOrigin": "Path/To/File"
	 *         }
	 *       ]
	 *     }
	 *   ]
	 * }
	 */
	public function getMenuTreeStructure($folderPath, $menuKey = 'main_menu', $maxDepth = 10, $baseUrl = '')
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Directory not found: " . $fullPath);
		}
		
		// Build menu tree structure
		$menuTree = $this->buildMenuTree($fullPath, $fullPath, 0, $maxDepth, $baseUrl);
		
		return [
			$menuKey => $menuTree
		];
	}
	
	/**
	 * 4G. Mendapatkan struktur menu tree untuk JavaScript (versi dengan custom options)
	 * Get menu tree structure for JavaScript (version with custom options)
	 * 
	 * @param string $folderPath Path folder yang akan dijadikan menu tree
	 * @param string $menuKey Key untuk menu utama (default: 'main_menu')
	 * @param int $maxDepth Kedalaman maksimal folder (default: 10)
	 * @param string $baseUrl Base URL untuk href (default: '')
	 * @param bool $includeHtmlExtension Apakah include .html di href (default: false)
	 * @param callable|null $labelFormatter Function untuk format label (default: null)
	 * @param callable|null $filterFunction Function untuk filter item (default: null)
	 * @return array Struktur menu tree
	 */
	public function getMenuTreeStructureAdvanced(
		$folderPath, 
		$menuKey = 'main_menu', 
		$maxDepth = 10, 
		$baseUrl = '', 
		$includeHtmlExtension = false,
		$labelFormatter = null,
		$filterFunction = null
	)
	{
		// Normalize path (handle both absolute and relative paths)
		$fullPath = $this->normalizePath($folderPath);
		
		if (!is_dir($fullPath)) {
			throw new \Exception("Directory not found: " . $fullPath);
		}
		
		// Build menu tree structure with advanced options
		$menuTree = $this->buildMenuTreeAdvanced(
			$fullPath, 
			$fullPath, 
			0, 
			$maxDepth, 
			$baseUrl, 
			$includeHtmlExtension,
			$labelFormatter,
			$filterFunction
		);
		
		return [
			$menuKey => $menuTree
		];
	}
	
	/**
	 * Private helper methods
	 */
	private function getFullPath($filename, $subfolder = '')
	{
		$path = $this->templateDir;
		
		if (!empty($subfolder)) {
			$path .= trim($subfolder, '/') . '/';
		}
		
		return $path . $filename;
	}
	
	/**
	 * Normalize path - handle both absolute and relative paths
	 * Jika path relative, akan disimpan di templateDir
	 * Jika path absolute, akan digunakan sesuai yang diberikan
	 */
	private function normalizePath($filePath)
	{
		$filePath = trim($filePath);
		
		// Check if it's an absolute path
		// Windows: C:\ or C:/ or \\server
		// Unix: /path/to/file
		$isAbsolute = false;
		
		if (preg_match('/^[a-z]:[\\\\\/]/i', $filePath)) {
			// Windows absolute path (C:\ or C:/)
			$isAbsolute = true;
		} elseif (strpos($filePath, DIRECTORY_SEPARATOR) === 0) {
			// Unix absolute path (starts with /)
			$isAbsolute = true;
		} elseif (strpos($filePath, '\\\\') === 0) {
			// Windows UNC path (\\server)
			$isAbsolute = true;
		}
		
		if ($isAbsolute) {
			// Absolute path - normalize separators but keep as is
			// Normalize path separators untuk konsistensi
			$filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
			return $filePath;
		} else {
			// Relative path - prepend templateDir
			// Remove leading slashes for relative paths
			$filePath = ltrim($filePath, '/\\');
			// Normalize path separators
			$filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
			$templateDirNormalized = rtrim($this->templateDir, '/\\');
			// Normalize templateDir separators juga
			$templateDirNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $templateDirNormalized);
			return $templateDirNormalized . DIRECTORY_SEPARATOR . $filePath;
		}
	}
	
	/**
	 * Cek apakah lokasi penyimpanan valid dan writable
	 * Check if save location is valid and writable
	 */
	public function isLocationWritable($filePath)
	{
		$fullPath = $this->normalizePath($filePath);
		$directory = dirname($fullPath);
		
		// Check if directory exists and is writable
		if (is_dir($directory)) {
			return is_writable($directory);
		}
		
		// Check if parent directory is writable (so we can create the directory)
		$parentDir = dirname($directory);
		return is_dir($parentDir) && is_writable($parentDir);
	}
	
	/**
	 * Mendapatkan informasi lokasi penyimpanan
	 * Get save location information
	 */
	public function getLocationInfo($filePath)
	{
		$fullPath = $this->normalizePath($filePath);
		$directory = dirname($fullPath);
		
		// Check if original path is absolute
		$originalPath = trim($filePath);
		$isAbsolute = false;
		if (preg_match('/^[a-z]:[\\\\\/]/i', $originalPath)) {
			$isAbsolute = true;
		} elseif (strpos($originalPath, DIRECTORY_SEPARATOR) === 0) {
			$isAbsolute = true;
		} elseif (strpos($originalPath, '\\\\') === 0) {
			$isAbsolute = true;
		}
		
		$info = [
			'full_path' => $fullPath,
			'directory' => $directory,
			'is_absolute' => $isAbsolute,
			'directory_exists' => is_dir($directory),
			'is_writable' => $this->isLocationWritable($filePath),
			'file_exists' => file_exists($fullPath),
			'relative_to_template' => str_replace($this->templateDir, '', $fullPath)
		];
		
		if (is_dir($directory)) {
			$info['directory_permissions'] = substr(sprintf('%o', fileperms($directory)), -4);
		}
		
		return $info;
	}
	
	/**
	 * Mendapatkan daftar folder yang dilindungi (tidak bisa dihapus)
	 * Get list of protected folders (cannot be deleted)
	 */
	private function getProtectedFolders()
	{
		return ['dashboard', 'tablet', 'mobile', 'theme'];
	}
	
	/**
	 * Mendapatkan daftar folder yang dilindungi (public method)
	 * Get list of protected folders (public method)
	 */
	public function getProtectedFoldersList()
	{
		return $this->getProtectedFolders();
	}
	
	/**
	 * Cek apakah path adalah folder yang dilindungi (public method)
	 * Check if path is a protected folder (public method)
	 */
	public function isPathProtected($path)
	{
		$fullPath = $this->normalizePath($path);
		return $this->isProtectedFolder($fullPath);
	}
	
	/**
	 * Cek apakah path berada di dalam templateDir
	 * Check if path is within templateDir
	 */
	private function isPathInTemplateDir($fullPath)
	{
		$templateDirNormalized = realpath($this->templateDir);
		
		// Check if it's a file or directory
		if (is_dir($fullPath)) {
			$fullPathNormalized = realpath($fullPath);
		} else {
			$fullPathNormalized = realpath(dirname($fullPath));
		}
		
		if ($templateDirNormalized === false || $fullPathNormalized === false) {
			return false;
		}
		
		// Check if fullPath is within templateDir
		return strpos($fullPathNormalized, $templateDirNormalized) === 0;
	}
	
	/**
	 * Cek apakah folder/file adalah folder yang dilindungi
	 * Check if folder/file is a protected folder
	 * 
	 * Logic: Hanya folder root protected yang tidak bisa dihapus
	 * Subfolder di dalam protected folder (seperti theme/subfolder) bisa dihapus
	 */
	private function isProtectedFolder($fullPath)
	{
		$protectedFolders = $this->getProtectedFolders();
		$relativePath = str_replace($this->templateDir, '', $fullPath);
		$relativePath = trim($relativePath, '/\\');
		
		if (empty($relativePath)) {
			return false;
		}
		
		// Normalize path separators
		$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
		
		// Split path into segments
		$pathParts = array_filter(explode(DIRECTORY_SEPARATOR, $relativePath));
		
		if (empty($pathParts)) {
			return false;
		}
		
		// Hanya cek apakah segment PERTAMA (root folder) adalah protected folder
		// Dan hanya jika path tersebut ADALAH folder protected itu sendiri (tidak ada subfolder)
		// Contoh:
		// - "theme" -> protected (true)
		// - "theme/subfolder" -> tidak protected (false, karena ini subfolder)
		// - "dashboard" -> protected (true)
		// - "dashboard/file.html" -> tidak protected (false, karena ini file di dalam folder)
		// - "dashboard/subfolder" -> tidak protected (false, karena ini subfolder)
		
		$firstSegment = strtolower(reset($pathParts));
		
		// Jika hanya ada 1 segment dan itu adalah protected folder, maka protected
		if (count($pathParts) === 1 && in_array($firstSegment, array_map('strtolower', $protectedFolders))) {
			return true;
		}
		
		// Jika lebih dari 1 segment, berarti ini subfolder/file di dalam protected folder
		// Subfolder di dalam protected folder BOLEH dihapus
		return false;
	}
	
	private function isHtmlFile($filepath)
	{
		return str_ends_with(strtolower($filepath), '.html');
	}
	
	private function scanHtmlFiles($directory, $recursive = true)
	{
		$files = [];
		$iterator = $recursive ? 
			new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) :
			new \DirectoryIterator($directory);
		
		foreach ($iterator as $file) {
			if ($file->isFile() && $this->isHtmlFile($file->getPathname())) {
				$relativePath = str_replace($this->templateDir, '', $file->getPathname());
				$files[] = [
					'name' => $file->getFilename(),
					'path' => $relativePath,
					'full_path' => $file->getPathname(),
					'size' => $file->getSize(),
					'modified' => date('Y-m-d H:i:s', $file->getMTime())
				];
			}
		}
		
		return $files;
	}
	
	private function getDefaultHtmlContent()
	{
		return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New HTML File</title>
</head>
<body>
    <h1>Welcome to your new HTML file</h1>
    <p>This is a default HTML template created by NexaSystem.</p>
</body>
</html>';
	}
	
	/**
	 * Helper method untuk menghapus folder secara rekursif
	 * Recursive folder deletion helper
	 */
	private function deleteFolderRecursive($dir)
	{
		if (!is_dir($dir)) {
			return false;
		}
		
		$files = array_diff(scandir($dir), array('.', '..'));
		
		foreach ($files as $file) {
			$filePath = $dir . DIRECTORY_SEPARATOR . $file;
			
			if (is_dir($filePath)) {
				$this->deleteFolderRecursive($filePath);
			} else {
				unlink($filePath);
			}
		}
		
		return rmdir($dir);
	}
	
	/**
	 * Helper method untuk membangun struktur folder tree
	 * Build folder tree structure helper
	 */
	private function buildFolderTree($path, $currentDepth = 0, $maxDepth = 3)
	{
		if ($currentDepth > $maxDepth) {
			return [];
		}
		
		$tree = [];
		$items = scandir($path);
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$itemPath = $path . DIRECTORY_SEPARATOR . $item;
			$relativePath = str_replace($this->templateDir, '', $itemPath);
			
			if (is_dir($itemPath)) {
				$tree[] = [
					'type' => 'folder',
					'name' => $item,
					'path' => $relativePath,
					'full_path' => $itemPath,
					'depth' => $currentDepth,
					'children' => $this->buildFolderTree($itemPath, $currentDepth + 1, $maxDepth),
					'html_files_count' => count($this->scanHtmlFiles($itemPath, false)),
					'subfolders_count' => count($this->getSubFolders($itemPath))
				];
			} elseif ($this->isHtmlFile($itemPath)) {
				$tree[] = [
					'type' => 'file',
					'name' => $item,
					'path' => $relativePath,
					'full_path' => $itemPath,
					'depth' => $currentDepth,
					'size' => filesize($itemPath),
					'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
				];
			}
		}
		
		return $tree;
	}
	
	/**
	 * Helper method untuk mendapatkan daftar subfolder
	 * Get subfolders list helper
	 */
	private function getSubFolders($directory)
	{
		$subFolders = [];
		
		if (!is_dir($directory)) {
			return $subFolders;
		}
		
		$items = scandir($directory);
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$itemPath = $directory . DIRECTORY_SEPARATOR . $item;
			
			if (is_dir($itemPath)) {
				$subFolders[] = [
					'name' => $item,
					'path' => str_replace($this->templateDir, '', $itemPath),
					'full_path' => $itemPath,
					'created' => date('Y-m-d H:i:s', filectime($itemPath)),
					'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
				];
			}
		}
		
		return $subFolders;
	}
	
	/**
	 * Helper method untuk mendapatkan daftar subfolder secara rekursif
	 * Get subfolders list recursively helper
	 */
	private function getSubFoldersRecursive($directory)
	{
		$subFolders = [];
		
		if (!is_dir($directory)) {
			return $subFolders;
		}
		
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		
		foreach ($iterator as $file) {
			if ($file->isDir()) {
				$itemPath = $file->getPathname();
				$relativePath = str_replace($this->templateDir, '', $itemPath);
				
				// Skip if already added (to avoid duplicates)
				$exists = false;
				foreach ($subFolders as $existing) {
					if ($existing['full_path'] === $itemPath) {
						$exists = true;
						break;
					}
				}
				
				if (!$exists) {
					$subFolders[] = [
						'name' => $file->getFilename(),
						'path' => $relativePath,
						'full_path' => $itemPath,
						'created' => date('Y-m-d H:i:s', $file->getCTime()),
						'modified' => date('Y-m-d H:i:s', $file->getMTime()),
						'depth' => $iterator->getDepth()
					];
				}
			}
		}
		
		return $subFolders;
	}
	
	/**
	 * Helper method untuk membangun struktur menu tree
	 * Build menu tree structure helper
	 */
	private function buildMenuTree($currentPath, $basePath, $currentDepth = 0, $maxDepth = 10, $baseUrl = '')
	{
		if ($currentDepth > $maxDepth) {
			return [];
		}
		
		$menuItems = [];
		
		if (!is_dir($currentPath)) {
			return $menuItems;
		}
		
		$items = scandir($currentPath);
		
		// Sort items: folders first, then files
		$folders = [];
		$files = [];
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
			
			if (is_dir($itemPath)) {
				$folders[] = $item;
			} elseif ($this->isHtmlFile($itemPath)) {
				$files[] = $item;
			}
		}
		
		// Process folders first
		foreach ($folders as $folder) {
			// Skip system folders: Oauth and Blog
			$folderLower = strtolower($folder);
			if (in_array($folderLower, ['oauth', 'blog'])) {
				continue;
			}
			
			$folderPath = $currentPath . DIRECTORY_SEPARATOR . $folder;
			$relativePath = str_replace($basePath, '', $folderPath);
			$relativePath = trim($relativePath, '/\\');
			
			// Get children of this folder
			$children = $this->buildMenuTree($folderPath, $basePath, $currentDepth + 1, $maxDepth, $baseUrl);
			
			// Only add folder if it has children or files
			if (!empty($children)) {
				// Get folder path relative to basePath
				$basePathReal = realpath($basePath);
				$folderPathReal = realpath($folderPath);
				
				$folderRelativePath = '';
				if ($basePathReal && $folderPathReal) {
					$basePathNormalized = str_replace('\\', '/', $basePathReal);
					$folderPathNormalized = str_replace('\\', '/', $folderPathReal);
					
					if (strpos($folderPathNormalized, $basePathNormalized) === 0) {
						$folderRelativePath = substr($folderPathNormalized, strlen($basePathNormalized));
						$folderRelativePath = trim($folderRelativePath, '/\\');
						$folderRelativePath = str_replace('\\', '/', $folderRelativePath);
					}
				}
				
				$menuItem = [
					'label' => $this->formatLabel($folder),
					'folder' => $folder,
					'type' => 'folder'
				];
				
				// Add children if exists
				if (!empty($children)) {
					$menuItem['children'] = $children;
				}
				
				$menuItems[] = $menuItem;
			}
		}
		
		// Process files
		foreach ($files as $file) {
			// Skip system files: index, footer, header (case-insensitive)
			$fileLabel = str_replace('.html', '', $file);
			$fileLabelLower = strtolower($fileLabel);
			if (in_array($fileLabelLower, ['index', 'footer', 'header'])) {
				continue;
			}
			
			$filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
			
			// Get relative path from basePath (folder yang dipilih), bukan dari templateDir
			// Normalize paths untuk perbandingan yang benar - gunakan realpath untuk canonical path
			$basePathReal = realpath($basePath);
			$filePathReal = realpath($filePath);
			
			if ($basePathReal && $filePathReal) {
				// Normalize ke forward slash untuk konsistensi
				$basePathNormalized = str_replace('\\', '/', $basePathReal);
				$filePathNormalized = str_replace('\\', '/', $filePathReal);
				
				// Get relative path from basePath
				if (strpos($filePathNormalized, $basePathNormalized) === 0) {
					$relativePath = substr($filePathNormalized, strlen($basePathNormalized));
					$relativePath = trim($relativePath, '/\\');
				} else {
					// Fallback: gunakan path dari currentPath
					$relativePath = str_replace($basePathNormalized . '/', '', $filePathNormalized);
					$relativePath = trim($relativePath, '/\\');
				}
			} else {
				// Fallback jika realpath gagal: normalisasi manual
				$basePathNormalized = rtrim(str_replace('\\', '/', $basePath), '/');
				$filePathNormalized = str_replace('\\', '/', $filePath);
				
				if (strpos($filePathNormalized, $basePathNormalized) === 0) {
					$relativePath = substr($filePathNormalized, strlen($basePathNormalized));
					$relativePath = trim($relativePath, '/\\');
				} else {
					// Fallback: gunakan nama file saja
					$relativePath = str_replace('.html', '', $file);
				}
			}
			
			// Remove .html extension from path for href
			$hrefPath = str_replace('.html', '', $relativePath);
			$hrefPath = str_replace('\\', '/', $hrefPath);
			
			// Jika nama file adalah "index", hapus dari path
			// Contoh: "wisata/index" menjadi "wisata"
			// Note: fileLabel sudah diambil sebelumnya untuk filtering
			if (strtolower($fileLabel) === 'index') {
				// Hapus "/index" atau "index" dari akhir path
				$hrefPath = preg_replace('#/index$|^index$#i', '', $hrefPath);
				$hrefPath = rtrim($hrefPath, '/');
			}
			
			// Format href - tanpa leading slash jika tidak ada baseUrl
			$hrefOrigin = $baseUrl ? rtrim($baseUrl, '/') . '/' . $hrefPath : $hrefPath;
			// Href lowercase untuk konsistensi
			$href = strtolower($hrefOrigin);
			
			// Get label from filename (without extension)
			// (fileLabel sudah diambil sebelumnya untuk pengecekan index)
			$label = $this->formatLabel($fileLabel);
			
			// Get folder path relative to basePath (untuk field "folder")
			$folderRelativePath = '';
			if ($basePathReal && $filePathReal) {
				$currentPathReal = realpath($currentPath);
				if ($currentPathReal) {
					$basePathNormalized = str_replace('\\', '/', $basePathReal);
					$currentPathNormalized = str_replace('\\', '/', $currentPathReal);
					
					if (strpos($currentPathNormalized, $basePathNormalized) === 0) {
						$folderRelativePath = substr($currentPathNormalized, strlen($basePathNormalized));
						$folderRelativePath = trim($folderRelativePath, '/\\');
						$folderRelativePath = str_replace('\\', '/', $folderRelativePath);
					}
				}
			}
			
			$menuItems[] = [
				'label' => $label,
				'file' => $fileLabel,
				'folder' => $folderRelativePath,
				'href' => $href,
				'hrefOrigin' => $hrefOrigin,
				'type' => 'file'
			];
		}
		
		return $menuItems;
	}
	
	/**
	 * Helper method untuk membangun struktur menu tree dengan options advanced
	 * Build menu tree structure with advanced options helper
	 */
	private function buildMenuTreeAdvanced(
		$currentPath, 
		$basePath, 
		$currentDepth = 0, 
		$maxDepth = 10, 
		$baseUrl = '', 
		$includeHtmlExtension = false,
		$labelFormatter = null,
		$filterFunction = null
	)
	{
		if ($currentDepth > $maxDepth) {
			return [];
		}
		
		$menuItems = [];
		
		if (!is_dir($currentPath)) {
			return $menuItems;
		}
		
		$items = scandir($currentPath);
		
		// Sort items: folders first, then files
		$folders = [];
		$files = [];
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
			
			// Apply filter function if provided
			if ($filterFunction && is_callable($filterFunction)) {
				if (!$filterFunction($item, $itemPath, is_dir($itemPath))) {
					continue;
				}
			}
			
			if (is_dir($itemPath)) {
				$folders[] = $item;
			} elseif ($this->isHtmlFile($itemPath)) {
				$files[] = $item;
			}
		}
		
		// Process folders first
		foreach ($folders as $folder) {
			// Skip system folders: Oauth and Blog
			$folderLower = strtolower($folder);
			if (in_array($folderLower, ['oauth', 'blog'])) {
				continue;
			}
			
			$folderPath = $currentPath . DIRECTORY_SEPARATOR . $folder;
			$relativePath = str_replace($basePath, '', $folderPath);
			$relativePath = trim($relativePath, '/\\');
			
			// Get children of this folder
			$children = $this->buildMenuTreeAdvanced(
				$folderPath, 
				$basePath, 
				$currentDepth + 1, 
				$maxDepth, 
				$baseUrl, 
				$includeHtmlExtension,
				$labelFormatter,
				$filterFunction
			);
			
			// Only add folder if it has children or files
			if (!empty($children)) {
				// Get folder path relative to basePath
				$basePathReal = realpath($basePath);
				$folderPathReal = realpath($folderPath);
				
				$folderRelativePath = '';
				if ($basePathReal && $folderPathReal) {
					$basePathNormalized = str_replace('\\', '/', $basePathReal);
					$folderPathNormalized = str_replace('\\', '/', $folderPathReal);
					
					if (strpos($folderPathNormalized, $basePathNormalized) === 0) {
						$folderRelativePath = substr($folderPathNormalized, strlen($basePathNormalized));
						$folderRelativePath = trim($folderRelativePath, '/\\');
						$folderRelativePath = str_replace('\\', '/', $folderRelativePath);
					}
				}
				
				// Format label
				$label = $labelFormatter && is_callable($labelFormatter) 
					? $labelFormatter($folder, $folderPath, true) 
					: $this->formatLabel($folder);
				
				$menuItem = [
					'label' => $label,
					'folder' => $folder,
					'type' => 'folder'
				];
				
				// Add children if exists
				if (!empty($children)) {
					$menuItem['children'] = $children;
				}
				
				$menuItems[] = $menuItem;
			}
		}
		
		// Process files
		foreach ($files as $file) {
			// Skip system files: index, footer, header (case-insensitive)
			$fileLabel = str_replace('.html', '', $file);
			$fileLabelLower = strtolower($fileLabel);
			if (in_array($fileLabelLower, ['index', 'footer', 'header'])) {
				continue;
			}
			
			$filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
			
			// Get relative path from basePath (folder yang dipilih), bukan dari templateDir
			// Normalize paths untuk perbandingan yang benar - gunakan realpath untuk canonical path
			$basePathReal = realpath($basePath);
			$filePathReal = realpath($filePath);
			
			if ($basePathReal && $filePathReal) {
				// Normalize ke forward slash untuk konsistensi
				$basePathNormalized = str_replace('\\', '/', $basePathReal);
				$filePathNormalized = str_replace('\\', '/', $filePathReal);
				
				// Get relative path from basePath
				if (strpos($filePathNormalized, $basePathNormalized) === 0) {
					$relativePath = substr($filePathNormalized, strlen($basePathNormalized));
					$relativePath = trim($relativePath, '/\\');
				} else {
					// Fallback: gunakan path dari currentPath
					$relativePath = str_replace($basePathNormalized . '/', '', $filePathNormalized);
					$relativePath = trim($relativePath, '/\\');
				}
			} else {
				// Fallback jika realpath gagal: normalisasi manual
				$basePathNormalized = rtrim(str_replace('\\', '/', $basePath), '/');
				$filePathNormalized = str_replace('\\', '/', $filePath);
				
				if (strpos($filePathNormalized, $basePathNormalized) === 0) {
					$relativePath = substr($filePathNormalized, strlen($basePathNormalized));
					$relativePath = trim($relativePath, '/\\');
				} else {
					// Fallback: gunakan nama file saja
					$relativePath = str_replace('.html', '', $file);
				}
			}
			
			// Format href
			$hrefPath = $relativePath;
			if (!$includeHtmlExtension) {
				$hrefPath = str_replace('.html', '', $hrefPath);
			}
			$hrefPath = str_replace('\\', '/', $hrefPath);
			
			// Jika nama file adalah "index", hapus dari path
			// Contoh: "wisata/index" menjadi "wisata"
			// Note: fileLabel sudah diambil sebelumnya untuk filtering
			if (strtolower($fileLabel) === 'index') {
				// Hapus "/index" atau "index" dari akhir path
				$hrefPath = preg_replace('#/index$|^index$#i', '', $hrefPath);
				$hrefPath = rtrim($hrefPath, '/');
			}
			
			// Format href - tanpa leading slash jika tidak ada baseUrl
			$hrefOrigin = $baseUrl ? rtrim($baseUrl, '/') . '/' . $hrefPath : $hrefPath;
			// Href lowercase untuk konsistensi
			$href = strtolower($hrefOrigin);
			
			// Format label
			$label = $labelFormatter && is_callable($labelFormatter) 
				? $labelFormatter($fileLabel, $filePath, false) 
				: $this->formatLabel($fileLabel);
			
			// Get folder path relative to basePath (untuk field "folder")
			$folderRelativePath = '';
			if ($basePathReal && $filePathReal) {
				$currentPathReal = realpath($currentPath);
				if ($currentPathReal) {
					$basePathNormalized = str_replace('\\', '/', $basePathReal);
					$currentPathNormalized = str_replace('\\', '/', $currentPathReal);
					
					if (strpos($currentPathNormalized, $basePathNormalized) === 0) {
						$folderRelativePath = substr($currentPathNormalized, strlen($basePathNormalized));
						$folderRelativePath = trim($folderRelativePath, '/\\');
						$folderRelativePath = str_replace('\\', '/', $folderRelativePath);
					}
				}
			}
			
			$menuItems[] = [
				'label' => $label,
				'file' => $fileLabel,
				'folder' => $folderRelativePath,
				'href' => $href,
				'hrefOrigin' => $hrefOrigin,
				'type' => 'file'
			];
		}
		
		return $menuItems;
	}
	
	/**
	 * Helper method untuk format label (mengubah underscore/hyphen menjadi space, capitalize)
	 * Format label helper (convert underscore/hyphen to space, capitalize)
	 */
	private function formatLabel($name)
	{
		// Replace underscores and hyphens with spaces
		$label = str_replace(['_', '-'], ' ', $name);
		
		// Capitalize first letter of each word
		$label = ucwords(strtolower($label));
		
		return $label;
	}
	
	/**
	 * ========================================
	 * FITUR TAMBAHAN: PENCARIAN & FILTER
	 * ========================================
	 */
	
	/**
	 * Pencarian teks dalam file HTML
	 * Search text in HTML files
	 */
	public function searchInHtmlFiles($searchTerm, $folderPath = '', $caseSensitive = false)
	{
		$results = [];
		$searchPath = $this->templateDir;
		
		if (!empty($folderPath)) {
			$searchPath .= trim($folderPath, '/') . '/';
		}
		
		$htmlFiles = $this->scanHtmlFiles($searchPath, true);
		
		foreach ($htmlFiles as $file) {
			$content = file_get_contents($file['full_path']);
			
			if ($caseSensitive) {
				$found = strpos($content, $searchTerm) !== false;
			} else {
				$found = stripos($content, $searchTerm) !== false;
			}
			
			if ($found) {
				// Find line numbers where term appears
				$lines = explode("\n", $content);
				$matchingLines = [];
				
				foreach ($lines as $lineNum => $line) {
					if ($caseSensitive) {
						$lineFound = strpos($line, $searchTerm) !== false;
					} else {
						$lineFound = stripos($line, $searchTerm) !== false;
					}
					
					if ($lineFound) {
						$matchingLines[] = [
							'line_number' => $lineNum + 1,
							'content' => trim($line)
						];
					}
				}
				
				$results[] = [
					'file' => $file,
					'matches' => count($matchingLines),
					'matching_lines' => $matchingLines
				];
			}
		}
		
		return $results;
	}
	
	/**
	 * Cari file HTML berdasarkan tag tertentu
	 * Find HTML files containing specific tags
	 */
	public function findHtmlByTag($tagName, $folderPath = '')
	{
		$results = [];
		$searchPath = $this->templateDir;
		
		if (!empty($folderPath)) {
			$searchPath .= trim($folderPath, '/') . '/';
		}
		
		$htmlFiles = $this->scanHtmlFiles($searchPath, true);
		
		foreach ($htmlFiles as $file) {
			$content = file_get_contents($file['full_path']);
			
			// Search for opening and closing tags
			$pattern = '/<' . preg_quote($tagName, '/') . '[\s>]/i';
			
			if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
				$results[] = [
					'file' => $file,
					'tag_count' => count($matches[0]),
					'tag_positions' => $matches[0]
				];
			}
		}
		
		return $results;
	}
	
	/**
	 * ========================================
	 * FITUR TAMBAHAN: TEMPLATE MANAGEMENT
	 * ========================================
	 */
	
	/**
	 * Membuat template HTML
	 * Create HTML template
	 */
	public function createHtmlTemplate($templateName, $content)
	{
		$templateDir = $this->templateDir . '_templates/';
		
		if (!is_dir($templateDir)) {
			mkdir($templateDir, 0755, true);
		}
		
		$templatePath = $templateDir . $templateName . '.html';
		
		if (file_exists($templatePath)) {
			throw new \Exception("Template already exists: " . $templateName);
		}
		
		if (file_put_contents($templatePath, $content) !== false) {
			return "Template created successfully: " . $templateName;
		} else {
			throw new \Exception("Failed to create template: " . $templateName);
		}
	}
	
	/**
	 * Menggunakan template dengan variabel
	 * Use template with variables
	 */
	public function generateFromTemplate($templateName, $variables = [], $outputFile = '', $subfolder = '')
	{
		$templatePath = $this->templateDir . '_templates/' . $templateName . '.html';
		
		if (!file_exists($templatePath)) {
			throw new \Exception("Template not found: " . $templateName);
		}
		
		$content = file_get_contents($templatePath);
		
		// Replace variables in format {{variable_name}}
		foreach ($variables as $key => $value) {
			$content = str_replace('{{' . $key . '}}', $value, $content);
		}
		
		if (!empty($outputFile)) {
			return $this->addHtmlFile($outputFile, $content, $subfolder);
		}
		
		return $content;
	}
	
	/**
	 * Daftar template yang tersedia
	 * List available templates
	 */
	public function listTemplates()
	{
		$templateDir = $this->templateDir . '_templates/';
		$templates = [];
		
		if (!is_dir($templateDir)) {
			return $templates;
		}
		
		$files = scandir($templateDir);
		
		foreach ($files as $file) {
			if ($file !== '.' && $file !== '..' && str_ends_with($file, '.html')) {
				$templatePath = $templateDir . $file;
				$templates[] = [
					'name' => str_replace('.html', '', $file),
					'path' => $templatePath,
					'size' => filesize($templatePath),
					'modified' => date('Y-m-d H:i:s', filemtime($templatePath))
				];
			}
		}
		
		return $templates;
	}
	
	/**
	 * ========================================
	 * FITUR TAMBAHAN: BACKUP & RESTORE
	 * ========================================
	 */
	
	/**
	 * Backup folder ke file ZIP
	 * Backup folder to ZIP file
	 */
	public function backupFolder($folderPath = '', $backupName = '')
	{
		$backupDir = $this->templateDir . '_backups/';
		
		if (!is_dir($backupDir)) {
			mkdir($backupDir, 0755, true);
		}
		
		$sourcePath = $this->templateDir;
		if (!empty($folderPath)) {
			$sourcePath .= trim($folderPath, '/') . '/';
		}
		
		if (!is_dir($sourcePath)) {
			throw new \Exception("Source folder not found: " . $sourcePath);
		}
		
		$backupName = $backupName ?: 'backup_' . date('Y-m-d_H-i-s');
		$zipPath = $backupDir . $backupName . '.zip';
		
		$zip = new \ZipArchive();
		
		if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
			throw new \Exception("Cannot create backup file: " . $zipPath);
		}
		
		$this->addFolderToZip($zip, $sourcePath, $sourcePath);
		$zip->close();
		
		return "Backup created successfully: " . $zipPath;
	}
	
	/**
	 * Daftar backup yang tersedia
	 * List available backups
	 */
	public function listBackups()
	{
		$backupDir = $this->templateDir . '_backups/';
		$backups = [];
		
		if (!is_dir($backupDir)) {
			return $backups;
		}
		
		$files = scandir($backupDir);
		
		foreach ($files as $file) {
			if ($file !== '.' && $file !== '..' && str_ends_with($file, '.zip')) {
				$backupPath = $backupDir . $file;
				$backups[] = [
					'name' => str_replace('.zip', '', $file),
					'path' => $backupPath,
					'size' => $this->formatBytes(filesize($backupPath)),
					'created' => date('Y-m-d H:i:s', filemtime($backupPath))
				];
			}
		}
		
		return $backups;
	}
	
	/**
	 * ========================================
	 * FITUR TAMBAHAN: ANALISIS HTML
	 * ========================================
	 */
	
	/**
	 * Analisis konten HTML
	 * Analyze HTML content
	 */
	public function analyzeHtmlContent($filename, $subfolder = '')
	{
		$content = $this->getHtmlFileContent($filename, $subfolder);
		
		// Basic HTML analysis
		$analysis = [
			'file_size' => strlen($content),
			'line_count' => substr_count($content, "\n") + 1,
			'word_count' => str_word_count(strip_tags($content)),
			'character_count' => strlen(strip_tags($content)),
			'tags' => [],
			'links' => [],
			'images' => [],
			'meta_tags' => []
		];
		
		// Count HTML tags
		preg_match_all('/<(\w+)/', $content, $tagMatches);
		$analysis['tags'] = array_count_values($tagMatches[1]);
		
		// Find links
		preg_match_all('/<a\s+[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $content, $linkMatches);
		$analysis['links'] = array_unique($linkMatches[1]);
		
		// Find images
		preg_match_all('/<img\s+[^>]*src\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $content, $imgMatches);
		$analysis['images'] = array_unique($imgMatches[1]);
		
		// Find meta tags
		preg_match_all('/<meta\s+[^>]*>/i', $content, $metaMatches);
		$analysis['meta_tags'] = $metaMatches[0];
		
		return $analysis;
	}
	
	/**
	 * Statistik folder HTML
	 * Get HTML folder statistics
	 */
	public function getHtmlStatistics($folderPath = '')
	{
		$searchPath = $this->templateDir;
		
		if (!empty($folderPath)) {
			$searchPath .= trim($folderPath, '/') . '/';
		}
		
		$htmlFiles = $this->scanHtmlFiles($searchPath, true);
		
		$stats = [
			'total_files' => count($htmlFiles),
			'total_size' => 0,
			'largest_file' => null,
			'smallest_file' => null,
			'average_size' => 0,
			'newest_file' => null,
			'oldest_file' => null,
			'file_extensions' => []
		];
		
		if (empty($htmlFiles)) {
			return $stats;
		}
		
		$sizes = [];
		$dates = [];
		
		foreach ($htmlFiles as $file) {
			$stats['total_size'] += $file['size'];
			$sizes[] = $file['size'];
			$dates[] = strtotime($file['modified']);
			
			// Track largest and smallest files
			if ($stats['largest_file'] === null || $file['size'] > $stats['largest_file']['size']) {
				$stats['largest_file'] = $file;
			}
			
			if ($stats['smallest_file'] === null || $file['size'] < $stats['smallest_file']['size']) {
				$stats['smallest_file'] = $file;
			}
		}
		
		$stats['average_size'] = $stats['total_size'] / count($htmlFiles);
		$stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
		$stats['average_size_formatted'] = $this->formatBytes($stats['average_size']);
		
		// Find newest and oldest files
		$maxDate = max($dates);
		$minDate = min($dates);
		
		foreach ($htmlFiles as $file) {
			if (strtotime($file['modified']) === $maxDate) {
				$stats['newest_file'] = $file;
			}
			if (strtotime($file['modified']) === $minDate) {
				$stats['oldest_file'] = $file;
			}
		}
		
		return $stats;
	}
	
	/**
	 * ========================================
	 * HELPER METHODS UNTUK FITUR TAMBAHAN
	 * ========================================
	 */
	
	/**
	 * Format bytes ke ukuran yang readable
	 * Format bytes to readable size
	 */
	private function formatBytes($bytes, $precision = 2)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		
		for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
			$bytes /= 1024;
		}
		
		return round($bytes, $precision) . ' ' . $units[$i];
	}
	
	/**
	 * Menambahkan folder ke ZIP secara rekursif
	 * Add folder to ZIP recursively
	 */
	private function addFolderToZip($zip, $folder, $basePath)
	{
		$files = scandir($folder);
		
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			
			$filePath = $folder . DIRECTORY_SEPARATOR . $file;
			$relativePath = str_replace($basePath, '', $filePath);
			
			if (is_dir($filePath)) {
				$zip->addEmptyDir($relativePath);
				$this->addFolderToZip($zip, $filePath, $basePath);
			} else {
				$zip->addFile($filePath, $relativePath);
			}
		}
	}
}