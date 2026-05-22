<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * ApplicationsController - Aplikasi management
 */
class ApplicationsController extends NexaController
{

      
    /**
     * Default method - Show applications index page
     */
    public function index(array $params = []): void
    {
          $Ret = $this->refModels('Office');  
          $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
          $this->setJsController([
            'tabel'        => $Ret->tablesRet(),
            ...$jsData
          ]);
        
    }

    

     public function metaData(array $params = []){
         $Ret = $this->refModels('Office');  
         $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
         return[
           'tabel'        => $Ret->tablesRet(),
           ...$jsData
         ];
     }

     public function metaDataID(array $params = []){

         $Ret = $this->refModels('Office');  
         $getUserId=$params['id'];
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


         $pckg = $this->Storage('controllers')
         ->select(["appname", "appid", "appicon","version"])
         ->where("pintasan", '1')
         ->where("userid",$getUserId)
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
           'pckg'          => $pckg,
           'upaccess'     => $acupdate,
           'territory'    => $territory,
           'appname'      => $appname,
           'assets'       => $this->url('/assets'),
           // 'name'         => $getUserId,
           'appversion'   => $version,
           'token_status' => $status,
           'tabel'        => $Ret->tablesRet(),
        ]; 
     
    }







     public function accessUser(array $status = []){
     return [
            'status' => 'success',
            'message' => 'Test data  successfully',
            'timestamp' => time(),
            'data' => $status,
        ];

         
     }




     public function metaDataPublic(array $params = []){
         $Ret = $this->refModels('Office');  
         $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
         return[
           ...$jsData
         ];
     }

      
   // const userAgen = await NXUI.Storage().package('applications').metaData(1);
   // NEXA.controllers.data=userAgen.data;



      public function Buckets(array $params = []){
         $jsData = (new JSController($this->getTemplateInstance(), $this->getDeviceLayouts()))->index();
         return $jsData;
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
