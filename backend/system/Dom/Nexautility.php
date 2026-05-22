<?php
namespace App\System\Dom;
class Nexautility {
    /**
     * Daftar utility classes dan properti CSS-nya
     * @var array
     */
    private static $utilities = [
        'tx' => 'text-align',    
        'position' => 'position',
        'pos' => 'position',
        'top' => 'top',
        'bottom' => 'bottom',
        'left' => 'left',
        'right' => 'right',
        'w' => 'width',
        'h' => 'height',
        'min-w' => 'min-width',
        'min-h' => 'min-height',
        'max-w' => 'max-width',
        'max-h' => 'max-height',
        
        // Margin
        'm' => 'margin',
        'mt' => 'margin-top',
        'mb' => 'margin-bottom',
        'ml' => 'margin-left',
        'mr' => 'margin-right',
        'mx' => 'margin-left margin-right',
        'my' => 'margin-top margin-bottom',
        
        // Padding
        'p' =>  'padding',
        'pt' => 'padding-top',
        'pb' => 'padding-bottom',
        'pl' => 'padding-left',
        'pr' => 'padding-right',
        'px' => 'padding-left padding-right',
        'py' => 'padding-top padding-bottom',
        
        // Font
        'fs' => 'font-size',
        'fw' => 'font-weight',
        'lh' => 'line-height',
        
        // Border
        'br' => 'border-radius',
        'bw' => 'border-width',
        
        // Opacity & Z-index
        'op' => 'opacity',
        'z' => 'z-index',
        
        // Background & Text color
        'bg' => 'background-color',
        'text' => 'color',
        
        // Display
        'hidden' => 'display',
        'block' => 'display',
        'inline' => 'display',
        'flex' => 'display',
        
        // Font Weight
        'bold' => 'font-weight',
        'normal' => 'font-weight',
        'thin' => 'font-weight',
        'light' => 'font-weight',
        'medium' => 'font-weight',
        'semibold' => 'font-weight',
        'extrabold' => 'font-weight',
        
        // Float
        'pull-right' => 'float',
        'pull-left' => 'float',
    ];

 

