<?php
declare(strict_types=1);

namespace App\System\Helpers;

/**
 * NexaAgent - Comprehensive User Agent Detection & Analysis with Geolocation
 * 
 * Features:
 * - Advanced browser detection (Chrome, Firefox, Safari, Edge, Opera, etc.)
 * - Device type detection (Desktop, Mobile, Tablet, TV, Gaming Console)
 * - Platform/OS detection (Windows, macOS, Linux, Android, iOS, etc.)
 * - Real IP address detection (behind proxies, CDN, load balancers)
 * - User agent parsing and analysis
 * - Device capabilities detection
 * - Bot/Crawler detection
 * - Security analysis (suspicious patterns)
 * - **NEW: IP-based Geolocation (Country, Province, City, District/Kabupaten)**
 * - **NEW: Coordinates (Latitude, Longitude), Timezone, ISP detection**
 * - Performance analytics support
 * 
 * Basic Usage:
 * ```php
 * $agent = new NexaAgent();
 * $info = $agent->analyze();
 * $browser = $agent->getBrowser();
 * $device = $agent->getDevice();
 * $platform = $agent->getPlatform();
 * $ip = $agent->getRealIP();
 * ```
 * 
 * Geolocation Usage:
 * ```php
 * $agent = new NexaAgent();
 * 
 * // Get full geolocation info
 * $geo = $agent->getGeolocation();
 * // Returns: ['country' => 'Indonesia', 'province' => 'Jawa Barat', 
 * //           'city' => 'Bandung', 'district' => 'Coblong', ...]
 * 
 * // Quick access methods
 * $propinsi = $agent->getPropinsi();      // "Jawa Barat"
 * $kabupaten = $agent->getKabupaten();    // "Bandung"
 * $kota = $agent->getCity();              // "Bandung"
 * $negara = $agent->getCountry();         // ['name' => 'Indonesia', 'code' => 'ID']
 * 
 * // Get formatted location
 * $lokasi = $agent->getFullLocation();         // "Bandung, Jawa Barat, Indonesia"
 * $lokasiID = $agent->getLokasiIndonesia();    // "Kab. Bandung, Bandung, Prov. Jawa Barat"
 * 
 * // Get coordinates & timezone
 * $koordinat = $agent->getCoordinates();  // ['latitude' => -6.9175, 'longitude' => 107.6191]
 * $timezone = $agent->getTimezone();      // "Asia/Jakarta"
 * 
 * // Get ISP info
 * $isp = $agent->getISP();                // ['isp' => 'Telkom Indonesia', 'organization' => '...']
 * 
 * // Check if user is from Indonesia
 * if ($agent->isIndonesia()) {
 *     echo "Pengunjung dari Indonesia";
 * }
 * ```
 * 
 * Advanced Usage:
 * ```php
 * $agent = new NexaAgent($customUserAgent);
 * $fullAnalysis = $agent->getFullAnalysis();
 * $fullWithGeo = $agent->getFullAnalysisWithGeo();  // Includes geolocation
 * 
 * $isBot = $agent->isBot();
 * $isMobile = $agent->isMobile();
 * $capabilities = $agent->getDeviceCapabilities();
 * 
 * // Custom IP geolocation
 * $geoCustom = $agent->getGeolocation('8.8.8.8', false);  // No cache
 * ```
 * 
 * @version 2.0.0
 * @author NexaAgent Team
 * @package App\System\Helpers
 */
class NexaAgent
{
    private string $userAgent;
    private ?array $analysisCache = null;
    private array $serverData;
    private ?array $geolocationCache = null;
    
    // Geolocation API endpoints (fallback order)
    private const GEO_API_ENDPOINTS = [
        'ipapi' => 'http://ip-api.com/json/{ip}?fields=status,message,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,isp,org,as,query',
        'ipwhois' => 'http://ipwhoapi.com/{ip}',
        'ipgeolocation' => 'https://ipapi.co/{ip}/json/',
    ];
    
