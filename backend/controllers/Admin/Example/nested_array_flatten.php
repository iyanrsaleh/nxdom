<?php
namespace App\Controllers\Admin\Example;
use App\System\NexaController;

/**
 * ============================================================================
 * DOKUMENTASI: CARA MEMBUAT ARRAY BERSARANG (NESTED ARRAY) DENGAN FLATTEN
 * ============================================================================
 * 
 * Tutorial ini menjelaskan cara menampilkan data nested array di template
 * menggunakan teknik FLATTEN STRUCTURE (menggabungkan parent dan child 
 * dalam satu array dengan flag pembeda).
 * 
 * @author NexaFramework
 * @version 1.0
 */
class nested_array_flatten extends NexaController
{
    /**
     * ========================================================================
     * CONTOH 1: ARRAY BERSARANG SEDERHANA (STRUKTUR AWAL)
     * ========================================================================
     * 
     * Ini adalah struktur data bersarang yang TIDAK BISA langsung digunakan
     * di template NexaDom karena sistem template tidak support nested blocks.
     */
    public function contoh_array_bersarang()
    {
        // ❌ STRUKTUR INI TIDAK BISA LANGSUNG DIGUNAKAN
        $produk = [
            [
                "id" => 101,
                "nama" => "Laptop",
                "harga" => 7500000,
                "item" => [  // ← Array di dalam array (nested)
                    ["kode" => "LP-01", "warna" => "Hitam", "stok" => 10],
                    ["kode" => "LP-02", "warna" => "Silver", "stok" => 5],
                    ["kode" => "LP-03", "warna" => "Putih", "stok" => 3]
                ]
            ],
            [
                "id" => 102,
                "nama" => "Mouse",
                "harga" => 150000,
                "item" => [  // ← Array di dalam array (nested)
                    ["kode" => "MS-01", "warna" => "Hitam", "stok" => 25],
                    ["kode" => "MS-02", "warna" => "Putih", "stok" => 15]
                ]
            ]
        ];
        
        // ❌ INI TIDAK AKAN BEKERJA:
        // $this->nexaBlock('produk', $produk);
        // Karena nexaBlock tidak bisa handle nested array secara otomatis
        
        return $produk;
    }
    
    /**
     * ========================================================================
     * CONTOH 2: FLATTEN STRUCTURE - SOLUSI YANG BENAR
     * ========================================================================
     * 
     * Kita "ratakan" (flatten) array bersarang menjadi satu array linear
     * dengan menambahkan FLAG PEMBEDA untuk membedakan jenis data.
     * 
     * KONSEP:
     * - Parent Header (is_parent_header = true)
     * - Child Items (is_child_item = true)
     * - Parent Footer (is_parent_footer = true)
     */
    public function contoh_flatten_structure()
    {
        // Data awal (nested)
        $produkNested = [
            [
                "id" => 101,
                "nama" => "Laptop",
                "harga" => 7500000,
                "item" => [
                    ["kode" => "LP-01", "warna" => "Hitam", "stok" => 10],
                    ["kode" => "LP-02", "warna" => "Silver", "stok" => 5],
                    ["kode" => "LP-03", "warna" => "Putih", "stok" => 3]
                ]
            ],
            [
                "id" => 102,
                "nama" => "Mouse",
                "harga" => 150000,
                "item" => [
                    ["kode" => "MS-01", "warna" => "Hitam", "stok" => 25],
                    ["kode" => "MS-02", "warna" => "Putih", "stok" => 15]
                ]
            ]
        ];
        
        // ✅ FLATTEN: Gabungkan parent dan child dalam satu array
        $produkFlat = [];
        
        foreach ($produkNested as $index => $parent) {
            // 1. TAMBAHKAN PARENT HEADER
            $produkFlat[] = [
                'is_parent_header' => true,  // ← FLAG: ini adalah header
                'parent_index' => $index,
                'id' => $parent['id'],
                'nama' => $parent['nama'],
                'harga' => number_format($parent['harga'], 0, ',', '.'),
                'has_items' => !empty($parent['item'])
            ];
            
            // 2. TAMBAHKAN CHILD ITEMS
            if (!empty($parent['item'])) {
                foreach ($parent['item'] as $child) {
                    $produkFlat[] = [
                        'is_child_item' => true,  // ← FLAG: ini adalah item
                        'parent_index' => $index,
                        'kode' => $child['kode'],
                        'warna' => $child['warna'],
                        'stok' => $child['stok']
                    ];
                }
            } else {
                // 3. TAMBAHKAN NO DATA (jika tidak ada items)
                $produkFlat[] = [
                    'is_no_data' => true,  // ← FLAG: tidak ada data
                    'parent_index' => $index
                ];
            }
            
            // 4. TAMBAHKAN PARENT FOOTER (penutup)
            $produkFlat[] = [
                'is_parent_footer' => true,  // ← FLAG: ini adalah footer
                'parent_index' => $index
            ];
        }
        
        // ✅ SEKARANG BISA DIGUNAKAN DI TEMPLATE
        $this->nexaBlock('produk', $produkFlat);
        
        /* 
         * HASIL ARRAY FLAT:
         * [
         *   0 => ['is_parent_header' => true, 'nama' => 'Laptop', ...],
         *   1 => ['is_child_item' => true, 'kode' => 'LP-01', ...],
         *   2 => ['is_child_item' => true, 'kode' => 'LP-02', ...],
         *   3 => ['is_child_item' => true, 'kode' => 'LP-03', ...],
         *   4 => ['is_parent_footer' => true, ...],
         *   5 => ['is_parent_header' => true, 'nama' => 'Mouse', ...],
         *   6 => ['is_child_item' => true, 'kode' => 'MS-01', ...],
         *   7 => ['is_child_item' => true, 'kode' => 'MS-02', ...],
         *   8 => ['is_parent_footer' => true, ...]
         * ]
         */
    }
    
