<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;
/**
 * Controller - Helper class untuk menyiapkan data JavaScript controller
 * Mengembalikan data yang siap digunakan untuk setJsController()
 */
class DocsController extends NexaController
{

    /**
     * Default index method - mengembalikan data untuk JavaScript controller
     * 
     * @param string|array $params URL parameters
     * @return void
     */
  public function index(string|array $params = []): void
    {
       $paramsArray = is_array($params) ? $params : ['path' => $params];
       $Meta = $this->useData('Meta', 'tags', [$paramsArray]);
       $this->assignVars($Meta);
    }
    /** templates/theme/docs/storages/index.html — indeks topik template */
  
    
    // /** templates/theme/docs/helper.html — indeks topik template */
    // public function helper(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Helper & NexaDom — indeks dokumentasi template',
    //         'page_description' => 'NexaDom, aset, Nexautility, NexaFilter',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }

    // /** URL lama /docs/utility — template: theme/docs/utility.html (sama isi helper) */
    // public function utility(array $params = []): void
    // {
    //     $this->helper($params);
    // }

    /** templates/theme/docs/nexadom.html */
    // public function nexadom(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Template / NexaDom — variabel, blok, sintaks',
    //         'page_description' => 'NexaDom: variabel, filter pipa, kondisional, blok NEXA',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }

    // /** templates/theme/docs/assets.html */
    // public function assets(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Aset template — CSS, JS, gambar',
    //         'page_description' => 'Variabel assets/ dan path absolut untuk stylesheet, script, gambar',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }

    // /** templates/theme/docs/style.html — Nexautility */
    // public function style(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Style — Nexautility (class → inline CSS)',
    //         'page_description' => 'Nexautility: kelas helper margin, padding, border, warna, display',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }
    // public function storages(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Style — Nexautility (class → inline CSS)',
    //         'page_description' => 'Nexautility: kelas helper margin, padding, border, warna, display',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }

    // /** templates/theme/docs/filters.html — NexaFilter */
    // public function filters(array $params = []): void
    // {
    //     $this->assignVars([
    //         'page_title' => 'Filter — NexaFilter (pipa template)',
    //         'page_description' => 'Daftar filter NexaFilter untuk variabel template',
    //         'current_page' => 'docs',
    //         'is_public_page' => true,
    //     ]);
    // }

} 