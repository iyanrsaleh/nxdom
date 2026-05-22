<?php

namespace App\System\Helpers;

use Exception;

/**
 * NexaNav Class
 * 
 * Class untuk generate HTML navigation menu dari struktur data
 * Dapat digunakan di berbagai halaman/controller
 */
class NexaNav
{
    /**
     * Generate HTML navigation menu from data structure
     * 
     * @param array|object $data The menu data structure
     * @param int $level Current nesting level (0 = top level)
     * @param array $classes Array of CSS classes configuration
     * @return string Generated HTML
     */
    private static function generateMenu($data, int $level = 0, array $classes = []): string
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        if (is_object($data)) {
            $data = (array) $data;
        }

        // Handle data_value wrapper
        if (isset($data['data_value'])) {
            $data = $data['data_value'];
        }

        // If data is an array of items, iterate through them
        if (isset($data[0])) {
            $html = '';
            foreach ($data as $item) {
                $html .= self::generateMenu($item, $level, $classes);
            }
            return $html;
        }

        // Skip items with no label
        if (empty($data['label'])) {
            return '';
        }

        $label = htmlspecialchars($data['label'], ENT_QUOTES, 'UTF-8');
        $hasChildren = !empty($data['children']) && is_array($data['children']) && count($data['children']) > 0;
        
        // Check if item has hrefOrigin or handler (handler is equivalent to hrefOrigin)
        $hasHrefOrigin = isset($data['hrefOrigin']) && !empty($data['hrefOrigin']);
        $hasHandler = isset($data['handler']) && !empty($data['handler']) && $data['handler'] !== '#';
        
        // Skip ONLY leaf nodes (items without children) that don't have hrefOrigin or handler
        // Items WITH children (parent/folder) are ALWAYS shown to maintain menu structure
        // This ensures menu hierarchy is preserved even if parent items don't have hrefOrigin/handler
        if (!$hasChildren && !$hasHrefOrigin && !$hasHandler) {
            return ''; // Skip leaf nodes without hrefOrigin or handler
        }
        // Continue processing for:
        // - Items with children (always shown)
        // - Leaf nodes with hrefOrigin or handler (shown)
        
        // Determine href
        // Items with children typically use javascript:void(0) for expandable menus
        // Only leaf nodes (items without children) use actual links
        $href = 'javascript:void(0)';
        if (!$hasChildren) {
            // Leaf node - prioritize hrefOrigin, then handler, then href
            if (isset($data['hrefOrigin']) && !empty($data['hrefOrigin'])) {
                $href = '/' . ltrim($data['hrefOrigin'], '/');
            } elseif (isset($data['handler']) && !empty($data['handler'])) {
                // If handler is '#', use javascript:void(0), otherwise use handler as path
                if ($data['handler'] === '#') {
                    $href = 'javascript:void(0)';
                } else {
                    $href = '/' . ltrim($data['handler'], '/');
                }
            } elseif (isset($data['href']) && !empty($data['href'])) {
                $href = '/' . ltrim($data['href'], '/');
            }
        } else {
            // Items with children - check if handler is '#' to use javascript:void(0)
            if (isset($data['handler']) && $data['handler'] === '#') {
                $href = 'javascript:void(0)';
            } elseif (isset($data['handler']) && !empty($data['handler'])) {
                $href = '/' . ltrim($data['handler'], '/');
            } elseif (isset($data['hrefOrigin']) && !empty($data['hrefOrigin'])) {
                $href = '/' . ltrim($data['hrefOrigin'], '/');
            }
        }

        // Get CSS classes from config or use defaults
        $navSubClass = $classes['sub'] ?? 'main-menu__nav_sub';
        $animationClass = $classes['animation'] ?? 'animation';
        $dropdownClass = $classes['dropdown'] ?? 'main-menu__dropdown';
        $dropdownExtraClass = $classes['dropdownExtra'] ?? '';
        $listClass = $classes['list'] ?? 'list';
        $listPosition = $classes['listPosition'] ?? 'after'; // 'before' or 'after'
        
        // Build list class string
        $listClassStr = $listClass ? " {$listClass}" : '';
        
