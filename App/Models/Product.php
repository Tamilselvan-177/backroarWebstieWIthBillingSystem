<?php

namespace App\Models;

class Product extends BaseModel
{
    protected $table = 'products';

    public function getColumns(): array
    {
        try {
            $stmt = $this->db->query("DESCRIBE {$this->table}");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => $r['Field'], $rows);
        } catch (\Exception $e) {
            return [
                'id','name','category_id','subcategory_id','brand_id','model_id','store_id',
                'price','store_price','sale_price','stock_quantity','barcode','gst_percent','is_featured',
                'is_active','no_store_stock','description','slug','created_at','updated_at'
            ];
        }
    }
    public function filterColumns(array $data): array
    {
        $columns = $this->getColumns();
        return array_intersect_key($data, array_flip($columns));
    }

    /**
     * Get products with filters and pagination
     */
    public function getFiltered($filters = [], $page = 1, $perPage = 24)
    {
        $conditions = ["p.is_active = 1"];
        $params = [];

        // Category filter
        if (!empty($filters['category_id'])) {
            $conditions[] = "p.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }

        // Subcategory filter
        if (!empty($filters['subcategory_id'])) {
            $conditions[] = "p.subcategory_id = :subcategory_id";
            $params['subcategory_id'] = $filters['subcategory_id'];
        }

        // Brand filter
        if (!empty($filters['brand_id'])) {
            $conditions[] = "p.brand_id = :brand_id";
            $params['brand_id'] = $filters['brand_id'];
        }

        // Model filter
        if (!empty($filters['model_id'])) {
            $conditions[] = "p.model_id = :model_id";
            $params['model_id'] = $filters['model_id'];
        }

        // Price range filter
        if (!empty($filters['min_price'])) {
            $conditions[] = "p.price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $conditions[] = "p.price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $conditions[] = "(p.name LIKE :search OR p.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Build WHERE clause
        $where = implode(' AND ', $conditions);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} p WHERE {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // Get products
        $offset = ($page - 1) * $perPage;
        $orderBy = $filters['sort'] ?? 'p.created_at DESC';

        $sql = "SELECT p.*, 
                       c.name as category_name, c.slug as category_slug,
                       s.name as subcategory_name,
                       b.name as brand_name, b.slug as brand_slug,
                       m.name as model_name,
                       pi.image_path
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories s ON p.subcategory_id = s.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN models m ON p.model_id = m.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE {$where}
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        return [
            'products' => $products,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get product by slug
     */
    public function findBySlug($slug)
    {
        $sql = "SELECT p.*, 
                       c.name as category_name, c.slug as category_slug,
                       s.name as subcategory_name,
                       b.name as brand_name, b.slug as brand_slug,
                       m.name as model_name,
                       st.name as store_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories s ON p.subcategory_id = s.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN models m ON p.model_id = m.id
                LEFT JOIN stores st ON p.store_id = st.id
                WHERE p.slug = :slug AND p.is_active = 1
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch();
    }

    /**
     * Get product images
     */
    public function getImages($productId)
    {
        $sql = "SELECT * FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, display_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll();
    }

    public function findBySku(string $sku)
    {
        $sql = "SELECT * FROM {$this->table} WHERE sku = :sku LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sku' => $sku]);
        return $stmt->fetch();
    }

    public function addImage($productId, $imagePath, $isPrimary = 0, $displayOrder = 0)
    {
        $sql = "INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (:product_id, :image_path, :is_primary, :display_order)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'product_id' => $productId,
            'image_path' => $imagePath,
            'is_primary' => $isPrimary ? 1 : 0,
            'display_order' => (int)$displayOrder
        ]);
        return $this->db->lastInsertId();
    }

    public function setPrimaryImage($productId, $imageId)
    {
        $reset = $this->db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id");
        $reset->execute(['product_id' => $productId]);

        $stmt = $this->db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :product_id");
        return $stmt->execute(['id' => $imageId, 'product_id' => $productId]);
    }

    public function deleteImage($imageId)
    {
        $stmt = $this->db->prepare("SELECT * FROM product_images WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $imageId]);
        $image = $stmt->fetch();
        if (!$image) {
            return false;
        }

        \App\Helpers\ImageUploader::deletePaths([$image['image_path'], self::thumbPathFromImage($image['image_path'])]);

        $del = $this->db->prepare("DELETE FROM product_images WHERE id = :id");
        $ok = $del->execute(['id' => $imageId]);

        if ($ok && $image['is_primary']) {
            $stmt2 = $this->db->prepare("SELECT id FROM product_images WHERE product_id = :pid ORDER BY created_at ASC LIMIT 1");
            $stmt2->execute(['pid' => $image['product_id']]);
            $next = $stmt2->fetch();
            if ($next) {
                $this->setPrimaryImage($image['product_id'], $next['id']);
            }
        }

        return $ok;
    }

    private static function thumbPathFromImage($imagePath)
    {
        $imagePath = ltrim($imagePath, '/');
        $parts = explode('/', $imagePath);
        // Expect: images/{batch}/{file}
        if (count($parts) >= 3 && $parts[0] === 'images') {
            $batch = $parts[1];
            $file = $parts[2];
            return '/images/' . $batch . '/thumbs/' . $file;
        }
        return null;
    }

    public function updateImageOrders($productId, $orderedIds)
    {
        if (is_string($orderedIds)) {
            $orderedIds = array_filter(array_map('trim', explode(',', $orderedIds)));
        }
        if (!is_array($orderedIds) || empty($orderedIds)) {
            return false;
        }

        $order = 0;
        foreach ($orderedIds as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }
            $stmt = $this->db->prepare("UPDATE product_images SET display_order = :order WHERE id = :id AND product_id = :pid");
            $stmt->execute(['order' => $order, 'id' => $id, 'pid' => (int)$productId]);
            $order++;
        }
        return true;
    }

    /**
     * Get featured products
     */
public function getFeatured($limit = 8)
{
    $sql = "SELECT 
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                pi.image_path
            FROM {$this->table} p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_images pi 
                ON p.id = pi.product_id AND pi.is_primary = 1
            WHERE p.is_featured = 1 
              AND p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT {$limit}";

    return $this->query(sql: $sql);
}


    /**
     * Get related products
     */
    public function getRelated($productId, $categoryId, $limit = 4)
    {
        $sql = "SELECT p.*, pi.image_path
                FROM {$this->table} p
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE p.category_id = :category_id 
                AND p.id != :product_id 
                AND p.is_active = 1
                ORDER BY RAND()
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'product_id' => $productId
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Search products
     */
    public function search($keyword, $page = 1, $perPage = 24)
    {
        $keyword = '%' . $keyword . '%';
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} 
                     WHERE (name LIKE :keyword OR description LIKE :keyword) 
                     AND is_active = 1";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute(['keyword' => $keyword]);
        $total = $stmt->fetch()['total'];

        // Get products
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, 
                       c.name as category_name,
                       b.name as brand_name,
                       pi.image_path
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE (p.name LIKE :keyword OR p.description LIKE :keyword) 
                AND p.is_active = 1
                ORDER BY p.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['keyword' => $keyword]);
        $products = $stmt->fetchAll();

        return [
            'products' => $products,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    public function searchForStore(string $keyword, int $storeId, int $limit = 20): array
    {
        $kw = '%' . $keyword . '%';
                $sql = "SELECT 
                                        p.*, 
                                        COALESCE(p.store_price, p.sale_price, p.price) AS price,
                                        pi.image_path, 
                                        ss.quantity AS available
                                FROM store_stock ss
                                JOIN products p ON p.id = ss.product_id
                                LEFT JOIN product_images pi 
                                        ON p.id = pi.product_id AND pi.is_primary = 1
                                WHERE ss.store_id = :store_id
                                    AND ss.quantity > 0
                                    AND p.no_store_stock = 0
                                    AND p.name LIKE :kw
                                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['store_id' => $storeId, 'kw' => $kw]);
        return $stmt->fetchAll();
    }

    public function searchForPosStore(string $keyword, int $storeId, int $limit = 20): array
    {
        $kw = '%' . $keyword . '%';
        $sql = "SELECT 
                    p.id, p.name,
                    COALESCE(p.store_price, p.sale_price, p.price) AS price,
                    COALESCE(p.gst_percent, 0) AS gst_percent,
                    ss.quantity AS available,
                    pi.image_path
                FROM {$this->table} p
                LEFT JOIN store_stock ss
                    ON ss.product_id = p.id AND ss.store_id = :store_id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE p.is_active = 1
                  AND (p.name LIKE :kw OR p.sku LIKE :kw)
                  AND (
                        (p.no_store_stock = 1 AND p.store_id = :store_id)
                        OR
                        (p.no_store_stock = 0 AND COALESCE(ss.quantity, 0) > 0)
                      )
                ORDER BY p.name ASC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['store_id' => $storeId, 'kw' => $kw]);
        return $stmt->fetchAll();
    }
}
