<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;

/**
 * ExampleController - Demonstrates NexaJs usage
 * Shows how to send data from PHP to JavaScript
 */
class IndexController extends NexaController
{
    /**
     * Example page with dynamic data sent to JavaScript
     */
    public function index(array $params = []): void
    {
          $Meta = $this->useData('Meta', 'tags', [$params]);
          $this->nexaVars($Meta);
          // $raw = $this->Storage('news')->orderBy('id', 'desc')->limit(3)->get();
          // $this->assignBlock('related', $raw); 
          // $this->setJsController([]);
    }


   
      //  public function FetchEvents(array $params = []){
      //        $this->eventsAccess($params);
      //  }

      //  public function FetchControllers(){
      //     return $this->eventsControllers();
      //  }


      // public function FetchModels(){
      //      $this->eventsModel();
      // }
    
} 