    // Browser patterns (order matters for accurate detection)
    private const BROWSER_PATTERNS = [
        'Edg' => ['pattern' => '/Edg\/([0-9.]+)/', 'name' => 'Edge', 'engine' => 'Blink'],
        'Edge' => ['pattern' => '/Edge\/([0-9.]+)/', 'name' => 'Edge Legacy', 'engine' => 'EdgeHTML'],
        'OPR' => ['pattern' => '/OPR\/([0-9.]+)/', 'name' => 'Opera', 'engine' => 'Blink'],
        'Opera' => ['pattern' => '/Opera[\/\s]([0-9.]+)/', 'name' => 'Opera', 'engine' => 'Presto'],
        'Chrome' => ['pattern' => '/Chrome\/([0-9.]+)/', 'name' => 'Chrome', 'engine' => 'Blink'],
        'Firefox' => ['pattern' => '/Firefox\/([0-9.]+)/', 'name' => 'Firefox', 'engine' => 'Gecko'],
        'Safari' => ['pattern' => '/Safari\/([0-9.]+)/', 'name' => 'Safari', 'engine' => 'WebKit'],
        'MSIE' => ['pattern' => '/MSIE ([0-9.]+)/', 'name' => 'Internet Explorer', 'engine' => 'Trident'],
        'Trident' => ['pattern' => '/Trident.*rv:([0-9.]+)/', 'name' => 'Internet Explorer', 'engine' => 'Trident'],
        'Vivaldi' => ['pattern' => '/Vivaldi\/([0-9.]+)/', 'name' => 'Vivaldi', 'engine' => 'Blink'],
        'Brave' => ['pattern' => '/Brave\/([0-9.]+)/', 'name' => 'Brave', 'engine' => 'Blink'],
        'UCBrowser' => ['pattern' => '/UC Browser\/([0-9.]+)/', 'name' => 'UC Browser', 'engine' => 'Blink'],
        'SamsungBrowser' => ['pattern' => '/SamsungBrowser\/([0-9.]+)/', 'name' => 'Samsung Internet', 'engine' => 'Blink'],
        'YaBrowser' => ['pattern' => '/YaBrowser\/([0-9.]+)/', 'name' => 'Yandex Browser', 'engine' => 'Blink'],
    ];
    
    // Platform patterns
    private const PLATFORM_PATTERNS = [
        'Windows NT 10.0' => 'Windows 10/11',
        'Windows NT 6.3' => 'Windows 8.1',
        'Windows NT 6.2' => 'Windows 8',
        'Windows NT 6.1' => 'Windows 7',
        'Windows NT 6.0' => 'Windows Vista',
        'Windows NT 5.1' => 'Windows XP',
        'Mac OS X' => 'macOS',
        'Ubuntu' => 'Ubuntu',
        'CentOS' => 'CentOS',
        'Linux' => 'Linux',
        'Android' => 'Android',
        'iPhone OS' => 'iOS',
        'iPad.*OS' => 'iPadOS',
    ];
    
    // Bot patterns
    private const BOT_PATTERNS = [
        'Googlebot', 'Bingbot', 'Slurp', 'facebookexternalhit', 'Twitterbot',
        'LinkedInBot', 'WhatsApp', 'Applebot', 'YandexBot', 'DuckDuckBot',
        'crawler', 'spider', 'bot', 'scraper', 'indexer'
    ];
    
    // IP header priority (for real IP detection)
    private const IP_HEADERS = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    public function __construct(?string $userAgent = null, ?array $serverData = null)
    {
        $this->userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->serverData = $serverData ?? $_SERVER;
    }
    
    /**
     * Get comprehensive analysis of user agent
     * 
     * @return array Complete analysis
     */
    public function analyze(): array
    {
        if ($this->analysisCache === null) {
            $this->analysisCache = $this->performAnalysis();
        }
        
        return $this->analysisCache;
    }
    
    /**
     * Perform comprehensive analysis
     * 
     * @return array Analysis results
     */
    private function performAnalysis(): array
    {
        $browser = $this->detectBrowser();
        $platform = $this->detectPlatform();
        $device = $this->detectDevice();
        $ip = $this->detectRealIP();
        
        return [
            // Core information
            'browser' => $browser['name'],
            'browser_version' => $browser['version'],
            'browser_engine' => $browser['engine'],
            'platform' => $platform['name'],
            'platform_version' => $platform['version'],
            'device_type' => $device['type'],
            'device_brand' => $device['brand'],
            'ip_address' => $ip['address'],
            'ip_type' => $ip['type'],
            
            // Advanced detection
            'is_mobile' => $device['is_mobile'],
            'is_tablet' => $device['is_tablet'],
            'is_desktop' => $device['is_desktop'],
            'is_bot' => $this->isBot(),
            'is_touch' => $device['is_touch'],
            
            // Capabilities
            'supports_webgl' => $this->supportsWebGL(),
            'supports_webrtc' => $this->supportsWebRTC(),
            'supports_websocket' => $this->supportsWebSocket(),
            
            // Security & Privacy
            'privacy_mode' => $this->detectPrivacyMode(),
            'do_not_track' => $this->getDoNotTrack(),
            'accepts_cookies' => $this->acceptsCookies(),
            
            // Network information
            'connection_type' => $this->getConnectionType(),
            'accepts_encoding' => $this->getAcceptedEncoding(),
            'language' => $this->getLanguage(),
            'languages' => $this->getLanguages(),
            
            // Meta information
            'user_agent' => $this->userAgent,
            'analysis_timestamp' => time(),
            'analysis_date' => date('Y-m-d H:i:s'),
            
            // Geolocation information (on-demand)
            'geolocation_available' => true,
        ];
    }
    
