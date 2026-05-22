<?php

/**
 * NexaUI Form Code Generator
 * 
 * Builder untuk generate HTML code dan PHP validation code yang bisa di-copy paste
 * ke file HTML atau template dan controller
 * 
 * @author NexaUI Team
 * @version 3.0.0
 */
class NexaForm {
    
    private static $validationRules = [];
    
    /**
     * Generate floating input code
     */
    public static function input($name, $label, $type = 'text', $col = 6, $required = false, $validation = null) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $required ? 'required|' . $validation : $validation;
        } elseif ($required) {
            self::$validationRules[$name] = 'required';
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <input type="' . $type . '" id="' . $name . '" name="' . $name . '" class="form-nexa-control" placeholder=" ">
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate floating input dengan icon code
     */
    public static function inputIcon($name, $label, $icon, $type = 'text', $col = 6, $required = false, $validation = null) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $required ? 'required|' . $validation : $validation;
        } elseif ($required) {
            self::$validationRules[$name] = 'required';
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <div class="form-nexa-icon">
            <input type="' . $type . '" id="' . $name . '" name="' . $name . '" class="form-nexa-control" placeholder=" ">
            <i class="' . $icon . '"></i>
        </div>
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate select dropdown code
     */
    public static function select($name, $label, $options, $col = 6, $required = false, $validation = null) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $required ? 'required|' . $validation : $validation;
        } elseif ($required) {
            self::$validationRules[$name] = 'required';
        }
        
        $optionsHtml = '';
        foreach ($options as $value => $text) {
            $optionsHtml .= '                    <option value="' . $value . '">' . $text . '</option>' . "\n";
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <select id="' . $name . '" name="' . $name . '" class="form-nexa-control">
' . $optionsHtml . '        </select>
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate date input code
     */
    public static function date($name, $label, $col = 6, $required = false, $validation = null) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $required ? 'required|' . $validation : $validation;
        } elseif ($required) {
            self::$validationRules[$name] = 'required';
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <input type="date" id="' . $name . '" name="' . $name . '" class="form-nexa-control" placeholder=" ">
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate textarea code
     */
    public static function textarea($name, $label, $col = 12, $rows = 4, $required = false, $validation = null) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $required ? 'required|' . $validation : $validation;
        } elseif ($required) {
            self::$validationRules[$name] = 'required';
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <textarea id="' . $name . '" name="' . $name . '" class="form-nexa-control" rows="' . $rows . '" placeholder=" "></textarea>
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate search dropdown code
     */
    public static function search($name, $placeholder = 'Cari...', $col = 12, $validation = null) {
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $validation;
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-search">
        <div class="form-nexa-search-container" id="searchContainer_' . $name . '">
            <input type="text" class="form-nexa-control" name="' . $name . '" placeholder="' . $placeholder . '" id="' . $name . '">
            <div class="form-nexa-search-dropdown">
                <div class="form-nexa-search-items" id="searchItems_' . $name . '">
                    <!-- NEXA service -->
                    <div class="form-nexa-search-item" data-value="{id}">
                        <i class="fas fa-flag"></i>
                        <span>{description}</span>
                    </div>
                    <!-- END service -->
                </div>
            </div>
            <small id="errors_' . $name . '" class="error-message"></small>
        </div>
    </div>
</div>';
    }
    
    /**
     * Generate checkbox code
     */
    public static function checkbox($name, $label, $col = 12, $validation = null) {
        $colClass = "form-nx-col-$col";
        
        // Store validation rule
        if ($validation) {
            self::$validationRules[$name] = $validation;
        }
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-check">
        <input type="checkbox" class="form-nexa-check-input" id="' . $name . '" name="' . $name . '">
        <label class="form-nexa-check-label" for="' . $name . '">' . $label . '</label>
    </div>
</div>';
    }
    
    /**
     * Generate file upload code
     */
    public static function file($name, $label, $col = 6, $required = false, $maxSize = 2048, $allowedTypes = ['jpg', 'png']) {
        $req = $required ? ' *' : '';
        $colClass = "form-nx-col-$col";
        
        // Store validation rule for file
        $validationRule = $required ? 'file' : 'fileOptional';
        if ($maxSize || $allowedTypes) {
            $validationRule .= '|max:' . $maxSize;
        }
        self::$validationRules[$name] = $validationRule;
        
        return '<div class="' . $colClass . '">
    <div class="form-nexa-floating">
        <input type="file" id="' . $name . '" name="' . $name . '" class="form-nexa-control">
        <label for="' . $name . '">' . $label . $req . '</label>
        <small id="errors_' . $name . '" class="error-message"></small>
    </div>
</div>';
    }
    
    /**
     * Generate button group code
     */
    public static function buttons($buttons) {
        $buttonHtml = '';
        foreach ($buttons as $button) {
            $type = $button['type'] ?? 'button';
            $class = $button['class'] ?? 'btn btn-primary';
            $text = $button['text'] ?? 'Button';
            $id = isset($button['id']) ? ' id="' . $button['id'] . '"' : '';
            
            $buttonHtml .= '        <button type="' . $type . '" class="' . $class . '"' . $id . '>' . $text . '</button>' . "\n";
        }
        
        return '<footer>
' . $buttonHtml . '</footer>';
    }
    
    /**
     * Generate form row wrapper code
     */
    public static function row($content) {
        return '<div class="form-nexa-row">
' . $content . '
</div>';
    }
    
    /**
     * Generate complete form code
     */
    public static function form($content) {
        return '<form class="form-nexa">
' . $content . '
</form>';
    }
    
    /**
     * Generate Modal wrapper code
     */
    public static function modal($id, $title, $content, $size = 'w-800px') {
        return '<Modal id="' . $id . '" title="' . $title . '" size="' . $size . '">
' . $content . '
</Modal>';
    }
    
    /**
     * Format validation rule to match the expected format (capitalize first letter)
     */
    private static function formatValidationRule($rule) {
        $parts = explode('|', $rule);
        $formattedParts = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (strpos($part, ':') !== false) {
                // Handle rules with parameters (like min:3, max:50)
                list($ruleName, $parameters) = explode(':', $part, 2);
                $ruleName = trim($ruleName);
                
                // Capitalize first letter and handle special cases
                switch (strtolower($ruleName)) {
                    case 'required':
                        $formattedParts[] = 'Required';
                        break;
                    case 'min':
                        $formattedParts[] = 'Min:' . $parameters;
                        break;
                    case 'max':
                        $formattedParts[] = 'Max:' . $parameters;
                        break;
                    case 'in':
                        $formattedParts[] = 'In:' . $parameters;
                        break;
                    case 'email':
                        $formattedParts[] = 'Email';
                        break;
                    case 'numeric':
                        $formattedParts[] = 'Numeric';
                        break;
                    case 'alpha':
                        $formattedParts[] = 'Alpha';
                        break;
                    case 'alphanum':
                        $formattedParts[] = 'AlphaNum';
                        break;
                    case 'phone':
                        $formattedParts[] = 'Phone';
                        break;
                    case 'date':
                        $formattedParts[] = 'Date';
                        break;
                    case 'file':
                        $formattedParts[] = 'File';
                        break;
                    case 'fileoptional':
                        $formattedParts[] = 'FileOptional';
                        break;
                    default:
                        $formattedParts[] = ucfirst($ruleName) . ':' . $parameters;
                }
            } else {
                // Handle rules without parameters
                switch (strtolower($part)) {
                    case 'required':
                        $formattedParts[] = 'Required';
                        break;
                    case 'email':
                        $formattedParts[] = 'Email';
                        break;
                    case 'numeric':
                        $formattedParts[] = 'Numeric';
                        break;
                    case 'alpha':
                        $formattedParts[] = 'Alpha';
                        break;
                    case 'alphanum':
                        $formattedParts[] = 'AlphaNum';
                        break;
                    case 'phone':
                        $formattedParts[] = 'Phone';
                        break;
                    case 'date':
                        $formattedParts[] = 'Date';
                        break;
                    case 'file':
                        $formattedParts[] = 'File';
                        break;
                    case 'fileoptional':
                        $formattedParts[] = 'FileOptional';
                        break;
                    default:
                        $formattedParts[] = ucfirst($part);
                }
            }
        }
        
        return implode('|', $formattedParts);
    }
    
    /**
     * Generate NexaForm validation PHP code
     */
    public static function generateValidation($formName = 'form', $isAjax = false, $successMessage = null, $errorMessage = null, $hasCallback = false) {
        if (empty(self::$validationRules)) {
            return '// No validation rules defined';
        }
        
        $success = $successMessage ?? 'Form berhasil disubmit!';
        $error = $errorMessage ?? 'Terjadi kesalahan dalam pengisian form.';
        
        // Convert to proper format (capitalize first letter, use | separator)
        $validationArray = '';
        foreach (self::$validationRules as $field => $rule) {
            $formattedRule = self::formatValidationRule($rule);
            $validationArray .= "                 '$field' => '$formattedRule',\n";
        }
        
        $ajaxSetting = $isAjax ? "\n           ->setAjax(true)" : '';
        $callbackSetting = $hasCallback ? "\n           ->setCallback(function(\$data) {\n             // Simpan data\n           })" : '';
        
        // Build the method chain in correct order
        $methodChain = "\$" . $formName . " = \$this->createForm()";
        
        if ($isAjax) {
            $methodChain .= "\n           ->setAjax(true)";
        }
        
        if ($hasCallback) {
            $methodChain .= "\n           ->setCallback(function(\$data) {\n             // Simpan data\n           })";
        }
        
        $methodChain .= "\n           ->fields([
$validationArray            ])
            ->setSuccess('$success')
            ->setError('$error');";
        
        return "// NexaForm Validation Code - Copy this to your controller
