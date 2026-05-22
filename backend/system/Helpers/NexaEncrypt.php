<?php

namespace App\System\Helpers;

use Exception;

/**
 * NexaEncrypt - Simple PHP JSON Obfuscation Class
 * Compatible with JavaScript NexaEncrypt class
 * Uses simple Base64 encoding with optional basic XOR encryption
 */
class NexaEncrypt 
{
    private $secretKey;

    /**
     * Constructor
     * @param string $secretKey Secret key for encryption/decryption
     */
    public function __construct($secretKey = 'nexa-default-secret-key-2025')
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Simple XOR encryption with fixed key (compatible with JS version)
     * @param string $text Text to encrypt
     * @param string $key Encryption key
     * @return string Encrypted text
     */
    private function simpleXOR($text, $key)
    {
        $result = '';
        $keyLength = strlen($key);
        $textLength = strlen($text);
        
        for ($i = 0; $i < $textLength; $i++) {
            $result .= chr(ord($text[$i]) ^ ord($key[$i % $keyLength]));
        }
        
        return $result;
    }

    /**
     * Encode JSON data to Base64 (simple obfuscation)
     * @param mixed $data Data to encode
     * @return string Base64 encoded data
     * @throws Exception
     */
    public function encodeJson($data)
    {
        try {
            // Convert data to JSON string
            $jsonString = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            
            if ($jsonString === false) {
                throw new Exception('Failed to encode data as JSON');
            }

            // Simple Base64 encoding
            return base64_encode($jsonString);
        } catch (Exception $e) {
            throw new Exception('Encoding failed: ' . $e->getMessage());
        }
    }

    /**
     * Decode Base64 JSON data
     * @param string $encodedData Base64 encoded data
     * @return mixed Decoded JSON data
     * @throws Exception
     */
    public function decodeJson($encodedData)
    {
        try {
            // Decode base64
            $jsonString = base64_decode($encodedData, true);
            
            if ($jsonString === false) {
                throw new Exception('Invalid base64 data');
            }

            // Try to parse as JSON, return as string if parsing fails
            $jsonData = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $jsonData;
            } else {
                return $jsonString;
            }
        } catch (Exception $e) {
            throw new Exception('Decoding failed: ' . $e->getMessage());
        }
    }

    /**
     * Obfuscate JSON data with simple XOR + Base64 (compatible with JS)
     * @param mixed $data Data to obfuscate
     * @return string Obfuscated data
     * @throws Exception
     */
    public function obfuscateJson($data)
    {
        try {
            // Convert data to JSON string
            $jsonString = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            
            if ($jsonString === false) {
                throw new Exception('Failed to encode data as JSON');
            }

            // Apply simple XOR
            $xorEncrypted = $this->simpleXOR($jsonString, $this->secretKey);

            // Encode to Base64
            return base64_encode($xorEncrypted);
        } catch (Exception $e) {
            throw new Exception('Obfuscation failed: ' . $e->getMessage());
        }
    }

    /**
     * Deobfuscate JSON data (compatible with JS)
     * @param string $obfuscatedData Obfuscated data
     * @return mixed Deobfuscated JSON data
     * @throws Exception
     */
    public function deobfuscateJson($obfuscatedData)
    {
        try {
            // Decode from Base64
            $xorEncrypted = base64_decode($obfuscatedData, true);
            
            if ($xorEncrypted === false) {
                throw new Exception('Invalid base64 data');
            }

            // Apply XOR to decrypt
            $jsonString = $this->simpleXOR($xorEncrypted, $this->secretKey);

            // Try to parse as JSON, return as string if parsing fails
            $jsonData = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $jsonData;
            } else {
                return $jsonString;
            }
        } catch (Exception $e) {
            throw new Exception('Deobfuscation failed: ' . $e->getMessage());
        }
    }

    /**
     * Legacy methods for backward compatibility
     */

    /**
     * Encrypt JSON data (simple obfuscation)
     * @param mixed $data Data to encrypt
     * @return string Obfuscated data
     * @throws Exception
     */
    public function encryptJson($data)
    {
        return $this->obfuscateJson($data);
    }

    /**
     * Decrypt JSON data (simple deobfuscation)
     * @param string $encryptedData Obfuscated data
     * @return mixed Deobfuscated data
     * @throws Exception
     */
    public function decryptJson($encryptedData)
    {
        return $this->deobfuscateJson($encryptedData);
    }

    /**
     * Encrypt simple string data
     * @param string $data String data to encrypt
     * @return string Obfuscated data
     * @throws Exception
     */
    public function encrypt($data)
    {
        return $this->obfuscateJson($data);
    }

    /**
     * Decrypt simple string data
     * @param string $encryptedData Obfuscated data
     * @return mixed Deobfuscated data
     * @throws Exception
     */
    public function decrypt($encryptedData)
    {
        return $this->deobfuscateJson($encryptedData);
    }

    /**
     * Set new secret key
     * @param string $newKey New secret key
     */
    public function setSecretKey($newKey)
    {
        $this->secretKey = $newKey;
    }

    /**
     * Get current secret key
     * @return string Current secret key
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Get encryption status information
     * @return array Status information
     */
    public function getEncryptionStatus()
    {
        return [
            'encryptionLevel' => 'simple',
            'method' => 'XOR + Base64',
            'compatible' => 'JavaScript NexaEncrypt',
            'recommendation' => 'Simple obfuscation - not for sensitive data',
        ];
    }

    /**
     * Quick encrypt helper method
     * @param mixed $data Data to encrypt
     * @param string $key Optional key (uses default if not provided)
     * @return string Encrypted data
     */
    public static function quickEncrypt($data, $key = null)
    {
        $instance = new self($key ?: 'nexa-default-secret-key-2025');
        return $instance->encryptJson($data);
    }

    /**
     * Quick decrypt helper method
     * @param string $encryptedData Encrypted data
     * @param string $key Optional key (uses default if not provided)
     * @return mixed Decrypted data
     */
    public static function quickDecrypt($encryptedData, $key = null)
    {
        $instance = new self($key ?: 'nexa-default-secret-key-2025');
        return $instance->decryptJson($encryptedData);
    }
}

// Usage Examples:
/*
// Initialize
$nexaEncrypt = new NexaEncrypt('your-simple-key');

// Check encryption status
var_dump($nexaEncrypt->getEncryptionStatus());

// Simple Base64 encoding (no encryption)
$data = ['name' => 'John', 'age' => 30, 'city' => 'Jakarta'];
$encoded = $nexaEncrypt->encodeJson($data);
echo "Encoded: " . $encoded . "\n";

$decoded = $nexaEncrypt->decodeJson($encoded);
var_dump($decoded);

// Simple obfuscation (XOR + Base64)
$obfuscated = $nexaEncrypt->obfuscateJson($data);
echo "Obfuscated: " . $obfuscated . "\n";

$deobfuscated = $nexaEncrypt->deobfuscateJson($obfuscated);
var_dump($deobfuscated);

// Using legacy methods
$encrypted = $nexaEncrypt->encryptJson($data);
echo "Encrypted: " . $encrypted . "\n";

$decrypted = $nexaEncrypt->decryptJson($encrypted);
var_dump($decrypted);

// Quick methods
$quickEncrypted = NexaEncrypt::quickEncrypt(['quick' => 'test']);
$quickDecrypted = NexaEncrypt::quickDecrypt($quickEncrypted);
var_dump($quickDecrypted);
*/ 