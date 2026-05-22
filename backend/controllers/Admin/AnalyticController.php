<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * AnalyticController - Analytics dashboard
 */
class AnalyticController extends NexaController
{




    /**
     * Default method - Show analytic index page
     */
    public function index(array $params = []): void
    {
        if ($this->mapping()->handleRouting($params['mapping'] ?? '')) {
            return; // Route sudah ditangani oleh resource mapping
        }
        

         $visit = $this->useModels('Role/Visitor', 'analytic',[]);
       
         // Format bulan dan tahun tertentu
          $bulanTahunTertentu = $this->Strings->getMonthYear(); // Misalnya, Anda bisa mengubah logika di dalam method untuk menerima parameter tanggal

         $this->setData([
            'yd'                  =>str_replace(' ', ', ', $bulanTahunTertentu),
            'hd'                  =>$this->Strings->getDay().','.$this->Strings->getMonth(),
            'top_pages'                  =>0,
            'browsers'                   =>0,
            'devices'                    =>0,
            'total_visitors'        =>$visit['total_visitors'],
            'total_page_views'      =>$visit['total_page_views'],
            'unique_page_views'     =>$visit['status_index']['statusIndex'],
            'maxTrafik'             =>$visit['status_index']['maxStatus'],
            'online_visitors'       =>$visit['online_visitors'],
            ...$params
            ]);

        // Set JS controller data and verify
        $this->setJsController($visit['browser']);
        $this->assignBlocks([
            'maxSeting'     => $visit['maxSeting'],
            'trafikuser'     => $visit['trafikUser'],
            'perangkat'     => $visit['perangkat'],
            'populer'     => $visit['page_populer'],
        ]);

    }
    public function bersihkan(array $params = []): void
    {

        
        $this->useModels('Role/Visitor', 'tabelDelete',[]);
        $this->redirect($params['page_home']);
        // code...
    }

   
} 
