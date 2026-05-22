<?php
declare(strict_types=1);
namespace App\Models\Office;
use App\System\NexaModel;
use App\System\Helpers\NexaEvent;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Exception;
use DateTime;

/**
 * HomeController - Enhanced with Integrated NexaNode for Frontend
 * Now uses Frontend namespace for public-facing pages
 */
class Ekstrak extends NexaModel
{
    /*
    |--------------------------------------------------------------------------
    | Initializes index 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public function index(array $data = []): array {
 
        // Ekstrak data Excel dari base64 menjadi JSON
        if (isset($data['bucketsStore'][0]['content']) && !empty($data['bucketsStore'][0]['content'])) {
            // Ambil skipHeaderRows dari parameter (default: 0 = tidak skip)
            $skipHeaderRows = isset($data['skipHeaderRows']) ? (int)$data['skipHeaderRows'] : 0;
            
            $excelData = $this->extractExcelToJson($data['bucketsStore'][0]['content'], $skipHeaderRows);
            $data['excelData'] = $excelData;
            return $excelData;
        }
   
    }

    /**
     * Ekstrak data Excel dari base64 string menjadi JSON
     * 
     * @param string $base64Content Base64 string atau data URI
     * @param int $skipHeaderRows Jumlah baris header yang akan di-skip (default: 0 = tidak skip)
     * @return array Data Excel dalam format array/JSON
     */
    private function extractExcelToJson(string $base64Content, int $skipHeaderRows = 0): array
    {
        $tempFile = null;
        try {
            // Trim whitespace
            $base64Content = trim($base64Content);
            
            if (empty($base64Content)) {
                throw new Exception('Base64 content is empty');
            }
            
            // Coba decode berulang sampai mendapatkan file Excel atau data URI dengan comma
            $currentContent = $base64Content;
            $maxAttempts = 10;
            $excelData = null;
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                $decoded = base64_decode($currentContent, true);
                
                // Jika decode gagal, berhenti
                if ($decoded === false) {
                    break;
                }
                
                // Cek apakah ini file Excel (PK signature)
                if (substr($decoded, 0, 2) === 'PK') {
                    $excelData = $decoded;
                    break;
                }
                
                // Cek apakah ini data URI
                if (strpos($decoded, 'data:') === 0) {
                    // Extract base64 dari data URI jika ada comma
                    if (strpos($decoded, 'base64,') !== false) {
                        // Format: data:...;base64,<actual_base64>
                        $extractedBase64 = explode('base64,', $decoded, 2)[1];
                        $currentContent = trim($extractedBase64);
                        continue;
                    } elseif (strpos($decoded, ',') !== false) {
                        // Format: data:...,<actual_base64>
                        $extractedBase64 = explode(',', $decoded, 2)[1];
                        $currentContent = trim($extractedBase64);
                        continue;
                    } else {
                        // Data URI tanpa comma, mungkin di-encode beberapa kali
                        // Coba decode lagi dengan content yang sama
                        $currentContent = $decoded;
                        continue;
                    }
                }
                
                // Bukan Excel dan bukan data URI, coba decode lagi
                $currentContent = $decoded;
            }
            
            // Jika belum dapat excelData setelah loop, coba decode sekali lagi
            if ($excelData === null) {
                $finalDecode = base64_decode($currentContent, true);
                if ($finalDecode !== false && substr($finalDecode, 0, 2) === 'PK') {
                    $excelData = $finalDecode;
                } else {
                    throw new Exception('Could not extract Excel file after ' . $maxAttempts . ' decode attempts. Last content type: ' . (strpos($currentContent, 'data:') === 0 ? 'data URI' : 'unknown') . ', preview: ' . substr($currentContent, 0, 100));
                }
            }
            
            // Validasi bahwa ini adalah file Excel (XLSX adalah ZIP file)
            // Magic bytes untuk ZIP: PK (0x50 0x4B)
            if (substr($excelData, 0, 2) !== 'PK') {
                throw new Exception('Decoded data is not a valid Excel file (missing ZIP signature). First 50 chars: ' . substr($excelData, 0, 50) . ' | Hex: ' . bin2hex(substr($excelData, 0, 20)));
            }
            
            // Validasi ukuran file
            if (strlen($excelData) < 100) {
                throw new Exception('Decoded file is too small to be a valid Excel file');
            }
            
            // Simpan ke temporary file dengan nama yang unik
            $tempDir = sys_get_temp_dir();
            $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'excel_' . uniqid() . '_' . time() . '.xlsx';
            
            $bytesWritten = file_put_contents($tempFile, $excelData);
            
            if ($bytesWritten === false || $bytesWritten === 0) {
                throw new Exception('Failed to write temporary file');
            }
            
            // Validasi file yang ditulis
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new Exception('Temporary file was not created or is empty');
            }
            