$methodChain

         \$result = \$" . $formName . "->process();";
    }
    
    /**
     * Generate file upload configuration PHP code
     */
    public static function generateUploadConfig($path = 'uploads/', $allowedTypes = ['jpg', 'png'], $maxSize = 2048) {
        return "// File Upload Configuration - Add this to your NexaForm
->setUpload([
    'path' => '$path',
    'allowed_types' => ['" . implode("', '", $allowedTypes) . "'],
    'max_size' => $maxSize
])";
    }
    
    /**
     * Generate fields array for validation
     */
    private static function generateFieldsArray() {
        // Use corrected validation rules
        $correctedRules = self::validateAndCorrectRules();
        
        $validationArray = '';
        foreach ($correctedRules as $field => $rule) {
            $validationArray .= "            '$field' => '$rule',\n";
        }
        return $validationArray;
    }
    
    /**
     * Validate and correct validation rules to match NexaValidation standards
     */
    private static function validateAndCorrectRules() {
        $correctedRules = [];
        
        foreach (self::$validationRules as $field => $rule) {
            $correctedRule = self::correctValidationRule($rule);
            $correctedRules[$field] = $correctedRule;
        }
        
        return $correctedRules;
    }
    
    /**
     * Correct individual validation rule to match NexaValidation standards
     */
    private static function correctValidationRule($rule) {
        // Rules yang valid di NexaValidation trait
        $validRules = [
            'required', 'min', 'max', 'email', 'numeric', 'alpha', 'alphaNum', 
            'phone', 'date', 'json', 'in', 'file', 'fileOptional',
            // Old format rules
            'name', 'title', 'password', 'passwordConfirm', 'address', 'select', 'textarea'
        ];
        
        // Split rule by |
        $parts = explode('|', $rule);
        $correctedParts = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Handle rules with parameters (like min:3, max:50, in:option1,option2)
            if (strpos($part, ':') !== false) {
                list($ruleName, $parameters) = explode(':', $part, 2);
                $ruleName = trim($ruleName);
                
                // Correct common mistakes
                switch (strtolower($ruleName)) {
                    case 'alphanum':
                        $correctedParts[] = 'alphaNum:' . $parameters;
                        break;
                    case 'fileoptional':
                        $correctedParts[] = 'fileOptional:' . $parameters;
                        break;
                    case 'passwordconfirm':
                        $correctedParts[] = 'passwordConfirm:' . $parameters;
                        break;
                    default:
                        $correctedParts[] = $part;
                }
            } else {
                // Handle rules without parameters
                switch (strtolower($part)) {
                    case 'alphanum':
                        $correctedParts[] = 'alphaNum';
                        break;
                    case 'fileoptional':
                        $correctedParts[] = 'fileOptional';
                        break;
                    case 'passwordconfirm':
                        $correctedParts[] = 'passwordConfirm';
                        break;
                    default:
                        $correctedParts[] = $part;
                }
            }
        }
        
        return implode('|', $correctedParts);
    }
    
    /**
     * Generate complete controller method PHP code
     */
    public static function generateController($methodName = 'handleForm', $isAjax = false, $uploadConfig = null) {
        $validationCode = self::generateValidation('form', $isAjax);
        $uploadCode = $uploadConfig ? self::generateUploadConfig($uploadConfig['path'] ?? 'uploads/', $uploadConfig['allowed_types'] ?? ['jpg', 'png'], $uploadConfig['max_size'] ?? 2048) : '';
        
        return "// Complete Controller Method - Copy this to your controller class
// Add this at the top of your controller file:
use App\System\Helpers\NexaForm;

public function $methodName() {
    \$form = NexaForm::createForm()
        ->fields([
" . self::generateFieldsArray() . "        ])
        ->setSuccess('" . ($isAjax ? 'Form berhasil disubmit!' : 'Form berhasil disubmit!') . "')
        ->setError('Terjadi kesalahan dalam pengisian form.')" . ($isAjax ? '->setAjax(true)' : '') . ($uploadCode ? "\n        " . $uploadCode : '') . ";

    // Process form
    \$result = \$form->process();

    // Handle result
    if (\$result['success']) {
        // Success - data tersedia di \$result['data']
        \$data = \$result['data'];
        
        // Lakukan sesuatu dengan data...
        // Example: save to database, send email, etc.
        
    } else {
        // Error - tampilkan error ke template
        \$errors = \$result['errors'];
    }

    // Untuk template variables (jika tidak menggunakan AJAX)
    \$templateVars = \$form->Response();
    
    return \$result;
}";
    }
    
    /**
     * Reset validation rules (untuk form baru)
     */
    public static function reset() {
        self::$validationRules = [];
    }
    
    /**
     * Get current validation rules
     */
    public static function getValidationRules() {
        return self::$validationRules;
    }
}