    /**
     * Class BEM / library yang tidak pernah jadi utility Nexautility — hindari ratusan preg_match per token.
     */
    private static function isPassthroughClass(string $class): bool
    {
        if ($class === '') {
            return true;
        }
        static $prefixes = [
            'nx-', 'fa-', 'js-', 'nx-html-', 'bi-', 'glyphicon-', 'material-icons-',
        ];
        foreach ($prefixes as $p) {
            if (str_starts_with($class, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse class utility patterns dan konversi ke inline style.
     */
    public static function transform(string $content): string
    {
        // Transform href attributes first
        // Process NexaDom elements (hide them)
        $content = self::processNexaDomElements($content);

        // Pattern untuk mencocokkan class warna hex
        $content = preg_replace_callback(
            '/class=(["\'])(b?#[0-9a-f]{3,6})\1/i',
            function($matches) {
                $hexClass = $matches[2];
                
                if (preg_match('/^(b?)#([0-9a-f]{3,6})$/i', $hexClass, $colorMatch)) {
                    $isBackground = ($colorMatch[1] === 'b');
                    $hexColor = '#' . $colorMatch[2];
                    $property = $isBackground ? 'background-color' : 'color';
                    return sprintf('style="%s:%s"', $property, $hexColor);
                }
                
                return $matches[0];
            },
            $content
        );

        if (($_ENV['NEXA_SKIP_HTML_TRANSFORMS'] ?? '') === 'true') {
            return $content;
        }

        // Pattern untuk mencocokkan seluruh tag HTML dengan atribut class
        $pattern = '/<([a-zA-Z0-9]+)([^>]*?class=(["\'])(.*?)\3[^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $tag = $matches[1];
            $fullAttributes = $matches[2];
            $quote = $matches[3];
            $classValue = $matches[4];
            
            // Ekstrak atribut kecuali class
            $attributes = preg_replace('/\bclass=(["\'])(.*?)\1/', '', $fullAttributes);
            
            $classes = explode(' ', $classValue);
            $styles = [];
            $remainingClasses = [];
            
            foreach ($classes as $class) {
                $class = trim($class);
                if (empty($class)) continue;

                if (self::isPassthroughClass($class)) {
                    $remainingClasses[] = $class;
                    continue;
                }
                
                $matched = false;
                
                // Tambahan: Border width, style, color (bw-1-solid-#ccc atau bw-1-#ccc)
                if (preg_match('/^bw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/', $class, $bwMatch)) {
                    $bw = $bwMatch[1] . 'px';
                    $bs = isset($bwMatch[2]) && $bwMatch[2] ? $bwMatch[2] : 'solid';
                    $bc = isset($bwMatch[3]) && $bwMatch[3] ? $bwMatch[3] : null;
                    $styles['border-width'] = $bw;
                    $styles['border-style'] = $bs;
                    if ($bc) $styles['border-color'] = $bc;
                    $matched = true;
                    continue;
                }
                // Tambahan: Border-top
                if (preg_match('/^btw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/', $class, $btwMatch)) {
                    $bw = $btwMatch[1] . 'px';
                    $bs = isset($btwMatch[2]) && $btwMatch[2] ? $btwMatch[2] : 'solid';
                    $bc = isset($btwMatch[3]) && $btwMatch[3] ? $btwMatch[3] : null;
                    $styles['border-top-width'] = $bw;
                    $styles['border-top-style'] = $bs;
                    if ($bc) $styles['border-top-color'] = $bc;
                    $matched = true;
                    continue;
                }
                // Tambahan: Border-bottom
                if (preg_match('/^bbw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/', $class, $bbwMatch)) {
                    $bw = $bbwMatch[1] . 'px';
                    $bs = isset($bbwMatch[2]) && $bbwMatch[2] ? $bbwMatch[2] : 'solid';
                    $bc = isset($bbwMatch[3]) && $bbwMatch[3] ? $bbwMatch[3] : null;
                    $styles['border-bottom-width'] = $bw;
                    $styles['border-bottom-style'] = $bs;
                    if ($bc) $styles['border-bottom-color'] = $bc;
                    $matched = true;
                    continue;
                }
                // Tambahan: Border-left
                if (preg_match('/^blw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/', $class, $blwMatch)) {
                    $bw = $blwMatch[1] . 'px';
                    $bs = isset($blwMatch[2]) && $blwMatch[2] ? $blwMatch[2] : 'solid';
                    $bc = isset($blwMatch[3]) && $blwMatch[3] ? $blwMatch[3] : null;
                    $styles['border-left-width'] = $bw;
                    $styles['border-left-style'] = $bs;
                    if ($bc) $styles['border-left-color'] = $bc;
                    $matched = true;
                    continue;
                }
                // Tambahan: Border-right
                if (preg_match('/^brw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/', $class, $brwMatch)) {
                    $bw = $brwMatch[1] . 'px';
                    $bs = isset($brwMatch[2]) && $brwMatch[2] ? $brwMatch[2] : 'solid';
                    $bc = isset($brwMatch[3]) && $brwMatch[3] ? $brwMatch[3] : null;
                    $styles['border-right-width'] = $bw;
                    $styles['border-right-style'] = $bs;
                    if ($bc) $styles['border-right-color'] = $bc;
                    $matched = true;
                    continue;
                }
                
                // Check for custom color classes (e.g., #ff0000, b#00ff00)
                if (preg_match('/^(b?)#([0-9a-f]{3,6})$/i', $class, $colorMatch)) {
                    $isBackground = ($colorMatch[1] === 'b');
                    $hexColor = '#' . $colorMatch[2];
                    $property = $isBackground ? 'background-color' : 'color';
                    $styles[$property] = $hexColor;
                    $matched = true;
                    continue;
                }
                
                // Check for special display classes
                if (in_array($class, ['hidden', 'block', 'inline', 'flex'])) {
                    $displayValue = $class === 'hidden' ? 'none' : $class;
                    $styles['display'] = $displayValue;
                    $matched = true;
                    continue;
                }
                
                // Check for special float classes
                if ($class === 'pull-right') {
                    $styles['float'] = 'right';
                    $matched = true;
                    continue;
                }
                if ($class === 'pull-left') {
                    $styles['float'] = 'left';
                    $matched = true;
                    continue;
                }
                
                // Check for special font weight classes
                if (in_array($class, ['bold', 'normal', 'thin', 'light', 'medium', 'semibold', 'extrabold'])) {
                    $fontWeightMap = [
                        'thin' => '100',
                        'light' => '300',
                        'normal' => '400',
                        'medium' => '500',
                        'semibold' => '600',
                        'bold' => '700',
                        'extrabold' => '800'
                    ];
                    $styles['font-weight'] = $fontWeightMap[$class];
                    $matched = true;
                    continue;
                }

                // Tailwind-style text-align (harus sebelum prefix "text-" untuk warna)
                if (preg_match('/^text-(left|center|right|justify|start|end)$/', $class, $taMatch)) {
                    $alignMap = [
                        'left' => 'left',
                        'center' => 'center',
                        'right' => 'right',
                        'justify' => 'justify',
                        'start' => 'start',
                        'end' => 'end',
                    ];
                    $styles['text-align'] = $alignMap[$taMatch[1]];
                    $matched = true;
                    continue;
                }
                
                if ($matched) continue;
                
                // Check utility classes
                foreach (self::$utilities as $prefix => $properties) {
                    if (preg_match('/^' . preg_quote($prefix, '/') . '-([a-zA-Z0-9\#\.]+)(?:-(.*?))?$/', $class, $utilMatch)) {
                        $value = $utilMatch[1];
                        
                        if ($properties === 'text-align') {
                            $alignValues = [
                                'center' => 'center',
                                'left' => 'left',
                                'right' => 'right',
                            ];
                            $value = $alignValues[$value] ?? $value;
                        } else {
                            $unit = $utilMatch[2] ?? self::getDefaultUnit($properties);
                            if (is_numeric($value)) {
                                $value .= $unit;
                            }
                        }
                        
                        foreach (explode(' ', $properties) as $prop) {
                            $styles[$prop] = $value;
                        }
                        $matched = true;
                        break;
                    }
                }
                
                if (!$matched) {
                    $remainingClasses[] = $class;
                }
            }

            $normalize = static function (string $s): string {
                return preg_replace('/\s+/', ' ', trim($s));
            };
            if (empty($styles) && $normalize($classValue) === $normalize(implode(' ', $remainingClasses))) {
                return $matches[0];
            }
            
            // Build output tag
            $output = "<$tag";
            
            if (!empty($remainingClasses)) {
                $output .= ' class=' . $quote . implode(' ', $remainingClasses) . $quote;
            }
            
            if (!empty($styles)) {
                $styleString = '';
                foreach ($styles as $prop => $value) {
                    $styleString .= "$prop:$value;";
                }
                
                if (strpos($attributes, 'style=') !== false) {
                    $attributes = preg_replace('/style=(["\'])(.*?)\1/', 'style="$2' . $styleString . '"', $attributes);
                    $output .= $attributes;
                } else {
                    $output .= ' style="' . $styleString . '"' . $attributes;
                }
            } else {
                $output .= $attributes;
            }
            
            $output .= '>';
            return $output;
        }, $content);
    }



    /**
     * Mendapatkan unit default berdasarkan properti
     * @param string $property Properti CSS
     * @return string Unit default
     */
    private static function getDefaultUnit(string $property): string
    {
        $timeProperties = ['transition', 'animation', 'animation-duration', 'transition-duration'];
        $unitlessProperties = ['opacity', 'z-index', 'font-weight', 'flex', 'order', 'scale'];
        
        if (in_array($property, $timeProperties)) {
            return 'ms';
        }
        if (in_array($property, $unitlessProperties)) {
            return '';
        }
        return 'px';
    }

    /**
     * Handle NexaDom attribute - hide elements and remove content
     * @param string $content Konten yang akan diproses
     * @return string Konten yang telah diproses
     */
    private static function processNexaDomElements(string $content): string
    {
        // Hanya tag yang benar-benar punya atribut NexaDom (bukan substring di nilai href / hash)
        $content = preg_replace_callback(
            '/<([a-zA-Z0-9]+)([^>]*?(?:\s|\/)NexaDom\s*(?:=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)|(?=\s|\/|>))[^>]*?)>/is',
            function($matches) {
                $tag = $matches[1];
                $attributes = $matches[2];
                
                // Check if style attribute already exists
                if (preg_match('/style=(["\'])(.*?)\1/', $attributes, $styleMatch)) {
                    $quote = $styleMatch[1];
                    $existingStyle = $styleMatch[2];
                    $newStyle = rtrim($existingStyle, ';') . ';display:none;';
                    $attributes = preg_replace('/style=(["\'])(.*?)\1/', 'style=' . $quote . $newStyle . $quote, $attributes);
                } else {
                    $attributes .= ' style="display:none"';
                }
                
                return "<{$tag}{$attributes}>";
            },
            $content
        );
        
        return $content;
    }

    


}