        // Top level items (level 0)
        if ($level === 0) {
            if (!$hasChildren) {
                // Simple top level item without children
                return "<li>\n<a href=\"{$href}\">{$label}</a>\n</li>\n";
            } else {
                // Top level item with children - ALWAYS shown to maintain menu structure
                // Build li class with list position
                if ($listPosition === 'before') {
                    $liClass = "{$listClass} {$navSubClass}";
                } else {
                    $liClass = "{$navSubClass}{$listClassStr}";
                }
                $liClass = trim($liClass);
                
                $html = "<li class=\"{$liClass}\">\n";
                
                // Add animation class for top level items with children
                $linkClass = !empty($animationClass) ? " class=\"{$animationClass}\"" : '';
                $html .= "<a{$linkClass} href=\"{$href}\">{$label}</a>\n";
                
                // Build dropdown ul class
                $dropdownUlClass = $dropdownClass;
                if (!empty($dropdownExtraClass)) {
                    $dropdownUlClass .= " {$dropdownExtraClass}";
                }
                $html .= "<ul class=\"{$dropdownUlClass}\">\n";
                
                // Process children recursively - children without hrefOrigin (leaf nodes) will be skipped
                // but parent items with children are always displayed
                foreach ($data['children'] as $child) {
                    $html .= self::generateMenu($child, $level + 1, $classes);
                }
                
                $html .= "</ul>\n";
                $html .= "</li>\n";
                return $html;
            }
        }

