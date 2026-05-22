<?php
declare(strict_types=1);

namespace App\System\Helpers;

/**
 * NexaArray - Advanced Array Manipulation Helper
 * 
 * IMPORTANT: All filter methods now use array_values() to re-index arrays
 * This ensures consistent output structure: [0, 1, 2, ...] instead of {1: {}, 2: {}}
 * 
 * This fixes the issue where filtered arrays would maintain original keys,
 * causing inconsistent JSON output between filtered and non-filtered arrays.
 */
class NexaArray
{
    /**
     * Data array yang akan diproses
     * @var array
     */
    private array $data = [];

    /**
     * Constructor untuk inisialisasi data
     * 
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Set data array
     * 
     * @param array $data
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get data array
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Instance method: Filter berdasarkan kondisi tertentu
     * 
     * @param callable $callback
     * @return self
     */
    public function filter(callable $callback): self
    {
        $this->data = self::filterStatic($this->data, $callback);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan key
     * 
     * @param array $keys
     * @return self
     */
    public function filterByKeys(array $keys): self
    {
        $this->data = self::filterByKeysStatic($this->data, $keys);
        return $this;
    }

    /**
     * Instance method: Filter key tertentu (alias untuk filterByKeys)
     * Otomatis mendeteksi apakah single array atau array of arrays
     * 
     * @param array $keys
     * @return self
     */
    public function filterKey(array $keys): self
    {
        $this->data = self::filterKeyStatic($this->data, $keys);
        return $this;
    }

    /**
     * Instance method: Filter key tertentu dari setiap elemen dalam array
     * Berguna untuk array multidimensional
     * 
     * @param array $keys
     * @return self
     */
    public function filterKeysFromElements(array $keys): self
    {
        $this->data = self::filterKeysFromElementsStatic($this->data, $keys);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan value
     * 
     * @param mixed $value
     * @param bool $strict
     * @return self
     */
    public function filterByValue($value, bool $strict = false): self
    {
        $this->data = self::filterByValueStatic($this->data, $value, $strict);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan tipe data
     * 
     * @param string $type
     * @return self
     */
    public function filterByType(string $type): self
    {
        $this->data = self::filterByTypeStatic($this->data, $type);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan range nilai
     * 
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function filterByRange($min, $max): self
    {
        $this->data = self::filterByRangeStatic($this->data, $min, $max);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan pattern regex
     * 
     * @param string $pattern
     * @return self
     */
    public function filterByPattern(string $pattern): self
    {
        $this->data = self::filterByPatternStatic($this->data, $pattern);
        return $this;
    }

    /**
     * Instance method: Filter nilai yang tidak empty
     * 
     * @param bool $strict
     * @return self
     */
    public function filterNotEmpty(bool $strict = false): self
    {
        $this->data = self::filterNotEmptyStatic($this->data, $strict);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan property objek
     * 
     * @param string $property
     * @param mixed $value
     * @param bool $strict
     * @return self
     */
    public function filterByProperty(string $property, $value, bool $strict = false): self
    {
        $this->data = self::filterByPropertyStatic($this->data, $property, $value, $strict);
        return $this;
    }

    /**
     * Instance method: Filter berdasarkan property objek dengan konversi tipe otomatis
     * 
     * @param string $property
     * @param mixed $value
     * @return self
     */
    public function filterByPropertyFlexible(string $property, $value): self
    {
        $this->data = self::filterByPropertyFlexibleStatic($this->data, $property, $value);
        return $this;
    }

    /**
     * Instance method: Menampilkan array
     * 
     * @param bool $return
     * @return string|void|self
     */
    public function display(bool $return = false)
    {
        if ($return) {
            return self::displayStatic($this->data, true);
        }
        self::displayStatic($this->data, false);
        return $this;
    }

    /**
     * Instance method: Menampilkan JSON
     * 
     * @param bool $pretty
     * @param bool $return
     * @return string|void|self
     */
    public function displayJson(bool $pretty = true, bool $return = false)
    {
        if ($return) {
            return self::displayJsonStatic($this->data, $pretty, true);
        }
        self::displayJsonStatic($this->data, $pretty, false);
        return $this;
    }

    /**
     * Instance method: Menampilkan tabel HTML
     * 
     * @param bool $return
     * @return string|void|self
     */
    public function displayTable(bool $return = false)
    {
        if ($return) {
            return self::displayTableStatic($this->data, true);
        }
        self::displayTableStatic($this->data, false);
        return $this;
    }

    /**
     * Instance method: Menampilkan info array
     * 
     * @param bool $return
     * @return string|void|self
     */
    public function displayInfo(bool $return = false)
    {
        if ($return) {
            return self::displayInfoStatic($this->data, true);
        }
        self::displayInfoStatic($this->data, false);
        return $this;
    }

    /**
     * Instance method: Cari elemen
     * 
     * @param mixed $search
     * @param bool $strict
     * @return self
     */
    public function search($search, bool $strict = false): self
    {
        $this->data = self::searchStatic($this->data, $search, $strict);
        return $this;
    }

    /**
     * Instance method: Urutkan berdasarkan key
     * 
     * @param string $key
     * @param string $direction
     * @return self
     */
    public function sortBy(string $key, string $direction = 'asc'): self
    {
        $this->data = self::sortByStatic($this->data, $key, $direction);
        return $this;
    }

    /**
     * Instance method: Kelompokkan berdasarkan key
     * 
     * @param string $key
     * @return self
     */
    public function groupBy(string $key): self
    {
        $this->data = self::groupByStatic($this->data, $key);
        return $this;
    }

    /**
     * Instance method: Bersihkan array
     * 
     * @param array $removeValues
     * @return self
     */
    public function clean(array $removeValues = [null, '', false]): self
    {
        $this->data = self::cleanStatic($this->data, $removeValues);
        return $this;
    }

    /**
     * Instance method: Hapus duplikat berdasarkan key
     * 
     * @param string $key
     * @return self
     */
    public function uniqueBy(string $key): self
    {
        $this->data = self::uniqueByStatic($this->data, $key);
        return $this;
    }

    /**
     * Instance method: Get nilai dengan dot notation
     * 
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public function get(string $path, $default = null)
    {
        return self::getStatic($this->data, $path, $default);
    }

    /**
     * Instance method: Set nilai dengan dot notation
     * 
     * @param string $path
     * @param mixed $value
     * @return self
     */
    public function set(string $path, $value): self
    {
        $this->data = self::setStatic($this->data, $path, $value);
        return $this;
    }

    /**
     * Instance method: Statistik array numerik
     * 
     * @return array
     */
    public function statistics(): array
    {
        return self::statisticsStatic($this->data);
    }

    /**
     * Instance method: Ambil elemen pertama dari array
     * 
     * @param mixed $default
     * @return mixed
     */
    public function first($default = null)
    {
        return self::firstStatic($this->data, $default);
    }

    /**
     * Instance method: Ambil elemen terakhir dari array
     * 
     * @param mixed $default
     * @return mixed
     */
    public function last($default = null)
    {
        return self::lastStatic($this->data, $default);
    }

    // ========================================
    // STATIC METHODS (Original methods)
    // ========================================

    /**
     * Filter array berdasarkan kondisi tertentu
     * 
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function filterStatic(array $array, callable $callback): array
    {
        $filtered = array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan key
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function filterByKeysStatic(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Filter array berdasarkan key (alias untuk filterByKeysStatic)
     * Otomatis mendeteksi apakah single array atau array of arrays
     * Mendukung alias dengan syntax 'original_key AS new_key'
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function filterKeyStatic(array $array, array $keys): array
    {
        // Jika array kosong, return kosong
        if (empty($array)) {
            return [];
        }
        
        // Parse keys untuk mendukung alias (AS syntax)
        $parsedKeys = self::parseKeysWithAlias($keys);
        
        // Cek apakah ini array of arrays/objects (multidimensional)
        $firstElement = reset($array);
        if (is_array($firstElement) || is_object($firstElement)) {
            // Array multidimensional - filter setiap elemen
            $result = [];
            foreach ($array as $item) {
                if (is_array($item) || is_object($item)) {
                    $itemArray = is_object($item) ? (array)$item : $item;
                    $filteredItem = self::applyKeysWithAlias($itemArray, $parsedKeys);
                    if (!empty($filteredItem)) {
                        $result[] = $filteredItem;
                    }
                }
            }
            return $result;
        } else {
            // Single level array - filter langsung
            return self::applyKeysWithAlias($array, $parsedKeys);
        }
    }

    /**
     * Parse keys array untuk mendukung alias dengan syntax AS
     * 
     * @param array $keys
     * @return array
     */
    private static function parseKeysWithAlias(array $keys): array
    {
        $parsedKeys = [];
        
        foreach ($keys as $key) {
            if (is_string($key) && stripos($key, ' AS ') !== false) {
                // Format: 'original_key AS new_key'
                $parts = preg_split('/\s+AS\s+/i', trim($key), 2);
                if (count($parts) === 2) {
                    $originalKey = trim($parts[0]);
                    $aliasKey = trim($parts[1]);
                    $parsedKeys[$originalKey] = $aliasKey;
                } else {
                    // Jika parsing gagal, gunakan sebagai key biasa
                    $parsedKeys[$key] = $key;
                }
            } else {
                // Key biasa tanpa alias
                $parsedKeys[$key] = $key;
            }
        }
        
        return $parsedKeys;
    }

    /**
     * Apply keys dengan alias ke array
     * 
     * @param array $array
     * @param array $parsedKeys
     * @return array
     */
    private static function applyKeysWithAlias(array $array, array $parsedKeys): array
    {
        $result = [];
        
        foreach ($parsedKeys as $originalKey => $aliasKey) {
            if (array_key_exists($originalKey, $array)) {
                $result[$aliasKey] = $array[$originalKey];
            }
        }
        
        return $result;
    }

    /**
     * Filter array berdasarkan key tertentu dari setiap elemen dalam array
     * Berguna untuk array multidimensional
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function filterKeysFromElementsStatic(array $array, array $keys): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) || is_object($item)) {
                $itemArray = is_object($item) ? (array)$item : $item;
                $filteredItem = array_intersect_key($itemArray, array_flip($keys));
                if (!empty($filteredItem)) {
                    $result[] = $filteredItem;
                }
            }
        }
        return $result;
    }

    /**
     * Filter array berdasarkan value
     * 
     * @param array $array
     * @param mixed $value
     * @param bool $strict
     * @return array
     */
    public static function filterByValueStatic(array $array, $value, bool $strict = false): array
    {
        $filtered = array_filter($array, function($item) use ($value, $strict) {
            return $strict ? $item === $value : $item == $value;
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan tipe data
     * 
     * @param array $array
     * @param string $type
     * @return array
     */
    public static function filterByTypeStatic(array $array, string $type): array
    {
        $filtered = array_filter($array, function($item) use ($type) {
            switch ($type) {
                case 'string':
                    return is_string($item);
                case 'int':
                case 'integer':
                    return is_int($item);
                case 'float':
                case 'double':
                    return is_float($item);
                case 'bool':
                case 'boolean':
                    return is_bool($item);
                case 'array':
                    return is_array($item);
                case 'object':
                    return is_object($item);
                case 'null':
                    return is_null($item);
                default:
                    return false;
            }
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan range nilai
     * 
     * @param array $array
     * @param mixed $min
     * @param mixed $max
     * @return array
     */
    public static function filterByRangeStatic(array $array, $min, $max): array
    {
        $filtered = array_filter($array, function($item) use ($min, $max) {
            return $item >= $min && $item <= $max;
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan pattern regex
     * 
     * @param array $array
     * @param string $pattern
     * @return array
     */
    public static function filterByPatternStatic(array $array, string $pattern): array
    {
        $filtered = array_filter($array, function($item) use ($pattern) {
            return is_string($item) && preg_match($pattern, $item);
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array untuk mendapatkan nilai yang tidak null/empty
     * 
     * @param array $array
     * @param bool $strict
     * @return array
     */
    public static function filterNotEmptyStatic(array $array, bool $strict = false): array
    {
        $filtered = array_filter($array, function($item) use ($strict) {
            return $strict ? $item !== null && $item !== '' : !empty($item);
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan property objek
     * 
     * @param array $array
     * @param string $property
     * @param mixed $value
     * @param bool $strict
     * @return array
     */
    public static function filterByPropertyStatic(array $array, string $property, $value, bool $strict = false): array
    {
        $filtered = array_filter($array, function($item) use ($property, $value, $strict) {
            if (is_object($item)) {
                if (property_exists($item, $property)) {
                    return $strict ? $item->$property === $value : $item->$property == $value;
                }
            } elseif (is_array($item)) {
                if (isset($item[$property])) {
                    return $strict ? $item[$property] === $value : $item[$property] == $value;
                }
            }
            return false;
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Filter array berdasarkan property objek dengan konversi tipe otomatis
     * Mendukung pencarian string nomor vs integer, dll
     * 
     * @param array $array
     * @param string $property
     * @param mixed $value
     * @return array
     */
    public static function filterByPropertyFlexibleStatic(array $array, string $property, $value): array
    {
        $filtered = array_filter($array, function($item) use ($property, $value) {
            $itemValue = null;
            
            if (is_object($item)) {
                if (property_exists($item, $property)) {
                    $itemValue = $item->$property;
                }
            } elseif (is_array($item)) {
                if (isset($item[$property])) {
                    $itemValue = $item[$property];
                }
            }
            
            if ($itemValue === null) {
                return false;
            }
            
            // Loose comparison pertama
            if ($itemValue == $value) {
                return true;
            }
            
            // Coba konversi string ke numeric dan sebaliknya
            if (is_numeric($itemValue) && is_numeric($value)) {
                return (float)$itemValue == (float)$value;
            }
            
            // Coba konversi ke string untuk perbandingan
            if (is_string($itemValue) || is_string($value)) {
                return (string)$itemValue === (string)$value;
            }
            
            return false;
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Menampilkan array dalam format yang mudah dibaca
     * 
     * @param array $array
     * @param bool $return
     * @return string|void
     */
    public static function displayStatic(array $array, bool $return = false)
    {
        $output = print_r($array, true);
        
        if ($return) {
            return $output;
        }
        
        echo $output;
    }

    /**
     * Menampilkan array dalam format JSON
     * 
     * @param array $array
     * @param bool $pretty
     * @param bool $return
     * @return string|void
     */
    public static function displayJsonStatic(array $array, bool $pretty = true, bool $return = false)
    {
        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : 0;
        $output = json_encode($array, $flags);
        
        if ($return) {
            return $output;
        }
        
        echo $output;
    }

    /**
     * Menampilkan array dalam format tabel HTML
     * 
     * @param array $array
     * @param bool $return
     * @return string|void
     */
    public static function displayTableStatic(array $array, bool $return = false)
    {
        $html = '<table border="1" cellpadding="5" cellspacing="0">';
        
        if (self::isAssociative($array)) {
            $html .= '<tr><th>Key</th><th>Value</th></tr>';
            foreach ($array as $key => $value) {
                $displayValue = is_array($value) || is_object($value) ? 
                    json_encode($value) : htmlspecialchars((string)$value);
                $html .= "<tr><td>{$key}</td><td>{$displayValue}</td></tr>";
            }
        } else {
            $html .= '<tr><th>Index</th><th>Value</th></tr>';
            foreach ($array as $index => $value) {
                $displayValue = is_array($value) || is_object($value) ? 
                    json_encode($value) : htmlspecialchars((string)$value);
                $html .= "<tr><td>{$index}</td><td>{$displayValue}</td></tr>";
            }
        }
        
        $html .= '</table>';
        
        if ($return) {
            return $html;
        }
        
        echo $html;
    }

    /**
     * Menampilkan informasi detail tentang array
     * 
     * @param array $array
     * @param bool $return
     * @return string|void
     */
    public static function displayInfoStatic(array $array, bool $return = false)
    {
        $info = [
            'Total Elements' => count($array),
            'Is Associative' => self::isAssociative($array) ? 'Yes' : 'No',
            'Is Multidimensional' => self::isMultidimensional($array) ? 'Yes' : 'No',
            'Memory Usage' => self::getMemoryUsage($array) . ' bytes',
            'Data Types' => self::getDataTypes($array),
            'Keys' => array_keys($array),
            'Values Preview' => array_slice($array, 0, 5)
        ];
        
        $output = "Array Information:\n";
        $output .= str_repeat('=', 50) . "\n";
        
        foreach ($info as $key => $value) {
            if (is_array($value)) {
                $output .= "{$key}: " . json_encode($value) . "\n";
            } else {
                $output .= "{$key}: {$value}\n";
            }
        }
        
        if ($return) {
            return $output;
        }
        
        echo $output;
    }

    /**
     * Mengecek apakah array adalah associative
     * 
     * @param array $array
     * @return bool
     */
    public static function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Mengecek apakah array adalah multidimensional
     * 
     * @param array $array
     * @return bool
     */
    public static function isMultidimensional(array $array): bool
    {
        return count($array) !== count($array, COUNT_RECURSIVE);
    }

    /**
     * Mendapatkan estimasi penggunaan memori array
     * 
     * @param array $array
     * @return int
     */
    public static function getMemoryUsage(array $array): int
    {
        return strlen(serialize($array));
    }

    /**
     * Mendapatkan tipe data yang ada dalam array
     * 
     * @param array $array
     * @return array
     */
    public static function getDataTypes(array $array): array
    {
        $types = [];
        foreach ($array as $value) {
            $type = gettype($value);
            if (!in_array($type, $types)) {
                $types[] = $type;
            }
        }
        return $types;
    }

    /**
     * Mencari elemen dalam array dengan berbagai kondisi
     * 
     * @param array $array
     * @param mixed $search
     * @param bool $strict
     * @return array
     */
    public static function searchStatic(array $array, $search, bool $strict = false): array
    {
        $results = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subResults = self::searchStatic($value, $search, $strict);
                if (!empty($subResults)) {
                    $results[$key] = $subResults;
                }
            } elseif (is_object($value)) {
                $objectArray = (array)$value;
                $subResults = self::searchStatic($objectArray, $search, $strict);
                if (!empty($subResults)) {
                    $results[$key] = $subResults;
                }
            } else {
                if ($strict) {
                    if ($value === $search) {
                        $results[$key] = $value;
                    }
                } else {
                    if (is_string($value) && is_string($search)) {
                        if (stripos($value, $search) !== false) {
                            $results[$key] = $value;
                        }
                    } elseif ($value == $search) {
                        $results[$key] = $value;
                    }
                }
            }
        }
        
        return $results;
    }

    /**
     * Mengurutkan array berdasarkan kriteria tertentu
     * 
     * @param array $array
     * @param string $key
     * @param string $direction
     * @return array
     */
    public static function sortByStatic(array $array, string $key, string $direction = 'asc'): array
    {
        usort($array, function($a, $b) use ($key, $direction) {
            $aVal = is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null);
            $bVal = is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null);
            
            if ($aVal === $bVal) {
                return 0;
            }
            
            $result = $aVal < $bVal ? -1 : 1;
            return $direction === 'desc' ? -$result : $result;
        });
        
        return $array;
    }

    /**
     * Mengelompokkan array berdasarkan key tertentu
     * 
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function groupByStatic(array $array, string $key): array
    {
        $groups = [];
        
        foreach ($array as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? 'unknown') : 
                       (is_object($item) ? ($item->$key ?? 'unknown') : 'unknown');
            
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            
            $groups[$groupKey][] = $item;
        }
        
        return $groups;
    }

    /**
     * Mengubah objek menjadi array secara rekursif
     * 
     * @param mixed $data
     * @return array
     */
    public static function objectToArray($data): array
    {
        if (is_object($data)) {
            $data = (array)$data;
        }
        
        if (is_array($data)) {
            return array_map([self::class, 'objectToArray'], $data);
        }
        
        return $data;
    }

    /**
     * Mengubah array menjadi objek secara rekursif
     * 
     * @param array $array
     * @return object
     */
    public static function arrayToObject(array $array): object
    {
        return json_decode(json_encode($array));
    }

    /**
     * Membersihkan array dari nilai null, empty, atau false
     * 
     * @param array $array
     * @param array $removeValues
     * @return array
     */
    public static function cleanStatic(array $array, array $removeValues = [null, '', false]): array
    {
        $filtered = array_filter($array, function($value) use ($removeValues) {
            return !in_array($value, $removeValues, true);
        });
        
        // Re-index array to maintain sequential numeric keys like [0, 1, 2, ...]
        return array_values($filtered);
    }

    /**
     * Mendapatkan nilai dari array/objek dengan dot notation
     * 
     * @param array|object $data
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public static function getStatic($data, string $path, $default = null)
    {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } elseif (is_object($current) && property_exists($current, $key)) {
                $current = $current->$key;
            } else {
                return $default;
            }
        }
        
        return $current;
    }

    /**
     * Mengatur nilai dalam array/objek dengan dot notation
     * 
     * @param array $array
     * @param string $path
     * @param mixed $value
     * @return array
     */
    public static function setStatic(array $array, string $path, $value): array
    {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
        
        return $array;
    }

    /**
     * Menghapus duplikat dari array berdasarkan key tertentu
     * 
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function uniqueByStatic(array $array, string $key): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($array as $item) {
            $keyValue = is_array($item) ? ($item[$key] ?? null) : 
                       (is_object($item) ? ($item->$key ?? null) : null);
            
            if (!in_array($keyValue, $seen)) {
                $seen[] = $keyValue;
                $unique[] = $item;
            }
        }
        
        return $unique;
    }

    /**
     * Menghitung statistik dari array numerik
     * 
     * @param array $array
     * @return array
     */
    public static function statisticsStatic(array $array): array
    {
        $numbers = array_filter($array, 'is_numeric');
        
        if (empty($numbers)) {
            return [];
        }
        
        sort($numbers);
        $count = count($numbers);
        $sum = array_sum($numbers);
        $mean = $sum / $count;
        
        return [
            'count' => $count,
            'sum' => $sum,
            'mean' => $mean,
            'median' => $count % 2 === 0 ? 
                ($numbers[$count/2 - 1] + $numbers[$count/2]) / 2 : 
                $numbers[floor($count/2)],
            'min' => min($numbers),
            'max' => max($numbers),
            'range' => max($numbers) - min($numbers)
        ];
    }

    /**
     * Ambil elemen pertama dari array
     * 
     * @param array $array
     * @param mixed $default
     * @return mixed
     */
    public static function firstStatic(array $array, $default = null)
    {
        return !empty($array) ? reset($array) : $default;
    }

    /**
     * Ambil elemen terakhir dari array
     * 
     * @param array $array
     * @param mixed $default
     * @return mixed
     */
    public static function lastStatic(array $array, $default = null)
    {
        return !empty($array) ? end($array) : $default;
    }

    /**
     * CONTOH PENGGUNAAN filterByProperty untuk string nomor:
     * 
     * // Data contoh
     * $data = [
     *     ['id' => 37, 'name' => 'John'],      // integer
     *     ['id' => '37', 'name' => 'Jane'],    // string
     *     ['id' => 42, 'name' => 'Bob'],
     *     ['id' => '42', 'name' => 'Alice']
     * ];
     * 
     * $nexaArray = new NexaArray($data);
     * 
     * // CARA LAMA (strict comparison) - hanya cocok dengan tipe yang sama
     * $result1 = $nexaArray->filterByProperty('id', '37', true); // hanya ['id' => '37', 'name' => 'Jane']
     * 
     * // CARA BARU (loose comparison) - cocok dengan nilai yang sama meskipun tipe berbeda
     * $result2 = $nexaArray->filterByProperty('id', '37', false); // ['id' => 37, 'name' => 'John'] dan ['id' => '37', 'name' => 'Jane']
     * 
     * // CARA FLEKSIBEL (otomatis handle konversi tipe)
     * $result3 = $nexaArray->filterByPropertyFlexible('id', '37'); // ['id' => 37, 'name' => 'John'] dan ['id' => '37', 'name' => 'Jane']
     * 
     * // Untuk menggunakan method static langsung:
     * $result4 = NexaArray::filterByPropertyStatic($data, 'id', '37', false);
     * $result5 = NexaArray::filterByPropertyFlexibleStatic($data, 'id', '37');
     */

    /**
     * CONTOH PERBAIKAN STRUKTUR OUTPUT:
     * 
     * // Data contoh dengan key tidak berurutan
     * $tasks = [
     *     0 => ['id' => 1, 'assigned_to' => 1, 'title' => 'Task A'],
     *     1 => ['id' => 2, 'assigned_to' => 2, 'title' => 'Task B'], 
     *     2 => ['id' => 3, 'assigned_to' => 1, 'title' => 'Task C']
     * ];
     * 
     * // SEBELUM PERBAIKAN:
     * // filterByProperty('assigned_to', 1) menghasilkan:
     * // {
     * //   "0": {"id": 1, "assigned_to": 1, "title": "Task A"},
     * //   "2": {"id": 3, "assigned_to": 1, "title": "Task C"}
     * // }
     * // ^ Struktur object dengan key numerik tidak berurutan
     * 
     * // SESUDAH PERBAIKAN:
     * // filterByProperty('assigned_to', 1) menghasilkan:
     * // [
     * //   {"id": 1, "assigned_to": 1, "title": "Task A"},
     * //   {"id": 3, "assigned_to": 1, "title": "Task C"}
     * // ]
     * // ^ Struktur array dengan key berurutan [0, 1, 2, ...]
     * 
     * // Ini membuat output konsisten dengan getAllTasks() yang menghasilkan:
     * // [
     * //   {"id": 1, "assigned_to": 1, "title": "Task A"},
     * //   {"id": 2, "assigned_to": 2, "title": "Task B"},
     * //   {"id": 3, "assigned_to": 1, "title": "Task C"}
     * // ]
     */
} 