<?php

namespace App\System\Helpers;

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

class NexaPreadsheet
{
    private $spreadsheet;
    private $activeSheet;
    private $uploadPath = 'uploads/';
    
    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->activeSheet = $this->spreadsheet->getActiveSheet();
        
        // Pastikan folder upload ada
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Buat instance baru NexaPreadsheet
     */
    public static function create()
    {
        return new self();
    }
    
    /**
     * Load file Excel/CSV yang sudah ada
     */
    public static function load($filepath)
    {
        $instance = new self();
        
        try {
            $reader = IOFactory::createReaderForFile($filepath);
            $instance->spreadsheet = $reader->load($filepath);
            $instance->activeSheet = $instance->spreadsheet->getActiveSheet();
            
            return $instance;
        } catch (\Exception $e) {
            throw new \Exception("Error loading file: " . $e->getMessage());
        }
    }
    
    /**
     * Set judul worksheet
     */
    public function setTitle($title)
    {
        $this->activeSheet->setTitle($title);
        return $this;
    }
    
    /**
     * Set data dari array 2 dimensi
     */
    public function setData($data, $startCell = 'A1', $hasHeader = true)
    {
        if (empty($data)) {
            return $this;
        }
        
        // Masukkan data
        $this->activeSheet->fromArray($data, null, $startCell);
        
        // Jika ada header, beri styling
        if ($hasHeader) {
            $this->styleHeader($startCell, count($data[0]));
        }
        
        return $this;
    }
    
    /**
     * Set header saja
     */
    public function setHeaders($headers, $startCell = 'A1')
    {
        $this->activeSheet->fromArray($headers, null, $startCell);
        $this->styleHeader($startCell, count($headers));
        return $this;
    }
    
    /**
     * Tambah baris data
     */
    public function addRow($data, $row = null)
    {
        if ($row === null) {
            $row = $this->activeSheet->getHighestRow() + 1;
        }
        
        $col = 1;
        foreach ($data as $value) {
            $this->activeSheet->setCellValueByColumnAndRow($col, $row, $value);
            $col++;
        }
        
        return $this;
    }
    
    /**
     * Set nilai cell tertentu
     */
    public function setCell($cell, $value)
    {
        $this->activeSheet->setCellValue($cell, $value);
        return $this;
    }
    
    /**
     * Styling header default
     */
    private function styleHeader($startCell, $columnCount)
    {
        $startCoordinate = Coordinate::coordinateFromString($startCell);
        $startColumn = $startCoordinate[0];
        $startRow = $startCoordinate[1];
        
        $endColumn = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($startColumn) + $columnCount - 1
        );
        
        $range = $startColumn . $startRow . ':' . $endColumn . $startRow;
        
        $style = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
        
