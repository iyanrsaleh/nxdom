<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;

/**
 * Example Form Controller - Testing NexaForm
 */
class ExampleController extends NexaController
{
    /**
     * AjaxPage - Menampilkan halaman AJAX form
     */
    public function ajaxpage(array $params = []): void
    {
        // Tidak perlu setData, langsung render template
    }

    /**
     * Index - Menampilkan form
     */
    public function index(array $params = []): void
    {
        // Data kategori untuk dropdown
        $categoryOptions = [
            ['value' => '1', 'label' => 'Teknologi'],
            ['value' => '2', 'label' => 'Bisnis'],
            ['value' => '3', 'label' => 'Pendidikan'],
            ['value' => '4', 'label' => 'Kesehatan'],
        ];
        
        // Initialize empty values untuk form
        $this->setData([
            'page_title' => 'Form Basics Example - NexaForm',
            'page_description' => 'Contoh implementasi NexaForm dengan validasi, dropdown, dan file upload',
            'success_message' => '',
            'informasi' => '',
            'title' => '',
            'category' => '',
            'content' => '',
            'image_info' => '',
            'action' => '/example/add'
        ]);
        
        // Restore form state jika ada error dari submit sebelumnya
        $formState = $this->getState('form_example', []);
        
        // Handle SELECT - set selected berdasarkan formState
        $category = $formState['category'] ?? '';
        foreach ($categoryOptions as &$option) {
            $option['selected'] = ($option['value'] === $category) ? 'selected' : '';
        }
        unset($option);
        
        // Assign category options untuk select (dengan selected sudah diset)
        $this->assignBlock('categories', $categoryOptions);
        
        // Assign ke template jika ada error
        if (!empty($formState)) {
            $this->nexaVars($formState);
        }
        
        // Clear form state setelah ditampilkan
        $this->clearState('form_example');
    }

    /**
     * Ajax - Memproses form submission via AJAX
     */
    public function ajax(array $params = []): void
    {
        // Setup form dengan validasi dan AJAX mode
        $form = $this->createForm()
            ->fields([
                'title' => 'Name|3|Judul minimal 3 karakter',
                'category' => 'Name|1|Kategori wajib dipilih',
                'content' => 'Name|10|Konten minimal 10 karakter',
                'image' => 'FileOptional|null|Upload file jika diperlukan'
            ])
            ->setUpload([
                'maxSize' => '2MB',
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif']
            ])
            ->setAjax(true)  // Enable AJAX mode
            ->setSuccess('Data berhasil disimpan!')
            ->setError('Mohon perbaiki kesalahan berikut!');

        // Process form - akan auto return JSON jika AJAX
        $result = $form->process();
        $this->setJsController([ 'page_title' => 'Nexa Dom Framework']);  
        // Jika sukses, simpan ke database
        if ($result['success']) {
            // TODO: Simpan ke database
            // $this->useModels('Article', 'create', [$result['data']]);
        }
    }

    /**
     * Add - Memproses form submission
     */
    public function add(array $params = []): void
    {
        // Setup form dengan validasi (OLD FORMAT - sesuai contoh)
        $form = $this->createForm()
            ->fields([
                'title' => 'Name|3|Judul minimal 3 karakter',
                'category' => 'Name|1|Kategori wajib dipilih',
                'content' => 'Name|10|Konten minimal 10 karakter',
                'image' => 'FileOptional|null|Upload file jika diperlukan'
            ])
            ->setUpload([
                'maxSize' => '2MB',
                'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif']
            ])
            ->setSuccess('✅ Data berhasil disimpan!')
            ->setError('❌ Mohon perbaiki kesalahan berikut!');

        // Proses form
        $result = $form->process();
        
        // DEBUG: Log hasil process ke file
        $logFile = __DIR__ . '/../../system/log/form_debug.log';
        $logData = "=== FORM PROCESS RESULT " . date('Y-m-d H:i:s') . " ===\n";
        $logData .= "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
        $logData .= "Result: " . print_r($result, true) . "\n";
        $logData .= "POST Data: " . print_r($_POST, true) . "\n";
        $logData .= "FILES Data: " . print_r($_FILES, true) . "\n";
        $logData .= "===================\n\n";
        file_put_contents($logFile, $logData, FILE_APPEND);
        
        // Jika SUKSES
        if ($result['success']) {
            // TODO: Simpan ke database
            // $this->useModels('Article', 'create', [$result['data']]);
            
            // Prepare response dengan field yang DIBERSIHKAN
            $clearValues = true;
            $templateVars = $form->Response($result, $clearValues);
            
            // Tambahkan pesan sukses dan HAPUS informasi "file"
            $templateVars['success_message'] = 'Data berhasil disimpan!';
            $templateVars['informasi'] = ''; // Clear informasi field
            
            // Simpan state untuk ditampilkan pesan sukses
            $this->setState('form_example', $templateVars);
            
            // Redirect ke halaman index
            $this->redirect('/example');
        }
        
        // Jika GAGAL validasi
        else {
            // Prepare response dengan field yang TIDAK DIKOSONGKAN
            $clearValues = false;
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan state error
            $this->setState('form_example', $templateVars);
            
            // Redirect kembali ke halaman index
            $this->redirect('/example');
        }
    }
}
