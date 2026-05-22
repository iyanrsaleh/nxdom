<?php
namespace App\Controllers\Admin\Example;
use App\System\NexaController;

/**
 * UploadController - File upload handler
 */
class UploadController extends NexaController
{

    private function track($params){
       
        return $this->routerAction($params,[
         'exadd'=>'add'
        ]);
    }



  
    /**
     * Default method - Show upload index page with form
     */
    public function index(array $params = []): void
    {
 


        // // Debug information (only for desktop)
        $this->setValue(['title','deskripsi','files']);
        $templateVars = $this->getState('form_add', $params);
        $this->nexaVars($templateVars);
       //$this->dump($params);
        $this->clearState('form_add');



        $form = $this->createForm()
            ->fields([
                'title'       => 'Name|3|Nama minimal 3 karakter',
                'deskripsi'   => 'Name|3|Alamat minimal 3 karakter',
                'files'       => 'FileOptional|2|Upload any supported file type'
            ])
             ->setUpload([
                 'maxSize' => '15MB',
                 'thumbnail' => ['150x150','161x161','300x200','400x300', '1200x628'],
                 'allowedExtensions' => [
                    'pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls', 'xlsm',
                    'json', 'csv',
                    'pdf', 'rtf', 'odt', 'ods', 'odp',
                    'jpg', 'jpeg', 'png', 'gif', 'webp'
                 ],
                 'thumbnailCropMode' => 'crop'
             ])
            ->setSuccess('Berhasil memperbaharui Akun')
            ->setError('Mohon perbaiki kesalahan berikut');

        $result = $form->process();

     
        // Clear field values if form was successful
        if ($this->isPost()) {
           $clearValues = $result['success'] ?? false;
           if ($result['success']) {
               $simpan=array(
                   "userid"=>$params['uid'],
                   ...$result['data']
               );
               $this->useModels('Drive/files', 'upload', [$simpan]);
           }
            $templateVars = $form->Response($result, $clearValues);
            $this->setState('form_add',$templateVars);
            $this->redirect($params['page_index']);
        }

    }



   
} 
