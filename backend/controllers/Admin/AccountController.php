<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * Account Controller - Manage user account settings
 * 
 * Handles user profile management including:
 * - Profile display with avatar, name, and email
 * - Photo/avatar upload and management
 */
class AccountController extends NexaController
{
    private const AJAX_METHODS = ['updateProfile', 'updatePassword', 'updateAvatar'];

    /**
     * Get template path for this controller. AdminController uses this instead of hardcoded logic.
     *
     * @param string $method Requested method from URL
     * @param string $requestMethod HTTP method (GET/POST)
     * @return string|null Template path, or null for AJAX endpoints (no render)
     */
    public static function getTemplatePath(string $method, string $requestMethod): ?string
    {
        // AJAX methods: jangan render, langsung return JSON
        if (in_array($method, self::AJAX_METHODS)) {
            return null;
        }
        if ($requestMethod === 'POST') {
            return 'account/index';
        }
        if (!empty($method) && $method !== 'index') {
            return 'account/' . $method;
        }
        return 'account/index';
    }

    /**
     * Route tracking for navigation and redirects
     * 
     * @param array $params Request parameters
     * @return array Route information
     */
    private function track($params)
    {
        return $this->routerAction($params, [
            'photo' => 'setPhoto',
            'telepon' => 'setTelepon'
        ]);
    }

    /**
     * Main account profile page
     * Display user avatar, name, and email
     * 
     * @param array $params Request parameters containing uid
     * @return void
     */
    public function index(array $params = []): void
    {
        $activeUsers = $this->useModels('User', 'byId', [$params['uid']]);
        
        // Get any form state (success/error messages) from previous operations
        $templateVars = $this->getState('form_add', $this->track($params));
        
        // Set error fields
        $this->setValue(['telepon'], 'errors');
        
        // Prepare telepon value - hanya set jika ada nilai yang valid dari DATABASE
        $teleponValue = null;
        if (isset($activeUsers['telepon']) && !empty($activeUsers['telepon']) && $activeUsers['telepon'] !== '') {
            $teleponValue = $activeUsers['telepon'];
        }
        
        // ⚠️ IMPORTANT: Hapus 'telepon' dari templateVars untuk menghindari override
        // Prioritas: Database > Session State
        if (isset($templateVars['telepon'])) {
            unset($templateVars['telepon']);
        }
        
        // Get user role for display


        $this->assignVars([
            'username' => $this->session->getUserSlug(),
            'user_real_name' => $activeUsers['nama'] ?? $this->session->getUserRealName(),
            'user_email' => $this->session->getUserEmail(),
            'user_avatar' => $activeUsers['avatar'],
            'avatar' => $activeUsers['avatar'], // For photo upload form
            'nik' => $activeUsers['nik'] ?? '',
            'telepon' => $teleponValue,  // ✅ Hanya dari database, bukan dari session
          
            'page_title' => 'Pengaturan Akun - SIAP KULIAH',
            'page_index' => $params['page_index'] ?? '',
            ...$templateVars
        ]);
        
        // Clear form state after displaying to prevent stale data
        $this->clearState('form_add');
    }

