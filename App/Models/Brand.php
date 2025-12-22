<?php

namespace App\Models;

class Brand extends BaseModel
{
    protected $table = 'brands';

    /**
     * Get all active brands
     */
    public function getActive()
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
        return $this->query($sql);
    }

    /**
     * Get brand by slug
     */
    public function findBySlug($slug)
    {
        return $this->whereFirst('slug', $slug);
    }

    /**
     * Get brands with product count
     */
    public function getWithProductCount()
    {
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM {$this->table} b
                LEFT JOIN products p ON b.id = p.brand_id AND p.is_active = 1
                WHERE b.is_active = 1
                GROUP BY b.id
                ORDER BY b.display_order ASC, b.name ASC";
        return $this->query($sql);
    }

    /**
     * Get brands for a specific category
     */
    public function getByCategoryWithCount($categoryId)
    {
        $sql = "SELECT b.*, COUNT(DISTINCT p.id) as product_count 
                FROM {$this->table} b
                INNER JOIN products p ON b.id = p.brand_id 
                WHERE p.category_id = :category_id AND p.is_active = 1 AND b.is_active = 1
                GROUP BY b.id
                ORDER BY b.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll();
    }
}
