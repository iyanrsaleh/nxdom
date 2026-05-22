<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * DirectoryController - Directory management
 */
class DirectoryController extends NexaController
{

      




   public function index(array $params = []): void
    {
       $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
       
       // Get menu tree structure dari folder theme
       // Parameter: folderPath, menuKey, maxDepth, baseUrl
    
       $this->setJsController($jsData);   
    }


     public function syAddMenu(array $params = []){
        $nexaSystem = $this->getNexaSystem();
        $files = $nexaSystem->getMenuTreeStructure('theme', 'main_menu', 10, '');
        return $files;   
     }



     public function syAdd(array $params = []){
        try {
            // Decode contentHtml jika sudah di-encode dengan base64
            if (isset($params['contentHtml']) && !empty($params['contentHtml'])) {
                // Check jika content sudah di-encode
                if (isset($params['contentHtmlEncoded']) && $params['contentHtmlEncoded'] === true) {
                    // Decode dari base64
                    $decodedContent = base64_decode($params['contentHtml'], true);
                    
                    if ($decodedContent !== false) {
                        // Verify decode berhasil dengan re-encode
                        if (base64_encode($decodedContent) === $params['contentHtml']) {
                            $params['contentHtml'] = $decodedContent;
                            $params['contentHtmlDecoded'] = true;
                            
                            // Log untuk debugging (optional)
                            error_log("✅ ContentHtml decoded successfully. Original length: " . 
                                     ($params['contentLength'] ?? 'unknown') . 
                                     ", Decoded length: " . strlen($decodedContent));
                        } else {
                            // Decode gagal, gunakan original
                            error_log("⚠️ ContentHtml decode verification failed, using original");
                        }
                    } else {
                        // Decode gagal
                        error_log("❌ ContentHtml base64 decode failed");
                        // Return error array (eventsControllers akan wrap dengan json)
                        return [
                            'success' => false,
                            'message' => 'Failed to decode contentHtml',
                            'error' => 'DECODE_ERROR'
                        ];
                    }
                }
            }
            
            // Proses data di sini
            $filePath = $params['filePath'] ?? null;
            $contentHtml = $params['contentHtml'] ?? null; // HTML yang sudah di-decode
            
            // Simpan ke file menggunakan NexaSystem jika filePath dan contentHtml tersedia
            $saveResult = null;
            if ($filePath && $contentHtml) {
                try {
                    $nexaSystem = $this->getNexaSystem();
                    
                    // Cek apakah file sudah ada
                    $fileExists = $nexaSystem->htmlFileExistsWithPath($filePath);
                    
                    if ($fileExists['exists']) {
                        // File sudah ada, update
                        $saveResult = $nexaSystem->editHtmlFileWithPath($filePath, $contentHtml);
                        $saveResult['action'] = 'updated';
                    } else {
                        // File belum ada, buat baru
                        $saveResult = $nexaSystem->addHtmlFileWithPath($filePath, $contentHtml);
                        $saveResult['action'] = 'created';
                    }
                    
                    // Log untuk debugging
                    // error_log("✅ File " . $saveResult['action'] . ": " . ($saveResult['path'] ?? $filePath));
                    
                } catch (\Exception $e) {
                    error_log("❌ Error saving file: " . $e->getMessage());
                    // Return error tapi tetap lanjutkan (jika ada fallback)
                    return [
                        'success' => false,
                        'message' => 'Error saving file: ' . $e->getMessage(),
                        'error' => 'FILE_SAVE_ERROR',
                        'filePath' => $filePath
                    ];
                }
            }
            
            // Return data array (eventsControllers akan wrap dengan json)
            return [
                'filePath' => $filePath,
                'contentLength' => isset($contentHtml) ? strlen($contentHtml) : 0,
                'contentHtmlDecoded' => $params['contentHtmlDecoded'] ?? false,
                'message' => 'Content saved successfully',
                'saveResult' => $saveResult // Include save result jika ada
            ];
            
        } catch (\Exception $e) {
            error_log("syAdd Error: " . $e->getMessage());
            // Return error array (eventsControllers akan wrap dengan json)
            return [
                'success' => false,
                'message' => 'Error processing request: ' . $e->getMessage(),
                'error' => 'PROCESSING_ERROR'
            ];
        }
     }



