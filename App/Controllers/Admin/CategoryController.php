<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Category;
use App\Helpers\Validator;

class CategoryController extends BaseController
{
    private Category $categories;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->categories = new Category();
    }

    public function index()
    {
        $items = $this->categories->all('display_order', 'ASC');
        $this->view('admin/categories/index.twig', [
            'title' => 'Categories',
            'categories' => $items
        ]);
    }

    public function create()
    {
        $this->view('admin/categories/create.twig', [
            'title' => 'Add Category',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        $name = \clean($_POST['name'] ?? '');
        $slug = \slugify($_POST['slug'] ?? $name);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // ========== VALIDATION ============
        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/categories/create');
        }

        // ========== IMAGE UPLOAD ============
        $imagePath = null;

        if (!empty($_FILES['image']['name'])) {
            $appCfg = require dirname(__DIR__, 2) . '/config/app.php';
            $basePath = rtrim($appCfg['images']['base_path'] ?? (dirname(__DIR__, 3) . '/images'), DIRECTORY_SEPARATOR);
            $uploadDir = $basePath . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('cat_') . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $baseUrl = '/' . trim($appCfg['images']['base_url'] ?? '/images', '/');
                $imagePath = $baseUrl . '/categories/' . $fileName;
            }
        }

        // ========== INSERT DATA ============
        $this->categories->create([
            'name' => $name,
            'slug' => $slug,
            'display_order' => $displayOrder,
            'is_active' => $isActive,
            'image_path' => $imagePath
        ]);

        \flash('success', 'Category created');
        return $this->redirect('/admin/categories');
    }

    public function edit($id)
    {
        $cat = $this->categories->find((int)$id);
        if (!$cat) return $this->redirect('/admin/categories');

        $this->view('admin/categories/edit.twig', [
            'title' => 'Edit Category',
            'category' => $cat,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function update($id)
    {
        $id = (int)$id;
        $category = $this->categories->find($id);

        if (!$category) {
            \flash('error', 'Category not found');
            return $this->redirect('/admin/categories');
        }

        $name = \clean($_POST['name'] ?? '');
        $slug = \slugify($_POST['slug'] ?? $name);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // ========== VALIDATION ============
        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect("/admin/categories/$id/edit");
        }

        // ========== IMAGE UPLOAD ============
        $imagePath = $category['image_path']; // keep old image

        if (!empty($_FILES['image']['name'])) {
            $appCfg = require dirname(__DIR__, 2) . '/config/app.php';
            $basePath = rtrim($appCfg['images']['base_path'] ?? (dirname(__DIR__, 3) . '/images'), DIRECTORY_SEPARATOR);
            $uploadDir = $basePath . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            if ($imagePath) {
                $abs = \App\Helpers\ImageUploader::toAbsolutePath($imagePath);
                if (is_file($abs)) unlink($abs);
            }

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('cat_') . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $baseUrl = '/' . trim($appCfg['images']['base_url'] ?? '/images', '/');
                $imagePath = $baseUrl . '/categories/' . $fileName;
            }
        }

        // ========== UPDATE ROW ============
        $this->categories->update($id, [
            'name' => $name,
            'slug' => $slug,
            'display_order' => $displayOrder,
            'is_active' => $isActive,
            'image_path' => $imagePath
        ]);

        \flash('success', 'Category updated');
        return $this->redirect('/admin/categories');
    }

    public function delete($id)
    {
        $id = (int)$id;
        $category = $this->categories->find($id);

        if ($category) {
            if (!empty($category['image_path'])) {
                $file = \App\Helpers\ImageUploader::toAbsolutePath($category['image_path']);
                if (is_file($file)) unlink($file);
            }

            $this->categories->delete($id);
        }

        \flash('success', 'Category deleted');
        return $this->redirect('/admin/categories');
    }
}
