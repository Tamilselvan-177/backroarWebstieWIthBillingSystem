<?php

namespace App\Controllers;

use App\Models\Order;

class OrderController extends BaseController
{
    private $orderModel;

    public function __construct()
    {
        $this->orderModel = new Order();
    }

    /**
     * Show order history
     */
    public function history()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to view orders');
            return $this->redirect('/login');
        }

        $page = (int) ($_GET['page'] ?? 1);
        $result = $this->orderModel->getUserOrders(\userId(), $page, 10);

        $this->view('order/history.twig', [
            'title' => 'Order History - BlackRoar',
            'orders' => $result['orders'],
            'pagination' => [
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages']
            ]
        ]);
    }

    /**
     * Show order detail
     */
    public function detail($orderNumber)
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to view order');
            return $this->redirect('/login');
        }

        // Get order
        $order = $this->orderModel->getOrderByNumber($orderNumber, \userId());

        if (!$order) {
            \flash('error', 'Order not found');
            return $this->redirect('/account/orders');
        }

        // Get order items
        $orderItems = $this->orderModel->getOrderItems($order['id']);

        $this->view('order/detail.twig', [
            'title' => 'Order #' . $orderNumber . ' - BlackRoar',
            'order' => $order,
            'order_items' => $orderItems
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel()
    {
        if (!\isLoggedIn()) {
            return $this->redirect('/login');
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);

        if ($orderId <= 0) {
            \flash('error', 'Invalid order');
            return $this->redirect('/account/orders');
        }

        $cancelled = $this->orderModel->cancelOrder($orderId, \userId());

        if ($cancelled) {
            \flash('success', 'Order cancelled successfully');
        } else {
            \flash('error', 'Unable to cancel order. Please contact support.');
        }

        return $this->redirect('/account/orders');
    }

   
}
