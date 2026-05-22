<?php
namespace App\System;
use App\System\Storage\NexaDb;
use App\System\Helpers\NexaRaw;
use App\System\Helpers\NexaDecode;
use App\System\Helpers\NexaSession;
use App\System\Storage\NexaJon;
use PDO;
class NexaModel {
    private $db;
    private $table;
    private $select = '*';
    private $where = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    private $groupBy = [];
    private $having = [];
    private $unions = [];
    
    // Security & Performance Properties
    private $allowedFunctions = ['UPPER', 'LOWER', 'DATE', 'YEAR', 'MONTH', 'DAY', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'RAND', 'CONCAT', 'GROUP_CONCAT', 'SUBSTRING', 'TRIM', 'LTRIM', 'RTRIM', 'LENGTH', 'COALESCE', 'IFNULL', 'NULLIF', 'DATE_FORMAT', 'FORMAT', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'IF', 'CURDATE', 'CURTIME', 'NOW', 'UNIX_TIMESTAMP', 'FROM_UNIXTIME', 'TIMESTAMPDIFF', 'DATE_ADD', 'DATE_SUB', 'INTERVAL', 'MONTHNAME', 'DAYNAME', 'WEEKDAY', 'DAYOFWEEK', 'DAYOFYEAR', 'WEEK', 'QUARTER', 'ROUND', 'CEIL', 'FLOOR', 'ABS', 'SIGN', 'MOD', 'POW', 'SQRT', 'LOG', 'EXP', 'GREATEST', 'LEAST', 'REPLACE', 'REVERSE', 'LEFT', 'RIGHT', 'LPAD', 'RPAD', 'LOCATE', 'POSITION', 'INSTR', 'FIND_IN_SET', 'FIELD', 'ASCII', 'CHAR', 'HEX', 'UNHEX', 'BIN', 'OCT', 'CONV', 'MD5', 'SHA1', 'SHA2', 'CRC32', 'COMPRESS', 'UNCOMPRESS', 'JSON_EXTRACT', 'JSON_UNQUOTE', 'JSON_ARRAY', 'JSON_OBJECT', 'JSON_MERGE', 'JSON_VALID', 'CAST', 'CONVERT', 'BINARY', 'CHAR_LENGTH', 'CHARACTER_LENGTH', 'BIT_LENGTH', 'OCTET_LENGTH', 'WEIGHT_STRING'];
    private $maxLimit = 10000;
    private $queryLog = [];
    protected ?NexaSession $session = null;
    protected ?int $UserId = null;
    protected ?int $requestUserId = null; // userid dari request data (tanpa session)
    // REMOVED: Cache functionality - using direct query execution instead
    // private $cacheEnabled = true;
    // private static $queryCache = [];

    public function __construct() {
        try {
            $this->db = NexaDb::getInstance()->getConnection();
            if (!$this->db) {
                $this->logError("Failed to get database instance", null, []);
                throw new \Exception("Failed to get database instance");
            }
            
            // Initialize session
            $this->session = NexaSession::getInstance();
            $this->session->start();
            
            $this->logInfo("NexaModel initialized successfully");
        } catch (\Exception $e) {
            $this->logError("NexaModel initialization error: " . $e->getMessage(), null, []);
            throw new \Exception("Failed to initialize database connection: " . $e->getMessage(), 0, $e);
        }
    }

