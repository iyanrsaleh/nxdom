<?php
namespace App\Models;
use App\System\NexaModel;
/**
 * Model Oauth
 * 
 * Kelas ini mendemonstrasikan cara menggunakan sistem database melalui Controller.
 * Menggunakan storage() method yang tersedia di Controller untuk operasi database.
 */
class Oauth extends NexaModel {
    private static $table = 'user';
    /**
     * Get user data for signin
     * 
     * @param array|string $data Email string or array containing email
     * @return array|null
     */
    public function signin($data): ?array {
        if (!is_array($data) || !isset($data['email']) || !isset($data['password'])) {
            return null;
        }
        $user = $this->Storage(self::$table)
                ->select(['*'])
                ->where('email', $data['email'])
                ->first();

        if (!$user) return null;

        $stored = $user['password'] ?? '';
        $valid = (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$'))
            ? password_verify($data['password'], $stored)
            : ($stored === $data['password']);

        return $valid ? $user : null;
    }

    /**
     * Create new user for signup
     * 
     * @param array $data
     * @return mixed
     */
    public function signup($data) : ?array {
        $insertData = [
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => 'user',
            'role' => 'user',
            'row' => '1',
            'gender' => $data['gender'] ?? 'male',
            'avatar' => $data['avatar'] ?? 'images/pria.png'
        ];
        try {
            return $this->Storage(self::$table)->insert($insertData);
        } catch (\Exception $e) {
            return false;
        }
    }
}
