<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;
use App\Controllers\MainController;

/**
 * Controller - Helper class untuk menyiapkan data JavaScript controller
 * Mengembalikan data yang siap digunakan untuk setJsController()
 */
class JSController extends NexaController
{

    /**
     * Default index method - mengembalikan data untuk JavaScript controller
     * 
     * @return array Data untuk setJsController
     */
    public function index(): array
    {

        $jsData = (new MainController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
        // Set JavaScript controller data untuk akses di frontend
        // Data ini akan tersedia di NEXA.controllers.data dan bisa diakses di browser console
       return  $jsData; 
     
    }
 
} 