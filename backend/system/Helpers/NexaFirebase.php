<?php
namespace App\System\Helpers;

use \Exception;

/**
 * Generates an HTML block tag that follows the Bootstrap documentation
 * on how to display  component.
 *
 * See {@link https://tatiye.net/} for more information.
 */
class NexaFirebase  {
    private $databaseURL;
    private $apiKey;
    
    public function __construct($databaseURL = null, $apiKey = null) {
        $this->databaseURL = $databaseURL;
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set Firebase configuration
     */
    public function setConfig($databaseURL, $apiKey) {
        $this->databaseURL = $databaseURL;
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Get current configuration
     */
    public function getConfig() {
        return [
            'databaseURL' => $this->databaseURL,
            'apiKey' => $this->apiKey
        ];
    }

    /**
     * Validate configuration before operations
     */
    private function validateConfig() {
        if (!$this->databaseURL || !$this->apiKey) {
            return ['error' => 'Firebase configuration not set. Please use setConfig() or provide parameters in constructor.'];
        }
        return null;
    }

    /**
     * Execute cURL request with error handling
     */
    private function executeCurl($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        // SSL Options - untuk mengatasi masalah SSL certificate
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'CURL Error: ' . $error];
        }
        
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return ['error' => 'HTTP Error ' . $httpCode . ': ' . $response];
        }
        
