<?php
namespace App\System\Components;

class NexaModal {
    private static $cache = [];
    private static $modalStack = [];
    private static $fileCache = [];
    private static $utilityCache = [];
    
    public static function transform(string &$content): string 
    {
        // Early return if no Modal tags found
        if (strpos($content, '<Modal') === false) {
            return $content;
        }
        
        // Pertama parse konten dari Components
        $content = self::parseComponents($content);
        
        $pattern = '/<Modal\s*(.*?)>(.*?)<\/Modal>/is';
        
        return preg_replace_callback($pattern, function($matches) {
            $attributeString = $matches[1];
            $modalContent = $matches[2];
            
            // Create cache key for this modal configuration
            $cacheKey = md5($attributeString . $modalContent);
            
            // Return cached result if available
            if (isset(self::$cache[$cacheKey])) {
                return self::$cache[$cacheKey];
            }
            
            $attributes = self::parseAttributes($attributeString);
            
            // Cek apakah ada atribut Components dengan caching
            if (isset($attributes['Components'])) {
                $routePath = dirname(__DIR__, 3) . '/' . ltrim($attributes['Components'], '/');
                
                // Use file cache
                if (isset(self::$fileCache[$routePath])) {
                    $modalContent = self::$fileCache[$routePath];
                } elseif (file_exists($routePath)) {
                    $modalContent = file_get_contents($routePath);
                    self::$fileCache[$routePath] = $modalContent;
                } else {
                    $modalContent = '<p class="nx-error">File tidak ditemukan: ' . htmlspecialchars($attributes['Components']) . '</p>';
                }
            }
            
            $html = self::buildModalHTML($attributes, $modalContent);
            
            // Cache the result
            self::$cache[$cacheKey] = $html;
            
            return $html;
        }, $content);
    }
    
