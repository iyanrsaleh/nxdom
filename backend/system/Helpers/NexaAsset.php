<?php
namespace App\System\Helpers;

use App\System\Helpers\NexaRequest;

/**
 * NexaAsset - Helper class untuk generate path asset
 * Mempermudah penulisan path asset dalam template
 * Integrated with NexaRequest for dynamic project paths
 * 
 * USAGE EXAMPLES:
 * 
 * === PHP Usage ===
 * NexaAsset::css('style.css')        -> /app/assets/css/style.css
 * NexaAsset::js('script.js')         -> /app/assets/js/script.js
 * NexaAsset::img('logo.png')         -> /app/assets/images/logo.png
 * NexaAsset::drive('file.pdf')       -> /app/assets/drive/file.pdf
 * NexaAsset::driveBase()             -> /app/assets/drive
 * NexaAsset::modules('Nexa.js')      -> /app/modules/Nexa.js
 * 
 * === Template Usage ===
 * {css/style.css}                    -> /app/assets/css/style.css
 * {js/script.js}                     -> /app/assets/js/script.js
 * {img/logo.png}                     -> /app/assets/images/logo.png
 * {drive/file.pdf}                   -> /app/assets/drive/file.pdf
 * {drive}                            -> /app/assets/drive
 * {modules/Nexa.js}                  -> /app/modules/Nexa.js
 * 
 * === Template dengan Variabel ===
 * {drive/{avatar}}                   -> resolve variabel avatar dalam path drive
 * {drive/avatar}                     -> jika 'avatar' adalah nama variabel
 * 
 * === Template dengan Filter ===
 * {drive/file.pdf|escape}            -> apply escape filter
 * {drive|escape}                     -> apply escape filter ke base path
 */
class NexaAsset
{
    // NexaRequest instance
    private static ?NexaRequest $request = null;
    
    // Base path untuk assets (akan dinamis)
    private static ?string $basePath = null;
    
    // Base path untuk templates (akan dinamis) 
    private static ?string $templatePath = null;
    
    // Slug directory untuk dynamic routing
    private static ?string $slugDir = null;

    /**
     * Pakai instance NexaRequest yang sama dengan bootstrap (Nexa) — hindari dua kali parse URI
     * dan pastikan path/base konsisten. Panggil dari Nexa::__construct() setelah new NexaRequest().
     */
    public static function setRequest(NexaRequest $request): void
    {
        self::$request = $request;
        self::$basePath = null;
        self::$templatePath = null;
        self::$slugDir = null;
    }

    /**
     * Pastikan ada NexaRequest (fallback: instans baru jika belum di-setRequest / CLI / test).
     */
    private static function ensureRequest(): void
    {
        if (self::$request === null) {
            self::$request = new NexaRequest();
        }
    }

    /**
     * Hitung base path dari request saat ini (dipanggil ulang setelah setRequest mengosongkan cache).
     */
    private static function ensurePaths(): void
    {
        if (self::$basePath !== null) {
            return;
        }

        try {
            $projectDir = self::$request->getBaseDirProyek();
            self::$slugDir = self::$request->getSlug(1);

            if (self::$slugDir !== null && !is_string(self::$slugDir)) {
                self::$slugDir = (string) self::$slugDir;
            }

            if (self::$slugDir !== null && self::$slugDir !== '') {
                $questionMarkPos = strpos(self::$slugDir, '?');
                if ($questionMarkPos !== false) {
                    self::$slugDir = substr(self::$slugDir, 0, $questionMarkPos);
                }
                self::$slugDir = rtrim(self::$slugDir, '/');
                if (self::$slugDir === '') {
                    self::$slugDir = null;
                }
            }

            if ($projectDir && is_string($projectDir)) {
                self::$basePath = '/' . $projectDir . '/assets';
                self::$templatePath = '/' . $projectDir . '/templates';
            } else {
                self::$basePath = '/assets';
                self::$templatePath = '/templates';
            }
        } catch (\Throwable $e) {
            self::$basePath = '/assets';
            self::$templatePath = '/templates';
            self::$slugDir = null;
        }
    }

    /**
     * Initialize NexaRequest and setup dynamic paths
     */
    private static function init(): void
    {
        self::ensureRequest();
        self::ensurePaths();
    }

    /**
     * Get current base path
     */
    public static function getBasePath(): string
    {
        self::init();
        return self::$basePath ?? '/assets';
    }

    /**
     * Get current template path
     */
    public static function getTemplatePath(): string
    {
        self::init();
        return self::$templatePath ?? '/templates';
    }


    /**
     * Generate path untuk Dashboard ASSETS (CSS, JS, Images)
     * Usage: NexaAsset::dashboard('nama.css')
     * Output: /project/assets/dashboard/nama.css (dynamic)
     * Note: For templates/dashboard/ use dash() method instead
     */
    public static function app(string $filename): string
    {
        self::init();
        $basePath = self::$templatePath ?? '/templates';
        return $basePath . '/assets/' . $filename;
    }




    /**
     * Get current slug directory
     */
    public static function getSlugDir(): ?string
    {
        self::init();
        return self::$slugDir;
    }

