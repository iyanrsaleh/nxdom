<?php
declare(strict_types=1);
namespace App\System\Helpers;
class NexaDecode {
    private string $secretKey;
    private string $encryptionMethod = 'AES-256-CBC';
    private bool $developmentMode = true; // Set to false for production

    public function __construct(string $secretKey = null) {
        if ($secretKey === null) {
            $secretKey = bin2hex(random_bytes(32));
        }
        // Remove any whitespace and ensure consistent key format
        $secretKey = trim($secretKey);
        // Create a consistent 32-byte key
        $this->secretKey = hash('sha256', $secretKey, true);
    }

    /**
     * Get the current secret key
     */
    public function getSecretKey(): string {
        return bin2hex($this->secretKey);
    }

    /**
     * Set development mode (static IV for consistent tokens)
     */
    public function setDevelopmentMode(bool $enabled): void {
        $this->developmentMode = $enabled;
    }

    /**
     * Check if development mode is enabled
     */
    public function isDevelopmentMode(): bool {
        return $this->developmentMode;
    }

    /**
     * Convert standard base64 to safe string (URL-safe base64)
     */
    private function safeEncode(string $data): string {
        $base64 = base64_encode($data);
        // Use URL-safe base64 encoding for better results
        return strtr($base64, array(
            '+' => '-',
            '/' => '_',
            '=' => ''  // Remove padding for cleaner URLs
        ));
    }

    /**
     * Convert safe string back to standard base64
     */
    private function safeDecode(string $data): string {
        // Convert back from URL-safe base64 to standard base64
        $base64 = strtr($data, array(
            '-' => '+',
            '_' => '/'
        ));
        // Add padding if needed
        $padLength = 4 - (strlen($base64) % 4);
        if ($padLength !== 4) {
            $base64 .= str_repeat('=', $padLength);
        }
        return base64_decode($base64);
    }

    /**
     * Encode array data with encryption
     * @param array $data Data to be encoded
     * @return string Encrypted data
     */
    public function encode(array $data): string {
        try {
            // Convert array to JSON
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new \Exception('Failed to encode JSON');
            }

            // Generate a 16 byte IV
            $ivLength = 16;
            
            if ($this->developmentMode) {
                // Use predictable but non-zero IV for development - more readable tokens
                $iv = hash('sha256', 'development_iv_' . json_encode($data), true);
                $iv = substr($iv, 0, $ivLength); // Take first 16 bytes
            } else {
                // Use random IV for production - secure but different tokens
                $iv = \openssl_random_pseudo_bytes($ivLength);
            }
            
            // Encrypt
            $encrypted = \openssl_encrypt(
                $jsonData,
                $this->encryptionMethod,
                $this->secretKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                throw new \Exception('Encryption failed');
            }

            // Combine IV and encrypted data and encode to safe string
            $combined = $iv . $encrypted;
            return $this->safeEncode($combined);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Decode encrypted data back to array
     * @param string $encodedData Encrypted data
     * @return array|null Decoded data or null if invalid
     */
    public function decode(string $encodedData): ?array {
        try {
            // Validate input
            if (empty($encodedData)) {
                return null;
            }

            // Decode from safe string
            $decoded = $this->safeDecode($encodedData);
            if ($decoded === false || strlen($decoded) < 16) {
                return null;
            }

            // Extract IV and encrypted data
            $ivLength = 16;
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);

            if (empty($encrypted)) {
                return null;
            }

            // Decrypt
            $decrypted = \openssl_decrypt(
                $encrypted,
                $this->encryptionMethod,
                $this->secretKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                return null;
            }

            // Convert back to array
            $decodedData = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return is_array($decodedData) ? $decodedData : null;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify if the decoded data matches expected structure
     * @param array $data Data to verify
     * @return bool True if valid structure
     */
    public function verifyDataStructure(?array $data): bool {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                return false;
            }
        }
        return true;
    }
}

