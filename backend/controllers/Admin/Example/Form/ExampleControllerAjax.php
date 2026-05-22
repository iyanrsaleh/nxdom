<?php
namespace App\Controllers\Admin\Example\Form;
use App\System\NexaController;

/**
 * ExampleControllerAjax - Simplified dengan 2 method saja
 */
class ExampleControllerAjax extends NexaController
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
 
       
   
       // $this->dump($params);
       $this->setJsController($params);
        $this->setValue(['nama','deskripsi']);

    }
    /*
    |--------------------------------------------------------------------------
    | Initializes create 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public  function create(array $params = []): void{
         $form = $this->createForm()
           ->setAjax(true)
           ->setCallback(function($data) {
               // This will be called before sending AJAX response
               return $this->useModels('Demo', 'send', [$data]);
           })
           ->fields([
                'nama'        =>'Name|3|Nama minimal 3 karakter',
                'deskripsi'   =>'Name|3|Alamat minimal 3 karakter',
            ])
            ->setSuccess('Berhasil memperbaharui Akun')
            ->setError('Mohon perbaiki kesalahan berikut');

         $result = $form->process();
       
    }
    /* and class create */

   

} 
