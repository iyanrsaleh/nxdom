<?php
namespace App\System\Components;

class NexaSpinner {
    private static $cache = [];
    
    /**
     * Instance method untuk render spinner langsung
     * @param string $text Loading text (optional)
     * @param string $type Type spinner (border, grow)
     * @param string $size Ukuran spinner (sm, md, lg)
     * @param string $color Warna spinner (primary, secondary, success, danger, warning, info, light, dark)
     * @param string $class Additional CSS classes
     * @return string HTML spinner
     */
    public function render($text = 'Loading...', $type = 'border', $size = '', $color = '', $class = '') {
        $baseClass = $type === 'grow' ? 'nx-spinner-grow' : 'nx-spinner';
        $classes = [$baseClass];
        
        if ($size) {
            $sizeClass = $type === 'grow' ? 
                "nx-spinner-grow-{$size}" : 
                "nx-spinner-{$size}";
            $classes[] = $sizeClass;
        }
        
        if ($color) {
            $classes[] = "nx-spinner-{$color}";
        }
        
        if ($class) {
            $classes[] = $class;
        }
        
        $html = '<div class="nx-spinner-container">';
        $html .= '<div class="' . implode(' ', $classes) . '"></div>';
        if ($text) {
            $html .= '<div class="nx-spinner-text">' . htmlspecialchars($text) . '</div>';
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Transform spinner elements dalam konten
     * @param string $content
     * @return string
     */
    public static function transform(string &$content): string 
    {
        // Pattern untuk mencocokkan Spinner elements
        $pattern = '/<Spinner\s+(.*?)\/>/is';
        return preg_replace_callback($pattern, function($matches) {
            $attributes = self::parseAttributes($matches[1]);
            
            // Ambil atribut yang diperlukan
            $class = $attributes['class'] ?? '';
            $size = $attributes['size'] ?? '';
            $color = $attributes['color'] ?? '';
            $type = $attributes['type'] ?? 'border'; // default ke border spinner
            
            // Base class berdasarkan type
            $baseClass = $type === 'grow' ? 'nx-spinner-grow' : 'nx-spinner';
            $classes = [$baseClass];
            
            // Tambahkan class ukuran jika ada
            if ($size) {
                $sizeClass = $type === 'grow' ? 
                    "nx-spinner-grow-{$size}" : 
                    "nx-spinner-{$size}";
                $classes[] = $sizeClass;
            }
            
            // Tambahkan class warna jika ada
            if ($color) {
                $classes[] = "nx-spinner-{$color}";
            }
            
            // Tambahkan class tambahan jika ada
            if ($class) {
                $classes[] = $class;
            }
            
            // Build spinner HTML
            $html = '<div class="' . implode(' ', $classes) . '"></div>';
            
            return $html;
        }, $content);
    }

    /**
     * Parse atribut dari string
     * @param string $attributeString
     * @return array
     */
    private static function parseAttributes(string $attributeString): array 
    {
        $attributes = [];
        
        // Pattern untuk menangkap atribut dengan nilai dalam quotes
        $pattern = '/([a-zA-Z0-9_-]+)\s*=\s*(["\'])(.*?)\2/s';
        
        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = $match[3];
                $attributes[$name] = trim($value);
            }
        }
        
        return $attributes;
    }
} 