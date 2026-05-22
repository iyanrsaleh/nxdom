<?php
namespace App\System\Office;

class NexaDocx
{
    private $phpWord;
    private $section;
    private $config;
    
    // Available paper sizes for Word documents
    public static $paperSizes = [
        'A4' => ['width' => 8.27, 'height' => 11.69],
        'A3' => ['width' => 11.69, 'height' => 16.54],
        'A5' => ['width' => 5.83, 'height' => 8.27],
        'Letter' => ['width' => 8.5, 'height' => 11],
        'Legal' => ['width' => 8.5, 'height' => 14],
        'Tabloid' => ['width' => 11, 'height' => 17],
        'Executive' => ['width' => 7.25, 'height' => 10.5]
    ];
    
    public function __construct($config = [])
    {
        require_once 'vendor/autoload.php';
        
        // Default configuration
        $defaultConfig = [
            'font' => 'Arial',
            'font_size' => 11,
            'paper' => 'A4',
            'orientation' => 'portrait',
            'margin_top' => 1,
            'margin_right' => 1,
            'margin_bottom' => 1,
            'margin_left' => 1,
            'line_spacing' => 1.15,
            'page_numbering' => false
        ];
        
        $this->config = array_merge($defaultConfig, $config);
        
        $this->phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Set default font
        $this->phpWord->setDefaultFontName($this->config['font']);
        $this->phpWord->setDefaultFontSize($this->config['font_size']);
        
        // Create first section with page setup
        $this->section = $this->phpWord->addSection($this->getSectionStyle());
    }
    
    // Get section style based on config
    private function getSectionStyle()
    {
        $paperSize = self::$paperSizes[$this->config['paper']];
        
        return [
            'pageSizeW' => $paperSize['width'] * 1440, // Convert inches to twips
            'pageSizeH' => $paperSize['height'] * 1440,
            'orientation' => $this->config['orientation'],
            'marginTop' => $this->config['margin_top'] * 1440,
            'marginRight' => $this->config['margin_right'] * 1440,
            'marginBottom' => $this->config['margin_bottom'] * 1440,
            'marginLeft' => $this->config['margin_left'] * 1440
        ];
    }
    
    // Set paper size and orientation
    public function setPaper($size = 'A4', $orientation = 'portrait')
    {
        $this->config['paper'] = $size;
        $this->config['orientation'] = $orientation;
        return $this;
    }
    
    // Set custom paper size (width, height in inches)
    public function setCustomPaper($width, $height, $orientation = 'portrait')
    {
        self::$paperSizes['Custom'] = ['width' => $width, 'height' => $height];
        $this->config['paper'] = 'Custom';
        $this->config['orientation'] = $orientation;
        return $this;
    }
    
    // Get available paper sizes
    public static function getAvailablePaperSizes()
    {
        return array_keys(self::$paperSizes);
    }
    
    // Add text with formatting
    public function addText($text, $style = [])
    {
        $defaultStyle = [
            'name' => $this->config['font'],
            'size' => $this->config['font_size']
        ];
        
        $textStyle = array_merge($defaultStyle, $style);
        $this->section->addText($text, $textStyle);
        return $this;
    }
    
    // Add heading
    public function addHeading($text, $level = 1, $style = [])
    {
        $defaultStyle = [
            'name' => $this->config['font'],
            'size' => $this->config['font_size'] + (4 - $level) * 2,
            'bold' => true
        ];
        
        $headingStyle = array_merge($defaultStyle, $style);
        $this->section->addText($text, $headingStyle);
        $this->section->addTextBreak();
        return $this;
    }
    
    // Add paragraph
    public function addParagraph($text, $style = [])
    {
        $paragraphStyle = [
            'lineHeight' => $this->config['line_spacing']
        ];
        
        $this->section->addText($text, $style, $paragraphStyle);
        $this->section->addTextBreak();
        return $this;
    }
    
    // Add table
    public function addTable($data, $style = [])
    {
        $defaultTableStyle = [
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80
        ];
        
        $tableStyle = array_merge($defaultTableStyle, $style);
        $table = $this->section->addTable($tableStyle);
        
        // Add header row if exists
        if (!empty($data)) {
            $firstRow = reset($data);
            if (is_array($firstRow)) {
                // Add header
                $table->addRow();
                foreach (array_keys($firstRow) as $header) {
                    $table->addCell(2000)->addText($header, ['bold' => true]);
                }
                
                // Add data rows
                foreach ($data as $row) {
                    $table->addRow();
                    foreach ($row as $cell) {
                        $table->addCell(2000)->addText($cell);
                    }
                }
            }
        }
        
        return $this;
    }
    
    // Add image
    public function addImage($imagePath, $style = [])
    {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: $imagePath");
        }
        
