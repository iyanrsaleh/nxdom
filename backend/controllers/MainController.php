<?php
namespace App\Controllers;
use App\System\NexaController;

/**
 * Controller - Helper class untuk menyiapkan data JavaScript controller
 * Mengembalikan data yang siap digunakan untuk setJsController()
 */
class MainController extends NexaController
{



    public function index(): array
    {

        // Set JavaScript controller data untuk akses di frontend
        // Data ini akan tersedia di NEXA.controllers.data dan bisa diakses di browser console
       return  $this->bankData(); 
     
    }
 




    /**
     * Default index method - mengembalikan data untuk JavaScript controller
     * 
     * @return array Data untuk setJsController
     */
    public function bankData(): array
    {

         $getUserId=$this->session->getUserId() ?? 1;
         $RealName=$this->session->getUserRealName() ?? 'User';
         $Avatar=$this->session->getUserAvatar() ?? 'avatar/pria.png';

         $System=$this->Storage('nexa_office')
         ->select(['data_key as updated', 'updated_at'])
         ->where('data_type','System')
          ->orderBy('id', 'DESC')
         ->first();

          $setApps= $this->Storage('nexa_office')
           ->select(['title','version','description','data_value AS properti'])
          ->where('data_type', 'Apps')
          ->first();


         $appname=$setApps['title']??'NexaUI';
         $version=$setApps['version']??'1.0.0';
         $user = $this->Storage('user')
         ->select(["nama", "token", "email", "kecamatan", "desa"])
         ->where("id", $getUserId)
         ->first();
          if ($user && isset($user['token']) && $user['token']) {
              $status =1;
          } else {
              $status = 0;
          }

  

         $access = $this->Storage('controllers')
         ->select(["acdelete","acpublik","approval","pintasan","acupdate","acmenu", "label AS variable","kecamatan","desa","acinsert"])
         ->where("categori","Accses")
         ->where("userid", $getUserId)
         ->get();

         // Initialize arrays
         $accessData = [];
         $acpublik = [];
         $pintasan = [];
         $approval = [];
         $acupdate = [];
         $acmenu = [];
         $kecamatan = [];
         $territory = [];
         $accessAdd = [];
         
         // Process access data in a single loop
         if (is_array($access) && count($access) > 0) {
             foreach ($access as $item) {
                 $variable = $item['variable'];
                 $acmenu[$variable] = $item['acmenu'] ?? false;
                 $accessData[$variable] = $item['acdelete'] ?? false;
                 $accessAdd[$variable]  = $item['acinsert'] ?? false;
                 $acpublik[$variable]   = $item['acpublik'] ?? false;
                 $pintasan[$variable]   = $item['pintasan'] ?? false;
                 $acupdate[$variable]   = $item['acupdate'] ?? false;
                 $approval[$variable]   = $item['approval'] ?? false;
                 $kecamatan[$variable]  = ($item['kecamatan'] == 1) ? ($user['kecamatan'] ?? false) : false;
                 $territory[$variable] = [
                    'kecamatan' => $kecamatan[$variable],
                    'desa' => ($item['desa'] == 1) ? ($user['desa'] ?? false) : false
                ];
             }
         } 
      
        // Set JavaScript controller data untuk akses di frontend
        // Data ini akan tersedia di NEXA.controllers.data dan bisa diakses di browser console
       return  [
           'version'      => $System,
           'apps'         => $setApps,
           'redaccess'    => $accessData,
           'accessData'   => $acmenu,
           'accessAdd'    => $accessAdd,
           'access'       => $acpublik,
           'approval'     => $approval,
           'pintasan'     => $pintasan,
           'upaccess'     => $acupdate,
           'territory'    => $territory,
           'appname'      => $appname,
           'assets'       => $this->url('/assets'),
           'name'         => $getUserId,
           'username'     => $RealName,
           'useremail'    => $user['email'] ?? '',
           'avatar'       => $this->url('/assets/drive/'.$Avatar),
           'appversion'   => $version,
           'token_status' => $status,
        ]; 
     
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