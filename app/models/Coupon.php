<?php

namespace App\Models;

use PDO;

class Coupon extends BaseModel
{
    protected $table = 'coupons';
    protected $primaryKey = 'id';

    /**
     * Get table columns (MySQL)
     */
    public function getColumns(): array
    {
        try {
            $stmt = $this->db->query("DESCRIBE {$this->table}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map(fn($r) => $r['Field'], $rows);
        } catch (\Exception $e) {
            return ['id','code','is_active','valid_from','valid_to','created_at','updated_at'];
        }
    }

    

    /**
     * Filter data to only existing columns
     */
    public function filterColumns(array $data): array
    {
        $columns = $this->getColumns();
        $data = $this->mapAliases($data);
        return array_intersect_key($data, array_flip($columns));
    }

private function mapAliases(array $data): array
{
    if (isset($data['discount_type'])) {
        $val = strtoupper($data['discount_type']);
        $data['type'] = ($val === 'FIXED') ? 'FIXED' : 'PERCENT';
        unset($data['discount_type']);
    }
    if (isset($data['discount_value'])) {
        $data['value'] = $data['discount_value'];
        unset($data['discount_value']);
    }
    if (isset($data['min_purchase'])) {
        $data['min_order_amount'] = $data['min_purchase'];
        unset($data['min_purchase']);
    }
    if (isset($data['max_discount'])) {
        $data['max_discount_amount'] = $data['max_discount'];
        unset($data['max_discount']);
    }
    if (isset($data['usage_limit'])) {
        $data['usage_limit_global'] = $data['usage_limit'];
        unset($data['usage_limit']);
    }
    if (isset($data['per_user_limit'])) {
        $data['usage_limit_per_user'] = $data['per_user_limit'];
        unset($data['per_user_limit']);
    }
    return $data;
}


    

    /**
     * Find coupon by code
     */
    public function findByCode(string $code)
    {
        $columns = $this->getColumns();
        $sql = "SELECT * FROM {$this->table} WHERE UPPER(code) = UPPER(:code)";
        if (in_array('is_active', $columns)) {
            $sql .= " AND is_active = 1";
        } elseif (in_array('active', $columns)) {
            $sql .= " AND active = 1";
        }
        if (in_array('valid_from', $columns) && in_array('valid_to', $columns)) {
            $sql .= " AND NOW() BETWEEN valid_from AND valid_to";
        } elseif (in_array('start_at', $columns) && in_array('end_at', $columns)) {
            $sql .= " AND NOW() BETWEEN start_at AND end_at";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all coupons with pagination
     */
    public function getAllPaginated(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get coupons with usage count
        $sql = "SELECT c.*, 
                       COUNT(cu.id) as usage_count
                FROM {$this->table} c
                LEFT JOIN coupon_usages cu ON c.id = cu.coupon_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'coupons' => $coupons,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Check if coupon code exists
     */
    public function codeExists(string $code, int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} 
                WHERE UPPER(code) = UPPER(:code)";
        
        $params = ['code' => $code];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['cnt'] > 0;
    }

    /**
     * User usage count
     */
    public function userUsageCount(int $couponId, int $userId): int
    {
        $sql = "SELECT COUNT(*) AS cnt
                FROM coupon_usages
                WHERE coupon_id = :coupon AND user_id = :user";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'coupon' => $couponId,
            'user' => $userId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Total global usage count
     */
    public function totalUsageCount(int $couponId): int
    {
        $sql = "SELECT COUNT(*) AS cnt
                FROM coupon_usages
                WHERE coupon_id = :coupon";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['coupon' => $couponId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Record usage
     */
    public function recordUsage(int $couponId, int $userId, int $orderId): bool
    {
        $sql = "INSERT INTO coupon_usages (coupon_id, user_id, order_id)
                VALUES (:coupon, :user, :orderId)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'coupon' => $couponId,
            'user'   => $userId,
            'orderId' => $orderId
        ]);
    }

    /**
     * Get coupon statistics
     */
    public function getStatistics(int $couponId): array
    {
        $sql = "SELECT 
                    COUNT(DISTINCT cu.user_id) as unique_users,
                    COUNT(cu.id) as total_uses,
                    COALESCE(SUM(o.discount_amount), 0) as total_discount_given
                FROM coupon_usages cu
                LEFT JOIN orders o ON cu.order_id = o.id
                WHERE cu.coupon_id = :coupon_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['coupon_id' => $couponId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'unique_users' => 0,
            'total_uses' => 0,
            'total_discount_given' => 0
        ];
    }
}
