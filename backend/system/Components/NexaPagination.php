<?php
namespace App\System\Components;

use App\System\Helpers\NexaSession;

class NexaPagination {
    
    /**
     * Membuat HTML pagination dengan debug
     * @param int $current Halaman saat ini
     * @param int $total Total halaman
     * @param string $baseUrl URL dasar untuk link pagination
     * @return string HTML pagination
     */
    public function render($current, $total, $baseUrl) {
        // ✅ DEBUG: Log parameter yang diterima
        
        // Initialize NexaSession dengan konfigurasi lengkap
        NexaSession::initialize();
        
        // Get NexaSession instance
        $session = NexaSession::getInstance();
        
        // Simpan halaman aktif ke session dengan nama "page-link" menggunakan NexaSession
        $session->set('page-link', [
            'current' => $current,
            'total' => $total,
            'url' => '?page/'.$current,
            'base_url' => $baseUrl,
            'timestamp' => time()
        ]);
      
        // ✅ Validasi parameter
        if (empty($current) || empty($total) || empty($baseUrl)) {
           
            return '<div class="pagination-error"></div>';
        }
        
        // ✅ Jika hanya 1 halaman, tidak perlu tampilkan pagination
        if ($total <= 1) {
            return '';
        }
        
        $html = '<div class="pagination">';
        // Hitung range halaman yang akan ditampilkan
        $range = 2; // Jumlah halaman di kiri dan kanan halaman aktif
        $start = max(1, $current - $range);
        $end = min($total, $current + $range);

        // Tambahkan tombol First
        if ($current > 1) {
            $html .= '<a href="'.$baseUrl.'1" class="page-link">First</a>';
        }

        // Tambahkan tombol Previous jika tidak di halaman pertama
        if ($current > 1) {
            $html .= '<a href="'.$baseUrl.($current-1).'" class="page-link">Previous</a>';
        }

        // Tampilkan halaman pertama jika start > 1
        if ($start > 1) {
            $html .= '<a href="'.$baseUrl.'1" class="page-link">1</a>';
            if ($start > 2) {
                $html .= '<span class="page-link">...</span>';
            }
        }

        // Tampilkan halaman dalam range
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $current) {
                $html .= '<span class="page-link active">'.$i.'</span>';
            } else {
                $html .= '<a href="'.$baseUrl.$i.'" class="page-link">'.$i.'</a>';
            }
        }

        // Tampilkan halaman terakhir jika end < total
        if ($end < $total) {
            if ($end < $total - 1) {
                $html .= '<span class="page-link">...</span>';
            }
            $html .= '<a href="'.$baseUrl.$total.'" class="page-link">'.$total.'</a>';
        }

        // Tambahkan tombol Next jika tidak di halaman terakhir
        if ($current < $total) {
            $html .= '<a href="'.$baseUrl.($current+1).'" class="page-link">Next</a>';
        }

        // Tambahkan tombol Last
        if ($current < $total) {
            $html .= '<a href="'.$baseUrl.$total.'" class="page-link">Last</a>';
        }

        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get current active page data from session
     * @return array|null
     */
    public function getCurrentPageFromSession(): ?array
    {
        $session = NexaSession::getInstance();
        return $session->getPageItem();
    }
    
    /**
     * Get current page number from session
     * @return int
     */
    public function getCurrentPageNumber(): int
    {
        $pageData = $this->getCurrentPageFromSession();
        return $pageData['current'] ?? 1;
    }
    
    /**
     * Get current page URL from session
     * @return string|null
     */
    public function getCurrentPageUrl(): ?string
    {
        $pageData = $this->getCurrentPageFromSession();
        return $pageData['url'] ?? null;
    }
    
    /**
     * Clear page session data
     * @return void
     */
    public function clearPageSession(): void
    {
        $session = NexaSession::getInstance();
        $session->remove('page-link');
    }
}
