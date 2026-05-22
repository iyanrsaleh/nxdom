<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\System\NexaController;
/**
 * Simple ApiController untuk API endpoints
 */
class AuthController extends NexaController
{

// 1h = 1 jam
// 7d = 7 hari
// 1m = 1 bulan
// 1y = 1 tahun
// Jika tidak ada expired dalam payload, akan default ke 1h (1 jam).  
    
    /**
     * Health check endpoint
     */
    public function index(): array
    {
// Simulate user authentication
        $username = $this->getPost('username');
        $password = $this->getPost('password');

        // Example validation (replace with your actual user validation)
        if ($username === 'admin' && $password === 'password123') {
            // Create token payload
            $payload = [
                'user_id' => 1,
                'username' => $username,
                'role' => 'admin',
                'expired' => '1h'
            ];
      
            // Generate token
            $token = $this->Authorization()->generateToken($payload);

            // Return success response
            return[
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token
            ];
        } else {
            // Return error response
            return [
                'status' => 'error',
                'message' => 'Invalid credentials'
            ];
        }
       //  1. Login (Get Token):
       // curl -X POST http://localhost/proyek/api/auth \
       //   -H "Content-Type: application/x-www-form-urlencoded" \
       //   -d "username=admin&password=password123"

    }

 /**
     * Example protected endpoint that requires valid token
     */
    public function protected() {
        // Validate token
       
        $tokenData = $this->Authorization()->validateToken();
        
        if (!$tokenData) {
            return[
                'status' => 'error',
                'message' => 'Unauthorized access'
            ];
            return;
        }

        // Token is valid, we can use the token data
        $data = [
            'sensitive_data' => 'This is protected information',
            'user_info' => [
                'id' => $tokenData['user_id'],
                'username' => $tokenData['username'],
                'role' => $tokenData['role']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return[
            'status' => 'success',
            'data' => $data
        ];

// 2. Access Protected Data:
// curl http://localhost/dev/api/auth/data \
//   -H "Authorization: Bearer eyJ0IjoidGtfNDhjNTRkNDkxYjgwNzNmMyIsImUiOjE3NTQwNDM0OTJ9.GA/80F"



    }
  
    /**
     * Example endpoint to refresh token
     */
    public function refresh() {
        $tokenData = $this->Authorization()->validateToken();
        if (!$tokenData) {
            return[
                'status' => 'error',
                'message' => 'Invalid token'
            ];
            
        }

        // Create new token with existing user data
        $newPayload = [
            'user_id' => $tokenData['user_id'],
            'username' => $tokenData['username'],
            'role' => $tokenData['role']
        ];

        $newToken = $this->Authorization()->generateToken($newPayload);

        return[
            'status' => 'success',
            'message' => 'Token refreshed',
            'token' => $newToken
        ];
// 3. Refresh Token:
// curl --location 'http://localhost/proyek/api/auth/refresh' \
// --header 'Authorization: Bearer eyJpIjoxLCJ1IjoiYWRtaW4iLCJyIjoiYWRtaW4iLCJlIjoxNzUwNjg5NTE4fQ==.uPr4dEKp'
    }  
 
} 