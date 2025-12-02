<?php

namespace App\Models;

class Wishlist extends BaseModel
{
    protected $table = 'wishlists';

    public function getUserWishlist(int $userId)
    {
        $sql = "SELECT w.*, 
                       p.name, 
                       p.slug, 
                       p.sale_price, 
                       p.price,
                       pi.image_path
                FROM {$this->table} w
                INNER JOIN products p ON w.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE w.user_id = :user_id
                ORDER BY w.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function add(int $userId, int $productId, string $notes = null): bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, product_id, notes) VALUES (:user_id, :product_id, :notes)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId,
            'notes' => $notes
        ]);
    }

    public function remove(int $userId, int $productId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
    }

    public function exists(int $userId, int $productId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE user_id = :user_id AND product_id = :product_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
        return (bool)$stmt->fetchColumn();
    }
}

