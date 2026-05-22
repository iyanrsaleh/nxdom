<?php
namespace App\System\Helpers;

class NexaCrypto
{
    /**
     * Default encryption method
     */
    private static $encryptionMethod = 'AES-256-CBC';
    
    /**
     * Encode data to base64 format compatible with JavaScript btoa()
     * 
     * @param mixed $data Data to encode (will be converted to JSON)
     * @return string Base64 encoded string
     */
    public static function encode($data)
    {
        // Convert data to JSON string
        $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // Encode to base64 (compatible with JavaScript btoa)
        return base64_encode($jsonString);
    }
    
    /**
     * Decode base64 string to original data (compatible with JavaScript atob)
     * 
     * @param string $encodedData Base64 encoded string
     * @param bool $assoc Return associative array instead of object (default: true)
     * @return mixed Decoded data
     */
    public static function decode($encodedData, $assoc = true)
    {
        // Decode from base64
        $jsonString = base64_decode($encodedData);
        
        // Convert JSON string back to data
        return json_decode($jsonString, $assoc);
    }
    
    /**
     * Encode data with encryption key
     * 
     * @param mixed $data Data to encode
     * @param string $key Encryption key
     * @return string Base64 encoded encrypted string
     */
    public static function encodeWithKey($data, $key)
    {
        // Convert data to JSON string
        $jsonString = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // Generate IV
        $iv = \openssl_random_pseudo_bytes(\openssl_cipher_iv_length(self::$encryptionMethod));
        
        // Encrypt the data
        $encrypted = \openssl_encrypt($jsonString, self::$encryptionMethod, $key, 0, $iv);
        
        // Combine IV and encrypted data
        $encryptedData = base64_encode($iv . $encrypted);
        
        return $encryptedData;
    }
    
    /**
     * Decode encrypted data with key
     * 
     * @param string $encryptedData Base64 encoded encrypted string
     * @param string $key Decryption key
     * @param bool $assoc Return associative array instead of object (default: true)
     * @return mixed Decoded data or false on failure
     */
    public static function decodeWithKey($encryptedData, $key, $assoc = true)
    {
        try {
            // Decode base64
            $data = base64_decode($encryptedData);
            
            // Get IV length
            $ivLength = \openssl_cipher_iv_length(self::$encryptionMethod);
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            // Decrypt the data
            $decrypted = \openssl_decrypt($encrypted, self::$encryptionMethod, $key, 0, $iv);
            
            if ($decrypted === false) {
                return false;
            }
            
            // Convert JSON string back to data
            return json_decode($decrypted, $assoc);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate a secure key
     * 
     * @param int $length Key length (default: 32)
     * @return string Generated key
     */
    public static function generateKey($length = 32)
    {
        return bin2hex(\openssl_random_pseudo_bytes($length / 2));
    }
    
    /**
     * Create hash from string for use as key
     * 
     * @param string $string String to hash
     * @param string $algorithm Hash algorithm (default: sha256)
     * @return string Hashed key
     */
    public static function createKey($string, $algorithm = 'sha256')
    {
        return hash($algorithm, $string);
    }
    
    /**
     * Encode JSON string directly to base64
     * 
     * @param string $jsonString JSON string to encode
     * @return string Base64 encoded string
     */
    public static function encodeJson($jsonString)
    {
        return base64_encode($jsonString);
    }
    
    /**
     * Decode base64 to JSON string
     * 
     * @param string $encodedData Base64 encoded string
     * @return string JSON string
     */
    public static function decodeToJson($encodedData)
    {
        return base64_decode($encodedData);
    }
    
    /**
     * Check if a string is valid base64
     * 
     * @param string $data String to check
     * @return bool True if valid base64
     */
    public static function isValidBase64($data)
    {
        return base64_encode(base64_decode($data, true)) === $data;
    }
    
    /**
     * Set encryption method
     * 
     * @param string $method Encryption method
     */
    public static function setEncryptionMethod($method)
    {
        self::$encryptionMethod = $method;
    }
    
    /**
     * Get available encryption methods
     * 
     * @return array Available methods
     */
    public static function getAvailableMethods()
    {
        return \openssl_get_cipher_methods();
    }
}