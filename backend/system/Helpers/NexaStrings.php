<?php
namespace App\System\Helpers;

class NexaStrings
{
    private $isIndonesian;

    public function __construct(bool $isIndonesian = true)
    {
        $this->isIndonesian = $isIndonesian;
    }

    /**
     * Text Case Manipulation
     */
    public static function toUpper(string $text): string 
    {
        return mb_strtoupper($text, 'UTF-8');
    }

    public static function toLower(string $text): string 
    {
        return mb_strtolower($text, 'UTF-8');
    }

    public static function toTitle(string $text): string 
    {
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    public static function toSentence(string $text): string 
    {
        $text = mb_strtolower($text, 'UTF-8');
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . 
               mb_substr($text, 1, null, 'UTF-8');
    }

    /**
     * Text Formatting
     */
    public static function trim(string $text, string $characters = " \t\n\r\0\x0B"): string 
    {
        return trim($text, $characters);
    }

    public static function padLeft(string $text, int $length, string $pad = " "): string 
    {
        return str_pad($text, $length, $pad, STR_PAD_LEFT);
    }

    public static function padRight(string $text, int $length, string $pad = " "): string 
    {
        return str_pad($text, $length, $pad, STR_PAD_RIGHT);
    }

    public static function padBoth(string $text, int $length, string $pad = " "): string 
    {
        return str_pad($text, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Format order/invoice/transaction code with zero padding
     * 
     * @param int|string $orderId Order ID number
     * @param int $padding Number of digits for padding (default: 4)
     * @return string Formatted order code with zero padding
     */
    public static function formatOrderCode($orderId, int $padding = 4): string 
    {
        $orderId = (int)$orderId;
        return str_pad((string)$orderId, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Format invoice number with prefix and zero padding
     * 
     * @param int|string $invoiceId Invoice ID number
     * @param string $prefix Prefix for invoice (default: 'INV')
     * @param int $padding Number of digits for padding (default: 4)
     * @param string $separator Separator between prefix and number (default: '-')
     * @return string Formatted invoice number
     */
    public static function formatInvoiceNumber($invoiceId, string $prefix = 'INV', int $padding = 4, string $separator = '-'): string 
    {
        $formattedId = self::formatOrderCode($invoiceId, $padding);
        return $prefix . $separator . $formattedId;
    }

    /**
     * Format transaction code with date and zero padding
     * 
     * @param int|string $transactionId Transaction ID number
     * @param string|null $date Date for transaction (null for current date)
     * @param int $padding Number of digits for padding (default: 4)
     * @param string $format Date format (default: 'Ymd')
     * @return string Formatted transaction code
     */
    public static function formatTransactionCode($transactionId, ?string $date = null, int $padding = 4, string $format = 'Ymd'): string 
    {
        $date = $date ?: date('Y-m-d');
        $dateFormatted = date($format, strtotime($date));
        $formattedId = self::formatOrderCode($transactionId, $padding);
        return $dateFormatted . $formattedId;
    }

    /**
     * Format document number with year and zero padding
     * 
     * @param int|string $docId Document ID number
     * @param string $prefix Document prefix (default: 'DOC')
     * @param int $padding Number of digits for padding (default: 4)
     * @param string|null $year Year (null for current year)
     * @return string Formatted document number
     */
    public static function formatDocumentNumber($docId, string $prefix = 'DOC', int $padding = 4, ?string $year = null): string 
    {
        $year = $year ?: date('Y');
        $formattedId = self::formatOrderCode($docId, $padding);
        return $prefix . '/' . $formattedId . '/' . $year;
    }

    /**
     * Format custom code with flexible parameters
     * 
     * @param int|string $id ID number
     * @param array $options Options for formatting
     *   - padding: int (default: 4)
     *   - prefix: string (default: '')
     *   - suffix: string (default: '')
     *   - separator: string (default: '-')
     *   - pad_char: string (default: '0')
     *   - include_date: bool (default: false)
     *   - date_format: string (default: 'Ymd')
     * @return string Formatted custom code
     */
    public static function formatCustomCode($id, array $options = []): string 
    {
        $defaults = [
            'padding' => 4,
            'prefix' => '',
            'suffix' => '',
            'separator' => '-',
            'pad_char' => '0',
            'include_date' => false,
            'date_format' => 'Ymd'
        ];
        
        $options = array_merge($defaults, $options);
        
        $id = (int)$id;
        $formattedId = str_pad((string)$id, $options['padding'], $options['pad_char'], STR_PAD_LEFT);
        
        $result = $formattedId;
        
        if (!empty($options['prefix'])) {
            $result = $options['prefix'] . $options['separator'] . $result;
        }
        
        if (!empty($options['suffix'])) {
            $result = $result . $options['separator'] . $options['suffix'];
        }
        
        if ($options['include_date']) {
            $dateFormatted = date($options['date_format']);
            $result = $dateFormatted . $options['separator'] . $result;
        }
        
        return $result;
    }

    /**
     * Text Cleaning
     */
    public static function removeSpaces(string $text): string 
    {
        return preg_replace('/\s+/', '', $text);
    }

    public static function removeExtraSpaces(string $text): string 
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }

    public static function removeSpecialChars(string $text): string 
    {
        return preg_replace('/[^A-Za-z0-9\s]/', '', $text);
    }

    public static function removeEmoji(string $text): string 
    {
        return preg_replace('/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u', '', $text);
    }

    /**
     * Text Analysis
     */
    public static function countWords(string $text): int 
    {
        return str_word_count($text);
    }

    public static function countChars(string $text, bool $includeSpaces = false): int 
    {
        if (!$includeSpaces) {
            $text = str_replace(' ', '', $text);
        }
        return mb_strlen($text, 'UTF-8');
    }

    public static function countSentences(string $text): int 
    {
        return preg_match_all('/[.!?]+/', $text, $matches);
    }

    public static function countParagraphs(string $text): int 
    {
        return count(array_filter(explode("\n\n", $text)));
    }

    /**
     * Text Extraction
     */
    public static function extractEmails(string $text): array 
    {
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches);
        return $matches[0];
    }

    public static function extractUrls(string $text): array 
    {
        preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<>\[\]{}"\']++/', $text, $matches);
        return $matches[0];
    }

    public static function extractHashtags(string $text): array 
    {
        preg_match_all('/#([^\s#]+)/', $text, $matches);
        return $matches[1];
    }

    public static function extractMentions(string $text): array 
    {
        preg_match_all('/@([^\s@]+)/', $text, $matches);
        return $matches[1];
    }

    /**
     * Text Transformation
     */
    public static function reverse(string $text): string 
    {
        return strrev($text);
    }

    public static function repeat(string $text, int $times): string 
    {
        return str_repeat($text, $times);
    }

    public static function wrap(string $text, int $width = 75, string $break = "\n"): string 
    {
        return wordwrap($text, $width, $break, false);
    }

    public static function truncate(string $text, int $length, string $suffix = '...'): string 
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }

    /**
     * Text Search and Replace
     */
    public static function replace(string $text, string $search, string $replace): string 
    {
        return str_replace($search, $replace, $text);
    }

    public static function replaceFirst(string $text, string $search, string $replace): string 
    {
        $pos = strpos($text, $search);
        if ($pos !== false) {
            return substr_replace($text, $replace, $pos, strlen($search));
        }
        return $text;
    }

    public static function replaceLast(string $text, string $search, string $replace): string 
    {
        $pos = strrpos($text, $search);
        if ($pos !== false) {
            return substr_replace($text, $replace, $pos, strlen($search));
        }
        return $text;
    }

    public static function replaceAll(string $text, array $search, array $replace): string 
    {
        return str_replace($search, $replace, $text);
    }

    /**
     * Date and Time Formatting
     */
    public static function formatDate(string $date, string $format = 'Y-m-d'): string 
    {
        return date($format, strtotime($date));
    }

    public static function formatInDate(string $date): string 
    {
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $tanggal = strtotime($date);
        $hari = date('d', $tanggal);
        $bulanIndex = date('n', $tanggal);
        $tahun = date('Y', $tanggal);

        return $hari . ' ' . $bulan[$bulanIndex] . ' ' . $tahun;
    }

    public static function formatInDateTime(string $datetime): string 
    {
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $tanggal = strtotime($datetime);
        $hari = date('d', $tanggal);
        $bulanIndex = date('n', $tanggal);
        $tahun = date('Y', $tanggal);
        $waktu = date('H:i', $tanggal);

        return $hari . ' ' . $bulan[$bulanIndex] . ' ' . $tahun . ' ' . $waktu;
    }

    public static function formatInDayDate(string $date): string 
    {
        $namaHari = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $tanggal = strtotime($date);
        $hariInggris = date('l', $tanggal);
        $hari = date('d', $tanggal);
        $bulanIndex = date('n', $tanggal);
        $tahun = date('Y', $tanggal);

        return $namaHari[$hariInggris] . ', ' . $hari . ' ' . $bulan[$bulanIndex] . ' ' . $tahun;
    }

    public static function formatTimeAgo(string $datetime): string 
    {
        $timestamp = strtotime($datetime);
        $selisih = time() - $timestamp;
        
        $detik = $selisih;
        $menit = round($selisih / 60);
        $jam = round($selisih / 3600);
        $hari = round($selisih / 86400);
        $minggu = round($selisih / 604800);
        $bulan = round($selisih / 2419200);
        $tahun = round($selisih / 29030400);
        
        if ($detik < 60) {
            return 'Baru saja';
        } else if ($menit < 60) {
            return $menit . ' menit yang lalu';
        } else if ($jam < 24) {
            return $jam . ' jam yang lalu';
        } else if ($hari < 7) {
            return $hari . ' hari yang lalu';
        } else if ($minggu < 4) {
            return $minggu . ' minggu yang lalu';
        } else if ($bulan < 12) {
            return $bulan . ' bulan yang lalu';
        } else {
            return $tahun . ' tahun yang lalu';
        }
    }

    public static function formatDuration(int $seconds): string 
    {
        if ($seconds < 60) {
            return $seconds . ' detik';
        }
        
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);
        
        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . ' hari';
            $hours = $hours % 24;
        }
        
        if ($hours > 0) {
            $parts[] = $hours . ' jam';
            $minutes = $minutes % 60;
        }
        
        if ($minutes > 0) {
            $parts[] = $minutes . ' menit';
            $seconds = $seconds % 60;
        }
        
        if ($seconds > 0 && count($parts) < 3) {
            $parts[] = $seconds . ' detik';
        }
        
        return implode(' ', $parts);
    }

