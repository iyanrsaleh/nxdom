<?php
namespace App\Controllers\Admin\Example;
use App\System\NexaController;

/**
 * StandardControllerNative - Contoh Controller Standar Native
 */
class StandardControllerNative extends NexaController
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
 
         ]);





        // Menggunakan NexaState useState untuk session management
        $templateVars = $this->getState('form_add', $this->track($params));
        
        // Assign variables to template
        $this->nexaVars(array_merge($templateVars));
        $this->dump($templateVars);
        
        // Clear form state after use
        $this->clearState('form_add');
    }
    
    /**
     * Add method - Menampilkan hasil POST dari index.html
     */
    public function add(array $params = []): void
    {
        $page = $this->track($params);
        
        $form = $this->createForm()
            ->fields([
                'nama'    => 'Name|3|Nama minimal 3 karakter',
                'deskripsi'  => 'Name|3|Alamat minimal 3 karakter',
            ])
            ->setSuccess('Berhasil memperbaharui Akun')
            ->setError('Mohon perbaiki kesalahan berikut');

        $result = $form->process();

     
        // Clear field values if form was successful
        $clearValues = $result['success'] ?? false;
        $templateVars = $form->Response($this->track(array_merge($params, $result)), $clearValues);

        // Save template variables to session state
        $this->setState('form_add', array_merge($templateVars, $result));

        // Redirect to index page
        $this->redirect($page['page_index']);
        // Redirect ke index
       
    }
} 
