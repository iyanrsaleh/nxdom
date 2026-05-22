<?php
declare(strict_types=1);
namespace App\Controllers;
use App\System\NexaController;

/**
 * DashboardController - Enhanced with Integrated NexaNode
 * Now uses built-in nodeController() methods instead of standalone NexaNode
 */
class AdminController extends NexaController
{
    private string $nodeNamespace = 'App\\Controllers\\Admin\\';
     /**
     * Track dan build parameter routing untuk controller actions
     * 
     * Generate parameter array dengan role dan routing information
     * yang diperlukan untuk operasi controller.
     * 
     * @param array $params Input parameters dari router
     * @return array Processed parameters dengan routing info
     */
      private function divert2()
      {
       // Redirect mobile and tablet users to base URL immediately
           $device = $this->getDevice();
           if ($device['is_mobile'] || $device['is_tablet']) {
               $this->redirect($this->getBaseUrl());
           }
      }
    
    /**
     * Constructor - No longer needs NexaNode instantiation
     * 
     * @param object $view View handler
     * @param array $deviceLayouts Device layout configuration
     */
    public function __construct($view, array $deviceLayouts = [])
    {
        parent::__construct($view, $deviceLayouts);
        $this->setControllerNamespace($this->nodeNamespace);
        
        // Check if user role is 'user', redirect to home
        $this->checkUserRole();
    }
    
    /**
     * Check user role and redirect if role is 'user'
     */
    private function checkUserRole(): void
    {
        if (!$this->isLoggedIn()) {
            return;
        }
        
        $role = $this->Storage('user')
            ->select(['role'])
            ->where('id', $this->session->getUserId())
            ->first();
        
        if ($role && isset($role['role']) && $role['role'] === 'user') {
            $this->redirect('/workspace');
            exit;
        }
    }
    
    /**
     * Track user online status (last seen)
     * Called in index() and page() methods after session is ready
     * 
     * Simple & lightweight - only update user status, no logging
     */
    private function trackUserActivity(): void
    {
        // Dinonaktifkan
    }
    
    private function link($page=''){
        return $this->url($this->getSession()->getUserSlug().$page);
    }
    private function dataGlobal(array $params = []): array{
         //  $packages= $this->useModels('Role/Package', 'User', [$this->session->getUserId()]); 
          $role = $this->Storage('user')
          ->select(["role","email",'avatar','nama','package'])
          ->where("id",$this->session->getUserId())
          ->first();
        // Fix URL construction to avoid double slashes
        $slug0 = $this->getSlug(0);
        $slug1 = $this->getSlug(1, '');
        $slug2 = $this->getSlug(2, '');
        $pageSearchUrl = $this->getBaseUrl() . '/' . $slug0;
        if (!empty($slug1)) {
            $pageSearchUrl .= '/' . $slug1;
        }
        if (!empty($slug2)) {
            $pageSearchUrl .= '/' . $slug2;
        }
        $analysis = $this->getBrowserInfo();
        $roleDisplay = $role['role'] === 'admin' ? 'Administrator' : 'Account User';
        $userRealName = $role['nama'];
        $userFirstName = $userRealName ? explode(' ', trim($userRealName))[0] : '';
        $allowedPages = !empty($role['package'])
            ? array_map('trim', explode(',', (string) $role['package']))
            : [];
        if (($role['role'] ?? '') === 'admin') {
            $allowedPages[] = 'package';
        }
        $packageOpts = $this->getPackageOptions();
        $menuPackages = [];
        foreach ($packageOpts as $p) {
            // Menu sidebar: hanya Public (2). System (1) dan hidden (3) tidak ditampilkan.
            if ((int) ($p['development'] ?? 2) !== 2) {
                continue;
            }
            $key = $p['key'] ?? '';
            $menuPackages[] = [
                'key' => $key,
                'label' => $p['label'] ?? $key,
                'icon' => $p['icon'] ?? 'fas fa-circle',
                'url' => $this->link('/' . $key),
                'has_access' => in_array($key, $allowedPages, true) ? '1' : '',
            ];
        }
      
        return [
            'darkmode'       => (isset($_COOKIE['darkmode']) && $_COOKIE['darkmode'] === '1') ? 'dark-mode' : '',
            'sidebarCollapsed' => (isset($_COOKIE['sidebarCollapsed']) && $_COOKIE['sidebarCollapsed'] === '1') ? 'sidebar-collapsed' : '',
            'user_id'        => $this->session->getUserId(),
            'user_name'      => $this->session->getUserSlug(),
            'user_real_name' => $userRealName,
            'user_first_name' => $userFirstName,
            'user_email'    => $role['email'] ?? '',
            'avatarUID'      => $role['avatar'] ?? '',
            'role'           => $roleDisplay,
            'collapsed'      => $this->getCollapsed(),
            'url'            => $this->link(),
            'home'           => $this->link(),
            'web'            => $this->url('/home'),
            'search'         => $this->link('/search'),
            'link'           => $this->url('/'),
            'logout'         => $this->url('/logout'),
            'assets'         => $this->url('/assets/'),
            'device'         => $analysis['device_type'],
            'menu_packages'  => $menuPackages,
            'page_search'    => $pageSearchUrl,
            'search_keyword' => '',
            'search_query'   => '',

        ];
    }

