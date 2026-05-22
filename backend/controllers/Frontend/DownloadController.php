<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;

/**
 * ExampleController - Demonstrates NexaJs usage
 * Shows how to send data from PHP to JavaScript
 */
class DownloadController extends NexaController
{
    /**
     * Example page with dynamic data sent to JavaScript
     */
    public function index(array $params = []): void
    {
        $requestParams = $this->paramsKeys(); 
         $token = $requestParams['token'] ?? '';
         $this->dump($token);
         $dsx = $this->Download->processDownload($token);
         if (!$dsx) {
             $this->redirect($params['base_url'] ?? '/');
             return;
         }
    }

    /**
     * Example with real-time data updates
     */
   
} 