    /**
     * Detect browser information
     * 
     * @return array Browser info
     */
    private function detectBrowser(): array
    {
        $browser = ['name' => 'Unknown', 'version' => 'Unknown', 'engine' => 'Unknown'];
        
        foreach (self::BROWSER_PATTERNS as $key => $config) {
            if (preg_match($config['pattern'], $this->userAgent, $matches)) {
                // Special handling for Chrome-based browsers
                if ($key === 'Chrome' && preg_match('/Edg|OPR|Vivaldi|Brave/', $this->userAgent)) {
                    continue; // Skip Chrome detection for Chrome-based browsers
                }
                
                // Special handling for Safari
                if ($key === 'Safari' && preg_match('/Chrome|Chromium/', $this->userAgent)) {
                    continue; // Skip Safari detection for Chrome-based browsers
                }
                
                $browser['name'] = $config['name'];
                $browser['engine'] = $config['engine'];
                
                // Get version
                if (isset($matches[1])) {
                    $browser['version'] = $matches[1];
                }
                
                // Special version handling for Safari
                if ($key === 'Safari' && preg_match('/Version\/([0-9.]+)/', $this->userAgent, $versionMatches)) {
                    $browser['version'] = $versionMatches[1];
                }
                
                break;
            }
        }
        
        return $browser;
    }
    
    /**
     * Detect platform/OS information
     * 
     * @return array Platform info
     */
    private function detectPlatform(): array
    {
        $platform = ['name' => 'Unknown', 'version' => 'Unknown', 'architecture' => 'Unknown'];
        
        // Windows detection
        if (preg_match('/Windows NT ([0-9.]+)/', $this->userAgent, $matches)) {
            $platform['name'] = self::PLATFORM_PATTERNS['Windows NT ' . $matches[1]] ?? 'Windows ' . $matches[1];
            $platform['version'] = $matches[1];
            $platform['architecture'] = preg_match('/WOW64|Win64|x64/', $this->userAgent) ? 'x64' : 'x86';
        }
        // macOS detection
        elseif (preg_match('/Mac OS X ([0-9_]+)/', $this->userAgent, $matches)) {
            $platform['name'] = 'macOS';
            $platform['version'] = str_replace('_', '.', $matches[1]);
            $platform['architecture'] = preg_match('/Intel/', $this->userAgent) ? 'Intel' : (preg_match('/PPC/', $this->userAgent) ? 'PowerPC' : 'Apple Silicon');
        }
        // Linux detection
        elseif (preg_match('/Linux/', $this->userAgent)) {
            if (preg_match('/Ubuntu/', $this->userAgent)) {
                $platform['name'] = 'Ubuntu';
            } elseif (preg_match('/CentOS/', $this->userAgent)) {
                $platform['name'] = 'CentOS';
            } elseif (preg_match('/Debian/', $this->userAgent)) {
                $platform['name'] = 'Debian';
            } elseif (preg_match('/Fedora/', $this->userAgent)) {
                $platform['name'] = 'Fedora';
            } else {
                $platform['name'] = 'Linux';
            }
            $platform['architecture'] = preg_match('/x86_64|amd64/', $this->userAgent) ? 'x64' : 'x86';
        }
        // Android detection
        elseif (preg_match('/Android ([0-9.]+)/', $this->userAgent, $matches)) {
            $platform['name'] = 'Android';
            $platform['version'] = $matches[1];
            $platform['architecture'] = preg_match('/arm64|aarch64/', $this->userAgent) ? 'ARM64' : 'ARM';
        }
        // iOS detection
        elseif (preg_match('/iPhone OS ([0-9_]+)/', $this->userAgent, $matches)) {
            $platform['name'] = 'iOS';
            $platform['version'] = str_replace('_', '.', $matches[1]);
            $platform['architecture'] = 'ARM';
        }
        // iPadOS detection
        elseif (preg_match('/iPad.*OS ([0-9_]+)/', $this->userAgent, $matches)) {
            $platform['name'] = 'iPadOS';
            $platform['version'] = str_replace('_', '.', $matches[1]);
            $platform['architecture'] = 'ARM';
        }
        
        return $platform;
    }
    