    /**
     * Generate path untuk template-aware assets (short form)
     * /assets/css/style.css otomatis resolve ke templates/{active}/assets/ atau assets/
     * Usage: NexaAsset::asset('css/style.css')
     * Output: /assets/css/style.css atau /project/assets/css/style.css
     */
    public static function asset(string $path): string
    {
        self::init();
        $path = ltrim($path, '/');
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/assets/' . $path;
        }
        return '/assets/' . $path;
    }

    /**
     * Generate path untuk CSS files
     * Usage: NexaAsset::css('nama.css') 
     * Output: /project/assets/css/nama.css (dynamic)
     */
    public static function css(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/css/' . $filename;
    }
    
    public static function dependencies(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/dependencies/' . $filename;
    }

    /**
     * Generate path untuk JavaScript files
     * Usage: NexaAsset::js('nama.js')
     * Output: /project/assets/js/nama.js (dynamic)
     */
    public static function js(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/js/' . $filename;
    }

    /**
     * Generate path untuk Image files (NEW: via ImagesController)
     * Usage: NexaAsset::img('nama.jpg')
     * Output: /images/nama.jpg or /project/images/nama.jpg (clean URL, routed via ImagesController)
     * Old: /project/assets/images/nama.jpg
     * New: /project/images/nama.jpg → Controller will resolve to assets/images/nama.jpg
     * Bonus: Support resize: NexaAsset::img('photo.jpg') . '?w=300&h=200'
     */
    public static function img(string $filename): string
    {
        self::init();
        // NEW: Use clean /images/ route with project directory support
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        
        // Only add project dir if it's not empty and not current directory
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/images/' . ltrim($filename, '/');
        }
        
        return '/images/' . ltrim($filename, '/');
    }



    /**
     * Generate path untuk Font files
     * Usage: NexaAsset::font('nama.ttf')
     * Output: /project/assets/fonts/nama.ttf (dynamic)
     */
    public static function font(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/fonts/' . $filename;
    }

    /**
     * Generate path untuk Dashboard ASSETS (CSS, JS, Images)
     * Usage: NexaAsset::dashboard('nama.css')
     * Output: /project/assets/dashboard/nama.css (dynamic)
     * Note: For templates/dashboard/ use dash() method instead
     */
    public static function dashboard(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/dashboard/' . $filename;
    }

    /**
     * Generate path untuk NexaUi assets
     * Usage: NexaAsset::nexaui('nama.js')
     * Output: /project/assets/NexaUi/nama.js (dynamic)
     */
    public static function nexaui(string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/NexaUI/' . $filename;
    }

    /**
     * Generate path untuk Drive assets (NEW: via DriveController)
     * Usage: NexaAsset::drive('nama.pdf')
     * Output: /drive/nama.pdf or /project/drive/nama.pdf (clean URL, routed via DriveController)
     * Old: /project/assets/drive/nama.pdf
     * New: /project/drive/nama.pdf → Controller will resolve to assets/drive/nama.pdf
     */
    public static function drive(string $filename): string
    {
        self::init();
        // NEW: Use clean /drive/ route with project directory support
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        
        // Only add project dir if it's not empty and not current directory
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/drive/' . ltrim($filename, '/');
        }
        
        return '/drive/' . ltrim($filename, '/');
    }

    /**
     * Generate path untuk Modules assets (NEW: via ModulesController)
     * Usage: NexaAsset::modules('Nexa.js')
     * Output: /modules/Nexa.js or /project/modules/Nexa.js (clean URL, routed via ModulesController)
     * Path: /project/modules/Nexa.js → Controller will resolve to assets/modules/Nexa.js
     * Support subdirectories: NexaAsset::modules('Select2/select2.min.js')
     */
    public static function modules(string $filename): string
    {
        self::init();
        // Use clean /modules/ route with project directory support
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        
        // Only add project dir if it's not empty and not current directory
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/modules/' . ltrim($filename, '/');
        }
        
        return '/modules/' . ltrim($filename, '/');
    }

    /**
     * Generate path untuk avatar (shortcut: /avatar/ → assets/drive/avatar/)
     * Usage: NexaAsset::avatar('2026/03/avatar_4569_1773162822.jpg')
     * Output: /avatar/2026/03/avatar_4569_1773162822.jpg
     */
    public static function avatar(string $path): string
    {
        self::init();
        $path = ltrim($path, '/');
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/avatar/' . $path;
        }
        return '/avatar/' . $path;
    }

    /**
     * Generate base path untuk Drive folder (tanpa filename)
     * Usage: NexaAsset::driveBase()
     * Output: /drive or /project/drive (clean URL)
     * Template Usage: {drive} (without filename)
     */
    public static function driveBase(): string
    {
        self::init();
        // NEW: Use clean /drive/ route with project directory support
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        
        // Only add project dir if it's not empty and not current directory
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/drive';
        }
        
        return '/drive';
    }

    /**
     * Generate path untuk asset custom
     * Usage: NexaAsset::path('custom/folder', 'nama.file')
     * Output: /project/assets/custom/folder/nama.file (dynamic)
     */
    public static function path(string $folder, string $filename): string
    {
        self::init();
        $basePath = self::$basePath ?? '/assets';
        return $basePath . '/' . $folder . '/' . $filename;
    }

    /**
     * Set custom base path (opsional)
     * Usage: NexaAsset::setBasePath('/custom/path')
     */
    public static function setBasePath(string $path): void
    {
        self::init();
        self::$basePath = rtrim($path, '/');
    }

    // ========== TEMPLATE ASSET METHODS ==========

    /**
     * URL publik file di templates/{folder}/ — sesuai TemplateController (/theme/, /mobile/, …).
     * Bukan path filesystem (/templates/...).
     */
    private static function templateServeUrl(string $folder, string $filename): string
    {
        self::init();
        $filename = ltrim(str_replace('\\', '/', $filename), '/');
        $projectDir = self::$request ? self::$request->getBaseDirProyek() : null;
        if ($projectDir && is_string($projectDir) && $projectDir !== '.' && $projectDir !== '') {
            return '/' . trim($projectDir, '/') . '/' . $folder . '/' . $filename;
        }
        return '/' . $folder . '/' . $filename;
    }

    /**
     * Generate path untuk Dashboard TEMPLATE assets (CSS, JS, Images)
     * Usage: NexaAsset::dash('style.css')
     * Output: /dashboard/slug/style.css (TemplateController)
     * Note: For assets/dashboard/ use dashboard() method instead
     */
    public static function dash(string $filename): string
    {
        self::init();

        $slugPath = '';
        if (self::$slugDir !== null && self::$slugDir !== '' && is_string(self::$slugDir)) {
            $slugPath = self::$slugDir . '/';
        }

        return self::templateServeUrl('dashboard', $slugPath . ltrim($filename, '/'));
    }

    public static function packages(string $filename): string
    {
        self::init();
        
        // FIXED: Robust handling for slugDir
        $slugPath = '';
        if (self::$slugDir !== null && self::$slugDir !== '' && is_string(self::$slugDir)) {
            $slugPath = self::$slugDir . '/';
        }

         if (!empty(self::$request->getBaseDirProyek())) {
            $templatePath='/'.self::$request->getBaseDirProyek();
         } else {
            $templatePath='';
         }
         
        // FIXED: Clean query parameters from slug(0) 
        $slug0 = self::$request->getSlug(0);
        if ($slug0 !== null && $slug0 !== '') {
            // Remove query parameters if present
            $questionMarkPos = strpos($slug0, '?');
            if ($questionMarkPos !== false) {
                $slug0 = substr($slug0, 0, $questionMarkPos);
            }
            // Remove any trailing slashes
            $slug0 = rtrim($slug0, '/');
        }
        
        return '/' . $slug0 . '/'. $slugPath . $filename;
    }




    /**
     * Generate path untuk Mobile template assets (CSS, JS, Images)
     * Usage: NexaAsset::mobile('mobile.css') — template: {mobile/mobile.css}
     * Output: /mobile/mobile.css (TemplateController)
     */
    public static function mobile(string $filename): string
    {
        return self::templateServeUrl('mobile', $filename);
    }

    /**
     * Generate path untuk Tablet template assets (CSS, JS, Images)
     * Usage: NexaAsset::tablet('tablet.css') — template: {tablet/tablet.css}
     * Output: /tablet/tablet.css (TemplateController)
     */
    public static function tablet(string $filename): string
    {
        return self::templateServeUrl('tablet', $filename);
    }

    /**
     * Generate path untuk Theme template assets (CSS, JS, Images)
     * Usage: NexaAsset::theme('docs/layout/modal/script.js') — template: {theme/docs/layout/modal/script.js}
     * Output: /theme/docs/layout/modal/script.js (TemplateController, bukan /templates/theme/...)
     */
    public static function theme(string $filename): string
    {
        return self::templateServeUrl('theme', $filename);
    }

    /**
     * Generate path untuk template asset custom (folder harus terdaftar di TemplateController)
     * Usage: NexaAsset::template('theme', 'docs/layout/modal/script.js')
     */
    public static function template(string $folder, string $filename): string
    {
        return self::templateServeUrl($folder, $filename);
    }

    /**
     * Set custom template path (opsional)
     * Usage: NexaAsset::setTemplatePath('/custom/templates')
     */
    public static function setTemplatePath(string $path): void
    {
        self::init();
        self::$templatePath = rtrim($path, '/');
    }

    /**
     * Set custom slug directory (opsional)
     * Usage: NexaAsset::setSlugDir('admin')
     */
    public static function setSlugDir(string $slugDir): void
    {
        self::init();
        // FIXED: Ensure slugDir is properly cleaned and remove query parameters
        if (!empty($slugDir)) {
            // Remove query parameters if present
            $questionMarkPos = strpos($slugDir, '?');
            if ($questionMarkPos !== false) {
                $slugDir = substr($slugDir, 0, $questionMarkPos);
            }
            
            // Remove any trailing slashes
            $slugDir = trim($slugDir, '/');
            
            // Set to null if empty after cleaning
            self::$slugDir = !empty($slugDir) ? $slugDir : null;
        } else {
            self::$slugDir = null;
        }
    }
} 