<?php
namespace App\System\Components;

class NexaJsonViewer {
    private static $cache = [];
    private static $assetsLoaded = false;
    private static $instanceCount = 0;
    private static $globalFunctionsLoaded = false;

    public static function renderJson($data, $baseUrl, $options = []): string
    {
        // Start output buffering to catch any potential output
        ob_start();
        
        try {
            self::$instanceCount++;
            
            $class = isset($options['class']) ? $options['class'] : 'container1';
            $title = isset($options['title']) ? $options['title'] : 'JSON Viewer';
            $style = isset($options['style']) ? $options['style'] : '';
            $id = isset($options['id']) ? $options['id'] : 'json-viewer-' . uniqid();
            
            // Handle JSON encoding with error checking
            if (is_string($data)) {
                $jsonData = $data;
                // Validate JSON string
                json_decode($jsonData);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON string provided: ' . json_last_error_msg());
                }
            } else {
                $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($jsonData === false) {
                    throw new \InvalidArgumentException('Failed to encode data as JSON: ' . json_last_error_msg());
                }
            }
            
            $output = '';
            
            // Load CSS/JS assets only once
            if (!self::$assetsLoaded) {
                $output .= self::renderAssets($baseUrl);
                self::$assetsLoaded = true;
            }
            
            // Individual instance styles
            $output .= self::renderInstanceStyles($class, $id, $style);
            
            // HTML content
            $output .= self::renderHtmlContent($class, $id, $title);
            
            // JavaScript for this instance
            $output .= self::renderInstanceScript($id, $jsonData);
            
            // Global functions (load only once)
            if (!self::$globalFunctionsLoaded) {
                $output .= self::renderGlobalFunctions();
                self::$globalFunctionsLoaded = true;
            }
            
            // Clean any existing output buffer
            ob_clean();
            
            return $output;
        } catch (\Exception $e) {
            // Clean buffer and rethrow exception
            ob_end_clean();
            throw $e;
        }
    }
    

    
    private static function renderAssets($baseUrl): string
    {
        return '
    <script>
        // Prevent duplicate script loading
        if (!window.nexaJsonViewerLoaded) {
            window.nexaJsonViewerLoaded = true;
            
            // Override customElements.define globally to prevent duplicate registration
            const originalDefine = customElements.define;
            customElements.define = function(name, constructor, options) {
                try {
                    if (customElements.get(name)) {
                        return;
                    }
                    return originalDefine.call(this, name, constructor, options);
                } catch (e) {
                    // Silent error handling
                }
            };
            
            // Load the bundle script from CDN
            const script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/@alenaksu/json-viewer@2.1.2/dist/json-viewer.bundle.js";
            script.onerror = function() {
                // Restore original define on error
                customElements.define = originalDefine;
            };
            document.head.appendChild(script);
        }
    </script>';
    }
    
    private static function renderInstanceStyles($class, $id, $customStyle): string
    {
        // Calculate offset for multiple instances - minimal spacing to match red line position
        if (self::$instanceCount == 1) {
            $topOffset = 90; // First instance keeps original position
        } else {
            $topOffset = 10; // Subsequent instances much closer - positioned where red line indicates
        }
        
        return '
    <style>
        .'.$class.' {
            margin-top:'.$topOffset.'px;
            margin-left:25%;
            max-width: 60%;
            position: relative;
        }
        #'.$id.' {
            padding-left: 30px;
        }
        .'.$class.' .copy-toolbar {
            position: absolute;
            right:10px;
            top:5px;
            z-index: 10;
            pointer-events: auto;
        }
        .'.$class.' .copy-btn {
            position: relative;
            z-index: 11;
            background: #007cba;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 2px;
            cursor: pointer;
            font-size: 13px;
            margin-left: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .'.$class.' .copy-btn:hover {
            background: #005a87;
            transform: translateY(-1px);
        }
        .'.$class.' .copy-btn:active {
            background: #004c73;
            transform: translateY(0);
        }
        .'.$class.' .copy-feedback {
            position: absolute;
            top: -30px;
            right: 0;
            z-index: 12;
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .'.$class.' .copy-feedback.show {
            opacity: 1;
            transform: translateY(-5px);
        }
        '.$customStyle.'
    </style>';
    }
    
    private static function renderHtmlContent($class, $id, $title): string
    {
        return '
    <div class="'.$class.'">
        <div class="copy-toolbar">
            <button class="copy-btn" type="button" onclick="nexaJsonCopy(\''.$id.'\')">📄</button>
            <div class="copy-feedback" id="feedback-'.$id.'">Copied!</div>
        </div>
        <json-viewer id="'.$id.'"></json-viewer>
    </div>';
    }
    
    private static function renderInstanceScript($id, $jsonData): string
    {
        return '
    <script>
        // Store data for this specific instance
        window.nexaJsonData = window.nexaJsonData || {};
        window.nexaJsonData["'.$id.'"] = '.$jsonData.';
        
        // Function to initialize viewer when ready
        function initializeViewer() {
            const viewer = document.getElementById("'.$id.'");
            if (viewer && window.nexaJsonData["'.$id.'"]) {
                // Check if custom element is available
                if (customElements.get("json-viewer")) {
                    viewer.data = window.nexaJsonData["'.$id.'"];
                } else {
                    // Wait for custom element to be defined
                    setTimeout(initializeViewer, 50);
                }
            }
        }
        
        // Initialize when DOM is ready
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initializeViewer);
        } else {
            // Small delay to ensure scripts are loaded
            setTimeout(initializeViewer, 100);
        }
    </script>';
    }
    
    private static function renderGlobalFunctions(): string
    {
        return '
    <script>
        // Global copy function (loaded only once)
        if (typeof nexaJsonCopy === "undefined") {
            function nexaJsonCopy(viewerId) {
                const data = window.nexaJsonData[viewerId];
                if (data) {
                    const text = JSON.stringify(data, null, 2);
                    nexaCopyToClipboard(text, "Copied!", viewerId);
                }
            }
            
            function nexaCopyToClipboard(text, message, viewerId) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => {
                        nexaShowCopyFeedback(message, viewerId);
                    }).catch(err => {
                        console.error("Failed to copy: ", err);
                        nexaFallbackCopy(text, message, viewerId);
                    });
                } else {
                    nexaFallbackCopy(text, message, viewerId);
                }
            }
            
            function nexaFallbackCopy(text, message, viewerId) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand("copy");
                    if (successful) {
                        nexaShowCopyFeedback(message, viewerId);
                    } else {
                        nexaShowCopyFeedback("Copy failed!", viewerId);
                    }
                } catch (err) {
                    console.error("Fallback: Could not copy text: ", err);
                    nexaShowCopyFeedback("Copy failed!", viewerId);
                }
                
                document.body.removeChild(textArea);
            }
            
            function nexaShowCopyFeedback(message, viewerId) {
                const feedback = document.getElementById("feedback-" + viewerId);
                if (feedback) {
                    feedback.textContent = message;
                    feedback.classList.add("show");
                    setTimeout(() => {
                        feedback.classList.remove("show");
                    }, 2000);
                }
            }
        }
    </script>';
    }
    
    public static function clearCache(): void
    {
        self::$cache = [];
    }
} 