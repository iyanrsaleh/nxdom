<?php
/**
 * NexaRaw - Enhanced Table Data Formatter
 * 
 * Handles both single arrays and array of arrays
 * Improved nested data structure support
 * Better formatting for complex data types
 * Enhanced support for API response structures
 * 
 * @author NexaUI Framework
 * @version 2.1.0
 */

namespace App\System\Helpers;

class NexaRaw
{
    private $rows;
    private $headers;
    private $colWidths = [];
    private $isSingleRow = false;
    private $isApiResponse = false;
    private $apiMetadata = [];

    /**
     * @param array $data Single array, array of arrays, or API response structure
     */
    public function __construct(array $data)
    {
        $this->processInputData($data);
        $this->calculateColWidths();
    }

    /**
     * Process input data and determine structure type
     */
    private function processInputData(array $data): void
    {
        // Check if this looks like an API response structure
        if ($this->isApiResponseStructure($data)) {
            $this->isApiResponse = true;
            $this->processApiResponse($data);
            return;
        }

        // Check if this is a single row (associative array with scalar/simple values)
        if ($this->isSingleRowData($data)) {
            $this->isSingleRow = true;
            $this->processSingleRow($data);
            return;
        }

        // This should be multiple rows
        $this->processMultipleRows($data);
    }

    /**
     * Check if data looks like an API response structure
     */
    private function isApiResponseStructure(array $data): bool
    {
        // Common API response patterns:
        // {"status": "success", "data": [...]}
        // {"success": true, "data": [...]}
        // {"error": false, "result": [...]}
        
        $apiKeys = ['data', 'result', 'results', 'items', 'records'];
        $statusKeys = ['status', 'success', 'error', 'code'];
        
        $hasStatusKey = false;
        $hasDataKey = false;
        
        foreach ($statusKeys as $key) {
            if (array_key_exists($key, $data)) {
                $hasStatusKey = true;
                break;
            }
        }
        
        foreach ($apiKeys as $key) {
            if (array_key_exists($key, $data) && is_array($data[$key])) {
                $hasDataKey = true;
                break;
            }
        }
        
        return $hasStatusKey && $hasDataKey;
    }

    /**
     * Process API response structure
     */
    private function processApiResponse(array $data): void
    {
        // Extract metadata (non-data fields)
        $metadataKeys = ['status', 'success', 'error', 'code', 'message', 'meta', 'pagination'];
        $dataKeys = ['data', 'result', 'results', 'items', 'records'];
        
        $this->apiMetadata = [];
        $actualData = [];
        
        // Separate metadata from actual data
        foreach ($data as $key => $value) {
            if (in_array($key, $metadataKeys)) {
                $this->apiMetadata[$key] = $value;
            } elseif (in_array($key, $dataKeys) && is_array($value)) {
                $actualData = $value;
            } else {
                // Unknown keys go to metadata
                $this->apiMetadata[$key] = $value;
            }
        }
        
        // Process the actual data
        if (!empty($actualData)) {
            if ($this->isSingleRowData($actualData)) {
                $this->isSingleRow = true;
                $this->processSingleRow($actualData);
            } else {
                $this->processMultipleRows($actualData);
            }
        } else {
            // No data found, show metadata as single row
            $this->isSingleRow = true;
            $this->processSingleRow($this->apiMetadata);
        }
    }

    /**
     * Check if data is a single row (associative array with scalar values or simple arrays)
     */
    private function isSingleRowData(array $data): bool
    {
        // If it's not an associative array, it's probably multiple rows
        if (array_keys($data) === range(0, count($data) - 1)) {
            return false;
        }
        
        // Check if this looks like a single record
        // If most values are scalar or simple arrays, treat as single row
        $scalarCount = 0;
        $arrayCount = 0;
        
        foreach ($data as $value) {
            if (is_scalar($value) || is_null($value)) {
                $scalarCount++;
            } elseif (is_array($value)) {
                $arrayCount++;
            }
        }
        
        // If mostly scalar values, treat as single row
        return $scalarCount >= $arrayCount;
    }