        $this->activeSheet->getStyle($range)->applyFromArray($style);
        return $this;
    }
    
    /**
     * Set styling custom untuk range tertentu
     */
    public function setStyle($range, $style)
    {
        $this->activeSheet->getStyle($range)->applyFromArray($style);
        return $this;
    }
    
    /**
     * Auto size semua kolom
     */
    public function autoSizeColumns()
    {
        $highestColumn = $this->activeSheet->getHighestColumn();
        $columnRange = range('A', $highestColumn);
        
        foreach ($columnRange as $column) {
            $this->activeSheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        return $this;
    }
    
    /**
     * Set lebar kolom tertentu
     */
    public function setColumnWidth($column, $width)
    {
        $this->activeSheet->getColumnDimension($column)->setWidth($width);
        return $this;
    }
    
    /**
     * Set tinggi baris tertentu
     */
    public function setRowHeight($row, $height)
    {
        $this->activeSheet->getRowDimension($row)->setRowHeight($height);
        return $this;
    }
    
    /**
     * Merge cells
     */
    public function mergeCells($range)
    {
        $this->activeSheet->mergeCells($range);
        return $this;
    }
    
    /**
     * Tambah worksheet baru
     */
    public function addSheet($title = null)
    {
        $newSheet = $this->spreadsheet->createSheet();
        if ($title) {
            $newSheet->setTitle($title);
        }
        $this->activeSheet = $newSheet;
        return $this;
    }
    
    /**
     * Pilih worksheet berdasarkan index
     */
    public function selectSheet($index)
    {
        $this->activeSheet = $this->spreadsheet->getSheet($index);
        return $this;
    }
    
    /**
     * Pilih worksheet berdasarkan nama
     */
    public function selectSheetByName($name)
    {
        $this->activeSheet = $this->spreadsheet->getSheetByName($name);
        return $this;
    }
    
    /**
     * Simpan sebagai file Excel
     */
    public function saveAsExcel($filename = null)
    {
        if ($filename === null) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.xlsx';
        }
        
        if (!str_ends_with($filename, '.xlsx')) {
            $filename .= '.xlsx';
        }
        
        $filepath = $this->uploadPath . $filename;
        
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'fullpath' => realpath($filepath)
        ];
    }
    
    /**
     * Simpan sebagai file CSV
     */
    public function saveAsCsv($filename = null)
    {
        if ($filename === null) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.csv';
        }
        
        if (!str_ends_with($filename, '.csv')) {
            $filename .= '.csv';
        }
        
        $filepath = $this->uploadPath . $filename;
        
        $writer = new Csv($this->spreadsheet);
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'fullpath' => realpath($filepath)
        ];
    }
    
    /**
     * Download langsung ke browser sebagai Excel
     */
    public function downloadAsExcel($filename = null)
    {
        if ($filename === null) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.xlsx';
        }
        
        if (!str_ends_with($filename, '.xlsx')) {
            $filename .= '.xlsx';
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Download langsung ke browser sebagai CSV
     */
    public function downloadAsCsv($filename = null)
    {
        if ($filename === null) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.csv';
        }
        
        if (!str_ends_with($filename, '.csv')) {
            $filename .= '.csv';
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Csv($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Baca data dari file dan return sebagai array
     */
    public function toArray($hasHeader = true)
    {
        $data = $this->activeSheet->toArray();
        
        if ($hasHeader && !empty($data)) {
            $headers = array_shift($data);
            
            $result = [];
            foreach ($data as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? null;
                }
                $result[] = $rowData;
            }
            
            return [
                'headers' => $headers,
                'data' => $result,
                'count' => count($result)
            ];
        }
        
        return [
            'data' => $data,
            'count' => count($data)
        ];
    }
    
    /**
     * Get informasi basic dari spreadsheet
     */
    public function getInfo()
    {
        return [
            'sheet_count' => $this->spreadsheet->getSheetCount(),
            'active_sheet' => $this->activeSheet->getTitle(),
            'highest_row' => $this->activeSheet->getHighestRow(),
            'highest_column' => $this->activeSheet->getHighestColumn(),
            'cell_count' => $this->activeSheet->getHighestRow() * Coordinate::columnIndexFromString($this->activeSheet->getHighestColumn())
        ];
    }
    
    /**
     * Bersihkan semua data di worksheet aktif
     */
    public function clear()
    {
        $this->activeSheet->disconnectCells();
        $this->activeSheet = $this->spreadsheet->getActiveSheet();
        return $this;
    }
    
    /**
     * Helper untuk membuat styling yang umum digunakan
     */
    public static function getCommonStyles()
    {
        return [
            'header' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ],
            'currency' => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
            ],
            'date' => [
                'numberFormat' => ['formatCode' => 'dd/mm/yyyy'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            'center' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            'border' => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]
        ];
    }
    
    /**
     * Quick method untuk export data array ke Excel
     */
    public static function quickExport($data, $filename = null, $headers = null)
    {
        $excel = self::create();
        
        if ($headers) {
            $excel->setHeaders($headers);
            $excel->setData($data, 'A2', false);
        } else {
            $excel->setData($data);
        }
        
        $excel->autoSizeColumns();
        
        return $excel->saveAsExcel($filename);
    }
    
    /**
     * Quick method untuk import dari file Excel/CSV
     */
    public static function quickImport($filepath, $hasHeader = true)
    {
        try {
            $excel = self::load($filepath);
            return $excel->toArray($hasHeader);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
