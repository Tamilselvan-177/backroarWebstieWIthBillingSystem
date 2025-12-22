<?php

namespace App\Models;

class Category extends BaseModel
{
    protected $table = 'categories';

    /**
     * Get all active categories
     */
    public function getActive()
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
        return $this->query($sql);
    }

    /**
     * Get category by slug
     */
    public function findBySlug($slug)
    {
        return $this->whereFirst('slug', $slug);
    }

    /**
     * Get category with product count
     */
    public function getWithProductCount()
    {
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM {$this->table} c
                LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.name ASC";
        return $this->query($sql);
    }

    /**
     * Get category with subcategories
     */
    public function getWithSubcategories($categoryId)
    {
        $category = $this->find($categoryId);
        if (!$category) {
            return null;
        }

        $sql = "SELECT * FROM subcategories WHERE category_id = :category_id AND is_active = 1 ORDER BY display_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        $category['subcategories'] = $stmt->fetchAll();

        return $category;
    }
}