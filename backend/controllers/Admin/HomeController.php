<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * IndexController - Simplified dengan 2 method saja
 */
class HomeController extends NexaController
{

    private function track($params){
       
        return $this->routerAction($params,[
         'exadd'=>'add'
        ]);

    }



    public function index(array $params = []): void
    {
            // Get JS controller data
           $Ret = $this->refModels('Office');  
           $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
           $this->setJsController([
             ...$jsData
           ]);
          
    }



    // public function visitor(array $params = []): void{
    //      // $this->useVisitor('Dashboard');
    //  }
   

   public function visit(array $params = []){
     $this->useVisitor($params);
     return $params;
    }
   public function analytic(array $params = []){
      $visit = $this->useModels('Role/Visitor', 'analytic',[]);
     return $visit;
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
