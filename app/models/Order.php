<?php

namespace App\Models;

class Order extends BaseModel
{
    protected $table = 'orders';
    protected $primaryKey = 'id';

    /**
     * Create new order
     */
    public function createOrder($userId, $cartItems, $address, $totals, $couponId = null, $couponCode = null, $discountAmount = 0)
{
    try {
        $this->db->beginTransaction();

        // Generate unique order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Build full shipping address line
        $shippingAddress = $address['address_line1'];
        if (!empty($address['address_line2'])) {
            $shippingAddress .= ', ' . $address['address_line2'];
        }

        // Insert order
        $orderData = [
            'user_id'         => $userId,
            'order_number'    => $orderNumber,
            'subtotal'        => $totals['subtotal'],
            'shipping_charge' => $totals['shipping'],
            'total_amount'    => $totals['total'],
            'payment_method'  => 'COD',
            'payment_status'  => 'Pending',
            'order_status'    => 'Pending',

            // Shipping
            'shipping_name'    => $address['full_name'],
            'shipping_phone'   => $address['phone'],
            'shipping_address' => $shippingAddress,
            'shipping_city'    => $address['city'],
            'shipping_state'   => $address['state'],
            'shipping_pincode' => $address['pincode'],

            // Coupon support (these columns exist in `orders`)
            'coupon_id'        => $couponId,
            'coupon_code'      => $couponCode,
            'discount_amount'  => $discountAmount,
        ];

        // Uses BaseModel::create() to insert and return new ID
        $orderId = $this->create($orderData);

        // Insert order items
        foreach ($cartItems as $item) {
            $price    = $item['sale_price'] ?? $item['price'];
            $subtotal = $price * $item['quantity'];

            $this->insertOrderItem([
                'order_id'     => $orderId,
                'product_id'   => $item['product_id'],
                'product_name' => $item['product_name'],
                'price'        => $price,
                'quantity'     => $item['quantity'],
                'subtotal'     => $subtotal,
            ]);

            // Update stock
            $this->updateProductStock($item['product_id'], $item['quantity']);
        }

        $this->db->commit();
        return $orderId;

    } catch (\Exception $e) {
        $this->db->rollBack();

        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $msg  = "[createOrder] " . date('Y-m-d H:i:s') . "\n";
        $msg .= "exception=" . $e->getMessage() . "\n";
        $msg .= $e->getTraceAsString() . "\n\n";
        file_put_contents($logDir . '/order_errors.log', $msg, FILE_APPEND);

        return false;
    }
}

    public function createPendingOrder($userId, $cartItems, $address, $totals)
    {
        try {
            $this->db->beginTransaction();

            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $orderData = [
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'subtotal' => $totals['subtotal'],
                'shipping_charge' => $totals['shipping'],
                'total_amount' => $totals['total'],
                'payment_method' => 'Online',
                'payment_status' => 'Pending',
                'order_status' => 'Pending',
                'shipping_name' => $address['full_name'],
                'shipping_phone' => $address['phone'],
                'shipping_address' => $address['address_line1'] . ($address['address_line2'] ? ', ' . $address['address_line2'] : ''),
                'shipping_city' => $address['city'],
                'shipping_state' => $address['state'],
                'shipping_pincode' => $address['pincode']
            ];

            $orderId = $this->create($orderData);

            foreach ($cartItems as $item) {
                $price = $item['sale_price'] ?? $item['price'];
                $subtotal = $price * $item['quantity'];
                $this->insertOrderItem([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal
                ]);
            }

            $this->db->commit();
            return $orderId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function confirmPaidOrder($orderId, $transactionId)
    {
        try {
            $this->db->beginTransaction();

            $items = $this->getOrderItems($orderId);
            foreach ($items as $item) {
                $this->updateProductStock($item['product_id'], $item['quantity']);
            }

            $sql = "UPDATE {$this->table} SET payment_status='Paid', order_status='Confirmed', updated_at=NOW() WHERE id=:id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $orderId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Insert order item
     */
    private function insertOrderItem($data)
    {
        $sql = "INSERT INTO order_items 
                (order_id, product_id, product_name, price, quantity, subtotal) 
                VALUES 
                (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Update product stock
     */
    private function updateProductStock($productId, $quantity)
    {
        $sql = "UPDATE products 
                SET stock_quantity = stock_quantity - :quantity 
                WHERE id = :product_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'quantity' => $quantity,
            'product_id' => $productId
        ]);
    }

    /**
     * Get user orders
     */
    public function getUserOrders($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute(['user_id' => $userId]);
        $total = $stmt->fetch()['total'];

        // Get orders
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $orders = $stmt->fetchAll();

        return [
            'orders' => $orders,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get order by ID
     */
    public function getOrderById($orderId, $userId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $params = ['id' => $orderId];

        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }


    
    /**
     * Get order by order number
     */
    public function getOrderByNumber($orderNumber, $userId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE order_number = :order_number";
        $params = ['order_number' => $orderNumber];

        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId)
    {
        $sql = "SELECT oi.*, p.slug as product_slug, pi.image_path
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                WHERE oi.order_id = :order_id
                ORDER BY oi.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($orderId, $status)
    {
        $sql = "UPDATE {$this->table} 
                SET order_status = :status, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'id' => $orderId
        ]);
    }

    /**
     * Cancel order
     */
    public function cancelOrder($orderId, $userId)
    {
        $order = $this->getOrderById($orderId, $userId);
        
        if (!$order) {
            return false;
        }

        // Only allow cancellation if order is Pending or Confirmed
        if (!in_array($order['order_status'], ['Pending', 'Confirmed'])) {
            return false;
        }

        // Update order status
        $sql = "UPDATE {$this->table} 
                SET order_status = 'Cancelled', updated_at = NOW() 
                WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        $cancelled = $stmt->execute([
            'id' => $orderId,
            'user_id' => $userId
        ]);

        if ($cancelled) {
            // Restore product stock
            $items = $this->getOrderItems($orderId);
            foreach ($items as $item) {
                $this->restoreProductStock($item['product_id'], $item['quantity']);
            }
        }

        return $cancelled;
    }

    /**
     * Restore product stock
     */
    private function restoreProductStock($productId, $quantity)
    {
        $sql = "UPDATE products 
                SET stock_quantity = stock_quantity + :quantity 
                WHERE id = :product_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'quantity' => $quantity,
            'product_id' => $productId
        ]);
    }
    
}
