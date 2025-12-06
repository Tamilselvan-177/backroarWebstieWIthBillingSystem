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

        $itemSql = "SELECT oi.*, p.slug AS product_slug, pi.image_path
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                    WHERE oi.order_id = {$orderId}";
        $items = $this->orders->query($itemSql);

        $this->view('admin/orders/show.twig', [
            'title' => 'Order Details',
            'order' => $order,
            'items' => $items
        ]);
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
