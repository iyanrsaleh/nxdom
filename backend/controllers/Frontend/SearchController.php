<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;

use App\System\NexaController;

/**
 * ProductController - Handle product routes and product details
 * 
 * Routes:
 * - /product → index page (product catalog)
 * - /product/makana → makana product list
 * - /product/makana/{product-slug} → product detail page
 * 
 * Examples:
 * - /product/makana/nasi-goreng → Shows detail for "nasi-goreng"
 * - /product/makana/sate-ayam → Shows detail for "sate-ayam"
 */
class SearchController extends NexaController
{
    /**
     * Handle product detail page (reusable for all product types)
     * 
     * @param string $productSlug Product slug from URL
     * @param string $dataClass Data class name (Retail, MakanMinum, etc.)
     * @return void
     */
    private function handleProductDetail(string $productSlug, string $dataClass): void
    {
        // Parse slug to get ID and name
        $slugInfo = $this->useData($dataClass, 'parseSlug', [$productSlug]);
        
        // Get product detail
        $productData = $this->useData($dataClass, 'detail', [$slugInfo['id']]);
        
        // If product not found, redirect to list page
        if (empty($productData)) {
            // Convert class name to URL path
            $categoryPath = strtolower(str_replace('MakanMinum', 'makan_minum', $dataClass));
            // Use url() method to include base path
            $this->redirect($this->url('/product/' . $categoryPath));
            return;
        }
        
        // Assign variables
        $this->assignVars([
            'page_title' => $slugInfo['display_name'] . ' - Product Detail',
            'page_description' => 'Detail produk ' . $slugInfo['display_name'],
            'current_page' => 'product-detail',
            'product_slug' => $productSlug,
            'product_name' => $slugInfo['display_name'],
            ...$productData,
            'is_public_page' => true,
        ]);
        
        // Render detail template
        $this->divert();
        $this->render('product/detail');
        exit;
    }

    public function index(array $params = []): void
    {
     
   
        $secondSegment = $this->getSlug(2);
        
        // Handle product detail page
        if (!empty($secondSegment) && $secondSegment !== 'pages') {
            $this->handleProductDetail($secondSegment, 'Search');
            return;
        }
        
        // Handle product list page
        $data = $this->useData('Search', 'list', [$params]);
        
        // Assign NEXA block untuk looping products
        $this->nexaBlock('food', $data['products']);
        
        // Assign variables ke template
        $this->assignVars([
            'page_title' => 'Makan Minum - Product Page',
            'pagination' => $data['pagination'],
            'records' => $data['total_records'],
            'crecords' => $data['current_records'],
            'device' => $data['device_type'],
            'search_query' => $data['search_query'],
            'page_description' => 'Browse our Makan Minum products',
            'current_page' => 'makan_minum',
            'is_public_page' => true,
        ]);
    }

}
