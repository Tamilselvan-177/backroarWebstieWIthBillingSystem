<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Models\Coupon;
use App\Helpers\Validator;

class CheckoutController extends BaseController
{
    private $cartModel;
    private $addressModel;
    private $orderModel;
    private $userModel;

    public function __construct()
    {
        $this->cartModel   = new Cart();
        $this->addressModel = new Address();
        $this->orderModel  = new Order();
        $this->userModel   = new User();
    }

    /**
     * Checkout page
     */
    public function index()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to checkout');
            return $this->redirect('/login');
        }

        $userId    = \userId();
        $cartItems = $this->cartModel->getCartItems($userId);

        if (empty($cartItems)) {
            \flash('error', 'Your cart is empty');
            return $this->redirect('/cart');
        }

        $validation = $this->cartModel->validateCart($userId);
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $e) {
                \flash('error', $e);
            }
            return $this->redirect('/cart');
        }

        $addresses      = $this->addressModel->getUserAddresses($userId);
        $defaultAddress = $this->addressModel->getDefaultAddress($userId);
        $totals         = $this->cartModel->getCartTotals($userId);

        $this->view('checkout/index.twig', [
            'title'           => 'Checkout - BlackRoar',
            'cart_items'      => $cartItems,
            'addresses'       => $addresses,
            'default_address' => $defaultAddress,
            'totals'          => $totals,
            'csrf_token'      => csrf_token(),
            'errors'          => $_SESSION['errors'] ?? [],
            'old'             => $_SESSION['old'] ?? [],
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Add address
     */
    public function addAddress()
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if (!\isLoggedIn()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Please login'], 401);
            }
            return $this->redirect('/login');
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Session expired. Please refresh.'], 419);
            }
            \flash('error', 'Session expired.');
            return $this->redirect('/checkout');
        }

        $data = [
            'full_name'     => clean($_POST['full_name']),
            'phone'         => clean($_POST['phone']),
            'address_line1' => clean($_POST['address_line1']),
            'address_line2' => clean($_POST['address_line2']),
            'city'          => clean($_POST['city']),
            'state'         => clean($_POST['state']),
            'pincode'       => clean($_POST['pincode']),
            'is_default'    => isset($_POST['is_default']) ? 1 : 0,
            'user_id'       => userId(),
        ];

        $v = new Validator($data);
        $v->required('full_name')->min('full_name', 3)
          ->required('phone')->phone('phone')
          ->required('address_line1')
          ->required('city')
          ->required('state')
          ->required('pincode')
          ->custom('pincode', fn($v) => preg_match('/^\d{6}$/', $v), "Pincode must be 6 digits");

        if ($v->fails()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'errors' => $v->getErrors()]);
            }
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old']    = $data;
            return $this->redirect('/checkout');
        }

        if ($this->addressModel->addAddress($data)) {
            if ($isAjax) {
                \flash('success', 'Address added'); // Set flash so it shows after reload
                return $this->json(['success' => true, 'message' => 'Address added']);
            }
            \flash('success', 'Address added');
        } else {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Failed to add address']);
            }
            \flash('error', 'Failed to add address');
        }

        return $this->redirect('/checkout');
    }

    /**
     * Apply coupon via AJAX
     */
    public function applyCoupon()
    {
        header("Content-Type: application/json");

        if (!\isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Please login']);
            return;
        }

        if (!\csrf_verify($_POST['_token'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $userId = userId();
        $code   = trim($_POST['coupon_code'] ?? "");

        if ($code === "") {
            echo json_encode(['success' => false, 'message' => 'Coupon code required']);
            return;
        }

        $couponModel = new Coupon();
        $coupon      = $couponModel->findByCode($code);

        if (!$coupon) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon']);
            return;
        }

        $totals = $this->cartModel->calculateTotals($userId, $coupon);

        if ($totals['discount'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Coupon not applicable']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Coupon applied',
            'totals'  => $totals,
        ]);
    }

    /**
     * Place order
     */
   public function placeOrder()
{
    if (!\isLoggedIn()) {
        return $this->redirect('/login');
    }

    $userId     = userId();
    $addressId  = (int)($_POST['address_id'] ?? 0);
    $couponCode = trim($_POST['coupon_code'] ?? '');

    // Validate address
    if ($addressId <= 0 || !$this->addressModel->verifyOwnership($addressId, $userId)) {
        \flash("error", "Invalid address");
        return $this->redirect("/checkout");
    }

    if (!\csrf_verify($_POST['_token'] ?? null)) {
        \flash('error', 'Session expired.');
        return $this->redirect('/checkout');
    }

    $address   = $this->addressModel->find($addressId);
    $cartItems = $this->cartModel->getCartItems($userId);

    if (empty($cartItems)) {
        \flash("error", "Your cart is empty");
        return $this->redirect("/cart");
    }

    // Validate cart
    $validation = $this->cartModel->validateCart($userId);
    if (!$validation['valid']) {
        foreach ($validation['errors'] as $e) {
            \flash('error', $e);
        }
        return $this->redirect('/cart');
    }

    // ----- COUPON LOGIC -----
    $couponModel    = new Coupon();
    $couponId       = null;
    $discountAmount = 0.0;
    $couponUsed     = null;

    if ($couponCode !== '') {
        $couponUsed = $couponModel->findByCode($couponCode);
    }

    if ($couponUsed) {
        // Recalculate with coupon
        $totals = $this->cartModel->calculateTotals($userId, $couponUsed);
        $couponId       = (int)$couponUsed['id'];
        $discountAmount = (float)$totals['discount'];
    } else {
        // No valid coupon
        $totals = $this->cartModel->calculateTotals($userId, null);
        $couponCode = null; // do not save empty code
    }

    // Normalise totals keys expected by createOrder()
    $totals = [
        'subtotal' => (float)$totals['subtotal'],
        'discount' => (float)($totals['discount'] ?? 0),
        'shipping' => (float)($totals['shipping'] ?? 0),
        'total'    => (float)$totals['total'],
    ];

    // Create order with coupon info
    $orderId = $this->orderModel->createOrder(
        $userId,
        $cartItems,
        $address,
        $totals,
        $couponId,
        $couponCode,
        $discountAmount
    );

    if (!$orderId) {
        \flash('error', 'Failed to place order');
        return $this->redirect('/checkout');
    }

    // Record coupon usage
    if ($couponUsed && $couponId) {
        $couponModel->recordUsage($couponId, $userId, $orderId);
    }

    // Clear cart
    $this->cartModel->clearCart($userId);

    $order = $this->orderModel->getOrderById($orderId);

    return $this->redirect('/checkout/success/' . $order['order_number']);
}

/**
 * Order success page
 */
public function success($orderNumber)
{
    if (!\isLoggedIn()) {
        return $this->redirect('/login');
    }

    $order = $this->orderModel->getOrderByNumber($orderNumber, userId());

    if (!$order) {
        \flash('error', 'Order not found');
        return $this->redirect('/account/orders');
    }

    $items = $this->orderModel->getOrderItems($order['id']);

    $this->view('checkout/success.twig', [
        'title'       => 'Order Placed Successfully',
        'order'       => $order,
        'order_items' => $items,
    ]);
}

}