    /**
     * Update profile (nama, nik, telepon) - AJAX
     */
    public function updateProfile(array $params = []): void
    {
        header('Content-Type: application/json');
        
        try {
            // Validate request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }
            
            $userId = $this->session->getUserId();
            $nama = $_POST['nama'] ?? '';
            $nik = $_POST['nik'] ?? '';
            $telepon = $_POST['telepon'] ?? '';
            
            // Validate nama
            if (empty($nama) || strlen($nama) < 3) {
                throw new \Exception('Nama lengkap minimal 3 karakter');
            }
            
            // Validate NIK
            if (empty($nik)) {
                throw new \Exception('NIK wajib diisi');
            }
            
            if (!preg_match('/^[0-9]{16}$/', $nik)) {
                throw new \Exception('NIK harus 16 digit angka');
            }
            
            // Validate telepon
            if (empty($telepon)) {
                throw new \Exception('Nomor telepon wajib diisi');
            }
            
            if (!preg_match('/^[0-9]{10,15}$/', $telepon)) {
                throw new \Exception('Format nomor telepon tidak valid (10-15 digit)');
            }
            
            // Check if NIK already exists for other user
            $existingNik = $this->Storage('user')
                ->select(['id'])
                ->where('nik', $nik)
                ->where('id', '!=', $userId)
                ->first();
            
            if ($existingNik) {
                throw new \Exception('NIK sudah terdaftar oleh user lain');
            }
            
            // Update database
            $updateData = [
                'nama' => $nama,
                'nik' => $nik,
                'telepon' => $telepon
            ];
            
            $result = $this->Storage('user')
                ->where('id', $userId)
                ->update($updateData);
            
            // Verify update
            $updatedUser = $this->Storage('user')
                ->select(['nama', 'nik', 'telepon'])
                ->where('id', $userId)
                ->first();
            
            if (!$result) {
                throw new \Exception('Gagal menyimpan perubahan');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Profil berhasil diperbarui',
                'data' => $updatedUser
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        exit;
    }

    /**
     * Update avatar (AJAX)
     */
    public function updateAvatar(array $params = []): void
    {
        header('Content-Type: application/json');
        
        try {
            // Validate request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }
            
            $userId = $this->session->getUserId();
            
            // Validate file upload
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('File upload failed');
            }
            
            $file = $_FILES['avatar'];
            
            // Validate file size (2MB max)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new \Exception('File terlalu besar. Maksimal 2MB');
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception('Format file tidak valid. Hanya JPG dan PNG yang diperbolehkan');
            }
            
            // Create upload directory based on year and month
            // Format: assets/drive/avatar/YYYY/MM/
            $year = date('Y');
            $month = date('m');
            $uploadDir = "assets/drive/avatar/{$year}/{$month}/";
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new \Exception('Gagal menyimpan file');
            }
            
            // Get old avatar for cleanup
            $oldUser = $this->Storage('user')
                ->select(['avatar'])
                ->where('id', $userId)
                ->first();
            
            // Update database
            $result = $this->Storage('user')
                ->where('id', $userId)
                ->update([
                    'avatar' => '/' . $uploadPath
                ]);
            
            if (!$result) {
                // Delete uploaded file if database update fails
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                throw new \Exception('Gagal menyimpan perubahan');
            }
            
            // Delete old avatar file (jangan hapus default pria.png / wanita.png)
            $oldPath = ltrim($oldUser['avatar'] ?? '', '/');
            $isDefault = (strpos($oldPath, 'avatar/pria.png') !== false || strpos($oldPath, 'avatar/wanita.png') !== false);
            if (!empty($oldUser['avatar']) && !$isDefault && file_exists($oldPath)) {
                unlink($oldPath);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Foto profil berhasil diperbarui',
                'avatar_url' => '/' . $uploadPath
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        exit;
    }

    /**
     * Update password (AJAX)
     */
    public function updatePassword(array $params = []): void
    {
        header('Content-Type: application/json');
        
        try {
            // Validate request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }
            
            // Sama seperti updateProfile: baca langsung dari $_POST
            $userId = $this->session->getUserId();
            if (!$userId) {
                throw new \Exception('Sesi tidak valid. Silakan login kembali.');
            }
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            
            // Validasi: minimal password baru & konfirmasi harus diisi
            if (empty($newPassword) || empty($confirmPassword)) {
                throw new \Exception('Password baru dan konfirmasi wajib diisi');
            }
            
            if (strlen($newPassword) < 6) {
                throw new \Exception('Password baru minimal 6 karakter');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new \Exception('Password baru dan konfirmasi tidak cocok');
            }
            
            // Get current user data
            $userData = $this->Storage('user')
                ->select(['password'])
                ->where('id', $userId)
                ->first();
            
            if (!$userData) {
                throw new \Exception('User tidak ditemukan');
            }
            
            // Jika current_password diisi, verifikasi. Kosong = user Google (set password pertama kali).
            if (!empty($currentPassword)) {
                if ($currentPassword !== ($userData['password'] ?? '')) {
                    throw new \Exception('Password lama tidak sesuai. Jika Anda mendaftar via Google, kosongkan field Password Lama.');
                }
            }
            
            // Update database (simpan plain text sesuai OauthController)
            $result = $this->Storage('user')
                ->where('id', $userId)
                ->update([
                    'password' => $newPassword
                ]);
            
            if (!$result) {
                throw new \Exception('Gagal mengubah password');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Password berhasil diubah'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        exit;
    }













    /**
     * Handle photo/avatar upload
     * 
     * @param array $params Request parameters containing uid
     * @return void
     */
    public function photo(array $params = []): void
    {
        $page = $this->track($params);
        
        // Get current user data to access existing avatar
        $userData = $this->useModels('User', 'byId', [$params['uid']]);
        
        // Determine if we should delete the old avatar file
        // Don't delete default avatars (pria.png, wanita.png)
        $deleFile = false;
        if (isset($userData['avatar']) && 
            $userData['avatar'] !== 'avatar/pria.png' && 
            $userData['avatar'] !== 'avatar/wanita.png' &&
            $userData['avatar'] !== 'images/pria.png' && 
            $userData['avatar'] !== 'images/wanita.png') {
            $deleFile = $userData['avatar'];
        }
        
        $form = $this->createForm()
            ->fields([
                'photo' => 'FileOptional|null|Upload file jika diperlukan'
            ])
            ->setUpload([
                'maxSize'           => '5MB',
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'allowedTypes'      => ['image/jpeg', 'image/png', 'image/webp'],
                'saveOriginal'      => '150x150',
                'baseUploadDir'     => dirname(__DIR__, 2) . '/assets/drive/avatar/'
            ])
            ->setSuccess('Berhasil memperbaharui Photo')
            ->deleteFile($deleFile)  // Delete old avatar if exists
            ->setError('Mohon perbaiki kesalahan berikut');

        $result = $form->process();

        // Update photo data in database if upload successful
        if ($result['success'] && isset($result['files']['photo'])) {
            $updateResult = $this->useModels('User', 'updatePhoto', [$result['files']['photo'], $params['uid']]);
            
            if ($updateResult['success'] && isset($updateResult['avatar_path'])) {
                // Update direct session key
                $this->session->set('avatar', $updateResult['avatar_path']);
                
                // Also update user data in session if exists
                $userData = $this->session->getUser();
                if ($userData) {
                    $userData['avatar'] = $updateResult['avatar_path'];
                    $this->session->setUser($userData);
                }
            }
        }

        // Clear field values if form was successful
        $clearValues = $result['success'] ?? false;
        $templateVars = $form->Response($this->track(array_merge($params, $result)), $clearValues);

        // Save template variables to session state
        $this->setState('form_add', array_merge($templateVars, $result));

        // Redirect to index page
        $this->redirect($params['page_index']);
    }

    /**
     * Handle phone number update (ONE-TIME ONLY)
     * Nomor telepon hanya bisa diinput sekali untuk mencegah manipulasi
     * 
     * @param array $params Request parameters containing uid
     * @return void
     */
    public function telepon(array $params = []): void
    {
        $page = $this->track($params);
        
        // Get current user data to check if phone already exists
        $userData = $this->useModels('User', 'byId', [$params['uid']]);
        
        // ⚠️ SECURITY: Cek apakah nomor telepon sudah ada (sudah diinput sebelumnya)
        if (!empty($userData['telepon'])) {
            // Nomor sudah ada, tidak bisa diubah lagi
            $templateVars = [
                'info_message' => '⚠️ Nomor telepon sudah terdaftar dan tidak dapat diubah. Hubungi admin jika ada kesalahan.',
                'action' => $this->track($params)
            ];
            
            $this->setState('form_add', $templateVars);
            $this->redirect($params['page_index']);
            return;
        }
        
        // Nomor belum ada, boleh input
        $form = $this->createForm()
            ->fields([
                'telepon' => 'Phone|null'  // Format sesuai referensi AccountControllerDev.php
            ])
            ->setSuccess('✅ Nomor telepon berhasil disimpan. Nomor ini tidak dapat diubah lagi.')
            ->setError('Mohon perbaiki kesalahan berikut');

        $result = $form->process();

        // Update phone number in database if validation successful
        if ($this->isPost() && $result['success']) {
            // Double check: Pastikan nomor belum ada sebelum update
            $latestUserData = $this->useModels('User', 'byId', [$params['uid']]);
            
            if (!empty($latestUserData['telepon'])) {
                $templateVars = [
                    'info_message' => '⚠️ Nomor telepon sudah terdaftar sebelumnya.',
                    'action' => $this->track($params)
                ];
                $this->setState('form_add', $templateVars);
                $this->redirect($params['page_index']);
                return;
            }
            
            $updateResult = $this->useModels('User', 'updateTelepon', [$result['data']['telepon'], $params['uid']]);
            
            // Update session if needed
            if ($updateResult && isset($updateResult['success']) && $updateResult['success']) {
                $userData = $this->session->getUser();
                if ($userData) {
                    $userData['telepon'] = $result['data']['telepon'];
                    $this->session->setUser($userData);
                }
            }
        }

        // Clear field values if form was successful
        $clearValues = $result['success'] ?? false;
        $templateVars = $form->Response($this->track(array_merge($params, $result)), $clearValues);

        // Save template variables to session state
        $this->setState('form_add', array_merge($templateVars, $result));

        // Redirect to index page
        $this->redirect($params['page_index']);
    }





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