    /**
     * Process single row data
     */
    private function processSingleRow(array $data): void
    {
        $this->headers = ['Field', 'Value'];
        $this->rows = [];
        
        foreach ($data as $key => $value) {
            $this->rows[] = [
                'Field' => $this->formatKey($key),
                'Value' => $this->formatValue($value)
            ];
        }
    }

    /**
     * Process multiple rows data
     */
    private function processMultipleRows(array $data): void
    {
        if (empty($data)) {
            $this->headers = ['Message'];
            $this->rows = [['Message' => 'No data available']];
            return;
        }

        // Get headers from first row or create numeric indices
        $firstRow = reset($data);
        
        if (is_array($firstRow)) {
            // If first row is associative, use its keys as headers
            if (array_keys($firstRow) !== range(0, count($firstRow) - 1)) {
                $this->headers = array_keys($firstRow);
            } else {
                // Numeric array, create column headers
                $this->headers = [];
                for ($i = 0; $i < count($firstRow); $i++) {
                    $this->headers[] = 'Column_' . ($i + 1);
                }
            }
        } else {
            // Single values, create simple structure
            $this->headers = ['Index', 'Value'];
        }

        // Process all rows
        $this->rows = [];
        foreach ($data as $index => $row) {
            if (is_array($row)) {
                $processedRow = [];
                foreach ($this->headers as $i => $header) {
                    if (array_keys($row) !== range(0, count($row) - 1)) {
                        // Associative array
                        $processedRow[$header] = $this->formatValue($row[$header] ?? '');
                    } else {
                        // Numeric array
                        $processedRow[$header] = $this->formatValue($row[$i] ?? '');
                    }
                }
                $this->rows[] = $processedRow;
            } else {
                // Single value
                $this->rows[] = [
                    'Index' => $index,
                    'Value' => $this->formatValue($row)
                ];
            }
        }
    }

    /**
     * Format key names for display
     */
    private function formatKey(string $key): string
    {
        // Convert snake_case and camelCase to readable format
        $formatted = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $formatted = str_replace('_', ' ', $formatted);
        return ucwords($formatted);
    }

    /**
     * Format values for display
     */
    private function formatValue($value): string
    {
        if (is_null($value)) {
            return '[NULL]';
        } elseif (is_bool($value)) {
            return $value ? '[TRUE]' : '[FALSE]';
        } elseif (is_array($value)) {
            if (empty($value)) {
                return '[EMPTY ARRAY]';
            } elseif (count($value) <= 5 && $this->isSimpleArray($value)) {
                // Small simple arrays - show inline
                return '[' . implode(', ', array_map([$this, 'formatValue'], $value)) . ']';
            } else {
                // Large or complex arrays - show summary
                return '[ARRAY: ' . count($value) . ' items]';
            }
        } elseif (is_object($value)) {
            return '[OBJECT: ' . get_class($value) . ']';
        } elseif (is_string($value)) {
            if (strlen($value) > 100) {
                return substr($value, 0, 97) . '...';
            }
            return $value;
        } else {
            return (string) $value;
        }
    }