    public static function formatToRFC3339(string $datetime): string 
    {
        return date('c', strtotime($datetime));
    }

    public static function formatToISO8601(string $datetime): string 
    {
        return date('c', strtotime($datetime));
    }

    public static function formatToMySQLDateTime(string $datetime): string 
    {
        return date('Y-m-d H:i:s', strtotime($datetime));
    }

    public static function getCurrentTimestamp(): string 
    {
        return date('Y-m-d H:i:s');
    }

    public static function isWeekend(string $date): bool 
    {
        return in_array(date('N', strtotime($date)), [6, 7]);
    }

    public static function getDayName(string $date, bool $short = false): string 
    {
        $hari = [
            'Sunday' => ['Minggu', 'Min'],
            'Monday' => ['Senin', 'Sen'],
            'Tuesday' => ['Selasa', 'Sel'],
            'Wednesday' => ['Rabu', 'Rab'],
            'Thursday' => ['Kamis', 'Kam'],
            'Friday' => ['Jumat', 'Jum'],
            'Saturday' => ['Sabtu', 'Sab']
        ];

        $namaHari = date('l', strtotime($date));
        return $short ? $hari[$namaHari][1] : $hari[$namaHari][0];
    }

    public static function getMonthName(string $date, bool $short = false): string 
    {
        $bulan = [
            1 => ['Januari', 'Jan'],
            2 => ['Februari', 'Feb'],
            3 => ['Maret', 'Mar'],
            4 => ['April', 'Apr'],
            5 => ['Mei', 'Mei'],
            6 => ['Juni', 'Jun'],
            7 => ['Juli', 'Jul'],
            8 => ['Agustus', 'Ags'],
            9 => ['September', 'Sep'],
            10 => ['Oktober', 'Okt'],
            11 => ['November', 'Nov'],
            12 => ['Desember', 'Des']
        ];

        $bulanIndex = date('n', strtotime($date));
        return $short ? $bulan[$bulanIndex][1] : $bulan[$bulanIndex][0];
    }

