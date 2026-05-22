<?php
declare(strict_types=1);
namespace App\System\Helpers;
class NexaShet {
    private $baseUploadDir;
    private $platformPatterns = [
        'youtube' => '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+/i'
    ];
    
    public function __construct() {
        // Set base upload directory
        $this->baseUploadDir = dirname(__DIR__, 2) . '/assets/drive/video/';
    }

    /**
     * Dynamic method to get thumbnail based on URL
     * 
     * @param string|null $url Video URL from YouTube
     * @return array|false Array containing remote URL and local path, or false if failed
     * @throws \InvalidArgumentException If URL is invalid or not a YouTube URL
     */
    public function getShet($url) {
        if (empty($url) || !is_string($url)) {
            throw new \InvalidArgumentException('URL tidak valid atau kosong');
        }

        // Detect if URL is YouTube
        if (!$this->isValidUrl($url, 'youtube')) {
            throw new \InvalidArgumentException('URL tidak valid. Hanya URL YouTube yang didukung.');
        }

        return $this->getYoutube($url);
    }

    /**
     * Validate URL for specific platform
     */
    private function isValidUrl($url, $platform): bool {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return isset($this->platformPatterns[$platform]) && 
               (bool)preg_match($this->platformPatterns[$platform], $url);
    }

    /**
     * Download content using cURL
     */
    private function curlGet($url, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $result : false;
    }

    /**
     * Download and save thumbnail to local directory
     * 
     * @param string $url Source URL of the thumbnail
     * @param string $platform Platform name (youtube, vimeo, etc.)
     * @return array|false Array containing local path and size, or false if failed
     */
    private function downloadThumbnail($url, $platform) {
        // Create year/month directory structure
        $yearMonth = date('Y/m/');
        $uploadDir = $this->baseUploadDir . $yearMonth;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }

        // Generate unique filename
        $filename = $platform . '_' . uniqid() . '_' . time() . '.jpg';
        $localPath = $uploadDir . $filename;

        // Download using cURL
        $content = $this->curlGet($url);
        if ($content === false) {
            return false;
        }

        // Save file
        if (file_put_contents($localPath, $content) === false) {
            return false;
        }

        // Get file size
        $size = filesize($localPath);

        // Return array with path and size
        return [
            'path' => $yearMonth . $filename,
            'size' => $size
        ];
    }

    /**
     * Extract YouTube video ID from various YouTube URL formats
     * 
     * @param string|null $url YouTube URL
     * @return string|false Video ID or false if not found/invalid
     */
    private function extractVideoId($url) {
        // Validate URL first
        if (!$this->isValidUrl($url, 'youtube')) {
            return false;
        }
        
        $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        
        return false;
    }

    /**
     * Get video details by parsing the video page
     * 
     * @param string $videoId YouTube video ID
     * @return array Array containing video details
     */
    private function getVideoDetails($videoId) {
        $url = "https://www.youtube.com/watch?v=" . $videoId;
        $html = $this->curlGet($url);
        
        if (!$html) {
            return [
                'title' => '',
                'description' => ''
            ];
        }

        // Extract title
        $title = '';
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            $title = trim(str_replace(' - YouTube', '', $matches[1]));
        }

        // Extract description
        $description = '';
        if (preg_match('/"description":{"simpleText":"(.*?)"}/', $html, $matches)) {
            $description = str_replace('\n', "\n", $matches[1]);
        }

        return [
            'title' => $title,
            'description' => $description
        ];
    }

    /**
     * Get YouTube thumbnail URL and save it locally
     * 
     * @param string|null $url YouTube video URL
     * @return array|false Array containing remote URL and local path, or false if failed
     * @throws \InvalidArgumentException If URL is invalid
     */
    public function getYoutube($url) {
        // Validate URL
        if (!$this->isValidUrl($url, 'youtube')) {
            throw new \InvalidArgumentException('URL YouTube tidak valid atau kosong');
        }
        
        // Extract video ID from URL
        $videoId = $this->extractVideoId($url);
        
        if (!$videoId) {
            throw new \InvalidArgumentException('Tidak dapat mengekstrak ID video dari URL');
        }
        
        // Get remote thumbnail URL
        $remoteUrl = "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
        
        // Try maxresdefault first
        $thumbnail = $this->downloadThumbnail($remoteUrl, 'youtube');
        
        // If maxresdefault fails, try hqdefault
        if ($thumbnail === false) {
            $remoteUrl = "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
            $thumbnail = $this->downloadThumbnail($remoteUrl, 'youtube');
        }
        
        if ($thumbnail === false) {
            throw new \InvalidArgumentException('Gagal mengunduh thumbnail video');
        }

        // Get video details
        $videoDetails = $this->getVideoDetails($videoId);
        
        return [
            'remote_img' => $remoteUrl,
            'files' => $thumbnail['path'],
            'sizefor' => $thumbnail['size'],
            'size' =>$this->formatBytes($thumbnail['size']),
            'video_id' => $videoId,
            'title' => $videoDetails['title'],
            'deskripsi' => $videoDetails['description']?? $videoDetails['title']
        ];
    }

private function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}







}
