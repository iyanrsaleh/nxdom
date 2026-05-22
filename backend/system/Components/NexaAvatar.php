<?php
namespace App\System\Components;

class NexaAvatar {
    private static $cache = [];
    
    /**
     * Instance method untuk render avatar langsung
     * @param string $src Image source URL
     * @param string $alt Alt text untuk image
     * @param string $size Ukuran avatar (sm, md, lg, xl)
     * @param string $status Status avatar (online, offline, busy, away)
     * @param string $class Additional CSS classes
     * @return string HTML avatar
     */
    public function render($src = '', $alt = '', $size = 'md', $status = '', $class = '') {
        $classes = ['nx-avatar'];
        $classes[] = 'nx-avatar-' . $size;
        
        if ($status) {
            $classes[] = 'nx-avatar-status';
            $classes[] = 'nx-avatar-' . $status;
        }
        
        if ($class) {
            $classes[] = $class;
        }
        
        $html = '<div class="' . implode(' ', $classes) . '">';
        if ($src) {
            $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '">';
        } else {
            // Default avatar jika tidak ada src
            $html .= '<div class="nx-avatar-placeholder">' . strtoupper(substr($alt, 0, 1)) . '</div>';
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Transform avatar elements dalam konten
     * @param string $content
     * @return string
     */
    public static function transform(string &$content): string 
    {
        // Pattern untuk mencocokkan Avatar elements
        $pattern = '/<Avatar\s+(.*?)\/>/is';
        return preg_replace_callback($pattern, function($matches) {
            $attributes = self::parseAttributes($matches[1]);
            
            // Ambil atribut yang diperlukan
            $class = $attributes['class'] ?? '';
            $src = $attributes['src'] ?? '';
            $alt = $attributes['alt'] ?? '';
            $size = $attributes['size'] ?? 'md';
            $status = $attributes['status'] ?? '';
            
            // Base classes
            $classes = ['nx-avatar'];
            $classes[] = 'nx-avatar-' . $size;
            
            if ($status) {
                $classes[] = 'nx-avatar-status';
                $classes[] = 'nx-avatar-' . $status;
            }
            
            if ($class) {
                $classes[] = $class;
            }
            
            // Build avatar HTML
            $html = '<div class="' . implode(' ', $classes) . '">';
            if ($src) {
                $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '">';
            }
            $html .= '</div>';
            
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
        
        // Pattern untuk menangkap atribut dengan nilai dalam quotes atau parentheses
        $pattern = '/([a-zA-Z0-9_-]+)\s*=\s*(?:(["\'])(.*?)\2|\((.*?)\))/s';
        
        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = $match[3] ?? $match[4] ?? '';
                $attributes[$name] = trim($value);
            }
        }
        
        return $attributes;
    }
} 