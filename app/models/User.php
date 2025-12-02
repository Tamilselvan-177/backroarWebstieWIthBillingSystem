<?php

namespace App\Models;

class User extends BaseModel
{
    protected $table = 'users';

    /**
     * Create new user
     */
    public function register($data)
    {
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // Set default role
        $data['role'] = $data['role'] ?? 'customer';
        
        return $this->create($data);
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->whereFirst('email', $email);
    }

    /**
     * Verify user credentials
     */
    public function verifyCredentials($email, $password)
    {
        $user = $this->findByEmail($email);
        
        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Check if user is active
        if (!$user['is_active']) {
            return false;
        }

        return $user;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email)
    {
        return $this->findByEmail($email) !== false;
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        $allowedFields = ['name', 'phone'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (!empty($updateData)) {
            return $this->update($userId, $updateData);
        }
        
        return false;
    }

    /**
     * Change password
     */
    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->update($userId, ['password' => $hashedPassword]);
    }

    /**
     * Get user by ID without password
     */
    public function getUserById($userId)
    {
        $sql = "SELECT id, name, email, phone, role, is_active, created_at FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch();
    }

    /**
     * Update last login
     */
    public function updateLastLogin($userId)
    {
        $sql = "UPDATE {$this->table} SET updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $userId]);
    }
}