    /**
     * Check if array is simple (only scalar values)
     */
    private function isSimpleArray(array $array): bool
    {
        foreach ($array as $value) {
            if (!is_scalar($value) && !is_null($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Calculate column widths with improved consistency for perfect alignment
     */
    private function calculateColWidths(): void
    {
        $this->colWidths = [];
        
        // Initialize with header widths (using mb_strlen for proper character counting)
        foreach ($this->headers as $header) {
            $this->colWidths[$header] = mb_strlen($header, 'UTF-8');
        }
        
        // Check row data widths
        foreach ($this->rows as $row) {
            foreach ($this->headers as $header) {
                if (isset($row[$header])) {
                    $value = (string)$row[$header];
                    $length = mb_strlen($value, 'UTF-8');
                    if ($length > $this->colWidths[$header]) {
                        $this->colWidths[$header] = $length;
                    }
                }
            }
        }
        
        // Set consistent minimum width and maximum width
        foreach ($this->colWidths as $header => $width) {
            // Minimum width based on content type
            $minWidth = max(mb_strlen($header, 'UTF-8'), 8);
            
            if ($width < $minWidth) {
                $this->colWidths[$header] = $minWidth;
            } elseif ($width > 50) {
                $this->colWidths[$header] = 50; // Max width 50 untuk readability
            }
        }
        
        // Ensure all widths are consistent (add padding for visual balance)
        foreach ($this->colWidths as $header => $width) {
            $this->colWidths[$header] = $width + 1; // Add 1 for visual padding
        }
    }

    /**
     * Render standard table
     */
    public function render(bool $withPre = false): void
    {
        if ($withPre) echo "<pre style='margin-left:24%;margin-top:5%; overflow-x: auto; font-family: \"Courier New\", monospace; font-size: 12px;'>";
         
        // Show API metadata if this is an API response
        if ($this->isApiResponse && !empty($this->apiMetadata)) {
            $this->renderApiMetadata();
            echo "\n";
        }
        
        $this->renderTable();
        
        if ($withPre) echo "</pre>";
    }

    /**
     * Render detailed table with additional information
     */
    public function renderDetailed(bool $withPre = false): void
    {
        if ($withPre) echo "<pre style='background: #f0f8ff; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: \"Courier New\", monospace; font-size: 12px;'>";
        
        // Show structure information
        $this->renderStructureInfo();
        echo "\n";
        
        // Show API metadata if this is an API response
        if ($this->isApiResponse && !empty($this->apiMetadata)) {
            $this->renderApiMetadata();
            echo "\n";
        }
        
        $this->renderTable();
        
        // Show additional statistics
        echo "\n";
        $this->renderStatistics();
        
        if ($withPre) echo "</pre>";
    }

    /**
     * Render API metadata section with lighter borders and better alignment
     */
    private function renderApiMetadata(): void
    {
        echo "┌───────────────────────────────────────────────────────────────────┐\n";
        echo "│                            API METADATA                           │\n";
        echo "├───────────────────────────────────────────────────────────────────┤\n";
        
        foreach ($this->apiMetadata as $key => $value) {
            $formattedKey = $this->padString($this->formatKey($key), 16, ' ', STR_PAD_RIGHT);
            $formattedValue = $this->formatValue($value);
            
            if (mb_strlen($formattedValue, 'UTF-8') > 48) {
                $formattedValue = mb_substr($formattedValue, 0, 45, 'UTF-8') . '...';
            }
            
            $paddedValue = $this->padString($formattedValue, 48, ' ', STR_PAD_RIGHT);
            echo "│ {$formattedKey}│ {$paddedValue} │\n";
        }
        
        echo "└───────────────────────────────────────────────────────────────────┘\n";
    }

    /**
     * Render structure information with lighter borders
     */
    private function renderStructureInfo(): void
    {
        echo "┌───────────────────────────────────────────────────────────────────┐\n";
        echo "│                          STRUCTURE INFO                           │\n";
        echo "├───────────────────────────────────────────────────────────────────┤\n";
        
        $type = $this->isApiResponse ? 'API Response' : ($this->isSingleRow ? 'Single Row' : 'Multiple Rows');
        $rowCount = count($this->rows);
        $colCount = count($this->headers);
        
        echo "│ Type: " . str_pad($type, 58) . " │\n";
        echo "│ Rows: " . str_pad((string)$rowCount, 58) . " │\n";
        echo "│ Columns: " . str_pad((string)$colCount, 55) . " │\n";
        
        if ($this->isApiResponse) {
            $metaCount = count($this->apiMetadata);
            echo "│ Metadata Fields: " . str_pad((string)$metaCount, 47) . " │\n";
        }
        
        echo "└───────────────────────────────────────────────────────────────────┘\n";
    }

    /**
     * Render main data table
     */
    private function renderTable(): void
    {
        if (empty($this->headers) || empty($this->rows)) {
            echo "No data to display.\n";
            return;
        }

        // Header separator
        $this->renderSeparator('top');
        $this->renderHeader();
        $this->renderSeparator('middle');
        
        // Rows
        foreach ($this->rows as $row) {
            $this->renderRow($row);
        }
        
        $this->renderSeparator('bottom');
    }

    /**
     * Render table separator with lighter border characters
     */
    private function renderSeparator(string $type): void
    {
        // Use lighter, more readable border characters
        $chars = [
            'top' => ['┌', '─', '┬', '┐'],
            'middle' => ['├', '─', '┼', '┤'],
            'bottom' => ['└', '─', '┴', '┘']
        ];
        
        echo $chars[$type][0];
        
        $isFirst = true;
        foreach ($this->headers as $header) {
            if (!$isFirst) {
                echo $chars[$type][2];
            }
            echo str_repeat($chars[$type][1], $this->colWidths[$header] + 2);
            $isFirst = false;
        }
        
        echo $chars[$type][3] . "\n";
    }

    /**
     * Render table header with proper padding and lighter borders
     */
    private function renderHeader(): void
    {
        echo "│";
        foreach ($this->headers as $header) {
            $width = $this->colWidths[$header];
            $padded = $this->padString($header, $width, ' ', STR_PAD_BOTH);
            echo " {$padded} │";
        }
        echo "\n";
    }

    /**
     * Render table row with improved alignment and lighter borders
     */
    private function renderRow(array $row): void
    {
        echo "│";
        foreach ($this->headers as $header) {
            $value = (string)($row[$header] ?? '');
            $width = $this->colWidths[$header];
            
            // Truncate if too long
            if (mb_strlen($value, 'UTF-8') > $width) {
                $value = mb_substr($value, 0, $width - 3, 'UTF-8') . '...';
            }
            
            // Better alignment: center for headers, left for text, right for numbers
            $padType = STR_PAD_LEFT; // Default to left align
            
            if (is_numeric($row[$header] ?? '')) {
                $padType = STR_PAD_LEFT; // Right align numbers
            } else {
                $padType = STR_PAD_RIGHT; // Left align text
            }
            
            $padded = $this->padString($value, $width, ' ', $padType);
            echo " {$padded} │";
        }
        echo "\n";
    }

    /**
     * Render statistics with lighter borders
     */
    private function renderStatistics(): void
    {
        echo "┌───────────────────────────────────────────────────────────────────┐\n";
        echo "│                            STATISTICS                             │\n";
        echo "├───────────────────────────────────────────────────────────────────┤\n";
        
        $totalCells = count($this->rows) * count($this->headers);
        $emptyCells = 0;
        
        foreach ($this->rows as $row) {
            foreach ($this->headers as $header) {
                if (empty($row[$header] ?? '')) {
                    $emptyCells++;
                }
            }
        }
        
        echo "│ Total Cells: " . str_pad((string)$totalCells, 52) . " │\n";
        echo "│ Empty Cells: " . str_pad((string)$emptyCells, 52) . " │\n";
        echo "│ Fill Rate: " . str_pad(round(($totalCells - $emptyCells) / $totalCells * 100, 1) . '%', 54) . " │\n";
        
        echo "└───────────────────────────────────────────────────────────────────┘\n";
    }

    /**
     * Multibyte-safe string padding function
     * Proper padding for characters with different widths
     */
    private function padString(string $input, int $padLength, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $inputLength = mb_strlen($input, 'UTF-8');
        
        if ($inputLength >= $padLength) {
            return $input;
        }
        
        $padNeeded = $padLength - $inputLength;
        $padStringLength = mb_strlen($padString, 'UTF-8');
        
        if ($padStringLength == 0) {
            return $input;
        }
        
        $padding = str_repeat($padString, ceil($padNeeded / $padStringLength));
        $padding = mb_substr($padding, 0, $padNeeded, 'UTF-8');
        
        switch ($padType) {
            case STR_PAD_LEFT:
                return $padding . $input;
            case STR_PAD_BOTH:
                $leftPad = floor($padNeeded / 2);
                $rightPad = $padNeeded - $leftPad;
                $leftPadding = mb_substr($padding, 0, $leftPad, 'UTF-8');
                $rightPadding = mb_substr($padding, 0, $rightPad, 'UTF-8');
                return $leftPadding . $input . $rightPadding;
            default: // STR_PAD_RIGHT
                return $input . $padding;
        }
    }
}