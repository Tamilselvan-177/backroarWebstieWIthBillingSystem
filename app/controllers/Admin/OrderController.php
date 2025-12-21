<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Order;

class OrderController extends BaseController
{
    private Order $orders;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->orders = new Order();
    }

    public function countOpen() {
        return count($this->all(['status' => ['pending', 'processing']]));
    }

    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)((require __DIR__ . '/../../config/app.php')['per_page'] ?? 24);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $orders = $this->orders->query($sql);
        $total = $this->orders->count();

        // Build item summaries for displayed orders
        $orderItemsSummary = [];
        $ids = array_map(static function ($o) { return (int)$o['id']; }, $orders);
        if (!empty($ids)) {
            $in = implode(',', $ids);
            $itemSql = "SELECT oi.order_id, oi.product_name, oi.quantity
            FROM order_items oi
            WHERE oi.order_id IN ({$in})";

            $rows = $this->orders->query($itemSql);
            foreach ($rows as $r) {
                $oid = (int)$r['order_id'];
                if (!isset($orderItemsSummary[$oid])) {
                    $orderItemsSummary[$oid] = [
                        'total_qty' => 0,
                        'distinct_count' => 0,
                        'preview' => []
                    ];
                }
                $orderItemsSummary[$oid]['total_qty'] += (int)$r['quantity'];
                $orderItemsSummary[$oid]['distinct_count'] += 1;
                if (count($orderItemsSummary[$oid]['preview']) < 3) {
                    $orderItemsSummary[$oid]['preview'][] = trim((string)$r['product_name']) . ' x' . (int)$r['quantity'];
                }
            }
            // Convert preview arrays to strings
            foreach ($orderItemsSummary as $oid => $sum) {
                $orderItemsSummary[$oid]['preview_str'] = implode(', ', $sum['preview']);
            }
        }

        $this->view('admin/orders/index.twig', [
            'title' => 'Orders',
            'orders' => $orders,
            'order_items_summary' => $orderItemsSummary,
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'total_pages' => max(1, (int)ceil($total / $perPage))
            ]
        ]);
    }

    // NEW: Show order details with user info and delivery address
    public function show($id)
    {
        $orderId = (int)$id;
        
        // Get order with user details
        $sql = "SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = {$orderId}";
        $order = $this->orders->query($sql);
        $order = $order[0] ?? null;

        if (!$order) {
            \flash('error', 'Order not found');
            return $this->redirect('/admin/orders');
        }

        $itemSql = "SELECT oi.*, p.slug AS product_slug, pi.image_path, s.name as store_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                    LEFT JOIN stores s ON p.store_id = s.id
                    WHERE oi.order_id = {$orderId}";
        $items = $this->orders->query($itemSql);

        $this->view('admin/orders/show.twig', [
            'title' => 'Order Details',
            'order' => $order,
            'items' => $items
        ]);
    }

    public function billing($id)
    {
        if (empty($_SESSION['pos_active'])) {
            \flash('error', 'Please login to POS first');
            return $this->redirect('/admin/pos/login');
        }
        
        $orderId = (int)$id;
        $orderSql = "SELECT * FROM orders WHERE id = {$orderId}";
        $order = $this->orders->query($orderSql);
        $order = $order[0] ?? null;

        if (!$order) {
            \flash('error', 'Order not found');
            return $this->redirect('/admin/orders');
        }

        // Fetch items with product details needed for POS
        $itemSql = "SELECT oi.*, p.name, p.sale_price, p.price, p.gst_percent, p.store_id, p.no_store_stock
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = {$orderId}";
        $items = $this->orders->query($itemSql);

        if (empty($items)) {
            \flash('error', 'Order has no items');
            return $this->redirect('/admin/orders/' . $orderId . '/show');
        }

        // Clear existing cart
        $_SESSION['pos_cart'] = [];
        
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            
            // Use order price if available, or product price
            $price = (float)$item['price']; // from order_items (price at purchase)
            
            $_SESSION['pos_cart'][$pid] = [
                'product_id' => $pid,
                'name' => $item['product_name'] ?? $item['name'],
                'price' => $price,
                'gst_percent' => (float)($item['gst_percent'] ?? 0),
                'discount_percent' => 0.0,
                'quantity' => $qty,
                'no_store_stock' => (int)($item['no_store_stock'] ?? 0) ? 1 : 0,
                'store_id' => (int)($item['store_id'] ?? 0)
            ];
        }

        // Set customer info - Prioritize shipping details from the order
        $userName = $order['shipping_name'] ?? '';
        $userPhone = $order['shipping_phone'] ?? '';
        
        // If empty, try to fetch from user profile
        if ((empty($userName) || empty($userPhone)) && $order['user_id']) {
            $uSql = "SELECT name, phone FROM users WHERE id = " . (int)$order['user_id'];
            $u = $this->orders->query($uSql)[0] ?? null;
            if ($u) {
                if (empty($userName)) $userName = $u['name'];
                if (empty($userPhone)) $userPhone = $u['phone'];
            }
        }
        
        $_SESSION['pos_temp_data'] = [
            'customer_name' => $userName,
            'customer_phone' => $userPhone,
            'staff_id' => 0, // Current admin
            'cash_amount' => '',
            'card_amount' => '',
            'upi_amount' => '',
            'sale_type' => 'Online'
        ];
        
        $_SESSION['pos_online_order_id'] = $orderId;
        
        // Order details loaded - status update will happen upon POS checkout if needed
        // $this->orders->update($orderId, ['order_status' => 'Confirmed']);

        \flash('success', 'Order loaded into POS');
        return $this->redirect('/admin/pos/billing');
    }

    public function updateStatus($id)
    {
        $status = $_POST['order_status'] ?? null;
        $valid = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        if (!in_array($status, $valid, true)) {
            \flash('error', 'Invalid status');
            return $this->redirect('/admin/orders');
        }
        $this->orders->update((int)$id, ['order_status' => $status]);
        \flash('success', 'Order status updated');
        return $this->redirect('/admin/orders');
    }
}
