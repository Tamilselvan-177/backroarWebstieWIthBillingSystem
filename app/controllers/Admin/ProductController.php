<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\PhoneModel;
use App\Models\Store;
use App\Models\StoreStock;
use App\Helpers\Validator;

class ProductController extends BaseController
{
    private Product $products;
    private Category $categories;
    private Subcategory $subcategories;
    private Brand $brands;
    private PhoneModel $models;
    private StoreStock $storeStock;
    private Store $stores;

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
        $this->storeStock = new StoreStock();
        $this->stores = new Store();
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
        $stores = $this->stores->getActive();
        $defaultStoreId = (int)($_SESSION['product_default_store_id'] ?? 0);

        $this->view('admin/products/create.twig', [
            'title' => 'Add Product',
            'categories' => $categories,
            'brands' => $brands,
            'subcategories' => $subcategories,
            'models' => $models,
            'stores' => $stores,
            'default_store_id' => $defaultStoreId,
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
            'store_id' => (int)($_POST['store_id'] ?? 0) ?: null,
            'price' => (float)($_POST['price'] ?? 0),
            'sale_price' => strlen((string)($_POST['sale_price'] ?? '')) ? (float)$_POST['sale_price'] : null,
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'barcode' => trim($_POST['barcode'] ?? '') ?: null,
            'gst_percent' => strlen((string)($_POST['gst_percent'] ?? '')) ? (float)$_POST['gst_percent'] : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'no_store_stock' => isset($_POST['no_store_stock']) ? 1 : 0,
            'description' => \clean($_POST['description'] ?? ''),
            'slug' => \slugify($_POST['slug'] ?? ($_POST['name'] ?? ''))
        ];

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->required('category_id', 'Category is required')
                  ->required('price', 'Price is required')
                  ->custom('gst_percent', function ($v) {
                      if ($v === '' || $v === null) return true;
                      return is_numeric($v) && $v >= 0 && $v <= 28;
                  }, 'GST% must be between 0 and 28');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/products/create');
        }

        $data = $this->products->filterColumns($data);
        $id = $this->products->create($data);
        if (!(int)($data['no_store_stock'] ?? 0) && (int)($data['store_id'] ?? 0) > 0) {
            $this->storeStock->setQuantity((int)$data['store_id'], (int)$id, (int)($data['stock_quantity'] ?? 0));
        }
        if ((int)($data['store_id'] ?? 0) > 0) {
            $_SESSION['product_default_store_id'] = (int)$data['store_id'];
        }
        \flash('success', 'Product created');
        if (isset($_POST['save_and_new'])) {
            return $this->redirect('/admin/products/create');
        }
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
        $stores = $this->stores->getActive();

        $this->view('admin/products/edit.twig', [
            'title' => 'Edit Product',
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'subcategories' => $subcategories,
            'models' => $models,
            'stores' => $stores,
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
            'store_id' => (int)($_POST['store_id'] ?? ($product['store_id'] ?? 0)) ?: null,
            'price' => (float)($_POST['price'] ?? $product['price']),
            'sale_price' => strlen((string)($_POST['sale_price'] ?? '')) ? (float)$_POST['sale_price'] : null,
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? $product['stock_quantity']),
            'barcode' => trim($_POST['barcode'] ?? ($product['barcode'] ?? '')) ?: null,
            'gst_percent' => strlen((string)($_POST['gst_percent'] ?? '')) ? (float)$_POST['gst_percent'] : (float)($product['gst_percent'] ?? 0),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'no_store_stock' => isset($_POST['no_store_stock']) ? 1 : (int)($product['no_store_stock'] ?? 0),
            'description' => \clean($_POST['description'] ?? $product['description']),
            'slug' => \slugify($_POST['slug'] ?? $product['slug'])
        ];

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->required('category_id', 'Category is required')
                  ->required('price', 'Price is required')
                  ->custom('gst_percent', function ($v) {
                      if ($v === '' || $v === null) return true;
                      return is_numeric($v) && $v >= 0 && $v <= 28;
                  }, 'GST% must be between 0 and 28');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/products/' . (int)$id . '/edit');
        }

        $data = $this->products->filterColumns($data);
        $this->products->update((int)$id, $data);
        if (!(int)($data['no_store_stock'] ?? 0) && (int)($data['store_id'] ?? 0) > 0) {
            $this->storeStock->setQuantity((int)$data['store_id'], (int)$id, (int)($data['stock_quantity'] ?? 0));
        }
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
