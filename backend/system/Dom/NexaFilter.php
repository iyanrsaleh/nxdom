<?php
declare(strict_types=1);
namespace App\System\Dom;
class NexaFilter {
    /**
     * Parse filter string into array of filters with arguments
     */
    public function parseFilters(string $filterString): array {
        $filters = [];
        $parts = explode('|', $filterString);
        foreach ($parts as $part) {
            $filter = trim($part);
            if (empty($filter)) continue;
            
            // Parse filter name and arguments
            if (strpos($filter, ':') !== false) {
                list($name, $args) = explode(':', $filter, 2);
                $args = array_map('trim', explode(',', $args));
            } else {
                $name = $filter;
                $args = [];
            }
            
   
            
            $filters[] = [
                'name' => $name,
                'args' => $args
            ];
        }
        return $filters;
    }

    /**
     * Apply filter to value
     */
    public function Filter($value, string $filterName, array $args = []) {
        // Ensure value is string
        if ($value === null) {
            return '';
        }
        
        // Convert value to string if not already
        if (!is_string($value)) {
            $value = (string)$value;
        }

        switch (strtolower($filterName)) {
            case 'upper':
                return strtoupper($value);
            case 'lower':
                return strtolower($value);
            case 'slug':
                // Replace non-alphanumeric with dash
                $value = preg_replace('/[^a-zA-Z0-9-]/', '-', $value);
                // Remove duplicate dashes
                $value = preg_replace('/-+/', '-', $value);
                // Trim dashes from start and end
                return trim($value, '-');
            case 'first_word':
                // Ambil kata pertama dari string menggunakan explode
                $words = explode(' ', trim($value));
                return $words[0] ?? '';
            case 'more':
                $length = isset($args[0]) ? (int)$args[0] : 100;
                return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
            case 'date':
                if (empty($value)) return '';
                $format = isset($args[0]) ? $args[0] : 'Y-m-d';
                $timestamp = strtotime($value);
                if (!$timestamp) return '';
                
                // Check if format contains Indonesian month patterns
                if (strpos($format, 'M') !== false) {
                    // Replace M with Indonesian short month names
                    $months = [
                        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
                    ];
                    $monthIndex = date('n', $timestamp) - 1;
                    $indonesianMonth = $months[$monthIndex];
                    
                    // Format date normally first, then replace English month with Indonesian
                    $formattedDate = date($format, $timestamp);
                    $englishMonth = date('M', $timestamp);
                    return str_replace($englishMonth, $indonesianMonth, $formattedDate);
                } elseif (strpos($format, 'F') !== false) {
                    // Replace F with Indonesian full month names
                    $months = [
                        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                    ];
                    $monthIndex = date('n', $timestamp) - 1;
                    $indonesianMonth = $months[$monthIndex];
                    
                    // Format date normally first, then replace English month with Indonesian
                    $formattedDate = date($format, $timestamp);
                    $englishMonth = date('F', $timestamp);
                    return str_replace($englishMonth, $indonesianMonth, $formattedDate);
                }
                
                return date($format, $timestamp);
            case 'time_ago':
                if (empty($value)) return '';
                $timestamp = strtotime($value);
                if (!$timestamp) return '';
                return $this->timeAgo($timestamp);
            case 'number_format':
                $decimals = isset($args[0]) ? (int)$args[0] : 0;
                return number_format((float)$value, $decimals);
            case 'currency':
                $currency = isset($args[0]) ? $args[0] : '';
                $decimals = isset($args[1]) ? (int)$args[1] : 0;
                return $currency . ' ' . number_format((float)$value, $decimals);
            case 'capitalize':
                return ucfirst($value);
            case 'nl2br':
                return nl2br($value);
            case 'strip_tags':
                $allowedTags = isset($args[0]) ? $args[0] : '';
                return strip_tags($value, $allowedTags);
            case 'round':
                $precision = isset($args[0]) ? (int)$args[0] : 0;
                return (string)round((float)$value, $precision);
            case 'percent':
                $decimals = isset($args[0]) ? (int)$args[0] : 0;
                return number_format((float)$value, $decimals) . '%';
            case 'escape':
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            case 'html_decode':
                return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            case 'url_encode':
                return urlencode($value);
            case 'encode':
                return base64_encode($value);
            case 'decode':
                return base64_decode($value);
            case 'md5':
                return md5($value);
            case 'trim':
                return trim($value);
            case 'replace':
                $search = isset($args[0]) ? $args[0] : '';
                $replace = isset($args[1]) ? $args[1] : '';
                return str_replace($search, $replace, $value);
            case 'substr':
                $start = isset($args[0]) ? (int)$args[0] : 0;
                $length = isset($args[1]) ? (int)$args[1] : null;
                return substr($value, $start, $length);
            case 'pad':
                $length = isset($args[0]) ? (int)$args[0] : 10;
                $pad = isset($args[1]) ? $args[1] : ' ';
                $type = isset($args[2]) ? strtolower($args[2]) : 'right';
                return $type === 'left' ? 
                    str_pad($value, $length, $pad, STR_PAD_LEFT) : 
                    str_pad($value, $length, $pad, STR_PAD_RIGHT);
            case 'abs':
                return (string)abs((float)$value);
            case 'ceil':
                return (string)ceil((float)$value);
            case 'floor':
                return (string)floor((float)$value);
            case 'rp':
                return 'Rp ' . number_format((float)$value, 0, ',', '.') ;
            case 'decimal_to_rupiah':
                return 'Rp ' . number_format((float)$value, 0, ',', '.') . ',-';
            case 'join':
                $separator = isset($args[0]) ? $args[0] : ', ';
                return is_array($value) ? implode($separator, $value) : $value;
            case 'split':
                $delimiter = isset($args[0]) ? $args[0] : ',';
                return implode(',', explode($delimiter, $value));
            case 'blog_keywords':
                // Daftar kata kunci dipisah koma → beberapa <a> + pemisah nx-news-tag-sep (untuk halaman blog).
                // Arg opsional: awalan URL, default /blog?q=
                $value = trim($value);
                if ($value === '') {
                    return '';
                }
                $prefix = isset($args[0]) && $args[0] !== '' ? (string) $args[0] : '/blog?q=';
                $rawParts = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
                if ($rawParts === false) {
                    return '';
                }
                $parts = [];
                foreach ($rawParts as $p) {
                    $t = trim((string) $p);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
                if ($parts === []) {
                    return '';
                }
                $chunks = [];
                foreach ($parts as $label) {
                    $href = $prefix . rawurlencode($label);
                    $chunks[] = '<a href="'
                        . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                        . '">'
                        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                        . '</a>';
                }
                return implode('<span class="nx-news-tag-sep">,</span>', $chunks);
            case 'blog_keywords_pills':
                // Sama seperti blog_keywords, tanpa koma — untuk pill flex di halaman detail.
                $value = trim($value);
                if ($value === '') {
                    return '';
                }
                $prefix = isset($args[0]) && $args[0] !== '' ? (string) $args[0] : '/blog?q=';
                $rawParts = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
                if ($rawParts === false) {
                    return '';
                }
                $parts = [];
                foreach ($rawParts as $p) {
                    $t = trim((string) $p);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
                if ($parts === []) {
                    return '';
                }
                $chunks = [];
                foreach ($parts as $label) {
                    $href = $prefix . rawurlencode($label);
                    $chunks[] = '<a class="nx-news-detail-tag-pill" href="'
                        . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                        . '">'
                        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                        . '</a>';
                }
                return implode('', $chunks);
            case 'in':
                $timestamp = strtotime($value);
                if (!$timestamp) return '';
                $months = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                ];
                $month = $months[date('n', $timestamp) - 1];
                return date('d', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
            case 'indonesian_date_short':
                $timestamp = strtotime($value);
                if (!$timestamp) return '';
                $months = [
                    'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
                ];
                $month = $months[date('n', $timestamp) - 1];
                return date('d', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
            case 'bulan_indonesia':
                return $this->bulan_indonesia($value);
            case 'bulan_singkat_indonesia':
                return $this->bulan_singkat_indonesia($value);
            case 'hari_indonesia':
                return $this->hari_indonesia($value);
            case 'order_code':
                $orderId = (int)$value;
                $padding = isset($args[0]) ? (int)$args[0] : 4;
                return str_pad((string)$orderId, $padding, '0', STR_PAD_LEFT);
            case 'terbilang':
                return self::terbilang((float)$value);
            case 'terbilang_rupiah':
                return self::terbilangRupiah((float)$value);
            default:
                return $value;
        }
    }

    /**
     * Create URL friendly slug
     */


    /**
     * Format file size to human readable
     */
    private function formatFileSize(float $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber(string $number): string {
        // Remove non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Format Indonesian numbers
        if (str_starts_with($number, '62') || str_starts_with($number, '0')) {
            $number = preg_replace('/^62|^0/', '+62 ', $number);
            return trim(chunk_split($number, 4, ' '));
        }
        
        return $number;
    }

    /**
     * Mask string (e.g. for credit card numbers)
     */
    private function maskString(string $string, string $mask = '*', int $start = 4, int $end = 4): string {
        $length = strlen($string);
        $visibleCount = $start + $end;
        
        if ($length <= $visibleCount) {
            return $string;
        }
        
        $maskLength = $length - $visibleCount;
        $maskedPart = str_repeat($mask, $maskLength);
        
        return substr($string, 0, $start) . $maskedPart . substr($string, -$end);
    }

    /**
     * Convert timestamp to time ago string
     */
    private function timeAgo(int $timestamp): string {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'baru saja';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' menit yang lalu';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' jam yang lalu';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' hari yang lalu';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' bulan yang lalu';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' tahun yang lalu';
        }
    }

    /**
     * Calculate age from birthdate
     */
    private function calculateAge(string $birthdate): int {
        $birth = new \DateTime($birthdate);
        $today = new \DateTime('today');
        return $birth->diff($today)->y;
    }

    /**
     * Get relative time with Indonesian text
     */
    private function getRelativeTime(int $timestamp): string {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'baru saja';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' menit yang lalu';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' jam yang lalu';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' hari yang lalu';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' bulan yang lalu';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' tahun yang lalu';
        }
    }

    /**
     * Format NIK (Nomor Induk Kependudukan)
     */
    private function formatNIK(string $nik): string {
        $nik = preg_replace('/[^0-9]/', '', $nik);
        if (strlen($nik) !== 16) return $nik;
        return implode('.', str_split($nik, 4));
    }

    /**
     * Format NPWP
     */
    private function formatNPWP(string $npwp): string {
        $npwp = preg_replace('/[^0-9]/', '', $npwp);
        if (strlen($npwp) !== 15) return $npwp;
        return substr($npwp, 0, 2) . '.' . 
               substr($npwp, 2, 3) . '.' . 
               substr($npwp, 5, 3) . '.' . 
               substr($npwp, 8, 1) . '-' . 
               substr($npwp, 9, 3) . '.' . 
               substr($npwp, 12, 3);
    }

    public function currency($value): string {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
    


    /**
     * Convert string to uppercase
     */
    public function upper($value): string {
        return is_string($value) ? strtoupper($value) : (string)$value;
    }

    /**
     * Convert string to lowercase
     */
    public function lower($value): string {
        return is_string($value) ? strtolower($value) : (string)$value;
    }

    /**
     * Capitalize first letter of each word
     */
    public function ucwords($value): string {
        return is_string($value) ? ucwords(strtolower($value)) : (string)$value;
    }

    /**
     * Capitalize first letter
     */
    public function ucfirst($value): string {
        return is_string($value) ? ucfirst(strtolower($value)) : (string)$value;
    }

    public function time_ago($value) {
        // Set default timezone if Config class is not available
        if (class_exists('\App\System\Config')) {
            $timezone = \App\System\Config::get('app.timezone', 'Asia/Jakarta');
            date_default_timezone_set($timezone);
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        return $this->timeAgo(strtotime($value));
    }

    public function bulan_indonesia($value) {
        if (empty($value)) return '';
        // Set default timezone if Config class is not available
        if (class_exists('\App\System\Config')) {
            $timezone = \App\System\Config::get('app.timezone', 'Asia/Jakarta');
            date_default_timezone_set($timezone);
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        $timestamp = strtotime($value);
        if (!$timestamp) return '';
        $months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        return $months[date('n', $timestamp) - 1];
    }

    public function hari_indonesia($value) {
        if (empty($value)) return '';
        // Set default timezone if Config class is not available
        if (class_exists('\App\System\Config')) {
            $timezone = \App\System\Config::get('app.timezone', 'Asia/Jakarta');
            date_default_timezone_set($timezone);
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        $timestamp = strtotime($value);
        if (!$timestamp) return '';
        $days = [
            'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
        ];
        return $days[date('w', $timestamp)];
    }

    public function bulan_singkat_indonesia($value) {
        if (empty($value)) return '';
        // Set default timezone if Config class is not available
        if (class_exists('\App\System\Config')) {
            $timezone = \App\System\Config::get('app.timezone', 'Asia/Jakarta');
            date_default_timezone_set($timezone);
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        $timestamp = strtotime($value);
        if (!$timestamp) return '';
        $months = [
            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
        ];
        return $months[date('n', $timestamp) - 1];
    }

    public function encode($value) {
        return base64_encode((string)$value);
    }

    public function decode($value) {
        return base64_decode((string)$value);
    }
    /**
     * Handle default/placeholder values
     * @param mixed $value Current value
     * @param string $default Default value if current is empty
     * @return string
     */
    public function value($value, $default = '') {
        if ($value === null || $value === '' || $value === 'default') {
            return $default;
        }
        return $value;
    }

    /**
     * Convert number to Indonesian words
     * 
     * @param int|float $number Number to convert (float will be converted to int)
     * @return string Indonesian words representation
     */
    public static function terbilang($number): string 
    {
        // Convert to integer if it's a float
        $number = is_float($number) ? intval($number) : $number;
        
        if ($number < 0) {
            return 'Minus ' . self::terbilang(abs($number));
        }
        
        if ($number == 0) {
            return 'Nol';
        }
        
        $ones = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
            'sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas',
            'enam belas', 'tujuh belas', 'delapan belas', 'sembilan belas'
        ];
        
        if ($number < 20) {
            return ucfirst($ones[$number]);
        }
        
        if ($number < 100) {
            $tens = intval($number / 10);
            $remainder = $number % 10;
            
            if ($tens == 2 && $remainder == 0) {
                return 'Dua puluh';
            } elseif ($tens == 2) {
                return 'Dua puluh ' . $ones[$remainder];
            } else {
                $tensWords = ['', '', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'];
                return ucfirst($tensWords[$tens]) . ' puluh' . ($remainder > 0 ? ' ' . $ones[$remainder] : '');
            }
        }
        
        if ($number < 200) {
            $remainder = $number - 100;
            return 'Seratus' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        if ($number < 1000) {
            $hundreds = intval($number / 100);
            $remainder = $number % 100;
            return ucfirst($ones[$hundreds]) . ' ratus' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        if ($number < 2000) {
            $remainder = $number - 1000;
            return 'Seribu' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        if ($number < 1000000) {
            $thousands = intval($number / 1000);
            $remainder = $number % 1000;
            return ucfirst(strtolower(self::terbilang($thousands))) . ' ribu' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        if ($number < 1000000000) {
            $millions = intval($number / 1000000);
            $remainder = $number % 1000000;
            return ucfirst(strtolower(self::terbilang($millions))) . ' juta' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        if ($number < 1000000000000) {
            $billions = intval($number / 1000000000);
            $remainder = $number % 1000000000;
            return ucfirst(strtolower(self::terbilang($billions))) . ' miliar' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
        }
        
        $trillions = intval($number / 1000000000000);
        $remainder = $number % 1000000000000;
        return ucfirst(strtolower(self::terbilang($trillions))) . ' triliun' . ($remainder > 0 ? ' ' . strtolower(self::terbilang($remainder)) : '');
    }

    public static function terbilangRupiah(float $amount): string 
    {
        $integerPart = intval($amount);
        $decimalPart = intval(($amount - $integerPart) * 100);
        
        $result = self::terbilang($integerPart) . ' rupiah';
        
        if ($decimalPart > 0) {
            $result .= ' ' . strtolower(self::terbilang($decimalPart)) . ' sen';
        }
        
        return $result;
    }

    /**
     * Magic method to handle filter calls
     */
    public function __call($name, $arguments) {
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        }
        return $arguments[0] ?? null;
    }
}