            // Baca file Excel menggunakan PhpSpreadsheet
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true); // Hanya baca data, tidak termasuk formatting
            $spreadsheet = $reader->load($tempFile);
            
            $result = [];
            $headerInfo = [];
            
            // Loop melalui semua worksheet
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                
                // Gunakan parameter skipHeaderRows yang diberikan
                // Setiap file Excel berbeda, jadi user bisa tentukan sendiri berapa baris yang di-skip
                $headerRows = max(0, $skipHeaderRows); // Pastikan tidak negatif
                
                // Simpan informasi header
                $headerInfo[$sheetName] = $headerRows;
                
                // Iterasi langsung melalui cells untuk mendapatkan nilai yang tepat
                $sheetData = [];
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
                
                for ($row = 1; $row <= $highestRow; $row++) {
                    // Skip header rows
                    if ($row <= $headerRows) {
                        continue;
                    }
                    
                    $rowData = [];
                    $hasData = false;
                    
                    // Iterasi melalui semua kolom
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $cellAddress = Coordinate::stringFromColumnIndex($col) . $row;
                        $cell = $worksheet->getCell($cellAddress);
                        
                        // Ambil nilai calculated (hasil formula) atau formatted value
                        $cellValue = $cell->getCalculatedValue();
                        
                        // Handle null
                        if ($cellValue === null) {
                            $rowData[] = '';
                            continue;
                        }
                        
                        // Handle object (RichText, DateTime, etc)
                        if (is_object($cellValue)) {
                            // RichText
                            if ($cellValue instanceof RichText) {
                                $cellValue = $cellValue->getPlainText();
                            }
                            // DateTime
                            elseif ($cellValue instanceof \DateTime) {
                                $cellValue = $cellValue->format('Y-m-d H:i:s');
                            }
                            // Cek method getPlainText
                            elseif (method_exists($cellValue, 'getPlainText')) {
                                $cellValue = $cellValue->getPlainText();
                            }
                            // Try toString
                            elseif (method_exists($cellValue, '__toString')) {
                                $cellValue = (string) $cellValue;
                            }
                            // Last resort: coba getValue() dari cell
                            else {
                                $cellValue = $cell->getValue();
                                if (is_object($cellValue)) {
                                    $cellValue = '';
                                }
                            }
                        }
                        
                        // Handle boolean
                        if (is_bool($cellValue)) {
                            $cellValue = $cellValue ? '1' : '0';
                        }
                        
                        // Convert to string untuk konsistensi
                        $cellValue = (string) $cellValue;
                        
                        // Cek apakah ada data (bukan string kosong)
                        if (trim($cellValue) !== '') {
                            $hasData = true;
                        }
                        
                        $rowData[] = $cellValue;
                    }
                    
                    // Hanya tambahkan baris yang memiliki data
                    if ($hasData) {
                        $sheetData[] = $rowData;
                    }
                }
                
                $result[$sheetName] = $sheetData;
            }
            
            return [
                'success' => true,
                'sheets' => $result,
                'sheetCount' => count($result),
                'fileSize' => strlen($excelData),
                'headerRowsSkipped' => $headerInfo
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            // Pastikan temporary file dihapus
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }







    
}