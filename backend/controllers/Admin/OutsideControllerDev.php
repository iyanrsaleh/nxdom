<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * AccessController - Controller untuk mengelola Access Control dan Role Management
 * 
 * Controller ini menangani operasi CRUD untuk sistem kontrol akses dan manajemen role.
 * Mendukung resource mapping untuk routing otomatis, form handling dengan validasi,
 * dan session state management.
 * 
 * Fitur Utama:
 * - CRUD operations untuk Access Control 
 * - Resource mapping untuk routing otomatis
 * - Form handling dengan validasi
 * - Session state management
 * - Integration dengan Role/Control model
 * 
 * URL Patterns:
 * - /{username}/access → index() - List semua access
 * - /{username}/access/add → add() - Tambah access baru  
 * - /{username}/access/edit/{id} → edit() - Edit access
 * - /{username}/access/delete/{id} → delete() - Hapus access
 * 
 * @package App\Controllers\Admin
 * @author Nexa Framework
 * @version 1.0
 */
class OutsideController extends NexaController
{
    /**
     * Inisialisasi controller dengan dukungan resource mappings
     * 
     * Setup routing mappings untuk URL pattern otomatis dan konfigurasi
     * controller yang diperlukan untuk operasi access control.
     * 
     * @return void
     */
    protected function init(): void
    {
        parent::init();
        
        // Setup resource mappings untuk URL routing otomatis
        $this->addResourceMappings([
            'delete' => 'delete',
        ]);
    }

    /**
     * Track dan build parameter routing untuk controller actions
     * 
     * Generate parameter array dengan role dan routing information
     * yang diperlukan untuk operasi controller.
     * 
     * @param array $params Input parameters dari router
     * @return array Processed parameters dengan routing info
     */
    private function track(array $params): array
    {
        return $this->routerAction($params, [
            'role' => 'add',
        ]);
    }
 
    /**
     * Ambil konfigurasi data global berdasarkan key
     * 
     * Return predefined data arrays untuk berbagai keperluan
     * seperti field validation dan form processing.
     * 
     * @param string $key Data key yang diminta
     * @return array Data array sesuai key atau empty array jika tidak ditemukan
     */
    private function variabelGlobal(string $key): array
    {
        $data = [
            'setValue' => ['categori', 'keywords', 'icon', 'label']
        ];

        return $data[$key] ?? [];
    }
 
    /**
     * Halaman utama Access Control - Menampilkan daftar access control
     * 
     * Method ini menangani halaman index yang menampilkan daftar semua access control
     * dengan form untuk add/edit. Handle resource routing secara otomatis untuk
     * operasi delete, edit, dan lainnya.
     * 
     * @param array $params Router parameters dari URL
     * @return void
     */
    public function index(array $params = []): void
    {
        // Handle resource routing (delete, edit, dll) secara otomatis  
        if ($this->mapping()->handleRouting($params['mapping'] ?? '')) {
            return; // Route sudah ditangani oleh resource mapping
        }
        
     $page = $this->pagesIntRequest(); 
        $requestParams = $this->paramsKeys(); 

        // Ambil data semua pengguna dari model
        $percentage = $this->useModels('Role/User', 'percentage',[]);
        $user = $this->useModels('Role/User', 'user',[
            $requestParams['search'],
            $page,
            $this->session->getUserId()
        ]);
        $this->nexaVars(array(
            'total'=>$user['total'],
            'user_total'=>$percentage['user'],
            'permoderator_total'=>$percentage['moderator'],
            'admin_total'=>$percentage['admin'],
            'last_page'=>$user['last_page'],
            
            'peruser'=>$percentage['peruser'],
            'permoderator'=>$percentage['permoderator'],
            'peradmin'=>$percentage['peradmin'],

            'badge_user'=>$percentage['badge_user'],
            'badge_moderator'=>$percentage['badge_moderator'],
            'badge_admin'=>$percentage['badge_admin'],

        ));
        // $this->nexaVars($percentage);

      // $this->dump($user);
        $this->nexaBlock("user", $user['data']);

         $paginationHTML = $this->NexaPagination()->render(
            $user['current_page'],
            $user['last_page'], 
            $params['page_index'] . "/user/?pages/"
        );
        
        $this->assignVar('pagination', $paginationHTML);


    }
    
    /**
     * Manajemen package pengguna
     * 
     * Method ini menangani halaman untuk mengatur package/paket yang dimiliki
     * oleh pengguna tertentu. Menampilkan form untuk mengubah package user
     * dan menangani form submission untuk update data.
     * 
     * @param array $params Router parameters dengan resource_id (user ID)
     * @return void
     */
    public function packages(array $params = []): void
    {
        // Ambil data pengguna berdasarkan resource_id
        $user = $this->useModels('Role/User', 'userById', [$params['resource_id']]);
        
        // Ambil data package dari controller dengan ID 150 (package template)
        $Package2 = $this->useModels('Role/User', 'packageById3', [$this->session->getUserId()]);
        $Package = $this->useModels('Role/User', 'packageById', [$this->session->getUserId()]);
        $categoryOptions = $this->useModels('Role/Select', 'option', ['Status']);
         $this->assignBlocks([
            'category' =>  $categoryOptions
        ]);
         // $this->dump($Package2);
        // Set data pengguna untuk template
        $this->setData($user);
        $this->assignVar('keywords', $Package2[0]['keywords']);
        $this->assignVar('package', $user[0]['package']);
        $this->setValue(['package','status'], 'errors');
        //$this->dump($params);
        // Ambil form state dari session
        $formState = $this->getState('form_add', $params);
        $this->nexaVars($formState);
        $this->clearState('form_add');

        // Handle POST request untuk update package
        if ($this->isPost()) {
            // Buat form dengan field validation
            $form = $this->createForm()
                ->fields([
                    'package' => 'Name|3|Package minimal 3 karakter',
                    'status'  => 'Select',
                ])
                ->setSuccess('Berhasil memperbarui Package User')
                ->setError('Mohon perbaiki kesalahan berikut');

            // Proses form dan update database
            $result = $form->process();
            if ($result['success']) {
                // Update package user dengan resource_id sebagai identifier
                $this->useModels('Role/User', 'packageAdd', [$result['data'], $params['resource_id']]); 
                 //$this->redirectBack('/user');
                // Redirect kembali ke halaman user
        
               $this->redirect($params['page_index'] . '/user/'.$this->getPageItem('url'));
            }
     
            // Handle form response untuk kasus error
            $clearValues = $result['success'] ?? false;
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan error state ke session
            $this->setState('form_add', array_merge($templateVars, $result));
            $this->redirect($params['current_url']);
        }
    }
}  
