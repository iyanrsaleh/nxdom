<?php
namespace App\Controllers\Admin\Example\Form;
use App\System\NexaController;

/**
 * ExampleFormController - Simplified dengan 2 method saja
 */
class ExampleFormController extends NexaController
{

    private function track($params){
       
        return $this->routerAction($params,[
         'exadd'=>'add'
        ]);
    }



    /**
     * Default method - Show example index page with form
     */
    public function index(array $params = []): void
    {
       
        $this->setData([
             // Initialize empty error messages for profile form
            'info_message'=>'',
            'nama_value'=>'',
            'deskripsi_value'=>'',
            'errors_nama'=>'',
            'errors_deskripsi'=>'',
            ...$this->track($params)
 
         ]);



    }
    
    /**
     * Add method - Menampilkan hasil POST dari index.html
     */
    public function add(array $params = []): void {

        $page = $this->track($params);
        $form = $this->createForm()
            ->fields([
                'nama'        =>'Name|3|Nama minimal 3 karakter',
                'deskripsi'   =>'Name|3|Alamat minimal 3 karakter',
            ])
            ->setAjax(true)  // 🚀 Enable AJAX auto-handling
            //->setRedirect($page['page_index'])  // Set redirect URL
            ->setSuccess('Berhasil memperbaharui Akun')
            ->setError('Mohon perbaiki kesalahan berikut');

         $result = $form->process();
        // For non-AJAX requests, handle manually (fallback)
        if ($result['success']) {
        // SIMPAN KE MODELS
        }
    }
} 