    /**
     * Detect device information
     * 
     * @return array Device info
     */
    private function detectDevice(): array
    {
        $device = [
            'type' => 'Desktop',
            'brand' => 'Unknown',
            'model' => 'Unknown',
            'is_mobile' => false,
            'is_tablet' => false,
            'is_desktop' => true,
            'is_touch' => false,
            'screen_size' => 'Unknown'
        ];
        
        // Mobile detection
        if (preg_match('/iPhone|iPod/', $this->userAgent)) {
            $device['type'] = 'Mobile';
            $device['brand'] = 'Apple';
            $device['is_mobile'] = true;
            $device['is_desktop'] = false;
            $device['is_touch'] = true;
            
            if (preg_match('/iPhone(\d+,\d+)/', $this->userAgent, $matches)) {
                $device['model'] = 'iPhone ' . $matches[1];
            }
        }
        // Tablet detection
        elseif (preg_match('/iPad/', $this->userAgent)) {
            $device['type'] = 'Tablet';
            $device['brand'] = 'Apple';
            $device['is_tablet'] = true;
            $device['is_desktop'] = false;
            $device['is_touch'] = true;
            
            if (preg_match('/iPad(\d+,\d+)/', $this->userAgent, $matches)) {
                $device['model'] = 'iPad ' . $matches[1];
            }
        }
        // Android devices
        elseif (preg_match('/Android/', $this->userAgent)) {
            if (preg_match('/Mobile/', $this->userAgent)) {
                $device['type'] = 'Mobile';
                $device['is_mobile'] = true;
                $device['is_desktop'] = false;
            } else {
                $device['type'] = 'Tablet';
                $device['is_tablet'] = true;
                $device['is_desktop'] = false;
            }
            $device['is_touch'] = true;
            
            // Detect Android brands
            if (preg_match('/Samsung/', $this->userAgent)) {
                $device['brand'] = 'Samsung';
            } elseif (preg_match('/Huawei/', $this->userAgent)) {
                $device['brand'] = 'Huawei';
            } elseif (preg_match('/Xiaomi/', $this->userAgent)) {
                $device['brand'] = 'Xiaomi';
            } elseif (preg_match('/OnePlus/', $this->userAgent)) {
                $device['brand'] = 'OnePlus';
            } elseif (preg_match('/LG/', $this->userAgent)) {
                $device['brand'] = 'LG';
            } elseif (preg_match('/Sony/', $this->userAgent)) {
                $device['brand'] = 'Sony';
            }
        }
        // Windows Phone
        elseif (preg_match('/Windows Phone|BlackBerry|BB10/', $this->userAgent)) {
            $device['type'] = 'Mobile';
            $device['is_mobile'] = true;
            $device['is_desktop'] = false;
            $device['is_touch'] = true;
        }
        // Gaming consoles
        elseif (preg_match('/PlayStation|Xbox|Nintendo/', $this->userAgent)) {
            $device['type'] = 'Gaming Console';
            $device['is_desktop'] = false;
            
            if (preg_match('/PlayStation/', $this->userAgent)) {
                $device['brand'] = 'Sony';
            } elseif (preg_match('/Xbox/', $this->userAgent)) {
                $device['brand'] = 'Microsoft';
            } elseif (preg_match('/Nintendo/', $this->userAgent)) {
                $device['brand'] = 'Nintendo';
            }
        }
        // Smart TV
        elseif (preg_match('/Smart-TV|SmartTV|TV/', $this->userAgent)) {
            $device['type'] = 'Smart TV';
            $device['is_desktop'] = false;
        }
        // Desktop/Laptop detection for touch capability
        elseif (preg_match('/Touch/', $this->userAgent)) {
            $device['is_touch'] = true;
        }
        
        return $device;
    }
    
