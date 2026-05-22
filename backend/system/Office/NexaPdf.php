<?php
namespace App\System\Office;

class NexaPdf
{
    private $dompdf;
    private $options;
    
    // Available paper sizes
    public static $paperSizes = [
        'A4' => [210, 297],
        'A3' => [297, 420], 
        'A5' => [148, 210],
        'Letter' => [216, 279],
        'Legal' => [216, 356],
        'Tabloid' => [279, 432],
        'Ledger' => [432, 279],
        'Executive' => [184, 267],
        'Folio' => [216, 330],
        'B4' => [250, 353],
        'B5' => [176, 250]
    ];
    
    public function __construct($config = [])
    {
        require_once 'vendor/autoload.php';
        
        $this->options = new \Dompdf\Options();
        
        // Default configuration
        $defaultConfig = [
            'font' => 'Arial',
            'remote' => true,
            'paper' => 'A4',
            'orientation' => 'portrait',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'enable_php' => false,
            'enable_javascript' => false,
            'enable_remote' => true,
            'enable_font_subsetting' => false,
            'temp_dir' => sys_get_temp_dir()
        ];
        
        $config = array_merge($defaultConfig, $config);
        
        $this->options->set('defaultFont', $config['font']);
        $this->options->set('isRemoteEnabled', $config['remote']);
        $this->options->set('isPhpEnabled', $config['enable_php']);
        $this->options->set('isJavascriptEnabled', $config['enable_javascript']);
        $this->options->set('isRemoteEnabled', $config['enable_remote']);
        $this->options->set('isFontSubsettingEnabled', $config['enable_font_subsetting']);
        $this->options->set('tempDir', $config['temp_dir']);
        
        $this->dompdf = new \Dompdf\Dompdf($this->options);
        $this->dompdf->setPaper($config['paper'], $config['orientation']);
    }
    
    // Convert image to base64 for embedding
    public static function imageToBase64($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: $imagePath");
        }
        
        $imageData = file_get_contents($imagePath);
        $mimeType = mime_content_type($imagePath);
        $base64 = base64_encode($imageData);
        