    /**
     * Dashboard index page - /{username}
     * Enhanced with integrated NexaNode functionality
     */
    public function index(array $params = []): void
    {
    
        // Validate user access
        $username = $params['username'] ?? '';
        if ($username !== $this->getSession()->getUserSlug()) {
            $this->redirect('signin');
            return;
        }
       // $this->divert2();
        
        // Track user activity
        $this->trackUserActivity();
        
        $this->setData($this->dataGlobal($params));
       // $this->useVisitor('Dashboard');
        // Setup page variables
        $this->assignVars([
            'page_title' => 'Akun - ' . $this->getSession()->getUserRealName(),
            'page_description' => 'User Dashboard',
            'current_page' => 'dashboard',
            ... $this->dataGlobal($params)
        ]);

        $page = $params['page'] ?? 'index';
        
        try {
            // Check if controller exists and execute it
            if ($this->controllerExists($page)) {
                // Execute controller - methods are now void and use assignVars()
                $this->nodeController($page, $params);
                if ($page === 'index') {
                    $templatePath = 'index';
                } else {
                    $templatePath = strtolower($page) . '/index';
                }

                $this->setDeviceType('dashboard');
                $this->render($templatePath);
                return;
                
            } else {
                // Controller not found - fallback to default dashboard
                $this->assignVar('fallback_info', [
                    'requested_page' => $page,
                    'reason' => 'Controller not found',
                    'available_controllers' => $this->getAvailableControllers()
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Dashboard index error: " . $e->getMessage());
            $this->assignVar('error_message', 'Page temporarily unavailable.');
        }
      
        // Fallback: render default dashboard index
        $this->setDeviceType('dashboard');
        $this->render('index');
    }
    
    /**
     * Dashboard page routing - /{username}/{page}/{method?}
     * Supports dynamic method routing with automatic fallback to index
     */
    public function page(array $params = []): void
    {
         $page = $params['page'] ?? $this->getSlug(1, '');
         
         // Check if user has phone number, redirect to account if null
         // Exception: allow access to 'account' page itself
         // Wrap in try-catch agar useModels error tidak mengganggu routing (ref: devplening)
         if ($page !== 'account') {
             try {
                 $userData = $this->useModels('User', 'byId', [$this->session->getUserId()]);
                 if (!empty($userData) && empty($userData['telepon'])) {
                     $accountUrl = $this->getBaseUrl() . '/' . $this->session->getUserSlug() . '/account';
                     $this->redirect($accountUrl);
                     return;
                 }
             } catch (\Throwable $e) {
                 // Jangan block routing jika model/DB error - lanjut
             }
         }

         //$this->divert2();
         
        // Akses umum: account postingan & home boleh untuk semua user
        // Halaman lain (package, theme, user, example, dll) hanya boleh jika ada di package user
        // Admin selalu boleh akses 'package' (untuk kelola package)
        $username = $params['username'] ?? '';
        if (
            $page !== 'account' && 
            $page !== 'postingan' && 
            $page !== 'workspace' && 
            $page !== 'home'
         ) {
            $me = $this->Storage('user')
                ->select(['package', 'role'])
                ->where('id', $this->session->getUserId())
                ->first();
            $allowedPages = !empty($me['package'])
                ? array_map('trim', explode(',', (string) $me['package']))
                : [];
            if (($me['role'] ?? '') === 'admin') {
                $allowedPages[] = 'package';
            }
            if (!in_array($page, $allowedPages, true)) {
                $this->redirect('signin');
                return;
            }
        }
        
        // IMPORTANT: Don't override $page here, it's already set above
        // $page is already set and may have been changed from ORD-* to 'seller'
        $requestMethod = $this->getRequestMethod();
        $requestedMethod = 'index'; // default fallback
        
        // Get third segment for method
        $thirdSegment = $this->getSlug(2);
        if (!empty($thirdSegment)) {
            $requestedMethod = $thirdSegment;
        }
        
        // Method validation & fallback system
        $targetClass = $this->nodeNamespace . ucfirst($page) . 'Controller';
        $finalMethod = $requestedMethod;
        $usedFallback = false;
        
        if (class_exists($targetClass)) {
            // Check if requested method exists, fallback to index if not
            if (!method_exists($targetClass, $requestedMethod)) {
                $finalMethod = 'index';
                $usedFallback = true;
            }
        }   


         // $this->redirect('/home');
        
        // Validate user access
        if ($username !== $this->getSession()->getUserSlug()) {
            $this->redirect('/signin');
            return;
        }
      //  $this->useVisitor(ucfirst($page));
      
        // Track user activity
        // $this->trackUserActivity();
      
        // Setup page context
        $this->assignVars([
            'page_title' => 'Dashboard - ' . ucfirst($page),
            'current_page' => $page,
            ... $this->dataGlobal($params)
        ]);

        try {
            // Check if controller exists first
            if ($this->controllerExists($page)) {
                // Execute controller with method - methods are now void
                $controllerCall = $this->callController($page)
                     ->method($finalMethod)
                     ->withParams($params)
                     ->param('request_method', $requestMethod)
                     ->param('form_data', $_POST)
                     ->param('method', $finalMethod)
                     ->param('requested_method', $requestedMethod)
                     ->param('used_fallback', $usedFallback)
                     ->param('dashboard_context', true)
                     ->execute();
                
                $getTemplatePathMethod = 'getTemplatePath';
                if (method_exists($targetClass, $getTemplatePathMethod)) {
                    $templatePath = $targetClass::$getTemplatePathMethod($finalMethod, $requestMethod);
                } else {
                    // Default: POST -> page/index, GET -> isFile3
                    if ($requestMethod === 'POST') {
                        $templatePath = strtolower($page) . '/index';
                    } else {
                        $templatePath = $this->isFile3(2, 'index', 'dashboard');
                    }
                }
                 if ($templatePath !== null) {
                     $this->setDeviceType('dashboard');
                     $this->render($templatePath);
                 }
                return;
                
            } else {
                // Controller not found - set fallback info
                $this->assignVar('fallback_info', [
                    'requested_page' => $page,
                    'requested_method' => $requestedMethod,
                    'reason' => 'Controller not found',
                    'available_controllers' => $this->getAvailableControllers()
                ]);
            }
            
        } catch (Exception $e) {
            // Silent fallback - no need to log since we fallback to index anyway
            $this->assignVar('error_message', 'Page not found or temporarily unavailable.');
        }
        
        // Fallback: render default dashboard index
        $this->setDeviceType('dashboard');
        $this->render('index');
    }

    private function getCollapsed() {
        if (!empty($_COOKIE['sidebarCollapsed'])) {
           return 'collapsed';
        } else {
           return '';
        }
    }

}
