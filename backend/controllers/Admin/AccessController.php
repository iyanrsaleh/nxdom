<?php
namespace App\Controllers\Admin;
use App\System\NexaController;
use App\System\Helpers\NexaBtoa;

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
class AccessController extends NexaController
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
        
        // Setup form fields dengan nilai default
        $this->setValue($this->variabelGlobal('setValue'));
        $this->setData($this->track($params));
        
        // Ambil session state untuk form management
        $templateVars = $this->getState('form_add', $this->track($params));
        
        // Load data dari model untuk ditampilkan
        $data = $this->useModels('Role/Control', 'data');  
        $this->nexaBlock("based", $data);
        $this->nexaVars(array_merge($templateVars));
      // $this->dump($data);
        
        // Clear form state setelah digunakan
        $this->clearState('form_add');

        // $Package = $this->useModels('Role/Control', 'packageById', [150]);
        // $this->setJsController([
        //   'keywords'=>$Package[0]['keywords'],
        // ],[
        // 'index'=>true
        // ]);

    }
    
    /**
     * Tambah Access Control baru
     * 
     * Method ini menangani form submission untuk menambah data access control baru.
     * Melakukan validasi form dan menyimpan data ke database jika valid.
     * 
     * @param array $params Router parameters dari URL
     * @return void
     */
    public function add(array $params = []): void
    {
        $page = $this->track($params);
        
        // Buat form dengan field validation
        $form = $this->createForm()
            ->fields([
                'categori' => 'Name|3|Categori minimal 3 karakter',
                'keywords' => 'Name|3|Keywords minimal 3 karakter', 
                'icon'     => 'Name|0|Icon minimal 3 karakter',
            ])
            ->setSuccess('Berhasil menambahkan Access Control')
            ->setError('Mohon perbaiki kesalahan berikut');

        // Proses form submission
        $result = $form->process();
        if ($result['success']) {
            // Simpan ke database via model
            $this->useModels('Role/Control', 'Access', [$result['data'], 'add']);  
        }
     
        // Handle form response dan template variables
        $clearValues = $result['success'] ?? false;
        $templateVars = $form->Response($this->track(array_merge($params, $result)), $clearValues);

        // Simpan template variables ke session state
        $this->setState('form_add', array_merge($templateVars, $result));

        // Redirect kembali ke halaman index
        $this->redirect($params['page_index']);
    }

    /**
     * Edit Access Control yang sudah ada
     * 
     * Method ini menangani operasi edit untuk access control yang sudah ada.
     * Load data existing untuk GET request dan handle form submission untuk
     * POST request dengan update ke database.
     * 
     * @param array $params Router parameters dengan resource_id
     * @return void  
     */
    public function helpdesk(array $params = []): void{
        $Exam = $this->useParams('Access/General', 'data',[$params]);
        
        // Check if there was an error in data retrieval
        if (isset($Exam['error'])) {
            // Handle the error case - redirect back or show error
            $this->setState('form_add', [
                'errors' => ['general' => $Exam['error']],
                'success' => false
            ]);
            $this->redirect($params['page_index'] ?? '/');
            return;
        }
        
        $label=$this->setDecode($this->getSlug(4, ''));
        $Order = $this->useParams('Access/General', 'order',[$params,$label]);
        //$this->dump($Exam);
        $this->nexaVars([
         'pagelabel'   =>$this->isSlug(4, 'edit'),
         'sluglabel'   =>$label,
         ...$Exam,
         ...$params,
         ...$Order
        ]);   
        $this->nexaVars($Exam);  

        if ($this->isPost()) {
            //$this->setState('form_add',$this->getPost());
             $this->useParams('Access/General', 'sendOrder',[$params,$label,$this->getPost()]);
            $this->redirect($params['page_index'].'/helpdesk/'.$params['slug3'].'/'.$params['slug4']);
        }

    }

    public function namespace(array $params = []): void{
        // return self::update($params);
        $Exam = $this->useParams('Access/General', 'data',[$params]);
        
        // Check if there was an error in data retrieval
        if (isset($Exam['error'])) {
            // Handle the error case - redirect back or show error
            $this->setState('form_add', [
                'errors' => ['general' => $Exam['error']],
                'success' => false
            ]);
            $this->redirect($params['page_index'] ?? '/');
            return;
        }
       
         $this->dump($Exam);
         $this->setJsController($Exam);
        $this->nexaVars($Exam);
        if ($this->isPost()) {
            $this->setState('form_add',$this->getPost());
            $this->useParams('Access/General', 'setResource',[$this->getPost(),$Exam['resource_id']]);
            $this->redirect($params['page_index']);
        }
        // $this->setValue($data);
    }
    public function update(array $params = []): void
    {
        $page = $this->track($params);
        $Token = $this->getSlug(3, '');
        $resource_id = $this->secretKey()->decode($Token);
        
        // Check if decode was successful
        if ($resource_id === null || !isset($resource_id['id'])) {
            $this->setState('form_add', [
                'errors' => ['general' => 'Invalid or expired token'],
                'success' => false
            ]);
            $this->redirect($params['page_index'] ?? '/');
            return;
        }
        
        // Load data existing based on resource_id
        $data = $this->useModels('Role/Control', 'find', [$resource_id['id']]);
        $this->setValue($data);

        // Load session state for form
        $templateVars = $this->getState('form_add', $this->track($params));
        $this->nexaVars($templateVars);
        $this->clearState('form_add');

        // Handle POST request for updating data
        if ($this->isPost()) {
            // Create form with the same validation
            $form = $this->createForm()
                ->fields([
                    'categori' => 'Name|3|Categori minimal 3 karakter',
                    'keywords' => 'Name|0|Keywords minimal 3 karakter',
                    'icon' => 'Name|0|Icon minimal 3 karakter',
                    'deskripsi' => 'Name|3|Icon minimal 3 karakter',
                    'role' => 'Select',
                ])
                ->setSuccess('Berhasil memperbarui Access Control')
                ->setError('Mohon perbaiki kesalahan berikut');

            // Process form and update database
            $result = $form->process();
            if ($result['success']) {
                // Update data with resource_id as identifier
                $this->useModels('Role/Control', 'Access', [$result['data'], $resource_id['id']]);
                $this->redirect($params['page_index']);
            }

            // Handle form response for error cases
            $clearValues = $result['success'] ?? false;
            $templateVars = $form->Response($this->track(array_merge($params, $result)), $clearValues);

            // Save error state to session
            $this->setState('form_add', array_merge($templateVars, $result));
            $this->redirect($params['current_url']);
        }
    }

    /**
     * Hapus Access Control
     * 
     * Method ini menangani operasi delete access control via resource mapping.
     * Melakukan pengecekan khusus untuk ID tertentu yang tidak boleh dihapus
     * dan redirect kembali ke halaman index setelah operasi.
     * 
     * @param array $params Router parameters dengan resource_id
     * @return void
     */
    public function delete(array $params = []): void
    {
        $Token = $this->getSlug(3,'');
        $resource_id = $this->secretKey()->decode($Token);
        
        // Check if decode was successful
        if ($resource_id === null || !isset($resource_id['id'])) {
            $this->redirect($params['page_index'] ?? '/');
            return;
        }
        
        // Proteksi untuk ID khusus yang tidak boleh dihapus
        if ($resource_id['id'] !== '150' && $resource_id['id'] !== '155') {
            $this->useModels('Role/Control', 'removeById', [$resource_id['id']]);
        }  
        
        // Redirect kembali ke halaman index
        $this->redirect($params['page_index']);
    }

    /**
     * Halaman manajemen pengguna
     * 
     * Method ini menampilkan daftar semua pengguna dalam sistem
     * kecuali admin untuk keperluan manajemen user.
     * 
     * @param array $params Router parameters dari URL
     * @return void
     */
    public function account(array $params = []): void
    {  
        $page = $this->pagesIntRequest(); 
        $requestParams = $this->paramsKeys(); 

        // Ambil data semua pengguna dari model
        $percentage = $this->useModels('Role/Control', 'percentage',[]);
        $user = $this->useModels('Role/Control', 'user',[
            $requestParams['search'],
            $page
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
        $user = $this->useModels('Role/Control', 'userById', [$params['resource_id']]);
        
        // Ambil data package dari controller dengan ID 150 (package template)
        $Package = $this->useModels('Role/Control', 'packageById', [150]);
        $categoryOptions = $this->useModels('Role/Select', 'option', ['Status']);
         $this->assignBlocks([
            'category' =>  $categoryOptions
        ]);
        // Set data pengguna untuk template
        $this->setData($user);
        $this->assignVar('keywords', $Package[0]['keywords']);
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
                $this->useModels('Role/Control', 'packageAdd', [$result['data'], $params['resource_id']]); 
                 //$this->redirectBack('/user');
                // Redirect kembali ke halaman user
        
               $this->redirect($params['page_index'].'/account');
            }
     
            // Handle form response untuk kasus error
            $clearValues = $result['success'] ?? false;
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan error state ke session
            $this->setState('form_add', array_merge($templateVars, $result));
            $this->redirect($params['current_url']);
        }
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
