<?php
namespace App\System\Helpers;

use Exception;
use App\System\NexaModel;

class NexaWebSocket {
    private $socket;
    private $clients = [];
    private $host;
    private $port;
    private $isRunning = false;
    private $model;
    private $allowedTables = [];
    private $enableDbOperations = true;
    private $lastDatabaseCheck = [];
    private $databaseCheckInterval = 2; // Check every 2 seconds
    private $lastCheckTime = 0;
    
    public function __construct($host = '127.0.0.1', $port = 8080, $options = []) {
        $this->host = $host;
        $this->port = $port;
        
        // Set database options but don't initialize yet
        $this->enableDbOperations = isset($options['enable_db']) ? $options['enable_db'] : false;
        
        // Set allowed tables for security
        if (isset($options['allowed_tables']) && is_array($options['allowed_tables'])) {
            $this->allowedTables = $options['allowed_tables'];
        }
    }
    
    /**
     * Enable database operations
     */
    public function enableDatabase(array $allowedTables = []) {
        $this->enableDbOperations = true;
        $this->allowedTables = $allowedTables;
        $this->log("Database operations enabled");
    }
    
    /**
     * Disable database operations
     */
    public function disableDatabase() {
        $this->enableDbOperations = false;
        $this->model = null;
        $this->log("Database operations disabled");
    }
    
