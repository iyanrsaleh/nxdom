<?php
declare(strict_types=1);
namespace App\Controllers;
use App\System\NexaController;
use App\System\Dom\NexaDom;

/**
 * FileController - File handling dengan 2 method utama
 */
class FileController extends NexaController
{





    /**
     * Default method - Show file index page
     */
    public function index($params = []): void
    {
   
         // Satu Baris dari Tatiye
    }
    /*
    |--------------------------------------------------------------------------
    | Initializes EventLoad 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public  function eventload($params = []): void
    {
      
           $this->JsEventload();
        
    }

    public  function eventMarkdownload($params = []): void
    {
      
           $this->JsMarkdownload ();
        
    }
 
    /* and class EventLoad */
    
} 