    /**
     * Current Date Information Methods - Instance Methods
     */
    public function getDay(): string 
    {
        $hari = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $today = date('l');
        return $this->isIndonesian ? $hari[$today] : $today;
    }

    public function getMonth(): string 
    {
        $bulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $currentMonth = date('n');
        return $this->isIndonesian ? $bulan[$currentMonth] : date('F');
    }

    public function getYear(): string 
    {
        return date('Y');
    }

    public function getDate(): string 
    {
        if ($this->isIndonesian) {
            return $this->getDay() . ', ' . 
                   date('d') . ' ' . 
                   $this->getMonth() . ' ' . 
                   $this->getYear();
        }
        return date('l, d F Y');
    }

    public function getMonthYear(): string 
    {
        if ($this->isIndonesian) {
            return $this->getMonth() . ' ' . $this->getYear();
        }
        return date('F Y');
    }

    public function getDayDate(): string 
    {
        if ($this->isIndonesian) {
            return $this->getDay() . ', ' . date('d');
        }
        return date('l, d');
    }

    public function getDetailedDate(): array 
    {
        return [
            'hari' => $this->getDay(),
            'tanggal' => date('d'),
            'bulan' => $this->getMonth(),
            'tahun' => $this->getYear(),
            'bulan_angka' => date('m'),
            'hari_dalam_minggu' => date('N'),
            'minggu_dalam_bulan' => ceil(date('d') / 7),
            'hari_dalam_bulan' => date('t'),
            'minggu_dalam_tahun' => date('W'),
            'hari_dalam_tahun' => date('z') + 1,
            'timestamp' => time()
        ];
    }

