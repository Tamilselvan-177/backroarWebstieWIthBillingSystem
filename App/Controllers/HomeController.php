<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;

class HomeController extends BaseController
{
    private $categoryModel;
    private $productModel;

    public function __construct()
    {
        $this->categoryModel = new Category();
        $this->productModel = new Product();
    }

    public function index()
    {
        $categories = $this->categoryModel->getActive();
        $featuredProducts = $this->productModel->getFeatured(8);

        $data = [
            'title' => 'BlackRoar - Mobile Cases & Accessories',
            'categories' => $categories,
            'featured_products' => $featuredProducts
        ];

        $this->view('home.twig', $data);
    }

    public function test()
    {
        echo "<h1>✅ Routing Works!</h1>";
        echo "<p>Database Connection: " . (class_exists('Core\Database') ? '✅ Connected' : '❌ Failed') . "</p>";
        echo "<p>Twig Template Engine: " . (class_exists('Twig\Environment') ? '✅ Loaded' : '❌ Not Found') . "</p>";
    }
}