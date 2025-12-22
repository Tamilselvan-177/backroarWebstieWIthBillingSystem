<?php

namespace App\Models;

class Address extends BaseModel
{
    protected $table = 'addresses';
    protected $primaryKey = 'id';

    /**
     * Get all addresses for a user
     */
    public function getUserAddresses($userId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY is_default DESC, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get default address
     */
    public function getDefaultAddress($userId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND is_default = 1 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    /**
     * Add new address
     */
    public function addAddress($data)
    {
        // If this is set as default, remove default from other addresses
        if (!empty($data['is_default'])) {
            $this->removeDefaultFlag($data['user_id']);
        }

        return $this->create($data);
    }

    /**
     * Update address
     */
    public function updateAddress($addressId, $userId, $data)
    {
        // If this is set as default, remove default from other addresses
        if (!empty($data['is_default'])) {
            $this->removeDefaultFlag($userId);
        }

        // Ensure user owns this address
        $sql = "UPDATE {$this->table} SET 
                full_name = :full_name,
                phone = :phone,
                address_line1 = :address_line1,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                pincode = :pincode,
                is_default = :is_default
                WHERE id = :id AND user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? '',
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'is_default' => $data['is_default'] ?? 0,
            'id' => $addressId,
            'user_id' => $userId
        ]);
    }

    /**
     * Delete address
     */
    public function deleteAddress($addressId, $userId)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $addressId,
            'user_id' => $userId
        ]);
    }

    /**
     * Set address as default
     */
    public function setAsDefault($addressId, $userId)
    {
        // Remove default from all addresses
        $this->removeDefaultFlag($userId);

        // Set this address as default
        $sql = "UPDATE {$this->table} SET is_default = 1 
                WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $addressId,
            'user_id' => $userId
        ]);
    }

    /**
     * Remove default flag from all addresses
     */
    private function removeDefaultFlag($userId)
    {
        $sql = "UPDATE {$this->table} SET is_default = 0 WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Verify address belongs to user
     */
    public function verifyOwnership($addressId, $userId)
    {
        $sql = "SELECT id FROM {$this->table} 
                WHERE id = :id AND user_id = :user_id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $addressId,
            'user_id' => $userId
        ]);
        return $stmt->fetch() !== false;
    }
}