# NexaForm Documentation

## Overview

**NexaForm** adalah sistem form handling yang powerful dan mudah digunakan untuk aplikasi PHP. Class ini menyediakan validasi otomatis, upload file, response AJAX, dan template variable generation dalam satu package yang terintegrasi.

## 🚀 Key Features

- ✅ **Validasi Otomatis** - Built-in validation rules untuk berbagai tipe data
- ✅ **AJAX Support** - Automatic JSON response handling untuk AJAX requests  
- ✅ **File Upload** - Advanced file upload dengan multiple file support
- ✅ **Template Integration** - Automatic template variable generation
- ✅ **Anti-Double Submit** - Built-in protection against double submission
- ✅ **File Management** - File deletion dan cleanup utilities
- ✅ **Redirect Handling** - Automatic redirect after successful submission
- ✅ **Error Management** - Comprehensive error handling dan reporting

## 📖 Table of Contents

1. [Quick Start](#quick-start)
2. [Basic Usage](#basic-usage) 
3. [AJAX Forms](#ajax-forms)
4. [File Upload](#file-upload)
5. [Validation Rules](#validation-rules)
6. [Method Reference](#method-reference)
7. [Configuration Options](#configuration-options)
8. [Best Practices](#best-practices)
9. [Examples](#examples)

## Quick Start

### Basic Form Example

```php
<?php
// In your controller
public function processForm() {
    $form = $this->createForm()
        ->fields([
            'nama'    => 'Name|3|Nama minimal 3 karakter',
            'email'   => 'Email||Email tidak valid', 
            'umur'    => 'Number|18|99|Umur harus 18-99'
        ])
        ->setSuccess('Data berhasil disimpan!')
        ->setError('Mohon perbaiki kesalahan berikut');
    
    $result = $form->process();
    
    if ($result['success']) {
        // Handle success - data tersedia di $result['data']
        $this->saveToDatabase($result['data']);
    }
}
```

### AJAX Form (Super Simple!)

```php
<?php
public function ajaxForm() {
    $form = $this->createForm()
        ->fields([
            'nama'    => 'Name|3|Nama minimal 3 karakter',
            'email'   => 'Email||Email tidak valid'
        ])
        ->setAjax(true)  // 🎉 That's it! Auto-handles JSON response
        ->setSuccess('Berhasil!')
        ->setError('Ada kesalahan');
    
    $result = $form->process();
    // No need for manual jsResponse() - it's automatic!
}
```

## Basic Usage

### 1. Form Creation

```php
// Method 1: Via Controller (Recommended)
$form = $this->createForm();

// Method 2: Direct instantiation  
$form = new NexaForm($controller);

// Method 3: Static method
$form = NexaForm::createForm();
```

### 2. Field Configuration

```php
$form->fields([
    'field_name' => 'ValidationRule|param1|param2|ErrorMessage',
    'nama'       => 'Name|3|Nama minimal 3 karakter',
    'email'      => 'Email||Email tidak valid',
    'umur'       => 'Number|18|99|Umur harus antara 18-99',
    'foto'       => 'FileOptional|jpg,png|2048|File foto maksimal 2MB'
]);
```

### 3. Message Configuration

```php
$form->setSuccess('Data berhasil disimpan!')
     ->setError('Mohon perbaiki kesalahan berikut');
```

### 4. Process Form

```php
$result = $form->process();

if ($result['success']) {
    // Success handling
    $data = $result['data'];
    $this->saveToDatabase($data);
} else {
    // Error handling  
    $errors = $result['errors'];
    $this->handleErrors($errors);
}
```

## AJAX Forms

### Enable AJAX Mode

```php
$form->setAjax(true);  // Enables automatic JSON response
```

### AJAX Response Structure

```json
{
    "success": true,
    "processed": true,
    "message": "Berhasil memperbaharui Akun",
    "data": {
        "nama": "John Doe",
        "email": "john@example.com"
    },
    "errors": [],
    "redirect": "/dashboard"
}
```

### Frontend JavaScript

```javascript
// NexaForm automatically handles AJAX detection
// Your form will automatically get JSON response when submitted via AJAX
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch(this.action, {
        method: 'POST',
        body: new FormData(this),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } else {
            // Handle validation errors
            Object.keys(data.errors).forEach(field => {
                document.getElementById('errors_' + field).textContent = data.errors[field];
            });
        }
    });
});
```

## File Upload

### Basic File Upload

```php
$form->fields([
    'dokumen' => 'File|pdf,doc,docx|5120|File dokumen maksimal 5MB'
])
->setUpload([
    'upload_path' => 'uploads/documents/',
    'allowed_types' => 'pdf,doc,docx',
    'max_size' => 5120, // KB
    'encrypt_name' => true
]);
```

### Optional File Upload

```php
$form->fields([
    'foto' => 'FileOptional|jpg,png,gif|2048|Foto profil maksimal 2MB'
]);
```

### Multiple File Upload

```php
$form->fields([
    'dokumen1' => 'File|pdf|2048|Dokumen 1',
    'dokumen2' => 'FileOptional|pdf|2048|Dokumen 2',
    'foto'     => 'FileOptional|jpg,png|1024|Foto'
])
->setUpload([
    'upload_path' => 'uploads/',
    'allowed_types' => 'pdf,jpg,png',
    'max_size' => 2048
]);
```

### File Upload Result

```php
$result = $form->process();

if ($result['success'] && isset($result['files'])) {
    foreach ($result['files'] as $fieldName => $fileInfo) {
        echo "Field: $fieldName\n";
        echo "Path: " . $fileInfo['path'] . "\n";
        echo "Original Name: " . $fileInfo['original_name'] . "\n";
        echo "Size: " . $fileInfo['size'] . " bytes\n";
    }
}
```

### File Deletion

```php
// Delete single file
$form->deleteFile('/path/to/file.pdf');

// Delete file with info array
$fileInfo = ['path' => '/uploads/file.pdf', 'name' => 'file.pdf'];
$form->deleteFile($fileInfo);

// Get deletion result
$deleteResult = $form->getDeleteResult();
```

## Validation Rules

### Available Rules

| Rule | Format | Description | Example |
|------|--------|-------------|---------|
| **Name** | `Name|minLength|ErrorMessage` | Nama/text validation | `'nama' => 'Name|3|Nama minimal 3 karakter'` |
| **Email** | `Email||ErrorMessage` | Email validation | `'email' => 'Email||Email tidak valid'` |
| **Number** | `Number|min|max|ErrorMessage` | Numeric range | `'umur' => 'Number|18|99|Umur 18-99'` |
| **Phone** | `Phone||ErrorMessage` | Phone number | `'telepon' => 'Phone||Nomor telepon tidak valid'` |
| **Required** | `Required||ErrorMessage` | Required field | `'field' => 'Required||Field wajib diisi'` |
| **File** | `File|extensions|maxSize|ErrorMessage` | Required file | `'dok' => 'File|pdf,doc|2048|File maksimal 2MB'` |
| **FileOptional** | `FileOptional|ext|size|ErrorMessage` | Optional file | `'foto' => 'FileOptional|jpg,png|1024|Foto max 1MB'` |

### Custom Validation

```php
// You can extend validation by adding custom rules in NexaValidation trait
// or by using temporary data for complex validation

$form->setTempData('custom_rule', $customValue);
$customValidation = $form->getTempData('custom_rule');
```

## Method Reference

### Core Methods

#### `fields(array $fields): NexaForm`
Set validation rules untuk form fields.

```php
$form->fields([
    'nama' => 'Name|3|Nama minimal 3 karakter',
    'email' => 'Email||Email tidak valid'
]);
```

#### `process(bool $autoRedirect = true): array`
Process form submission dan return hasil.

```php
$result = $form->process();
// Returns: ['success' => bool, 'data' => array, 'errors' => array, ...]
```

#### `setAjax(bool $enabled = true): NexaForm`
Enable/disable AJAX mode dengan automatic JSON response.

```php
$form->setAjax(true);  // Auto-handle AJAX responses
```

#### `setSuccess(string $message): NexaForm`
Set success message.

```php
$form->setSuccess('Data berhasil disimpan!');
```

#### `setError(string $message): NexaForm`
Set error message.

```php
$form->setError('Mohon perbaiki kesalahan berikut');
```

#### `setRedirect(string $url): NexaForm`
Set redirect URL untuk successful submission.

```php
$form->setRedirect('/dashboard');
```

### File Methods

#### `setUpload(array $config): NexaForm`
Configure file upload settings.

```php
$form->setUpload([
    'upload_path' => 'uploads/',
    'allowed_types' => 'jpg,png,pdf',
    'max_size' => 2048,
    'encrypt_name' => true
]);
```

#### `deleteFile($fileInfo): NexaForm`
Delete file menggunakan NexaFile helper.

```php
$form->deleteFile('/path/to/file.pdf');
```

#### `getDeleteResult(): array|null`
Get hasil file deletion.

#### `getDeleteError(): string|null`
Get error message dari file deletion.

### Template Methods

#### `Response(array $additionalVars = [], bool $clearValues = false): array`
Generate template variables untuk view.

```php
$templateVars = $form->Response(['extra_var' => 'value'], false);
// Returns array dengan field values, errors, dan messages
```

### Data Methods

#### `setTempData($key, $value = null): NexaForm`
Set temporary data untuk validation atau processing.

```php
$form->setTempData('previous_file', $oldFileName)
     ->setTempData(['key1' => 'value1', 'key2' => 'value2']);
```

#### `getTempData($key = null, $default = null): mixed`
Get temporary data.

```php
$value = $form->getTempData('previous_file');
$allData = $form->getTempData(); // Get all temp data
```

#### `getCurrentErrors(): array`
Get current validation errors.

```php
$errors = $form->getCurrentErrors();
```

## Configuration Options

### Upload Configuration

```php
$uploadConfig = [
    'upload_path'    => 'uploads/',           // Upload directory
    'allowed_types'  => 'jpg,png,pdf,doc',   // Allowed file extensions
    'max_size'       => 2048,                // Max size in KB
    'encrypt_name'   => true,                // Encrypt filename
    'remove_spaces'  => true,                // Remove spaces from filename
    'overwrite'      => false                // Allow overwrite existing files
];

$form->setUpload($uploadConfig);
```

### Response Structure

```php
// Success Response
[
    'success'   => true,
    'processed' => true,
    'message'   => 'Success message',
    'data'      => [...], // Validated form data
    'files'     => [...], // File upload results (if any)
    'redirect'  => '/url', // Redirect URL (if set)
    'errors'    => []
]

// Error Response  
[
    'success'   => false,
    'processed' => true,
    'message'   => 'Error message',
    'data'      => [...], // Partial valid data
    'errors'    => [...], // Validation errors
    'postData'  => [...]  // Original POST data
]
```

## Best Practices

### 1. AJAX Forms (Recommended Pattern)

```php
public function ajaxAction() {
    $form = $this->createForm()
        ->fields([
            'nama'  => 'Name|3|Nama minimal 3 karakter',
            'email' => 'Email||Email tidak valid'
        ])
        ->setAjax(true)  // 🎉 Auto-handle AJAX
        ->setSuccess('Berhasil!')
        ->setError('Ada kesalahan');
    
    $result = $form->process();
    
    // Optional: Handle database operations for successful submissions
    if ($result['success']) {
        // Database operations here
        $this->saveToDatabase($result['data']);
    }
    
    // AJAX responses are handled automatically!
    // No need for manual jsResponse() calls
}
```

### 2. File Upload Forms

```php
public function uploadAction() {
    $form = $this->createForm()
        ->fields([
            'judul'    => 'Name|3|Judul minimal 3 karakter',
            'dokumen'  => 'File|pdf,doc|5120|File maksimal 5MB',
            'foto'     => 'FileOptional|jpg,png|1024|Foto opsional max 1MB'
        ])
        ->setUpload([
            'upload_path' => 'uploads/documents/',
            'allowed_types' => 'pdf,doc,jpg,png',
            'max_size' => 5120,
            'encrypt_name' => true
        ])
        ->setAjax(true)
        ->setSuccess('File berhasil diupload!')
        ->setError('Upload gagal, periksa file Anda');
    
    $result = $form->process();
    
    if ($result['success']) {
        // Save to database with file paths
        $data = $result['data'];
        $files = $result['files'] ?? [];
        
        $this->saveDocument($data, $files);
    }
}
```

### 3. Template Integration

```php
// In Controller
public function index() {
    // Initialize default template data
    $initialData = [
        'nama_value'        => '',
        'email_value'       => '',
        'errors_nama'       => '',
        'errors_email'      => '',
        'info_message'      => ''
    ];
    
    // Get form state from session (if any)
    $templateVars = $this->getState('form_data', $initialData);
    
    // Pass to template
    $this->nexaVars($templateVars);
    
    // Clear state after use
    $this->clearState('form_data');
}

public function processAction() {
    $form = $this->createForm()
        ->fields([...])
        ->setAjax(true);
        
    $result = $form->process();
    
    // For non-AJAX fallback
    if (!$result['success'] && !$this->isAjaxRequest()) {
        $templateVars = $form->Response([], false);
        $this->setState('form_data', $templateVars);
        $this->redirect('/form-page');
    }
}
```

### 4. Error Handling

```php
try {
    $result = $form->process();
    
    if ($result['success']) {
        // Success logic
        $this->logActivity('Form submitted successfully');
    } else {
        // Validation errors - handled automatically by NexaForm
        $this->logErrors($result['errors']);
    }
    
} catch (Exception $e) {
    // System errors
    $this->logError('Form processing error: ' . $e->getMessage());
    
    if ($this->isAjaxRequest()) {
        $this->jsResponse([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem'
        ]);
    } else {
        $this->redirect('/error-page');
    }
}
```

## Examples

### Complete AJAX Form Example

**Controller:**

```php
<?php
namespace App\Controllers;
use App\System\NexaController;

class ContactController extends NexaController {
    
    public function index() {
        // Initialize template data
        $this->nexaVars([
            'nama_value'         => '',
            'email_value'        => '',
            'pesan_value'        => '',
            'errors_nama'        => '',
            'errors_email'       => '',
            'errors_pesan'       => '',
            'info_message'       => ''
        ]);
    }
    
    public function submit() {
        $form = $this->createForm()
            ->fields([
                'nama'  => 'Name|3|Nama minimal 3 karakter',
                'email' => 'Email||Email tidak valid',
                'pesan' => 'Name|10|Pesan minimal 10 karakter'
            ])
            ->setAjax(true)  // 🚀 Auto AJAX handling
            ->setSuccess('Pesan berhasil dikirim!')
            ->setError('Mohon perbaiki kesalahan berikut');
        
        $result = $form->process();
        
        // Handle successful submission
        if ($result['success']) {
            // Send email, save to database, etc.
            $this->sendContactEmail($result['data']);
            $this->saveContactMessage($result['data']);
        }
        
        // AJAX response handled automatically!
    }
    
    private function sendContactEmail($data) {
        // Email logic here
    }
    
    private function saveContactMessage($data) {
        // Database save logic here
    }
}
```

**HTML Template:**

```html
<form id="contactForm" action="/contact/submit" method="POST">
    <div class="form-group">
        <label>Nama:</label>
        <input type="text" name="nama" value="{{nama_value}}" class="form-control">
        <div class="feedback text-danger">{{errors_nama}}</div>
    </div>
    
    <div class="form-group">
        <label>Email:</label>
        <input type="email" name="email" value="{{email_value}}" class="form-control">
        <div class="feedback text-danger">{{errors_email}}</div>
    </div>
    
    <div class="form-group">
        <label>Pesan:</label>
        <textarea name="pesan" class="form-control">{{pesan_value}}</textarea>
        <div class="feedback text-danger">{{errors_pesan}}</div>
    </div>
    
    <button type="submit" class="btn btn-primary">Kirim Pesan</button>
    
    <div id="info-message" class="alert mt-3" style="display:none;"></div>
</form>

<script>
// Auto-handled by nexa-core.js FormHandler
// or custom AJAX handling:

document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch(this.action, {
        method: 'POST',
        body: new FormData(this),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success handling
            document.getElementById('info-message').innerHTML = 
                '<div class="alert alert-success">' + data.message + '</div>';
            this.reset(); // Clear form
        } else {
            // Error handling
            Object.keys(data.errors).forEach(field => {
                const errorElement = document.querySelector(`[name="${field}"]`)
                    .closest('.form-group').querySelector('.feedback');
                if (errorElement) {
                    errorElement.textContent = data.errors[field];
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});
</script>
```

### File Upload Example

**Controller:**

```php
public function uploadDocument() {
    $form = $this->createForm()
        ->fields([
            'judul'       => 'Name|3|Judul dokumen minimal 3 karakter',
            'kategori'    => 'Required||Kategori wajib dipilih',
            'dokumen'     => 'File|pdf,doc,docx|5120|File dokumen maksimal 5MB',
            'thumbnail'   => 'FileOptional|jpg,png|1024|Thumbnail opsional max 1MB'
        ])
        ->setUpload([
            'upload_path'   => 'uploads/documents/',
            'allowed_types' => 'pdf,doc,docx,jpg,png',
            'max_size'      => 5120,
            'encrypt_name'  => true
        ])
        ->setAjax(true)
        ->setSuccess('Dokumen berhasil diupload!')
        ->setError('Upload gagal, periksa file dan form Anda');
    
    $result = $form->process();
    
    if ($result['success']) {
        // Save document info to database
        $documentData = [
            'judul'     => $result['data']['judul'],
            'kategori'  => $result['data']['kategori'],
            'file_path' => $result['data']['dokumen'], // File path
            'thumbnail' => $result['data']['thumbnail'] ?? null,
            'created_at'=> date('Y-m-d H:i:s')
        ];
        
        $this->saveDocument($documentData);
        
        // Optional: Delete old file if updating
        if (!empty($_POST['old_file'])) {
            $form->deleteFile($_POST['old_file']);
        }
    }
}
```

---

## 🎯 Summary

**NexaForm** menyediakan solusi lengkap untuk form handling di PHP dengan:

- **Zero-config AJAX** - Cukup `setAjax(true)` dan semuanya otomatis!
- **Powerful Validation** - Built-in rules untuk semua kebutuhan umum
- **File Upload Made Easy** - Multi-file upload dengan konfigurasi fleksibel  
- **Template Integration** - Automatic variable generation untuk view
- **Error Handling** - Comprehensive error management

### Migration dari Manual jsResponse()

**Before (Manual):**
```php
// 20+ lines of complex conditional logic
if ($this->isAjaxRequest()) {
    $this->jsResponse([...], forceJson: true);
    return;
}
// Redirect logic...
```

**After (NexaForm):**
```php
// 1 line - everything automatic!
$form->setAjax(true);
```

🚀 **Ready to use!** NexaForm handles everything automatically - validation, file uploads, AJAX responses, redirects, dan template variables.
