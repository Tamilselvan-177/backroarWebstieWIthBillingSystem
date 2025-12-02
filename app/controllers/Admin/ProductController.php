<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\PhoneModel;
use App\Helpers\Validator;

class ProductController extends BaseController
{
    private Product $products;
    private Category $categories;
    private Subcategory $subcategories;
    private Brand $brands;
    private PhoneModel $models;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->products = new Product();
        $this->categories = new Category();
        $this->subcategories = new Subcategory();
        $this->brands = new Brand();
        $this->models = new PhoneModel();
    }

    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)((require __DIR__ . '/../../config/app.php')['per_page'] ?? 24);
        $q = trim($_GET['q'] ?? '');
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $brandId = (int)($_GET['brand_id'] ?? 0);
        $active = $_GET['active'] ?? '';

        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];
        if ($q !== '') {
            $conditions[] = '(p.name LIKE :q OR p.slug LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($categoryId > 0) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($brandId > 0) {
            $conditions[] = 'p.brand_id = :brand_id';
            $params['brand_id'] = $brandId;
        }
        if ($active !== '') {
            $conditions[] = 'p.is_active = :active';
            $params['active'] = $active === '1' ? 1 : 0;
        }
        $where = count($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countSql = "SELECT COUNT(*) as total FROM products p {$where}";
        $stmt = $this->products->query($countSql, $params);
        $total = $stmt[0]['total'] ?? 0;

        $sql = "SELECT p.*, c.name as category_name, b.name as brand_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                {$where}
                ORDER BY p.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $products = $this->products->query($sql, $params);

        $categories = $this->categories->getActive();
        $brands = $this->brands->getActive();

        $this->view('admin/products/index.twig', [
            'title' => 'Products',
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'q' => $q,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'active' => $active
            ],
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'total_pages' => max(1, (int)ceil($total / $perPage))
            ]
        ]);
    }

    public function create()
    {
        $categories = $this->categories->getActive();
        $brands = $this->brands->getActive();
        $subcategories = [];
        $models = [];

        $this->view('admin/products/create.twig', [
            'title' => 'Add Product',
            'categories' => $categories,
            'brands' => $brands,
            'subcategories' => $subcategories,
            'models' => $models,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        $data = [
            'name' => \clean($_POST['name'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'subcategory_id' => (int)($_POST['subcategory_id'] ?? 0) ?: null,
            'brand_id' => (int)($_POST['brand_id'] ?? 0) ?: null,
            'model_id' => (int)($_POST['model_id'] ?? 0) ?: null,
            'price' => (float)($_POST['price'] ?? 0),
            'sale_price' => strlen((string)($_POST['sale_price'] ?? '')) ? (float)$_POST['sale_price'] : null,
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'description' => \clean($_POST['description'] ?? ''),
            'slug' => \slugify($_POST['slug'] ?? ($_POST['name'] ?? ''))
        ];

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->required('category_id', 'Category is required')
                  ->required('price', 'Price is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/products/create');
        }

        $id = $this->products->create($data);
        \flash('success', 'Product created');
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    public function edit($id)
    {
        $product = $this->products->find((int)$id);
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/products');
        }
        $categories = $this->categories->getActive();
        $brands = $this->brands->getActive();
        $subcategories = $product['category_id'] ? $this->subcategories->getByCategory($product['category_id']) : [];
        $models = $product['brand_id'] ? $this->models->getByBrand($product['brand_id']) : [];

        $this->view('admin/products/edit.twig', [
            'title' => 'Edit Product',
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'subcategories' => $subcategories,
            'models' => $models,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function ajaxSubcategories($categoryId)
    {
        $data = $this->subcategories->getByCategory((int)$categoryId);
        return $this->json($data);
    }

    public function ajaxModels($brandId)
    {
        $data = $this->models->getByBrand((int)$brandId);
        return $this->json($data);
    }

    public function toggleActive($id)
    {
        $p = $this->products->find((int)$id);
        if (!$p) return $this->redirect('/admin/products');
        $this->products->update((int)$id, ['is_active' => $p['is_active'] ? 0 : 1]);
        return $this->redirect('/admin/products');
    }

    public function toggleFeatured($id)
    {
        $p = $this->products->find((int)$id);
        if (!$p) return $this->redirect('/admin/products');
        $this->products->update((int)$id, ['is_featured' => $p['is_featured'] ? 0 : 1]);
        return $this->redirect('/admin/products');
    }

    public function update($id)
    {
        $product = $this->products->find((int)$id);
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/products');
        }

        $data = [
            'name' => \clean($_POST['name'] ?? $product['name']),
            'category_id' => (int)($_POST['category_id'] ?? $product['category_id']),
            'subcategory_id' => (int)($_POST['subcategory_id'] ?? $product['subcategory_id']) ?: null,
            'brand_id' => (int)($_POST['brand_id'] ?? $product['brand_id']) ?: null,
            'model_id' => (int)($_POST['model_id'] ?? $product['model_id']) ?: null,
            'price' => (float)($_POST['price'] ?? $product['price']),
            'sale_price' => strlen((string)($_POST['sale_price'] ?? '')) ? (float)$_POST['sale_price'] : null,
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? $product['stock_quantity']),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'description' => \clean($_POST['description'] ?? $product['description']),
            'slug' => \slugify($_POST['slug'] ?? $product['slug'])
        ];

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->required('category_id', 'Category is required')
                  ->required('price', 'Price is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/products/' . (int)$id . '/edit');
        }

        $this->products->update((int)$id, $data);
        \flash('success', 'Product updated');
        return $this->redirect('/admin/products/' . (int)$id . '/edit');
    }

    public function delete($id)
    {
        $product = $this->products->find((int)$id);
        if ($product) {
            $this->products->delete((int)$id);
            \flash('success', 'Product deleted');
        } else {
            \flash('error', 'Product not found');
        }
        return $this->redirect('/admin/products');
    }
}
