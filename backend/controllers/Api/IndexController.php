<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\System\NexaController;

/**
 * IndexController - API Controller for handling project and task-related endpoints
 * 
 * This controller provides endpoints for:
 * - Health check and index data
 * - User tasks management
 * - Task logs and validation
 * - Project information retrieval
 */
class IndexController extends NexaController
{
    /**
     * Health check endpoint and main index data retrieval
     * 
     * @return array Returns merged index data for project ID 56
     */
  
    public function index(): array
    {

 
        return [
            'status' => 'success',
            'message' => 'Hello from API!',
            'timestamp' => time(),
            'greeting' => 'Hello World!'
        ];


    }
    
    /**
     * Hello endpoint
     */
    public function hello(): array
    {
        return [
            'status' => 'success',
            'message' => 'Hello from API!',
            'timestamp' => time(),
            'greeting' => 'Hello World!'
        ];
    }
    
    /**
     * Error test endpoint
     */
    public function error(): array
    {
        return [
            'status' => 'error',
            'message' => 'This is a test error response',
            'error_code' => 'TEST_ERROR',
            'timestamp' => time()
        ];
    }
    
    /**
     * Created endpoint for POST requests
     */
    public function created($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Test data created successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
    }

    /**
     * Red endpoint
     */
    public function red($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Test data createdsssssssssssss successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
        
    }
    /**
     * Updated endpoint for PUT/PATCH requests
     */
    public function updated($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Test data updated successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
    }
    
    /**
     * Deleted endpoint for DELETE requests
     */
    public function deleted($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Test data deleted successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
    }
} 