        return "data:$mimeType;base64,$base64";
    }
    
    // Helper method to create HTML with embedded image
    public static function embedImage($imagePath, $width = null, $height = null, $alt = '')
    {
        $base64 = self::imageToBase64($imagePath);
        
        $style = '';
        if ($width) $style .= "width: {$width}px; ";
        if ($height) $style .= "height: {$height}px; ";
        
        return "<img src=\"$base64\" alt=\"$alt\" style=\"$style\" />";
    }
    
    // Create PDF with watermark image
    public function addWatermark($imagePath, $opacity = 0.3)
    {
        $base64 = self::imageToBase64($imagePath);
        
        $watermarkStyle = "
        <style>
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                opacity: $opacity;
                z-index: -1;
                pointer-events: none;
            }
        </style>
        ";
        
        return $watermarkStyle . "<div class=\"watermark\"><img src=\"$base64\" /></div>";
    }
    
    // Quick method to create PDF with images
    public static function createWithImages($html, $imagePaths = [], $filename = 'document.pdf', $config = [])
    {
        // Replace image placeholders with base64
        foreach ($imagePaths as $placeholder => $imagePath) {
            $base64 = self::imageToBase64($imagePath);
            $html = str_replace("{{$placeholder}}", $base64, $html);
        }
        
        $pdf = new self($config);
        $pdf->loadHtml($html)->output($filename);
    }
    
    // Set paper size and orientation
    public function setPaper($size = 'A4', $orientation = 'portrait')
    {
        $this->dompdf->setPaper($size, $orientation);
        return $this;
    }
    
    // Set custom paper size (width, height in mm)
    public function setCustomPaper($width, $height, $orientation = 'portrait')
    {
        $this->dompdf->setPaper([$width, $height, 'mm'], $orientation);
        return $this;
    }
    
    // Get available paper sizes
    public static function getAvailablePaperSizes()
    {
        return array_keys(self::$paperSizes);
    }
    
    // Load HTML content
    public function loadHtml($html)
    {
        $this->dompdf->loadHtml($html);
        return $this;
    }
    
    // Load HTML from file
    public function loadFile($filePath)
    {
        if (file_exists($filePath)) {
            $html = file_get_contents($filePath);
            $this->dompdf->loadHtml($html);
        }
        return $this;
    }
    
    // Generate PDF and output to browser
    public function output($filename = 'document.pdf', $download = false)
    {
        $this->dompdf->render();
        $this->dompdf->stream($filename, ['Attachment' => $download]);
    }
    
    // Save PDF to file
    public function save($filePath)
    {
        $this->dompdf->render();
        $output = $this->dompdf->output();
        file_put_contents($filePath, $output);
        return $this;
    }
    
    // Get PDF content as string
    public function getString()
    {
        $this->dompdf->render();
        return $this->dompdf->output();
    }
    
    // Quick method to create PDF from HTML
    public static function create($html, $filename = 'document.pdf', $config = [])
    {
        $pdf = new self($config);
        $pdf->loadHtml($html)->output($filename);
    }
    
    // Quick method to create PDF from template with data
    public static function createFromTemplate($templatePath, $data = [], $filename = 'document.pdf', $config = [])
    {
        if (!file_exists($templatePath)) {
            throw new Exception("Template file not found: $templatePath");
        }
        
        $html = file_get_contents($templatePath);
        
        // Replace template variables
        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        
        $pdf = new self($config);
        $pdf->loadHtml($html)->output($filename);
    }
    
    // Helper method to create different paper sizes quickly
    public static function createA4($html, $filename = 'document.pdf', $orientation = 'portrait')
    {
        return self::create($html, $filename, ['paper' => 'A4', 'orientation' => $orientation]);
    }
    
    public static function createA3($html, $filename = 'document.pdf', $orientation = 'portrait')
    {
        return self::create($html, $filename, ['paper' => 'A3', 'orientation' => $orientation]);
    }
    
    public static function createLetter($html, $filename = 'document.pdf', $orientation = 'portrait')
    {
        return self::create($html, $filename, ['paper' => 'Letter', 'orientation' => $orientation]);
    }
    
    public static function createLegal($html, $filename = 'document.pdf', $orientation = 'portrait')
    {
        return self::create($html, $filename, ['paper' => 'Legal', 'orientation' => $orientation]);
    }
    
    // Custom method to set header, body, footer
    public function set($config)
    {
        $header = '';
        $body = '';
        $footer = '';
        $filename = $config['nama_file'] ?? 'document';
        
        // Load header
        if (isset($config['header'])) {
            if (file_exists($config['header'] . '.html')) {
                $header = file_get_contents($config['header'] . '.html');
            } else {
                $header = $config['header'];
            }
        }
        
        // Load body
        if (isset($config['body'])) {
            if (file_exists($config['body'] . '.html')) {
                $body = file_get_contents($config['body'] . '.html');
            } else {
                $body = $config['body'];
            }
        }
        
        // Load footer
        if (isset($config['footer'])) {
            if (file_exists($config['footer'] . '.html')) {
                $footer = file_get_contents($config['footer'] . '.html');
            } else {
                $footer = $config['footer'];
            }
        }
        
        // Combine all parts
        $fullHtml = $this->combineHtmlParts($header, $body, $footer);
        
        // Load HTML and output
        $this->loadHtml($fullHtml);
        $this->output($filename . '.pdf');
        
        return $this;
    }
    
    // Enhanced custom method with page control
    public function setWithPageControl($config)
    {
        $header = '';
        $body = '';
        $footer = '';
        $filename = $config['nama_file'] ?? 'document';
        $pageNumbers = $config['page_numbers'] ?? true;
        
        // Load header
        if (isset($config['header'])) {
            if (file_exists($config['header'] . '.html')) {
                $header = file_get_contents($config['header'] . '.html');
            } else {
                $header = $config['header'];
            }
        }
        
        // Load body
        if (isset($config['body'])) {
            if (file_exists($config['body'] . '.html')) {
                $body = file_get_contents($config['body'] . '.html');
            } else {
                $body = $config['body'];
            }
        }
        
        // Load footer
        if (isset($config['footer'])) {
            if (file_exists($config['footer'] . '.html')) {
                $footer = file_get_contents($config['footer'] . '.html');
            } else {
                $footer = $config['footer'];
            }
        }
        
        // Add page numbers to footer if enabled
        if ($pageNumbers) {
            $footer .= '<div style="text-align: center; margin-top: 10px; font-size: 10px;">
                        <script type="text/php">
                            if (isset($pdf)) {
                                $font = $fontMetrics->getFont("Arial", "normal");
                                $size = 10;
                                $pageText = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
                                $pdf->text(250, 820, $pageText, $font, $size);
                            }
                        </script>
                        </div>';
        }
        
        // Combine all parts with proper positioning
        $fullHtml = $this->combineHtmlPartsAdvanced($header, $body, $footer);
        
        // Load HTML and output
        $this->loadHtml($fullHtml);
        $this->output($filename . '.pdf');
        
        return $this;
    }
    
    // Advanced HTML combination with better positioning
    private function combineHtmlPartsAdvanced($header, $body, $footer)
    {
        $bodyContent = $this->extractBodyContent($body);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 0;
                    size: A4 portrait;
                }
                
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 0;
                    font-size: 12px;
                }
                
                .pdf-header {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 80px;
                    background: white;
                    border-bottom: 1px solid #ddd;
                    z-index: 1000;
                    padding: 10px 20px;
                    box-sizing: border-box;
                }
                
                .pdf-footer {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 60px;
                    background: white;
                    border-top: 1px solid #ddd;
                    z-index: 1000;
                    padding: 10px 20px;
                    box-sizing: border-box;
                }
                
                .pdf-body {
                    margin-top: 100px;
                    margin-bottom: 80px;
                    padding: 0 20px;
                    min-height: calc(100vh - 180px);
                }
                
                /* Table improvements */
                table {
                    page-break-inside: avoid;
                    border-collapse: collapse;
                }
                
                tr {
                    page-break-inside: avoid;
                }
                
                /* Avoid orphaned headers */
                h1, h2, h3, h4, h5, h6 {
                    page-break-after: avoid;
                }
                
                /* Force page break */
                .page-break {
                    page-break-before: always;
                }
            </style>
        </head>
        <body>
            <div class="pdf-header">
                ' . $header . '
            </div>
            
            <div class="pdf-footer">
                ' . $footer . '
            </div>
            
            <div class="pdf-body">
                ' . $bodyContent . '
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    // Helper method to combine HTML parts
    private function combineHtmlParts($header, $body, $footer)
    {
        // Extract body content if it's a complete HTML document
        $bodyContent = $this->extractBodyContent($body);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin-top: 80mm;
                    margin-bottom: 60mm;
                    margin-left: 20mm;
                    margin-right: 20mm;
                }
                
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 0; 
                }
                
                .pdf-header {
                    position: fixed;
                    top: -70mm;
                    left: -20mm;
                    right: -20mm;
                    height: 60mm;
                    background: white;
                    z-index: 1000;
                }
                
                .pdf-footer {
                    position: fixed;
                    bottom: -50mm;
                    left: -20mm;
                    right: -20mm;
                    height: 40mm;
                    background: white;
                    z-index: 1000;
                }
                
                .pdf-body {
                    margin: 0;
                    padding: 20px 0;
                }
                
                /* Page break control */
                .page-break {
                    page-break-after: always;
                }
                
                /* Avoid breaking inside elements */
                .no-break {
                    page-break-inside: avoid;
                }
            </style>
        </head>
        <body>
            <div class="pdf-header">
                ' . $header . '
            </div>
            
            <div class="pdf-footer">
                ' . $footer . '
            </div>
            
            <div class="pdf-body">
                ' . $bodyContent . '
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    // Extract content from body tag or return as is
    private function extractBodyContent($html)
    {
        // If it's a complete HTML document, extract body content
        if (strpos($html, '<body') !== false) {
            preg_match('/<body[^>]*>(.*?)<\/body>/s', $html, $matches);
            return isset($matches[1]) ? $matches[1] : $html;
        }
        
        // If it contains style tag, preserve it
        if (strpos($html, '<style') !== false) {
            return $html;
        }
        
        // Return as is for simple HTML content
        return $html;
    }
    
    // Quick method for template-based PDF
    public static function createFromParts($config)
    {
        $pdf = new self();
        return $pdf->set($config);
    }
}
// $pdf = new NexaPdf();
// $pdf->setWithPageControl([
//     "nama_file" => 'proper_positioning_test',
//     "header" => 'test_header',
//     "body" => 'test_body', 
//     "footer" => 'test_footer',
//     "page_numbers" => true
// ]);
?>