    /**
     * Parse konten dari Components tag
     * @param string $content
     * @return string
     */
    private static function parseComponents(string $content): string 
    {
        $pattern = '/@Components\(["\'](.+?)["\']\)/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $filePath = dirname(__DIR__, 3) . '/' . ltrim($matches[1], '/');
            
            if (file_exists($filePath)) {
                // Baca konten file langsung tanpa NexaUI::transform untuk menghindari circular reference
                return file_get_contents($filePath);
            }
            
            return '<p class="nx-error">File tidak ditemukan: ' . htmlspecialchars($matches[1]) . '</p>';
        }, $content);
    }
    
    /**
     * Parse atribut dari string atribut modal
     * @param string $attributeString
     * @return array
     */
    private static function parseAttributes(string $attributeString): array 
    {
        $attributes = [];
        
        // Parse atribut dengan nilai
        $pattern = '/([a-zA-Z0-9_-]+)\s*=\s*(["\'])(.*?)\2/s';
        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[3];
            }
        }
        
        // Parse atribut boolean (tanpa nilai)
        $booleanPattern = '/\b([a-zA-Z0-9_-]+)(?!\s*=)/';
        if (preg_match_all($booleanPattern, $attributeString, $boolMatches)) {
            foreach ($boolMatches[1] as $boolAttr) {
                // Hanya tambahkan jika belum ada (untuk menghindari override atribut dengan nilai)
                if (!isset($attributes[$boolAttr])) {
                    $attributes[$boolAttr] = true;
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Check if size contains utility classes
     * @param string $size
     * @return bool
     */
    private static function isUtilityClass(string $size): bool 
    {
        // Check for common utility patterns
        $utilityPatterns = [
            '/^w-/', // width utilities
            '/^h-/', // height utilities
            '/^max-w-/', // max-width utilities
            '/^max-h-/', // max-height utilities
            '/^min-w-/', // min-width utilities
            '/^min-h-/', // min-height utilities
        ];
        
        foreach ($utilityPatterns as $pattern) {
            if (preg_match($pattern, $size)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse utility classes and convert to CSS styles
     * @param string $sizeValue
     * @return string
     */
    private static function parseUtilityClasses(string $sizeValue): string 
    {
        $styles = [];
        $classes = explode(' ', $sizeValue);
        
        // Utility mappings based on Nexautility.php
        $utilities = [
            'w' => 'width',
            'h' => 'height',
            'min-w' => 'min-width',
            'min-h' => 'min-height',
            'max-w' => 'max-width',
            'max-h' => 'max-height',
        ];
        
        foreach ($classes as $class) {
            $class = trim($class);
            if (empty($class)) continue;
            
            // Parse utility class pattern: prefix-value
            foreach ($utilities as $prefix => $property) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '-(.+)$/', $class, $matches)) {
                    $value = $matches[1];
                    
                    // Add unit if numeric
                    if (is_numeric($value)) {
                        $value .= 'px';
                    }
                    
                    $styles[$property] = $value;
                    break;
                }
            }
        }
        
        // Convert styles array to CSS string
        $cssString = '';
        foreach ($styles as $property => $value) {
            $cssString .= $property . ':' . $value . ';';
        }
        
        return $cssString;
    }
    
    /**
     * Generate JavaScript untuk inisialisasi modal
     * @param string $modalId
     * @param array $options
     * @return string
     */
    public static function generateModalScript(string $modalId, array $options = []): string 
    {
        $defaultOptions = [
            'backdrop' => true,
            'keyboard' => true,
            'focus' => true,
            'show' => false
        ];
        
        $options = array_merge($defaultOptions, $options);
        $optionsJson = json_encode($options);
        
        return sprintf(
            '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const modal = document.getElementById("%s");
                    if (modal && typeof NexaModal !== "undefined") {
                        new NexaModal(modal, %s);
                    }
                });
            </script>',
            $modalId,
            $optionsJson
        );
    }
    
    /**
     * Generate modal trigger button
     * @param string $modalId
     * @param string $buttonText
     * @param array $buttonAttributes
     * @return string
     */
    public static function generateTriggerButton(string $modalId, string $buttonText, array $buttonAttributes = []): string 
    {
        $class = $buttonAttributes['class'] ?? 'btn btn-primary';
        $id = $buttonAttributes['id'] ?? '';
        $extraAttributes = '';
        
        foreach ($buttonAttributes as $key => $value) {
            if (!in_array($key, ['class', 'id'])) {
                $extraAttributes .= sprintf(' %s="%s"', $key, htmlspecialchars($value));
            }
        }
        
        return sprintf(
            '<button type="button" class="%s"%s onclick="openModal(\'%s\')"%s>%s</button>',
            $class,
            $id ? ' id="' . $id . '"' : '',
            $modalId,
            $extraAttributes,
            htmlspecialchars($buttonText)
        );
    }
    
    /**
     * Validate modal structure
     * @param string $content
     * @return array
     */
    public static function validateModalStructure(string $content): array 
    {
        $errors = [];
        $warnings = [];
        
        // Check for nested modals
        if (preg_match_all('/<Modal\s*.*?>/i', $content, $matches)) {
            if (count($matches[0]) > 1) {
                $warnings[] = 'Multiple modals detected. Consider using modal stacking properly.';
            }
        }
        
        // Check for missing title
        if (preg_match('/<Modal\s*.*?>/i', $content, $match)) {
            if (!preg_match('/title\s*=\s*["\'].*?["\']/i', $match[0])) {
                $warnings[] = 'Modal title not specified. Consider adding title attribute for accessibility.';
            }
        }
        
        // Check for form without id
        if (preg_match('/form\s*=\s*["\'].*?["\']/i', $content, $match)) {
            if (!preg_match('/id\s*=\s*["\'].*?["\']/i', $content)) {
                $errors[] = 'Modal with form attribute must have an id specified.';
            }
        }
        
        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => empty($errors)
        ];
    }
    
    /**
     * Build modal HTML with optimized performance
     * @param array $attributes
     * @param string $modalContent
     * @return string
     */
    private static function buildModalHTML(array $attributes, string $modalContent): string 
    {
        // Pisahkan footer dari konten utama
        $footer = '';
        if (preg_match('/<footer>(.*?)<\/footer>/is', $modalContent, $footerMatches)) {
            $footer = $footerMatches[1];
            $modalContent = preg_replace('/<footer>.*?<\/footer>/is', '', $modalContent);
        }
        
        // Ambil atribut-atribut modal dengan default values
        $id = $attributes['id'] ?? 'modal-' . uniqid();
        $title = $attributes['title'] ?? 'Modal';
        $size = $attributes['size'] ?? 'md';
        $formId = $attributes['form'] ?? '';
        $class = $attributes['class'] ?? '';
        $parent = $attributes['parent'] ?? '';
        $backdrop = $attributes['backdrop'] ?? 'true';
        $keyboard = $attributes['keyboard'] ?? 'true';
        $focus = $attributes['focus'] ?? 'true';
        $static = isset($attributes['static']) ? true : false;
        $footerAlign = $attributes['footer-align'] ?? 'right';
        
        // Tentukan class modal berdasarkan size dengan caching
        [$sizeClass, $customSizeStyle] = self::parseSizeWithCache($size);
        
        // Build modal class
        $modalClass = 'nx-modal ' . $class;
        if ($parent) {
            $modalClass .= ' nx-modal-child';
        }
        
        // Build data attributes efficiently
        $dataAttributes = self::buildDataAttributes($backdrop, $keyboard, $focus, $static);
        
        // Use array for efficient string building
        $htmlParts = [];
        
        // Modal wrapper
        $htmlParts[] = sprintf(
            '<div id="%s" class="%s" role="dialog" aria-labelledby="%s-title" aria-hidden="true" tabindex="-1"%s%s>',
            $id,
            $modalClass,
            $id,
            $parent ? ' data-parent="' . $parent . '"' : '',
            $dataAttributes
        );
        
        // Modal dialog wrapper
        if ($customSizeStyle) {
            $htmlParts[] = sprintf('<div class="nx-modal-dialog %s" style="%s">', $sizeClass, $customSizeStyle);
        } else {
            $htmlParts[] = sprintf('<div class="nx-modal-dialog %s">', $sizeClass);
        }
        
        // Modal content
        if ($formId) {
            $htmlParts[] = sprintf('<form class="nx-modal-content" id="from_%s"  action="%s" method="POST" role="form" novalidate>', $formId, $formId);
        } else {
            $htmlParts[] = '<div class="nx-modal-content">';
        }
       
            
       
        // Header
        $htmlParts[] = '<div class="nx-modal-header">';
        $htmlParts[] = sprintf('<h5 class="nx-modal-title" id="%s-title">%s</h5>', $id, htmlspecialchars($title));
        $htmlParts[] = sprintf('<button type="button" class="nx-modal-close" onclick="closeModal(\'%s\')" aria-label="Close">', $id);
        $htmlParts[] = '<span class="material-symbols-outlined">close</span>';
        $htmlParts[] = '</button>';
        $htmlParts[] = '</div>';
        
        // Body
        $htmlParts[] = '<div class="nx-modal-body">';
        // Replace data-dismiss="modal" in modal content
        $modalContent = str_replace('data-dismiss="modal"', "onclick=\"closeModal('$id')\"", $modalContent);
        $htmlParts[] = trim($modalContent);
        $htmlParts[] = '</div>';
        
        // Footer if exists
        if ($footer) {
            $htmlParts[] = '<div class="nx-modal-footer">';
            $htmlParts[] = self::buildFooterContent($footer, $footerAlign, $id);
            $htmlParts[] = '</div>';
        }
        
        // Close modal content
        $htmlParts[] = $formId ? '</form>' : '</div>';
        
        // Close modal dialog and modal
        $htmlParts[] = '</div>';
        $htmlParts[] = '</div>';
        
        return implode('', $htmlParts);
    }
    
    /**
     * Parse size with caching for better performance
     * @param string $size
     * @return array [$sizeClass, $customSizeStyle]
     */
    private static function parseSizeWithCache(string $size): array 
    {
        // Check cache first
        if (isset(self::$utilityCache[$size])) {
            return self::$utilityCache[$size];
        }
        
        $sizeClass = '';
        $customSizeStyle = '';
        
        // Check if size contains utility classes
        if (self::isUtilityClass($size)) {
            $sizeClass = 'nx-modal-custom';
            $customSizeStyle = self::parseUtilityClasses($size);
        } else {
            // Standard size classes
            $sizeClass = match($size) {
                'sm' => 'nx-modal-sm',
                'lg' => 'nx-modal-lg',
                'xl' => 'nx-modal-xl',
                default => 'nx-modal-md'
            };
        }
        
        $result = [$sizeClass, $customSizeStyle];
        
        // Cache the result
        self::$utilityCache[$size] = $result;
        
        return $result;
    }
    
    /**
     * Build data attributes efficiently
     * @param string $backdrop
     * @param string $keyboard
     * @param string $focus
     * @param bool $static
     * @return string
     */
    private static function buildDataAttributes(string $backdrop, string $keyboard, string $focus, bool $static): string 
    {
        $attributes = [];
        
        if ($backdrop === 'false' || $backdrop === 'static') {
            $attributes[] = 'data-backdrop="' . $backdrop . '"';
        }
        if ($keyboard === 'false') {
            $attributes[] = 'data-keyboard="false"';
        }
        if ($focus === 'false') {
            $attributes[] = 'data-focus="false"';
        }
        if ($static) {
            $attributes[] = 'data-static="true"';
        }
        
        return empty($attributes) ? '' : ' ' . implode(' ', $attributes);
    }
    
    /**
     * Clear all caches to free memory
     * @return void
     */
    public static function clearCache(): void 
    {
        self::$cache = [];
        self::$fileCache = [];
        self::$utilityCache = [];
        self::$modalStack = [];
    }
    
    /**
     * Get cache statistics for monitoring
     * @return array
     */
    public static function getCacheStats(): array 
    {
        return [
            'modal_cache_count' => count(self::$cache),
            'file_cache_count' => count(self::$fileCache),
            'utility_cache_count' => count(self::$utilityCache),
            'modal_stack_count' => count(self::$modalStack),
            'memory_usage' => [
                'modal_cache' => strlen(serialize(self::$cache)),
                'file_cache' => strlen(serialize(self::$fileCache)),
                'utility_cache' => strlen(serialize(self::$utilityCache)),
                'total_estimated' => strlen(serialize(self::$cache)) + 
                                   strlen(serialize(self::$fileCache)) + 
                                   strlen(serialize(self::$utilityCache))
            ]
        ];
    }
    
    /**
     * Limit cache size to prevent memory issues
     * @param int $maxCacheSize
     * @return void
     */
    public static function limitCacheSize(int $maxCacheSize = 100): void 
    {
        if (count(self::$cache) > $maxCacheSize) {
            // Remove oldest entries (FIFO)
            self::$cache = array_slice(self::$cache, -$maxCacheSize, null, true);
        }
        
        if (count(self::$fileCache) > $maxCacheSize) {
            self::$fileCache = array_slice(self::$fileCache, -$maxCacheSize, null, true);
        }
        
        if (count(self::$utilityCache) > $maxCacheSize) {
            self::$utilityCache = array_slice(self::$utilityCache, -$maxCacheSize, null, true);
        }
    }
    
    /**
     * Build footer content with alignment support
     * @param string $footer
     * @param string $footerAlign
     * @param string $modalId
     * @return string
     */
    private static function buildFooterContent(string $footer, string $footerAlign, string $modalId): string 
    {
        $footer = trim($footer);
        
        // Replace data-dismiss="modal" with onclick="closeModal('modalId')"
        $footer = str_replace('data-dismiss="modal"', "onclick=\"closeModal('$modalId')\"", $footer);
        
        switch ($footerAlign) {
            case 'left':
                return '<div class="nx-footer-left">' . $footer . '</div>';
                
            case 'center':
                return '<div class="nx-footer-center">' . $footer . '</div>';
                
            case 'split':
                return self::buildSplitFooter($footer);
                
            case 'between':
                return self::buildBetweenFooter($footer);
                
            case 'right':
            default:
                return '<div class="nx-footer-right">' . $footer . '</div>';
        }
    }
    
    /**
     * Build split footer with left and right sections
     * @param string $footer
     * @return string
     */
    private static function buildSplitFooter(string $footer): string 
    {
        // Parse konten footer untuk mencari pembagian left|right
        if (preg_match('/^(.*?)\|(.*)$/s', $footer, $matches)) {
            $leftContent = trim($matches[1]);
            $rightContent = trim($matches[2]);
            
            return '<div class="nx-footer-split">' .
                   '<div class="nx-footer-left">' . $leftContent . '</div>' .
                   '<div class="nx-footer-right">' . $rightContent . '</div>' .
                   '</div>';
        }
        
        // Jika tidak ada pembagian, tampilkan semua di kanan
        return '<div class="nx-footer-right">' . $footer . '</div>';
    }
    
    /**
     * Build between footer with space-between layout
     * @param string $footer
     * @return string
     */
    private static function buildBetweenFooter(string $footer): string 
    {
        // Parse konten footer untuk mencari pembagian left|right
        if (preg_match('/^(.*?)\|(.*)$/s', $footer, $matches)) {
            $leftContent = trim($matches[1]);
            $rightContent = trim($matches[2]);
            
            return '<div class="nx-footer-between">' .
                   '<div class="nx-footer-start">' . $leftContent . '</div>' .
                   '<div class="nx-footer-end">' . $rightContent . '</div>' .
                   '</div>';
        }
        
        // Jika tidak ada pembagian, tampilkan semua di kanan
        return '<div class="nx-footer-right">' . $footer . '</div>';
    }
    
    /**
     * Auto-optimize cache based on usage
     * @return void
     */
    public static function autoOptimize(): void 
    {
        // Limit cache size automatically
        self::limitCacheSize(50);
        
        // Clear cache if memory usage is too high (estimated > 1MB)
        $stats = self::getCacheStats();
        if ($stats['memory_usage']['total_estimated'] > 1048576) { // 1MB
            self::clearCache();
        }
    }
}