    /**
     * Static Methods - For backward compatibility
     */
    public static function getCurrentDay(bool $isIndonesian = true): string 
    {
        $instance = new self($isIndonesian);
        return $instance->getDay();
    }

    public static function getCurrentMonth(bool $isIndonesian = true): string 
    {
        $instance = new self($isIndonesian);
        return $instance->getMonth();
    }

    public static function getCurrentYear(): string 
    {
        $instance = new self();
        return $instance->getYear();
    }

    public static function getCurrentDate(bool $isIndonesian = true): string 
    {
        $instance = new self($isIndonesian);
        return $instance->getDate();
    }

    public static function getCurrentMonthYear(bool $isIndonesian = true): string 
    {
        $instance = new self($isIndonesian);
        return $instance->getMonthYear();
    }

    public static function getCurrentDayDate(bool $isIndonesian = true): string 
    {
        $instance = new self($isIndonesian);
        return $instance->getDayDate();
    }

    public static function getDetailedCurrentDate(): array 
    {
        $instance = new self();
        return $instance->getDetailedDate();
    }

    public static function getCurrentDateInfo(string $format = 'full'): string 
    {
        switch (strtolower($format)) {
            case 'short':
                return date('d/m/Y');
            case 'medium':
                return date('d') . ' ' . self::getCurrentMonth(true) . ' ' . date('Y');
            case 'long':
                return self::getCurrentDay() . ', ' . date('d') . ' ' . self::getCurrentMonth() . ' ' . date('Y');
            case 'full':
                return self::getCurrentDay() . ', ' . date('d') . ' ' . self::getCurrentMonth() . ' ' . date('Y') . ' ' . date('H:i:s');
            default:
                return date($format);
        }
    }

    public static function isToday(string $date): bool 
    {
        return date('Y-m-d', strtotime($date)) === date('Y-m-d');
    }

    public static function isCurrentMonth(string $date): bool 
    {
        return date('Y-m', strtotime($date)) === date('Y-m');
    }