    /**
     * ========================================================================
     * CONTOH 3: TEMPLATE UNTUK FLATTEN STRUCTURE
     * ========================================================================
     * 
     * File: templates/produk.html
     * 
     * <!-- NEXA produk -->
     * 
     * {if is_parent_header}
     * <div class="product-card">
     *     <h3>{nama}</h3>
     *     <p>Harga: Rp {harga}</p>
     *     <table class="ui table">
     *         <thead>
     *             <tr>
     *                 <th>Kode</th>
     *                 <th>Warna</th>
     *                 <th>Stok</th>
     *             </tr>
     *         </thead>
     *         <tbody>
     * {endif}
     * 
     * {if is_child_item}
     *             <tr>
     *                 <td>{kode}</td>
     *                 <td>{warna}</td>
     *                 <td>{stok}</td>
     *             </tr>
     * {endif}
     * 
     * {if is_no_data}
     *             <tr>
     *                 <td colspan="3">Tidak ada item</td>
     *             </tr>
     * {endif}
     * 
     * {if is_parent_footer}
     *         </tbody>
     *     </table>
     * </div>
     * {endif}
     * 
     * <!-- END produk -->
     */
    
    /**
     * ========================================================================
     * CONTOH 4: IMPLEMENTASI REAL - ORDER TRACKING
     * ========================================================================
     * 
     * Contoh nyata dari OrderController untuk menampilkan tracking order
     */
    public function contoh_order_tracking()
    {
        // Simulasi data orders
        $orders = [
            [
                'order_number' => 'ORD-001',
                'status' => 'Paid',
                'tracking' => [
                    ['status' => 'Confirmasi', 'message' => 'Pesanan dikonfirmasi', 'time' => '09:00'],
                    ['status' => 'DeliveryStatus', 'message' => 'Sedang dikirim', 'time' => '10:30'],
                    ['status' => 'Complete', 'message' => 'Pesanan diterima', 'time' => '14:00']
                ]
            ],
            [
                'order_number' => 'ORD-002',
                'status' => 'Pending',
                'tracking' => []  // Tidak ada tracking
            ]
        ];
        
        // FLATTEN STRUCTURE
        $trackingData = [];
        
        foreach ($orders as $orderIndex => $order) {
            $trackingItems = $order['tracking'];
            
            // 1. ORDER HEADER
            $trackingData[] = [
                'is_order_header' => true,
                'order_index' => $orderIndex,
                'id' => $order['order_number'],
                'status' => $order['status'],
                'has_data' => !empty($trackingItems)
            ];
            
            // 2. TRACKING ITEMS
            if (!empty($trackingItems)) {
                foreach ($trackingItems as $item) {
                    $trackingData[] = [
                        'is_tracking_item' => true,
                        'order_index' => $orderIndex,
                        'status' => $item['status'],
                        'message' => $item['message'],
                        'time' => $item['time']
                    ];
                }
            } else {
                // 3. NO DATA
                $trackingData[] = [
                    'is_no_data' => true,
                    'order_index' => $orderIndex
                ];
            }
            
            // 4. ORDER FOOTER
            $trackingData[] = [
                'is_order_footer' => true,
                'order_index' => $orderIndex
            ];
        }
        
        // Assign ke template
        $this->nexaBlock('tracking', $trackingData);
    }
    
    /**
     * ========================================================================
     * RINGKASAN: LANGKAH-LANGKAH MEMBUAT FLATTEN STRUCTURE
     * ========================================================================
     * 
     * STEP 1: Siapkan array nested (parent dengan child)
     * STEP 2: Buat array kosong untuk hasil flatten
     * STEP 3: Loop parent, untuk setiap parent:
     *         a. Tambahkan parent header (dengan flag is_parent_header)
     *         b. Loop child, tambahkan setiap child (dengan flag is_child_item)
     *         c. Jika tidak ada child, tambahkan no_data (dengan flag is_no_data)
     *         d. Tambahkan parent footer (dengan flag is_parent_footer)
     * STEP 4: Assign hasil flatten ke nexaBlock()
     * STEP 5: Di template, gunakan {if flag} untuk membedakan jenis data
     * 
     * ========================================================================
     * KEUNTUNGAN FLATTEN STRUCTURE:
     * ========================================================================
     * ✅ Kompatibel dengan NexaDom template system
     * ✅ Hanya butuh SATU nexaBlock() call
     * ✅ Mudah di-maintain dan di-debug
     * ✅ Fleksibel untuk berbagai jenis nested data
     * ✅ Performa lebih baik (tidak perlu nested loop di template)
     * 
     * ========================================================================
     * TIPS:
     * ========================================================================
     * 1. Gunakan nama flag yang jelas: is_header, is_item, is_footer
     * 2. Tambahkan parent_index untuk tracking relasi parent-child
     * 3. Tambahkan flag has_data untuk conditional rendering
     * 4. Selalu tambahkan no_data handling untuk UX yang baik
     * 5. Gunakan footer untuk menutup HTML tags yang dibuka di header
     * 
     * ========================================================================
     */
    
    /**
     * Method untuk render halaman contoh
     */
    public function index(array $params = []): void
    {
        // Jalankan contoh flatten structure
        $this->contoh_flatten_structure();
        
        // Assign info ke template
        $this->assignVars([
            'title' => 'Contoh Nested Array dengan Flatten Structure',
            'description' => 'Tutorial lengkap cara menampilkan nested array di NexaDom'
        ]);
    }
}
