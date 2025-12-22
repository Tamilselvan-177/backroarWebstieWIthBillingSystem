<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Address;
use App\Models\Order;
use App\Models\PaymentLog;

class PaymentController extends BaseController
{
    private Cart $cart;
    private Address $address;
    private Order $orders;
    private PaymentLog $logs;

    public function __construct()
    {
        $this->cart = new Cart();
        $this->address = new Address();
        $this->orders = new Order();
        $this->logs = new PaymentLog();
    }

    public function razorpayCreate()
    {
        if (!\isLoggedIn()) {
            return $this->json(['success' => false, 'message' => 'Login required'], 401);
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->json(['success' => false, 'message' => 'Session expired'], 419);
        }

        $userId = \userId();
        $addressId = (int)($_POST['address_id'] ?? 0);
        if ($addressId <= 0 || !$this->address->verifyOwnership($addressId, $userId)) {
            return $this->json(['success' => false, 'message' => 'Invalid address'], 422);
        }
        $address = $this->address->find($addressId);

        $items = $this->cart->getCartItems($userId);
        if (empty($items)) {
            return $this->json(['success' => false, 'message' => 'Cart is empty'], 422);
        }
        $validation = $this->cart->validateCart($userId);
        if (!$validation['valid']) {
            return $this->json(['success' => false, 'message' => 'Cart invalid', 'errors' => $validation['errors']], 422);
        }
        $totals = $this->cart->getCartTotals($userId);

        $orderId = $this->orders->createPendingOrder($userId, $items, $address, $totals);
        if (!$orderId) {
            return $this->json(['success' => false, 'message' => 'Unable to create order'], 500);
        }
        $order = $this->orders->getOrderById($orderId, $userId);

        $keyId = getenv('RAZORPAY_KEY_ID') ?: '';
        $keySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';
        if (!$keyId || !$keySecret) {
            return $this->json(['success' => false, 'message' => 'Payment configuration missing'], 500);
        }

        $payload = [
            'amount' => (int)round($order['total_amount'] * 100),
            'currency' => 'INR',
            'receipt' => $order['order_number'],
            'payment_capture' => 1
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 && $code !== 201) {
            return $this->json(['success' => false, 'message' => 'Failed to init payment'], 502);
        }
        $data = json_decode($resp, true);
        $this->logs->log($orderId, 'Razorpay', $data['id'] ?? null, $order['total_amount'], 'Pending', $data);

        return $this->json([
            'success' => true,
            'key_id' => $keyId,
            'razorpay_order_id' => $data['id'],
            'amount' => $payload['amount'],
            'currency' => 'INR',
            'order_number' => $order['order_number']
        ]);
    }

    public function razorpayVerify()
    {
        if (!\isLoggedIn()) {
            return $this->redirect('/login');
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired. Please try again.');
            return $this->redirect('/checkout');
        }

        $orderNumber = $_POST['order_number'] ?? '';
        $paymentId = $_POST['razorpay_payment_id'] ?? '';
        $orderIdRzp = $_POST['razorpay_order_id'] ?? '';
        $signature = $_POST['razorpay_signature'] ?? '';

        $keySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';
        if (!$keySecret) {
            \flash('error', 'Payment configuration missing');
            return $this->redirect('/checkout');
        }

        $expected = hash_hmac('sha256', $orderIdRzp . '|' . $paymentId, $keySecret);
        if ($expected !== $signature) {
            $order = $this->orders->getOrderByNumber($orderNumber, \userId());
            if ($order) {
                $this->logs->log($order['id'], 'Razorpay', $paymentId, $order['total_amount'], 'Failed', $_POST);
                $this->orders->update((int)$order['id'], ['payment_status' => 'Failed']);
            }
            \flash('error', 'Payment verification failed');
            return $this->redirect('/checkout');
        }

        $order = $this->orders->getOrderByNumber($orderNumber, \userId());
        if (!$order) {
            \flash('error', 'Order not found');
            return $this->redirect('/account/orders');
        }

        $this->logs->log($order['id'], 'Razorpay', $paymentId, $order['total_amount'], 'Success', $_POST);
        $this->orders->confirmPaidOrder((int)$order['id'], $paymentId);

        $this->cart->clearCart(\userId());

        return $this->redirect('/checkout/success/' . $order['order_number']);
    }

    public function razorpayWebhook()
    {
        $secret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
        if (!$secret) {
            http_response_code(500);
            echo 'config missing';
            exit;
        }
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        $expected = hash_hmac('sha256', $payload, $secret);
        if ($expected !== $signature) {
            http_response_code(401);
            echo 'invalid signature';
            exit;
        }
        $event = json_decode($payload, true);
        $paymentId = $event['payload']['payment']['entity']['id'] ?? null;
        $amount = ($event['payload']['payment']['entity']['amount'] ?? 0) / 100;
        $orderReceipt = $event['payload']['payment']['entity']['order_id'] ?? null;

        if ($paymentId && $orderReceipt) {
            $order = $this->orders->getOrderByNumber($orderReceipt);
            if ($order) {
                $this->logs->log($order['id'], 'Razorpay', $paymentId, $amount, 'Success', $event);
                if ($order['payment_status'] !== 'Paid') {
                    $this->orders->confirmPaidOrder((int)$order['id'], $paymentId);
                }
            }
        }
        http_response_code(200);
        echo 'ok';
    }
}

