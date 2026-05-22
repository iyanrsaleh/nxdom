<?php
/**
 * NexaAgent - Contoh Penggunaan dengan Geolocation
 * 
 * File ini berisi berbagai contoh penggunaan NexaAgent
 * untuk deteksi user agent dan geolocation (Propinsi, Kabupaten, dll)
 */

require_once __DIR__ . '/NexaAgent.php';

use App\System\Helpers\NexaAgent;

// ============================================================================
// CONTOH 1: Penggunaan Dasar - Deteksi Browser & Device
// ============================================================================

echo "=== CONTOH 1: Deteksi Browser & Device ===\n\n";

$agent = new NexaAgent();

// Analisis lengkap
$info = $agent->analyze();
echo "Browser: {$info['browser']} {$info['browser_version']}\n";
echo "Platform: {$info['platform']}\n";
echo "Device: {$info['device_type']}\n";
echo "IP Address: {$info['ip_address']}\n";
echo "Is Mobile: " . ($info['is_mobile'] ? 'Yes' : 'No') . "\n";
echo "Is Bot: " . ($info['is_bot'] ? 'Yes' : 'No') . "\n\n";


// ============================================================================
// CONTOH 2: Geolocation - Mendapatkan Propinsi & Kabupaten
// ============================================================================

echo "=== CONTOH 2: Geolocation (Propinsi & Kabupaten) ===\n\n";

$agent = new NexaAgent();

// Mendapatkan informasi geolocation lengkap
$geo = $agent->getGeolocation();

if ($geo['success']) {
    echo "✅ Geolocation berhasil dideteksi:\n";
    echo "Negara: {$geo['country']} ({$geo['country_code']})\n";
    echo "Propinsi: {$geo['province']}\n";
    echo "Kota: {$geo['city']}\n";
    echo "Kabupaten/District: {$geo['district']}\n";
    echo "Kode Pos: {$geo['zip']}\n";
    echo "Koordinat: {$geo['latitude']}, {$geo['longitude']}\n";
    echo "Timezone: {$geo['timezone']}\n";
    echo "ISP: {$geo['isp']}\n\n";
} else {
    echo "❌ Geolocation gagal: {$geo['error']}\n\n";
}


// ============================================================================
// CONTOH 3: Quick Access Methods - Akses Cepat
// ============================================================================

echo "=== CONTOH 3: Quick Access Methods ===\n\n";

$agent = new NexaAgent();

// Akses cepat ke informasi geografis
echo "Propinsi: " . $agent->getPropinsi() . "\n";
echo "Kabupaten: " . $agent->getKabupaten() . "\n";
echo "Kota: " . $agent->getCity() . "\n";

// Mendapatkan negara
$negara = $agent->getCountry();
echo "Negara: {$negara['name']} ({$negara['code']})\n";

// Mendapatkan koordinat
$koordinat = $agent->getCoordinates();
echo "Latitude: {$koordinat['latitude']}\n";
echo "Longitude: {$koordinat['longitude']}\n";

// Mendapatkan timezone
echo "Timezone: " . $agent->getTimezone() . "\n\n";


// ============================================================================
// CONTOH 4: Formatted Location Strings
// ============================================================================

echo "=== CONTOH 4: Formatted Location Strings ===\n\n";

$agent = new NexaAgent();

// Format lokasi lengkap
echo "Full Location: " . $agent->getFullLocation() . "\n";

// Format lokasi Indonesia (dengan label Kab. & Prov.)
echo "Lokasi Indonesia: " . $agent->getLokasiIndonesia() . "\n\n";


// ============================================================================
// CONTOH 5: ISP Information
// ============================================================================

echo "=== CONTOH 5: ISP Information ===\n\n";

$agent = new NexaAgent();

$isp = $agent->getISP();
echo "ISP: {$isp['isp']}\n";
echo "Organization: {$isp['organization']}\n";
echo "AS Number: {$isp['as_number']}\n\n";


// ============================================================================
// CONTOH 6: Conditional Logic - Pengunjung dari Indonesia
// ============================================================================

echo "=== CONTOH 6: Conditional Logic ===\n\n";

$agent = new NexaAgent();

if ($agent->isIndonesia()) {
    echo "✅ Pengunjung dari Indonesia\n";
    echo "Propinsi: " . $agent->getPropinsi() . "\n";
    echo "Lokasi: " . $agent->getLokasiIndonesia() . "\n";
} else {
    echo "🌍 Pengunjung dari luar Indonesia\n";
    echo "Lokasi: " . $agent->getFullLocation() . "\n";
}
echo "\n";


// ============================================================================
// CONTOH 7: Custom IP Geolocation
// ============================================================================

echo "=== CONTOH 7: Custom IP Geolocation ===\n\n";

$agent = new NexaAgent();

// Cek lokasi dari IP tertentu (contoh: Google DNS)
$customGeo = $agent->getGeolocation('8.8.8.8', false);

echo "IP: {$customGeo['ip']}\n";
echo "Country: {$customGeo['country']}\n";
echo "City: {$customGeo['city']}\n\n";


// ============================================================================
// CONTOH 8: Full Analysis dengan Geolocation
// ============================================================================

echo "=== CONTOH 8: Full Analysis dengan Geolocation ===\n\n";

$agent = new NexaAgent();

$fullAnalysis = $agent->getFullAnalysisWithGeo();

echo "Browser: {$fullAnalysis['browser']} {$fullAnalysis['browser_version']}\n";
echo "Platform: {$fullAnalysis['platform']}\n";
echo "Device: {$fullAnalysis['device_type']}\n";
echo "IP: {$fullAnalysis['ip_address']}\n";
echo "Location: {$fullAnalysis['location_string']}\n";
echo "Is Indonesia: " . ($fullAnalysis['is_indonesia'] ? 'Yes' : 'No') . "\n\n";


