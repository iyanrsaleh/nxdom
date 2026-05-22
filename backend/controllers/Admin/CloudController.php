<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * ExampleController - Contoh Form Handler yang Benar
 */
class CloudController extends NexaController
{



    /**
     * Index - Menampilkan form
     */
    public function index(array $params = []): void
    {

    }


    /**
     * Add - Memproses form submission updat profil user
     */
    public function avatar($data = []): array
    {
        // Handle checkbox array SEBELUM validasi
        // Convert hobi[] array menjadi string
      return $data;
    }

    /**
     * Add - Memproses form submission menabah file 
     */
    public function add($data = []): array
    {
        // Handle checkbox array SEBELUM validasi
        // Convert hobi[] array menjadi string
      return $data;
    }

    /**
     * Add - Memproses form submission megganti file
     */
    public function upd($data = []): array
    {
        // Handle checkbox array SEBELUM validasi
        // Convert hobi[] array menjadi string
      return $data;
    }

    /**
     * Add - Memproses form submission megganti file
     */
    public function del($data = []): array
    {
        // Handle checkbox array SEBELUM validasi
        // Convert hobi[] array menjadi string
      return $data;
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
