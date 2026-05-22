<?php
namespace App\Controllers\Api;

use App\System\NexaController;

/**
 * Google Authentication Controller
 * Handles Google Sign-In for signup and signin
 */
class GoogleAuthController extends NexaController {
    
    private $googleClientId = '439618760894-3230nsh6lbsvo9sier0kdfnunt5743id.apps.googleusercontent.com';
    

    /**
     * Handle direct registration (nama + email + password + optional avatar)
     * Dipakai Electron: setelah Google OAuth, user buat password sendiri.
     */
    public function register() {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        if (ob_get_level()) ob_clean();

        if (!$this->isPost()) {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data  = json_decode($input, true);

        $nama     = trim($data['nama']     ?? '');
        $email    = trim($data['email']    ?? '');
        $password = trim($data['password'] ?? '');

        if (!$nama || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Nama, email, dan password wajib diisi']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
            exit;
        }

        $userModel = $this->refModels('Oauth');

        // Cek email sudah terdaftar
        $existing = $userModel->Storage('user')
            ->select(['*'])
            ->where('email', $email)
            ->first();

        $googlePicture = $data['avatar'] ?? null;

        // Jika ada Google credential, verifikasi token dan ambil foto dari JWT
        // (sama pola dengan signup() — prioritas picture dari token di atas $data['avatar'])
        if (!empty($data['credential'])) {
            $googleUser = $this->verifyGoogleToken($data['credential']);
            if ($googleUser && !empty($googleUser['picture'])) {
                $googlePicture = $googleUser['picture'];
            }
        }

        if ($existing) {
            // Email sudah terdaftar — jika dari Google OAuth, langsung auto-login
            // Sinkronisasi foto Google ke drive lokal (seperti signup())
            if ($googlePicture) {
                $this->syncGoogleAvatarToLocal($userModel, $existing, $googlePicture);
            }

            $userEntity = $this->slugify($existing['nama']);
            $avatarUrl  = $existing['avatar'] ?? 'images/pria.png';
            if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                $avatarUrl = $this->url($avatarUrl);
            }

            $this->setUser([
                'user_id'        => $existing['id'],
                'userid'         => $existing['userid'] ?? $existing['id'],
                'user_name'      => $userEntity,
                'user_real_name' => $existing['nama'],
                'email'          => $existing['email'],
                'avatar'         => $avatarUrl,
                'status'         => $existing['status'] ?? 'user',
                'role'           => $existing['role']   ?? 'user',
                'login_time'     => time(),
                'last_activity'  => time(),
            ]);

            // Ambil token lisensi aktif milik user
            $existingLicense = $userModel->Storage('licenses')
                ->select(['license_key'])
                ->where('userid', (string) $existing['id'])
                ->where('status', 'active')
                ->orderBy('id', 'DESC')
                ->first();
            $existingToken = $existingLicense['license_key'] ?? ($existing['token'] ?? null);

            echo json_encode([
                'success'  => true,
                'existed'  => true,
                'message'  => 'Login berhasil!',
                'user'     => [
                    'id'     => $existing['id'],
                    'nama'   => $existing['nama'],
                    'email'  => $existing['email'],
                    'avatar' => $avatarUrl,
                    'status' => $existing['status'],
                    'role'   => $existing['role'],
                    'token'  => $existingToken,
                ],
            ]);
            exit;
        }

        // Nama dari Google bisa mengandung tanda kutip — bersihkan
        $cleanName = $this->cleanGoogleName($nama);

        $gender        = $data['gender'] ?? 'male';
        $defaultAvatar = ($gender === 'female') ? '/drive/avatar/wanita.png' : '/drive/avatar/pria.png';

        $insertData = [
            'nama'     => $cleanName,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status'   => 'user',
            'role'     => 'user',
            'row'      => '1',
            'gender'   => $gender,
            'avatar'   => $googlePicture ?? $defaultAvatar,
        ];

        try {
            $result = $userModel->Storage('user')->insert($insertData);

            if (!$result) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data']);
                exit;
            }

            // Ambil data user yang baru dibuat
            $newUser = $userModel->Storage('user')
                ->select(['*'])
                ->where('email', $email)
                ->first();

            if ($newUser) {
                // Generate 7-day trial license key — sama dengan signup()
                $trialKey    = $this->licenseGenerate((int) $newUser['id']);
                $trialExpiry = time() + (7 * 24 * 3600);
                $userModel->Storage('licenses')->insert([
                    'userid'      => (string) $newUser['id'],
                    'license_key' => $trialKey,
                    'trial'       => '7',
                    'status'      => 'active',
                    'max_devices' => 1,
                    'expired_at'  => $trialExpiry,
                ]);
                $userModel->Storage('user')
                    ->where('id', $newUser['id'])
                    ->update(['token' => $trialKey]);

                // Unduh foto Google ke drive lokal (seperti signup())
                if ($googlePicture) {
                    $this->syncGoogleAvatarToLocal($userModel, $newUser, $googlePicture);
                }

                $userEntity = $this->slugify($newUser['nama']);
                $avatarUrl  = $newUser['avatar'] ?? 'images/pria.png';
                if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                    $avatarUrl = $this->url($avatarUrl);
                }

                $this->setUser([
                    'user_id'        => $newUser['id'],
                    'userid'         => $newUser['userid'] ?? $newUser['id'],
                    'user_name'      => $userEntity,
                    'user_real_name' => $newUser['nama'],
                    'email'          => $newUser['email'],
                    'avatar'         => $avatarUrl,
                    'status'         => $newUser['status'] ?? 'user',
                    'role'           => $newUser['role']   ?? 'user',
                    'login_time'     => time(),
                    'last_activity'  => time(),
                ]);

                echo json_encode([
                    'success'  => true,
                    'message'  => 'Pendaftaran berhasil!',
                    'user'     => [
                        'id'     => $newUser['id'],
                        'nama'   => $newUser['nama'],
                        'email'  => $newUser['email'],
                        'avatar' => $avatarUrl,
                        'status' => $newUser['status'],
                        'role'   => $newUser['role'],
                        'token'  => $trialKey,
                    ],
                ]);
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'User dibuat tapi gagal dimuat']);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Handle Google Sign-Up
     */
    public function signup() {
        // IMPORTANT: Set headers first to prevent any output before JSON
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Clean output buffer to prevent HTML comments
        if (ob_get_level()) {
            ob_clean();
        }
        
        if (!$this->isPost()) {
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit; // Exit immediately after JSON response
        }
        
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!isset($data['credential'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Credential tidak ditemukan'
            ]);
            exit;
        }
        
        // Verify and decode Google JWT token
        $googleUser = $this->verifyGoogleToken($data['credential']);
        
        if (!$googleUser) {
            echo json_encode([
                'success' => false,
                'message' => 'Token Google tidak valid'
            ]);
            exit;
        }
        
        // Check if user already exists
        $userModel = $this->refModels('Oauth');
        $existingUser = $userModel->Storage('user')
            ->select(['*'])
            ->where('email', $googleUser['email'])
            ->first();
        
        if ($existingUser) {
            // User already exists, log them in instead
            $userEntity = $this->slugify($existingUser['nama']);
            
            $this->syncGoogleAvatarToLocal($userModel, $existingUser, $googleUser['picture'] ?? null);
            $avatarUrl = $existingUser['avatar'] ?? '/drive/avatar/pria.png';
            
            // If avatar is a relative path, convert to full URL
            if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                $avatarUrl = $this->url($avatarUrl);
            }
            
            $this->setUser([
                'user_id' => $existingUser['id'],
                'userid' => $existingUser['userid'] ?? $existingUser['id'],
                'user_name' => $userEntity,
                'user_real_name' => $existingUser['nama'],
                'email' => $existingUser['email'],
                'avatar' => $avatarUrl,
                'status' => $existingUser['status'] ?? 'user',
                'role' => $existingUser['status'] ?? 'user',
                'login_time' => time(),
                'last_activity' => time()
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil dengan Google!',
                'redirect' => $this->url($userEntity),
                'user' => [
                    'name' => $existingUser['nama'],
                    'email' => $existingUser['email']
                ]
            ]);
            exit;
        }
        
        // Create new user
        // Generate secure random password for Google users
        // They won't use this password (login via Google), but it's required by DB
        $randomPassword = password_hash(uniqid('google_', true), PASSWORD_DEFAULT);
        
        // Clean name: remove quotes and extra spaces
        $cleanedName = $this->cleanGoogleName($googleUser['name']);
        
        $insertData = [
            'nama' => $cleanedName,
            'email' => $googleUser['email'],
            'password' => $randomPassword, // Secure bcrypt hash (60 chars)
            'status' => 'user',
            'role' => 'user',
            'row' => '1',
            'gender' => 'male', // Default
            'avatar' => '/drive/avatar/pria.png'
        ];
        
        try {
            $result = $userModel->Storage('user')->insert($insertData);
            
            if ($result) {
                // Get the newly created user
                $newUser = $userModel->Storage('user')
                    ->select(['*'])
                    ->where('email', $googleUser['email'])
                    ->first();
                
                if ($newUser) {
                    // Auto login the user
                    $userEntity = $this->slugify($newUser['nama']);

                    // Generate 7-day trial license key dan simpan ke tabel licenses + user.token
                    $trialKey    = $this->licenseGenerate((int) $newUser['id']);
                    $trialExpiry = time() + (7 * 24 * 3600);
                    $userModel->Storage('licenses')->insert([
                        'userid'      => (string) $newUser['id'],
                        'license_key' => $trialKey,
                        'trial'       => '7',
                        'status'      => 'active',
                        'max_devices' => 1,
                        'expired_at'  => $trialExpiry,
                    ]);
                    $userModel->Storage('user')
                        ->where('id', $newUser['id'])
                        ->update(['token' => $trialKey]);
                    
                    $this->syncGoogleAvatarToLocal($userModel, $newUser, $googleUser['picture'] ?? null);
                    $avatarUrl = $newUser['avatar'] ?? '/drive/avatar/pria.png';
                    
                    // If avatar is a relative path, convert to full URL
                    if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                        $avatarUrl = $this->url($avatarUrl);
                    }
                    
                    $this->setUser([
                        'user_id' => $newUser['id'],
                        'userid' => $newUser['userid'] ?? $newUser['id'],
                        'user_name' => $userEntity,
                        'user_real_name' => $newUser['nama'],
                        'email' => $newUser['email'],
                        'avatar' => $avatarUrl,
                        'status' => $newUser['status'] ?? 'user',
                        'role' => $newUser['role'] ?? 'user',
                        'login_time' => time(),
                        'last_activity' => time()
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pendaftaran berhasil dengan Google!',
                    'redirect' => $this->url($userEntity ?? '/'),
                    'user' => [
                        'name' => $cleanedName,
                        'email' => $googleUser['email']
                    ]
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal menyimpan data user'
                ]);
                exit;
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Handle Google Sign-In
     */
    public function signin() {
        // IMPORTANT: Set headers first to prevent any output before JSON
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Clean output buffer to prevent HTML comments
        if (ob_get_level()) {
            ob_clean();
        }
        
        if (!$this->isPost()) {
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
        
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!isset($data['credential'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Credential tidak ditemukan'
            ]);
            exit;
        }
        
        // Verify and decode Google JWT token
        $googleUser = $this->verifyGoogleToken($data['credential']);
        
        if (!$googleUser) {
            echo json_encode([
                'success' => false,
                'message' => 'Token Google tidak valid'
            ]);
            exit;
        }
        
        // Check if user exists
        $userModel = $this->refModels('Oauth');
        $user = $userModel->Storage('user')
            ->select(['*'])
            ->where('email', $googleUser['email'])
            ->first();
        
        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'Akun tidak ditemukan. Silakan daftar terlebih dahulu.'
            ]);
            exit;
        }
        
        // Login the user
        $userEntity = $this->slugify($user['nama']);
        
        $this->syncGoogleAvatarToLocal($userModel, $user, $googleUser['picture'] ?? null);
        $avatarUrl = $user['avatar'] ?? '/drive/avatar/pria.png';
        
        // If avatar is a relative path, convert to full URL
        if (!filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $avatarUrl = $this->url($avatarUrl);
        }
        
        $this->setUser([
            'user_id' => $user['id'],
            'userid' => $user['userid'] ?? $user['id'],
            'user_name' => $userEntity,
            'user_real_name' => $user['nama'],
            'email' => $user['email'],
            'avatar' => $avatarUrl,
            'status' => $user['status'] ?? 'user',
            'role' => $user['role'] ?? 'user',
            'login_time' => time(),
            'last_activity' => time()
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login berhasil dengan Google!',
            'redirect' => $this->url($userEntity),
            'user' => [
                'name' => $user['nama'],
                'email' => $user['email']
            ]
        ]);
        exit;
    }
    
    /**
     * Verify Google ID Token
     * 
     * @param string $token Google ID Token
     * @return array|false User data or false on failure
     */
    private function verifyGoogleToken($token) {
        try {
            // Decode JWT without verification (for development)
            // In production, you should verify the signature properly
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $payload = json_decode($this->base64UrlDecode($parts[1]), true);
            
            if (!$payload) {
                return false;
            }
            
            // Verify issuer
            if (!isset($payload['iss']) || 
                ($payload['iss'] !== 'https://accounts.google.com' && 
                 $payload['iss'] !== 'accounts.google.com')) {
                return false;
            }
            
            // Verify audience (client ID)
            if (!isset($payload['aud']) || $payload['aud'] !== $this->googleClientId) {
                return false;
            }
            
            // Verify expiration
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                return false;
            }
            
            // Extract user data
            return [
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? 'Google User',
                'picture' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
                'sub' => $payload['sub'] ?? null // Google User ID
            ];
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Base64 URL Decode
     */
    private function base64UrlDecode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
    
    /**
     * Unduh foto profil Google ke assets/drive/avatar/YYYY/MM/ dan update kolom avatar user.
     *
     * @param \App\Models\Oauth $userModel
     * @param array $userRow baris user (id, avatar); diubah in-place jika berhasil simpan
     * @param string|null $pictureUrl URL dari klaim JWT Google
     */
    private function syncGoogleAvatarToLocal($userModel, array &$userRow, ?string $pictureUrl): void {
        $saved = $this->saveGooglePictureToAvatarDir($pictureUrl, (int) $userRow['id']);
        if ($saved === null) {
            return;
        }
        $this->removePreviousAvatarFile($userRow['avatar'] ?? null);
        $userModel->Storage('user')
            ->where('id', $userRow['id'])
            ->update(['avatar' => $saved]);
        $userRow['avatar'] = $saved;
    }

    /**
     * Unduh gambar dari URL Google, simpan sebagai file lokal (format sama AccountController).
     *
     * @return string|null path untuk DB, mis. /assets/drive/avatar/2026/04/avatar_1_123.jpg
     */
    private function saveGooglePictureToAvatarDir(?string $pictureUrl, int $userId): ?string {
        if (empty($pictureUrl) || !filter_var($pictureUrl, FILTER_VALIDATE_URL)) {
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 20,
                'follow_location' => 1,
                'user_agent'      => 'Mozilla/5.0 (NexaUI)',
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $data = @file_get_contents($pictureUrl, false, $ctx);
        if ($data === false || strlen($data) > 2 * 1024 * 1024) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
        ];
        if (!isset($mimeMap[$mime])) {
            return null;
        }
        $ext = $mimeMap[$mime];
        $year = date('Y');
        $month = date('m');
        $relDir = "assets/drive/avatar/{$year}/{$month}/";
        $root = dirname(__DIR__, 2);
        $dirFs = $root . '/' . $relDir;
        if (!is_dir($dirFs)) {
            if (!@mkdir($dirFs, 0755, true)) {
                return null;
            }
        }
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $fullPath = $dirFs . $filename;
        if (file_put_contents($fullPath, $data) === false) {
            return null;
        }
        return '/' . $relDir . $filename;
    }

    /**
     * Hapus file avatar lama di disk jika bukan default (sama prinsip AccountController).
     */
    private function removePreviousAvatarFile(?string $avatar): void {
        if (empty($avatar)) {
            return;
        }
        $path = ltrim($avatar, '/');
        $isDefault = (strpos($path, 'avatar/pria.png') !== false
            || strpos($path, 'avatar/wanita.png') !== false
            || strpos($path, 'images/pria.png') !== false);
        if ($isDefault) {
            return;
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return;
        }
        $root = dirname(__DIR__, 2);
        $full = $root . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Handle forgot password — generate & kirim password sementara via email
     * POST /api/forgot  { email }
     */
    public function forgot() {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        if (ob_get_level()) ob_clean();

        if (!$this->isPost()) {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data  = json_decode($input, true);
        $email = trim($data['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
            exit;
        }

        $userModel = $this->refModels('Oauth');
        $user = $userModel->Storage('user')
            ->select(['id', 'nama', 'email'])
            ->where('email', $email)
            ->first();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Email tidak terdaftar']);
            exit;
        }

        // Generate password sementara 10 karakter (huruf + angka)
        $chars   = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $tempPass = '';
        for ($i = 0; $i < 10; $i++) {
            $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $userModel->Storage('user')
            ->where('id', $user['id'])
            ->update(['password' => password_hash($tempPass, PASSWORD_DEFAULT)]);

        echo json_encode([
            'success'  => true,
            'message'  => 'Password sementara berhasil dibuat',
            'email'    => $user['email'],
            'nama'     => $user['nama'],
            'password' => $tempPass,
        ]);
        exit;
    }

    /**
     * Clean Google Name
     * Remove quotes and normalize spaces
     * Example: Abdul Maskhur "Iyan R Saleh" Saleh -> Abdul Maskhur Iyan R Saleh Saleh
     * 
     * @param string $name Original name from Google
     * @return string Cleaned name
     */
    private function cleanGoogleName($name) {
        if (empty($name)) {
            return 'Google User';
        }
        
        // Remove all types of quotes (single and double, straight and curly)
        $quotes = [
            '"',  // Straight double quote
            "'",  // Straight single quote
            "\xE2\x80\x9C", // Left double quotation mark (")
            "\xE2\x80\x9D", // Right double quotation mark (")
            "\xE2\x80\x98", // Left single quotation mark (')
            "\xE2\x80\x99"  // Right single quotation mark (')
        ];
        $name = str_replace($quotes, '', $name);
        
        // Normalize multiple spaces to single space
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Trim leading and trailing spaces
        $name = trim($name);
        
        return $name;
    }
}