// ============================================================================
// CONTOH 9: JSON Export
// ============================================================================

echo "=== CONTOH 9: JSON Export ===\n\n";

$agent = new NexaAgent();

// Export ke JSON (pretty print)
$json = $agent->toJson(true);
echo "JSON Output (first 500 chars):\n";
echo substr($json, 0, 500) . "...\n\n";


// ============================================================================
// CONTOH 10: Practical Use Case - Membuat Welcome Message
// ============================================================================

echo "=== CONTOH 10: Practical Use Case - Welcome Message ===\n\n";

$agent = new NexaAgent();

function createWelcomeMessage($agent) {
    $device = $agent->isMobile() ? 'ponsel' : ($agent->isTablet() ? 'tablet' : 'komputer');
    $browser = $agent->getBrowser();
    
    $message = "Selamat datang! Anda mengakses dari {$device} menggunakan {$browser['name']}. ";
    
    if ($agent->isIndonesia()) {
        $propinsi = $agent->getPropinsi();
        $kota = $agent->getCity();
        $message .= "Terdeteksi lokasi Anda di {$kota}, {$propinsi}. ";
    } else {
        $location = $agent->getFullLocation();
        $message .= "Terdeteksi lokasi Anda di {$location}. ";
    }
    
    return $message;
}

echo createWelcomeMessage($agent) . "\n\n";


// ============================================================================
// CONTOH 11: Cache Management
// ============================================================================

echo "=== CONTOH 11: Cache Management ===\n\n";

$agent = new NexaAgent();

// First call - akan hit API
$geo1 = $agent->getGeolocation();
echo "First call (with cache): {$geo1['city']}\n";

// Second call - dari cache
$geo2 = $agent->getGeolocation();
echo "Second call (from cache): {$geo2['city']}\n";

// Clear cache
$agent->clearGeolocationCache();

// Third call - akan hit API lagi
$geo3 = $agent->getGeolocation(null, false);
echo "Third call (no cache): {$geo3['city']}\n\n";


// ============================================================================
// CONTOH 12: Error Handling
// ============================================================================

echo "=== CONTOH 12: Error Handling ===\n\n";

$agent = new NexaAgent();

// Coba dengan IP lokal (akan gagal)
$localGeo = $agent->getGeolocation('127.0.0.1', false);

if ($localGeo['success']) {
    echo "Geolocation: {$localGeo['city']}\n";
} else {
    echo "❌ Error: {$localGeo['error']}\n";
    echo "IP Type: {$localGeo['ip_type']}\n";
}

echo "\n";


// ============================================================================
// CONTOH 13: Untuk Statistik Website
// ============================================================================

echo "=== CONTOH 13: Website Statistics ===\n\n";

function logVisitorStats($agent) {
    $stats = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $agent->getRealIP(),
        'browser' => $agent->getBrowser()['name'],
        'device' => $agent->getDevice()['type'],
        'platform' => $agent->getPlatform()['name'],
        'is_mobile' => $agent->isMobile(),
        'is_bot' => $agent->isBot(),
        'country' => $agent->getCountry()['name'],
        'province' => $agent->getPropinsi(),
        'city' => $agent->getCity(),
        'district' => $agent->getKabupaten(),
        'timezone' => $agent->getTimezone(),
    ];
    
    return $stats;
}

$agent = new NexaAgent();
$visitorStats = logVisitorStats($agent);

echo "Visitor Statistics:\n";
foreach ($visitorStats as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'Yes' : 'No';
    }
    echo ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
}

echo "\n";


// ============================================================================
// CONTOH 14: Regional Content Delivery
// ============================================================================

echo "=== CONTOH 14: Regional Content Delivery ===\n\n";

function getRegionalContent($agent) {
    if (!$agent->isIndonesia()) {
        return "International content";
    }
    
    $province = $agent->getPropinsi();
    
    // Konten berdasarkan propinsi
    $regionalContent = [
        'Jawa Barat' => 'Wilujeng sumping di situs kami! (Sundanese)',
        'Jawa Tengah' => 'Sugeng rawuh ing situs kita! (Javanese)',
        'Bali' => 'Om Swastiastu! Rahajeng rauh! (Balinese)',
        'Sumatera Utara' => 'Horas! Njuah-juah di website ta! (Batak)',
    ];
    
    return $regionalContent[$province] ?? "Selamat datang di situs kami! (Indonesian)";
}

$agent = new NexaAgent();
echo "Regional Greeting: " . getRegionalContent($agent) . "\n\n";


// ============================================================================
// CONTOH 15: Security & Bot Detection dengan Location
// ============================================================================

echo "=== CONTOH 15: Security & Bot Detection ===\n\n";

function securityCheck($agent) {
    $security = $agent->getSecurityInfo();
    $geo = $agent->getGeolocation();
    
    $alerts = [];
    
    if ($security['is_bot']) {
        $alerts[] = "⚠️ Bot detected";
    }
    
    if (!empty($security['suspicious_patterns'])) {
        $alerts[] = "🚨 Suspicious patterns: " . implode(', ', $security['suspicious_patterns']);
    }
    
    if ($security['ip_type'] === 'private') {
        $alerts[] = "ℹ️ Private IP address";
    }
    
    if ($geo['success']) {
        $alerts[] = "📍 Location: {$geo['city']}, {$geo['country']}";
    }
    
    return $alerts;
}

$agent = new NexaAgent();
$securityAlerts = securityCheck($agent);

echo "Security Check Results:\n";
foreach ($securityAlerts as $alert) {
    echo $alert . "\n";
}

echo "\n=== End of Examples ===\n";
