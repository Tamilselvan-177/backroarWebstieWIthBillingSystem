<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Helpers\Validator;

class CheckoutController extends BaseController
{
    private $cartModel;
    private $addressModel;
    private $orderModel;
    private $userModel;

    public function __construct()
    {
        $this->cartModel = new Cart();
        $this->addressModel = new Address();
        $this->orderModel = new Order();
        $this->userModel = new User();
    }

    /**
     * Show checkout page
     */
    public function index()
    {
        // Check if user is logged in
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to checkout');
            return $this->redirect('/login');
        }

        $userId = \userId();

        // Check if cart is empty
        $cartItems = $this->cartModel->getCartItems($userId);
        if (empty($cartItems)) {
            \flash('error', 'Your cart is empty');
            return $this->redirect('/cart');
        }

        // Validate cart
        $validation = $this->cartModel->validateCart($userId);
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                \flash('error', $error);
            }
            return $this->redirect('/cart');
        }

        // Get addresses
        $addresses = $this->addressModel->getUserAddresses($userId);
        $defaultAddress = $this->addressModel->getDefaultAddress($userId);

        // Get totals
        $totals = $this->cartModel->getCartTotals($userId);

        $customer = $this->userModel->getUserById($userId) ?: [];
        $razorpayEnabled = getenv('RAZORPAY_KEY_ID') && getenv('RAZORPAY_KEY_SECRET');

        $this->view('checkout/index.twig', [
            'title' => 'Checkout - BlackRoar',
            'cart_items' => $cartItems,
            'addresses' => $addresses,
            'default_address' => $defaultAddress,
            'totals' => $totals,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? [],
            'csrf_token' => csrf_token(),
            'razorpay_enabled' => (bool)$razorpayEnabled,
            'customer' => $customer
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Add new address
     */
    public function addAddress()
    {
        if (!\isLoggedIn()) {
            return $this->redirect('/login');
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired. Please refresh and try again.');
            return $this->redirect('/checkout');
        }

        $data = [
            'full_name' => \clean($_POST['full_name'] ?? ''),
            'phone' => \clean($_POST['phone'] ?? ''),
            'address_line1' => \clean($_POST['address_line1'] ?? ''),
            'address_line2' => \clean($_POST['address_line2'] ?? ''),
            'city' => \clean($_POST['city'] ?? ''),
            'state' => \clean($_POST['state'] ?? ''),
            'pincode' => \clean($_POST['pincode'] ?? ''),
            'is_default' => isset($_POST['is_default']) ? 1 : 0
        ];

        // Validation
        $validator = new Validator($data);
        $validator->required('full_name', 'Full name is required')
                  ->min('full_name', 3)
                  ->required('phone', 'Phone is required')
                  ->phone('phone')
                  ->required('address_line1', 'Address is required')
                  ->required('city', 'City is required')
                  ->required('state', 'State is required')
                  ->required('pincode', 'Pincode is required')
                  ->custom('pincode', function($value) {
                      return preg_match('/^\d{6}$/', $value);
                  }, 'Pincode must be 6 digits');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $data;
            return $this->redirect('/checkout');
        }

        // Add user_id
        $data['user_id'] = \userId();

        // Save address
        $addressId = $this->addressModel->addAddress($data);

        if ($addressId) {
            \flash('success', 'Address added successfully');
        } else {
            \flash('error', 'Failed to add address');
        }

        return $this->redirect('/checkout');
    }

    /**
     * Place order
     */
    public function placeOrder()
    {
        if (!\isLoggedIn()) {
            return $this->redirect('/login');
        }

        $userId = \userId();
        $addressId = (int) ($_POST['address_id'] ?? 0);

        // Debug logging
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $debug = [];
        $debug[] = "[placeOrder] time=" . date('Y-m-d H:i:s');
        $debug[] = "user_id={$userId}, address_id={$addressId}";

        // Validate address
        if ($addressId <= 0) {
            \flash('error', 'Please select a delivery address');
            file_put_contents($logDir . '/order_debug.log', implode("\n", $debug) . "\n[error] no_address_selected\n\n", FILE_APPEND);
            return $this->redirect('/checkout');
        }

        // Verify address belongs to user
        if (!$this->addressModel->verifyOwnership($addressId, $userId)) {
            \flash('error', 'Invalid address');
            file_put_contents($logDir . '/order_debug.log', implode("\n", $debug) . "\n[error] address_verify_failed\n\n", FILE_APPEND);
            return $this->redirect('/checkout');
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired. Please refresh and try again.');
            return $this->redirect('/checkout');
        }

        // Get address
        $address = $this->addressModel->find($addressId);

        // Get cart items
        $cartItems = $this->cartModel->getCartItems($userId);
        $debug[] = 'cart_items_count=' . count($cartItems);
        if (empty($cartItems)) {
            \flash('error', 'Your cart is empty');
            file_put_contents($logDir . '/order_debug.log', implode("\n", $debug) . "\n[error] cart_empty\n\n", FILE_APPEND);
            return $this->redirect('/cart');
        }

        // Validate cart
        $validation = $this->cartModel->validateCart($userId);
        $debug[] = 'cart_valid=' . ($validation['valid'] ? '1' : '0');
        if (!$validation['valid']) {
            $debug[] = 'validation_errors=' . implode('|', $validation['errors']);
        }
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                \flash('error', $error);
            }
            file_put_contents($logDir . '/order_debug.log', implode("\n", $debug) . "\n[error] cart_validation_failed\n\n", FILE_APPEND);
            return $this->redirect('/cart');
        }

        // Get totals
        $totals = $this->cartModel->getCartTotals($userId);
        $debug[] = 'totals=' . json_encode($totals);

        // Create order
        $orderId = $this->orderModel->createOrder($userId, $cartItems, $address, $totals);

        $debug[] = 'createOrder_result=' . var_export($orderId, true);
        file_put_contents($logDir . '/order_debug.log', implode("\n", $debug) . "\n\n", FILE_APPEND);

        if ($orderId) {
            // Clear cart
            $this->cartModel->clearCart($userId);

            // Get order
            $order = $this->orderModel->getOrderById($orderId);

            // Redirect to success page
            return $this->redirect('/checkout/success/' . $order['order_number']);
        } else {
            \flash('error', 'Failed to place order. Please try again.');
            file_put_contents($logDir . '/order_debug.log', "[placeOrder] Failed to create order for user {$userId}\n", FILE_APPEND);
            return $this->redirect('/checkout');
        }
    }

    /**
     * Order success page
     */
    public function success($orderNumber)
    {
        if (!\isLoggedIn()) {
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

        $this->view('checkout/success.twig', [
            'title' => 'Order Placed Successfully - BlackRoar',
            'order' => $order,
            'order_items' => $orderItems
        ]);
    }
}