    private function ensureConnection() {
        try {
            if (!$this->db) {
                $this->db = NexaDb::getInstance()->getConnection();
                
                if (!$this->db) {
                    $this->logError("Database connection test failed", null, []);
                    throw new \Exception("Database connection test failed");
                }
                $this->logInfo("Database connection established successfully");
            }
        } catch (\Exception $e) {
            $this->logError("Database connection error: " . $e->getMessage(), null, []);
            throw new \Exception("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Enhanced error logging with context
     */
    private function logError($message, $query = null, $bindings = []) {
        $context = [
            'message' => $message,
            'query' => $query,
            'bindings' => $bindings,
            'file' => __FILE__,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        error_log("NexaModel Error: " . json_encode($context));
    }

    /**
     * Info logging 
     */
    private function logInfo($message) {
        // error_log("NexaModel Info: $message - " . date('Y-m-d H:i:s'));
    }

    /**
     * Validate table name for security
     */
    private function validateTableName($table) {
        if (empty($table)) {
            throw new \InvalidArgumentException("Table name cannot be empty");
        }
        
        // Only allow alphanumeric characters, underscores, and dots
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name: $table");
        }
        
        return true;
    }
    
    public function secretKey($Key='nexaui2025'){
        $nexaDecode = new NexaDecode($Key);
        return $nexaDecode;
    }

    /**
     * Get user ID - prioritaskan dari request data, fallback ke session
     * 
     * @return int|null User ID atau null jika tidak ada
     */
    public function userid(){
        // Prioritas 1: userid dari request data (untuk API tanpa session)
        if ($this->requestUserId !== null) {
            return $this->requestUserId;
        }
        
        // Prioritas 2: userid dari session (untuk request dengan session)
        if ($this->session !== null) {
            $sessionUserId = $this->session->getUserId();
            if ($sessionUserId !== null) {
                return $sessionUserId;
            }
        }
        
        // Prioritas 3: cek langsung dari request data (POST/GET) sebagai fallback
        if (isset($_POST['userid']) && !empty($_POST['userid'])) {
            return (int)$_POST['userid'];
        }
        if (isset($_GET['userid']) && !empty($_GET['userid'])) {
            return (int)$_GET['userid'];
        }
        
        return null;
    }
    
    /**
     * Set user ID dari request data (untuk API tanpa session)
     * 
     * @param int|null $userId User ID dari request
     * @return void
     */
    public function setRequestUserId(?int $userId): void {
        $this->requestUserId = $userId;
    }



    /**
     * Validate column name for security with SQL alias support
     */
    private function validateColumnName($column) {
        if (empty($column)) {
            throw new \InvalidArgumentException("Column name cannot be empty");
        }
        
        // Trim whitespace
        $column = trim($column);
        
        // Check if it's a SQL alias (column AS alias)
        if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $column, $matches)) {
            $originalColumn = trim($matches[1]);
            $aliasName = trim($matches[2]);
            
            // Validate original column recursively (could be function call)
            $this->validateColumnName($originalColumn);
            // Validate alias name (should be simple column name)
            $this->validateSingleColumnName($aliasName);
            
            return true;
        }
        
        // Check for CASE WHEN statements (complex validation)
        if (preg_match('/^CASE\s+/i', $column)) {
            return $this->validateCaseStatement($column);
        }
        
        // Check if it's a function call
        if (preg_match('/^([A-Z_]+)\(/', $column, $matches)) {
            $function = $matches[1];
            if (!in_array($function, $this->allowedFunctions)) {
                throw new \InvalidArgumentException("Function not allowed: $function");
            }
            
            // Enhanced validation for function parameters
            return $this->validateFunctionParameters($column);
        }
        
        // Validate single column name
        return $this->validateSingleColumnName($column);
    }
    
    /**
     * Validate CASE WHEN statements
     */
    private function validateCaseStatement($statement) {
        // Basic CASE statement pattern validation
        // Allow CASE WHEN ... THEN ... ELSE ... END patterns
        $pattern = '/^CASE\s+.*\s+END$/i';
        if (!preg_match($pattern, $statement)) {
            throw new \InvalidArgumentException("Invalid CASE statement syntax");
        }
        
        // Check for required keywords
        $requiredKeywords = ['CASE', 'WHEN', 'THEN', 'END'];
        foreach ($requiredKeywords as $keyword) {
            if (!preg_match('/\b' . $keyword . '\b/i', $statement)) {
                if ($keyword !== 'ELSE') { // ELSE is optional
                    throw new \InvalidArgumentException("Missing required keyword in CASE statement: $keyword");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Enhanced validation for function parameters
     */
    private function validateFunctionParameters($functionCall) {
        // Extract function name and parameters
        if (!preg_match('/^([A-Z_]+)\((.+)\)$/i', $functionCall, $matches)) {
            return true; // Simple function without parameters
        }
        
        $function = strtoupper($matches[1]);
        $params = $matches[2];
        
        // Special validation for specific functions
        switch ($function) {
            case 'DATE_FORMAT':
                return $this->validateDateFormatParams($params);
            case 'FORMAT':
                return $this->validateFormatParams($params);
            case 'CASE':
                return $this->validateCaseStatement($functionCall);
            default:
                // General parameter validation
                // Allow column names, strings, numbers, operators, and common SQL patterns including *
                $allowedPattern = '/^[a-zA-Z0-9_\.\,\s\'\"\:\/-=?&@+#%\(\)\[\]<>!|*]+$/';
                if (!preg_match($allowedPattern, $params)) {
                    throw new \InvalidArgumentException("Invalid function parameters: $params");
                }
                return true;
        }
    }
    
    /**
     * Validate DATE_FORMAT function parameters
     */
    private function validateDateFormatParams($params) {
        // DATE_FORMAT(date, format) - should have exactly 2 parameters
        $paramArray = $this->splitFunctionParams($params);
        if (count($paramArray) !== 2) {
            throw new \InvalidArgumentException("DATE_FORMAT requires exactly 2 parameters");
        }
        
        // First parameter should be a column name or date expression
        $dateParam = trim($paramArray[0], " '\"");
        if (!preg_match('/^[a-zA-Z0-9_\.()]+$/', $dateParam)) {
            throw new \InvalidArgumentException("Invalid date parameter in DATE_FORMAT: $dateParam");
        }
        
        // Second parameter should be a format string (allow more characters for date formats)
        $formatParam = trim($paramArray[1], " '\"");
        if (!preg_match('/^[%a-zA-Z0-9\-\/\:\s\.\,\(\)]+$/', $formatParam)) {
            throw new \InvalidArgumentException("Invalid format parameter in DATE_FORMAT: $formatParam");
        }
        
        return true;
    }
    
    /**
     * Validate FORMAT function parameters
     */
    private function validateFormatParams($params) {
        // FORMAT(number, decimals) - should have 1 or 2 parameters
        $paramArray = $this->splitFunctionParams($params);
        if (count($paramArray) < 1 || count($paramArray) > 2) {
            throw new \InvalidArgumentException("FORMAT requires 1 or 2 parameters");
        }
        
        // First parameter should be a column name or number
        $numberParam = trim($paramArray[0], " '\"");
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $numberParam)) {
            throw new \InvalidArgumentException("Invalid number parameter in FORMAT");
        }
        
        // Second parameter (if exists) should be a number
        if (isset($paramArray[1])) {
            $decimalsParam = trim($paramArray[1], " '\"");
            if (!is_numeric($decimalsParam)) {
                throw new \InvalidArgumentException("Invalid decimals parameter in FORMAT");
            }
        }
        
        return true;
    }
    
    /**
     * Split function parameters respecting nested parentheses and quotes
     */
    private function splitFunctionParams($params) {
        $parameters = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = '';
        
        for ($i = 0; $i < strlen($params); $i++) {
            $char = $params[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = '';
                $current .= $char;
            } elseif (!$inQuotes && $char === '(') {
                $depth++;
                $current .= $char;
            } elseif (!$inQuotes && $char === ')') {
                $depth--;
                $current .= $char;
            } elseif (!$inQuotes && $char === ',' && $depth === 0) {
                $parameters[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if (!empty($current)) {
            $parameters[] = trim($current);
        }
        
        return $parameters;
    }
    
    /**
     * Validate single column name (without alias)
     */
    private function validateSingleColumnName($column) {
        if (empty($column)) {
            throw new \InvalidArgumentException("Column name cannot be empty");
        }
        
        // Only allow alphanumeric characters, underscores, dots, and asterisk
        if (!preg_match('/^[a-zA-Z0-9_\.\*]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: $column");
        }
        
        return true;
    }

    /**
     * Validate limit value
     */
    private function validateLimit($limit) {
        if ($limit <= 0) {
            throw new \InvalidArgumentException("Limit must be greater than 0");
        }
        
        if ($limit > $this->maxLimit) {
            throw new \InvalidArgumentException("Limit cannot exceed $this->maxLimit");
        }
        
        return true;
    }

    /**
     * Pilih tabel yang akan digunakan
     */
    public function table($table) {
        $this->validateTableName($table);
        $this->table = $table;
        return $this;
    }
    
    /**
     * Pilih tabel yang akan digunakan
     */
    public function Storage($table) {
        $this->validateTableName($table);
        $this->table = $table;
        return $this;
    }
    /**
     * Pilih kolom yang akan diambil
     */
    public function select($columns) {
        if (is_array($columns)) {
            foreach ($columns as $column) {
                $this->validateColumnName(trim($column));
            }
            $this->select = implode(', ', $columns);
        } else {
            // Handle comma-separated string
            if (strpos($columns, ',') !== false) {
                $columnArray = array_map('trim', explode(',', $columns));
                foreach ($columnArray as $column) {
                    $this->validateColumnName($column);
                }
            } else {
                $this->validateColumnName($columns);
            }
            $this->select = $columns;
        }
        return $this;
    }

    /**
     * Pilih semua kolom kecuali yang disebutkan
     * @param array|string $excludeColumns Kolom yang akan dikecualikan
     * @return $this
     * 
     * Example:
     * $model->table('users')->except(['password', 'remember_token'])->get();
     * $model->table('users')->except('password,remember_token')->get();
     */
    public function except($excludeColumns) {
        if (!$this->table) {
            throw new \InvalidArgumentException("Table must be set before using except() method");
        }

        // Convert string to array if needed
        if (is_string($excludeColumns)) {
            $excludeColumns = array_map('trim', explode(',', $excludeColumns));
        }

        if (!is_array($excludeColumns)) {
            throw new \InvalidArgumentException("Exclude columns must be array or comma-separated string");
        }

        // Validate excluded column names
        foreach ($excludeColumns as $column) {
            $this->validateColumnName(trim($column));
        }

        // Get all columns from table
        $allColumns = $this->getTableColumnNames();
        
        // Remove excluded columns
        $selectedColumns = array_diff($allColumns, $excludeColumns);
        
        if (empty($selectedColumns)) {
            throw new \InvalidArgumentException("Cannot exclude all columns from table");
        }

        $this->select = implode(', ', $selectedColumns);
        return $this;
    }

    /**
     * Alias untuk method except()
     * @param array|string $excludeColumns Kolom yang akan dikecualikan
     * @return $this
     */
    public function exclude($excludeColumns) {
        return $this->except($excludeColumns);
    }

    /**
     * Method yang lebih intuitif untuk tidak memilih field tertentu
     * @param array|string $excludeColumns Kolom yang tidak akan dipilih
     * @return $this
     * 
     * Example:
     * $model->table('users')->noSelect(['password', 'remember_token'])->get();
     * $model->table('users')->noSelect('password,token')->get();
     */
    public function noSelect($excludeColumns) {
        return $this->except($excludeColumns);
    }

    /**
     * Method untuk tidak mengambil field tertentu
     * @param array|string $excludeColumns Kolom yang tidak akan diambil
     * @return $this
     * 
     * Example:
     * $model->table('users')->without(['password', 'secret'])->get();
     */
    public function without($excludeColumns) {
        return $this->except($excludeColumns);
    }

    /**
     * Method untuk mengabaikan field tertentu
     * @param array|string $excludeColumns Kolom yang akan diabaikan
     * @return $this
     * 
     * Example:
     * $model->table('users')->ignore(['password', 'api_key'])->get();
     */
    public function ignore($excludeColumns) {
        return $this->except($excludeColumns);
    }

    /**
     * Method untuk melewati field tertentu
     * @param array|string $excludeColumns Kolom yang akan dilewati
     * @return $this
     * 
     * Example:
     * $model->table('users')->skip(['password', 'token'])->get();
     */
    public function skipFields($excludeColumns) {
        return $this->except($excludeColumns);
    }

    /**
     * Tambahkan kondisi WHERE
     * 
     * Mendukung berbagai format:
     * - where('name', 'John')                      // name = 'John'
     * - where('age', '>', 18)                      // age > 18
     * - where('category', '!=', ['Drive', 'Meta']) // category NOT IN ('Drive', 'Meta')
     * - where('status', '=', ['active', 'pending']) // status IN ('active', 'pending')
     * - where(['name' => 'John', 'age' => 25])     // name = 'John' AND age = 25
     */
    public function where($column, $operator = null, $value = null, $boolean = 'AND') {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where[] = [$key, '=', $val, 'AND'];
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            
            // Special handling for array values with != operator (convert to NOT IN)
            if (is_array($value) && ($operator === '!=' || $operator === '<>')) {
                if (empty($value)) {
                    return $this;
                }
                $this->where[] = [$column, 'NOT IN', $value, $boolean, 'NOT_IN'];
                return $this;
            }
            
            // Special handling for array values with = operator (convert to IN)
            if (is_array($value) && $operator === '=') {
                if (empty($value)) {
                    return $this;
                }
                $this->where[] = [$column, 'IN', $value, $boolean, 'IN'];
                return $this;
            }
            
            // Special handling for LIKE queries
            if (strtoupper($operator) === 'LIKE') {
                // If the value already contains wildcards, use it as is
                if (strpos($value, '%') !== false) {
                    $this->where[] = [$column, $operator, $value, $boolean];
                } else {
                    // Otherwise, wrap the value in wildcards
                    $this->where[] = [$column, $operator, "%$value%", $boolean];
                }
            } else {
                $this->where[] = [$column, $operator, $value, $boolean];
            }
        }
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE
     */
    public function orWhere($column, $operator = null, $value = null) {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE IN
     */
    public function whereIn($column, array $values, $boolean = 'AND') {
        if (empty($values)) {
            return $this;
        }
        $this->where[] = [$column, 'IN', $values, $boolean, 'IN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT IN
     */
    public function whereNotIn($column, array $values, $boolean = 'AND') {
        if (empty($values)) {
            return $this;
        }
        $this->where[] = [$column, 'NOT IN', $values, $boolean, 'NOT_IN'];
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE IN
     */
    public function orWhereIn($column, array $values) {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Tambahkan kondisi OR WHERE NOT IN
     */
    public function orWhereNotIn($column, array $values) {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE BETWEEN
     */
    public function whereBetween($column, array $values, $boolean = 'AND') {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween requires exactly 2 values');
        }
        $this->where[] = [$column, 'BETWEEN', $values, $boolean, 'BETWEEN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT BETWEEN
     */
    public function whereNotBetween($column, array $values, $boolean = 'AND') {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween requires exactly 2 values');
        }
        $this->where[] = [$column, 'NOT BETWEEN', $values, $boolean, 'NOT_BETWEEN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NULL
     */
    public function whereNull($column, $boolean = 'AND') {
        $this->where[] = [$column, 'IS NULL', null, $boolean, 'NULL'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT NULL
     */
    public function whereNotNull($column, $boolean = 'AND') {
        $this->where[] = [$column, 'IS NOT NULL', null, $boolean, 'NOT_NULL'];
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE NULL
     */
    public function orWhereNull($column) {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Tambahkan kondisi OR WHERE NOT NULL
     */
    public function orWhereNotNull($column) {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE DATE
     */
    public function whereDate($column, $operator, $value, $boolean = 'AND') {
        return $this->where("DATE($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE YEAR
     */
    public function whereYear($column, $operator, $value, $boolean = 'AND') {
        return $this->where("YEAR($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE MONTH
     */
    public function whereMonth($column, $operator, $value, $boolean = 'AND') {
        return $this->where("MONTH($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE DAY
     */
    public function whereDay($column, $operator, $value, $boolean = 'AND') {
        return $this->where("DAY($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE untuk periode tertentu (dari tanggal mulai + jumlah hari)
     * @param string $column Nama kolom tanggal
     * @param string $startDate Tanggal mulai (format Y-m-d)
     * @param int $periodDays Jumlah hari periode (contoh: 2 = dari startDate sampai startDate + 1 hari)
     * @param string $boolean Operator boolean (AND/OR)
     * @return $this
     * 
     * Example:
     * $query->wherePeriode('created_at', '2025-04-21', 2);
     * // Akan mencari data dari 2025-04-21 sampai 2025-04-22 (periode 2 hari)
     */
    public function wherePeriode($column, $startDate, $periodDays, $boolean = 'AND') {
        // Validasi input
        if (empty($startDate)) {
            throw new \InvalidArgumentException("Start date cannot be empty");
        }
        
        if (!is_numeric($periodDays) || $periodDays < 0) {
            throw new \InvalidArgumentException("Period days must be a non-negative number");
        }
        
        // Validasi format tanggal
        $dateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $startDate) {
            throw new \InvalidArgumentException("Invalid date format. Use Y-m-d format (e.g., 2025-07-14)");
        }
        
        // Hitung tanggal akhir - untuk period 5 hari dari 2025-04-21, range: 2025-04-21 sampai 2025-04-25
        $endDateTime = clone $dateTime;
        $endDate = $endDateTime->add(new \DateInterval('P' . ($periodDays - 1) . 'D'))->format('Y-m-d');
        
        // Gunakan format datetime yang kompatibel dengan semua jenis kolom tanggal
        $this->where($column, '>=', $startDate . ' 00:00:00', $boolean);
        $this->where($column, '<=', $endDate . ' 23:59:59', 'AND');
        
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE untuk periode tertentu
     * @param string $column Nama kolom tanggal
     * @param string $startDate Tanggal mulai (format Y-m-d)
     * @param int $periodDays Jumlah hari periode
     * @return $this
     */
    public function orWherePeriode($column, $startDate, $periodDays) {
        return $this->wherePeriode($column, $startDate, $periodDays, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE untuk range tanggal yang spesifik (inclusive)
     * Method ini mengatasi masalah datetime di wherePeriode
     * @param string $column Nama kolom tanggal
     * @param string $startDate Tanggal mulai (format Y-m-d)
     * @param string $endDate Tanggal akhir (format Y-m-d)
     * @param string $boolean Operator boolean (AND/OR)
     * @return $this
     * 
     * Example:
     * $query->whereDateRange('created_at', '2025-04-21', '2025-04-24');
     * // Akan mencari data dari 2025-04-21 00:00:00 sampai 2025-04-24 23:59:59
     */
    public function whereDateRange($column, $startDate, $endDate, $boolean = 'AND') {
        // Validasi input
        if (empty($startDate) || empty($endDate)) {
            throw new \InvalidArgumentException("Start date and end date cannot be empty");
        }
        
        // Validasi format tanggal
        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$startDateTime || $startDateTime->format('Y-m-d') !== $startDate) {
            throw new \InvalidArgumentException("Invalid start date format. Use Y-m-d format (e.g., 2025-04-21)");
        }
        
        if (!$endDateTime || $endDateTime->format('Y-m-d') !== $endDate) {
            throw new \InvalidArgumentException("Invalid end date format. Use Y-m-d format (e.g., 2025-04-24)");
        }
        
        // Pastikan start date <= end date
        if ($startDateTime > $endDateTime) {
            throw new \InvalidArgumentException("Start date must be less than or equal to end date");
        }
        
        // Gunakan DATE() function untuk memastikan perbandingan hanya tanggal (bukan datetime)
        $this->where("DATE($column)", '>=', $startDate, $boolean);
        $this->where("DATE($column)", '<=', $endDate, 'AND');
        
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE untuk range tanggal
     */
    public function orWhereDateRange($column, $startDate, $endDate) {
        return $this->whereDateRange($column, $startDate, $endDate, 'OR');
    }

    /**
     * Tambahkan JOIN
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER') {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type
        ];
        return $this;
    }

    /**
     * Tambahkan LEFT JOIN
     */
    public function leftJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Tambahkan ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        // Handle array of columns
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->orderBy[] = [$col, strtoupper($direction)];
            }
        } 
        // Handle comma-separated string
        else if (strpos($column, ',') !== false) {
            $columns = array_map('trim', explode(',', $column));
            foreach ($columns as $col) {
                $this->orderBy[] = [$col, strtoupper($direction)];
            }
        } 
        // Handle single column
        else {
            $this->orderBy[] = [$column, strtoupper($direction)];
        }
        return $this;
    }

    /**
     * Tambahkan LIMIT
     */
    public function limit($limit) {
        $this->validateLimit($limit);
        $this->limit = $limit;
        return $this;
    }

    /**
     * Tambahkan OFFSET
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Tambahkan GROUP BY
     */
    public function groupBy($columns) {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * Tambahkan HAVING
     */
    public function having($column, $operator = null, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->having[] = [$column, $operator, $value];
        return $this;
    }

    /**
     * Eksekusi query SELECT
     */
    public function get() {
        $this->ensureConnection();
        
        $query = $this->buildSelectQuery();
        $bindings = $this->getWhereValues();
        
        // REMOVED: Cache checking logic - executing query directly for fresh data
        
        try {
            $startTime = microtime(true);
            
            $stmt = $this->db->prepare($query);
            $this->bindWhereValues($stmt);
            $stmt->execute();
            
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $executionTime = (microtime(true) - $startTime) * 1000; // milliseconds
            
            // Log slow queries
            if ($executionTime > 1000) { // > 1 second
                $this->logError("Slow query detected", $query, $bindings);
            }
            
            // Handle JSON fields
            foreach ($results as &$row) {
                foreach ($row as $key => $value) {
                    if (is_string($value) && $this->isJson($value)) {
                        $row[$key] = json_decode($value, true);
                    }
                }
            }
            
            // REMOVED: Cache storage logic - returning fresh results directly
            
            $this->resetQueryState();
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logError("Query execution failed: " . $e->getMessage(), $query, $bindings);
            $this->resetQueryState();
            throw new \Exception("Query execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a string is valid JSON
     */
    private function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * REMOVED: Cache key generation - no longer needed
     */

    /**
     * REMOVED: Cache cleanup - no longer needed
     */

    /**
     * REMOVED: Clear cache - no longer needed for backward compatibility
     */
    public function clearCache() {
        // No cache to clear - method kept for backward compatibility
        return $this;
    }

    /**
     * REMOVED: Cache control - no longer needed for backward compatibility
     */
    public function withoutCache() {
        // No cache to disable - method kept for backward compatibility
        return $this;
    }

    /**
     * REMOVED: Cache control - no longer needed for backward compatibility
     */
    public function withCache() {
        // No cache to enable - method kept for backward compatibility
        return $this;
    }

    /**
     * REMOVED: Cache invalidation - no longer needed
     */
    private function invalidateTableCache() {
        // No cache to invalidate - method kept for backward compatibility
    }

    /**
     * REMOVED: Cache statistics - no longer needed for backward compatibility
     */
    public function getCacheStats() {
        return [
            'total_items' => 0,
            'memory_usage_bytes' => 0,
            'memory_usage_mb' => 0.0,
            'status' => 'Cache disabled - using direct query execution'
        ];
    }

    /**
     * Configure model settings
     */
    public function configure(array $options) {
        if (isset($options['max_limit'])) {
            $this->maxLimit = (int)$options['max_limit'];
        }
        
        // REMOVED: Cache configuration - no longer needed
        // if (isset($options['cache_enabled'])) {
        //     $this->cacheEnabled = (bool)$options['cache_enabled'];
        // }
        
        if (isset($options['allowed_functions'])) {
            $this->allowedFunctions = (array)$options['allowed_functions'];
        }
        
        return $this;
    }

    /**
     * Get current configuration
     */
    public function getConfig() {
        return [
            'max_limit' => $this->maxLimit,
            'cache_enabled' => false, // Cache disabled - using direct execution
            'allowed_functions' => $this->allowedFunctions
        ];
    }

    /**
     * Force delete without WHERE clause (dangerous)
     */
    public function forceDelete() {
        $this->ensureConnection();
        
        try {
            $query = "DELETE FROM {$this->table}";
            if (!empty($this->where)) {
                $query .= ' WHERE ' . $this->buildWhereClause();
            }
            
            $bindings = $this->getWhereValues();
            
            $this->logInfo("Force delete executed on table: {$this->table}");
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($bindings);
            
                    // REMOVED: Cache invalidation - no longer needed
            
            $this->resetQueryState();
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError("Force delete failed: " . $e->getMessage(), $query ?? null, $bindings ?? []);
            $this->resetQueryState();
            throw new \Exception("Force delete operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get query performance statistics
     */
    public function getPerformanceStats() {
        return [
            'query_log_count' => count($this->queryLog),
            'cache_stats' => $this->getCacheStats(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Reset query state for fresh query
     */
    private function resetQueryState() {
        $this->select = '*';
        $this->where = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->groupBy = [];
        $this->having = [];
        $this->unions = [];
        // Keep table name as it might be needed for chaining
    }

    /**
     * Ambil satu baris hasil
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        // Note: get() already calls resetQueryState()
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Ambil satu baris data terakhir
     */
    public function last() {
        $this->orderBy('id', 'DESC');
        return $this->first();
    }

    /**
     * Insert data
     */
    public function insert(array $data) {
        if (empty($data)) {
            throw new \InvalidArgumentException("Data cannot be empty for insert operation");
        }
        
        $this->ensureConnection();
        
        try {
            // Validate column names
            foreach (array_keys($data) as $column) {
                $this->validateColumnName($column);
            }
            
            // Encode any array/object values as JSON
            $data = $this->encodeJsonFields($data);
            
            // Escape column names with backticks for MySQL reserved keywords (e.g. row, order)
            $columns = implode(', ', array_map(function($col) {
                return '`' . str_replace('`', '``', $col) . '`';
            }, array_keys($data)));
            $values = implode(', ', array_fill(0, count($data), '?'));
            
            $query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
            
            $startTime = microtime(true);
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute(array_values($data));
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Log slow queries
            if ($executionTime > 1000) {
                $this->logError("Slow insert query", $query, array_values($data));
            }
            
                    // REMOVED: Cache invalidation - no longer needed
            
            $this->resetQueryState();
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError("Insert failed: " . $e->getMessage(), $query ?? null, array_values($data));
            $this->resetQueryState();
            throw new \Exception("Insert operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get last insert ID after insert operation
     * @return int
     */
    public function getLastInsertId(): int {
        $this->ensureConnection();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Insert multiple records at once
     * @param array $data Array of associative arrays
     * @return bool
     */
    public function insertMany(array $data) {
        if (empty($data)) {
            return false;
        }
        
        // Ensure all rows have the same structure
        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        
        foreach ($data as &$row) {
            $row = $this->encodeJsonFields($row);
            // Ensure all rows have the same columns
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    $row[$column] = null;
                }
            }
        }
        
        // Escape column names with backticks for MySQL reserved keywords (e.g. row, order)
        $columnList = implode(', ', array_map(function($col) {
            return '`' . str_replace('`', '``', $col) . '`';
        }, $columns));
        $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
        $values = str_repeat($placeholders . ',', count($data) - 1) . $placeholders;
        
        $query = "INSERT INTO {$this->table} ($columnList) VALUES $values";
        $stmt = $this->db->prepare($query);
        
        // Flatten the data array
        $flatValues = [];
        foreach ($data as $row) {
            foreach ($columns as $column) {
                $flatValues[] = $row[$column];
            }
        }
        
        $result = $stmt->execute($flatValues);
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Insert or update on duplicate key
     * @param array $data Data to insert
     * @param array $updateColumns Columns to update on duplicate (if empty, update all)
     * @return bool
     */
    public function upsert(array $data, array $updateColumns = []) {
        // Encode any array/object values as JSON
        $data = $this->encodeJsonFields($data);
        
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
        
        if (!empty($updateColumns)) {
            $updates = [];
            foreach ($updateColumns as $column) {
                $updates[] = "$column = VALUES($column)";
            }
            $query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        } else {
            // Update all columns except primary key
            $updates = [];
            foreach (array_keys($data) as $column) {
                if ($column !== 'id') { // Assuming 'id' is primary key
                    $updates[] = "$column = VALUES($column)";
                }
            }
            if (!empty($updates)) {
                $query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
            }
        }
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute(array_values($data));
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Update data
     */
    public function update(array $data) {
        if (empty($data)) {
            throw new \InvalidArgumentException("Data cannot be empty for update operation");
        }
        
        $this->ensureConnection();
        
        try {
            // Validate column names
            foreach (array_keys($data) as $column) {
                $this->validateColumnName($column);
            }
            
            // Encode any array/object values as JSON
            $data = $this->encodeJsonFields($data);
            
            $set = [];
            foreach ($data as $column => $value) {
                $set[] = "$column = ?";
            }
            
            $query = "UPDATE {$this->table} SET " . implode(', ', $set);
            if (!empty($this->where)) {
                $query .= ' WHERE ' . $this->buildWhereClause();
            }
            
            $values = array_merge(array_values($data), $this->getWhereValues());
            
            $startTime = microtime(true);
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($values);
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Log slow queries
            if ($executionTime > 1000) {
                $this->logError("Slow update query", $query, $values ?? []);
            }
            
                    // REMOVED: Cache invalidation - no longer needed
            
            $this->resetQueryState();
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError("Update failed: " . $e->getMessage(), $query ?? null, $values ?? []);
            $this->resetQueryState();
            throw new \Exception("Update operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Encode array/object values as JSON and convert ISO 8601 datetime formats
     */
    private function encodeJsonFields(array $data) {
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$key] = json_encode($value);
            } elseif (is_string($value) && $this->isIso8601DateTime($value)) {
                $data[$key] = $this->convertIso8601ToMysqlDateTime($value);
            }
        }
        return $data;
    }
    
    /**
     * Check if a string is in ISO 8601 datetime format
     */
    private function isIso8601DateTime($value) {
        if (!is_string($value)) {
            return false;
        }
        
        // Match ISO 8601 format: YYYY-MM-DDTHH:MM:SS.sssZ or YYYY-MM-DDTHH:MM:SSZ
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z?$/', $value);
    }
    
    /**
     * Convert ISO 8601 datetime format to MySQL datetime format
     */
    private function convertIso8601ToMysqlDateTime($value) {
        try {
            // Create DateTime object from ISO 8601 format
            $dateTime = new \DateTime($value);
            
            // Return in MySQL datetime format (Y-m-d H:i:s)
            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // If conversion fails, log error and return original value
            $this->logError("Failed to convert ISO 8601 datetime: " . $e->getMessage(), null, [$value]);
            return $value;
        }
    }

    /**
     * Delete data
     */
    public function delete() {
        $this->ensureConnection();
        
        // Safety check: prevent delete without WHERE clause
        if (empty($this->where)) {
            throw new \Exception("DELETE without WHERE clause is not allowed for safety. Use raw() method if you really need to delete all records.");
        }
        
        try {
            $query = "DELETE FROM {$this->table}";
            $query .= ' WHERE ' . $this->buildWhereClause();
            
            $bindings = $this->getWhereValues();
            
            $startTime = microtime(true);
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($bindings);
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Log slow queries
            if ($executionTime > 1000) {
                $this->logError("Slow delete query", $query, $bindings);
            }
            
                    // REMOVED: Cache invalidation - no longer needed
            
            $this->resetQueryState();
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError("Delete failed: " . $e->getMessage(), $query ?? null, $bindings ?? []);
            $this->resetQueryState();
            throw new \Exception("Delete operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build query SELECT
     */
    private function buildSelectQuery() {
        $query = "SELECT {$this->select} FROM {$this->table}";

        // Add joins
        foreach ($this->joins as $join) {
            $query .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add where conditions
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
        }

        // Add group by
        if (!empty($this->groupBy)) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Add having
        if (!empty($this->having)) {
            $query .= ' HAVING ' . $this->buildHavingClause();
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $parts = [];
            foreach ($this->orderBy as $order) {
                $parts[] = "{$order[0]} {$order[1]}";
            }
            $query .= ' ORDER BY ' . implode(', ', $parts);
        }

        // Add limit and offset
        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $query .= " OFFSET {$this->offset}";
            }
        }

        return $query;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause() {
        $conditions = [];
        $isFirst = true;
        
        foreach ($this->where as $where) {
            $column = $where[0];
            $operator = $where[1];
            $value = $where[2];
            $boolean = $where[3] ?? 'AND';
            $type = $where[4] ?? 'BASIC';
            
            $condition = '';
            
            // Add boolean operator (AND/OR) except for first condition
            if (!$isFirst) {
                $condition .= " $boolean ";
            }
            
            switch ($type) {
                case 'IN':
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $condition .= "$column IN ($placeholders)";
                    break;
                    
                case 'NOT_IN':
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $condition .= "$column NOT IN ($placeholders)";
                    break;
                    
                case 'BETWEEN':
                    $condition .= "$column BETWEEN ? AND ?";
                    break;
                    
                case 'NOT_BETWEEN':
                    $condition .= "$column NOT BETWEEN ? AND ?";
                    break;
                    
                case 'NULL':
                    $condition .= "$column IS NULL";
                    break;
                    
                case 'NOT_NULL':
                    $condition .= "$column IS NOT NULL";
                    break;
                    
                default: // BASIC
                    $condition .= "$column $operator ?";
                    break;
            }
            
            $conditions[] = $condition;
            $isFirst = false;
        }
        
        return implode('', $conditions);
    }

    /**
     * Get WHERE values for binding
     */
    private function getWhereValues() {
        $values = [];
        
        foreach ($this->where as $where) {
            $value = $where[2];
            $type = $where[4] ?? 'BASIC';
            
            switch ($type) {
                case 'IN':
                case 'NOT_IN':
                    // Flatten array values for IN/NOT IN
                    foreach ($value as $v) {
                        $values[] = $v;
                    }
                    break;
                    
                case 'BETWEEN':
                case 'NOT_BETWEEN':
                    // Add both values for BETWEEN
                    $values[] = $value[0];
                    $values[] = $value[1];
                    break;
                    
                case 'NULL':
                case 'NOT_NULL':
                    // No values needed for NULL checks
                    break;
                    
                default: // BASIC
                    if ($value !== null) {
                        $values[] = $value;
                    }
                    break;
            }
        }
        
        return $values;
    }

    /**
     * Bind WHERE values to statement
     */
    private function bindWhereValues($stmt) {
        $values = $this->getWhereValues();
        foreach ($values as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
    }

    /**
     * Build HAVING clause
     */
    private function buildHavingClause() {
        $conditions = [];
        foreach ($this->having as $having) {
            $conditions[] = "{$having[0]} {$having[1]} {$having[2]}";
        }
        return implode(' AND ', $conditions);
    }
    /**
     * Count records
     * @param string $column Column to count (default: *)
     * @return int
     */
    public function count($column = '*') {
        $originalSelect = $this->select;
        $this->select = "COUNT($column) as count";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Count records by multiple conditions
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param string $countColumn Column to count (default: '*') or 'percentage' for percentage calculation
     * @return array Associative array with condition as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countByConditions('status', ['on', 'off', 'pending']);
     * // Returns: ['on' => 75, 'off' => 25, 'pending' => 10]
     * 
     * $counts = $db->table('orders')->countByConditions('status', ['completed', 'pending'], 'id');
     * // Returns: ['completed' => 150, 'pending' => 45]
     * 
     * $counts = $db->table('orders')->countByConditions('status', ['completed', 'pending'], 'percentage');
     * // Returns: ['completed' => 150, 'pending' => 45, 'percompleted' => '77%', 'perpending' => '23%']
     */
    public function countByConditions($column, array $conditions, $countColumn = '*') {
        if (empty($conditions)) {
            return [];
        }

        $includePercentage = ($countColumn === 'percentage');
        if ($includePercentage) {
            $countColumn = '*'; // Use default count for percentage calculation
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        foreach ($conditions as $condition) {
            $caseStatements[] = "SUM(CASE WHEN $column = ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
        }

        // Add total count for percentage calculation
        if ($includePercentage) {
            $caseStatements[] = "COUNT($countColumn) as total_count";
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($conditions as $condition) {
            $stmt->bindValue($bindIndex++, $condition);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $counts = [];
        if ($result) {
            $totalCount = $includePercentage ? (int)($result['total_count'] ?? 0) : 0;
            
            foreach ($conditions as $condition) {
                $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
                $count = (int)($result[$key] ?? 0);
                $counts[$condition] = $count;
                
                // Add percentage if requested
                if ($includePercentage && $totalCount > 0) {
                    $percentage = round(($count / $totalCount) * 100, 1);
                    $counts['per' . $condition] = $percentage . '%';
                }
            }
        }
        
        return $counts;
    }

    /**
     * Count records by conditions with percentage calculation
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @return array Associative array with counts and percentages
     * 
     * Example:
     * $result = $db->table('orders')->countWithPercentage('status', ['completed', 'pending']);
     * // Returns: [
     * //   'completed' => 150, 
     * //   'pending' => 45, 
     * //   'percompleted' => '77%', 
     * //   'perpending' => '23%',
     * //   'total' => 195
     * // ]
     */
    public function countWithPercentage($column, array $conditions) {
        return $this->countByConditions($column, $conditions, 'percentage');
    }

    /**
     * Get only percentage values for conditions
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param int $decimals Number of decimal places (default: 1)
     * @return array Associative array with condition as key and percentage as value
     * 
     * Example:
     * $percentages = $db->table('orders')->getPercentages('status', ['completed', 'pending']);
     * // Returns: ['completed' => '77%', 'pending' => '23%']
     * 
     * $percentages = $db->table('orders')->getPercentages('status', ['completed', 'pending'], 2);
     * // Returns: ['completed' => '76.92%', 'pending' => '23.08%']
     */
    public function getPercentages($column, array $conditions, $decimals = 1) {
        if (empty($conditions)) {
            return [];
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        foreach ($conditions as $condition) {
            $caseStatements[] = "SUM(CASE WHEN $column = ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
        }
        
        // Add total count
        $caseStatements[] = "COUNT(*) as total_count";

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($conditions as $condition) {
            $stmt->bindValue($bindIndex++, $condition);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result - only percentages
        $percentages = [];
        if ($result) {
            $totalCount = (int)($result['total_count'] ?? 0);
            
            if ($totalCount > 0) {
                foreach ($conditions as $condition) {
                    $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
                    $count = (int)($result[$key] ?? 0);
                    $percentage = round(($count / $totalCount) * 100, $decimals);
                    $percentages[$condition] = $percentage . '%';
                }
            }
        }
        
        return $percentages;
    }

    /**
     * Count records grouped by a specific column
     * @param string $groupColumn Column to group by
     * @param string $countColumn Column to count (default: *)
     * @param array $filterValues Optional array to filter specific values only
     * @return array Associative array with group value as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countByGroup('status');
     * // Returns: ['active' => 100, 'inactive' => 50, 'pending' => 25]
     * 
     * $counts = $db->table('users')->countByGroup('status', '*', ['active', 'inactive']);
     * // Returns: ['active' => 100, 'inactive' => 50]
     */
    public function countByGroup($groupColumn, $countColumn = '*', array $filterValues = []) {
        $originalSelect = $this->select;
        $originalGroupBy = $this->groupBy;
        
        $this->select = "$groupColumn, COUNT($countColumn) as count";
        $this->groupBy = [$groupColumn];
        
        // Add filter for specific values if provided
        if (!empty($filterValues)) {
            $this->whereIn($groupColumn, $filterValues);
        }
        
        $results = $this->get();
        
        // Restore original state
        $this->select = $originalSelect;
        $this->groupBy = $originalGroupBy;
        
        // Format result
        $counts = [];
        foreach ($results as $row) {
            $counts[$row[$groupColumn]] = (int)$row['count'];
        }
        
        return $counts;
    }

    /**
     * Count records with multiple WHERE conditions in one query
     * @param array $whereConditions Array of where conditions
     * @param string $countColumn Column to count (default: *)
     * @return array Associative array with condition label as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countMultipleWhere([
     *     'active_users' => ['status', '=', 'active'],
     *     'premium_users' => ['plan', '=', 'premium'],
     *     'recent_users' => ['created_at', '>=', '2024-01-01']
     * ]);
     * // Returns: ['active_users' => 150, 'premium_users' => 75, 'recent_users' => 200]
     */
    public function countMultipleWhere(array $whereConditions, $countColumn = '*') {
        if (empty($whereConditions)) {
            return [];
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        $bindings = [];
        
        foreach ($whereConditions as $label => $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $caseStatements[] = "SUM(CASE WHEN $column $operator ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $label);
            $bindings[] = $value;
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($bindings as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $counts = [];
        if ($result) {
            foreach ($whereConditions as $label => $condition) {
                $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $label);
                $counts[$label] = (int)($result[$key] ?? 0);
            }
        }
        
        return $counts;
    }

    /**
     * Quick count for common status-based conditions
     * @param string $statusColumn Column name for status (default: 'status')
     * @param array $statusValues Array of status values to count
     * @return array Associative array with status as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->quickStatusCount();
     * // Returns: ['active' => 100, 'inactive' => 50] (default status values)
     * 
     * $counts = $db->table('orders')->quickStatusCount('order_status', ['pending', 'completed', 'cancelled']);
     * // Returns: ['pending' => 25, 'completed' => 150, 'cancelled' => 10]
     */
    public function quickStatusCount($statusColumn = 'status', array $statusValues = ['active', 'inactive']) {
        return $this->countByConditions($statusColumn, $statusValues);
    }

    /**
     * Count records by multiple columns (aggregate functions)
     * @param array $columns Array of columns to count/sum
     * @param string $operation Operation to perform: 'count', 'sum', 'avg', 'max', 'min', 'percentage' (default: 'count')
     * @param bool $includePercentage Whether to include percentage calculation for sum operations
     * @return array Associative array with column name as key and result as value
     * 
     * Example:
     * $counts = $db->table('posts')->countByColumn(['views', 'shares', 'likes']);
     * // Returns: ['views' => 1500, 'shares' => 250, 'likes' => 890] (COUNT of non-null values)
     * 
     * $sums = $db->table('posts')->countByColumn(['views', 'shares'], 'sum');
     * // Returns: ['views' => 15000, 'shares' => 2500] (SUM of all values)
     * 
     * $averages = $db->table('products')->countByColumn(['price', 'rating'], 'avg');
     * // Returns: ['price' => 25.50, 'rating' => 4.2] (AVERAGE values)
     * 
     * $sumsWithPercentage = $db->table('posts')->countByColumn(['views', 'shares'], 'sum', true);
     * // Returns: ['views' => 15000, 'shares' => 2500, 'perviews' => '85.7%', 'pershares' => '14.3%']
     * 
     * $percentageOnly = $db->table('posts')->countByColumn(['views', 'shares'], 'percentage');
     * // Returns: ['views' => '85.7%', 'shares' => '14.3%'] (percentage of total sum)
     */
    public function countByColumn(array $columns, $operation = 'count', $includePercentage = false) {
        if (empty($columns)) {
            return [];
        }

        $validOperations = ['count', 'sum', 'avg', 'max', 'min', 'percentage'];
        $operation = strtolower($operation);
        
        if (!in_array($operation, $validOperations)) {
            throw new \InvalidArgumentException("Invalid operation. Allowed: " . implode(', ', $validOperations));
        }

        // Handle percentage operation
        $isPercentageOnly = ($operation === 'percentage');
        if ($isPercentageOnly) {
            $operation = 'sum'; // Use sum for percentage calculation
            $includePercentage = true;
        }

        // Build aggregate statements for each column
        $aggregateStatements = [];
        foreach ($columns as $column) {
            $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
            
            switch ($operation) {
                case 'count':
                    // Count non-null values
                    $aggregateStatements[] = "COUNT($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'sum':
                    $aggregateStatements[] = "SUM($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'avg':
                    $aggregateStatements[] = "AVG($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'max':
                    $aggregateStatements[] = "MAX($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'min':
                    $aggregateStatements[] = "MIN($column) as {$operation}_{$cleanColumn}";
                    break;
            }
        }

        // Add total sum for percentage calculation
        if ($includePercentage && $operation === 'sum') {
            $totalSumColumns = [];
            foreach ($columns as $column) {
                $totalSumColumns[] = "COALESCE($column, 0)";
            }
            $aggregateStatements[] = "SUM(" . implode(' + ', $totalSumColumns) . ") as total_sum";
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $aggregateStatements);

        // Execute query
        $result = $this->first();
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $results = [];
        if ($result) {
            $totalSum = 0;
            if ($includePercentage && $operation === 'sum') {
                $totalSum = (float)($result['total_sum'] ?? 0);
            }

            foreach ($columns as $column) {
                $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
                $key = "{$operation}_{$cleanColumn}";
                
                $value = $result[$key] ?? 0;
                
                // Convert to appropriate type based on operation
                switch ($operation) {
                    case 'count':
                        $columnValue = (int)$value;
                        break;
                    case 'sum':
                        $columnValue = is_numeric($value) ? (float)$value : 0;
                        break;
                    case 'avg':
                        $columnValue = is_numeric($value) ? round((float)$value, 2) : 0;
                        break;
                    case 'max':
                    case 'min':
                        $columnValue = $value;
                        break;
                }

                // Add main value (unless percentage only)
                if (!$isPercentageOnly) {
                    $results[$column] = $columnValue;
                }
                
                // Add percentage if requested
                if ($includePercentage && $operation === 'sum' && $totalSum > 0) {
                    $percentage = round(($columnValue / $totalSum) * 100, 1);
                    $results[$isPercentageOnly ? $column : 'per' . $column] = $percentage . '%';
                }
            }
        }
        
        return $results;
    }

    /**
     * Count non-null values for multiple columns (shortcut for countByColumn with 'count')
     * @param array $columns Array of columns to count non-null values
     * @return array Associative array with column name as key and count as value
     * 
     * Example:
     * $counts = $db->table('posts')->countColumns(['views', 'shares', 'likes']);
     * // Returns: ['views' => 150, 'shares' => 120, 'likes' => 180] (count of non-null values)
     */
    public function countColumns(array $columns) {
        return $this->countByColumn($columns, 'count');
    }

    /**
     * Sum values for multiple columns (shortcut for countByColumn with 'sum')
     * @param array $columns Array of columns to sum
     * @return array Associative array with column name as key and sum as value
     * 
     * Example:
     * $sums = $db->table('posts')->sumColumns(['views', 'shares', 'likes']);
     * // Returns: ['views' => 15000, 'shares' => 2500, 'likes' => 8900] (sum of all values)
     */
    public function sumColumns(array $columns) {
        return $this->countByColumn($columns, 'sum');
    }

    /**
     * Get average values for multiple columns (shortcut for countByColumn with 'avg')
     * @param array $columns Array of columns to average
     * @return array Associative array with column name as key and average as value
     * 
     * Example:
     * $averages = $db->table('products')->avgColumns(['price', 'rating', 'discount']);
     * // Returns: ['price' => 25.50, 'rating' => 4.2, 'discount' => 15.75] (average values)
     */
    public function avgColumns(array $columns) {
        return $this->countByColumn($columns, 'avg');
    }

    /**
     * Sum values of a column
     * @param string $column
     * @return float|int
     */
    public function sum($column) {
        $originalSelect = $this->select;
        $this->select = "SUM($column) as sum";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? ($result['sum'] ?? 0) : 0;
    }

    /**
     * Average value of a column
     * @param string $column
     * @return float
     */
    public function avg($column) {
        $originalSelect = $this->select;
        $this->select = "AVG($column) as avg";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? (float)($result['avg'] ?? 0) : 0;
    }

    /**
     * Maximum value of a column
     * @param string $column
     * @return mixed
     */
    public function max($column) {
        $originalSelect = $this->select;
        $this->select = "MAX($column) as max";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? $result['max'] : null;
    }

    /**
     * Minimum value of a column
     * @param string $column
     * @return mixed
     */
    public function min($column) {
        $originalSelect = $this->select;
        $this->select = "MIN($column) as min";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? $result['min'] : null;
    }

    /**
     * Fungsi untuk mendapatkan data dengan pagination (legacy method)
     * @param int $page Halaman saat ini
     * @param int $perPage Jumlah item per halaman
     * @return array ['data' => array, 'total' => int, 'last_page' => int, 'current_page' => int]
     */
    public function countPaginate($page = 1, $perPage = 10) {
        // Hitung total data
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($this->where)) {
            $countQuery .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($countQuery);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Hitung last page
        $lastPage = ceil($total / $perPage);
        
        // Set limit dan offset untuk pagination
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        
        // Ambil data
        $data = $this->get();
        
        return [
            'data' => $data,
            'total' => (int) $total,
            'last_page' => $lastPage,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage
        ];
    }
    /**
     * Fungsi untuk mendapatkan data dengan pagination
     * @param int $page Halaman saat ini
     * @param int $perPage Jumlah item per halaman
     * @return array ['data' => array, 'total' => int, 'last_page' => int, 'current_page' => int]
     */
    public function paginate($page = 1, $perPage = 10) {
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($this->where)) {
            $countQuery .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($countQuery);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        $lastPage = ceil($total / $perPage);
        
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        
        $data = $this->get();
        
        return [
            'data' => $data,
            'total' => (int) $total,
            'last_page' => $lastPage,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage
        ];
    }

    /**
     * Get pagination info only without fetching actual data
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return array ['total' => int, 'last_page' => int, 'current_page' => int, 'per_page' => int]
     */
    public function paginateInfo($page = 1, $perPage = 10) {
        $this->ensureConnection();
        
        try {
            // Build count query
            $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
            if (!empty($this->where)) {
                $countQuery .= ' WHERE ' . $this->buildWhereClause();
            }
            
            // Execute count query
            $stmt = $this->db->prepare($countQuery);
            $this->bindWhereValues($stmt);
            $stmt->execute();
            $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

            // Calculate pagination info
            $lastPage = $total > 0 ? ceil($total / $perPage) : 1;
            $currentPage = max(1, min($page, $lastPage)); // Ensure page is within valid range
            
            $this->resetQueryState();
            
            return [
                'total' => $total,
                'last_page' => $lastPage,
                'current_page' => $currentPage,
                'per_page' => (int) $perPage,
                'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
                'to' => min($currentPage * $perPage, $total),
                'has_pages' => $lastPage > 1,
                'has_next' => $currentPage < $lastPage,
                'has_previous' => $currentPage > 1
            ];
            
        } catch (\Exception $e) {
            $this->logError("Pagination info failed: " . $e->getMessage(), $countQuery ?? null, $this->getWhereValues());
            $this->resetQueryState();
            throw new \Exception("Failed to get pagination info: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute raw SQL query
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function raw($sql, array $bindings = []) {
        $this->ensureConnection();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the SQL query string (for debugging)
     * @return string
     */
    public function toSql() {
        return $this->buildSelectQuery();
    }

    /**
     * Get the bindings array (for debugging)
     * @return array
     */
    public function getBindings() {
        return $this->getWhereValues();
    }

    /**
     * Execute transaction
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function transaction($callback) {
        $this->ensureConnection();
        $this->db->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Check if record exists
     * @return bool
     */
    public function exists() {
        return $this->count() > 0;
    }

    /**
     * Get first record or throw exception if not found
     * @return array
     * @throws Exception
     */
    public function firstOrFail() {
        $result = $this->first();
        if (!$result) {
            throw new \Exception("No records found");
        }
        return $result;
    }

    /**
     * Get value of a single column
     * @param string $column
     * @return mixed
     */
    public function value($column) {
        $result = $this->first([$column]);
        return $result ? $result[$column] : null;
    }

    /**
     * Get array of values from a single column
     * @param string $column
     * @param string|null $key Optional key column
     * @return array
     */
    public function pluck($column, $key = null) {
        $columns = [$column];
        if ($key) {
            $columns[] = $key;
        }
        
        $results = $this->select($columns)->get();
        
        if ($key) {
            $plucked = [];
            foreach ($results as $result) {
                $plucked[$result[$key]] = $result[$column];
            }
            return $plucked;
        }
        
        return array_column($results, $column);
    }

    /**
     * Add ORDER BY RAND() for random ordering
     * @return $this
     */
    public function inRandomOrder() {
        return $this->orderBy('RAND()');
    }

    /**
     * Order by latest (descending by created_at or specified column)
     * @param string $column
     * @return $this
     */
    public function latest($column = 'created_at') {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by oldest (ascending by created_at or specified column)
     * @param string $column
     * @return $this
     */
    public function oldest($column = 'created_at') {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Alias for limit()
     * @param int $value
     * @return $this
     */
    public function take($value) {
        return $this->limit($value);
    }

    /**
     * Alias for offset()
     * @param int $value
     * @return $this
     */
    public function skip($value) {
        return $this->offset($value);
    }

    /**
     * Get distinct values
     * @param string|array $columns
     * @return $this
     */
    public function distinct($columns = null) {
        if ($columns) {
            $columns = is_array($columns) ? implode(', ', $columns) : $columns;
            $this->select = "DISTINCT $columns";
        } else {
            $this->select = "DISTINCT " . $this->select;
        }
        return $this;
    }

    /**
     * Add UNION query
     * @param NexaBrif $query
     * @param bool $all
     * @return $this
     */
    public function union($query, $all = false) {
        // This is a simplified implementation
        // In a full implementation, you'd need to handle this in buildSelectQuery()
        $unionType = $all ? 'UNION ALL' : 'UNION';
        $this->unions[] = ['query' => $query, 'type' => $unionType];
        return $this;
    }

    /**
     * Add UNION ALL query
     * @param NexaBrif $query
     * @return $this
     */
    public function unionAll($query) {
        return $this->union($query, true);
    }

    /**
     * Increment a column value
     * @param string $column
     * @param int $amount
     * @param array $extra Additional columns to update
     * @return bool
     */
    public function increment($column, $amount = 1, array $extra = []) {
        $updates = ["$column = $column + ?"];
        $bindings = [$amount];
        
        foreach ($extra as $key => $value) {
            $updates[] = "$key = ?";
            $bindings[] = $value;
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $updates);
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
            $bindings = array_merge($bindings, $this->getWhereValues());
        }
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($bindings);
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Decrement a column value
     * @param string $column
     * @param int $amount
     * @param array $extra Additional columns to update
     * @return bool
     */
    public function decrement($column, $amount = 1, array $extra = []) {
        return $this->increment($column, -$amount, $extra);
    }

    /**
     * Debug: Print the SQL query and bindings
     * @return $this
     */
    public function dd() {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        die();
    }

    /**
     * Debug: Dump the SQL query and bindings without stopping execution
     * @return $this
     */
    public function dump() {
        echo "SQL: " . $this->toSql() . "\n";   
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        return $this;
    }

    /**
     * Sum values for multiple columns with percentage calculation
     * @param array $columns Array of columns to sum with percentage
     * @return array Associative array with column sums and percentages
     * 
     * Example:
     * $result = $db->table('posts')->sumColumnsWithPercentage(['views', 'shares', 'likes']);
     * // Returns: [
     * //   'views' => 15000, 'shares' => 2500, 'likes' => 8900,
     * //   'perviews' => '57.7%', 'pershares' => '9.6%', 'perlikes' => '32.7%'
     * // ]
     */
    public function sumColumnsWithPercentage(array $columns) {
        return $this->countByColumn($columns, 'sum', true);
    }

    /**
     * Get percentage distribution for multiple columns (based on sum)
     * @param array $columns Array of columns to calculate percentage
     * @return array Associative array with column name as key and percentage as value
     * 
     * Example:
     * $percentages = $db->table('posts')->getColumnPercentages(['views', 'shares', 'likes']);
     * // Returns: ['views' => '57.7%', 'shares' => '9.6%', 'likes' => '32.7%']
     */
    public function getColumnPercentages(array $columns) {
        return $this->countByColumn($columns, 'percentage');
    }

    /**
     * Get list of all tables in the database
     * @return array
     */
            public function showTables() {
                $this->ensureConnection();
                // Ambil semua tabel dari database
                $stmt = $this->db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(\PDO::FETCH_NUM);

                // Array hasil dengan key stabil
                $stableKeys = [];

                foreach ($tables as $table) {
                    $tableName = $table[0]; // nama tabel
                    $uniqueKey = hexdec(substr(md5($tableName), 0, 12)); // kode unik stabil berupa angka
                    $stableKeys[$uniqueKey] = $tableName;       // simpan ke hasil
                }
                return $stableKeys;
            }



    /**
     * Show specific tables by their index positions with optional aliases
     * @param array $indexes Array of index positions to show. Can be:
     *                      - Numeric array: [8, 2, 6] 
     *                      - Associative array: [3 => 'data Penduduk', 6 => 'data Kemiskinan']
     * @return array Array of selected table names or aliases
     */
    public function showTablesRet(array $indexes = []) {
        if (empty($indexes)) {
            return [];
        }

        // Get all tables first
        $allTables = $this->showTables();
        $selectedTables = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $selectedTables[$alias] = $allTables[$index];
                }
            }
        } else {
            // Handle numeric array (original behavior)
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $selectedTables[] = $allTables[$index];
                }
            }
        }

        return $selectedTables;
    }

    /**
     * Show specific tables info by their index positions with detailed information
     * @param array $indexes Array of index positions to show. Can be:
     *                      - Numeric array: [8, 2, 6] 
     *                      - Associative array: [3 => 'data Penduduk', 6 => 'data Kemiskinan']
     * @return array Array of selected tables with detailed info
     */
    public function showTablesRetInfo(array $indexes = []) {
        if (empty($indexes)) {
            return [];
        }

        // Get all tables info first
        $allTablesInfo = $this->getTablesInfo();
        $selectedTablesInfo = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTablesInfo[$index])) {
                    $tableInfo = $allTablesInfo[$index];
                    $tableInfo['alias'] = $alias; // Add alias to table info
                    $selectedTablesInfo[$alias] = $tableInfo;
                }
            }
        } else {
            // Handle numeric array (original behavior)
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTablesInfo[$index])) {
                    $selectedTablesInfo[] = $allTablesInfo[$index];
                }
            }
        }

        return $selectedTablesInfo;
    }

  

    /**
     * Display specific tables raw data without formatting
     * @param array $indexes Array of index positions to show. Can be:
     *                      - Numeric array: [8, 2, 6] 
     *                      - Associative array: [3 => 'data Penduduk', 6 => 'data Kemiskinan']
     * @param bool $withPre Whether to include HTML pre tags
     * @param string $format Output format: 'array', 'json', 'clean', 'var_dump'
     * @return void
     */
    public function showTablesRetRaw(array $indexes = [], $withPre = false, $format = 'array') {
        // Return data array instead of echoing
        return $this->showTablesRetInfo($indexes);
    }

    /**
     * Get actual data/records from specific tables by their index positions
     * @param array $indexes Array of index positions to show. Can be:
     *                      - Numeric array: [8, 2, 6] 
     *                      - Associative array: [3 => 'member', 6 => 'data Kemiskinan']
     * @param int $limit Maximum number of records to fetch per table (default: 10)
     * @param array $columns Specific columns to select (empty for all columns)
     * @param string $orderBy Column to order by (optional)
     * @param string $orderDirection Order direction: ASC or DESC (default: ASC)
     * @param array $whereConditions WHERE conditions array for filtering data
     * @param array|string $groupBy GROUP BY columns for data grouping
     * @param int $offset Number of records to skip for pagination (default: 0)
     * @return array Array of actual table data/records
     */
    public function getTablesRetData(array $indexes = [], $limit = 10, array $columns = [], $orderBy = null, $orderDirection = 'ASC', $whereConditions = [], $groupBy = [], $offset = 0) {
        if (empty($indexes)) {
            return [];
        }

        // Get all tables first
        $allTables = $this->showTables();
        $tablesData = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $data = $this->getTableData($tableName, $limit, $columns, $orderBy, $orderDirection, $whereConditions, $groupBy, $offset);
                    $tablesData= [
                        // 'table_name' => $tableName,
                        // 'alias' => $alias,
                        ...$data
                    ];
                }
            }
        } else {
            // Handle numeric array
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $data = $this->getTableData($tableName, $limit, $columns, $orderBy, $orderDirection, $whereConditions, $groupBy, $offset);
                    $tablesData[] = [
                        'table_name' => $tableName,
                        'data' => $data
                    ];
                }
            }
        }

        return $tablesData;
    }

    /**
     * Helper method to get data from a specific table
     * @param string $tableName Name of the table
     * @param int $limit Number of records to fetch
     * @param array $columns Specific columns to select (empty for all columns)
     * @param string $orderBy Column to order by (optional)
     * @param string $orderDirection Order direction: ASC or DESC (default: ASC)
     * @param array $whereConditions WHERE conditions for filtering data
     * @param array|string $groupBy GROUP BY columns for data grouping
     * @param int $offset Number of records to skip for pagination
     * @return array Table data
     */
    private function getTableData($tableName, $limit = 10, array $columns = [], $orderBy = null, $orderDirection = 'DESC', $whereConditions = [], $groupBy = [], $offset = 0) {
        try {
            $this->ensureConnection();
            $this->validateTableName($tableName);
            
            // Build column list
            if (empty($columns)) {
                $columnList = '*';
            } else {
                // Validate each column name
                foreach ($columns as $column) {
                    $this->validateColumnName($column);
                }
                $columnList = '`' . implode('`, `', $columns) . '`';
            }
            
            // Build WHERE clause
            $whereClause = '';
            $bindings = [];
            if (!empty($whereConditions)) {
                $whereParts = [];
                foreach ($whereConditions as $column => $condition) {
                    $this->validateColumnName($column);
                    
                    if (is_array($condition)) {
                        // Handle IN clause: ['status' => ['aktif', 'tidak']]
                        $placeholders = str_repeat('?,', count($condition) - 1) . '?';
                        $whereParts[] = "`{$column}` IN ({$placeholders})";
                        $bindings = array_merge($bindings, $condition);
                    } else {
                        // Handle simple equality: ['status' => 'aktif']
                        $whereParts[] = "`{$column}` = ?";
                        $bindings[] = $condition;
                    }
                }
                $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
            }
            
            // Build GROUP BY clause
            $groupByClause = '';
            if (!empty($groupBy)) {
                $groupByColumns = [];
                
                if (is_array($groupBy)) {
                    // Handle array of columns: ['status', 'type']
                    foreach ($groupBy as $column) {
                        $this->validateColumnName($column);
                        $groupByColumns[] = "`{$column}`";
                    }
                } else {
                    // Handle single column: 'status'
                    $this->validateColumnName($groupBy);
                    $groupByColumns[] = "`{$groupBy}`";
                }
                
                $groupByClause = ' GROUP BY ' . implode(', ', $groupByColumns);
                
                // When using GROUP BY, we need to modify column selection
                // to include aggregate functions or group by columns
                if (empty($columns)) {
                    // If no specific columns, select group by columns and count
                    $groupByColumnsList = implode(', ', $groupByColumns);
                    $columnList = "{$groupByColumnsList}, COUNT(*) as count";
                } else {
                    // Validate that selected columns are either in GROUP BY or are aggregate functions
                    $validatedColumns = [];
                    foreach ($columns as $column) {
                        if (strpos($column, '(') !== false) {
                            // This looks like an aggregate function, allow it
                            $validatedColumns[] = $column;
                        } else {
                            // Regular column, validate it
                            $this->validateColumnName($column);
                            $validatedColumns[] = "`{$column}`";
                        }
                    }
                    $columnList = implode(', ', $validatedColumns);
                }
            }
            
            // Build ORDER BY clause
            $orderClause = '';
            if (!empty($orderBy)) {
                $this->validateColumnName($orderBy);
                $orderDirection = strtoupper(trim($orderDirection));
                
                // Validate order direction
                if (!in_array($orderDirection, ['ASC', 'DESC'])) {
                    throw new \InvalidArgumentException("Order direction must be ASC or DESC");
                }
                
                $orderClause = " ORDER BY `{$orderBy}` {$orderDirection}";
            }
            
            // Build LIMIT and OFFSET clause
            $limitClause = " LIMIT " . (int)$limit;
            if ($offset > 0) {
                $limitClause .= " OFFSET " . (int)$offset;
            }
            
            $query = "SELECT {$columnList} FROM `{$tableName}`{$whereClause}{$groupByClause}{$orderClause}{$limitClause}";
            
            if (!empty($bindings)) {
                $stmt = $this->db->prepare($query);
                $stmt->execute($bindings);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->db->query($query);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Exception $e) {
            $this->logError("Error fetching data from table: " . $tableName, $query ?? '', []);
            return [];
        }
    }

    /**
     * Display actual data/records from specific tables
     * @param array $indexes Array of index positions to show
     * @param int $limit Maximum number of records per table (default: 10) - ignored if $perPage is set
     * @param array $whereConditions WHERE conditions array. Examples:
     *                               - Simple equality: ['status' => 'aktif']
     *                               - IN clause: ['status' => ['aktif', 'tidak']]
     *                               - Multiple conditions: ['status' => 'aktif', 'type' => 'premium']
     * @param string $format Output format: 'array', 'json', 'table'
     * @param array $columns Specific columns to select (empty for all columns)
     * @param string $orderBy Column to order by (optional)
     * @param string $orderDirection Order direction: ASC or DESC (default: ASC)
     * @param array|string $groupBy GROUP BY columns. Examples:
     *                              - Single column: 'status'
     *                              - Multiple columns: ['status', 'type']
     *                              - With aggregate: use $columns = ['status', 'COUNT(*) as total']
     * @param int $page Page number for pagination (default: 1) - only used if $perPage is set
     * @param int|null $perPage Records per page for pagination (default: null = no pagination)
     *                          If set, this overrides $limit and enables pagination
     * @return array
     */
    public function showTablesRetData(array $indexes = [], $limit = 10, $whereConditions = [], $format = 'array', array $columns = [], $orderBy = null, $orderDirection = 'DESC', $groupBy = [], $page = 1, $perPage = null) {
        // Handle pagination
        if ($perPage !== null) {
            $offset = ($page - 1) * $perPage;
            $actualLimit = $perPage;
        } else {
            $offset = 0;
            $actualLimit = $limit;
        }
        
        // Return data array instead of echoing
        return $this->getTablesRetData($indexes, $actualLimit, $columns, $orderBy, $orderDirection, $whereConditions, $groupBy, $offset);
    }

    /**
     * Get grouped data from specific tables by their index positions
     * @param array $indexes Array of index positions to show. Can be:
     *                      - Numeric array: [8, 2, 6] 
     *                      - Associative array: [3 => 'member', 6 => 'data Kemiskinan']
     * @param string $groupByField Field/column to group by
     * @param string $aggregateFunction Aggregate function: 'count', 'sum', 'avg', 'max', 'min' (default: 'count')
     * @param string $aggregateField Field for aggregation (required for sum, avg, max, min)
     * @param int $limit Maximum number of groups to fetch (default: 100)
     * @param string $orderBy Order by: 'group' or 'aggregate' (default: 'aggregate')
     * @param string $orderDirection Order direction: ASC or DESC (default: DESC)
     * @return array Array of grouped table data
     * 
     * Example:
     * // Group by status and count records
     * $data = $model->getTablesRetGroup([25 => 'Member'], 'status');
     * 
     * // Group by category and sum amount
     * $data = $model->getTablesRetGroup([10 => 'Sales'], 'category', 'sum', 'amount');
     * 
     * // Group by department and get average salary
     * $data = $model->getTablesRetGroup([5 => 'Employees'], 'department', 'avg', 'salary');
     */
    public function getTablesRetGroup(array $indexes = [], $groupByField = '', $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC') {
        if (empty($indexes)) {
            return [];
        }

        if (empty($groupByField)) {
            throw new \InvalidArgumentException("Group by field is required");
        }

        // Validate aggregate function
        $validFunctions = ['count', 'sum', 'avg', 'max', 'min'];
        $aggregateFunction = strtolower($aggregateFunction);
        
        if (!in_array($aggregateFunction, $validFunctions)) {
            throw new \InvalidArgumentException("Invalid aggregate function. Allowed: " . implode(', ', $validFunctions));
        }

        // For sum, avg, max, min - aggregateField is required
        if (in_array($aggregateFunction, ['sum', 'avg', 'max', 'min']) && empty($aggregateField)) {
            throw new \InvalidArgumentException("Aggregate field is required for {$aggregateFunction} function");
        }

        // Get all tables first
        $allTables = $this->showTables();
        $tablesGroupedData = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedData($tableName, $groupByField, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[$alias] = [
                        'table_name' => $tableName,
                        'alias' => $alias,
                        'group_by' => $groupByField,
                        'aggregate_function' => $aggregateFunction,
                        'aggregate_field' => $aggregateField,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        } else {
            // Handle numeric array
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedData($tableName, $groupByField, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[] = [
                        'table_name' => $tableName,
                        'group_by' => $groupByField,
                        'aggregate_function' => $aggregateFunction,
                        'aggregate_field' => $aggregateField,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        }

        return $tablesGroupedData;
    }

    /**
     * Helper method to get grouped data from a specific table
     * @param string $tableName Name of the table
     * @param string $groupByField Field to group by
     * @param string $aggregateFunction Aggregate function
     * @param string $aggregateField Field for aggregation
     * @param int $limit Number of groups to fetch
     * @param string $orderBy Order by group or aggregate
     * @param string $orderDirection Order direction
     * @return array Grouped data
     */
    private function getTableGroupedData($tableName, $groupByField, $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC') {
        try {
            $this->ensureConnection();
            $this->validateTableName($tableName);
            $this->validateColumnName($groupByField);
            
            // Build aggregate expression
            $aggregateExpression = '';
            switch ($aggregateFunction) {
                case 'count':
                    $aggregateExpression = 'COUNT(*)';
                    break;
                case 'sum':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "SUM(`{$aggregateField}`)";
                    break;
                case 'avg':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "AVG(`{$aggregateField}`)";
                    break;
                case 'max':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "MAX(`{$aggregateField}`)";
                    break;
                case 'min':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "MIN(`{$aggregateField}`)";
                    break;
            }
            
            // Build ORDER BY clause
            $orderByClause = '';
            if ($orderBy === 'group') {
                $orderByClause = " ORDER BY `{$groupByField}` {$orderDirection}";
            } else {
                $orderByClause = " ORDER BY aggregate_value {$orderDirection}";
            }
            
            $query = "SELECT `{$groupByField}`, {$aggregateExpression} as aggregate_value 
                     FROM `{$tableName}` 
                     GROUP BY `{$groupByField}`
                     {$orderByClause}
                     LIMIT " . (int)$limit;
            
            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calculate total for percentage
            $total = 0;
            foreach ($results as $row) {
                $total += $row['aggregate_value'];
            }
            
            // Format results with percentage
            $formattedResults = [];
            foreach ($results as $row) {
                $value = $row['aggregate_value'];
                $percent = $total > 0 ? round(($value / $total) * 100, 2) : 0;
                $progressBar = $total > 0 ? round(($value / $total) * 100) . '%' : '0%';
                
                $formattedResults[] = [
                    $groupByField => $row[$groupByField],
                    $aggregateFunction => $value,
                    'percent' => $percent.'%',
                    'progress_bar' => $progressBar
                ];
            }
            
            return $formattedResults;
            
        } catch (\Exception $e) {
            $this->logError("Error getting grouped data from table: " . $tableName, $query ?? '', []);
            return [];
        }
    }

    /**
     * Display grouped data from specific tables (returns data array)
     * @param array $indexes Array of index positions to show
     * @param string|array $groupByField Field/column to group by (string for single field, array for multiple fields)
     * @param string $aggregateFunction Aggregate function: 'count', 'sum', 'avg', 'max', 'min'
     * @param string $aggregateField Field for aggregation
     * @param int $limit Maximum number of groups (default: 100)
     * @param string $orderBy Order by: 'group' or 'aggregate' (default: 'aggregate')
     * @param string $orderDirection Order direction: ASC or DESC (default: DESC)
     * @param bool $withPre Deprecated parameter (kept for compatibility)
     * @param string $format Deprecated parameter (kept for compatibility)
     * @param bool $simpleOutput Whether to return simple array without metadata (default: true)
     * @return array Grouped data array
     * 
     * Example:
     * // Single field grouping (with automatic percent & progress_bar)
     * $data = $model->showTablesRetGroup([25 => 'Member'], 'status');
     * // Result: [['status' => 'active', 'count' => 150, 'percent' => 72.46, 'progress_bar' => '72%'], ...]
     * 
     * // Multi-field grouping (with automatic percent & progress_bar)
     * $data = $model->showTablesRetGroup([25 => 'Member'], ['status', 'category']);
     * // Result: [['status' => 'active', 'category' => 'premium', 'count' => 85, 'percent' => 41.06, 'progress_bar' => '41%'], ...]
     * 
     * // Group by category and sum amount (with automatic percent & progress_bar)
     * $data = $model->showTablesRetGroup([10 => 'Sales'], 'category', 'sum', 'amount', 10);
     * // Result: [['category' => 'Electronics', 'sum' => 45000, 'percent' => 52.94, 'progress_bar' => '53%'], ...]
     * 
     * // Get full metadata (set simpleOutput = false)
     * $data = $model->showTablesRetGroup([25 => 'Member'], 'status', 'count', '', 100, 'aggregate', 'DESC', false, 'array', false);
     */
    public function showTablesRetGroup(array $indexes = [], $groupByField = '', $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC', $withPre = false, $format = 'array', $simpleOutput = true) {
        // Check if groupByField is array (multi-field) or string (single field)
        if (is_array($groupByField)) {
            // Multi-field grouping
            $result = $this->getTablesRetGroupMultiField($indexes, $groupByField, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
        } else {
            // Single field grouping
            $result = $this->getTablesRetGroup($indexes, $groupByField, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
        }
        
        // Default behavior: return simple output (grouped_data only)
        if ($simpleOutput && !empty($result)) {
            $firstTable = reset($result);
            return $firstTable['grouped_data'] ?? [];
        }
        
        return $result;
    }

    /**
     * Get grouped data by multiple fields from specific tables
     * @param array $indexes Array of index positions to show
     * @param array $groupByFields Array of fields to group by: ['field1', 'field2']
     * @param string $aggregateFunction Aggregate function: 'count', 'sum', 'avg', 'max', 'min'
     * @param string $aggregateField Field for aggregation
     * @param int $limit Maximum number of groups (default: 100)
     * @param string $orderBy Order by: 'group' or 'aggregate' (default: 'aggregate')
     * @param string $orderDirection Order direction: ASC or DESC (default: DESC)
     * @return array Array of grouped table data
     * 
     * Example:
     * // Group by category and status
     * $data = $model->getTablesRetGroupMultiField([10 => 'Sales'], ['category', 'status']);
     * 
     * // Group by department and position, sum salary
     * $data = $model->getTablesRetGroupMultiField([5 => 'Employees'], ['department', 'position'], 'sum', 'salary');
     */
    public function getTablesRetGroupMultiField(array $indexes = [], array $groupByFields = [], $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC') {
        if (empty($indexes)) {
            return [];
        }

        if (empty($groupByFields)) {
            throw new \InvalidArgumentException("Group by fields cannot be empty");
        }

        // Validate aggregate function
        $validFunctions = ['count', 'sum', 'avg', 'max', 'min'];
        $aggregateFunction = strtolower($aggregateFunction);
        
        if (!in_array($aggregateFunction, $validFunctions)) {
            throw new \InvalidArgumentException("Invalid aggregate function. Allowed: " . implode(', ', $validFunctions));
        }

        // For sum, avg, max, min - aggregateField is required
        if (in_array($aggregateFunction, ['sum', 'avg', 'max', 'min']) && empty($aggregateField)) {
            throw new \InvalidArgumentException("Aggregate field is required for {$aggregateFunction} function");
        }

        // Get all tables first
        $allTables = $this->showTables();
        $tablesGroupedData = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedDataMultiField($tableName, $groupByFields, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[$alias] = [
                        'table_name' => $tableName,
                        'alias' => $alias,
                        'group_by' => $groupByFields,
                        'aggregate_function' => $aggregateFunction,
                        'aggregate_field' => $aggregateField,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        } else {
            // Handle numeric array
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedDataMultiField($tableName, $groupByFields, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[] = [
                        'table_name' => $tableName,
                        'group_by' => $groupByFields,
                        'aggregate_function' => $aggregateFunction,
                        'aggregate_field' => $aggregateField,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        }

        return $tablesGroupedData;
    }

    /**
     * Helper method to get grouped data by multiple fields from a specific table
     * @param string $tableName Name of the table
     * @param array $groupByFields Array of fields to group by
     * @param string $aggregateFunction Aggregate function
     * @param string $aggregateField Field for aggregation
     * @param int $limit Number of groups to fetch
     * @param string $orderBy Order by group or aggregate
     * @param string $orderDirection Order direction
     * @return array Grouped data
     */
    private function getTableGroupedDataMultiField($tableName, array $groupByFields, $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC') {
        try {
            $this->ensureConnection();
            $this->validateTableName($tableName);
            
            // Validate all group by fields
            foreach ($groupByFields as $field) {
                $this->validateColumnName($field);
            }
            
            // Build aggregate expression
            $aggregateExpression = '';
            switch ($aggregateFunction) {
                case 'count':
                    $aggregateExpression = 'COUNT(*)';
                    break;
                case 'sum':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "SUM(`{$aggregateField}`)";
                    break;
                case 'avg':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "AVG(`{$aggregateField}`)";
                    break;
                case 'max':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "MAX(`{$aggregateField}`)";
                    break;
                case 'min':
                    $this->validateColumnName($aggregateField);
                    $aggregateExpression = "MIN(`{$aggregateField}`)";
                    break;
            }
            
            // Build SELECT clause with multiple fields
            $selectFields = [];
            foreach ($groupByFields as $field) {
                $selectFields[] = "`{$field}`";
            }
            $selectFields[] = "{$aggregateExpression} as aggregate_value";
            
            // Build GROUP BY clause
            $groupByClause = '`' . implode('`, `', $groupByFields) . '`';
            
            // Build ORDER BY clause
            $orderByClause = '';
            if ($orderBy === 'group') {
                $orderByClause = " ORDER BY `{$groupByFields[0]}` {$orderDirection}";
            } else {
                $orderByClause = " ORDER BY aggregate_value {$orderDirection}";
            }
            
            $query = "SELECT " . implode(', ', $selectFields) . " 
                     FROM `{$tableName}` 
                     GROUP BY {$groupByClause}
                     {$orderByClause}
                     LIMIT " . (int)$limit;
            
            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calculate total for percentage
            $total = 0;
            foreach ($results as $row) {
                $total += $row['aggregate_value'];
            }
            
            // Format results with percentage
            $formattedResults = [];
            foreach ($results as $row) {
                $value = $row['aggregate_value'];
                $percent = $total > 0 ? round(($value / $total) * 100, 2) : 0;
                $progressBar = $total > 0 ? round(($value / $total) * 100) . '%' : '0%';
                
                $resultRow = [];
                foreach ($groupByFields as $field) {
                    $resultRow[$field] = $row[$field];
                }
                $resultRow[$aggregateFunction] = $value;
                $resultRow['percent'] = $percent.'%';
                $resultRow['progress_bar'] = $progressBar;
                $formattedResults[] = $resultRow;
            }
            
            return $formattedResults;
            
        } catch (\Exception $e) {
            $this->logError("Error getting multi-field grouped data from table: " . $tableName, $query ?? '', []);
            return [];
        }
    }

    /**
     * Display grouped data by multiple fields (returns data array)
     * @param array $indexes Array of index positions to show
     * @param array $groupByFields Array of fields to group by
     * @param string $aggregateFunction Aggregate function
     * @param string $aggregateField Field for aggregation
     * @param int $limit Maximum number of groups
     * @param string $orderBy Order by: 'group' or 'aggregate'
     * @param string $orderDirection Order direction: ASC or DESC
     * @return array Grouped data array
     * 
     * Example:
     * // Group by category and status
     * $data = $model->showTablesRetGroupMultiField([10 => 'Sales'], ['category', 'status']);
     * 
     * // Group by department and position, average salary
     * $data = $model->showTablesRetGroupMultiField([5 => 'Employees'], ['department', 'position'], 'avg', 'salary');
     */
    public function showTablesRetGroupMultiField(array $indexes = [], array $groupByFields = [], $aggregateFunction = 'count', $aggregateField = '', $limit = 100, $orderBy = 'aggregate', $orderDirection = 'DESC') {
        return $this->getTablesRetGroupMultiField($indexes, $groupByFields, $aggregateFunction, $aggregateField, $limit, $orderBy, $orderDirection);
    }

    /**
     * Get grouped data with multiple aggregate functions from specific tables
     * @param array $indexes Array of index positions to show
     * @param string $groupByField Single field to group by
     * @param array $aggregateFunctions Array of aggregate functions with fields
     * @param int $limit Maximum number of groups (default: 100)
     * @param string $orderBy Field name to order by (default: first aggregate)
     * @param string $orderDirection Order direction: ASC or DESC (default: DESC)
     * @return array Array of grouped table data
     * 
     * Example:
     * // Group by category with multiple aggregates
     * $data = $model->getTablesRetGroupMultiAggregate([10 => 'Sales'], 'category', [
     *     'count' => '*',
     *     'sum' => 'amount',
     *     'avg' => 'amount',
     *     'max' => 'amount'
     * ]);
     */
    public function getTablesRetGroupMultiAggregate(array $indexes = [], $groupByField = '', array $aggregateFunctions = [], $limit = 100, $orderBy = '', $orderDirection = 'DESC') {
        if (empty($indexes)) {
            return [];
        }

        if (empty($groupByField)) {
            throw new \InvalidArgumentException("Group by field is required");
        }

        if (empty($aggregateFunctions)) {
            throw new \InvalidArgumentException("Aggregate functions cannot be empty");
        }

        // Validate aggregate functions
        $validFunctions = ['count', 'sum', 'avg', 'max', 'min'];
        foreach (array_keys($aggregateFunctions) as $function) {
            if (!in_array(strtolower($function), $validFunctions)) {
                throw new \InvalidArgumentException("Invalid aggregate function: {$function}");
            }
        }

        // Get all tables first
        $allTables = $this->showTables();
        $tablesGroupedData = [];

        // Check if this is an associative array (has string values as aliases)
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedDataMultiAggregate($tableName, $groupByField, $aggregateFunctions, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[$alias] = [
                        'table_name' => $tableName,
                        'alias' => $alias,
                        'group_by' => $groupByField,
                        'aggregate_functions' => $aggregateFunctions,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        } else {
            // Handle numeric array
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $groupedData = $this->getTableGroupedDataMultiAggregate($tableName, $groupByField, $aggregateFunctions, $limit, $orderBy, $orderDirection);
                    $tablesGroupedData[] = [
                        'table_name' => $tableName,
                        'group_by' => $groupByField,
                        'aggregate_functions' => $aggregateFunctions,
                        'grouped_data' => $groupedData
                    ];
                }
            }
        }

        return $tablesGroupedData;
    }

    /**
     * Display grouped data with multiple aggregates (returns data array)
     * @param array $indexes Array of index positions to show
     * @param string $groupByField Field to group by
     * @param array $aggregateFunctions Array of aggregate functions with fields
     * @param int $limit Maximum number of groups
     * @param string $orderBy Field name to order by
     * @param string $orderDirection Order direction: ASC or DESC
     * @return array Grouped data array
     * 
     * Example:
     * // Multiple aggregates on sales data
     * $data = $model->showTablesRetGroupMultiAggregate([10 => 'Sales'], 'category', [
     *     'count' => '*',
     *     'sum' => 'amount',
     *     'avg' => 'amount'
     * ]);
     */
    public function showTablesRetGroupMultiAggregate(array $indexes = [], $groupByField = '', array $aggregateFunctions = [], $limit = 100, $orderBy = '', $orderDirection = 'DESC') {
        return $this->getTablesRetGroupMultiAggregate($indexes, $groupByField, $aggregateFunctions, $limit, $orderBy, $orderDirection);
    }

    /**
     * Helper method to get grouped data with multiple aggregates from a specific table
     * @param string $tableName Name of the table
     * @param string $groupByField Field to group by
     * @param array $aggregateFunctions Array of aggregate functions with fields
     * @param int $limit Number of groups to fetch
     * @param string $orderBy Field name to order by
     * @param string $orderDirection Order direction
     * @return array Grouped data
     */
    private function getTableGroupedDataMultiAggregate($tableName, $groupByField, array $aggregateFunctions, $limit = 100, $orderBy = '', $orderDirection = 'DESC') {
        try {
            $this->ensureConnection();
            $this->validateTableName($tableName);
            $this->validateColumnName($groupByField);
            
            // Build SELECT clause
            $selectFields = ["`{$groupByField}`"];
            $aggregateAliases = [];
            
            foreach ($aggregateFunctions as $function => $field) {
                $function = strtolower($function);
                $alias = "{$function}_value";
                $aggregateAliases[] = $alias;
                
                switch ($function) {
                    case 'count':
                        if ($field === '*') {
                            $selectFields[] = "COUNT(*) as {$alias}";
                        } else {
                            $this->validateColumnName($field);
                            $selectFields[] = "COUNT(`{$field}`) as {$alias}";
                        }
                        break;
                    case 'sum':
                    case 'avg':
                    case 'max':
                    case 'min':
                        $this->validateColumnName($field);
                        $selectFields[] = strtoupper($function) . "(`{$field}`) as {$alias}";
                        break;
                }
            }
            
            // Build ORDER BY clause
            $orderByClause = '';
            if (!empty($orderBy)) {
                if ($orderBy === 'group') {
                    $orderByClause = " ORDER BY `{$groupByField}` {$orderDirection}";
                } elseif (in_array($orderBy, $aggregateAliases)) {
                    $orderByClause = " ORDER BY {$orderBy} {$orderDirection}";
                } else {
                    // Use first aggregate as default
                    $firstAlias = $aggregateAliases[0] ?? 'count_value';
                    $orderByClause = " ORDER BY {$firstAlias} {$orderDirection}";
                }
            } else {
                // Use first aggregate as default
                $firstAlias = $aggregateAliases[0] ?? 'count_value';
                $orderByClause = " ORDER BY {$firstAlias} {$orderDirection}";
            }
            
            $query = "SELECT " . implode(', ', $selectFields) . " 
                     FROM `{$tableName}` 
                     GROUP BY `{$groupByField}`
                     {$orderByClause}
                     LIMIT " . (int)$limit;
            
            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Format results
            $formattedResults = [];
            foreach ($results as $row) {
                $resultRow = [$groupByField => $row[$groupByField]];
                
                $i = 0;
                foreach ($aggregateFunctions as $function => $field) {
                    $alias = $aggregateAliases[$i++];
                    $resultRow[$function] = $row[$alias];
                }
                
                $formattedResults[] = $resultRow;
            }
            
            return $formattedResults;
            
        } catch (\Exception $e) {
            $this->logError("Error getting multi-aggregate grouped data from table: " . $tableName, $query ?? '', []);
            return [];
        }
    }



    /**
     * Clean data array for JSON output
     * @param array $data Raw data array
     * @return array Cleaned data array
     */
    private function cleanDataForJson($data) {
        $cleaned = [];
        
        foreach ($data as $key => $tableInfo) {
            $tableName = is_string($key) ? $key : $tableInfo['table_name'];
            
            $cleaned[$tableName] = [
                'table_info' => [
                    'real_name' => $tableInfo['table_name'],
                    'alias' => $tableInfo['alias'] ?? $tableName,
                    'total_records' => count($tableInfo['data'])
                ],
                'records' => []
            ];
            
            // Clean each record
            foreach ($tableInfo['data'] as $record) {
                $cleanRecord = [];
                foreach ($record as $field => $value) {
                    // Handle null values
                    if ($value === null) {
                        $cleanRecord[$field] = null;
                    } elseif (is_string($value) && trim($value) === '') {
                        $cleanRecord[$field] = "";
                    } else {
                        $cleanRecord[$field] = $value;
                    }
                }
                $cleaned[$tableName]['records'][] = $cleanRecord;
            }
        }
        
        return $cleaned;
    }

    /**
     * Read specific variable/field value from table by index
     * @param array $indexes Array with table index and field name: [3 => 'member', 'field' => 'nama']
     * @param string $field Field/column name to read
     * @param int $recordId Record ID to read (default: 1)
     * @return mixed Field value or null if not found
     */
    public function readVariable($indexes, $field = null, $recordId = 1) {
        if (empty($indexes)) {
            return null;
        }

        // Handle different input formats
        if (is_array($indexes) && isset($indexes['field'])) {
            $field = $indexes['field'];
            unset($indexes['field']);
        }

        if (empty($field)) {
            throw new \InvalidArgumentException("Field name is required");
        }

        // Get table data
        $tableData = $this->getTablesRetData($indexes, 1000); // Get more records to find the ID
        
        if (empty($tableData)) {
            return null;
        }

        // Get first table data
        $firstTable = reset($tableData);
        $records = $firstTable['data'] ?? [];

        if (empty($records)) {
            return null;
        }

        // Find record by ID
        foreach ($records as $record) {
            if (isset($record['id']) && $record['id'] == $recordId) {
                return $record[$field] ?? null;
            }
        }

        // If ID not found, return from first record
        return $records[0][$field] ?? null;
    }

    /**
     * Read multiple variables from table
     * @param array $indexes Table index array
     * @param array $fields Array of field names to read
     * @param int $recordId Record ID to read (default: 1)
     * @return array Array of field values
     */
    public function readVariables($indexes, array $fields, $recordId = 1) {
        if (empty($indexes) || empty($fields)) {
            return [];
        }

        $tableData = $this->getTablesRetData($indexes, 1000);
        
        if (empty($tableData)) {
            return [];
        }

        $firstTable = reset($tableData);
        $records = $firstTable['data'] ?? [];

        if (empty($records)) {
            return [];
        }

        // Find record by ID
        $targetRecord = null;
        foreach ($records as $record) {
            if (isset($record['id']) && $record['id'] == $recordId) {
                $targetRecord = $record;
                break;
            }
        }

        // If ID not found, use first record
        if (!$targetRecord) {
            $targetRecord = $records[0];
        }

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $targetRecord[$field] ?? null;
        }

        return $result;
    }

    /**
     * Read all variables from a specific record
     * @param array $indexes Table index array
     * @param int $recordId Record ID to read (default: 1)
     * @return array|null Complete record data or null if not found
     */
    public function readRecord($indexes, $recordId = 1) {
        if (empty($indexes)) {
            return null;
        }

        $tableData = $this->getTablesRetData($indexes, 1000);
        
        if (empty($tableData)) {
            return null;
        }

        $firstTable = reset($tableData);
        $records = $firstTable['data'] ?? [];

        if (empty($records)) {
            return null;
        }

        // Find record by ID
        foreach ($records as $record) {
            if (isset($record['id']) && $record['id'] == $recordId) {
                return $record;
            }
        }

        // If ID not found, return first record
        return $records[0] ?? null;
    }

    /**
     * Read variable with alias support (more secure)
     * @param array $indexes Array with alias: [3 => 'member']
     * @param string $field Field name to read
     * @param int $recordId Record ID (default: 1)
     * @return mixed Field value
     */
    public function readVar($indexes, $field, $recordId = 1) {
        return $this->readVariable($indexes, $field, $recordId);
    }

    public function getVariablesType($indexes) {
        // Type,Lengt,Default
        if (empty($indexes)) {
            return [];
        }

        // Get all tables first
        $allTables = $this->showTables();
        $variables = [];

        // Check if this is an associative array
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $columns = $this->getTableColumns($tableName);
                    
                    // Format columns with Type, Length, Default
                    $formattedColumns = [];
                    foreach ($columns as $column) {
                        $formattedColumns[$column['name']] = [
                            'name' => $column['name'],
                            'type' => $column['type'],
                            'length' => $column['max_length'] ?? null,
                            'default' => $column['default_value'] ?? null,
                            'nullable' => $column['nullable'],
                            'key_type' => $column['key_type'],
                            'extra' => $column['extra']
                        ];
                    }
                    
                    $variables[$alias] = [
                        'key' => $index,
                        'table_name' => $alias,
                        'variables' => $formattedColumns
                    ];
                }
            }
        } else {
            // Handle numeric array - still use index as key for consistency
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $columns = $this->getTableColumns($tableName);
                    
                    // Format columns with Type, Length, Default
                    $formattedColumns = [];
                    foreach ($columns as $column) {
                        $formattedColumns[$column['name']] = [
                            'name' => $column['name'],
                            'type' => $column['type'],
                            'length' => $column['max_length'] ?? null,
                            'default' => $column['default_value'] ?? null,
                            'nullable' => $column['nullable'],
                            'key_type' => $column['key_type'],
                            'extra' => $column['extra']
                        ];
                    }
                    
                    // Use table name as alias for numeric arrays
                    $variables[$tableName] = [
                        'table_name' => $tableName,
                        'alias' => $tableName,
                        'variables' => $formattedColumns
                    ];
                }
            }
        }

        return $variables;
    }
    /**
     * Get list of variables/columns from table by index
     * @param array $indexes Table index array: [3 => 'member']
     * @return array Array of column names
     */
    public function getVariablesList($indexes) {
        if (empty($indexes)) {
            return [];
        }

        // Get all tables first
        $allTables = $this->showTables();
        $variables = [];

        // Check if this is an associative array
        $isAssociative = array_keys($indexes) !== range(0, count($indexes) - 1);

        if ($isAssociative) {
            // Handle associative array with aliases
            foreach ($indexes as $index => $alias) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $columns = $this->getTableColumnNames($tableName);
                    $variables[$alias] = [
                        'key'        => $index,
                        'table_name' => $alias,
                        'real_table' => $tableName,
                        'variables'  => $columns,
                    ];
                }
            }
        } else {
            // Handle numeric array - still use index as key for consistency
            foreach ($indexes as $index) {
                if (is_numeric($index) && isset($allTables[$index])) {
                    $tableName = $allTables[$index];
                    $columns = $this->getTableColumnNames($tableName);
                    // Use table name as alias for numeric arrays
                    $variables[$tableName] = [
                        'table_name' => $tableName,
                        'real_table' => $tableName,
                        'alias'      => $tableName,
                        'variables'  => $columns,
                    ];
                }
            }
        }

        return $variables;
    }

    /**
     * Helper method to get column names from table name or current table
     * @param string|null $tableName Name of the table (optional, uses current table if null)
     * @return array Array of column names
     */
    private function getTableColumnNames($tableName = null) {
        try {
            $this->ensureConnection();
            
            // If no table name provided, use current table
            if ($tableName === null) {
                if (!$this->table) {
                    throw new \InvalidArgumentException("Table must be set to get column names");
                }
                $tableName = $this->table;
            }
            
            $this->validateTableName($tableName);
            
            $query = "SHOW COLUMNS FROM `{$tableName}`";
            $stmt = $this->db->query($query);
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $columnNames = [];
            foreach ($columns as $column) {
                $columnNames[] = $column['Field'];
            }
            
            return $columnNames;
        } catch (\Exception $e) {
            $this->logError("Error getting column names from table: " . ($tableName ?? 'current'), $query ?? '', []);
            return [];
        }
    }



    /**
     * Get list of variables/columns from table (returns data array)
     * @param array $indexes Table index array
     * @param bool $withPre Deprecated parameter (kept for compatibility)
     * @param string $format Deprecated parameter (kept for compatibility)
     * @return array Variables list data
     */
    public function showVariablesList($indexes, $withPre = false, $format = 'list') {
        // Return data array instead of echoing
        return $this->getVariablesList($indexes);
    }

    /**
     * Get simple array of variable names from table
     * @param array $indexes Table index array (single table)
     * @return array Simple array of column names
     */
    public function getVariables($indexes) {
        $variablesList = $this->getVariablesList($indexes);
        
        if (empty($variablesList)) {
            return [];
        }

        // Return variables from first table
        $firstTable = reset($variablesList);
        return $firstTable['variables'] ?? [];
    }

    /**
     * Check if variable exists in table
     * @param array $indexes Table index array
     * @param string $variableName Variable/column name to check
     * @return bool True if variable exists
     */
    public function hasVariable($indexes, $variableName) {
        $variables = $this->getVariables($indexes);
        return in_array($variableName, $variables);
    }

    /**
     * Get variable count from table
     * @param array $indexes Table index array
     * @return int Number of variables/columns
     */
    public function countVariables($indexes) {
        $variables = $this->getVariables($indexes);
        return count($variables);
    }

    /**
     * Search for variables containing specific text
     * @param array $indexes Table index array
     * @param string $searchText Text to search in variable names
     * @return array Array of matching variable names
     */
    public function searchVariables($indexes, $searchText) {
        $variables = $this->getVariables($indexes);
        $matches = [];
        
        foreach ($variables as $variable) {
            if (stripos($variable, $searchText) !== false) {
                $matches[] = $variable;
            }
        }
        
        return $matches;
    }

    /**
     * Generate menu structure from table aliases
     * @param array $indexes Table index array with aliases: [3 => 'member', 6 => 'exsampel']
     * @param array $config Menu configuration options
     * @return array Menu structure array
     * 
     * Example:
     * $menu = $model->generateMenuFromTables([3 => 'member', 6 => 'exsampel']);
     * Returns: {
     *   "label": "Tabel",
     *   "icon": "database", 
     *   "submenu": [
     *     {"label": "exsampel", "icon": "database", "action": "newFolder"},
     *     {"label": "member", "icon": "database", "action": "createNewFile"}
     *   ]
     * }
     */
    public function generateMenuFromTables(array $indexes = [], array $config = []) {
        if (empty($indexes)) {
            return [];
        }

        // Default configuration
        $defaultConfig = [
            'mainLabel' => 'Tabel',
            'mainIcon' => 'database',
            'itemIcon' => 'database',
            'actions' => ['newTabel'], // Alternating actions
            'singleAction' => null // If set, all items use this action
        ];

        $config = array_merge($defaultConfig, $config);
        $selectedTables = $this->showTablesRet($indexes);
        
        if (empty($selectedTables)) {
            return [];
        }

        // Generate submenu items
        $submenu = [];
        $actionIndex = 0;
        
        // Get original table indexes from input array
        $originalIndexes = array_keys($indexes);
        $indexCounter = 0;
        
        foreach ($selectedTables as $alias => $realTable) {
            $action = $config['singleAction'] ?? $config['actions'][$actionIndex % count($config['actions'])];
            
            // Use original table index as key (3, 6, etc.)
            $tableIndex = $originalIndexes[$indexCounter] ?? $indexCounter;
            
            // Get field count for the table
            $columns = $this->getTableColumns($realTable);
            $fieldCount = count($columns);
            
            $submenu[] = [
                'label' => $alias,
                'key' => (string)$tableIndex, // Use table index number as key
                'icon' => $config['itemIcon'],
                'action' => $action,
                'realTable' => $realTable, // Hidden reference to real table name
                'fieldCount' => $fieldCount // Jumlah field dalam tabel
            ];
            
            $actionIndex++;
            $indexCounter++;
        }

        return [
            'label' => $config['mainLabel'],
            'key' => strtolower(str_replace(' ', '_', $config['mainLabel'])),
            'icon' => $config['mainIcon'],
            'submenu' => $submenu
        ];
    }


    /**
     * Generate menu structure without real table references (clean output)
     * @param array $indexes Table index array with aliases
     * @param array $config Menu configuration options
     * @return array Clean menu structure array
     */
    public function generateCleanMenuFromTables(array $indexes = [], array $config = []) {
        $menu = $this->generateMenuFromTables($indexes, $config);
        
        // Remove realTable references from submenu items (keep key)
        if (isset($menu['submenu'])) {
            foreach ($menu['submenu'] as &$item) {
                unset($item['realTable']);
            }
        }
        
        return $menu;
    }

    /**
     * Generate multiple menu structures with different configurations
     * @param array $indexes Table index array with aliases
     * @param array $menuConfigs Array of menu configurations
     * @return array Array of menu structures
     */
    public function generateMultipleMenus(array $indexes = [], array $menuConfigs = []) {
        if (empty($indexes) || empty($menuConfigs)) {
            return [];
        }

        $menus = [];
        foreach ($menuConfigs as $config) {
            $menus[] = $this->generateMenuFromTables($indexes, $config);
        }
        
        return $menus;
    }

    /**
     * Generate table menu structure with alternating actions
     * @param array $indexes Table index array with aliases
     * @return array Menu structure with alternating actions
     */
    public function generateTableMenu(array $indexes = []) {
        return $this->generateCleanMenuFromTables($indexes, [
            'mainLabel' => 'Tabel',
            'mainIcon' => 'plus',
            'itemIcon' => 'plus',
            'actions' => ['newTabel']
        ]);
    }

    /**
     * Generate database menu (main method for production use)
     * @param array $indexes Table index array with aliases
     * @return array Professional menu structure
     */
    public function generateDatabaseMenu(array $indexes = []) {
        return $this->generateTableMenu($indexes);
    }

    /**
     * Generate menu structure (short alias)
     * @param array $indexes Table index array with aliases
     * @return array Menu structure
     */
    public function generateMenu(array $indexes = []) {
        return $this->generateTableMenu($indexes);
    }

    /**
     * Create menu from tables (most intuitive name)
     * @param array $indexes Table index array with aliases
     * @return array Menu structure
     */
    public function createMenuFromTables(array $indexes = []) {
        return $this->generateTableMenu($indexes);
    }

    /**
     * Generate menu with all same action
     * @param array $indexes Table index array with aliases
     * @param string $action Single action for all items
     * @param string $icon Icon for all items
     * @return array Menu structure with same action
     */
    public function generateUniformMenu(array $indexes = [], $action = 'createTabelFile', $icon = 'database') {
        return $this->generateCleanMenuFromTables($indexes, [
            'mainLabel' => 'Tabel',
            'mainIcon' => 'database',
            'itemIcon' => $icon,
            'singleAction' => $action
        ]);
    }

    /**
     * Generate custom menu with flexible icon and action patterns
     * @param array $indexes Table index array with aliases
     * @param array $patterns Pattern configuration
     * @return array Custom menu structure
     * 
     * Example patterns:
     * $patterns = [
     *   'icons' => ['folder', 'file', 'database'],
     *   'actions' => ['newTabel', 'openDatabase']
     * ];
     */
    public function generateCustomMenuPattern(array $indexes = [], array $patterns = []) {
        $defaultPatterns = [
            'icons' => ['database'],
            'actions' => ['newTabel']
        ];
        
        $patterns = array_merge($defaultPatterns, $patterns);
        
        $selectedTables = $this->showTablesRet($indexes);
        
        if (empty($selectedTables)) {
            return [];
        }

        $submenu = [];
        $iconIndex = 0;
        $actionIndex = 0;
        
        // Get original table indexes from input array
        $originalIndexes = array_keys($indexes);
        $indexCounter = 0;
        
        foreach ($selectedTables as $alias => $realTable) {
            // Use original table index as key (3, 6, etc.)
            $tableIndex = $originalIndexes[$indexCounter] ?? $indexCounter;
            
            // Get field count for the table
            $columns = $this->getTableColumns($realTable);
            $fieldCount = count($columns);
            
            $submenu[] = [
                'label' => $alias,
                'key' => (string)$tableIndex, // Use table index number as key
                'icon' => $patterns['icons'][$iconIndex % count($patterns['icons'])],
                'action' => $patterns['actions'][$actionIndex % count($patterns['actions'])],
                'fieldCount' => $fieldCount // Jumlah field dalam tabel
            ];
            
            $iconIndex++;
            $actionIndex++;
            $indexCounter++;
        }

        return [
            'label' => 'Tabel',
            'key' => 'tabel',
            'icon' => 'database',
            'submenu' => $submenu
        ];
    }



    /**
     * Get detailed information about all tables
     * @return array
     */
    public function getTablesInfo() {
        $this->ensureConnection();
        $query = "
            SELECT 
                TABLE_NAME as name,
                TABLE_ROWS as rows,
                DATA_LENGTH as data_length,
                INDEX_LENGTH as index_length,
                TABLE_COMMENT as comment,
                ENGINE as engine,
                TABLE_COLLATION as collation
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY TABLE_NAME
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get columns information for a specific table
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName) {
        $this->ensureConnection();
        $query = "
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                COLUMN_KEY as key_type,
                EXTRA as extra,
                COLUMN_COMMENT as comment
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$tableName]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a table exists
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        $this->ensureConnection();
        $query = "
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$tableName]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int)$result['count'] > 0;
    }

    /**
     * Get database information
     * @return array
     */
    public function getDatabaseInfo() {
        $this->ensureConnection();
        $info = [
            'database_name' => '',
            'total_tables' => 0,
            'database_size' => 0,
            'charset' => '',
            'collation' => ''
        ];
        
        // Get database name
        $stmt = $this->db->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $info['database_name'] = $result['db_name'];
        
        // Get database info
        $query = "
            SELECT 
                COUNT(*) as table_count,
                SUM(DATA_LENGTH + INDEX_LENGTH) as total_size,
                DEFAULT_CHARACTER_SET_NAME as charset,
                DEFAULT_COLLATION_NAME as collation
            FROM INFORMATION_SCHEMA.TABLES t
            JOIN INFORMATION_SCHEMA.SCHEMATA s ON t.TABLE_SCHEMA = s.SCHEMA_NAME
            WHERE t.TABLE_SCHEMA = DATABASE()
        ";
        
        $stmt = $this->db->query($query);
        $dbInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $info['total_tables'] = (int)$dbInfo['table_count'];
        $info['database_size'] = (int)$dbInfo['total_size'];
        $info['charset'] = $dbInfo['charset'];
        $info['collation'] = $dbInfo['collation'];
        
        return $info;
    }

    /**
     * Format bytes into human readable format
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $base = log($bytes, 1024);
        $index = floor($base);
        
        if ($index >= count($units)) {
            $index = count($units) - 1;
        }
        
        $size = round(pow(1024, $base - $index), $precision);
        return $size . ' ' . $units[$index];
    }

    /**
     * Display query results in formatted table using NexaRaw
     * @param bool $withPre Whether to include HTML pre tags
     * @return $this
     */
    public function render($withPre = false) {
        $results = $this->get();
        $renderer = new NexaRaw($results);
        $renderer->render($withPre);
        return $this;
    }

    /**
     * Display tables list in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTablesFormatted($withPre = false) {
        $tablesInfo = $this->getTablesInfo();
        $renderer = new NexaRaw($tablesInfo);
        $renderer->render($withPre);
    }

    /**
     * Display simple tables list in formatted table with data count and size
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTablesListFormatted($withPre = false) {
        $tablesInfo = $this->getTablesInfo();
        $formattedTables = [];
        foreach ($tablesInfo as $index => $tableInfo) {
            $sizeInBytes = ($tableInfo['data_length'] ?? 0) + ($tableInfo['index_length'] ?? 0);
            $formattedTables[] = [
                'No' => $index + 1,
                'Table Name' => $tableInfo['name'],
                'Rows' => number_format($tableInfo['rows'] ?? 0),
                'Size' => $this->formatBytes($sizeInBytes),
                'Engine' => $tableInfo['engine'] ?? 'Unknown'
            ];
        }
        $renderer = new NexaRaw($formattedTables);
        $renderer->render($withPre);
    }

    /**
     * Display table columns in formatted table
     * @param string $tableName
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTableColumnsFormatted($tableName, $withPre = false) {
        $columns = $this->getTableColumns($tableName);
        $renderer = new NexaRaw($columns);
        $renderer->render($withPre);
    }

    /**
     * Display database information in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showDatabaseInfoFormatted($withPre = false) {
        $info = $this->getDatabaseInfo();
        $formattedInfo = [
            [
                'Property' => 'Database Name',
                'Value' => $info['database_name']
            ],
            [
                'Property' => 'Total Tables',
                'Value' => $info['total_tables']
            ],
            [
                'Property' => 'Database Size',
                'Value' => $this->formatBytes($info['database_size'])
            ],
            [
                'Property' => 'Charset',
                'Value' => $info['charset']
            ],
            [
                'Property' => 'Collation',
                'Value' => $info['collation']
            ]
        ];
        $renderer = new NexaRaw($formattedInfo);
        $renderer->render($withPre);
    }

    /**
     * Health check method
     */
    public function healthCheck() {
        $health = [
            'database_connection' => false,
            'cache_status' => false, // Cache disabled
            'cache_items' => 0, // No cache items
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'errors' => []
        ];

        try {
            $this->ensureConnection();
            // Simple connection test
            $this->db->query("SELECT 1");
            $health['database_connection'] = true;
        } catch (\Exception $e) {
            $health['errors'][] = "Database connection: " . $e->getMessage();
        }

        return $health;
    }

    /**
     * Show performance and health information
     */
    public function showHealth($withPre = false) {
        $health = $this->healthCheck();
        $formattedHealth = [
            [
                'Component' => 'Database Connection',
                'Status' => $health['database_connection'] ? 'OK' : 'FAILED',
                'Details' => $health['database_connection'] ? 'Connected' : 'Not Connected'
            ],
            [
                'Component' => 'Cache System',
                'Status' => $health['cache_status'] ? 'ENABLED' : 'DISABLED',
                'Details' => 'Cache disabled - using direct execution'
            ],
            [
                'Component' => 'Memory Usage',
                'Status' => 'INFO',
                'Details' => 'Current: ' . $health['memory_usage'] . ', Peak: ' . $health['peak_memory']
            ]
        ];

        if (!empty($health['errors'])) {
            foreach ($health['errors'] as $error) {
                $formattedHealth[] = [
                    'Component' => 'Error',
                    'Status' => 'ERROR',
                    'Details' => $error
                ];
            }
        }

        $renderer = new NexaRaw($formattedHealth);
        $renderer->render($withPre);
    }

    /**
     * Method to test performance with a query
     */
    public function benchmarkQuery($iterations = 10) {
        if (empty($this->table)) {
            throw new \Exception("Table must be set before benchmarking");
        }

        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            // REMOVED: Cache checking logic - direct execution only
            $this->get();
            $times[] = (microtime(true) - $start) * 1000; // ms
        }

        return [
            'iterations' => $iterations,
            'avg_time_ms' => round(array_sum($times) / count($times), 2),
            'min_time_ms' => round(min($times), 2),
            'max_time_ms' => round(max($times), 2),
            'cache_hits' => 0, // Cache disabled
            'cache_hit_ratio' => '0% (Cache disabled - direct execution)'
        ];
    }

    /**
     * Execute query and display results in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The query results
     */
    public function getAndDisplay($withPre = false) {
        $results = $this->get();
        $renderer = new NexaRaw($results);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Get first record and display in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return array|null The first record
     */
    public function firstAndDisplay($withPre = false) {
        $result = $this->first();
        if ($result) {
            $renderer = new NexaRaw([$result]);
            $renderer->render($withPre);
        } else {
            echo $withPre ? "<pre>(no data found)</pre>" : "(no data found)\n";
        }
        return $result;
    }

    /**
     * Count records and display result in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @param string $column Column to count
     * @return int The count result
     */
    public function countAndDisplay($withPre = false, $column = '*') {
        $count = $this->count($column);
        $result = [['Count' => $count]];
        $renderer = new NexaRaw($result);
        $renderer->render($withPre);
        return $count;
    }

    /**
     * Display count by conditions in formatted table
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The count results
     */
    public function countByConditionsAndDisplay($column, array $conditions, $withPre = false) {
        $counts = $this->countByConditions($column, $conditions);
        $formattedCounts = [];
        foreach ($counts as $condition => $count) {
            $formattedCounts[] = [
                'Condition' => $condition,
                'Count' => $count
            ];
        }
        $renderer = new NexaRaw($formattedCounts);
        $renderer->render($withPre);
        return $counts;
    }

    /**
     * Display count with percentage in formatted table
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The count and percentage results
     */
    public function countWithPercentageAndDisplay($column, array $conditions, $withPre = false) {
        $results = $this->countWithPercentage($column, $conditions);
        $formattedResults = [];
        
        foreach ($conditions as $condition) {
            $count = $results[$condition] ?? 0;
            $percentage = $results['per' . $condition] ?? '0%';
            $formattedResults[] = [
                'Condition' => $condition,
                'Count' => $count,
                'Percentage' => $percentage
            ];
        }
        
        $renderer = new NexaRaw($formattedResults);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Display aggregate results (sum, avg, etc.) in formatted table
     * @param array $columns Array of columns to aggregate
     * @param string $operation Operation to perform
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The aggregate results
     */
    public function aggregateAndDisplay(array $columns, $operation = 'sum', $withPre = false) {
        $results = $this->countByColumn($columns, $operation);
        $formattedResults = [];
        
        foreach ($results as $column => $value) {
            $formattedResults[] = [
                'Column' => $column,
                ucfirst($operation) => $value
            ];
        }
        
        $renderer = new NexaRaw($formattedResults);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Full database explorer with formatted display
     * @param bool $withPre Whether to include HTML pre tags
     * @param bool $showColumns Whether to show columns for each table
     * @return void
     */
    public function exploreDatabase($withPre = false, $showColumns = false) {
        if ($withPre) echo "<h3>Database Explorer</h3>";
        else echo "=== DATABASE EXPLORER ===\n\n";
        
        // Show database info
        if ($withPre) echo "<h4>Database Information:</h4>";
        else echo "Database Information:\n";
        $this->showDatabaseInfoFormatted($withPre);
        
        if ($withPre) echo "<br><h4>Tables Overview:</h4>";
        else echo "\nTables Overview:\n";
        $this->showTablesFormatted($withPre);
        
        if ($showColumns) {
            $tables = $this->showTables();
            foreach ($tables as $tableName) {
                if ($withPre) echo "<br><h4>Columns in '$tableName':</h4>";
                else echo "\nColumns in '$tableName':\n";
                $this->showTableColumnsFormatted($tableName, $withPre);
            }
        }
    }
    
    /**
     * Test column validation (for debugging)
     * @param string|array $columns Columns to test
     * @return array Test results
     */
    public function testColumnValidation($columns): array
    {
        $results = [];
        $testColumns = is_array($columns) ? $columns : [$columns];
        
        foreach ($testColumns as $column) {
            try {
                $this->validateColumnName($column);
                $results[$column] = [
                    'valid' => true,
                    'message' => 'Column validation passed'
                ];
            } catch (\Exception $e) {
                $results[$column] = [
                    'valid' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Test column validation and display results
     * @param string|array $columns Columns to test
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function testColumnValidationAndDisplay($columns, $withPre = false): void
    {
        $results = $this->testColumnValidation($columns);
        $formattedResults = [];
        
        foreach ($results as $column => $result) {
            $formattedResults[] = [
                'Column' => $column,
                'Valid' => $result['valid'] ? 'YES' : 'NO',
                'Message' => $result['message']
            ];
        }
        
        $renderer = new NexaRaw($formattedResults);
        $renderer->render($withPre);
    }
    
    /**
     * ✅ DATE AND WORKING DAYS HELPER METHODS
     * These methods provide utilities for handling working days and date ranges
     */
    
    /**
     * Get working days range between two dates (excluding weekends)
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param int $workingDays Number of working days to calculate
     * @return array Array with 'start' and 'end' dates
     */
    public function getWorkingDaysRange($startDate, $workingDays = 5)
    {
        $start = new \DateTime($startDate);
        
        // Simple: just add the number of days from start date
        $end = clone $start;
        $end->add(new \DateInterval('P' . ($workingDays - 1) . 'D'));
        
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }
    
    /**
     * Get current work week range (Monday to Friday)
     * 
     * @param string $referenceDate Reference date (Y-m-d format, defaults to today)
     * @return array Array with 'start' and 'end' dates for current work week
     */
    public function getCurrentWorkWeek($referenceDate = null)
    {
        if ($referenceDate === null) {
            $referenceDate = date('Y-m-d');
        }
        
        $date = new \DateTime($referenceDate);
        
        // Get Monday of current week
        $monday = clone $date;
        $monday->modify('monday this week');
        
        // Get Friday of current week
        $friday = clone $date;
        $friday->modify('friday this week');
        
        return [
            'start' => $monday->format('Y-m-d'),
            'end' => $friday->format('Y-m-d')
        ];
    }
    
    /**
     * Filter query by consecutive days range
     * 
     * @param string $dateColumn Column name for date filtering
     * @param string $startDate Start date (Y-m-d format)
     * @param int $workingDays Number of consecutive days (0 = single date, 1+ = range)
     * @return $this
     */
    public function whereWorkingDays($dateColumn, $startDate = null, $workingDays = 5)
    {
        if ($startDate === null) {
            $startDate = date('Y-m-d');
        }
        
        // If workingDays is 0, filter by single date only
        if ($workingDays === 0) {
            return $this->where($dateColumn, $startDate);
        }
        
        // Otherwise, use consecutive days range
        $range = $this->getWorkingDaysRange($startDate, $workingDays);
        return $this->whereBetween($dateColumn, [$range['start'], $range['end']]);
    }
    
    /**
     * Filter query by current work week
     * 
     * @param string $dateColumn Column name for date filtering
     * @param string $referenceDate Reference date (defaults to today)
     * @return $this
     */
    public function whereCurrentWorkWeek($dateColumn, $referenceDate = null)
    {
        $range = $this->getCurrentWorkWeek($referenceDate);
        return $this->whereBetween($dateColumn, [$range['start'], $range['end']]);
    }
    
    /**
     * Filter query by specific date (only if it's a working day)
     * 
     * @param string $dateColumn Column name for date filtering
     * @param string $date Specific date to filter (Y-m-d format)
     * @param bool $skipWeekendCheck If true, allows weekend dates (default: false)
     * @return $this
     */
    public function whereWorkingDay($dateColumn, $date = null, $skipWeekendCheck = false)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        // Check if it's a working day (unless skipped)
        if (!$skipWeekendCheck && !$this->isWorkingDay($date)) {
            // If it's weekend, you might want to handle this differently
            // For now, we'll still filter by the date but log a warning
            error_log("Warning: Filtering by weekend date: {$date}");
        }
        
        return $this->where($dateColumn, $date);
    }
    
    /**
     * Check if a date is a working day (Monday to Friday)
     * 
     * @param string $date Date to check (Y-m-d format)
     * @return bool True if it's a working day, false if weekend
     */
    public function isWorkingDay($date)
    {
        $dayOfWeek = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
        return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Monday to Friday
    }
    
    /**
     * Get list of working days between two dates
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Array of working day dates
     */
    public function getWorkingDaysList($startDate, $endDate)
    {
        $workingDays = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            if ($this->isWorkingDay($current->format('Y-m-d'))) {
                $workingDays[] = $current->format('Y-m-d');
            }
            $current->add(new \DateInterval('P1D'));
        }
        
        return $workingDays;
    }
    
    /**
     * Count working days between two dates
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return int Number of working days
     */
    public function countWorkingDays($startDate, $endDate)
    {
        return count($this->getWorkingDaysList($startDate, $endDate));
    }

    /**
     * Insert data using table aliases/indexes
     * @param array $tableAlias [index => alias] e.g., [25 => 'Member']
     * @param array $data Data to insert
     * @return array Complete response with inserted data
     * 
     * Example:
     * $response = $model->tablesRetInsert([25 => 'Member'], ['name' => 'dantrik', 'title' => 'tatiye']);
     * $response = $model->tablesRetInsert([10 => 'Exsampel'], ['field1' => 'value1']);
     * 
     * Response format:
     * {
     *   "success": true,
     *   "insert_id": 61,
     *   "model": "TableName", 
     *   "method": "tablesRetInsert",
     *   "timestamp": "2025-08-28 04:17:18",
     *   "id": 61,
     *   "userid": 1,
     *   "nama": "example",
     *   "title": "example title",
     *   "created_at": "2025-08-28 04:17:18",
     *   "updated_at": "2025-08-28 04:17:18"
     * }
     */
    public function tablesRetInsert(array $tableAlias, array $data): array
    {
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            if (empty($tableAlias)) {
                throw new \InvalidArgumentException("Table alias cannot be empty");
            }
            
            if (empty($data)) {
                throw new \InvalidArgumentException("Insert data cannot be empty");
            }
            
            // Get table name from alias/index
            $tableIndex = array_keys($tableAlias)[0];
            $tableAlias = array_values($tableAlias)[0];
            $allTables = $this->showTables();
            
            if (!isset($allTables[$tableIndex])) {
                throw new \InvalidArgumentException("Table index {$tableIndex} not found. Available tables: " . count($allTables));
            }
            
            $tableName = $allTables[$tableIndex];
            $this->validateTableName($tableName);

            // Hanya isi kolom timestamp bila ada di skema fisik — hindari Unknown column untuk tabel tanpa Laravel timestamps
            $columnNames = $this->getTableColumnNames($tableName);
            if ($columnNames !== []) {
                if (!isset($data['created_at']) && in_array('created_at', $columnNames, true)) {
                    $data['created_at'] = $timestamp;
                }
                if (!isset($data['updated_at']) && in_array('updated_at', $columnNames, true)) {
                    $data['updated_at'] = $timestamp;
                }
            }
            
            // Perform insert using Storage method
            $this->Storage($tableName)->insert($data);
            
            // Get last insert ID
            $insertId = (int)$this->db->lastInsertId();
            
            // Add ID to data for complete response
            $data['id'] = $insertId;
            
            return $data;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'model' => $tableAlias ?? 'Unknown',
                'method' => 'tablesRetInsert',
                'timestamp' => $timestamp
            ];
        }
    }
    
    /**
     * Update data using table aliases/indexes
     * @param array $tableAlias [index => alias] e.g., [25 => 'Member']
     * @param array $data Data to update
     * @param int $id Record ID to update
     * @return bool Success status
     * 
     * Example:
     * $success = $model->tablesRetUpdate([25 => 'Member'], ['name' => 'updated_name'], 1);
     */
    public function tablesRetUpdate(array $tableAlias, array $data, int $id): bool
    {
        if (empty($tableAlias)) {
            throw new \InvalidArgumentException("Table alias cannot be empty");
        }
        
        if (empty($data)) {
            throw new \InvalidArgumentException("Update data cannot be empty");
        }
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for update operation");
        }
        
        // Get table name from alias/index
        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();
        
        if (!isset($allTables[$tableIndex])) {
            throw new \InvalidArgumentException("Table index {$tableIndex} not found");
        }
        
        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);

        // Hanya geser updated_at jika kolom tersebut ada pada tabel
        $columnNames = $this->getTableColumnNames($tableName);
        if ($columnNames !== [] && in_array('updated_at', $columnNames, true)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Perform update using Storage method
        return $this->Storage($tableName)->where('id', $id)->update($data);
    }
    
    /**
     * Delete data using table aliases/indexes
     * @param array $tableAlias [index => alias] e.g., [25 => 'Member']
     * @param int $id Record ID to delete
     * @return bool Success status
     * 
     * Example:
     * $success = $model->tablesRetDelete([25 => 'Member'], 1);
     */
    public function tablesRetDelete(array $tableAlias, int $id): bool
    {
        if (empty($tableAlias)) {
            throw new \InvalidArgumentException("Table alias cannot be empty");
        }
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for delete operation");
        }
        
        // Get table name from alias/index
        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();
        
        if (!isset($allTables[$tableIndex])) {
            throw new \InvalidArgumentException("Table index {$tableIndex} not found");
        }
        
        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);
        
        // Perform delete using Storage method
        return $this->Storage($tableName)->where('id', $id)->delete();
    }
    
    /**
     * Find data using table aliases/indexes
     * @param array $tableAlias [index => alias] e.g., [25 => 'Member']
     * @param int $id Record ID to find
     * @return array|null Record data or null if not found
     * 
     * Example:
     * $record = $model->tablesRetFind([25 => 'Member'], 1);
     */
    public function tablesRetFind(array $tableAlias, int $id): ?array
    {
        if (empty($tableAlias)) {
            throw new \InvalidArgumentException("Table alias cannot be empty");
        }
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for find operation");
        }
        
        // Get table name from alias/index
        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();
        
        if (!isset($allTables[$tableIndex])) {
            throw new \InvalidArgumentException("Table index {$tableIndex} not found");
        }
        
        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);
        
        // Perform find using Storage method
        return $this->Storage($tableName)->where('id', $id)->first();
    }


    public function tablesRetFindKey(array $tableAlias, string $failed, string $id): ?array
    {
        if (empty($tableAlias)) {
            throw new \InvalidArgumentException("Table alias cannot be empty");
        }
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for find operation");
        }
        
        // Get table name from alias/index
        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();
        
        if (!isset($allTables[$tableIndex])) {
            throw new \InvalidArgumentException("Table index {$tableIndex} not found");
        }
        
        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);
        
        // Perform find using Storage method
        return $this->Storage($tableName)
         ->where('userid', $this->userid())
         ->where($failed, $id)->first();
    }






public function showgetTables(): array {
    $this->ensureConnection();

    // Ambil semua tabel dari database
    $stmt = $this->db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(\PDO::FETCH_NUM);

    // Array hasil dengan key stabil
    $stableKeys = [];

    foreach ($tables as $table) {
        $tableName = $table[0]; // nama tabel
        $uniqueKey = hexdec(substr(md5($tableName), 0, 12)); // kode unik stabil berupa angka
        $stableKeys[$uniqueKey] = $tableName;       // simpan ke hasil
    }

    return $stableKeys;
}


public function tablesIndex(int $id): ?string {
    $allTables = $this->showgetTables();

    // Cek apakah index ada dalam array
    if (!isset($allTables[$id])) {
        return null;
    }

    $tableName = $allTables[$id];
    
    // Cek apakah nama tabel kosong
    if (empty($tableName)) {
        return null;
    }
    
    $this->validateTableName($tableName);

    return $tableName; // kembalikan nama tabel berdasarkan index
}

public function firstAtFind(array $data, string $keyword = ''): ?array
{
    try {
        $id = (int) $data['index'];
        $field = $data['variable'];
        $tableAlias = [$id => $data['classname']];

        if (empty($tableAlias)) {
            return null; // aman
        }

        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();

        if (!isset($allTables[$tableIndex])) {
            return null; // aman
        }

        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);

        $result = $this->Storage($tableName)
            ->select(['id', $field])
            ->where($field, $keyword)
            ->first();

        return $result ? (array)$result : null;
    } catch (\Throwable $e) {
        // Tangkap semua error, jangan lempar 500
        error_log("firstAtFind error: " . $e->getMessage());
        return null;
    }
}



public function firstAtGroup(array $tableAlias, $columns = '', string $access = ''): ?array
{
    try {
        if (empty($tableAlias)) {
            return null; // aman
        }

        $tableIndex = array_keys($tableAlias)[0];
        $allTables = $this->showTables();

        if (!isset($allTables[$tableIndex])) {
            return null; // aman
        }

        $tableName = $allTables[$tableIndex];
        $this->validateTableName($tableName);

        // Handle both string and array columns
        $selectColumns = is_array($columns) ? $columns : [$columns];
        $groupByColumns = is_array($columns) ? $columns : [$columns];

        // Build the query
        $query = $this->Storage($tableName);
        
        if ($access == "private") {
            $query->where('userid', $this->userid());
        }
        
        // Select and group by the same columns to comply with only_full_group_by
        $result = $query
            ->select($selectColumns)
            ->groupBy($groupByColumns)
            ->limit(100)
            ->get();

        return $result ? (array)$result : null;
    } catch (\Throwable $e) {
        error_log("firstAtGroup error: " . $e->getMessage());
        return null;
    }
}

    public function searchAtPopulate(array $data): array
    {
        try {
            $tabelId  = (int) ($data['tabel'] ?? 0);
            $field    = $data['fieldSearch'] ?? null;
            $keyword  = trim($data['search'] ?? '');
            $relasiKey = $data['relasiKey'] ?? null;
            $recordId = $data['recordId'] ?? $data['id'] ?? null;
            $debug = isset($data['debug']) && ($data['debug'] === true || $data['debug'] === 1 || $data['debug'] === '1');
            $access = isset($data['access']) ? (string)$data['access'] : '';
            $userid = $data['userid'] ?? null;
            $hasRecordId = $recordId !== null && $recordId !== '' && is_numeric((string)$recordId);

            if (!$tabelId || !$field) {
                return ['success' => false, 'message' => 'tabel or key missing'];
            }

            if (strlen($keyword) < 1 && !$hasRecordId) {
                $out = ['success' => true, 'table' => '', 'field' => $field, 'relasiKey' => $relasiKey, 'data' => []];
                if ($debug) {
                    $out['debug'] = [
                        'reason' => 'keyword kosong',
                        'tabelId' => $tabelId,
                        'field' => $field,
                        'keyword' => $keyword,
                        'relasiKey' => $relasiKey,
                        'access' => $access,
                        'userid' => $userid,
                        'recordId' => $recordId,
                    ];
                }
                return $out;
            }

            // Resolve table name via stable hash key
            $tableName = $this->tablesIndex($tabelId);

            if (!$tableName) {
                return ['success' => false, 'message' => "Table with id {$tabelId} not found"];
            }

            // Query: search by fieldSearch (index key) with keyword
            $query = $this->Storage($tableName);
            if ($hasRecordId) {
                $recordIdInt = (int)$recordId;
                $whereKey = is_string($relasiKey) && trim($relasiKey) !== '' ? trim($relasiKey) : 'id';
                $query->where($whereKey, $recordIdInt);
            } else {
                $query->where($field, 'LIKE', "%{$keyword}%");
            }
            // Optional scope by userid if requested (populate index bisa private)
            if (($access === 'private' || $access === 'mine') && $userid !== null && $userid !== '') {
                $query->where('userid', (int)$userid);
            }
            $results = $query->limit(10)->get();

            // Strip sensitive fields
            if (!empty($results) && is_array($results)) {
                foreach ($results as &$item) {
                    unset($item['password'], $item['password_hash']);
                }
                unset($item);
            }

            $out = [
                'success'  => true,
                'table'    => $tableName,
                'field'    => $field,
                'relasiKey'=> $relasiKey,
                'response'     => $results ?? [],
            ];
            if ($debug) {
                $out['debug'] = [
                    'tabelId' => $tabelId,
                    'table' => $tableName,
                    'field' => $field,
                    'keyword' => $keyword,
                    'relasiKey' => $relasiKey,
                    'access' => $access,
                    'userid' => $userid,
                    'recordId' => $recordId,
                    'count' => is_array($results) ? count($results) : 0,
                ];
                error_log("searchAtPopulate debug: " . json_encode($out['debug']));
            }
            return $out;

        } catch (\Throwable $e) {
            error_log("searchAtPopulate error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    public function searchAtFind(array $data,string $keyword): array
    {
        $id = (int)$data['metadata'];
        $field =$data['field'];
        $label =$data['label'];
        $fieldAS =$data['field'].' AS data';
        $labelAS =$data['label'].' AS label';
        $label2AS = !empty($data['title']) ? $data['title'].' AS title' : null;
        $valueAS =$data['value'].' AS value';
        $wherefield =$data['where']['field'] ??  null;
        $whereValues =$data['where']['value'] ?? null;
        $access =$data['access'] ?? 'public';


        $tableAlias=[
          $id =>$data['field']
        ];
           if (empty($tableAlias)) {
               throw new \InvalidArgumentException("Table alias cannot be empty");
           }
           // Get table name from alias/index
           $tableIndex = array_keys($tableAlias)[0];
           $allTables = $this->showTables();
        
           if (!isset($allTables[$tableIndex])) {
               throw new \InvalidArgumentException("Table index {$tableIndex} not found");
           }
        
           $tableName = $allTables[$tableIndex];
           $this->validateTableName($tableName);
       
             if ($access=="private") {
                 $userId = $this->userid();
                 
                 // Check if table has userid field, if not use 'id' field
                 $columnNames = $this->getTableColumnNames($tableName);
                 $userField = in_array('userid', $columnNames) ? 'userid' : 'id';
                 
                 $selectFields = array_filter([$fieldAS, $labelAS, $valueAS, $label2AS]);
                 if ($wherefield) {
                  $data = $this->Storage($tableName)
                    ->select('*, ' . implode(', ', $selectFields))
                    ->where($userField, $userId)
                    ->where($wherefield, $whereValues)
                    ->where($field, 'LIKE', "%{$keyword}%")
                    ->limit(5)
                    ->get();
                 } else {
                   $data = $this->Storage($tableName)
                    ->select('*, ' . implode(', ', $selectFields))
                    ->where($userField, $userId)
                    ->where($field, 'LIKE', "%{$keyword}%")
                    ->limit(5)
                    ->get();
                 }
                 // Hapus field password dari hasil jika ada
                 if (!empty($data) && is_array($data)) {
                     foreach ($data as &$item) {
                         if (isset($item['password'])) {
                             unset($item['password']);
                         }
                         if (isset($item['password_hash'])) {
                             unset($item['password_hash']);
                         }
                     }
                     unset($item);
                 }
                 return $data ?? [];
             } else {
                 $selectFields = array_filter([$fieldAS, $labelAS, $valueAS, $label2AS]);
                 if ($wherefield) {
                  $data = $this->Storage($tableName)
                    ->select('*, ' . implode(', ', $selectFields))
                    ->where($wherefield, $whereValues)
                    ->where($field, 'LIKE', "%{$keyword}%")
                    ->limit(5)
                    ->get();
                 } else {
                  $data = $this->Storage($tableName)
                    ->select('*, ' . implode(', ', $selectFields))
                    ->where($field, 'LIKE', "%{$keyword}%")
                    ->limit(5)
                    ->get();
                 }
                 // Hapus field password dari hasil jika ada
                 if (!empty($data) && is_array($data)) {
                     foreach ($data as &$item) {
                         if (isset($item['password'])) {
                             unset($item['password']);
                         }
                         if (isset($item['password_hash'])) {
                             unset($item['password_hash']);
                         }
                     }
                     unset($item);
                 }
                 return $data ?? [];

             }
           
    }
    /**
     * ✅ GENERIC METHODS FOR NEXAFORM DELEGATION
     * These methods provide a clean interface for NexaForm to delegate operations
     * without performing direct database operations
     */
    /**
     * Generic insert method for NexaForm delegation
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int Last insert ID
     */
    public function StorageLast(string $table, array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Insert data cannot be empty");
        }
        
        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Use Storage method to insert
        $this->Storage($table)->insert($data);
        
        // Return last insert ID
        return $this->db->lastInsertId();
    }    
    /**
     * Generic insert method for NexaForm delegation
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int Last insert ID
     */
    public function insertRecord(string $table, array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Insert data cannot be empty");
        }
        
        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Use Storage method to insert
        $this->Storage($table)->insert($data);
        
        // Return last insert ID
        return $this->db->lastInsertId();
    }
    
    /**
     * Generic update method for NexaForm delegation
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param int $id Record ID
     * @return bool Success status
     */
    public function updateRecord(string $table, array $data, int $id): bool
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Update data cannot be empty");
        }
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for update operation");
        }
        
        // Add timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Use Storage method to update
        return $this->Storage($table)->where('id', $id)->update($data);
    }
    
    /**
     * Generic delete method for NexaForm delegation
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @return bool Success status
     */
    public function deleteRecord(string $table, int $id): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for delete operation");
        }
        
        // Use Storage method to delete
        return $this->Storage($table)->where('id', $id)->delete();
    }
    
    /**
     * Generic find by ID method for NexaForm delegation
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @return array|null Record data or null if not found
     */
    public function findRecord(string $table, int $id): ?array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID for find operation");
        }
        
        return $this->Storage($table)->where('id', $id)->first();
    }
    

    /**
     * Mengubah array menjadi JSON string dengan penanganan null
     * @param mixed $data Data yang akan diubah menjadi JSON
     * @param bool $prettyPrint Format JSON agar mudah dibaca (opsional)
     * @return string|null JSON string atau null jika data kosong
     */
    public function toJson($data, $prettyPrint = false) {
        if (!isset($data)) {
            return null;
        }
        
        if (is_array($data)) {
            return $this->arrayToJsonString($data, $prettyPrint);
        }
        
        return '"' . addslashes($data) . '"';
    }
    
    private function arrayToJsonString($array, $prettyPrint = false, $indent = 0) {
        if (empty($array)) {
            return '{}';
        }
        
        $spaces = $prettyPrint ? str_repeat('    ', $indent) : '';
        $newline = $prettyPrint ? "\n" : '';
        $output = '{' . $newline;
        
        $count = 0;
        $total = count($array);
        
        foreach ($array as $key => $value) {
            $output .= $spaces . '    "' . addslashes($key) . '": ';
            
            if (is_array($value)) {
                $output .= $this->arrayToJsonString($value, $prettyPrint, $indent + 1);
            } elseif (is_null($value)) {
                $output .= 'null';
            } elseif (is_bool($value)) {
                $output .= $value ? 'true' : 'false';
            } elseif (is_numeric($value)) {
                $output .= $value;
            } else {
                $output .= '"' . addslashes($value) . '"';
            }
            
            $comma = ($count < $total - 1) ? ',' : '';
            $output .= $comma . $newline;
            $count++;
        }
        
        $output .= $spaces . '}';
        return $output;
    }
    public function addSlug($value) {
        // Replace non-alphanumeric with dash
        $value = preg_replace('/[^a-zA-Z0-9-]/', '-', $value);
        // Remove duplicate dashes
        $value = preg_replace('/-+/', '-', $value);
        // Trim dashes from start and end
        return trim($value, '-');
    }

    /**
     * Handle null values in results with default values
     * 
     * @param array $results Query results
     * @param array $defaults Default values for null fields
     * @return array Results with null values replaced
     * 
     * Example:
     * $results = $model->handleNullValues($results, [
     *     'status' => 'inactive',
     *     'role' => 'user',
     *     'avatar' => '/assets/images/default-avatar.png'
     * ]);
     */
    public function handleNullValues(array $results, array $defaults = []): array {
        if (empty($results) || empty($defaults)) {
            return $results;
        }

        foreach ($results as &$row) {
            if (is_array($row)) {
                foreach ($defaults as $field => $defaultValue) {
                    if (!isset($row[$field]) || $row[$field] === null || $row[$field] === '') {
                        $row[$field] = $defaultValue;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Handle null values for single record
     * 
     * @param array|null $record Single record
     * @param array $defaults Default values for null fields
     * @return array|null Record with null values replaced
     */
    public function handleNullValue($record, array $defaults = []) {
        if (!$record || empty($defaults)) {
            return $record;
        }

        foreach ($defaults as $field => $defaultValue) {
            if (!isset($record[$field]) || $record[$field] === null || $record[$field] === '') {
                $record[$field] = $defaultValue;
            }
        }

        return $record;
    }

    /**
     * Select with null handling using COALESCE
     * 
     * @param array $columns Array of columns with default values
     * @return $this
     * 
     * Example:
     * $query->selectWithDefaults([
     *     'id' => null,
     *     'nama' => null,
     *     'status' => 'inactive',
     *     'email' => null,
     *     'avatar' => '/assets/images/default-avatar.png'
     * ]);
     */
    public function selectWithDefaults(array $columns) {
        $selectFields = [];
        
        foreach ($columns as $column => $defaultValue) {
            if ($defaultValue !== null) {
                $selectFields[] = "COALESCE($column, '$defaultValue') AS $column";
            } else {
                $selectFields[] = $column;
            }
        }
        
        return $this->select($selectFields);
    }



    /**
     * Pilih semua kolom kecuali field sensitif umum (password, token, etc.)
     * @param array $additionalExcludes Field tambahan yang akan dikecualikan
     * @return $this
     * 
     * Example:
     * $model->table('users')->exceptSensitive()->get();
     * $model->table('users')->exceptSensitive(['api_key', 'secret'])->get();
     */
    public function exceptSensitive(array $additionalExcludes = []) {
        $defaultSensitive = [
            'password',
            'password_hash',
            'remember_token',
            'api_token',
            'api_key',
            'secret',
            'secret_key',
            'access_token',
            'refresh_token',
            'private_key',
            'salt'
        ];

        $excludeColumns = array_merge($defaultSensitive, $additionalExcludes);
        return $this->except($excludeColumns);
    }

    /**
     * Pilih semua kolom kecuali timestamp fields (created_at, updated_at, deleted_at)
     * @param array $additionalExcludes Field tambahan yang akan dikecualikan
     * @return $this
     * 
     * Example:
     * $model->table('posts')->exceptTimestamps()->get();
     * $model->table('posts')->exceptTimestamps(['published_at'])->get();
     */
    public function exceptTimestamps(array $additionalExcludes = []) {
        $timestampFields = [
            'created_at',
            'updated_at',
            'deleted_at'
        ];

        $excludeColumns = array_merge($timestampFields, $additionalExcludes);
        return $this->except($excludeColumns);
    }

    /**
     * Pilih semua kolom kecuali field ID dan timestamps
     * @param array $additionalExcludes Field tambahan yang akan dikecualikan
     * @return $this
     * 
     * Example:
     * $model->table('users')->exceptSystemFields()->get();
     * $model->table('users')->exceptSystemFields(['status'])->get();
     */
    public function exceptSystemFields(array $additionalExcludes = []) {
        $systemFields = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at'
        ];

        $excludeColumns = array_merge($systemFields, $additionalExcludes);
        return $this->except($excludeColumns);
    }

    /**
     * Pilih hanya field yang aman untuk public display (mengecualikan sensitive + internal fields)
     * @param array $additionalIncludes Field tambahan yang akan disertakan
     * @return $this
     * 
     * Example:
     * $model->table('users')->selectPublicFields()->get();
     * $model->table('users')->selectPublicFields(['profile_image'])->get();
     */
    public function selectPublicFields(array $additionalIncludes = []) {
        $excludeColumns = [
            'password',
            'password_hash',
            'remember_token',
            'api_token',
            'api_key',
            'secret',
            'secret_key',
            'access_token',
            'refresh_token',
            'private_key',
            'salt',
            'deleted_at',
            'email_verified_at',
            'phone_verified_at'
        ];

        // Get all columns and remove excluded ones
        $allColumns = $this->getTableColumnNames();
        $selectedColumns = array_diff($allColumns, $excludeColumns);
        
        // Add additional includes
        if (!empty($additionalIncludes)) {
            foreach ($additionalIncludes as $include) {
                if (!in_array($include, $selectedColumns)) {
                    $selectedColumns[] = $include;
                }
            }
        }

        if (empty($selectedColumns)) {
            throw new \InvalidArgumentException("No public fields available for selection");
        }

        $this->select = implode(', ', $selectedColumns);
        return $this;
    }

    /**
     * Pilih field minimal untuk list/index display
     * @param array $baseFields Field dasar yang akan dipilih
     * @param array $additionalFields Field tambahan
     * @return $this
     * 
     * Example:
     * $model->table('users')->selectMinimal()->get(); // id, name, email saja
     * $model->table('users')->selectMinimal(['id', 'title'], ['status'])->get();
     */
    public function selectMinimal(array $baseFields = ['id', 'name', 'email'], array $additionalFields = []) {
        $selectedFields = array_merge($baseFields, $additionalFields);
        
        // Validate all fields
        foreach ($selectedFields as $field) {
            $this->validateColumnName($field);
        }

        $this->select = implode(', ', array_unique($selectedFields));
        return $this;
    }

    /**
     * Pilih semua kolom kecuali yang disebutkan (tanpa validasi table)
     * @param array|string $excludeColumns Kolom yang akan dikecualikan
     * @param array $allColumns Semua kolom yang tersedia
     * @return $this
     * 
     * Example:
     * $model->exceptFrom(['password', 'token'], ['id', 'name', 'email', 'password', 'token']);
     */
    public function exceptFrom($excludeColumns, array $allColumns) {
        // Convert string to array if needed
        if (is_string($excludeColumns)) {
            $excludeColumns = array_map('trim', explode(',', $excludeColumns));
        }

        if (!is_array($excludeColumns)) {
            throw new \InvalidArgumentException("Exclude columns must be array or comma-separated string");
        }

        if (empty($allColumns)) {
            throw new \InvalidArgumentException("All columns array cannot be empty");
        }

        // Validate excluded column names
        foreach ($excludeColumns as $column) {
            $this->validateColumnName(trim($column));
        }

        // Remove excluded columns
        $selectedColumns = array_diff($allColumns, $excludeColumns);
        
        if (empty($selectedColumns)) {
            throw new \InvalidArgumentException("Cannot exclude all columns");
        }

        $this->select = implode(', ', $selectedColumns);
        return $this;
    }

    /**
     * Method noSelect dengan daftar kolom manual (lebih cepat)
     * @param array|string $excludeColumns Kolom yang tidak akan dipilih
     * @param array $allColumns Semua kolom yang tersedia
     * @return $this
     * 
     * Example:
     * $model->noSelectFrom(['password', 'token'], ['id', 'name', 'email', 'password', 'token']);
     */
    public function noSelectFrom($excludeColumns, array $allColumns) {
        return $this->exceptFrom($excludeColumns, $allColumns);
    }

    /**
     * Method untuk tidak mengambil field dari daftar kolom yang sudah diketahui
     * @param array|string $excludeColumns Kolom yang tidak akan diambil
     * @param array $allColumns Semua kolom yang tersedia
     * @return $this
     * 
     * Example:
     * $model->withoutFrom(['password'], ['id', 'name', 'email', 'password']);
     */
    public function withoutFrom($excludeColumns, array $allColumns) {
        return $this->exceptFrom($excludeColumns, $allColumns);
    }

    /**
     * Shortcut: Tidak memilih field password
     * @return $this
     * 
     * Example:
     * $model->table('users')->noPassword()->get();
     */
    public function noPassword() {
        return $this->except(['password', 'password_hash']);
    }

    /**
     * Shortcut: Tidak memilih field timestamps
     * @return $this
     * 
     * Example:
     * $model->table('posts')->noTimestamps()->get();
     */
    public function noTimestamps() {
        return $this->exceptTimestamps();
    }

    /**
     * Shortcut: Tidak memilih field sensitif
     * @return $this
     * 
     * Example:
     * $model->table('users')->noSensitive()->get();
     */
    public function noSensitive() {
        return $this->exceptSensitive();
    }

    /**
     * Shortcut: Tidak memilih field sistem (id + timestamps)
     * @return $this
     * 
     * Example:
     * $model->table('categories')->noSystem()->get();
     */
    public function noSystem() {
        return $this->exceptSystemFields();
    }

    /**
     * Shortcut: Tidak memilih field ID
     * @return $this
     * 
     * Example:
     * $model->table('users')->noId()->get();
     */
    public function noId() {
        return $this->except(['id']);
    }


    protected function redJson() {
        $nexaJon = new NexaJon();
        return $nexaJon;
    }






    /**
     * Shortcut: Tidak memilih field token (semua jenis token)
     * @return $this
     * 
     * Example:
     * $model->table('users')->noTokens()->get();
     */
    public function noTokens() {
        return $this->except([
            'remember_token', 
            'api_token', 
            'access_token', 
            'refresh_token',
            'api_key',
            'secret_key'
        ]);
    }

    /**
     * Tidak menampilkan/memilih record berdasarkan kondisi field tertentu
     * @param array $conditions Kondisi field yang tidak ingin ditampilkan
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->noSelectFields(['account_name' => 'Biaya Gaji'])->get();
     * $model->table('users')->noSelectFields(['status' => 'inactive', 'role' => 'banned'])->get();
     * $model->table('posts')->noSelectFields(['published' => 0])->get();
     */
    public function noSelectFields(array $conditions) {
        if (empty($conditions)) {
            return $this;
        }

        foreach ($conditions as $field => $value) {
            // Validate field name
            $this->validateColumnName($field);
            
            // Add WHERE NOT condition for each field
            if (is_array($value)) {
                // If value is array, use NOT IN
                $this->whereNotIn($field, $value);
            } elseif ($value === null) {
                // If value is null, use IS NOT NULL
                $this->whereNotNull($field);
            } else {
                // Regular NOT equal condition
                $this->where($field, '!=', $value);
            }
        }

        return $this;
    }

    /**
     * Alias untuk noSelectFields - tidak menampilkan record berdasarkan kondisi
     * @param array $conditions Kondisi field yang tidak ingin ditampilkan
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->hideRecords(['account_name' => 'Biaya Gaji'])->get();
     */
    public function hideRecords(array $conditions) {
        return $this->noSelectFields($conditions);
    }

    /**
     * Tidak menampilkan record yang memiliki nilai tertentu
     * @param array $conditions Kondisi yang akan disembunyikan
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->excludeRecords(['account_name' => 'Biaya Gaji'])->get();
     */
    public function excludeRecords(array $conditions) {
        return $this->noSelectFields($conditions);
    }

    /**
     * Melewati/skip record berdasarkan kondisi tertentu
     * @param array $conditions Kondisi record yang akan dilewati
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->skipRecords(['account_name' => 'Biaya Gaji'])->get();
     */
    public function skipRecords(array $conditions) {
        return $this->noSelectFields($conditions);
    }

    /**
     * Tidak mengambil record yang memenuhi kondisi tertentu
     * @param array $conditions Kondisi record yang tidak akan diambil
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->withoutRecords(['account_name' => 'Biaya Gaji'])->get();
     */
    public function withoutRecords(array $conditions) {
        return $this->noSelectFields($conditions);
    }

    /**
     * Filter out records yang memiliki nilai tertentu
     * @param array $conditions Kondisi yang akan difilter keluar
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->filterOut(['account_name' => 'Biaya Gaji'])->get();
     */
    public function filterOut(array $conditions) {
        return $this->noSelectFields($conditions);
    }

    /**
     * Shortcut: Tidak menampilkan record yang tidak aktif
     * @param string $statusField Nama field status (default: 'is_active')
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->onlyActive()->get();
     * $model->table('users')->onlyActive('status')->get(); // jika field status namanya 'status'
     */
    public function onlyActive($statusField = 'is_active') {
        return $this->where($statusField, 1);
    }

    /**
     * Shortcut: Tidak menampilkan record yang aktif (hanya yang inactive)
     * @param string $statusField Nama field status (default: 'is_active')
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->onlyInactive()->get();
     */
    public function onlyInactive($statusField = 'is_active') {
        return $this->where($statusField, 0);
    }

    /**
     * Shortcut: Tidak menampilkan record yang sudah dihapus (soft delete)
     * @param string $deletedField Nama field deleted (default: 'deleted_at')
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->notDeleted()->get();
     */
    public function notDeleted($deletedField = 'deleted_at') {
        return $this->whereNull($deletedField);
    }

    /**
     * Shortcut: Hanya menampilkan record yang sudah dihapus (soft delete)
     * @param string $deletedField Nama field deleted (default: 'deleted_at')
     * @return $this
     * 
     * Example:
     * $model->table('accounts')->onlyDeleted()->get();
     */
    public function onlyDeleted($deletedField = 'deleted_at') {
        return $this->whereNotNull($deletedField);
    }

    /**
     * ✅ CHART.JS METHODS
     * Metode khusus untuk mempersiapkan data Chart.js dalam format array yang siap pakai
     */

    /**
     * Generate data untuk Line Chart dari data berdasarkan kolom tanggal
     * 
     * @param string $dateColumn Kolom tanggal untuk x-axis
     * @param string $valueColumn Kolom nilai untuk y-axis
     * @param string $dateFormat Format tanggal untuk label (default: 'Y-m-d')
     * @param array $options Opsi tambahan untuk chart
     * @return array Data siap untuk Chart.js Line Chart
     * 
     * Example:
     * $lineData = $model->table('sales')->chartLine('created_at', 'amount');
     * $lineData = $model->table('views')->chartLine('date', 'count', 'M Y', ['backgroundColor' => '#36A2EB']);
     */
    public function chartLine($dateColumn, $valueColumn, $dateFormat = 'Y-m-d', array $options = []) {
        // Default options
        $defaultOptions = [
            'label' => 'Data',
            'borderColor' => '#36A2EB',
            'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
            'tension' => 0.1
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Get data
        $results = $this->orderBy($dateColumn, 'ASC')->get();
        
        $labels = [];
        $data = [];
        
        foreach ($results as $row) {
            if (isset($row[$dateColumn]) && isset($row[$valueColumn])) {
                $date = date($dateFormat, strtotime($row[$dateColumn]));
                $labels[] = $date;
                $data[] = (float)$row[$valueColumn];
            }
        }
        
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $options['label'],
                    'data' => $data,
                    'borderColor' => $options['borderColor'],
                    'backgroundColor' => $options['backgroundColor'],
                    'tension' => $options['tension']
                ]]
            ]
        ];
    }

  
    /**
     * Helper: Convert hex color to rgba
     */
    private function hexToRgba($hex, $alpha = 1) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgba($r, $g, $b, $alpha)";
    }

    /**
     * Generate data untuk Radar Chart
     * 
     * @param array $columns Array kolom untuk radar axes
     * @param array $options Opsi tambahan
     * @return array Data siap untuk Chart.js Radar Chart
     * 
     * Example:
     * $radarData = $model->table('performance')->chartRadar(['speed', 'quality', 'efficiency', 'innovation']);
     */
    public function chartRadar(array $columns, array $options = []) {
        $defaultOptions = [
            'label' => 'Performance',
            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
            'borderColor' => '#36A2EB',
            'pointBackgroundColor' => '#36A2EB'
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Get average values for each column
        $averages = $this->avgColumns($columns);
        
        $labels = array_map(function($col) {
            return ucfirst(str_replace('_', ' ', $col));
        }, $columns);
        
        $data = array_values($averages);
        
        return [
            'type' => 'radar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $options['label'],
                    'data' => $data,
                    'backgroundColor' => $options['backgroundColor'],
                    'borderColor' => $options['borderColor'],
                    'pointBackgroundColor' => $options['pointBackgroundColor']
                ]]
            ]
        ];
    }

    /**
     * Generate complete Chart.js configuration dengan responsive options
     * 
     * @param array $chartData Data chart dari metode chart lainnya
     * @param array $chartOptions Opsi konfigurasi Chart.js
     * @return array Complete Chart.js configuration
     * 
     * Example:
     * $barData = $model->table('sales')->chartBar('month');
     * $config = $model->chartConfig($barData, ['title' => 'Monthly Sales Report']);
     */
    public function chartConfig(array $chartData, array $chartOptions = []) {
        $defaultOptions = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'title' => [
                    'display' => false,
                    'text' => 'Chart Title'
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'top'
                ]
            ]
        ];
        
        // Merge with custom options
        if (isset($chartOptions['title'])) {
            $defaultOptions['plugins']['title']['display'] = true;
            $defaultOptions['plugins']['title']['text'] = $chartOptions['title'];
            unset($chartOptions['title']);
        }
        
        $finalOptions = array_replace_recursive($defaultOptions, $chartOptions);
        
        return [
            'type' => $chartData['type'],
            'data' => $chartData['data'],
            'options' => $finalOptions
        ];
    }

}