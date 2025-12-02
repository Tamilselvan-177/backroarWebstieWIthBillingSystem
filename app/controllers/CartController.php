<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Models\Product;

class CartController extends BaseController
{
    private $cartModel;
    private $productModel;

    public function __construct()
    {
        $this->cartModel = new Cart();
        $this->productModel = new Product();
    }

    /**
     * Show cart page
     */
    public function index()
    {
        // Check if user is logged in
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to view your cart');
            return $this->redirect('/login');
        }

        $userId = \userId();
        $cartItems = $this->cartModel->getCartItems($userId);
        $totals = $this->cartModel->getCartTotals($userId);

        $this->view('cart/index.twig', [
            'title' => 'Shopping Cart - BlackRoar',
            'cart_items' => $cartItems,
            'totals' => $totals
        ]);
    }
public function buyNow()
{
    // Must be logged in
    if (!\isLoggedIn()) {
        return $this->json(['success' => false, 'redirect' => '/login']);
    }

    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity  = (int) ($_POST['quantity'] ?? 1);

    // Load product
    $product = $this->productModel->find($productId);
    if (!$product) {
        return $this->json(['success' => false, 'message' => 'Product not found']);
    }

    // Check stock
    if ($quantity > $product['stock_quantity']) {
        return $this->json([
            'success' => false,
            'message' => 'Only ' . $product['stock_quantity'] . ' items available.'
        ]);
    }

    $userId = \userId();

    // 1️⃣ Clear cart before adding product
    $this->cartModel->clearCart($userId);

    // 2️⃣ Add only this product
    $this->cartModel->addItem($userId, $productId, $quantity);

    // 3️⃣ Redirect to checkout
    return $this->json([
        'success' => true,
        'redirect' => '/checkout'
    ]);
}

    /**
     * Add product to cart
     */
   public function add()
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Check login
    if (!\isLoggedIn()) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'login' => true]);
            return;
        }
        \flash('error', 'Please login to add items to cart');
        return $this->redirect('/login');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = (int)($_POST['quantity'] ?? 1);

    if ($productId <= 0) {
        return $isAjax 
            ? print json_encode(['success' => false, 'message' => 'Invalid product'])
            : $this->redirect('/products');
    }

    $product = $this->productModel->find($productId);

    if (!$product) {
        return $isAjax
            ? print json_encode(['success' => false, 'message' => 'Product not found'])
            : $this->redirect('/products');
    }

    if (!$product['is_active']) {
        return $isAjax
            ? print json_encode(['success' => false, 'message' => 'Product is not available'])
            : $this->redirect('/product/' . $product['slug']);
    }

    if ($quantity > $product['stock_quantity']) {
        return $isAjax
            ? print json_encode(['success' => false, 'message' => 'Only '.$product['stock_quantity'].' left'])
            : $this->redirect('/product/' . $product['slug']);
    }

    // Add to cart
    $userId = \userId();
    $added = $this->cartModel->addItem($userId, $productId, $quantity);

    if ($added) {
        if ($isAjax) {
            echo json_encode(['success' => true]);
            return;
        }

        \flash('success', 'Product added to cart');
    } else {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
            return;
        }

        \flash('error', 'Failed to add product to cart');
    }

    return $this->redirect('/product/' . $product['slug']);
}

    /**
     * Update cart item quantity
     */
    public function update()
    {
        if (!\isLoggedIn()) {
            return $this->json(['success' => false, 'message' => 'Please login']);
        }

        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);

        if ($cartItemId <= 0) {
            return $this->json(['success' => false, 'message' => 'Invalid cart item']);
        }

        // Update quantity
        $updated = $this->cartModel->updateQuantity($cartItemId, $quantity);

        if ($updated) {
            $totals = $this->cartModel->getCartTotals(\userId());
            return $this->json([
                'success' => true,
                'message' => 'Cart updated',
                'totals' => $totals
            ]);
        }

        return $this->json(['success' => false, 'message' => 'Failed to update cart']);
    }

    /**
     * Remove item from cart
     */
    public function remove()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login');
            return $this->redirect('/login');
        }

        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);

        if ($cartItemId <= 0) {
            \flash('error', 'Invalid cart item');
            return $this->redirect('/cart');
        }

        $removed = $this->cartModel->removeItem($cartItemId, \userId());

        if ($removed) {
            \flash('success', 'Item removed from cart');
        } else {
            \flash('error', 'Failed to remove item');
        }

        return $this->redirect('/cart');
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login');
            return $this->redirect('/login');
        }

        $cleared = $this->cartModel->clearCart(\userId());

        if ($cleared) {
            \flash('success', 'Cart cleared');
        } else {
            \flash('error', 'Failed to clear cart');
        }

        return $this->redirect('/cart');
    }

    /**
     * Get cart count (AJAX)
     */
    public function count()
    {
        if (!\isLoggedIn()) {
            return $this->json(['count' => 0]);
        }

        $count = $this->cartModel->getCartCount(\userId());
        return $this->json(['count' => $count]);
    }

    /**
     * Helper: Return JSON response
     */
    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}