    /**
     * Detect real IP address (behind proxies, CDN, etc.)
     * 
     * @return array IP info
     */
    private function detectRealIP(): array
    {
        $ip = ['address' => '0.0.0.0', 'type' => 'unknown', 'source' => 'default'];
        
        foreach (self::IP_HEADERS as $header) {
            if (array_key_exists($header, $this->serverData)) {
                $headerIP = $this->serverData[$header];
                
                if (strpos($headerIP, ',') !== false) {
                    $headerIP = explode(',', $headerIP)[0];
                }
                
                $headerIP = trim($headerIP);
                
                if (filter_var($headerIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip['address'] = $headerIP;
                    $ip['type'] = 'public';
                    $ip['source'] = $header;
                    break;
                } elseif (filter_var($headerIP, FILTER_VALIDATE_IP)) {
                    if ($ip['address'] === '0.0.0.0') {
                        $ip['address'] = $headerIP;
                        $ip['type'] = $this->getIPType($headerIP);
                        $ip['source'] = $header;
                    }
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        if ($ip['address'] === '0.0.0.0') {
            $ip['address'] = $this->serverData['REMOTE_ADDR'] ?? '0.0.0.0';
            $ip['type'] = $this->getIPType($ip['address']);
            $ip['source'] = 'REMOTE_ADDR';
        }
        
        return $ip;
    }
    
    /**
     * Get IP address type (public, private, localhost, etc.)
     * 
     * @param string $ip IP address
     * @return string IP type
     */
    private function getIPType(string $ip): string
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'localhost';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'public';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'private';
        } else {
            return 'invalid';
        }
    }
    
    /**
     * Check if user agent is a bot/crawler
     * 
     * @return bool True if bot
     */
    public function isBot(): bool
    {
        $ua = strtolower($this->userAgent);
        
        foreach (self::BOT_PATTERNS as $pattern) {
            if (strpos($ua, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if device is mobile
     * 
     * @return bool True if mobile
     */
    public function isMobile(): bool
    {
        return $this->analyze()['is_mobile'];
    }
    
    /**
     * Check if device is tablet
     * 
     * @return bool True if tablet
     */
    public function isTablet(): bool
    {
        return $this->analyze()['is_tablet'];
    }
    
    /**
     * Check if device is desktop
     * 
     * @return bool True if desktop
     */
    public function isDesktop(): bool
    {
        return $this->analyze()['is_desktop'];
    }
    
    /**
     * Get browser information
     * 
     * @return array Browser info
     */
    public function getBrowser(): array
    {
        $analysis = $this->analyze();
        return [
            'name' => $analysis['browser'],
            'version' => $analysis['browser_version'],
            'engine' => $analysis['browser_engine']
        ];
    }
    
    /**
     * Get platform information
     * 
     * @return array Platform info
     */
    public function getPlatform(): array
    {
        $analysis = $this->analyze();
        return [
            'name' => $analysis['platform'],
            'version' => $analysis['platform_version']
        ];
    }
    
    /**
     * Get device information
     * 
     * @return array Device info
     */
    public function getDevice(): array
    {
        $analysis = $this->analyze();
        return [
            'type' => strtolower($analysis['device_type']),
            'brand' => $analysis['device_brand'],
            'is_mobile' => $analysis['is_mobile'],
            'is_tablet' => $analysis['is_tablet'],
            'is_desktop' => $analysis['is_desktop'],
            'is_touch' => $analysis['is_touch']
        ];
    }
    
    /**
     * Get real IP address
     * 
     * @return string IP address
     */
    public function getRealIP(): string
    {
        return $this->analyze()['ip_address'];
    }
    
    /**
     * Get full IP information
     * 
     * @return array IP info
     */
    public function getIPInfo(): array
    {
        $ip = $this->detectRealIP();
        return [
            'address' => $ip['address'],
            'type' => $ip['type'],
            'source' => $ip['source']
        ];
    }
    
    /**
     * Check WebGL support (heuristic)
     * 
     * @return bool Likely WebGL support
     */
    private function supportsWebGL(): bool
    {
        // Modern browsers generally support WebGL
        $browser = $this->detectBrowser();
        $modernBrowsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];
        
        return in_array($browser['name'], $modernBrowsers) && 
               version_compare($browser['version'], '10.0', '>=');
    }
    
    /**
     * Check WebRTC support (heuristic)
     * 
     * @return bool Likely WebRTC support
     */
    private function supportsWebRTC(): bool
    {
        $browser = $this->detectBrowser();
        $webrtcBrowsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];
        
        return in_array($browser['name'], $webrtcBrowsers);
    }
    
    /**
     * Check WebSocket support (heuristic)
     * 
     * @return bool Likely WebSocket support
     */
    private function supportsWebSocket(): bool
    {
        $browser = $this->detectBrowser();
        $modernBrowsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera', 'Internet Explorer'];
        
        return in_array($browser['name'], $modernBrowsers);
    }
    
    /**
     * Detect privacy/incognito mode (heuristic)
     * 
     * @return bool Possibly in privacy mode
     */
    private function detectPrivacyMode(): bool
    {
        // This is difficult to detect server-side, mostly heuristic
        return isset($this->serverData['HTTP_DNT']) && $this->serverData['HTTP_DNT'] === '1';
    }
    
    /**
     * Get Do Not Track setting
     * 
     * @return bool DNT enabled
     */
    private function getDoNotTrack(): bool
    {
        return isset($this->serverData['HTTP_DNT']) && $this->serverData['HTTP_DNT'] === '1';
    }
    
    /**
     * Check if cookies are accepted (heuristic)
     * 
     * @return bool Likely accepts cookies
     */
    private function acceptsCookies(): bool
    {
        // This is a heuristic - most browsers accept cookies by default
        return !$this->isBot();
    }
    
    /**
     * Get connection type from headers
     * 
     * @return string Connection type
     */
    private function getConnectionType(): string
    {
        if (isset($this->serverData['HTTP_CONNECTION'])) {
            return $this->serverData['HTTP_CONNECTION'];
        }
        
        return 'Unknown';
    }
    
    /**
     * Get accepted encoding
     * 
     * @return array Accepted encodings
     */
    private function getAcceptedEncoding(): array
    {
        if (isset($this->serverData['HTTP_ACCEPT_ENCODING'])) {
            return array_map('trim', explode(',', $this->serverData['HTTP_ACCEPT_ENCODING']));
        }
        
        return [];
    }
    
    /**
     * Get primary language
     * 
     * @return string Primary language
     */
    private function getLanguage(): string
    {
        if (isset($this->serverData['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $this->serverData['HTTP_ACCEPT_LANGUAGE']);
            return trim(explode(';', $languages[0])[0]);
        }
        
        return 'Unknown';
    }
    
    /**
     * Get all accepted languages
     * 
     * @return array All languages
     */
    private function getLanguages(): array
    {
        if (isset($this->serverData['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $this->serverData['HTTP_ACCEPT_LANGUAGE']);
            return array_map(function($lang) {
                return trim(explode(';', $lang)[0]);
            }, $languages);
        }
        
        return [];
    }
    
    /**
     * Get device capabilities
     * 
     * @return array Device capabilities
     */
    public function getDeviceCapabilities(): array
    {
        $analysis = $this->analyze();
        
        return [
            'supports_webgl' => $analysis['supports_webgl'],
            'supports_webrtc' => $analysis['supports_webrtc'],
            'supports_websocket' => $analysis['supports_websocket'],
            'is_touch' => $analysis['is_touch'],
            'accepts_cookies' => $analysis['accepts_cookies'],
            'languages' => $analysis['languages'],
            'accepted_encoding' => $analysis['accepts_encoding']
        ];
    }
    
    /**
     * Get security information
     * 
     * @return array Security info
     */
    public function getSecurityInfo(): array
    {
        $analysis = $this->analyze();
        
        return [
            'is_bot' => $analysis['is_bot'],
            'privacy_mode' => $analysis['privacy_mode'],
            'do_not_track' => $analysis['do_not_track'],
            'ip_type' => $analysis['ip_type'],
            'suspicious_patterns' => $this->detectSuspiciousPatterns()
        ];
    }
    
    /**
     * Detect suspicious patterns in user agent
     * 
     * @return array Suspicious patterns found
     */
    private function detectSuspiciousPatterns(): array
    {
        $suspicious = [];
        $ua = $this->userAgent;
        
        // Check for common attack patterns
        if (preg_match('/<script|javascript:|data:|vbscript:/i', $ua)) {
            $suspicious[] = 'Script injection attempt';
        }
        
        if (preg_match('/\.\.\//i', $ua)) {
            $suspicious[] = 'Directory traversal attempt';
        }
        
        if (preg_match('/union.*select|drop.*table|insert.*into/i', $ua)) {
            $suspicious[] = 'SQL injection attempt';
        }
        
        if (strlen($ua) > 1000) {
            $suspicious[] = 'Unusually long user agent';
        }
        
        if (empty($ua)) {
            $suspicious[] = 'Empty user agent';
        }
        
        return $suspicious;
    }
    
    /**
     * Get full analysis with all available information
     * 
     * @return array Complete analysis
     */
    public function getFullAnalysis(): array
    {
        $analysis = $this->analyze();
        
        return array_merge($analysis, [
            'ip_info' => $this->getIPInfo(),
            'capabilities' => $this->getDeviceCapabilities(),
            'security' => $this->getSecurityInfo(),
            'raw_headers' => $this->getRawHeaders()
        ]);
    }
    
    /**
     * Get raw headers for debugging
     * 
     * @return array Raw headers
     */
    private function getRawHeaders(): array
    {
        $headers = [];
        
        foreach ($this->serverData as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Export analysis to JSON
     * 
     * @param bool $pretty Pretty print JSON
     * @return string JSON string
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($this->analyze(), $flags);
    }
    
    /**
     * Export analysis to array
     * 
     * @return array Analysis array
     */
    public function toArray(): array
    {
        return $this->analyze();
    }
    
    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString(): string
    {
        $analysis = $this->analyze();
        return sprintf(
            '%s %s on %s (%s) - IP: %s',
            $analysis['browser'],
            $analysis['browser_version'],
            $analysis['platform'],
            $analysis['device_type'],
            $analysis['ip_address']
        );
    }
    
    /**
     * Clear analysis cache (useful for testing)
     * 
     * @return self
     */
    public function clearCache(): self
    {
        $this->analysisCache = null;
        return $this;
    }
    
    /**
     * Set custom user agent for analysis
     * 
     * @param string $userAgent New user agent
     * @return self
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        $this->clearCache();
        return $this;
    }
    
    /**
     * Get current user agent
     * 
     * @return string Current user agent
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
    
    /**
     * Get geolocation information from IP address
     * 
     * @param string|null $ip IP address (null = auto detect)
     * @param bool $useCache Use cached result
     * @return array Geolocation info
     */
    public function getGeolocation(?string $ip = null, bool $useCache = true): array
    {
        // Use cached result if available
        if ($useCache && $this->geolocationCache !== null) {
            return $this->geolocationCache;
        }
        
        // Get IP address
        if ($ip === null) {
            $ip = $this->getRealIP();
        }
        
        // Check if IP is local/private
        $ipType = $this->getIPType($ip);
        if ($ipType === 'localhost' || $ipType === 'private') {
            $this->geolocationCache = [
                'success' => false,
                'error' => 'Cannot geolocate local/private IP addresses',
                'ip' => $ip,
                'ip_type' => $ipType,
                'country' => 'Unknown',
                'country_code' => 'XX',
                'province' => 'Unknown',
                'city' => 'Unknown',
                'district' => 'Unknown',
                'zip' => 'Unknown',
                'latitude' => 0,
                'longitude' => 0,
                'timezone' => 'Unknown',
                'isp' => 'Unknown',
            ];
            return $this->geolocationCache;
        }
        
        // Try each API endpoint until one succeeds
        foreach (self::GEO_API_ENDPOINTS as $apiName => $endpoint) {
            try {
                $result = $this->fetchGeolocation($ip, $endpoint, $apiName);
                if ($result['success']) {
                    $this->geolocationCache = $result;
                    return $result;
                }
            } catch (\Exception $e) {
                // Continue to next API
                continue;
            }
        }
        
        // All APIs failed
        $this->geolocationCache = [
            'success' => false,
            'error' => 'All geolocation APIs failed',
            'ip' => $ip,
            'ip_type' => $ipType,
            'country' => 'Unknown',
            'country_code' => 'XX',
            'province' => 'Unknown',
            'city' => 'Unknown',
            'district' => 'Unknown',
            'zip' => 'Unknown',
            'latitude' => 0,
            'longitude' => 0,
            'timezone' => 'Unknown',
            'isp' => 'Unknown',
        ];
        
        return $this->geolocationCache;
    }
    
    /**
     * Fetch geolocation from specific API
     * 
     * @param string $ip IP address
     * @param string $endpoint API endpoint
     * @param string $apiName API name
     * @return array Geolocation result
     */
    private function fetchGeolocation(string $ip, string $endpoint, string $apiName): array
    {
        $url = str_replace('{ip}', $ip, $endpoint);
        
        // Use cURL for better control
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'NexaAgent/1.0 (PHP User Agent Analyzer)',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            throw new \Exception("API request failed: {$error}");
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new \Exception("Invalid JSON response");
        }
        
        // Parse response based on API
        return $this->parseGeolocationResponse($data, $apiName, $ip);
    }
    
    /**
     * Parse geolocation response from different APIs
     * 
     * @param array $data Response data
     * @param string $apiName API name
     * @param string $ip IP address
     * @return array Normalized geolocation data
     */
    private function parseGeolocationResponse(array $data, string $apiName, string $ip): array
    {
        $result = [
            'success' => false,
            'api' => $apiName,
            'ip' => $ip,
            'ip_type' => 'public',
            'country' => 'Unknown',
            'country_code' => 'XX',
            'province' => 'Unknown',
            'city' => 'Unknown',
            'district' => 'Unknown',
            'zip' => 'Unknown',
            'latitude' => 0,
            'longitude' => 0,
            'timezone' => 'Unknown',
            'isp' => 'Unknown',
            'organization' => 'Unknown',
            'as_number' => 'Unknown',
        ];
        
        // Parse based on API format
        if ($apiName === 'ipapi') {
            // ip-api.com format
            if (isset($data['status']) && $data['status'] === 'success') {
                $result['success'] = true;
                $result['country'] = $data['country'] ?? 'Unknown';
                $result['country_code'] = $data['countryCode'] ?? 'XX';
                $result['province'] = $data['regionName'] ?? 'Unknown';
                $result['region_code'] = $data['region'] ?? 'Unknown';
                $result['city'] = $data['city'] ?? 'Unknown';
                $result['district'] = $data['district'] ?? 'Unknown';
                $result['zip'] = $data['zip'] ?? 'Unknown';
                $result['latitude'] = $data['lat'] ?? 0;
                $result['longitude'] = $data['lon'] ?? 0;
                $result['timezone'] = $data['timezone'] ?? 'Unknown';
                $result['isp'] = $data['isp'] ?? 'Unknown';
                $result['organization'] = $data['org'] ?? 'Unknown';
                $result['as_number'] = $data['as'] ?? 'Unknown';
            }
        } elseif ($apiName === 'ipwhois') {
            // ipwhoapi.com format
            if (isset($data['success']) && $data['success'] === true) {
                $result['success'] = true;
                $result['country'] = $data['country'] ?? 'Unknown';
                $result['country_code'] = $data['country_code'] ?? 'XX';
                $result['province'] = $data['region'] ?? 'Unknown';
                $result['city'] = $data['city'] ?? 'Unknown';
                $result['zip'] = $data['postal'] ?? 'Unknown';
                $result['latitude'] = $data['latitude'] ?? 0;
                $result['longitude'] = $data['longitude'] ?? 0;
                $result['timezone'] = $data['timezone'] ?? 'Unknown';
                $result['isp'] = $data['isp'] ?? 'Unknown';
                $result['organization'] = $data['org'] ?? 'Unknown';
            }
        } elseif ($apiName === 'ipgeolocation') {
            // ipapi.co format
            if (isset($data['ip'])) {
                $result['success'] = true;
                $result['country'] = $data['country_name'] ?? 'Unknown';
                $result['country_code'] = $data['country_code'] ?? 'XX';
                $result['province'] = $data['region'] ?? 'Unknown';
                $result['city'] = $data['city'] ?? 'Unknown';
                $result['zip'] = $data['postal'] ?? 'Unknown';
                $result['latitude'] = $data['latitude'] ?? 0;
                $result['longitude'] = $data['longitude'] ?? 0;
                $result['timezone'] = $data['timezone'] ?? 'Unknown';
                $result['isp'] = $data['org'] ?? 'Unknown';
            }
        }
        
        return $result;
    }
    
    /**
     * Get country information
     * 
     * @return array Country info
     */
    public function getCountry(): array
    {
        $geo = $this->getGeolocation();
        return [
            'name' => $geo['country'],
            'code' => $geo['country_code'],
        ];
    }
    
    /**
     * Get province/region information
     * 
     * @return string Province name
     */
    public function getProvince(): string
    {
        $geo = $this->getGeolocation();
        return $geo['province'];
    }
    
    /**
     * Get city information
     * 
     * @return string City name
     */
    public function getCity(): string
    {
        $geo = $this->getGeolocation();
        return $geo['city'];
    }
    
    /**
     * Get district/kabupaten information
     * 
     * @return string District name
     */
    public function getDistrict(): string
    {
        $geo = $this->getGeolocation();
        return $geo['district'] ?? 'Unknown';
    }
    
    /**
     * Get kabupaten (alias for getDistrict)
     * 
     * @return string Kabupaten name
     */
    public function getKabupaten(): string
    {
        return $this->getDistrict();
    }
    
    /**
     * Get propinsi (alias for getProvince)
     * 
     * @return string Propinsi name
     */
    public function getPropinsi(): string
    {
        return $this->getProvince();
    }
    
    /**
     * Get coordinates (latitude, longitude)
     * 
     * @return array Coordinates
     */
    public function getCoordinates(): array
    {
        $geo = $this->getGeolocation();
        return [
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
        ];
    }
    
    /**
     * Get timezone
     * 
     * @return string Timezone
     */
    public function getTimezone(): string
    {
        $geo = $this->getGeolocation();
        return $geo['timezone'];
    }
    
    /**
     * Get ISP information
     * 
     * @return array ISP info
     */
    public function getISP(): array
    {
        $geo = $this->getGeolocation();
        return [
            'isp' => $geo['isp'] ?? 'Unknown',
            'organization' => $geo['organization'] ?? 'Unknown',
            'as_number' => $geo['as_number'] ?? 'Unknown',
        ];
    }
    
    /**
     * Get full location string (City, Province, Country)
     * 
     * @return string Full location
     */
    public function getFullLocation(): string
    {
        $geo = $this->getGeolocation();
        
        $parts = [];
        if ($geo['city'] !== 'Unknown') {
            $parts[] = $geo['city'];
        }
        if ($geo['province'] !== 'Unknown') {
            $parts[] = $geo['province'];
        }
        if ($geo['country'] !== 'Unknown') {
            $parts[] = $geo['country'];
        }
        
        return implode(', ', $parts) ?: 'Unknown';
    }
    
    /**
     * Get Indonesian location string (Kabupaten, Propinsi)
     * 
     * @return string Indonesian location format
     */
    public function getLokasiIndonesia(): string
    {
        $geo = $this->getGeolocation();
        
        // Check if it's Indonesia
        if ($geo['country_code'] !== 'ID') {
            return $this->getFullLocation();
        }
        
        $parts = [];
        if ($geo['district'] !== 'Unknown' && !empty($geo['district'])) {
            $parts[] = 'Kab. ' . $geo['district'];
        }
        if ($geo['city'] !== 'Unknown') {
            $parts[] = $geo['city'];
        }
        if ($geo['province'] !== 'Unknown') {
            $parts[] = 'Prov. ' . $geo['province'];
        }
        
        return implode(', ', $parts) ?: 'Indonesia';
    }
    
    /**
     * Check if location is in Indonesia
     * 
     * @return bool True if in Indonesia
     */
    public function isIndonesia(): bool
    {
        $geo = $this->getGeolocation();
        return $geo['country_code'] === 'ID';
    }
    
    /**
     * Clear geolocation cache
     * 
     * @return self
     */
    public function clearGeolocationCache(): self
    {
        $this->geolocationCache = null;
        return $this;
    }
    
    /**
     * Get full analysis with geolocation
     * This is an enhanced version that includes geolocation data
     * 
     * @param bool $includeGeolocation Include geolocation data
     * @return array Complete analysis with geolocation
     */
    public function getFullAnalysisWithGeo(bool $includeGeolocation = true): array
    {
        $analysis = $this->getFullAnalysis();
        
        if ($includeGeolocation) {
            $analysis['geolocation'] = $this->getGeolocation();
            $analysis['location_string'] = $this->getFullLocation();
            $analysis['is_indonesia'] = $this->isIndonesia();
        }
        
        return $analysis;
    }
} 