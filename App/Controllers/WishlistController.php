<?php

namespace App\Controllers;

use App\Models\Wishlist;
use App\Models\Product;

class WishlistController extends BaseController
{
    private Wishlist $wishlist;
    private Product $product;

    public function __construct()
    {
        $this->wishlist = new Wishlist();
        $this->product = new Product();
    }

    public function index()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to view your wishlist');
            return $this->redirect('/login');
        }

        $items = $this->wishlist->getUserWishlist(\userId());
        $this->view('wishlist/index.twig', [
            'title' => 'My Wishlist - BlackRoar',
            'items' => $items
        ]);
    }

    public function add()
    {
        if (!\isLoggedIn()) {
            return $this->json(['success' => false, 'message' => 'Please login'], 401);
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->json(['success' => false, 'message' => 'Session expired'], 419);
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId <= 0 || !$this->product->find($productId)) {
            return $this->json(['success' => false, 'message' => 'Invalid product'], 422);
        }

        if ($this->wishlist->exists(\userId(), $productId)) {
            return $this->json(['success' => true, 'message' => 'Already in wishlist']);
        }

        $this->wishlist->add(\userId(), $productId);

        return $this->json(['success' => true, 'message' => 'Added to wishlist']);
    }

    public function remove()
    {
        if (!\isLoggedIn()) {
            return $this->json(['success' => false, 'message' => 'Please login'], 401);
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->json(['success' => false, 'message' => 'Session expired'], 419);
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId <= 0) {
            return $this->json(['success' => false, 'message' => 'Invalid product'], 422);
        }

        $this->wishlist->remove(\userId(), $productId);

        return $this->json(['success' => true, 'message' => 'Removed from wishlist']);
    }

    public function removeForm()
    {
        if (!\isLoggedIn()) {
            \flash('error', 'Please login');
            return $this->redirect('/login');
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/wishlist');
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $this->wishlist->remove(\userId(), $productId);
            \flash('success', 'Item removed from wishlist');
        }

        return $this->redirect('/wishlist');
    }
}

