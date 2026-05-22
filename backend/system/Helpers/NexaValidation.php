<?php
namespace App\System\Helpers;

trait NexaValidation {
    private $errors = [];
    private $validData = [];
    private $postData = [];
    private $tempData = [];


    /**
     * Get all validation errors
     * @return array Array of errors in format ['errors_fieldname' => 'Error message']
     * 
     * Example return:
     * [
     *   'errors_nama' => 'Nama minimal 12 karakter',
     *   'errors_email' =>'Format email tidak valid'
     * ]
     */
    protected function getErrors() {
        return $this->errors;
    }

    /**
     * Get valid data after validation
     * @return array
     */
    protected function getValidData() {
        return $this->validData;
    }

    /**
     * Get sanitized POST data
     * @param string|null $key Specific POST key to get
     * @return mixed Single value if key provided, array of all POST data if no key
     * 
     * Example usage:
     * $nama = $this->getPostValue('nama'); // Get single value
     * $allData = $this->getPostValue(); // Get array of all POST data
     */
    protected function getPostValue($key = null) {
        if ($key !== null) {
            return $this->postData[$key] ?? null;
        }
        return $this->postData;
    }

    /**
     * Sanitize and store POST data
     * @param array $data POST data to sanitize
     * @return array Sanitized data
     */
    protected function sanitizePostData($data = null) {
        $data = $data ?? $_POST;
        $this->postData = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->postData[$key] = $this->sanitizePostData($value);
            } else {
                $this->postData[$key] = $this->sanitizeInput($value);
            }
        }
        
        return $this->postData;
    }

    /**
     * Sanitize a single input value
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeInput($value) {
        if (is_null($value)) {
            return '';
        }
        
        if (is_string($value)) {
            // Remove whitespace
            $value = trim($value);
            // Convert special characters to HTML entities
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }

    /**
     * Parse validation rules - supports both array and string format
     * @param mixed $rules Rules in array or string format
     * @return array Parsed rules [function, min_length, message]
     */
    private function parseValidationRules($rules) {
        // Handle array format (old system)
        if (is_array($rules)) {
            return [
                'type' => strtolower($rules[0]),
                'min_length' => isset($rules[1]) ? (int)$rules[1] : null,
                'message' => isset($rules[2]) ? $rules[2] : null,
                'format' => 'old'
            ];
        }
        
        // Handle string format
        if (is_string($rules)) {
            // Check if using new format (contains multiple rules separated by |)
            if (strpos($rules, '|') !== false) {
                $rulesArray = explode('|', $rules);
                
                // Check if this is new format (multiple validation rules) or old format (type|min_length|message)
                $isNewFormat = false;
                $customMessage = null;
                
                // If last part is not a validation rule, treat it as custom message
                $lastPart = end($rulesArray);
                $validationRules = ['required', 'min', 'max', 'numeric', 'alpha', 'alphanum', 'email', 'phone', 'date', 'json', 'in', 'file', 'fileoptional'];
                
                // Add old format validation types
                $oldFormatRules = ['name', 'title', 'password', 'oldpassword', 'newpassword', 'passwordconfirm', 'address', 'select', 'textarea'];
                $allValidationRules = array_merge($validationRules, $oldFormatRules);
                
                $isLastPartRule = false;
                foreach ($allValidationRules as $validRule) {
                    if (strpos(strtolower($lastPart), $validRule) === 0) {
                        $isLastPartRule = true;
                        break;
                    }
                }
                
                // Check if first part is an old format validation rule
                $firstPart = $rulesArray[0];
                $isFirstPartOldRule = false;
                foreach ($oldFormatRules as $oldRule) {
                    if (strtolower(trim($firstPart)) === $oldRule) {
                        $isFirstPartOldRule = true;
                        break;
                    }
                }
                
                // If first part is old format rule and we have 2-3 parts, treat as old format
                if ($isFirstPartOldRule && count($rulesArray) >= 2 && count($rulesArray) <= 3) {
                    return [
                        'type' => strtolower(trim($rulesArray[0])),
                        'min_length' => isset($rulesArray[1]) && is_numeric($rulesArray[1]) ? (int)$rulesArray[1] : null,
                        'message' => isset($rulesArray[2]) ? $rulesArray[2] : null,
                        'format' => 'old'
                    ];
                }
                
                // Handle single old format rule without parameters (like 'Select')
                if ($isFirstPartOldRule && count($rulesArray) === 1) {
                    return [
                        'type' => strtolower(trim($rulesArray[0])),
                        'min_length' => null,
                        'message' => null,
                        'format' => 'old'
                    ];
                }
                
                if (!$isLastPartRule && count($rulesArray) > 1) {
                    $customMessage = array_pop($rulesArray); // Remove custom message from rules
                }
                
                // Parse individual rules
                $parsedRules = [];
                foreach ($rulesArray as $rule) {
                    $rule = trim($rule);
                    if (strpos($rule, ':') !== false) {
                        list($ruleName, $parameters) = explode(':', $rule, 2);
                        $parsedRules[strtolower(trim($ruleName))] = explode(',', $parameters);
                    } else {
                        $parsedRules[strtolower(trim($rule))] = [];
                    }
                }
                
                return [
                    'rules' => $parsedRules,
                    'custom_message' => $customMessage,
                    'format' => 'new'
                ];
            }
            
            // Single rule without |
            $singleRule = strtolower(trim($rules));
            $oldFormatRules = ['name', 'title', 'password', 'oldpassword', 'newpassword', 'passwordconfirm', 'address', 'select', 'textarea'];
            
            // Check if it's an old format rule
            if (in_array($singleRule, $oldFormatRules)) {
                return [
                    'type' => $singleRule,
                    'min_length' => null,
                    'message' => null,
                    'format' => 'old'
                ];
            }
            
            // Otherwise treat as new format
            return [
                'rules' => [$singleRule => []],
                'custom_message' => null,
                'format' => 'new'
            ];
        }
        
        return ['format' => 'invalid'];
    }

    /**
     * Map pipe rule name (lowercase key) to actual validate* method name.
     */
    private function pipeRuleToMethodName(string $ruleName): string {
        $key = strtolower(trim($ruleName));
        static $map = [
            'alphanum' => 'validateAlphaNum',
            'fileoptional' => 'validateFileOptional',
            'passwordconfirm' => 'validatePasswordConfirm',
            'oldpassword' => 'validateOldPassword',
            'newpassword' => 'validateNewPassword',
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }
        return 'validate' . ucfirst($key);
    }

    /**
     * Add error message
     * @param string $field
     * @param string $message
     */
    private function addError($field, $message) {
        $this->errors['errors_' . $field] = $message;
    }

    /**
     * Validate multiple fields at once
     * @param array $data Array of field validation rules
     * @return bool Returns true if all validations pass
     * 
     * Supports two formats:
     * Array format: ['ValidationFunction', min_length, 'Custom Message']
     * String format: 'ValidationFunction|min_length|Custom Message'
     */
    protected function validateAll($data) {
        $this->errors = [];
        $this->validData = [];
        $isValid = true;

        foreach ($data as $fieldName => $rules) {
            $parsedRules = $this->parseValidationRules($rules);
            
            // Debug: tampilkan parsing result
            if (method_exists($this, 'dump')) {
                $this->dump("Parsing Rules for '$fieldName':", [
                    'original' => $rules,
                    'parsed' => $parsedRules
                ]);
            }
            
            if ($parsedRules['format'] === 'invalid') {
                $this->addError($fieldName, "Invalid validation format");
                $isValid = false;
                continue;
            }

            // Handle old format validation
            if ($parsedRules['format'] === 'old') {
                $validationFunction = 'validate' . ucfirst(strtolower($parsedRules['type']));
                
                if (method_exists($this, $validationFunction)) {
                    $result = $parsedRules['min_length'] !== null ? 
                        $this->$validationFunction($fieldName, $parsedRules['min_length'], $parsedRules['message']) :
                        $this->$validationFunction($fieldName, null, $parsedRules['message']);
                    
                    // Handle both array and boolean return types
                    $validationPassed = is_array($result) ? $result[0] : $result;
                    
                    if ($validationPassed) {
                        $this->validData[$fieldName] = $this->getPostValue($fieldName);
                    } else {
                        $isValid = false;
                    }
                } else {
                    $this->addError($fieldName, "Invalid validation function: {$parsedRules['type']}");
                    $isValid = false;
                }
                continue;
            }

            // Handle new format validation
            if ($parsedRules['format'] === 'new') {
                $value = $this->getPostValue($fieldName);
                $customMessage = $parsedRules['custom_message'] ?? null;
                
                // Check if field is required
                $isRequired = isset($parsedRules['rules']['required']);
                if (!$isRequired && empty($value)) {
                    continue; // Skip validation for optional empty fields
                }
                
                // Validate against all rules
                foreach ($parsedRules['rules'] as $ruleName => $parameters) {
                    $methodName = $this->pipeRuleToMethodName($ruleName);
                    
                    if (method_exists($this, $methodName)) {
                        // For required validation, pass custom message if available
                        if ($ruleName === 'required' && $customMessage) {
                            if (!$this->$methodName($fieldName, $value, [$customMessage])) {
                                $isValid = false;
                                break;
                            }
                        } else {
                            // Untuk validasi In:option, pass custom message langsung ke method
                            if ($ruleName === 'in' && count($parameters) === 1 && strtolower($parameters[0]) === 'option' && $customMessage) {
                                $validationResult = $this->$methodName($fieldName, $value, $parameters, $customMessage);
                            } else {
                                $validationResult = $this->$methodName($fieldName, $value, $parameters);
                            }
                            
                            if (!$validationResult) {
                                // Jika validasi gagal dan ada custom message, gunakan custom message
                                if ($customMessage && !isset($this->errors['errors_' . $fieldName])) {
                                    $this->addError($fieldName, $customMessage);
                                }
                                $isValid = false;
                                break;
                            }
                        }
                    } else {
                        $this->addError($fieldName, "Invalid validation rule: {$ruleName}");
                        $isValid = false;
                        break;
                    }
                }
                
                if (!isset($this->errors['errors_' . $fieldName])) {
                    $this->validData[$fieldName] = $value;
                }
            }
        }
        
        return $isValid;
    }

    /**
     * Validate required field (supports both old and new format)
     * @param string $fieldName Field name to validate
     * @param mixed $min_length_or_value For old format: min_length, for new format: actual value
     * @param string|array|null $customMessage_or_parameters For old format: custom message, for new format: parameters array
     * @return array|bool Returns array [is_valid, message] for old format, bool for new format
     */
    private function validateRequired($fieldName, $min_length_or_value = null, $customMessage_or_parameters = null) {
        // Detect format based on parameters
        $isNewFormat = is_array($customMessage_or_parameters) || 
                       (!is_null($min_length_or_value) && !is_numeric($min_length_or_value));
        
        if ($isNewFormat) {
            // New format: validateRequired($fieldName, $value, $parameters)
            $value = $min_length_or_value;
            $parameters = is_array($customMessage_or_parameters) ? $customMessage_or_parameters : [];
            
            $isEmpty = is_null($value) || $value === '' || (is_array($value) && empty($value));
            
            if ($isEmpty) {
                $customMessage = !empty($parameters) ? $parameters[0] : "Field $fieldName wajib diisi";
                $this->addError($fieldName, $customMessage);
                return false;
            }
            return true;
        } else {
            // Old format: validateRequired($fieldName, $min_length, $customMessage)
            $value = $this->getPostValue($fieldName);
            $value = is_null($value) ? '' : trim($value);
            $is_valid = !empty($value);
            
            if ($is_valid) {
                if (isset($this->errors['errors_' . $fieldName])) {
                    unset($this->errors['errors_' . $fieldName]);
                }
                return [true, "valid"];
            }

            $this->addError($fieldName, $customMessage_or_parameters ?: "Field ini wajib diisi");
            return [false, $customMessage_or_parameters ?: "Field ini wajib diisi"];
        }
    }

    /**
     * Validate password with minimum length and complexity
     * @param string $fieldName Field name to validate
     * @param int $min_length
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validatePassword($fieldName, $min_length = 6, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi wajib diisi");
            return [false, $customMessage ?: "Kata sandi wajib diisi"];
        }

        if (strlen($value) < $min_length) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi minimal {$min_length} karakter");
            return [false, $customMessage ?: "Kata sandi minimal {$min_length} karakter"];
        }

        if (!preg_match('/[0-9]/', $value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi harus mengandung minimal satu angka");
            return [false, $customMessage ?: "Kata sandi harus mengandung minimal satu angka"];
        }

        if (!preg_match('/[a-zA-Z]/', $value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi harus mengandung minimal satu huruf");
            return [false, $customMessage ?: "Kata sandi harus mengandung minimal satu huruf"];
        }
        
        return [true, "valid"];
    }

    /**
     * Validate old password against stored password hash
     * @param string $fieldName Field name to validate
     * @param mixed $userId User ID to check password against (passed via temp data)
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateOldPassword($fieldName, $userId = null, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi lama wajib diisi");
            return [false, $customMessage ?: "Kata sandi lama wajib diisi"];
        }

        // Get user ID from temp data if not provided
        if ($userId === null) {
            $userId = $this->getTemp('current_user_id');
        }

        if (empty($userId)) {
            $this->addError($fieldName, $customMessage ?: "Session expired, silakan login ulang");
            return [false, $customMessage ?: "Session expired, silakan login ulang"];
        }

        // Get stored password hash from database
        $storedPasswordHash = $this->getTemp('stored_password_hash');
        
        if (empty($storedPasswordHash)) {
            $this->addError($fieldName, $customMessage ?: "Tidak dapat memverifikasi kata sandi");
            return [false, $customMessage ?: "Tidak dapat memverifikasi kata sandi"];
        }

        // Check for plain text password first (if you're using plain text - NOT RECOMMENDED)
        if ($value === $storedPasswordHash) {
            return [true, "valid"];
        }
        
        // Verify hashed password (RECOMMENDED)
        $isValid = password_verify($value, $storedPasswordHash);
        
        if ($isValid) {
            return [true, "valid"];
        }

        // Check if hash is valid
        $hashInfo = password_get_info($storedPasswordHash);
        if (!$hashInfo['algo']) {
            $this->addError($fieldName, "Terjadi kesalahan sistem. Silakan hubungi administrator.");
            return [false, "Terjadi kesalahan sistem. Silakan hubungi administrator."];
        }
        $this->addError($fieldName, $customMessage ?: "Kata sandi lama tidak benar");
        return [false, $customMessage ?: "Kata sandi lama tidak benar"];
    }

    /**
     * Validate new password with balanced security requirements
     * @param string $fieldName Field name to validate
     * @param int $min_length Minimum length required
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateNewPassword($fieldName, $min_length = 8, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi baru wajib diisi");
            return [false, $customMessage ?: "Kata sandi baru wajib diisi"];
        }

        // Check minimum length
        if (strlen($value) < $min_length) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi baru minimal {$min_length} karakter");
            return [false, $customMessage ?: "Kata sandi baru minimal {$min_length} karakter"];
        }

        // Check for at least one letter (either uppercase or lowercase)
        if (!preg_match('/[a-zA-Z]/', $value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi baru harus mengandung minimal satu huruf");
            return [false, $customMessage ?: "Kata sandi baru harus mengandung minimal satu huruf"];
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $value)) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi baru harus mengandung minimal satu angka");
            return [false, $customMessage ?: "Kata sandi baru harus mengandung minimal satu angka"];
        }

        // Optional: Check for mixed case (recommended but not required)
        $hasUpper = preg_match('/[A-Z]/', $value);
        $hasLower = preg_match('/[a-z]/', $value);
        $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $value);

        // Password strength validation passed - no additional requirements enforced

        // Check if new password is same as old password
        $oldPassword = $this->getPostValue('password');
        if (!empty($oldPassword) && $value === $oldPassword) {
            $this->addError($fieldName, $customMessage ?: "Kata sandi baru tidak boleh sama dengan kata sandi lama");
            return [false, $customMessage ?: "Kata sandi baru tidak boleh sama dengan kata sandi lama"];
        }

        return [true, "valid"];
    }

    /**
     * Validate password confirmation matches the new password
     * @param string $fieldName Field name to validate
     * @param int $min_length Minimum length (should match new password requirements)
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validatePasswordConfirm($fieldName, $min_length = 8, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        
        $newPassword = $this->getPostValue('passwordbaru');
        $newPassword = is_null($newPassword) ? '' : trim($newPassword);

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: "Konfirmasi kata sandi wajib diisi");
            return [false, $customMessage ?: "Konfirmasi kata sandi wajib diisi"];
        }

        if (strlen($value) < $min_length) {
            $this->addError($fieldName, $customMessage ?: "Konfirmasi kata sandi minimal {$min_length} karakter");
            return [false, $customMessage ?: "Konfirmasi kata sandi minimal {$min_length} karakter"];
        }

        // Main validation: Check if confirmation matches new password
        if ($value !== $newPassword) {
            $this->addError($fieldName, $customMessage ?: "Konfirmasi kata sandi tidak sama dengan kata sandi baru");
            return [false, $customMessage ?: "Konfirmasi kata sandi tidak sama dengan kata sandi baru"];
        }

        // If new password is empty, confirmation should also be considered invalid
        if (empty($newPassword)) {
            $this->addError($fieldName, "Silakan isi kata sandi baru terlebih dahulu");
            return [false, "Silakan isi kata sandi baru terlebih dahulu"];
        }

        return [true, "valid"];
    }

    /**
     * Validate title with minimum length requirement
     * @param string $fieldName Field name to validate
     * @param int $min_length Minimum length required
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateTitle($fieldName, $min_length, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        $len = mb_strlen($value, 'UTF-8');

        if ($len >= $min_length) {
            if (isset($this->errors['errors_' . $fieldName])) {
                unset($this->errors['errors_' . $fieldName]);
            }
            return [true, "valid"];
        }

        $this->addError($fieldName, $customMessage ?: ucfirst($fieldName)." minimal {$min_length} karakter");
        return [false, $customMessage ?: "Title minimal {$min_length} karakter"];
    }

    /**
     * Validate name with minimum length requirement
     * @param string $fieldName Field name to validate
     * @param int $min_length
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateName($fieldName, $min_length, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        $len = mb_strlen($value, 'UTF-8');
        
        if ($len >= $min_length) {
            if (isset($this->errors['errors_' . $fieldName])) {
                unset($this->errors['errors_' . $fieldName]);
            }
            return [true, "valid"];
        }

        $this->addError($fieldName, $customMessage ?: ucfirst($fieldName)." minimal {$min_length} karakter");
        return [false, $customMessage ?: "Nama minimal {$min_length} karakter"];
    }

    /**
     * Validate email format
     * @param string $fieldName Field name to validate
     * @param mixed $min_length Not used for email validation
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateEmail($fieldName, $min_length = null, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        
        $filter_result = filter_var($value, FILTER_VALIDATE_EMAIL);
        $is_valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        
        if ($is_valid) {
            if (isset($this->errors['errors_' . $fieldName])) {
                unset($this->errors['errors_' . $fieldName]);
            }
            return [true, "valid"];
        }

        $this->addError($fieldName, $customMessage ?: "Format email tidak valid");
        return [false, $customMessage ?: "Format email tidak valid"];
    }

    /**
     * Validate phone number
     * @param string $fieldName Field name to validate
     * @param mixed $min_length Not used for phone validation
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validatePhone($fieldName, $min_length = null, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : preg_replace('/[^0-9]/', '', $value);
        $is_valid = strlen($value) >= 10;
        
        if ($is_valid) {
            if (isset($this->errors['errors_' . $fieldName])) {
                unset($this->errors['errors_' . $fieldName]);
            }
            return [true, "valid"];
        }

        $this->addError($fieldName, $customMessage ?: "Nomor telepon tidak valid (minimal 10 digit)");
        return [false, $customMessage ?: "Nomor telepon tidak valid (minimal 10 digit)"];
    }

    /**
     * Validate address with minimum length
     * @param string $fieldName Field name to validate
     * @param int $min_length
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateAddress($fieldName, $min_length, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        $len = mb_strlen($value, 'UTF-8');
        
        if ($len >= $min_length) {
            if (isset($this->errors['errors_' . $fieldName])) {
                unset($this->errors['errors_' . $fieldName]);
            }
            return [true, "valid"];
        }

        $this->addError($fieldName, $customMessage ?: "Alamat minimal {$min_length} karakter");
        return [false, $customMessage ?: "Alamat minimal {$min_length} karakter"];
    }

    /**
     * Validate file upload, only checks if file is uploaded
     * @param string $fieldName Name of the file input field
     * @param int $min_length Not used, kept for consistency with other validate methods
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateFile($fieldName, $min_length = null, $customMessage = null) {
        if (empty($fieldName) || !isset($_FILES[$fieldName])) {
            $this->addError($fieldName, $customMessage ?: "File wajib diunggah");
            return [false, "File wajib diunggah"];
        }

        $file = $_FILES[$fieldName];

        if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
            $this->postData[$fieldName] = $file['name'];
            return [true, "valid"];
        }

        // Simpan nama file yang pernah dipilih untuk informasi user
        if (!empty($file['name'])) {
            $this->setTemp('previous_file_' . $fieldName, $file['name']);
        }

        $errorMessage = match($file['error']) {
            UPLOAD_ERR_NO_FILE => "File wajib diunggah",
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File terlalu besar",
            default => "Error dalam upload file"
        };

        $this->addError($fieldName, $customMessage ?: $errorMessage);
        return [false, $errorMessage];
    }

    /**
     * Validate optional file upload
     * @param string $fieldName Name of the file input field
     * @param int $min_length Not used, kept for consistency with other validate methods
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateFileOptional($fieldName, $min_length = null, $customMessage = null) {
        // If no file field exists or no file was selected, it's valid for optional
        if (empty($fieldName) || !isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return [true, "valid"];
        }

        $file = $_FILES[$fieldName];

        // If file upload was successful
        if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
            $this->postData[$fieldName] = $file['name'];
            return [true, "valid"];
        }

        // Simpan nama file yang pernah dipilih untuk informasi user
        if (!empty($file['name'])) {
            $this->setTemp('previous_file_' . $fieldName, $file['name']);
        }

        // Handle other upload errors
        $errorMessage = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File terlalu besar",
            UPLOAD_ERR_PARTIAL => "File tidak terupload dengan sempurna",
            UPLOAD_ERR_NO_TMP_DIR => "Folder temporary tidak ditemukan",
            UPLOAD_ERR_CANT_WRITE => "Gagal menulis file ke disk",
            UPLOAD_ERR_EXTENSION => "Upload dihentikan oleh ekstensi",
            default => "Error dalam upload file"
        };

        $this->addError($fieldName, $customMessage ?: $errorMessage);
        return [false, $errorMessage];
    }

    /**
     * Validate select/dropdown input
     * @param string $fieldName Field name to validate
     * @param mixed $min_length Not used for select validation
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateSelect($fieldName, $min_length = null, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        
        if (empty($value) || $value === '0' || $value === '-' || $value === 'default') {
            $this->addError($fieldName, $customMessage ?: "Silakan pilih {$fieldName}");
            return [false, $customMessage ?: "Silakan pilih {$fieldName}"];
        }

        return [true, "valid"];
    }

    /**
     * Validate textarea input with minimum length requirement
     * @param string $fieldName Field name to validate
     * @param int $min_length Minimum length required
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateTextarea($fieldName, $min_length = 1, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);
        $len = mb_strlen($value, 'UTF-8');

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: ucfirst($fieldName) . " wajib diisi");
            return [false, $customMessage ?: ucfirst($fieldName) . " wajib diisi"];
        }

        if ($len < $min_length) {
            $this->addError($fieldName, $customMessage ?: ucfirst($fieldName) . " minimal {$min_length} karakter");
            return [false, $customMessage ?: ucfirst($fieldName) . " minimal {$min_length} karakter"];
        }

        return [true, "valid"];
    }

    /**
     * Validate JSON format
     * @param string $fieldName Field name to validate
     * @param mixed $min_length Not used for JSON validation
     * @param string|null $customMessage Custom error message
     * @return array [is_valid, message]
     */
    private function validateJson($fieldName, $min_length = null, $customMessage = null) {
        $value = $this->getPostValue($fieldName);
        $value = is_null($value) ? '' : trim($value);

        if (empty($value)) {
            $this->addError($fieldName, $customMessage ?: "Data JSON wajib diisi");
            return [false, $customMessage ?: "Data JSON wajib diisi"];
        }

        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($fieldName, $customMessage ?: "Format JSON tidak valid");
            return [false, $customMessage ?: "Format JSON tidak valid"];
        }

        return [true, "valid"];
    }

    /**
     * Set temporary data
     * @param string|array $key Key or array of key-value pairs
     * @param mixed $value Value (if key is string)
     * @return void
     */
    protected function setTemp($key, $value = null) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->tempData[$k] = $v;
            }
        } else {
            $this->tempData[$key] = $value;
        }
    }

    /**
     * Get temporary data
     * @param string|null $key Specific key to get, null for all temp data
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function getTemp($key = null, $default = null) {
        if ($key === null) {
            return $this->tempData;
        }
        return $this->tempData[$key] ?? $default;
    }

    /**
     * Check if temporary data exists
     * @param string $key Key to check
     * @return bool
     */
    protected function hasTemp($key) {
        return isset($this->tempData[$key]);
    }

    /**
     * Remove temporary data
     * @param string|null $key Key to remove, null to clear all
     * @return void
     */
    protected function removeTemp($key = null) {
        if ($key === null) {
            $this->tempData = [];
        } else {
            unset($this->tempData[$key]);
        }
    }

    /**
     * Flash temporary data - get and remove
     * @param string $key Key to flash
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function flashTemp($key, $default = null) {
        $value = $this->getTemp($key, $default);
        $this->removeTemp($key);
        return $value;
    }

    /**
     * Generate fields array from validation rules
     * @param array $validationRules Array of validation rules
     * @return array Fields array with empty values and error keys
     * 
     * Example usage:
     * $validationRules = [
     *   'nama' => 'Name|3|Nama minimal 3 karakter',
     *   'email' => 'Email|null|Format email tidak valid'
     * ];
     * $fields = $this->getFields($validationRules);
     * // Returns:
     * // [
     * //   'nama' => '',
     * //   'errors_nama' => '',
     * //   'email' => '',
     * //   'errors_email' => ''
     * // ]
     */
    protected function getFields($validationRules) {
        $fields = [];
        foreach ($validationRules as $key => $value) {
            $fields[$key] = '';
            $fields['errors_' . $key] = '';
        }
        return $fields;
    }

    /**
     * Generate template variables automatically from validation rules
     * @param array $validationRules Array of validation rules
     * @param array $additionalVars Additional variables to merge (optional)
     * @return array Template variables ready for NexaVars
     * 
     * Example usage:
     * $validationRules = [
     *   'nama' => 'Name|3|Nama minimal 3 karakter',
     *   'email' => 'Email|null|Format email tidak valid',
     *   'file' => 'File|null|File wajib diunggah'
     * ];
     * $templateVars = $this->buildTemplateVars($validationRules);
     * // Returns:
     * // [
     * //   'nama_value' => 'current_post_value_or_empty',
     * //   'email_value' => 'current_post_value_or_empty', 
     * //   'error_nama' => 'error_message_or_empty',
     * //   'error_email' => 'error_message_or_empty',
     * //   'error_file' => 'error_message_or_empty'
     * // ]
     */
    protected function buildTemplateVars($validationRules, $additionalVars = []) {
        $templateVars = [];
        $errors = $this->getErrors();
        
        foreach ($validationRules as $fieldName => $rules) {
            $parsedRules = $this->parseValidationRules($rules);
            $validationFunction = strtolower($parsedRules[0]);
            
            // Add field value (for regular input fields, not files)
            if ($validationFunction !== 'file' && $validationFunction !== 'fileoptional') {
                $templateVars[$fieldName . '_value'] = htmlspecialchars($this->getPostValue($fieldName) ?? '');
            }
            
            // Add error message
            $templateVars['error_' . $fieldName] = $errors['errors_' . $fieldName] ?? '';
        }
        
        // Handle file info for file fields
        foreach ($validationRules as $fieldName => $rules) {
            $parsedRules = $this->parseValidationRules($rules);
            $validationFunction = strtolower($parsedRules[0]);
            
            if ($validationFunction === 'file' || $validationFunction === 'fileoptional') {
                $templateVars[$fieldName . '_info'] = $this->getFileInfo($fieldName);
            }
        }
        
        // Merge with additional variables if provided
        if (!empty($additionalVars)) {
            $templateVars = array_merge($templateVars, $additionalVars);
        }
        
        return $templateVars;
    }

    /**
     * Get file upload information message
     * @param string $fieldName Field name
     * @return string Information message about file upload
     */
    protected function getFileInfo($fieldName) {
        // Cek apakah ada file yang berhasil diupload (tanpa error)
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $fileName = htmlspecialchars($_FILES[$fieldName]['name']);
            $fileSize = number_format($_FILES[$fieldName]['size'] / 1024, 2);
            return '<small style="color: green;"><em>✓ File berhasil dipilih: "' . $fileName . '" (' . $fileSize . ' KB)</em></small>';
        }
        
        // Jika ada error validasi, tampilkan informasi error
        if (!empty($this->errors)) {
            $previousFile = $this->getTemp('previous_file_' . $fieldName);
            
            if (!empty($previousFile)) {
                return '<small style="color: blue;"><em>File sebelumnya: "' . htmlspecialchars($previousFile) . '" - Pilih ulang file yang sama</em></small>';
            } else {
                return '<small style="color: orange;"><em>* File perlu dipilih ulang jika ada error validasi</em></small>';
            }
        }
        
        // Jika tidak ada file dan tidak ada error, tampilkan pesan netral
        return '';
    }

    private function validateField($fieldName, $value, $rules) {
        foreach ($rules as $ruleName => $parameters) {
            $methodName = 'validate' . ucfirst(strtolower($ruleName));
            
            if (method_exists($this, $methodName)) {
                $isValid = $this->$methodName($fieldName, $value, $parameters);
                if (!$isValid) {
                    return false;
                }
            } else {
                $this->addError($fieldName, "Invalid validation rule: {$ruleName}");
                return false;
            }
        }
        
        return true;
    }

    private function validateMin($fieldName, $value, $parameters) {
        $min = isset($parameters[0]) ? (int)$parameters[0] : 0;
        
        if (is_string($value)) {
            if (strlen($value) < $min) {
                $this->addError($fieldName, "Field $fieldName minimal harus $min karakter");
                return false;
            }
        } elseif (is_numeric($value)) {
            if ($value < $min) {
                $this->addError($fieldName, "Field $fieldName minimal harus bernilai $min");
                return false;
            }
        }
        
        return true;
    }

    private function validateMax($fieldName, $value, $parameters) {
        $max = isset($parameters[0]) ? (int)$parameters[0] : PHP_INT_MAX;
        
        if (is_string($value)) {
            if (strlen($value) > $max) {
                $this->addError($fieldName, "Field $fieldName maksimal $max karakter");
                return false;
            }
        } elseif (is_numeric($value)) {
            if ($value > $max) {
                $this->addError($fieldName, "Field $fieldName maksimal bernilai $max");
                return false;
            }
        }
        
        return true;
    }

    private function validateIn($fieldName, $value, $parameters, $customMessage = null) {
        // Jika parameter hanya satu dan berupa string 'option', skip validasi detail
        // Ini untuk kasus dimana opsi berasal dari database atau sumber dinamis lainnya
        if (count($parameters) === 1 && strtolower($parameters[0]) === 'option') {
            // Hanya validasi bahwa nilai tidak kosong dan bukan nilai default
            if (empty($value) || $value === '0' || $value === '-' || $value === 'default' || $value === '' || trim($value) === '') {
                // Gunakan custom message jika ada, atau message default
                if ($customMessage) {
                    $this->addError($fieldName, $customMessage);
                } else {
                    $this->addError($fieldName, "Silakan pilih $fieldName yang valid");
                }
                return false;
            }
            return true;
        }
        
        // Standard validation dengan parameter eksplisit
        if (!in_array($value, $parameters)) {
            $validValues = implode(', ', $parameters);
            $this->addError($fieldName, "Field $fieldName harus salah satu dari: $validValues");
            return false;
        }
        return true;
    }

    private function validateNumeric($fieldName, $value, $parameters) {
        if (!is_numeric($value)) {
            $this->addError($fieldName, "Field $fieldName harus berupa angka");
            return false;
        }
        return true;
    }

    private function validateAlpha($fieldName, $value, $parameters) {
        if (!ctype_alpha($value)) {
            $this->addError($fieldName, "Field $fieldName hanya boleh berisi huruf");
            return false;
        }
        return true;
    }

    private function validateAlphaNum($fieldName, $value, $parameters) {
        if (!ctype_alnum($value)) {
            $this->addError($fieldName, "Field $fieldName hanya boleh berisi huruf dan angka");
            return false;
        }
        return true;
    }

    /**
     * Validate date format
     * @param string $fieldName Field name to validate
     * @param mixed $value Value to validate
     * @param array $parameters Additional parameters (format, etc.)
     * @return bool
     */
    private function validateDate($fieldName, $value, $parameters = []) {
        // Check if value is empty for required validation
        if (empty($value)) {
            return true; // Let required validation handle empty values
        }

        // Default date format
        $format = $parameters[0] ?? 'Y-m-d';
        
        // Try to create DateTime object from the value
        $date = \DateTime::createFromFormat($format, $value);
        
        // Check if date is valid and matches the format exactly
        if ($date && $date->format($format) === $value) {
            return true;
        }
        
        // Try common date formats if default fails
        $commonFormats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'm/d/Y'];
        
        foreach ($commonFormats as $testFormat) {
            $date = \DateTime::createFromFormat($testFormat, $value);
            if ($date && $date->format($testFormat) === $value) {
                return true;
            }
        }
        
        $this->addError($fieldName, "Field $fieldName harus berupa tanggal yang valid");
        return false;
    }



} 