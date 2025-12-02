<?php

namespace App\Controllers;

use App\Models\Review;
use App\Models\Product;

class ReviewController extends BaseController
{
    private Review $reviews;
    private Product $products;

    public function __construct()
    {
        $this->reviews = new Review();
        $this->products = new Product();
    }

    public function store()
    {
        // Check authentication
        if (!\isLoggedIn()) {
            return $this->json(['success' => false, 'message' => 'Please login to review'], 401);
        }

        // Verify CSRF token
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->json(['success' => false, 'message' => 'Session expired'], 419);
        }

        // Sanitize and validate input
        $productId = (int)($_POST['product_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $title = trim(htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'));
        $comment = trim(htmlspecialchars($_POST['comment'] ?? '', ENT_QUOTES, 'UTF-8'));

        // Validate product
        if ($productId <= 0) {
            return $this->json(['success' => false, 'message' => 'Invalid product'], 422);
        }

        try {
            $product = $this->products->find($productId);
            if (!$product) {
                return $this->json(['success' => false, 'message' => 'Product not found'], 404);
            }
        } catch (\Exception $e) {
            \error_log('Product lookup error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error validating product'], 500);
        }

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return $this->json(['success' => false, 'message' => 'Rating must be between 1 and 5'], 422);
        }

        // Validate title
        if (empty($title)) {
            return $this->json(['success' => false, 'message' => 'Review title is required'], 422);
        }
        if (strlen($title) > 200) {
            return $this->json(['success' => false, 'message' => 'Title must be 200 characters or less'], 422);
        }

        // Validate comment
        if (empty($comment)) {
            return $this->json(['success' => false, 'message' => 'Review comment is required'], 422);
        }
        if (strlen($comment) < 10) {
            return $this->json(['success' => false, 'message' => 'Comment must be at least 10 characters'], 422);
        }
        if (strlen($comment) > 2000) {
            return $this->json(['success' => false, 'message' => 'Comment must be 2000 characters or less'], 422);
        }

        // Check for existing review
        try {
            if ($this->reviews->exists(\userId(), $productId)) {
                return $this->json(['success' => false, 'message' => 'You already reviewed this product'], 422);
            }
        } catch (\Exception $e) {
            \error_log('Review existence check error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error checking existing review'], 500);
        }

        // Check if verified purchase
        try {
            $verified = $this->reviews->userCanReview(\userId(), $productId) ? 1 : 0;
        } catch (\Exception $e) {
            \error_log('Verification check error: ' . $e->getMessage());
            $verified = 0; // Default to unverified on error
        }

        // Create review
        try {
            $reviewId = $this->reviews->createReview([
                'user_id' => \userId(),
                'product_id' => $productId,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'status' => 'pending',
                'is_verified_purchase' => $verified,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$reviewId) {
                return $this->json(['success' => false, 'message' => 'Failed to submit review'], 500);
            }

            return $this->json([
                'success' => true,
                'message' => 'Review submitted successfully and is pending approval'
            ]);
        } catch (\Exception $e) {
            \error_log('Review creation error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error submitting review'], 500);
        }
    }
}