<?php
namespace App\Controllers\Admin\Example;
use App\System\NexaController;

/**
 * IndexController - Simplified dengan 2 method saja
 */
class CheckoutController extends NexaController
{




    /**
     * Default method - Show example index page with form
     */
    public function index(array $params = []): void
    {
       $baru=  $this->useModels('Product', 'Baru', [6]);
    
       
       // Get session and user ID
       // $session = $this->getSession();
       // $userId = $session->getUserId();
       
       // // Get cart count (Pending orders)
       // $cartCount = 0;
       // if (!empty($userId)) {
       //     $orderData = $this->Storage('orders')
       //                ->where('buyer_id', (string)$userId)
       //                ->where('status', 'Pending')
       //                ->count();
       //     $cartCount = $orderData ? (int)$orderData : 0;
       // }
       
       // Get cart count from OrderController
       // $keranjang = $this->refParams('Product/Order')->cart();
       // $this->nexaBlock('keranjang', $keranjang);


       
       // $orderController = $this->refParams('Product/Order');
       // $cartHtml = $orderController ? $orderController->cartCount() : '';

       // $this->assignVars([
       //     'cart' => $cartHtml,
       // ]);
       // $this->redirect($params['slug'].'/home');


        $produk = [
            [
                "id" => 101,
                "nama" => "Laptop",
                "harga" => 7500000,
                "item" => [
                    ["kode" => "LP-01", "warna" => "Hitam", "stok" => 10],
                    ["kode" => "LP-02", "warna" => "Silver", "stok" => 5],
                    ["kode" => "LP-03", "warna" => "Silver", "stok" => 5]
                ]
            ],
            [
                "id" => 102,
                "nama" => "Mouse",
                "harga" => 150000,
                "item" => [
                    ["kode" => "MS-01", "warna" => "Hitam", "stok" => 25],
                ]
            ]
        ];

        

        // FITUR BARU: Assign nested blocks untuk array bersarang
        


    }
    

 
} 
