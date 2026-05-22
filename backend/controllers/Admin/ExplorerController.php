<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * ExplorerController - File/content explorer
 */
class ExplorerController extends NexaController
{

      




   public function index(array $params = []): void
    {
       $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
       $this->setJsController($jsData);   
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
