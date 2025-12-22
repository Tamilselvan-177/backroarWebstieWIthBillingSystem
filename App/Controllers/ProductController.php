<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use App\Models\Review;

class ProductController extends BaseController
{
    private $productModel;
    private $categoryModel;
    private $wishlistModel;
    private $reviewModel;

    public function __construct()
    {
        $this->productModel = new Product();
        $this->categoryModel = new Category();
        $this->wishlistModel = new Wishlist();
        $this->reviewModel = new Review();
    }

    /**
     * Show all products
     */
    public function index()
    {
        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'sort' => $_GET['sort'] ?? 'p.created_at DESC'
        ];

        $result = $this->productModel->getFiltered($filters, $page, 24);
        $categories = $this->categoryModel->getActive();

        $this->view('product/list.twig', [
            'title' => 'All Products - BlackRoar',
            'products' => $result['products'],
            'categories' => $categories,
            'pagination' => [
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages'],
                'sort' => $filters['sort']
            ],
            'filters' => $filters
        ]);
    }

    /**
     * Show single product
     */
    public function detail($slug)
    {
        $product = $this->productModel->findBySlug($slug);

        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/products');
        }

        // Get product images
        $images = $this->productModel->getImages($product['id']);

        // Get related products
        $relatedProducts = $this->productModel->getRelated(
            $product['id'],
            $product['category_id'],
            4
        );

        $inWishlist = false;
        if (\isLoggedIn()) {
            $inWishlist = $this->wishlistModel->exists(\userId(), (int)$product['id']);
        }

        $reviews = $this->reviewModel->getApprovedForProduct($product['id']);
        $ratingStats = $this->reviewModel->getAverageRating($product['id']);

        $this->view('product/detail.twig', [
            'title' => $product['name'] . ' - BlackRoar',
            'product' => $product,
            'images' => $images,
            'related_products' => $relatedProducts,
            'in_wishlist' => $inWishlist,
            'reviews' => $reviews,
            'rating_stats' => $ratingStats
        ]);
    }

    /**
     * Search products
     */
    public function search()
    {
        $keyword = \clean($_GET['q'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);

        if (empty($keyword)) {
            return $this->redirect('/products');
        }

        $result = $this->productModel->search($keyword, $page, 24);

        $this->view('product/search.twig', [
            'title' => 'Search Results for "' . $keyword . '" - BlackRoar',
            'keyword' => $keyword,
            'products' => $result['products'],
            'pagination' => [
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages']
            ]
        ]);
    }
}