        $defaultStyle = [
            'width' => 200,
            'height' => 150,
            'wrappingStyle' => 'inline'
        ];
        
        $imageStyle = array_merge($defaultStyle, $style);
        $this->section->addImage($imagePath, $imageStyle);
        return $this;
    }
    
    // Add page break
    public function addPageBreak()
    {
        $this->section->addPageBreak();
        return $this;
    }
    
    // Load content from HTML (enhanced conversion with basic formatting)
    public function loadHtml($html)
    {
        // Remove style tags completely (including content)
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Remove script tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        
        // Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        // Convert headings with preserved formatting
        for ($i = 1; $i <= 6; $i++) {
            $html = preg_replace_callback("/<h{$i}[^>]*>(.*?)<\/h{$i}>/is", function($matches) use ($i) {
                $text = $this->extractTextWithBasicFormatting($matches[1]);
                if (trim($text)) {
                    $fontSize = 16 - ($i - 1) * 2; // H1=16, H2=14, H3=12, etc
                    $this->addHeading(trim($text), $i, ['size' => $fontSize, 'bold' => true]);
                }
                return '';
            }, $html);
        }
        
        // Convert divs with class info
        $html = preg_replace_callback('/<div[^>]*class=["\']([^"\']*)["\'][^>]*>(.*?)<\/div>/is', function($matches) {
            $class = $matches[1];
            $content = $matches[2];
            
            // Handle specific classes
            $style = [];
            if (strpos($class, 'header') !== false) {
                $style = ['bold' => true, 'size' => 14, 'color' => '2196F3'];
            } elseif (strpos($class, 'footer') !== false) {
                $style = ['size' => 10, 'color' => '666666'];
            } elseif (strpos($class, 'info') !== false || strpos($class, 'alert') !== false) {
                $style = ['italic' => true];
            }
            
            $text = $this->extractTextWithBasicFormatting($content);
            if (trim($text) && !preg_match('/<(h[1-6]|p|div|table)/i', $content)) {
                $this->addText(trim($text), $style);
                $this->section->addTextBreak();
            }
            return '';
        }, $html);
        
        // Convert paragraphs
        $html = preg_replace_callback('/<p[^>]*>(.*?)<\/p>/is', function($matches) {
            $text = $this->extractTextWithBasicFormatting($matches[1]);
            if (trim($text)) {
                $this->addParagraph(trim($text));
            }
            return '';
        }, $html);
        
        // Convert simple tables with better formatting
        $html = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function($matches) {
            $tableHtml = $matches[1];
            $rows = [];
            
            // Extract table rows
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $rowMatches);
            foreach ($rowMatches[1] as $rowHtml) {
                $cells = [];
                preg_match_all('/<t([hd])[^>]*>(.*?)<\/t[hd]>/is', $rowHtml, $cellMatches);
                for ($i = 0; $i < count($cellMatches[0]); $i++) {
                    $cellType = $cellMatches[1][$i]; // 'h' for th, 'd' for td
                    $cellContent = $cellMatches[2][$i];
                    $cellText = $this->extractTextWithBasicFormatting($cellContent);
                    $cells[] = [
                        'text' => trim($cellText),
                        'isHeader' => ($cellType === 'h')
                    ];
                }
                if (!empty($cells)) {
                    $rows[] = $cells;
                }
            }
            
            if (!empty($rows)) {
                $this->addEnhancedTable($rows);
            }
            return '';
        }, $html);
        
        // Handle remaining content
        $remainingText = strip_tags($html);
        $remainingText = html_entity_decode($remainingText, ENT_QUOTES, 'UTF-8');
        
        if (trim($remainingText)) {
            $lines = explode("\n", $remainingText);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && strlen($line) > 3) {
                    $this->addText($line);
                    $this->section->addTextBreak();
                }
            }
        }
        
        return $this;
    }
    
    // Extract text while preserving basic formatting
    private function extractTextWithBasicFormatting($html)
    {
        // Handle bold/strong tags
        $html = preg_replace('/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/is', '**$2**', $html);
        
        // Handle italic/em tags
        $html = preg_replace('/<(em|i)[^>]*>(.*?)<\/(em|i)>/is', '*$2*', $html);
        
        // Strip remaining tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        return trim($text);
    }
    
    // Enhanced table with proper formatting
    private function addEnhancedTable($rows)
    {
        if (empty($rows)) return;
        
        $table = $this->section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 100 * 50
        ]);
        
        foreach ($rows as $rowIndex => $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $cellObj = $table->addCell(2000);
                
                if ($cell['isHeader'] || $rowIndex === 0) {
                    // Header cells
                    $cellObj->addText($cell['text'], [
                        'bold' => true,
                        'color' => 'ffffff'
                    ]);
                } else {
                    // Regular cells
                    $cellObj->addText($cell['text']);
                }
            }
        }
    }
    
    // Improved header/footer handling
    public function set($config)
    {
        $filename = $config['nama_file'] ?? 'document';
        
        // Load and add header with formatting
        if (isset($config['header'])) {
            $headerContent = $this->loadContent($config['header']);
            $headerText = $this->extractFormattedContent($headerContent);
            if ($headerText) {
                $header = $this->section->addHeader();
                $header->addText($headerText, [
                    'bold' => true, 
                    'size' => 14,
                    'color' => '2196F3'
                ]);
            }
        }
        
        // Load and add body
        if (isset($config['body'])) {
            $bodyContent = $this->loadContent($config['body']);
            $this->loadHtml($bodyContent);
        }
        
        // Load and add footer with formatting
        if (isset($config['footer'])) {
            $footerContent = $this->loadContent($config['footer']);
            $footerText = $this->extractFormattedContent($footerContent);
            if ($footerText) {
                $footer = $this->section->addFooter();
                $footer->addText($footerText, [
                    'size' => 9,
                    'color' => '666666'
                ]);
            }
        }
        
        // Add page numbers
        if (isset($config['page_numbers']) && $config['page_numbers']) {
            if (!isset($footer)) {
                $footer = $this->section->addFooter();
            }
            $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', 
                ['size' => 9], 
                ['alignment' => 'center']
            );
        }
        
        $this->output($filename . '.docx');
        return $this;
    }
    
    // Extract formatted content for headers/footers
    private function extractFormattedContent($html)
    {
        // Remove style tags
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Extract content from specific divs
        if (preg_match('/<div[^>]*class=["\'][^"\']*(?:header|footer)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $content = $matches[1];
            
            // Look for title/logo text
            if (preg_match('/<[^>]*class=["\'][^"\']*(?:logo|title)[^"\']*["\'][^>]*>(.*?)<\/[^>]*>/is', $content, $titleMatch)) {
                return strip_tags($titleMatch[1]);
            }
            
            // Extract h1-h6 content
            if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $headingMatch)) {
                return strip_tags($headingMatch[1]);
            }
            
            return strip_tags($content);
        }
        
        // Fallback: extract clean text
        return $this->extractCleanText($html);
    }
    
    // Helper method to load content from file or string
    private function loadContent($content)
    {
        if (file_exists($content . '.html')) {
            return file_get_contents($content . '.html');
        } else {
            return $content;
        }
    }
    
    // Helper method to extract clean text from HTML
    private function extractCleanText($html)
    {
        // Remove style and script tags
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        
        // Strip all HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    // Quick paper methods
    public static function createA4($content, $filename = 'document.docx', $orientation = 'portrait')
    {
        return self::create($content, $filename, ['paper' => 'A4', 'orientation' => $orientation]);
    }
    
    public static function createA3($content, $filename = 'document.docx', $orientation = 'portrait')
    {
        return self::create($content, $filename, ['paper' => 'A3', 'orientation' => $orientation]);
    }
    
    public static function createLetter($content, $filename = 'document.docx', $orientation = 'portrait')
    {
        return self::create($content, $filename, ['paper' => 'Letter', 'orientation' => $orientation]);
    }
    
    public static function createLegal($content, $filename = 'document.docx', $orientation = 'portrait')
    {
        return self::create($content, $filename, ['paper' => 'Legal', 'orientation' => $orientation]);
    }
    
    // Load content from file
    public function loadFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        $content = file_get_contents($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        if (strtolower($extension) === 'html') {
            return $this->loadHtml($content);
        } else {
            return $this->addText($content);
        }
    }
    
    // Save document
    public function save($filePath, $format = 'Word2007')
    {
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($this->phpWord, $format);
        $writer->save($filePath);
        return $this;
    }
    
    // Output to browser
    public function output($filename = 'document.docx', $download = true)
    {
        if ($download) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($this->phpWord, 'Word2007');
        $writer->save('php://output');
        return $this;
    }
    
    // Get document as string (base64)
    public function getString()
    {
        ob_start();
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($this->phpWord, 'Word2007');
        $writer->save('php://output');
        $content = ob_get_clean();
        return base64_encode($content);
    }
    
    // Quick method to create document
    public static function create($content, $filename = 'document.docx', $config = [])
    {
        $docx = new self($config);
        
        if (is_string($content)) {
            $docx->addText($content);
        }
        
        $docx->output($filename);
    }
    
    // Template method with data replacement
    public static function createFromTemplate($templatePath, $data = [], $filename = 'document.docx', $config = [])
    {
        if (!file_exists($templatePath)) {
            throw new Exception("Template file not found: $templatePath");
        }
        
        $content = file_get_contents($templatePath);
        
        // Replace template variables
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        $docx = new self($config);
        $docx->loadHtml($content);
        $docx->output($filename);
    }
}

?>
