<?php

namespace App\Models;

class Review extends BaseModel
{
    protected $table = 'reviews';

    public function getApprovedForProduct(int $productId)
    {
        $sql = "SELECT r.*, u.name as user_name
                FROM {$this->table} r
                INNER JOIN users u ON r.user_id = u.id
                WHERE r.product_id = :product_id AND r.status = 'Approved'
                ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll();
    }

    public function getAverageRating(int $productId)
    {
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total
                FROM {$this->table}
                WHERE product_id = :product_id AND status = 'Approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetch() ?: ['avg_rating' => null, 'total' => 0];
    }

    public function userCanReview(int $userId, int $productId): bool
    {
        // If user purchased product OR review already exists (prevent duplicates)
        $sql = "SELECT oi.id
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE o.user_id = :user_id AND oi.product_id = :product_id AND o.order_status IN ('Delivered')
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
        return (bool)$stmt->fetch();
    }

    public function createReview(array $data)
    {
        return $this->create($data);
    }

    public function updateStatus(int $reviewId, string $status)
    {
        return $this->update($reviewId, ['status' => $status]);
    }

    public function exists(int $userId, int $productId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE user_id = :user_id AND product_id = :product_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
        return (bool)$stmt->fetchColumn();
    }
}