    /**
     * Handle database operations via WebSocket
     */
    private function handleDatabaseOperation($client, $operation) {
        $this->log("Handling database operation for client: {$client['id']}");
        $this->log("Operation data: " . json_encode($operation));
        
        if (!$this->enableDbOperations) {
            $this->log("Database operations are disabled");
            return $this->sendToClient($client['id'], json_encode([
                'type' => 'error',
                'message' => 'Database operations are disabled'
            ]));
        }
        
        // Initialize model only when needed
        // Force create fresh NexaModel instance every time to avoid cache
        try {
            $this->log("Creating fresh NexaModel...");
            $this->model = new NexaModel();
            
            // FORCE CONSISTENT DATA - create brand new model instance
            unset($this->model);  // Force garbage collection
            $this->model = new NexaModel();
            $this->log("Double-fresh NexaModel created for consistency");
            
            $this->log("Fresh NexaModel created successfully");
        } catch (Exception $e) {
            $this->log("NexaModel creation failed: " . $e->getMessage());
            return $this->sendToClient($client['id'], json_encode([
                'type' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
        
        try {
            $action = $operation['action'] ?? null;
            $table = $operation['table'] ?? null;
            $data = $operation['data'] ?? [];
            $conditions = $operation['conditions'] ?? [];
            
            $this->log("Action: {$action}, Table: {$table}");
            $this->log("Allowed tables: " . implode(', ', $this->allowedTables));
            
            // Validate table access
            if (!empty($this->allowedTables) && !in_array($table, $this->allowedTables)) {
                $this->log("Access denied to table: {$table}");
                throw new Exception("Access denied to table: {$table}");
            }
            
            $this->log("Table access allowed, executing {$action} on {$table}");
            $result = null;
            
            switch ($action) {
                case 'select':
                    $this->log("Executing SELECT query...");
                    $result = $this->handleSelect($table, $data, $conditions);
                    $this->log("SELECT result count: " . (is_array($result) ? count($result) : 'not array'));
                    $this->log("SELECT result data: " . json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR));
                    break;
                    
                case 'insert':
                    $result = $this->handleInsert($table, $data);
                    break;
                    
                case 'update':
                    $result = $this->handleUpdate($table, $data, $conditions);
                    break;
                    
                case 'delete':
                    $result = $this->handleDelete($table, $conditions);
                    break;
                    
                case 'count':
                    $result = $this->handleCount($table, $conditions);
                    break;
                    
                default:
                    throw new Exception("Unknown database action: {$action}");
            }
            
            $this->log("Sending success response to client...");
            // Send success response
            $this->sendToClient($client['id'], json_encode([
                'type' => 'db_response',
                'action' => $action,
                'table' => $table,
                'success' => true,
                'data' => $result
            ]));
            
            // Broadcast to ALL clients including sender for realtime updates
            if (in_array($action, ['insert', 'update', 'delete'])) {
                $this->broadcastDatabaseChange($action, $table, $data, null);
                // Also broadcast fresh data to all clients
                $this->broadcastFreshData($table);
            }
            
        } catch (Exception $e) {
            $this->log("Database operation error: " . $e->getMessage());
            $this->sendToClient($client['id'], json_encode([
                'type' => 'db_error',
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Handle SELECT operations
     */
    private function handleSelect($table, $data, $conditions) {
        $this->log("=== RAW PDO SELECT DEBUG ===");
        $this->log("Table: {$table}");
        $this->log("Data: " . json_encode($data));
        $this->log("Conditions: " . json_encode($conditions));
        
        try {
            // FORCE RAW PDO - NO CACHE!
            $rawPdo = new \PDO(
                "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'],
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD']
            );
            $rawPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Build columns selection
            $columns = "*";
            if (!empty($data['columns']) && is_array($data['columns'])) {
                $columns = implode(', ', array_map(function($col) {
                    return "`" . str_replace("`", "``", $col) . "`";
                }, $data['columns']));
                $this->log("Applied columns: " . $columns);
            }
            
            // Build base query
            $sql = "SELECT {$columns} FROM `{$table}`";
            $params = [];
            
            // Apply WHERE conditions
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $condition) {
                    if (isset($condition['column'], $condition['operator'], $condition['value'])) {
                        $placeholder = ':cond_' . count($params);
                        $whereClause[] = "`{$condition['column']}` {$condition['operator']} {$placeholder}";
                        $params[$placeholder] = $condition['value'];
                        $this->log("Applied condition: {$condition['column']} {$condition['operator']} {$condition['value']}");
                    }
                }
                if (!empty($whereClause)) {
                    $sql .= " WHERE " . implode(' AND ', $whereClause);
                }
            }
            
            // Apply ORDER BY
            if (isset($data['order_by'])) {
                $direction = strtoupper($data['order_direction'] ?? 'ASC');
                $sql .= " ORDER BY `{$data['order_by']}` {$direction}";
                $this->log("Applied order: {$data['order_by']} {$direction}");
            }
            
            // Apply LIMIT
            if (isset($data['limit']) && is_numeric($data['limit'])) {
                $sql .= " LIMIT " . intval($data['limit']);
                $this->log("Applied limit: {$data['limit']}");
            }
            
            $this->log("=== EXECUTING RAW QUERY ===");
            $this->log("SQL: " . $sql);
            $this->log("Params: " . json_encode($params));
            
            $stmt = $rawPdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->log("=== RAW QUERY COMPLETED ===");
            $this->log("Raw PDO result count: " . count($result));
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Raw PDO SELECT failed: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Handle INSERT operations
     */
    private function handleInsert($table, $data) {
        return $this->model->table($table)->insert($data);
    }
    
    /**
     * Handle UPDATE operations
     */
    private function handleUpdate($table, $data, $conditions) {
        $query = $this->model->table($table);
        
        // Apply conditions
        foreach ($conditions as $condition) {
            if (isset($condition['column'], $condition['operator'], $condition['value'])) {
                $query = $query->where($condition['column'], $condition['operator'], $condition['value']);
            }
        }
        
        return $query->update($data);
    }
    
    /**
     * Handle DELETE operations
     */
    private function handleDelete($table, $conditions) {
        $query = $this->model->table($table);
        
        // Apply conditions
        foreach ($conditions as $condition) {
            if (isset($condition['column'], $condition['operator'], $condition['value'])) {
                $query = $query->where($condition['column'], $condition['operator'], $condition['value']);
            }
        }
        
        return $query->delete();
    }
    
    /**
     * Handle COUNT operations
     */
    private function handleCount($table, $conditions) {
        $query = $this->model->table($table);
        
        // Apply conditions
        foreach ($conditions as $condition) {
            if (isset($condition['column'], $condition['operator'], $condition['value'])) {
                $query = $query->where($condition['column'], $condition['operator'], $condition['value']);
            }
        }
        
        return $query->count();
    }
    
    /**
     * Broadcast database changes to other clients
     */
    private function broadcastDatabaseChange($action, $table, $data, $excludeClientId) {
        $message = json_encode([
            'type' => 'db_broadcast',
            'action' => $action,
            'table' => $table,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $this->broadcast($message, $excludeClientId);
    }
    
    /**
     * Broadcast fresh data to all clients for realtime sync
     */
    private function broadcastFreshData($table, $excludeClientId = null) {
        try {
            // Initialize model if needed
            if (!$this->model) {
                $this->model = new NexaModel();
            }
            
            // Get only the latest record (just 1, not 10)
            $freshData = $this->model->table($table)->latest()->limit(1)->get();
            
            $message = json_encode([
                'type' => 'realtime_sync',
                'table' => $table,
                'data' => $freshData,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $this->broadcast($message, $excludeClientId);
            $this->log("Broadcasted fresh data for table: {$table} - " . count($freshData) . " record(s)");
            
        } catch (Exception $e) {
            $this->log("Failed to broadcast fresh data: " . $e->getMessage());
        }
    }
    
    /**
     * Handle real-time table monitoring
     */
    public function monitorTable($table, $clientId = null) {
        if (!$this->enableDbOperations) {
            return false;
        }
        
        // Initialize model only when needed
        if (!$this->model) {
            try {
                $this->model = new NexaModel();
            } catch (Exception $e) {
                return false;
            }
        }
        
        $data = $this->model->table($table)->latest()->limit(10)->get();
        
        $message = json_encode([
            'type' => 'table_monitor',
            'table' => $table,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if ($clientId) {
            return $this->sendToClient($clientId, $message);
        } else {
            $this->broadcast($message);
            return true;
        }
    }
    
    /**
     * Initialize database monitoring
     */
    private function initializeDatabaseMonitoring() {
        if (!$this->enableDbOperations || empty($this->allowedTables)) {
            return;
        }
        
        try {
            // Initialize model if needed
            if (!$this->model) {
                $this->model = new NexaModel();
            }
            
            // Store initial record counts for each table (using fresh model)
            foreach ($this->allowedTables as $table) {
                $freshModel = new NexaModel();
                $count = $freshModel->table($table)->count();
                $this->lastDatabaseCheck[$table] = $count;
                $this->log("Initialized monitoring for table '{$table}' with {$count} records");
            }
            
            $this->lastCheckTime = time();
            
        } catch (Exception $e) {
            $this->log("Failed to initialize database monitoring: " . $e->getMessage());
        }
    }
    
    /**
     * Check for database changes
     */
    private function checkDatabaseChanges() {
        $currentTime = time();
        
        // Only check every N seconds
        if ($currentTime - $this->lastCheckTime < $this->databaseCheckInterval) {
            return;
        }
        
        $this->log("=== DATABASE MONITORING CHECK ===");
        $this->log("Enable DB: " . ($this->enableDbOperations ? 'YES' : 'NO'));
        $this->log("Allowed tables: " . implode(', ', $this->allowedTables));
        $this->log("Connected clients: " . count($this->clients));
        
        if (!$this->enableDbOperations || empty($this->allowedTables)) {
            $this->log("Skipping database check - DB disabled or no tables");
            return;
        }
        
        // Continue monitoring even without clients (untuk detect external changes)
        if (empty($this->clients)) {
            $this->log("No clients connected, but continuing monitoring...");
        }
        
        try {
            // Initialize model if needed
            if (!$this->model) {
                $this->model = new NexaModel();
            }
            // Force create fresh NexaModel every time to avoid cache
            $freshModel = new NexaModel();
            
            foreach ($this->allowedTables as $table) {
                // Test both NexaModel and raw PDO count
                $nexaCount = $freshModel->table($table)->count();
                
                // Try raw PDO query as comparison
                try {
                    $rawPdo = new \PDO(
                        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'],
                        $_ENV['DB_USERNAME'],
                        $_ENV['DB_PASSWORD']
                    );
                    $stmt = $rawPdo->prepare("SELECT COUNT(*) FROM {$table}");
                    $stmt->execute();
                    $rawCount = $stmt->fetchColumn();
                    
                    $this->log("Count comparison - NexaModel: {$nexaCount}, Raw PDO: {$rawCount}");
                    
                    // Use raw PDO count as the authoritative source
                    $currentCount = $rawCount;
                } catch (Exception $e) {
                    $this->log("Raw PDO failed, using NexaModel: " . $e->getMessage());
                    $currentCount = $nexaCount;
                }
                
                $lastCount = $this->lastDatabaseCheck[$table] ?? 0;
                
                $this->log("Checking table '{$table}': current={$currentCount}, last={$lastCount}");
                
                if ($currentCount !== $lastCount) {
                    $this->log("🔥 Table '{$table}' changed from {$lastCount} to {$currentCount} records");
                    
                    // Broadcast fresh data to subscribed clients
                    $this->broadcastFreshData($table);
                    
                    // Update last count
                    $this->lastDatabaseCheck[$table] = $currentCount;
                } else {
                    $this->log("Table '{$table}' unchanged: {$currentCount} records");
                }
            }
            
            $this->lastCheckTime = $currentTime;
            
        } catch (Exception $e) {
            $this->log("Failed to check database changes: " . $e->getMessage());
        }
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats($clientId) {
        if (!$this->enableDbOperations) {
            return $this->sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => 'Database operations are disabled'
            ]));
        }
        
        // Initialize model only when needed
        if (!$this->model) {
            try {
                $this->model = new NexaModel();
            } catch (Exception $e) {
                return $this->sendToClient($clientId, json_encode([
                    'type' => 'error',
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ]));
            }
        }
        
        try {
            $stats = [
                'database_info' => $this->model->getDatabaseInfo(),
                'tables' => $this->model->getTablesInfo(),
                'health' => $this->model->healthCheck()
            ];
            
            $this->sendToClient($clientId, json_encode([
                'type' => 'db_stats',
                'data' => $stats
            ]));
            
        } catch (Exception $e) {
            $this->sendToClient($clientId, json_encode([
                'type' => 'error',
                'message' => 'Failed to get database stats: ' . $e->getMessage()
            ]));
        }
    }
    
    /**
     * Start WebSocket server
     */
    public function start() {
        try {
            // Create socket
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($this->socket === false) {
                throw new Exception("Socket creation failed: " . socket_strerror(socket_last_error()));
            }
            
            // Set socket options
            socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
            
            // Bind socket
            if (!socket_bind($this->socket, $this->host, $this->port)) {
                throw new Exception("Socket bind failed: " . socket_strerror(socket_last_error($this->socket)));
            }
            
            // Listen for connections
            if (!socket_listen($this->socket, 20)) {
                throw new Exception("Socket listen failed: " . socket_strerror(socket_last_error($this->socket)));
            }
            
            $this->isRunning = true;
            $this->log("WebSocket server started on {$this->host}:{$this->port}");
            if ($this->enableDbOperations) {
                $this->log("Database operations enabled for tables: " . implode(', ', $this->allowedTables ?: ['ALL']));
            }
            
            // Initialize database monitoring
            $this->initializeDatabaseMonitoring();
            
            // Main server loop
            while ($this->isRunning) {
                $this->acceptConnections();
                $this->handleClients();
                $this->checkDatabaseChanges();
                usleep(10000); // 10ms delay
            }
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage());
            $this->stop();
        }
    }
    
    /**
     * Accept new connections
     */
    private function acceptConnections() {
        $read = [$this->socket];
        $write = null;
        $except = null;
        
        if (socket_select($read, $write, $except, 0) > 0) {
            foreach ($read as $socket) {
                if ($socket === $this->socket) {
                    $client = socket_accept($this->socket);
                    if ($client !== false) {
                        $this->addClient($client);
                    }
                }
            }
        }
    }
    
    /**
     * Handle existing clients
     */
    private function handleClients() {
        if (empty($this->clients)) {
            return;
        }
        
        $read = array_column($this->clients, 'socket');
        $write = null;
        $except = null;
        
        if (socket_select($read, $write, $except, 0) > 0) {
            foreach ($read as $socket) {
                $client = $this->getClientBySocket($socket);
                if ($client) {
                    $this->handleClientData($client);
                }
            }
        }
    }
    
    /**
     * Add new client
     */
    private function addClient($socket) {
        $clientId = uniqid();
        $this->clients[$clientId] = [
            'id' => $clientId,
            'socket' => $socket,
            'handshaked' => false,
            'connected_at' => time(),
            'subscriptions' => [],
            'table_subscriptions' => []
        ];
        
        $this->log("New client connected: {$clientId}");
    }
    
    /**
     * Handle client data
     */
    private function handleClientData($client) {
        $data = socket_read($client['socket'], 2048);
        
        if ($data === false || $data === '') {
            $this->removeClient($client['id']);
            return;
        }
        
        if (!$client['handshaked']) {
            $this->performHandshake($client, $data);
        } else {
            $message = $this->decodeFrame($data);
            if ($message !== false) {
                $this->onMessage($client, $message);
            }
        }
    }
    
    /**
     * Perform WebSocket handshake
     */
    private function performHandshake($client, $request) {
        $lines = explode("\r\n", $request);
        $headers = [];
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        if (!isset($headers['Sec-WebSocket-Key'])) {
            $this->removeClient($client['id']);
            return;
        }
        
        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
        
        socket_write($client['socket'], $response);
        
        $this->clients[$client['id']]['handshaked'] = true;
        $this->log("Handshake completed for client: {$client['id']}");
        
        $this->onConnect($client);
    }
    
    /**
     * Decode WebSocket frame
     */
    private function decodeFrame($data) {
        if (strlen($data) < 2) {
            return false;
        }
        
        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        
        $fin = ($firstByte & 0x80) === 0x80;
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7F;
        
        $offset = 2;
        
        if ($payloadLength === 126) {
            $payloadLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            $payloadLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }
        
        if ($masked) {
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }
        
        $payload = substr($data, $offset, $payloadLength);
        
        if ($masked) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }
        
        return $payload;
    }
    
    /**
     * Encode WebSocket frame
     */
    private function encodeFrame($message, $opcode = 0x1) {
        $length = strlen($message);
        $frame = chr(0x80 | $opcode);
        
        if ($length < 126) {
            $frame .= chr($length);
        } elseif ($length < 65536) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        $frame .= $message;
        
        return $frame;
    }
    
    /**
     * Send message to client
     */
    public function sendToClient($clientId, $message) {
        if (!isset($this->clients[$clientId])) {
            return false;
        }
        
        $client = $this->clients[$clientId];
        if (!$client['handshaked']) {
            return false;
        }
        
        $frame = $this->encodeFrame($message);
        $result = socket_write($client['socket'], $frame);
        
        return $result !== false;
    }
    
    /**
     * Broadcast message to all clients
     */
    public function broadcast($message, $excludeClientId = null) {
        $sentCount = 0;
        $this->log("=== BROADCASTING MESSAGE ===");
        $this->log("Message: " . substr($message, 0, 100) . "...");
        $this->log("Total clients: " . count($this->clients));
        $this->log("Exclude client: " . ($excludeClientId ?? 'none'));
        
        foreach ($this->clients as $client) {
            if ($client['handshaked'] && $client['id'] !== $excludeClientId) {
                $this->sendToClient($client['id'], $message);
                $sentCount++;
                $this->log("Sent to client: " . $client['id']);
            } else {
                $this->log("Skipped client: " . $client['id'] . " (handshaked: " . ($client['handshaked'] ? 'yes' : 'no') . ")");
            }
        }
        
        $this->log("Broadcast sent to {$sentCount} clients");
        $this->log("=== BROADCAST COMPLETED ===");
    }
    
    /**
     * Remove client
     */
    public function removeClient($clientId) {
        if (isset($this->clients[$clientId])) {
            $client = $this->clients[$clientId];
            socket_close($client['socket']);
            unset($this->clients[$clientId]);
            
            $this->log("Client disconnected: {$clientId}");
            $this->onDisconnect($client);
        }
    }
    
    /**
     * Get client by socket
     */
    private function getClientBySocket($socket) {
        foreach ($this->clients as $client) {
            if ($client['socket'] === $socket) {
                return $client;
            }
        }
        return null;
    }
    
    /**
     * Get all connected clients
     */
    public function getClients() {
        return $this->clients;
    }
    
    /**
     * Get client count
     */
    public function getClientCount() {
        return count($this->clients);
    }
    
    /**
     * Stop WebSocket server
     */
    public function stop() {
        $this->isRunning = false;
        
        // Close all client connections
        foreach ($this->clients as $client) {
            socket_close($client['socket']);
        }
        $this->clients = [];
        
        // Close server socket
        if ($this->socket) {
            socket_close($this->socket);
        }
        
        $this->log("WebSocket server stopped");
    }
    
    /**
     * Subscribe client to table updates
     */
    private function subscribeToTable($clientId, $table) {
        if (isset($this->clients[$clientId])) {
            if (!in_array($table, $this->clients[$clientId]['table_subscriptions'])) {
                $this->clients[$clientId]['table_subscriptions'][] = $table;
                $this->log("Client {$clientId} subscribed to table: {$table}");
                
                // Send current data to newly subscribed client
                $this->sendCurrentTableData($clientId, $table);
                
                $this->sendToClient($clientId, json_encode([
                    'type' => 'subscription_success',
                    'table' => $table,
                    'message' => "Subscribed to table: {$table}"
                ]));
            }
        }
    }
    
    /**
     * Unsubscribe client from table updates
     */
    private function unsubscribeFromTable($clientId, $table) {
        if (isset($this->clients[$clientId])) {
            $subscriptions = &$this->clients[$clientId]['table_subscriptions'];
            $key = array_search($table, $subscriptions);
            if ($key !== false) {
                unset($subscriptions[$key]);
                $subscriptions = array_values($subscriptions); // Re-index array
                $this->log("Client {$clientId} unsubscribed from table: {$table}");
                
                $this->sendToClient($clientId, json_encode([
                    'type' => 'unsubscription_success',
                    'table' => $table,
                    'message' => "Unsubscribed from table: {$table}"
                ]));
            }
        }
    }
    
    /**
     * Send current table data to client
     */
    private function sendCurrentTableData($clientId, $table) {
        try {
            if (!$this->model) {
                $this->model = new NexaModel();
            }
            
            $data = $this->model->table($table)->latest()->limit(10)->get();
            
            $this->sendToClient($clientId, json_encode([
                'type' => 'table_data',
                'table' => $table,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            
        } catch (Exception $e) {
            $this->log("Failed to send current table data: " . $e->getMessage());
        }
    }
    
    /**
     * Event: On client connect
     */
    protected function onConnect($client) {
        // Send welcome message with available features
        $welcomeMessage = json_encode([
            'type' => 'welcome',
            'client_id' => $client['id'],
            'features' => [
                'database' => $this->enableDbOperations,
                'allowed_tables' => $this->allowedTables,
                'server_time' => date('Y-m-d H:i:s')
            ]
        ]);
        
        $this->sendToClient($client['id'], $welcomeMessage);
        $this->log("Client {$client['id']} connected and welcomed");
    }
    
    /**
     * Event: On message received
     */
    protected function onMessage($client, $message) {
        $this->log("Message from {$client['id']}: {$message}");
        
        // Try to parse as JSON for structured commands
        $decodedMessage = json_decode($message, true);
        $this->log("JSON decode result: " . ($decodedMessage ? 'SUCCESS' : 'FAILED'));
        
        if ($decodedMessage && isset($decodedMessage['type'])) {
            $this->log("Message type: " . $decodedMessage['type']);
            switch ($decodedMessage['type']) {
                case 'db_operation':
                    $this->log("Calling handleDatabaseOperation...");
                    $this->handleDatabaseOperation($client, $decodedMessage);
                    $this->log("handleDatabaseOperation completed");
                    break;
                    
                case 'monitor_table':
                    if (isset($decodedMessage['table'])) {
                        $this->monitorTable($decodedMessage['table'], $client['id']);
                    }
                    break;
                    
                case 'db_stats':
                    $this->getDatabaseStats($client['id']);
                    break;
                    
                case 'subscribe_table':
                    $this->log("🔔 SUBSCRIPTION REQUEST from {$client['id']} for table: " . ($decodedMessage['table'] ?? 'NULL'));
                    if (isset($decodedMessage['table'])) {
                        $this->subscribeToTable($client['id'], $decodedMessage['table']);
                    }
                    break;
                    
                case 'unsubscribe_table':
                    if (isset($decodedMessage['table'])) {
                        $this->unsubscribeFromTable($client['id'], $decodedMessage['table']);
                    }
                    break;
                    
                case 'ping':
                    $this->sendToClient($client['id'], json_encode(['type' => 'pong']));
                    break;
                    
                default:
                    // Regular message, broadcast to other clients
                    $this->broadcast("Client {$client['id']}: {$message}", $client['id']);
                    break;
            }
        } else {
            // Regular text message, broadcast to other clients
            $this->broadcast("Client {$client['id']}: {$message}", $client['id']);
        }
    }
    
    /**
     * Event: On client disconnect
     */
    protected function onDisconnect($client) {
        // Notify other clients about disconnection
        $this->broadcast(json_encode([
            'type' => 'client_disconnected',
            'client_id' => $client['id'],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        $this->log("Client {$client['id']} disconnected");
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
    
    /**
     * Send ping to client
     */
    public function ping($clientId) {
        if (!isset($this->clients[$clientId])) {
            return false;
        }
        
        $client = $this->clients[$clientId];
        if (!$client['handshaked']) {
            return false;
        }
        
        $frame = $this->encodeFrame('ping', 0x9);
        return socket_write($client['socket'], $frame) !== false;
    }
    
    /**
     * Check if server is running
     */
    public function isRunning() {
        return $this->isRunning;
    }
    
    /**
     * Get server info
     */
    public function getServerInfo() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'running' => $this->isRunning,
            'clients' => count($this->clients),
            'database_enabled' => $this->enableDbOperations,
            'allowed_tables' => $this->allowedTables
        ];
    }
}