<?php
declare(strict_types=1);
namespace App\System\Helpers;

/**
 * NexaMarkdown - Markdown to HTML Converter
 * 
 * Uses League CommonMark 2.8 with GitHub Flavored Markdown support.
 * 
 * Supports all CommonMark and GFM features:
 * - Headers, Emphasis, Links, Images
 * - Lists, Code blocks, Blockquotes
 * - Tables (GFM)
 * - Strikethrough, Task Lists
 * - Syntax highlighting ready (with language-* classes)
 */
class NexaMarkdown
{
    private $useCommonMark = false;
    private $commonMarkConverter = null;
    
    public function __construct()
    {
        // Use GithubFlavoredMarkdownConverter (supports tables and GFM features)
        if (class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
            $this->useCommonMark = true;
            try {
                $this->commonMarkConverter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
            } catch (\Exception $e) {
                $this->useCommonMark = false;
            }
        }
        // Fallback to CommonMarkConverter if GFM not available
        elseif (class_exists('\League\CommonMark\CommonMarkConverter')) {
            $this->useCommonMark = true;
            try {
                $this->commonMarkConverter = new \League\CommonMark\CommonMarkConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
            } catch (\Exception $e) {
                $this->useCommonMark = false;
            }
        }
    }

    /**
     * Convert markdown text to HTML
     * @param string $text The markdown text
     * @return string The HTML output
     */
    public function text($text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Use League CommonMark if available
        if ($this->useCommonMark && $this->commonMarkConverter !== null) {
            try {
                $result = $this->commonMarkConverter->convert($text);
                $html = $result->getContent();
                
                // Convert anchor links from #anchor to /anchor
                $html = $this->convertAnchorLinks($html);
                
                // Add GitHub-style styling
                $html = $this->addGitHubStyles($html);
                
                return $html;
            } catch (\Exception $e) {
                // If error, return plain text
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
        
        // If CommonMark not available, return escaped text
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Convert anchor links from #anchor to /anchor
     */
    protected function convertAnchorLinks($html): string
    {
        // Replace href="#anchor" with href="/anchor"
        $html = preg_replace_callback(
            '/href="(#([^"]+))"/i',
            function($matches) {
                $anchor = $matches[2];
                return 'href="/' . $anchor . '"';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Add GitHub-style CSS to HTML output
     */
    protected function addGitHubStyles($html): string
    {
        // Add paragraph styles to remove borders
        if (strpos($html, '<p') !== false) {
            $html = $this->styleParagraphs($html);
        }
        
        // Add table styles if tables are present
        if (strpos($html, '<table') !== false) {
            $html = $this->styleTables($html);
        }
        
        // Add code block styles if code blocks are present
        if (strpos($html, '<pre><code') !== false || strpos($html, '<code') !== false) {
            $html = $this->styleCodeBlocks($html);
        }
        
        return $html;
    }
    
    /**
     * Style paragraphs - remove borders
     */
    protected function styleParagraphs($html): string
    {
        // Add global style to remove borders from all paragraphs
        // Use data attribute to identify paragraph styles (so they won't be extracted by client-side)
        $paragraphStyle = '<style data-paragraph-style="true">
            p {
                border: none !important;
                border-width: 0 !important;
                border-style: none !important;
                border-color: transparent !important;
                outline: none !important;
                box-shadow: none !important;
            }
            /* Ensure all paragraphs have no border */
            p, p * {
                border: none !important;
                outline: none !important;
            }
        </style>';
        
        // Insert style tag at the beginning of HTML (after opening body or at start)
        if (strpos($html, '<body') !== false) {
            $html = preg_replace('/(<body[^>]*>)/i', '$1' . $paragraphStyle, $html, 1);
        } elseif (strpos($html, '<html') !== false) {
            $html = preg_replace('/(<html[^>]*>)/i', '$1' . $paragraphStyle, $html, 1);
        } else {
            // If no body/html tag, add at the beginning
            $html = $paragraphStyle . $html;
        }
        
        // Also style individual paragraph tags to ensure no border (inline style as backup)
        $html = preg_replace_callback(
            '/<p\b[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                // Check if style attribute already exists
                if (strpos($tag, 'style=') !== false) {
                    // Remove any border-related styles from existing style
                    $tag = preg_replace('/border[^;]*;?/i', '', $tag);
                    $tag = preg_replace('/outline[^;]*;?/i', '', $tag);
                    $tag = preg_replace('/box-shadow[^;]*;?/i', '', $tag);
                    // Add border: none explicitly
                    $tag = preg_replace('/style="([^"]*)"/i', 'style="$1 border: none !important; outline: none !important;"', $tag);
                } else {
                    // Add style with no border
                    return str_replace('<p', '<p style="border: none !important; outline: none !important; margin: 0 0 16px 0; padding: 0;"', $tag);
                }
                return $tag;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Add GitHub-style table CSS
     */
    protected function styleTables($html): string
    {
        // Style table
        $html = preg_replace_callback(
            '/<table\b[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                if (strpos($tag, 'style=') === false) {
                    return str_replace('<table', '<table style="border-collapse: collapse; width: 100%; margin: 16px 0; border-spacing: 0; display: table;"', $tag);
                }
                return $tag;
            },
            $html
        );
        
        // Style table headers
        $html = preg_replace_callback(
            '/<th\b[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                if (strpos($tag, 'style=') === false) {
                    return str_replace('<th', '<th style="border: 1px solid #d0d7de; padding: 6px 13px; font-weight: 600; text-align: left; background-color: #f6f8fa; font-size: 14px; line-height: 1.5;"', $tag);
                }
                return $tag;
            },
            $html
        );
        
        // Style table cells
        $html = preg_replace_callback(
            '/<td\b[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                if (strpos($tag, 'style=') === false) {
                    return str_replace('<td', '<td style="border: 1px solid #d0d7de; padding: 6px 13px; text-align: left; font-size: 14px; line-height: 1.5;"', $tag);
                }
                return $tag;
            },
            $html
        );
        
        // Add zebra striping
        $html = preg_replace_callback(
            '/<tbody\b[^>]*>/i',
            function($matches) {
                return $matches[0] . '<style>
                    table tbody tr:nth-child(even) { background-color: #ffffff; }
                    table tbody tr:nth-child(odd) { background-color: #f6f8fa; }
                </style>';
            },
            $html,
            1
        );
        
        return $html;
    }
    
    /**
     * Add GitHub-style code block CSS
     */
    protected function styleCodeBlocks($html): string
    {
        // First, mark all <code> inside <pre> to avoid styling them
        // Replace <pre><code with a temporary marker
        $html = preg_replace('/<pre\b[^>]*>\s*<code/iu', '<pre___CODE___<code', $html);
        
        // Style <pre> wrapper (GitHub-style, Prism.js will handle code inside)
        $html = preg_replace_callback(
            '/<pre\b[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                if (strpos($tag, 'style=') === false) {
                    return str_replace('<pre', '<pre style="padding: 16px; overflow: auto; margin: 16px 0; font-size: 85%; line-height: 1.45; font-family: \'SFMono-Regular\', Consolas, \'Liberation Mono\', Menlo, Courier, monospace;"', $tag);
                }
                return $tag;
            },
            $html
        );
        
        // Restore <pre><code markers
        $html = str_replace('<pre___CODE___<code', '<pre><code', $html);
        
        // Style inline <code> (not inside <pre>)
        // Only style <code> that doesn't have class="language-*" (which indicates it's in a code block)
        $html = preg_replace_callback(
            '/<code(?:\s+class="([^"]+)")?[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                $class = isset($matches[1]) ? $matches[1] : '';
                
                // Skip if it has language-* class (it's inside <pre>)
                if (strpos($class, 'language-') !== false) {
                    return $tag;
                }
                
                // Skip if style already exists
                if (strpos($tag, 'style=') !== false) {
                    return $tag;
                }
                
                // This is inline code, add styling (without background)
                $style = 'style="padding: 0.2em 0.4em; margin: 0; font-size: 85%; font-family: \'SFMono-Regular\', Consolas, \'Liberation Mono\', Menlo, Courier, monospace; color: #24292f;"';
                return str_replace('<code', '<code ' . $style, $tag);
            },
            $html
        );
        
        // Add Prism.js CSS and placeholder for client-side initialization
        // Script tags will be extracted and processed by client-side JavaScript
        if (strpos($html, 'prism-core.min.js') === false && strpos($html, 'prism.js') === false) {
            $prismResources = '
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-coy.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js" data-languages-path="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/"></script>
<script>
(function(){
  function runPrism(){
    if(typeof Prism==="undefined")return;
    Prism.plugins.autoloader.languages_path="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/";
    Prism.highlightAll();
  }
  if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",runPrism);}
  else{runPrism();}
})();
</script>';
            
            // Insert before closing body tag or at the end
            if (strpos($html, '</body>') !== false) {
                $html = str_replace('</body>', $prismResources . '</body>', $html);
            } elseif (strpos($html, '</html>') !== false) {
                $html = str_replace('</html>', $prismResources . '</html>', $html);
            } else {
                $html .= $prismResources;
            }
        }
        
        return $html;
    }
}
