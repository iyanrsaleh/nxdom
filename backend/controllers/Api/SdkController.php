<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\System\NexaController;

/**
 * Test Controller untuk API endpoints
 */
class SdkController extends NexaController
{
    public function index($data = [], $params = []): array
    {
    $expiration  = $params['expiration'] ?? [];
        $isExpired   = $params['expired'] ?? false;
        $neverExpires = (bool)($expiration['neverExpires'] ?? false);
        $previewDate  = $expiration['preview'] ?? null;

        // Hitung sisa hari jika ada tanggal expiry
        $daysLeft = null;
        if (!$neverExpires && !empty($previewDate)) {
            $diff = strtotime($previewDate) - time();
            $daysLeft = $diff > 0 ? (int)ceil($diff / 86400) : 0;
        }

        return [
            'status'          => $isExpired ? 'expired' : 'active',
            'expiration' => [
                'never_expires' => $neverExpires,
                'expires_at'    => $previewDate,
                'days_left'     => $neverExpires ? null : $daysLeft,
                'is_expired'    => $isExpired,
            ],
            'timestamp' => time(),
        ];
    }
    
    /**
     * Info endpoint — menampilkan detail kredensial & expiration
     */
    public function info($data = [], $params = []): array
    {
        $expiration  = $params['expiration'] ?? [];
        $isExpired   = $params['expired'] ?? false;
        $neverExpires = (bool)($expiration['neverExpires'] ?? false);
        $previewDate  = $expiration['preview'] ?? null;

        // Hitung sisa hari jika ada tanggal expiry
        $daysLeft = null;
        if (!$neverExpires && !empty($previewDate)) {
            $diff = strtotime($previewDate) - time();
            $daysLeft = $diff > 0 ? (int)ceil($diff / 86400) : 0;
        }

        return [
            'status'          => $isExpired ? 'expired' : 'active',
            'expiration' => [
                'never_expires' => $neverExpires,
                'expires_at'    => $previewDate,
                'days_left'     => $neverExpires ? null : $daysLeft,
                'is_expired'    => $isExpired,
            ],
            'timestamp' => time(),
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
     * Red endpoint PUT Update data
     */
    public function red($data = [], $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Test data PUT successfully',
            'timestamp' => time(),
            'data' => $data,
            'params' => $params
        ];
        
    }


    /**
     * Patch endpoint — partial update (PATCH requests)
     */
    public function patch($data = [], $params = []): array
    {
        $buildQuery = $params['build_query'] ?? null;

        if (empty($buildQuery)) {
            return ['status' => 'error', 'message' => 'build_query tidak tersedia', 'timestamp' => time()];
        }

        // Override build_query dengan nilai dari body request
        if (!empty($data['limit']))  $buildQuery['limit']  = (int)$data['limit'];
        if (isset($data['offset']))  $buildQuery['offset'] = (int)$data['offset'];
        if (!empty($data['where']))  $buildQuery['where']  = $data['where'];
        if (!empty($data['order']))  $buildQuery['order']  = $data['order'];

        return $this->refModels('Office')->executeOperation($buildQuery);
    }

    /**
     * Options endpoint — allowed methods info (OPTIONS requests)
     */
    public function options($data = [], $params = []): array
    {
        $expiration  = $params['expiration'] ?? [];
        $isExpired   = $params['expired'] ?? false;
        $neverExpires = (bool)($expiration['neverExpires'] ?? false);
        $previewDate  = $expiration['preview'] ?? null;

        $daysLeft = null;
        if (!$neverExpires && !empty($previewDate)) {
            $diff = strtotime($previewDate) - time();
            $daysLeft = $diff > 0 ? (int)ceil($diff / 86400) : 0;
        }

        return [
            'status'          => $isExpired ? 'expired' : 'active',
            'appid'           => $params['appid'] ?? null,
            'appname'         => $params['appname'] ?? null,
            'developer'       => $params['developer'] ?? null,
            'version'         => $params['version'] ?? null,
            'endpoint'        => $params['endpoint'] ?? null,
            'description'     => $params['description'] ?? null,
            'allowed_methods' => $params['allowed_methods'] ?? [],
            'expiration' => [
                'never_expires' => $neverExpires,
                'expires_at'    => $previewDate,
                'days_left'     => $neverExpires ? null : $daysLeft,
                'is_expired'    => $isExpired,
            ],
            'timestamp'       => time(),
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