  public function sydelFile(array $params = []){
        $nexaSystem = $this->getNexaSystem();
        
        try {
            // Validasi parameter type
            if (!isset($params['type'])) {
                return [
                    'success' => false,
                    'message' => 'Type parameter is required (File or Folder)',
                    'error' => 'MISSING_TYPE'
                ];
            }
            
            if ($params['type'] == "File") {
                // Validasi parameter untuk delete file
                if (!isset($params['hrefOrigin']) || empty($params['hrefOrigin'])) {
                    return [
                        'success' => false,
                        'message' => 'hrefOrigin parameter is required for file deletion',
                        'error' => 'MISSING_PARAMETER'
                    ];
                }
                
                $filePath = 'theme/' . $params['hrefOrigin'];
                $result = $nexaSystem->deleteHtmlFileWithPath($filePath);
                
                return [
                    'success' => true,
                    'message' => 'File deleted successfully',
                    'result' => $result
                ];
                
            } else if ($params['type'] == "Folder") {
                // Validasi parameter untuk delete folder
                if (!isset($params['folder']) || empty($params['folder'])) {
                    return [
                        'success' => false,
                        'message' => 'Folder parameter is required for folder deletion',
                        'error' => 'MISSING_PARAMETER'
                    ];
                }
                
                // Ambil path folder dari berbagai sumber (prioritas: submenu > itemData > params)
                $folderName = '';
                
                // Cek submenu untuk mendapatkan path folder yang benar
                // Contoh: submenu[0].folder = "Components/bagus" -> path lengkap
                if (isset($params['itemData']['submenu']) && is_array($params['itemData']['submenu']) && !empty($params['itemData']['submenu'])) {
                    $firstSubmenu = $params['itemData']['submenu'][0];
                    if (isset($firstSubmenu['folder']) && !empty($firstSubmenu['folder'])) {
                        // Ambil path folder dari submenu (contoh: "Components/bagus")
                        $folderName = trim($firstSubmenu['folder']);
                    }
                }
                
                // Jika tidak ada di submenu, coba itemData.folder
                if (empty($folderName) && isset($params['itemData']['folder']) && !empty($params['itemData']['folder'])) {
                    $folderName = trim($params['itemData']['folder']);
                }
                
                // Fallback ke params.folder
                if (empty($folderName)) {
                    $folderName = trim($params['folder']);
                }
                
                $protectedFolders = ['theme', 'dashboard', 'tablet', 'mobile'];
                
                // Normalisasi path: hapus 'theme/' di awal jika sudah ada
                $folderName = preg_replace('#^theme[/\\\\]#i', '', $folderName);
                $folderName = trim($folderName, '/\\');
                
                // Jika folder yang akan dihapus adalah folder protected itu sendiri, tolak
                if (in_array(strtolower($folderName), array_map('strtolower', $protectedFolders))) {
                    return [
                        'success' => false,
                        'message' => 'Cannot delete protected root folder: ' . $folderName,
                        'error' => 'PROTECTED_FOLDER',
                        'params' => $params,
                        'protected_folders' => $protectedFolders
                    ];
                }
                
                // Validasi: Pastikan folderName tidak kosong setelah normalisasi
                if (empty($folderName)) {
                    return [
                        'success' => false,
                        'message' => 'Cannot delete root theme folder. Only subfolders inside theme can be deleted.',
                        'error' => 'INVALID_FOLDER'
                    ];
                }
                
                // Hanya subfolder di dalam theme yang bisa dihapus
                // Contoh: $folderName = 'subfolder' -> $folderPath = 'theme/subfolder'
                // Contoh: $folderName = 'Components/bagus' -> $folderPath = 'theme/Components/bagus'
                // Yang dihapus hanya folder tersebut, bukan folder 'theme' itu sendiri
                $folderPath = 'theme/' . $folderName;
                
                // Cek apakah folder ada sebelum menghapus
                $folderExists = $nexaSystem->folderExists($folderPath);
                
                // Jika tidak ditemukan, coba cari di subfolder yang ada
                if (!$folderExists) {
                    // Ambil nama folder terakhir (misal: "Components/bagus" -> "bagus")
                    $folderParts = explode('/', str_replace('\\', '/', $folderName));
                    $lastFolderName = end($folderParts);
                    
                    // Dapatkan daftar subfolder di theme
                    try {
                        $themeInfo = $nexaSystem->getFolderInfoWithPath('theme');
                        $themeSubfolders = array_column($themeInfo['subfolders'] ?? [], 'name');
                        
                        // Cari folder di dalam setiap subfolder theme
                        foreach ($themeSubfolders as $subfolder) {
                            $potentialPath = 'theme/' . $subfolder . '/' . $lastFolderName;
                            if ($nexaSystem->folderExists($potentialPath)) {
                                $folderPath = $potentialPath;
                                $folderExists = true;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore error, continue with original path
                    }
                }
                
                if (!$folderExists) {
                    // Coba beberapa alternatif path untuk membantu debugging
                    $alternativePaths = [];
                    $alternatives = [
                        'theme/' . $folderName,
                        'theme/' . str_replace('\\', '/', $folderName),
                        'theme/' . str_replace('/', '\\', $folderName),
                        $params['folder'] ?? '',
                        $params['itemData']['folder'] ?? ''
                    ];
                    
                    foreach ($alternatives as $altPath) {
                        if (!empty($altPath) && $altPath !== $folderPath) {
                            $altExists = $nexaSystem->folderExists($altPath);
                            if ($altExists) {
                                $alternativePaths[] = [
                                    'path' => $altPath,
                                    'exists' => true
                                ];
                            }
                        }
                    }
                    
                    // Dapatkan informasi folder di theme untuk debugging
                    $themeInfo = null;
                    try {
                        $themeInfo = $nexaSystem->getFolderInfoWithPath('theme');
                    } catch (\Exception $e) {
                        // Ignore error
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Folder not found: ' . $folderPath,
                        'error' => 'FOLDER_NOT_FOUND',
                        'params' => $params,
                        'folder_path' => $folderPath,
                        'folder_name' => $folderName,
                        'searched_path' => $folderPath,
                        'alternative_paths_checked' => $alternativePaths,
                        'theme_folder_info' => $themeInfo ? [
                            'subfolders' => array_column($themeInfo['subfolders'] ?? [], 'name'),
                            'subfolders_count' => $themeInfo['subfolders_count'] ?? 0
                        ] : null,
                        'debug_info' => [
                            'original_params_folder' => $params['folder'] ?? null,
                            'itemData_folder' => $params['itemData']['folder'] ?? null,
                            'normalized_folder_name' => $folderName
                        ]
                    ];
                }
                
                // Hapus folder
                $result = $nexaSystem->deleteFolderWithPath($folderPath, true);
                
                return [
                    'success' => true,
                    'message' => 'Folder deleted successfully',
                    'result' => $result
                ];
                
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid type parameter. Must be "File" or "Folder"',
                    'error' => 'INVALID_TYPE'
                ];
            }
            
        } catch (\Exception $e) {
            error_log("sydelFile Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error deleting: ' . $e->getMessage(),
                'error' => 'DELETE_ERROR',
                'error_details' => [
                    'type' => $params['type'] ?? null,
                    'folder' => $params['folder'] ?? null,
                    'hrefOrigin' => $params['hrefOrigin'] ?? null,
                    'exception' => get_class($e)
                ]
            ];
        }
     }



  public function syAddFile(array $params = []){
        $nexaSystem = $this->getNexaSystem();
        $filePath = 'theme/'.$params['hrefOrigin'];
        $content = '<h1>Selamat Datanga </h1>';
        try {
            // Cek apakah file sudah ada
            $fileExists = $nexaSystem->htmlFileExistsWithPath($filePath);
            
            if ($fileExists['exists']) {
               return [
                "status"=>'file sudah ada'
               ];
            } else {
                // File belum ada, langsung buat baru
                $result = $nexaSystem->addHtmlFileWithPath($filePath, $content);
               return [
                "status"=>'sukses membuat file'
               ];
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }

     }
// $result = $nexa->deleteFolderWithPath('pages', true);
  public function office(array $params = []){
        $this->setGlobalSlug(3,1);
        // office/file/pdf
        // $data = $this->inputs();
        // return $this->json($data);
     }

     public function Fetch(){
         return $this->NexaRender();
     }

   
     public function FetchEvents(array $params = []){
           $this->eventsAccess($params);
     }

     public function FetchControllers(){
        return $this->eventsControllers();
     }


    public function FetchModels(){
         $this->eventsModel();
    }


} 
