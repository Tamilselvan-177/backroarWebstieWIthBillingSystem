<?php

namespace App\Models;

class PhoneModel extends BaseModel
{
    protected $table = 'models';

    /**
     * Get models by brand
     */
    public function getByBrand($brandId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE brand_id = :brand_id AND is_active = 1 
                ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['brand_id' => $brandId]);
        return $stmt->fetchAll();
    }

    /**
     * Get model by slug
     */
    public function findBySlug($brandId, $slug)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE brand_id = :brand_id AND slug = :slug LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'brand_id' => $brandId,
            'slug' => $slug
        ]);
        return $stmt->fetch();
    }

    /**
     * Get models with product count
     */
    public function getByBrandWithCount($brandId)
    {
        $sql = "SELECT m.*, COUNT(p.id) as product_count 
                FROM {$this->table} m
                LEFT JOIN products p ON m.id = p.model_id AND p.is_active = 1
                WHERE m.brand_id = :brand_id AND m.is_active = 1
                GROUP BY m.id
                ORDER BY m.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['brand_id' => $brandId]);
        return $stmt->fetchAll();
    }

    /**
     * Get models for specific category and brand
     */
    public function getByCategoryAndBrand($categoryId, $brandId)
    {
        $sql = "SELECT m.*, COUNT(DISTINCT p.id) as product_count 
                FROM {$this->table} m
                INNER JOIN products p ON m.id = p.model_id 
                WHERE p.category_id = :category_id 
                AND p.brand_id = :brand_id 
                AND p.is_active = 1 
                AND m.is_active = 1
                GROUP BY m.id
                ORDER BY m.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'brand_id' => $brandId
        ]);
        return $stmt->fetchAll();
    }
}