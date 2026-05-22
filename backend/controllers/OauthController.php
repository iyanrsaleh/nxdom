<?php
/**
 * OAuth Controller
 * Handles authentication functionality
 */
namespace App\Controllers;
use App\System\NexaController;
use App\System\Helpers\NexaNav;
class OauthController extends NexaController {

    /**
     * Signup method - Handle user registration
     */
    public function signup() { 
        // Redirect if already logged in
        if ($this->isLoggedIn()) {
            $this->redirect($this->getUser()['user_name'] ?? '/');
        }

        $analysis = $this->getBrowserInfo();

        // Set unique visitor ID for tracking
        $this->setJsController([
            'uniqueId' => $this->session->getVisitorKey(),
        ]);

        // Get template variables from previous state (for error display)
        $templateVars = $this->getState('form_signup');

        // Set values that will be preserved on error
        $this->setValue([
            "nama",
            "email",
            "password",
            "konfirmasi_password",
            "message",
        ]);

        // Assign template variables
        $this->assignVars([
            'page_title' => "Signup",
            'title' => "Daftar Akun Baru",
            'home' => $this->url('/home'),
            'device' => $analysis['device_type'],
            'signup' => $this->url('/signup'),
            'signin' => $this->url('/signin'),
            'uniqueId' => $this->session->getVisitorKey(),
            ...$templateVars ?? []
        ]);

        // Clear state after using it
        $this->clearState('form_signup');

        // Create form with validation rules
        $form = $this->createForm()
            ->fields([
                'nama'                => 'Name|3|Nama minimal 3 karakter',
                'email'               => 'required|email|Format email tidak valid',
                'password'            => 'Name|6|Password minimal 6 karakter',
                'konfirmasi_password' => 'Name|6|Konfirmasi password minimal 6 karakter',
            ])
            ->setSuccess('Pendaftaran berhasil! Silakan login dengan akun Anda.')
            ->setError('Mohon perbaiki kesalahan berikut');

        // Process form
        $result = $form->process();

        if ($this->isPost()) {
            $clearValues = $result['success'] ?? false;

            // Additional validation for password confirmation
            if ($result['success']) {
                $password = $result['data']['password'] ?? '';
                $konfirmasiPassword = $result['data']['konfirmasi_password'] ?? '';

                if ($password !== $konfirmasiPassword) {
                    $result['success'] = false;
                    $result['errors_password'] = 'Password tidak cocok';
                    $result['errors_konfirmasi_password'] = 'Password tidak cocok';
                    $result['message'] = 'Password dan konfirmasi password tidak cocok';
                    $clearValues = false;
                }
            }

            // Check if email already exists
            if ($result['success']) {
                $user = $this->refModels('Oauth');
                $existingUser = $user->Storage('user')
                    ->select(['email'])
                    ->where('email', $result['data']['email'])
                    ->first();

                if ($existingUser) {
                    $result['success'] = false;
                    $result['errors_email'] = 'Email sudah terdaftar';
                    $result['message'] = 'Email sudah digunakan, gunakan email lain atau login';
                    $clearValues = false;
                }
            }

            // If validation passed, create user
            if ($result['success']) {
                $user = $this->refModels('Oauth');
                $signupData = [
                    'nama'     => $result['data']['nama'],
                    'email'    => $result['data']['email'],
                    'password' => $result['data']['password'], // Note: In production, hash this!
                ];

                $status = $user->signup($signupData);

                if ($status) {
                    // Registration successful
                    $result['message'] = 'Pendaftaran berhasil! Silakan login.';
                    $result['success'] = true;
                    
                    $templateVars = $form->Response(array_merge($result, ['signup_success' => true]), true);
                    $this->setState('form_signup', $templateVars);
                    
                    // Set success flash message
                    $this->setFlash('success', 'Pendaftaran berhasil! Selamat datang, silakan login.');
                    
                    // Redirect to signin page
                    $this->redirect('signin');
                } else {
                    // Registration failed
                    $result['success'] = false;
                    $result['message'] = 'Pendaftaran gagal. Terjadi kesalahan saat menyimpan data.';
                    $result['errors_nama'] = 'Terjadi kesalahan saat menyimpan data';
                    $clearValues = false;
                    
                    $templateVars = $form->Response($result, $clearValues);
                    $this->setState('form_signup', $templateVars);
                    $this->redirect('signup');
                }
            } else {
                // Validation failed - stay on signup page with errors
                $templateVars = $form->Response($result, $clearValues);
                $this->setState('form_signup', $templateVars);
                $this->redirect('signup');
            }
        }

        $this->render('oauth/signup');
    } 
    public function signin() {  
        if ($this->isLoggedIn()) {
              $this->redirect($this->getUser()['user_name'] ?? '/');
        }
        $analysis = $this->getBrowserInfo();

        
         // echo  $uniqueId = $this->session->getVisitorId();
                $this->setJsController([
                      'uniqueId' =>$this->session->getVisitorKey(), // Changed to use persistent visitor key
                  ]);
           $templateVars = $this->getState('form_add');
   
            $this->setValue([
              "email",
              "password",
              "message",
            ]);
       
            $this->assignVars([
                 'page_title'=>"Signin",
                 'device' => $analysis['device_type'],
                 'home' => $this->url('/home'),
                 'signup' => $this->url('/signup'),
                 'signin' => $this->url('/signin'),
                 'uniqueId'=>$this->session->getVisitorKey(), // Changed to use persistent visitor key
                ...$templateVars??[]
            ]);

          $this->setJsController([
                 'page_title'=>"Signin",
                 'device' => $analysis['device_type'],
                 'home' => $this->url('/home'),
           ]);


            $this->clearState('form_add');

             $form = $this->createForm()
            ->fields([
                'email'     => 'Name|3|Nama minimal 3 karakter',
                'password'  => 'Name|3|Alamat minimal 3 karakter',
            ])
            ->setSuccess('Berhasil Login Akun')
            ->setError('Mohon perbaiki kesalahan berikut');

        $result = $form->process();

       if ($this->isPost()) {
          $clearValues = $result['success'] ?? false;
          $user = $this->refModels('Oauth');
          $status = $user->signin($result['data']);
          
          // Check if authentication was successful
          if ($status && is_array($status)) {
              // User found and authenticated successfully
              $status['authenticated'] = true;
              $userEntity=$this->slugify($status['nama']);
              // Save user data to session
              $this->setUser([
                  'user_id' => $status['id'],
                  'userid' => $status['userid'] ?? $status['id'], // Fallback to id if userid not available
                  'user_name' =>$userEntity ,
                  'user_real_name' =>$status['nama'],
                  'email' => $status['email'],
                  'avatar' =>$this->url($status['avatar'] ?? 'images/pria.png'),
                  'status' => $status['status'] ?? 'user',
                  'role' => $status['status'] ?? 'user',
                  'login_time' => time(),
                  'last_activity' => time()
              ]);
              $templateVars = $form->Response(array_merge($result, $status), $clearValues);
              $this->setState('form_add', $templateVars);
              // Set success flash message
              $this->setFlash('success', 'Login berhasil! Selamat datang ' . $status['nama']);
              // Redirect to dashboard or success page
                    $jsData = (new MainController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
                    $this->setJsController([
                      ...$jsData
                  ]);

              $this->redirect($userEntity);
          } else {
              // Authentication failed - user not found or invalid credentials
              $result['errors_email'] = 'Email atau password salah';
              $result['errors_password'] = 'Email atau password salah';
              $result['message'] = 'Login gagal. Periksa kembali email dan password Anda.';
              $result['success'] = false;
              $status = ['user' => null, 'authenticated' => false];
              $templateVars = $form->Response(array_merge($result, $status), false);
              $this->setState('form_add', $templateVars);
              $this->redirect('signin');
              // Don't redirect on failure - stay on signin page to show errors
          }
        
         }
       $this->render('oauth/signin');


    }

    /**
     * Logout method - menggunakan method dari NexaController
     */
    public function logout() {  
          $this->setLogout();
          $this->redirect('home');
    }


} 
