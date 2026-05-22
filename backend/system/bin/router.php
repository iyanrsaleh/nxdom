<?php
/**
 * PHP CLI Server Router
 *
 * Router ini menangani request untuk PHP CLI server dan memastikan
 * file static (JS, CSS, images, dll) dikirim dengan MIME type yang benar.
 *
 * Lokasi: system/bin/router.php — akar proyek = dua tingkat di atas file ini.
 *
 * Usage (dari root proyek): php -S localhost:8000 system/bin/router.php
 */

$root = dirname(__DIR__, 2);

// MIME types mapping
$mimeTypes = [
    // JavaScript
    'js' => 'application/javascript',
    'mjs' => 'application/javascript',

    // CSS
    'css' => 'text/css',

    // Images
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',

    // Fonts
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',

    // Documents
    'pdf' => 'application/pdf',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'txt' => 'text/plain',
    'html' => 'text/html',
    'htm' => 'text/html',

    // Archives
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',

    // Video/Audio
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
];

// Get requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove query string for path matching
$uriPath = strtok($uri, '?');

// ═══════════════════════════════════════════════════════════════════════════════
// IMPORTANT: /drive and /images routes must go through PHP framework
// This allows DriveController and ImagesController to handle resize requests
// ═══════════════════════════════════════════════════════════════════════════════
$isDriveRoute = (strpos($uriPath, '/drive/') !== false);
$isImagesRoute = (strpos($uriPath, '/images/') !== false);

// If it's a /drive or /images route, pass to framework (don't serve directly)
if ($isDriveRoute || $isImagesRoute) {
    if (file_exists($root . '/index.php')) {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        require $root . '/index.php';
        return true;
    }
}

// Get file extension
$extension = strtolower(pathinfo($uriPath, PATHINFO_EXTENSION));
$uri = $uriPath; // Use path without query string for file operations

/**
 * /modules/{path} → assets/modules/{path}
 * Penting: nexa.css memuat puluhan @import; jangan bootstrap index.php per file.
 */
if ($extension && isset($mimeTypes[$extension]) && str_starts_with($uriPath, '/modules/')) {
    $modulesRel = substr($uriPath, strlen('/modules/'));
    $modulesRel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $modulesRel);
    $modulesFile = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $modulesRel;
    if (is_file($modulesFile)) {
        $mimeType = $mimeTypes[$extension];
        if ($extension === 'js' || $extension === 'mjs') {
            header('Content-Type: ' . $mimeType . '; charset=utf-8');
        } else {
            header('Content-Type: ' . $mimeType);
        }
        header('Cache-Control: public, max-age=31536000');
        readfile($modulesFile);
        return true;
    }
}

// Check if it's a static file request
if ($extension && isset($mimeTypes[$extension])) {
    $filePath = $root . $uri;

    // Check if file exists
    if (file_exists($filePath) && is_file($filePath)) {
        // Set correct MIME type
        $mimeType = $mimeTypes[$extension];

        // Special handling for JavaScript modules
        if ($extension === 'js' || $extension === 'mjs') {
            // Ensure JavaScript files are served with correct MIME type
            header('Content-Type: ' . $mimeType . '; charset=utf-8');
        } else {
            header('Content-Type: ' . $mimeType);
        }

        // Set cache headers for static assets (optional)
        if (in_array($extension, ['js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2'])) {
            header('Cache-Control: public, max-age=3600');
        }

        // Output file
        readfile($filePath);
        return true;
    }
}

// If not a static file or file doesn't exist, route to index.php
if (file_exists($root . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require $root . '/index.php';
    return true;
}

// 404 if nothing matches
http_response_code(404);
echo "404 Not Found";
return false;