        // Nested items (level > 0)
        if (!$hasChildren) {
            // Leaf node - simple list item
            return "<li><a href=\"{$href}\">{$label}</a></li>\n";
        } else {
            // Item with children - nested structure - ALWAYS shown to maintain menu structure
            $hasNestedChildren = false;
            foreach ($data['children'] as $child) {
                if (!empty($child['children']) && is_array($child['children']) && count($child['children']) > 0) {
                    $hasNestedChildren = true;
                    break;
                }
            }
            
            // Build li class with list position
            // All items with children should have list class (if enabled)
            if ($listClass) {
                if ($listPosition === 'before') {
                    $liClass = "{$listClass} {$navSubClass}";
                } else {
                    $liClass = "{$navSubClass}{$listClassStr}";
                }
            } else {
                $liClass = $navSubClass;
            }
            $liClass = trim($liClass);
            
            $html = "<li class=\"{$liClass}\">\n";
            $html .= "<a href=\"{$href}\">\n{$label}\n</a>\n";
            
            // Build dropdown ul class for nested items
            $dropdownUlClass = $dropdownClass;
            if (!empty($dropdownExtraClass)) {
                $dropdownUlClass .= " {$dropdownExtraClass}";
            }
            $html .= "<ul class=\"{$dropdownUlClass}\">\n";
            
            // Process children recursively - children without hrefOrigin (leaf nodes) will be skipped
            // but parent items with children are always displayed to maintain menu hierarchy
            foreach ($data['children'] as $child) {
                $html .= self::generateMenu($child, $level + 1, $classes);
            }
            
            $html .= "</ul>\n";
            $html .= "</li>\n";
            return $html;
        }
    }

    /**
     * Generate complete navigation HTML from menu data
     * 
     * @param mixed $menuData Menu data (array, object, or JSON string)
     * @param array $classes Optional CSS classes configuration:
     *   - 'main' => nav class (default: 'main-menu__nav')
     *   - 'sub' => sub menu item class (default: 'main-menu__nav_sub')
     *   - 'ul' => ul class for main menu (default: '')
     *   - 'animation' => animation class for top level links (default: 'animation')
     *   - 'dropdown' => dropdown ul class (default: 'main-menu__dropdown')
     *   - 'dropdownExtra' => extra class for dropdown (e.g. 'sub-menu' for mobile)
     *   - 'list' => list class for items with children (default: 'list')
     *   - 'listPosition' => position of list class: 'before' or 'after' (default: 'after')
     * @return string Complete navigation HTML with nav wrapper
     */
    public static function generate($menuData, array $classes = []): string
    {
        try {
            // Set default classes if not provided
            $navMainClass = $classes['main'] ?? 'main-menu__nav';
            $navSubClass = $classes['sub'] ?? 'main-menu__nav_sub';
            $ulClass = $classes['ul'] ?? '';
            
            // Merge classes for passing to generateMenu
            $classesConfig = [
                'main' => $navMainClass,
                'sub' => $navSubClass,
                'ul' => $ulClass,
                'animation' => $classes['animation'] ?? 'animation',
                'dropdown' => $classes['dropdown'] ?? 'main-menu__dropdown',
                'dropdownExtra' => $classes['dropdownExtra'] ?? '',
                'list' => $classes['list'] ?? 'list',
                'listPosition' => $classes['listPosition'] ?? 'after'
            ];
            
            if (empty($menuData)) {
                $ulTag = $ulClass ? "<ul class=\"{$ulClass}\">" : '<ul>';
                return "<nav class=\"{$navMainClass}\">{$ulTag}</ul></nav>";
            }

            // Handle object property access
            if (is_object($menuData) && isset($menuData->data_value)) {
                $menuData = $menuData->data_value;
            }
            
            // Decode if it's JSON string
            if (is_string($menuData)) {
                $menuData = json_decode($menuData, true);
            }

            if (empty($menuData)) {
                $ulTag = $ulClass ? "<ul class=\"{$ulClass}\">" : '<ul>';
                return "<nav class=\"{$navMainClass}\">{$ulTag}</ul></nav>";
            }

            $html = "<nav class=\"{$navMainClass}\">\n";
            $ulTag = $ulClass ? "<ul class=\"{$ulClass}\">" : '<ul>';
            $html .= "{$ulTag}\n";
            $html .= self::generateMenu($menuData, 0, $classesConfig);
            $html .= "</ul>\n";
            $html .= "</nav>\n";

            return $html;
        } catch (Exception $e) {
            $navMainClass = $classes['main'] ?? 'main-menu__nav';
            return "<nav class=\"{$navMainClass}\"><ul><li>Error: " . htmlspecialchars($e->getMessage()) . "</li></ul></nav>";
        }
    }

    /**
     * Generate standard/flat navigation HTML with brand section
     * 
     * Format output:
     * <nav class="navbar">
     *   <a class="nav-brand" href="...">
     *     <img class="brand-icon" src="..." alt="...">
     *     <div class="brand">...</div>
     *   </a>
     *   <div class="nav-links">
     *     <a href="...">...</a>
     *     ...
     *   </div>
     * </nav>
     * 
     * @param array $data Data array containing:
     *   - 'menuData' => Menu data (array, object, or JSON string) - flat structure, no nested children
     *   - 'brandConfig' => Brand configuration:
     *     - 'href' => brand link URL (default: '/')
     *     - 'logo' => logo image src (optional)
     *     - 'alt' => logo alt text (default: 'Brand')
     *     - 'text' => brand text (optional)
     *   - 'classes' => Optional CSS classes configuration:
     *     - 'nav' => nav class (default: 'navbar')
     *     - 'brand' => brand link class (default: 'nav-brand')
     *     - 'brandIcon' => brand icon class (default: 'brand-icon')
     *     - 'brandText' => brand text div class (default: 'brand')
     *     - 'navLinks' => nav links container class (default: 'nav-links')
     * @return string Complete navigation HTML with brand and links
     */
    public static function generateStandard(array $data = []): string
    {
        try {
            // Extract menuData, brandConfig, and classes from data parameter
            $menuData = $data['menuData'] ?? [];
            $brandConfig = $data['brandConfig'] ?? [];
            $classes = $data['classes'] ?? [];

            // Set default classes
            $navClass = $classes['nav'] ?? 'navbar';
            $brandClass = $classes['brand'] ?? 'nav-brand';
            $brandIconClass = $classes['brandIcon'] ?? 'brand-icon';
            $brandTextClass = $classes['brandText'] ?? 'brand';
            $navLinksClass = $classes['navLinks'] ?? 'nav-links';

            // Brand configuration
            $brandHref = $brandConfig['href'] ?? '/';
            $brandLogo = $brandConfig['logo'] ?? '';
            $brandAlt = $brandConfig['alt'] ?? 'Brand';
            $brandText = $brandConfig['text'] ?? '';

            // Normalize menu data
            if (is_string($menuData)) {
                $menuData = json_decode($menuData, true);
            }
            
            if (is_object($menuData)) {
                $menuData = (array) $menuData;
            }

            // Handle data_value wrapper
            if (isset($menuData['data_value'])) {
                $menuData = $menuData['data_value'];
            }

            // Ensure menuData is array
            if (!is_array($menuData)) {
                $menuData = [];
            }

            // Start building HTML
            $html = "<nav class=\"{$navClass}\">\n";

            // Build brand section
            if (!empty($brandLogo) || !empty($brandText)) {
                $brandHrefEscaped = htmlspecialchars($brandHref, ENT_QUOTES, 'UTF-8');
                $html .= "        <a class=\"{$brandClass}\" href=\"{$brandHrefEscaped}\">\n";
                
                if (!empty($brandLogo)) {
                    $brandLogoEscaped = htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8');
                    $brandAltEscaped = htmlspecialchars($brandAlt, ENT_QUOTES, 'UTF-8');
                    $html .= "            <img class=\"{$brandIconClass}\" src=\"{$brandLogoEscaped}\" alt=\"{$brandAltEscaped}\">\n";
                }
                
                if (!empty($brandText)) {
                    $brandTextEscaped = htmlspecialchars($brandText, ENT_QUOTES, 'UTF-8');
                    $html .= "            <div class=\"{$brandTextClass}\">{$brandTextEscaped}</div>\n";
                }
                
                $html .= "         </a>\n";
            }

            // Build nav links section
            $html .= "        <div class=\"{$navLinksClass}\">\n";

            // Process menu items (flat structure only)
            if (!empty($menuData) && is_array($menuData)) {
                foreach ($menuData as $item) {
                    // Normalize item
                    if (is_object($item)) {
                        $item = (array) $item;
                    }

                    // Skip if no label
                    if (empty($item['label'])) {
                        continue;
                    }

                    $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');

                    // Determine href - prioritize hrefOrigin, then handler, then href
                    $href = '#';
                    if (isset($item['hrefOrigin']) && !empty($item['hrefOrigin'])) {
                        $href = $item['hrefOrigin'];
                        // Ensure absolute URL or add leading slash
                        if (!preg_match('/^(https?:\/\/|\/)/', $href)) {
                            $href = '/' . ltrim($href, '/');
                        }
                    } elseif (isset($item['handler']) && !empty($item['handler']) && $item['handler'] !== '#') {
                        $href = $item['handler'];
                        // Ensure absolute URL or add leading slash
                        if (!preg_match('/^(https?:\/\/|\/)/', $href)) {
                            $href = '/' . ltrim($href, '/');
                        }
                    } elseif (isset($item['href']) && !empty($item['href'])) {
                        $href = $item['href'];
                        // Ensure absolute URL or add leading slash
                        if (!preg_match('/^(https?:\/\/|\/)/', $href)) {
                            $href = '/' . ltrim($href, '/');
                        }
                    }

                    $hrefEscaped = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                    $html .= "          <a href=\"{$hrefEscaped}\">{$label}</a>\n";
                }
            }

            $html .= "        </div>\n";
            $html .= "      </nav>\n";

            return $html;
        } catch (Exception $e) {
            $navClass = $classes['nav'] ?? 'navbar';
            return "<nav class=\"{$navClass}\"><div class=\"nav-links\">Error: " . htmlspecialchars($e->getMessage()) . "</div></nav>";
        }
    }

    /**
     * Auto-detect menu type and generate appropriate navigation HTML
     * 
     * Automatically determines whether to use generateStandard() or generate() based on:
     * - 'type' key in data ('Standard' or 'Complex')
     * - Presence of nested children in menu data
     * 
     * @param array $data Data array containing:
     *   - 'type' => 'Standard' or 'Complex' (optional, will auto-detect if not provided)
     *   - 'menuData' => Menu data (array, object, or JSON string)
     *   - 'brandConfig' => Brand configuration (required for Standard type)
     *   - 'classes' => Optional CSS classes configuration
     * @return string Complete navigation HTML
     */
    public static function generateAuto(array $data = []): string
    {
        try {
            // Extract type, menuData, brandConfig, and classes
            $type = $data['type'] ?? null;
            $menuData = $data['menuData'] ?? [];
            $brandConfig = $data['brandConfig'] ?? [];
            $classes = $data['classes'] ?? [];

            // Auto-detect type if not provided
            if (empty($type)) {
                // Normalize menuData for checking
                $checkData = $menuData;
                if (is_string($checkData)) {
                    $checkData = json_decode($checkData, true);
                }
                if (is_object($checkData)) {
                    $checkData = (array) $checkData;
                }
                if (isset($checkData['data_value'])) {
                    $checkData = $checkData['data_value'];
                }

                // Check if menu has nested children (Complex) or flat structure (Standard)
                $hasChildren = false;
                if (is_array($checkData)) {
                    // Check if it's array of items
                    if (isset($checkData[0])) {
                        foreach ($checkData as $item) {
                            if (isset($item['children']) && !empty($item['children']) && is_array($item['children'])) {
                                $hasChildren = true;
                                break;
                            }
                        }
                    } else {
                        // Single item
                        if (isset($checkData['children']) && !empty($checkData['children']) && is_array($checkData['children'])) {
                            $hasChildren = true;
                        }
                    }
                }

                $type = $hasChildren ? 'Complex' : 'Standard';
            }

            // Generate based on type
            if (strtolower($type) === 'standard') {
                // Use generateStandard for flat menu structure
                return self::generateStandard([
                    'menuData' => $menuData,
                    'brandConfig' => $brandConfig,
                    'classes' => $classes
                ]);
            } else {
                // Use generate for complex/nested menu structure
                return self::generate($menuData, $classes);
            }
        } catch (Exception $e) {
            return "<nav class=\"navbar\"><div class=\"nav-links\">Error: " . htmlspecialchars($e->getMessage()) . "</div></nav>";
        }
    }
}
