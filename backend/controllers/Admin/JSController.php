<?php
namespace App\Controllers\Admin;
use App\System\NexaController;
use App\Controllers\MainController;

/**
 * Controller - Helper class untuk menyiapkan data JavaScript controller
 * Mengembalikan data yang siap digunakan untuk setJsController()
 */
class JSController extends NexaController
{



    public function index(array $params = []): array
    {
      
        // Set JavaScript controller data untuk akses di frontend
        // Data ini akan tersedia di NEXA.controllers.data dan bisa diakses di browser console
       return  self::bankData($params); 
     
    }
 




    /**
     * Default index method - mengembalikan data untuk JavaScript controller
     * 
     * @return array Data untuk setJsController
     */
    public function bankData(array $params = []): array
    {

        $jsData = (new MainController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index($params);
        // Set JavaScript controller data untuk akses di frontend
        // Data ini akan tersedia di NEXA.controllers.data dan bisa diakses di browser console
       return  $jsData ;
     
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