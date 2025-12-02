<?php

namespace App\Models;

use Core\Database;

class Cart extends BaseModel
{
    protected $table = 'cart_items';
    protected $primaryKey = 'id';

    /**
     * Get cart items for a user
     */
    public function getCartItems($userId)
    {
        $sql = "SELECT 
                ci.*,
                p.id as product_id,
                p.name as product_name,
                p.slug as product_slug,
                p.price,
                p.sale_price,
                p.stock_quantity,
                p.is_active,
                pi.image_path,
                b.name as brand_name,
                m.name as model_name
            FROM {$this->table} ci
            INNER JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN models m ON p.model_id = m.id
            WHERE ci.user_id = :user_id
            ORDER BY ci.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get cart count for a user
     */
    public function getCartCount($userId)
    {
        $sql = "SELECT SUM(quantity) as total FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Add item to cart
     */
    public function addItem($userId, $productId, $quantity = 1)
    {
        // Check if item already exists
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND product_id = :product_id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            return $this->updateQuantity($existing['id'], $newQuantity);
        } else {
            // Insert new item
            return $this->create([
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity($cartItemId, $quantity)
    {
        if ($quantity <= 0) {
            return $this->delete($cartItemId);
        }

        $sql = "UPDATE {$this->table} SET quantity = :quantity WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'quantity' => $quantity,
            'id' => $cartItemId
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem($cartItemId, $userId)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $cartItemId,
            'user_id' => $userId
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clearCart($userId)
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Get cart totals
     */
    public function getCartTotals($userId)
    {
        $items = $this->getCartItems($userId);
        
        $subtotal = 0;
        $totalItems = 0;

        foreach ($items as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
            $totalItems += $item['quantity'];
        }

        return [
            'subtotal' => $subtotal,
            'total_items' => $totalItems,
            'shipping' => 0, // Free shipping or calculate based on rules
            'total' => $subtotal
        ];
    }

    /**
     * Check if item exists in cart
     */
    public function itemExists($userId, $productId)
    {
        $sql = "SELECT id FROM {$this->table} 
                WHERE user_id = :user_id AND product_id = :product_id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
        return $stmt->fetch() !== false;
    }

    /**
     * Validate cart items (check stock, availability)
     */
    public function validateCart($userId)
    {
        $items = $this->getCartItems($userId);
        $errors = [];

        foreach ($items as $item) {
            // Check if product is active
            if (!$item['is_active']) {
                $errors[] = "{$item['product_name']} is no longer available.";
            }

            // Check stock
            if ($item['quantity'] > $item['stock_quantity']) {
                $errors[] = "{$item['product_name']} has only {$item['stock_quantity']} items in stock.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}