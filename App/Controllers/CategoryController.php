<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\PhoneModel;
use App\Models\Product;

class CategoryController extends BaseController
{
    private $categoryModel;
    private $subcategoryModel;
    private $brandModel;
    private $phoneModel;
    private $productModel;

    public function __construct()
    {
        $this->categoryModel = new Category();
        $this->subcategoryModel = new Subcategory();
        $this->brandModel = new Brand();
        $this->phoneModel = new PhoneModel();
        $this->productModel = new Product();
    }

    /**
     * Show all categories
     */
    public function index()
    {
        $categories = $this->categoryModel->getWithProductCount();

        $this->view('category/list.twig', [
            'title' => 'Shop by Categories - BlackRoar',
            'categories' => $categories
        ]);
    }

    /**
     * Show single category with products
     */
    public function show($slug)
    {
        $category = $this->categoryModel->findBySlug($slug);

        if (!$category) {
            return $this->redirect('/categories');
        }

        // Get filters from query string
        $page = (int) ($_GET['page'] ?? 1);
        $filters = [
            'category_id' => $category['id'],
            'subcategory_id' => $_GET['subcategory'] ?? null,
            'brand_id' => $_GET['brand'] ?? null,
            'model_id' => $_GET['model'] ?? null,
            'min_price' => $_GET['min_price'] ?? null,
            'max_price' => $_GET['max_price'] ?? null,
            'sort' => $_GET['sort'] ?? 'p.created_at DESC'
        ];

        // Get products
        $result = $this->productModel->getFiltered($filters, $page, 24);

        // Get subcategories
        $subcategories = $this->subcategoryModel->getWithProductCount($category['id']);

        // Get brands for this category
        $brands = $this->brandModel->getByCategoryWithCount($category['id']);

        // Get models if brand is selected
        $models = [];
        if (!empty($filters['brand_id'])) {
            $models = $this->phoneModel->getByCategoryAndBrand($category['id'], $filters['brand_id']);
        }

        $this->view('category/view.twig', [
            'title' => $category['name'] . ' - BlackRoar',
            'category' => $category,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'models' => $models,
            'products' => $result['products'],
            'pagination' => [
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages'],
                'per_page' => $result['per_page']
            ],
            'filters' => $filters
        ]);
    }
}