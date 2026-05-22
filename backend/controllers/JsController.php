<?php
declare(strict_types=1);
namespace App\Controllers;

use App\System\Helpers\NexaJs;

/**
 * NexaJsController - Bridge controller untuk mengakses NexaJs
 * Menangani rute /app/main.js dan rute app lainnya
 */
class JsController extends NexaJs
{
    /**
     * Constructor - memanggil parent constructor tanpa parameter
     */
    public function __construct()
    {
        // Panggil parent constructor tanpa parameter
        parent::__construct();
    }

    /**
     * Default index method - memanggil index dari NexaJs
     * 
     * @param array $params Parameter dari URL
     * @return void
     */
    public function index($params = null): void
    {
        // Handle both main.js and min.js requests
        if ($params === 'main.js' || $params === 'min.js' || empty($params)) {
            // Always call main() for JavaScript requests
            $this->main();
            return;
        }

        // For any other .js files, try to find specific method
        if (str_ends_with($params, '.js')) {
            $methodName = str_replace('.js', '', $params);
            if (method_exists($this, $methodName)) {
                call_user_func([$this, $methodName]);
                return;
            }
        }

        // If we get here, call main() as fallback
        $this->main();
    }
 
} 