    public static function isCurrentYear(string $date): bool 
    {
        return date('Y', strtotime($date)) === date('Y');
    }

    public static function getDaysInCurrentMonth(): int 
    {
        return date('t');
    }

    public static function getWeekNumberInYear(): int 
    {
        return date('W');
    }


    public static function getQuarterOfYear(): int 
    {
        return ceil(date('n') / 3);
    }

    public static  function exclude(array $input): array
    {
        $mapped = array_map(function($v) {
            return "'" . $v . "'";
        }, $input);
    
        $result = implode(',', $mapped);
        $output = explode(',', str_replace("'", '', $result));
        return $output;
    }

    public static function limitChars(string $value, int $length = 100): string
    {
        return mb_strlen($value, 'UTF-8') > $length
            ? mb_substr($value, 0, $length, 'UTF-8') . '...'
            : $value;
    }

    /**
     * Number and Currency Formatting
     */
    public static function formatRupiah(float $amount, bool $withSymbol = true, bool $withSuffix = true): string 
    {
        $formatted = number_format($amount, 0, ',', '.');
        
        if ($withSymbol) {
            $formatted = 'Rp ' . $formatted;
        }
        
        if ($withSuffix) {
            $formatted .= ',-';
        }
        
        return $formatted;
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
            return 'minus ' . self::terbilang(abs($number));
        }
        
        if ($number == 0) {
            return 'nol';
        }
        
