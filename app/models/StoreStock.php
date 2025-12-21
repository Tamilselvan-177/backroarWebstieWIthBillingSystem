<?php

namespace App\Models;

use PDO;

class StoreStock extends BaseModel
{
    protected $table = 'store_stock';

    public function getByStoreAndProduct(int $storeId, int $productId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE store_id = :sid AND product_id = :pid LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId, 'pid' => $productId]);
        return $stmt->fetch();
    }

    public function getTotalForProduct(int $productId)
    {
        $sql = "SELECT COALESCE(SUM(quantity),0) AS total FROM {$this->table} WHERE product_id = :pid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    public function adjustStock(int $storeId, int $productId, int $delta)
    {
        $existing = $this->getByStoreAndProduct($storeId, $productId);
        if ($existing) {
            $newQty = (int)$existing['quantity'] + $delta;
            if ($newQty < 0) {
                return false;
            }
            $stmt = $this->db->prepare("UPDATE {$this->table} SET quantity = :q WHERE id = :id");
            return $stmt->execute(['q' => $newQty, 'id' => (int)$existing['id']]);
        } else {
            if ($delta < 0) {
                return false;
            }
            $stmt = $this->db->prepare("INSERT INTO {$this->table} (store_id, product_id, quantity) VALUES (:sid, :pid, :q)");
            return $stmt->execute(['sid' => $storeId, 'pid' => $productId, 'q' => $delta]);
        }
    }

    public function setQuantity(int $storeId, int $productId, int $quantity)
    {
        if ($quantity < 0) {
            return false;
        }
        $existing = $this->getByStoreAndProduct($storeId, $productId);
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET quantity = :q WHERE id = :id");
            return $stmt->execute(['q' => $quantity, 'id' => (int)$existing['id']]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO {$this->table} (store_id, product_id, quantity) VALUES (:sid, :pid, :q)");
            return $stmt->execute(['sid' => $storeId, 'pid' => $productId, 'q' => $quantity]);
        }
    }
}
