<?php

namespace App\Models;

class Subcategory extends BaseModel
{
    protected $table = 'subcategories';

    /**
     * Get subcategories by category
     */
    public function getByCategory($categoryId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE category_id = :category_id AND is_active = 1 
                ORDER BY display_order ASC, name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Get subcategory by slug
     */
    public function findBySlug($categoryId, $slug)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE category_id = :category_id AND slug = :slug LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'slug' => $slug
        ]);
        return $stmt->fetch();
    }

    /**
     * Get subcategory with product count
     */
    public function getWithProductCount($categoryId)
    {
        $sql = "SELECT s.*, COUNT(p.id) as product_count 
                FROM {$this->table} s
                LEFT JOIN products p ON s.id = p.subcategory_id AND p.is_active = 1
                WHERE s.category_id = :category_id AND s.is_active = 1
                GROUP BY s.id
                ORDER BY s.display_order ASC, s.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll();
    }
}