        return ['success' => true, 'data' => json_decode($response, true)];
    }
    
    // CREATE
    public function create($path, $data) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            $url = $this->databaseURL . '/' . $path . '.json';
            
            $result = $this->executeCurl($url, 'POST', $data);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'data' => $result['data']
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // READ
    public function read($path, $filter = null) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            $url = $this->databaseURL . '/' . $path . '.json';
            if ($filter) {
                $url .= '?orderBy="' . $filter['field'] . '"&equalTo="' . $filter['value'] . '"';
            }
            
            $result = $this->executeCurl($url);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'data' => $result['data']
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Advanced READ with flexible filtering options
     * 
     * @param string $path Firebase path
     * @param array $options Filtering options
     * @return array
     */
    public function readAdvanced($path, $options = []) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            $url = $this->databaseURL . '/' . $path . '.json';
            $queryParams = [];
            
            // Order by field
            if (isset($options['orderBy'])) {
                $queryParams[] = 'orderBy="' . $options['orderBy'] . '"';
            }
            
            // Equal to value
            if (isset($options['equalTo'])) {
                $queryParams[] = 'equalTo="' . $options['equalTo'] . '"';
            }
            
            // Start at value
            if (isset($options['startAt'])) {
                $queryParams[] = 'startAt="' . $options['startAt'] . '"';
            }
            
            // End at value
            if (isset($options['endAt'])) {
                $queryParams[] = 'endAt="' . $options['endAt'] . '"';
            }
            
            // Limit to first N results
            if (isset($options['limitToFirst'])) {
                $queryParams[] = 'limitToFirst=' . (int)$options['limitToFirst'];
            }
            
            // Limit to last N results
            if (isset($options['limitToLast'])) {
                $queryParams[] = 'limitToLast=' . (int)$options['limitToLast'];
            }
            
            // Print option for pretty formatting
            if (isset($options['print']) && $options['print'] === 'pretty') {
                $queryParams[] = 'print=pretty';
            }
            
            // Shallow option for getting keys only
            if (isset($options['shallow']) && $options['shallow'] === true) {
                $queryParams[] = 'shallow=true';
            }
            
            // Add query parameters to URL
            if (!empty($queryParams)) {
                $url .= '?' . implode('&', $queryParams);
            }
            
            $result = $this->executeCurl($url);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'data' => $result['data'],
                'query' => $url // For debugging purposes
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // UPDATE
    public function update($path, $key, $data) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            $url = $this->databaseURL . '/' . $path . '/' . $key . '.json';
            
            $result = $this->executeCurl($url, 'PATCH', $data);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'data' => $result['data']
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // DELETE
    public function delete($path, $key) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            $url = $this->databaseURL . '/' . $path . '/' . $key . '.json';
            
            $result = $this->executeCurl($url, 'DELETE');
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'message' => 'Data berhasil dihapus'
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function createKey($path, $data, $customKey) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            // Validasi input
            if (empty($path) || empty($data) || empty($customKey)) {
                return ['error' => 'Path, data, dan custom key tidak boleh kosong'];
            }

            // Validasi format custom key
            // if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customKey)) {
            //     return ['error' => 'Custom key hanya boleh mengandung huruf, angka, underscore, dan dash'];
            // }

            // Buat URL dengan custom key
            $url = $this->databaseURL . '/' . $path . '/' . $customKey . '.json';
            
            $result = $this->executeCurl($url, 'PUT', $data);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'key' => $customKey,
                'data' => $result['data']
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ========== BATCH OPERATIONS ==========

    /**
     * Batch CREATE - Create multiple records at once
     * 
     * @param string $path Firebase path
     * @param array $dataArray Array of data to create
     * @return array
     */
    public function batchCreate($path, $dataArray) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            if (!is_array($dataArray) || empty($dataArray)) {
                return ['error' => 'Data array tidak boleh kosong'];
            }
            
            $results = [];
            $errors = [];
            
            foreach ($dataArray as $index => $data) {
                $result = $this->create($path, $data);
                if (isset($result['error'])) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $data,
                        'error' => $result['error']
                    ];
                } else {
                    $results[] = [
                        'index' => $index,
                        'key' => $result['data']['name'] ?? null,
                        'data' => $result['data']
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'total' => count($dataArray),
                'success_count' => count($results),
                'error_count' => count($errors),
                'results' => $results,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Batch UPDATE - Update multiple records at once
     * 
     * @param string $path Firebase path
     * @param array $updates Array of updates [key => data]
     * @return array
     */
    public function batchUpdate($path, $updates) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            if (!is_array($updates) || empty($updates)) {
                return ['error' => 'Updates array tidak boleh kosong'];
            }
            
            $results = [];
            $errors = [];
            
            foreach ($updates as $key => $data) {
                $result = $this->update($path, $key, $data);
                if (isset($result['error'])) {
                    $errors[] = [
                        'key' => $key,
                        'data' => $data,
                        'error' => $result['error']
                    ];
                } else {
                    $results[] = [
                        'key' => $key,
                        'data' => $result['data']
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'total' => count($updates),
                'success_count' => count($results),
                'error_count' => count($errors),
                'results' => $results,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Batch DELETE - Delete multiple records at once
     * 
     * @param string $path Firebase path
     * @param array $keys Array of keys to delete
     * @return array
     */
    public function batchDelete($path, $keys) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            if (!is_array($keys) || empty($keys)) {
                return ['error' => 'Keys array tidak boleh kosong'];
            }
            
            $results = [];
            $errors = [];
            
            foreach ($keys as $key) {
                $result = $this->delete($path, $key);
                if (isset($result['error'])) {
                    $errors[] = [
                        'key' => $key,
                        'error' => $result['error']
                    ];
                } else {
                    $results[] = [
                        'key' => $key,
                        'message' => $result['message']
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'total' => count($keys),
                'success_count' => count($results),
                'error_count' => count($errors),
                'results' => $results,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Batch CREATE with custom keys
     * 
     * @param string $path Firebase path
     * @param array $dataWithKeys Array of [key => data]
     * @return array
     */
    public function batchCreateWithKeys($path, $dataWithKeys) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            if (!is_array($dataWithKeys) || empty($dataWithKeys)) {
                return ['error' => 'Data with keys array tidak boleh kosong'];
            }
            
            $results = [];
            $errors = [];
            
            foreach ($dataWithKeys as $key => $data) {
                $result = $this->createKey($path, $data, $key);
                if (isset($result['error'])) {
                    $errors[] = [
                        'key' => $key,
                        'data' => $data,
                        'error' => $result['error']
                    ];
                } else {
                    $results[] = [
                        'key' => $result['key'],
                        'data' => $result['data']
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'total' => count($dataWithKeys),
                'success_count' => count($results),
                'error_count' => count($errors),
                'results' => $results,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Multi-path update - Update multiple paths in single operation
     * 
     * @param array $updates Array of [path => data]
     * @return array
     */
    public function multiPathUpdate($updates) {
        try {
            $configError = $this->validateConfig();
            if ($configError) return $configError;
            
            if (!is_array($updates) || empty($updates)) {
                return ['error' => 'Updates array tidak boleh kosong'];
            }
            
            $url = $this->databaseURL . '.json';
            
            $result = $this->executeCurl($url, 'PATCH', $updates);
            if (isset($result['error'])) {
                return $result;
            }
            
            return [
                'status' => 'success',
                'data' => $result['data'],
                'paths_updated' => array_keys($updates)
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}