        $ones = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
            'sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas',
            'enam belas', 'tujuh belas', 'delapan belas', 'sembilan belas'
        ];
        
        if ($number < 20) {
            return $ones[$number];
        }
        
        if ($number < 100) {
            $tens = intval($number / 10);
            $remainder = $number % 10;
            
            if ($tens == 2 && $remainder == 0) {
                return 'dua puluh';
            } elseif ($tens == 2) {
                return 'dua puluh ' . $ones[$remainder];
            } else {
                $tensWords = ['', '', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'];
                return $tensWords[$tens] . ' puluh' . ($remainder > 0 ? ' ' . $ones[$remainder] : '');
            }
        }
        
        if ($number < 200) {
            $remainder = $number - 100;
            return 'seratus' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        if ($number < 1000) {
            $hundreds = intval($number / 100);
            $remainder = $number % 100;
            return $ones[$hundreds] . ' ratus' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        if ($number < 2000) {
            $remainder = $number - 1000;
            return 'seribu' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        if ($number < 1000000) {
            $thousands = intval($number / 1000);
            $remainder = $number % 1000;
            return self::terbilang($thousands) . ' ribu' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        if ($number < 1000000000) {
            $millions = intval($number / 1000000);
            $remainder = $number % 1000000;
            return self::terbilang($millions) . ' juta' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        if ($number < 1000000000000) {
            $billions = intval($number / 1000000000);
            $remainder = $number % 1000000000;
            return self::terbilang($billions) . ' miliar' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
        }
        
        $trillions = intval($number / 1000000000000);
        $remainder = $number % 1000000000000;
        return self::terbilang($trillions) . ' triliun' . ($remainder > 0 ? ' ' . self::terbilang($remainder) : '');
    }

    public static function terbilangRupiah(float $amount): string 
    {
        $integerPart = intval($amount);
        $decimalPart = intval(($amount - $integerPart) * 100);
        
        $result = self::terbilang($integerPart) . ' rupiah';
        
        if ($decimalPart > 0) {
            $result .= ' ' . self::terbilang($decimalPart) . ' sen';
        }
        
        return $result;
    }

    /**
     * Convert number to Indonesian words with title case (first letter of each word capitalized)
     * 
     * @param int|float $number Number to convert
     * @return string Indonesian words in title case
     */
    public static function terbilangTitle($number): string 
    {
        return self::toTitle(self::terbilang($number));
    }

    public static function terbilangRupiahTitle(float $amount): string 
    {
        return self::toTitle(self::terbilangRupiah($amount));
    }

    /**
     * Convert number to Indonesian words with sentence case (first letter capitalized)
     * 
     * @param int|float $number Number to convert
     * @return string Indonesian words in sentence case
     */
    public static function terbilangSentence($number): string 
    {
        return self::toSentence(self::terbilang($number));
    }

    public static function terbilangRupiahSentence(float $amount): string 
    {
        return self::toSentence(self::terbilangRupiah($amount));
    }

    public static function formatNumber(float $number, int $decimals = 0, string $decimalSeparator = ',', string $thousandsSeparator = '.'): string 
    {
        return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public static function parseNumber(string $formattedNumber): float 
    {
        // Remove currency symbols and text
        $cleaned = preg_replace('/[^\d,.-]/', '', $formattedNumber);
        
        // Handle Indonesian format (1.000.000,50)
        if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
            // Check if comma is decimal separator
            $lastComma = strrpos($cleaned, ',');
            $lastDot = strrpos($cleaned, '.');
            
            if ($lastComma > $lastDot) {
                // Indonesian format: 1.000.000,50
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // English format: 1,000,000.50
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (strpos($cleaned, ',') !== false) {
            // Only comma, could be decimal separator or thousands separator
            $commaCount = substr_count($cleaned, ',');
            if ($commaCount == 1 && strlen(substr($cleaned, strrpos($cleaned, ',') + 1)) <= 2) {
                // Likely decimal separator
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // Likely thousands separator
                $cleaned = str_replace(',', '', $cleaned);
            }
        }
        
        return floatval($cleaned);
    }

    /**
     * Convert date range to readable format
     * Output example: "5 bulan (April - Agustus 2025)"
     */
    public static function convertDateRange($start_date, $end_date): string {
        try {
            $start = new \DateTime($start_date);
            $end = new \DateTime($end_date);
            
            // Calculate month difference
            $months = $start->diff($end)->m + ($start->diff($end)->y * 12);
            
            // Get month names in Indonesian
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            
            $startMonth = $monthNames[(int)$start->format('n')];
            $endMonth = $monthNames[(int)$end->format('n')];
            $year = $end->format('Y');
            
            // Calculate actual months including partial months
            $actualMonths = $months;
            if ($start->format('d') == 1 && $end->format('d') == $end->format('t')) {
                $actualMonths += 1; // Full months
            } else {
                $actualMonths = $months + 1; // Include partial months
            }
            
            return "{$actualMonths} bulan ({$startMonth} - {$endMonth} {$year})";
            
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Roman Numerals Conversion
     */
    
    /**
     * Convert an integer to Roman numerals
     * 
     * @param int $number Number to convert (1-3999)
     * @return string Roman numeral representation
     * @throws \InvalidArgumentException if number is out of range
     */
    public static function toRoman(int $number): string 
    {
        if ($number <= 0 || $number > 3999) {
            throw new \InvalidArgumentException('Number must be between 1 and 3999');
        }

        $romanNumerals = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I'
        ];

        $result = '';
        
        foreach ($romanNumerals as $value => $numeral) {
            $count = intval($number / $value);
            if ($count) {
                $result .= str_repeat($numeral, $count);
                $number -= $value * $count;
            }
        }

        return $result;
    }

    /**
     * Convert Roman numerals to integer
     * 
     * @param string $roman Roman numeral string
     * @return int Integer representation
     * @throws \InvalidArgumentException if invalid Roman numeral
     */
    public static function fromRoman(string $roman): int 
    {
        $roman = strtoupper(trim($roman));
        
        if (!preg_match('/^M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $roman)) {
            throw new \InvalidArgumentException('Invalid Roman numeral format');
        }

        $romanValues = [
            'I' => 1,
            'V' => 5,
            'X' => 10,
            'L' => 50,
            'C' => 100,
            'D' => 500,
            'M' => 1000
        ];

        $result = 0;
        $prevValue = 0;

        for ($i = strlen($roman) - 1; $i >= 0; $i--) {
            $currentValue = $romanValues[$roman[$i]];
            
            if ($currentValue < $prevValue) {
                $result -= $currentValue;
            } else {
                $result += $currentValue;
            }
            
            $prevValue = $currentValue;
        }

        return $result;
    }

    /**
     * Check if a string is a valid Roman numeral
     * 
     * @param string $roman String to validate
     * @return bool True if valid Roman numeral
     */
    public static function isValidRoman(string $roman): bool 
    {
        try {
            $roman = strtoupper(trim($roman));
            return preg_match('/^M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $roman) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Convert number to Roman numerals with lowercase option
     * 
     * @param int $number Number to convert
     * @param bool $lowercase Whether to return lowercase Roman numerals
     * @return string Roman numeral representation
     */
    public static function toRomanCase(int $number, bool $lowercase = false): string 
    {
        $roman = self::toRoman($number);
        return $lowercase ? strtolower($roman) : $roman;
    }

    /**
     * Generate array of Roman numerals from 1 to specified number
     * 
     * @param int $max Maximum number (default 20)
     * @param bool $lowercase Whether to return lowercase
     * @return array Array of Roman numerals
     */
    public static function getRomanSequence(int $max = 20, bool $lowercase = false): array 
    {
        if ($max <= 0 || $max > 3999) {
            throw new \InvalidArgumentException('Max number must be between 1 and 3999');
        }

        $sequence = [];
        for ($i = 1; $i <= $max; $i++) {
            $sequence[$i] = self::toRomanCase($i, $lowercase);
        }

        return $sequence;
    }

    /**
     * Convert Roman numeral with case preservation
     * 
     * @param string $roman Roman numeral to convert
     * @return array Array with 'number' and 'original_case' keys
     */
    public static function fromRomanWithCase(string $roman): array 
    {
        $originalCase = $roman;
        $number = self::fromRoman($roman);
        
        return [
            'number' => $number,
            'original_case' => $originalCase,
            'uppercase' => strtoupper($roman),
            'lowercase' => strtolower($roman)
        ];
    }

    /**
     * Convert current month to Roman numerals
     * 
     * @return string Current month in Roman numerals (always uppercase)
     */
    public static function toRomanMonth(): string 
    {
        $currentMonth = (int) date('n'); // Get current month (1-12)
        return self::toRoman($currentMonth);
    }

    /**
     * Convert specific month to Roman numerals
     * 
     * @param int $month Month number (1-12)
     * @return string Month in Roman numerals (always uppercase)
     * @throws \InvalidArgumentException if month is out of range
     */
    public static function monthToRoman(int $month): string 
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
        
        return self::toRoman($month);
    }

    /**
     * Convert current month and year to Roman numerals
     * 
     * @param string $separator Separator between month and year
     * @return string Current month and year in Roman numerals (always uppercase)
     */
    public static function toRomanMonthYear(string $separator = '/'): string 
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        
        $romanMonth = self::toRoman($currentMonth);
        $romanYear = self::toRoman($currentYear);
        
        return $romanMonth . $separator . $romanYear;
    }

    /**
     * Get array of all months in Roman numerals
     * 
     * @param bool $withNames Whether to include Indonesian month names
     * @return array Array of months in Roman numerals (always uppercase)
     */
    public static function getAllMonthsRoman(bool $withNames = false): array 
    {
        $months = [];
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        for ($i = 1; $i <= 12; $i++) {
            $roman = self::toRoman($i);
            
            if ($withNames) {
                $months[$i] = [
                    'number' => $i,
                    'roman' => $roman,
                    'name' => $monthNames[$i]
                ];
            } else {
                $months[$i] = $roman;
            }
        }

        return $months;
    }

    /**
     * Convert date to Roman format (day/month/year)
     * 
     * @param string|null $date Date to convert (null for current date)
     * @param string $separator Separator between date parts
     * @return string Date in Roman numerals format (always uppercase)
     */
    public static function dateToRoman(?string $date = null, string $separator = '/'): string 
    {
        $timestamp = $date ? strtotime($date) : time();
        
        if ($timestamp === false) {
            throw new \InvalidArgumentException('Invalid date format');
        }
        
        $day = (int) date('j', $timestamp);
        $month = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp);
        
        $romanDay = self::toRoman($day);
        $romanMonth = self::toRoman($month);
        $romanYear = self::toRoman($year);
        
        return $romanDay . $separator . $romanMonth . $separator . $romanYear;
    }
}
